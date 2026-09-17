-- OsservatorioPromo - revisione: il modello ruota sull'offerta, non sul volantino
--
-- Il documento (PDF o immagini) diventa un artefatto di lavorazione transitorio:
-- si conserva l'hash per la deduplica, i byte si possono cancellare a estrazione
-- completata. Quello che resta e' la risposta a "in quale citta', quando, con
-- quale tipologia di punto vendita".
--
-- Applicare dopo 001_init.sql.

SET NAMES utf8mb4;

-- ============================================================
-- 1. TIPOLOGIA DI PUNTO VENDITA
-- ============================================================
-- In Italia la stessa insegna opera formati diversi con promozioni diverse
-- (Conad City vs Spazio Conad, Carrefour Express vs Iper). La tipologia e'
-- quindi una dimensione di analisi di primo livello, non un attributo minore.

CREATE TABLE IF NOT EXISTS store_banners (
  id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  chain_id   SMALLINT UNSIGNED NOT NULL,
  slug       VARCHAR(60)  NOT NULL,
  name       VARCHAR(120) NOT NULL COMMENT 'sotto-insegna: Conad City, Carrefour Express, ...',
  format     ENUM('ipermercato','superstore','supermercato','discount','superette','cash_and_carry')
             NOT NULL,
  avg_sqm    SMALLINT UNSIGNED DEFAULT NULL COMMENT 'superficie media indicativa',
  UNIQUE KEY uq_banner (chain_id, slug),
  KEY idx_banner_format (format),
  CONSTRAINT fk_banner_chain FOREIGN KEY (chain_id) REFERENCES chains(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE stores
  ADD COLUMN banner_id SMALLINT UNSIGNED DEFAULT NULL AFTER chain_id,
  ADD KEY idx_store_banner (banner_id),
  ADD CONSTRAINT fk_store_banner FOREIGN KEY (banner_id) REFERENCES store_banners(id);

-- Posizionamento commerciale dell'insegna, per confrontare discount e non.
ALTER TABLE chains
  ADD COLUMN segment ENUM('insegna_tradizionale','discount','cash_and_carry')
      NOT NULL DEFAULT 'insegna_tradizionale' AFTER name;

-- ============================================================
-- 2. IL DOCUMENTO DIVENTA TRANSITORIO
-- ============================================================
-- source_kind = 'structured': la catena espone gia' le offerte in forma
-- strutturata, non c'e' nulla da rasterizzare ne' da estrarre con l'AI.
-- In quel caso file_hash contiene lo sha256 del payload normalizzato, cosi'
-- la deduplica funziona in modo uniforme per entrambe le sorgenti.

ALTER TABLE flyers
  ADD COLUMN source_kind ENUM('document','structured') NOT NULL DEFAULT 'document' AFTER chain_id,
  ADD COLUMN purged_at DATETIME DEFAULT NULL
      COMMENT 'quando i byte del documento sono stati cancellati; l hash resta',
  MODIFY COLUMN file_path VARCHAR(512) DEFAULT NULL
      COMMENT 'NULL per sorgenti strutturate o dopo il purge',
  MODIFY COLUMN file_type ENUM('pdf','images','json') NOT NULL DEFAULT 'pdf';

-- Un'offerta da sorgente strutturata non ha una pagina di provenienza.
ALTER TABLE offers
  MODIFY COLUMN flyer_page_id INT UNSIGNED DEFAULT NULL,
  ADD KEY idx_offer_validity (valid_from, valid_to);

-- ============================================================
-- 3. VISTE DI ANALISI
-- ============================================================
-- v_offers_geo e' la vista di lavoro della dashboard: un'offerta per ogni
-- punto vendita in cui e' valida, con citta', provincia, insegna e tipologia
-- gia' risolte. Le date cadono sull'offerta se presenti, altrimenti sulla
-- campagna che la contiene.

CREATE OR REPLACE VIEW v_offers_geo AS
SELECT
    o.id                                AS offer_id,
    o.raw_name,
    o.brand,
    o.category_raw,
    o.price,
    o.price_original,
    o.discount_percent,
    o.price_per_kg,
    o.price_per_l,
    o.promo_type,
    o.requires_loyalty,
    o.confidence,
    COALESCE(o.valid_from, f.valid_from) AS valid_from,
    COALESCE(o.valid_to,   f.valid_to)   AS valid_to,
    c.id                                AS chain_id,
    c.slug                              AS chain_slug,
    c.name                              AS chain_name,
    c.segment                           AS chain_segment,
    b.id                                AS banner_id,
    b.name                              AS banner_name,
    COALESCE(b.format, 'supermercato')  AS store_format,
    s.id                                AS store_id,
    s.city,
    s.province,
    s.region,
    s.postal_code,
    f.id                                AS flyer_id,
    f.source_kind
FROM offers o
JOIN flyers       f  ON f.id  = o.flyer_id
JOIN chains       c  ON c.id  = f.chain_id
JOIN flyer_stores fs ON fs.flyer_id = f.id
JOIN stores       s  ON s.id  = fs.store_id
LEFT JOIN store_banners b ON b.id = s.banner_id
WHERE s.is_active = 1;

-- v_promo_calendar risponde direttamente a "in questa citta', quando ci sono
-- offerte e da parte di quale tipologia di supermercato".

CREATE OR REPLACE VIEW v_promo_calendar AS
SELECT
    province,
    city,
    chain_slug,
    chain_name,
    chain_segment,
    store_format,
    valid_from,
    valid_to,
    YEARWEEK(valid_from, 3)             AS iso_yearweek,
    DATEDIFF(valid_to, valid_from) + 1  AS durata_giorni,
    COUNT(DISTINCT offer_id)            AS offerte,
    COUNT(DISTINCT store_id)            AS punti_vendita,
    ROUND(AVG(discount_percent), 2)     AS sconto_medio,
    MAX(discount_percent)               AS sconto_max
FROM v_offers_geo
WHERE valid_from IS NOT NULL
GROUP BY province, city, chain_slug, chain_name, chain_segment, store_format,
         valid_from, valid_to, YEARWEEK(valid_from, 3),
         DATEDIFF(valid_to, valid_from);

-- v_coverage serve al monitoraggio dell'MVP: dice per quali settimane e citta'
-- la raccolta ha effettivamente prodotto dati, e dove ci sono buchi.

CREATE OR REPLACE VIEW v_coverage AS
SELECT
    province,
    city,
    chain_slug,
    store_format,
    YEARWEEK(valid_from, 3)  AS iso_yearweek,
    COUNT(DISTINCT flyer_id) AS campagne,
    COUNT(DISTINCT offer_id) AS offerte
FROM v_offers_geo
WHERE valid_from IS NOT NULL
GROUP BY province, city, chain_slug, store_format, YEARWEEK(valid_from, 3);
