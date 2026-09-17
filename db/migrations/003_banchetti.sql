-- OsservatorioPromo - lo scopo reale: organizzare i banchetti solidali
--
-- Le segretarie di Teniamoci per Mano APS devono sapere QUANDO un punto vendita
-- sara' affollato (campagna promozionale attiva) e CHI contattare per chiedere
-- l'autorizzazione al banchetto. Il prezzo delle singole offerte non serve a
-- questo scopo: serve la finestra temporale e il contatto.
--
-- Applicare dopo 002_offer_centric.sql.

SET NAMES utf8mb4;

-- ============================================================
-- 1. CONTATTI DEL PUNTO VENDITA
-- ============================================================
-- Recapiti generali del negozio: numero del punto vendita, email di filiale.
-- Non sono dati personali finche' restano recapiti aziendali.

ALTER TABLE stores
  ADD COLUMN phone          VARCHAR(40)  DEFAULT NULL AFTER address,
  ADD COLUMN email          VARCHAR(160) DEFAULT NULL AFTER phone,
  ADD COLUMN opening_hours  VARCHAR(255) DEFAULT NULL AFTER email,
  ADD COLUMN has_parking    TINYINT(1)   DEFAULT NULL
      COMMENT 'parcheggio ampio: piu transito e piu spazio per il banchetto',
  ADD COLUMN outdoor_space_notes VARCHAR(255) DEFAULT NULL
      COMMENT 'spazio disponibile davanti al negozio, annotato dai volontari';

-- Referenti nominativi (direttore, responsabile di filiale).
-- DATI PERSONALI: raccogliere il minimo indispensabile, solo per la gestione
-- del rapporto con il punto vendita, e cancellare quando non servono piu'.
CREATE TABLE IF NOT EXISTS store_contacts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id   INT UNSIGNED NOT NULL,
  full_name  VARCHAR(160) DEFAULT NULL,
  role       VARCHAR(120) DEFAULT NULL COMMENT 'direttore, vicedirettore, responsabile CSR',
  phone      VARCHAR(40)  DEFAULT NULL,
  email      VARCHAR(160) DEFAULT NULL,
  preferred_channel ENUM('telefono','email','di_persona','pec') DEFAULT NULL,
  notes      VARCHAR(500) DEFAULT NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_contact_store (store_id),
  CONSTRAINT fk_contact_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. RICHIESTE DI AUTORIZZAZIONE
-- ============================================================
-- Il registro di lavoro delle segretarie: chi ho contattato, per quale data,
-- con che esito, quando devo richiamare.

CREATE TABLE IF NOT EXISTS outreach_requests (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id         INT UNSIGNED NOT NULL,
  store_contact_id INT UNSIGNED DEFAULT NULL,
  flyer_id         INT UNSIGNED DEFAULT NULL
                   COMMENT 'campagna promozionale su cui e stata agganciata la richiesta',
  requested_by     VARCHAR(120) NOT NULL COMMENT 'segretaria che ha in carico il contatto',
  target_date_from DATE NOT NULL COMMENT 'prima data utile per il banchetto',
  target_date_to   DATE NOT NULL COMMENT 'ultima data utile',
  channel          ENUM('telefono','email','di_persona','pec') NOT NULL DEFAULT 'telefono',
  status           ENUM('da_contattare','contattato','in_attesa','autorizzato',
                        'rifiutato','rimandato','annullato')
                   NOT NULL DEFAULT 'da_contattare',
  contacted_at     DATETIME DEFAULT NULL,
  response_at      DATETIME DEFAULT NULL,
  authorized_date  DATE     DEFAULT NULL COMMENT 'data concessa dal punto vendita',
  next_follow_up   DATE     DEFAULT NULL COMMENT 'quando richiamare',
  refusal_reason   VARCHAR(255) DEFAULT NULL,
  notes            TEXT DEFAULT NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_or_store (store_id),
  KEY idx_or_status (status, next_follow_up),
  KEY idx_or_target (target_date_from, target_date_to),
  CONSTRAINT fk_or_store   FOREIGN KEY (store_id)         REFERENCES stores(id) ON DELETE CASCADE,
  CONSTRAINT fk_or_contact FOREIGN KEY (store_contact_id) REFERENCES store_contacts(id) ON DELETE SET NULL,
  CONSTRAINT fk_or_flyer   FOREIGN KEY (flyer_id)         REFERENCES flyers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. BANCHETTI SVOLTI
-- ============================================================
-- Chiude il cerchio: registrando la raccolta effettiva si scopre quali punti
-- vendita rendono davvero, e la stima di affluenza smette di essere un'ipotesi.

CREATE TABLE IF NOT EXISTS stall_events (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id             INT UNSIGNED NOT NULL,
  outreach_request_id  INT UNSIGNED DEFAULT NULL,
  event_date           DATE NOT NULL,
  start_time           TIME DEFAULT NULL,
  end_time             TIME DEFAULT NULL,
  volunteers_count     TINYINT UNSIGNED DEFAULT NULL,
  donations_eur        DECIMAL(10,2) DEFAULT NULL,
  items_collected      INT UNSIGNED DEFAULT NULL COMMENT 'per le raccolte in natura',
  promo_active         TINYINT(1) DEFAULT NULL COMMENT 'c era una campagna attiva quel giorno',
  footfall_rating      TINYINT UNSIGNED DEFAULT NULL COMMENT 'affluenza percepita 1-5',
  notes                TEXT DEFAULT NULL,
  created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_se_store_date (store_id, event_date),
  CONSTRAINT fk_se_store   FOREIGN KEY (store_id)            REFERENCES stores(id) ON DELETE CASCADE,
  CONSTRAINT fk_se_request FOREIGN KEY (outreach_request_id) REFERENCES outreach_requests(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. VISTE OPERATIVE
-- ============================================================

-- Le finestre di affluenza: dove e quando c'e' una campagna attiva.
-- Non tocca la tabella offers: per sapere QUANDO il negozio e' pieno bastano
-- le date della campagna, non il contenuto del volantino.
CREATE OR REPLACE VIEW v_finestre_affluenza AS
SELECT
    s.id                               AS store_id,
    s.name                             AS punto_vendita,
    s.address,
    s.city,
    s.province,
    s.postal_code,
    s.phone,
    s.has_parking,
    c.slug                             AS chain_slug,
    c.name                             AS insegna,
    b.name                             AS banner,
    COALESCE(b.format, 'supermercato') AS tipologia,
    f.id                               AS flyer_id,
    f.title                            AS campagna,
    f.valid_from                       AS inizio,
    f.valid_to                         AS fine,
    DATEDIFF(f.valid_to, f.valid_from) + 1 AS durata_giorni,
    YEARWEEK(f.valid_from, 3)          AS iso_yearweek
FROM flyers f
JOIN chains       c  ON c.id  = f.chain_id
JOIN flyer_stores fs ON fs.flyer_id = f.id
JOIN stores       s  ON s.id  = fs.store_id
LEFT JOIN store_banners b ON b.id = s.banner_id
WHERE s.is_active = 1
  AND f.valid_from IS NOT NULL
  AND f.valid_to   IS NOT NULL;

-- L'agenda delle segretarie: campagne future con lo stato del contatto.
-- Un punto vendita senza richiesta aperta compare con stato 'da_contattare'.
CREATE OR REPLACE VIEW v_agenda_contatti AS
SELECT
    w.store_id,
    w.punto_vendita,
    w.city,
    w.province,
    w.chain_slug,
    w.insegna,
    w.tipologia,
    w.phone,
    w.flyer_id,
    w.campagna,
    w.inizio,
    w.fine,
    COALESCE(r.status, 'da_contattare') AS stato_richiesta,
    r.id                                AS outreach_request_id,
    r.requested_by                      AS in_carico_a,
    r.next_follow_up                    AS richiamare_il,
    r.authorized_date                   AS data_autorizzata
FROM v_finestre_affluenza w
LEFT JOIN outreach_requests r
       ON r.store_id = w.store_id
      AND r.target_date_from <= w.fine
      AND r.target_date_to   >= w.inizio
      AND r.status <> 'annullato'
WHERE w.fine >= CURRENT_DATE;

-- Resa storica per punto vendita: quali negozi rendono davvero.
-- Dopo qualche mese sostituisce la stima teorica di affluenza.
CREATE OR REPLACE VIEW v_resa_punti_vendita AS
SELECT
    s.id                              AS store_id,
    s.name                            AS punto_vendita,
    s.city,
    s.province,
    c.name                            AS insegna,
    COALESCE(b.format, 'supermercato') AS tipologia,
    COUNT(e.id)                       AS banchetti_svolti,
    SUM(e.donations_eur)              AS raccolto_totale,
    ROUND(AVG(e.donations_eur), 2)    AS raccolto_medio,
    ROUND(AVG(NULLIF(e.footfall_rating, 0)), 2) AS affluenza_media,
    ROUND(AVG(CASE WHEN e.promo_active = 1 THEN e.donations_eur END), 2) AS medio_con_promo,
    ROUND(AVG(CASE WHEN e.promo_active = 0 THEN e.donations_eur END), 2) AS medio_senza_promo,
    MAX(e.event_date)                 AS ultimo_banchetto
FROM stall_events e
JOIN stores s  ON s.id = e.store_id
JOIN chains c  ON c.id = s.chain_id
LEFT JOIN store_banners b ON b.id = s.banner_id
GROUP BY s.id, s.name, s.city, s.province, c.name, COALESCE(b.format, 'supermercato');
