-- OsservatorioPromo - schema iniziale
-- MariaDB 10.6+ / InnoDB / utf8mb4
-- Applicare con: mysql -u root -p osservatorio_promo < db/migrations/001_init.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- ANAGRAFICHE
-- ============================================================

CREATE TABLE IF NOT EXISTS chains (
  id                SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug              VARCHAR(50)  NOT NULL,
  name              VARCHAR(120) NOT NULL,
  website_url       VARCHAR(255) NOT NULL,
  adapter           VARCHAR(80)  NOT NULL,
  robots_allowed    TINYINT(1)   DEFAULT NULL COMMENT 'NULL = mai verificato',
  robots_checked_at DATETIME     DEFAULT NULL,
  legal_notes       TEXT         DEFAULT NULL COMMENT 'esito lettura robots.txt e ToS',
  is_active         TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'attivabile solo dopo gate legale',
  created_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_chain_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stores (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  chain_id    SMALLINT UNSIGNED NOT NULL,
  external_id VARCHAR(80)  NOT NULL COMMENT 'id del PDV sul sito della catena',
  name        VARCHAR(160) NOT NULL,
  address     VARCHAR(255) DEFAULT NULL,
  postal_code VARCHAR(5)   DEFAULT NULL,
  city        VARCHAR(100) NOT NULL,
  province    CHAR(2)      NOT NULL,
  region      VARCHAR(60)  DEFAULT NULL,
  latitude    DECIMAL(9,6) DEFAULT NULL,
  longitude   DECIMAL(9,6) DEFAULT NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_store (chain_id, external_id),
  KEY idx_geo (province, city),
  KEY idx_cap (postal_code),
  CONSTRAINT fk_store_chain FOREIGN KEY (chain_id) REFERENCES chains(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VOLANTINI
-- ============================================================

CREATE TABLE IF NOT EXISTS flyers (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  chain_id      SMALLINT UNSIGNED NOT NULL,
  external_id   VARCHAR(120)  DEFAULT NULL,
  title         VARCHAR(255)  DEFAULT NULL,
  valid_from    DATE          DEFAULT NULL,
  valid_to      DATE          DEFAULT NULL,
  source_url    VARCHAR(1000) NOT NULL,
  file_type     ENUM('pdf','images') NOT NULL,
  file_path     VARCHAR(512)  NOT NULL,
  file_hash     CHAR(64)      NOT NULL COMMENT 'sha256 del file scaricato: chiave di dedup',
  file_bytes    INT UNSIGNED  DEFAULT NULL,
  page_count    SMALLINT UNSIGNED DEFAULT NULL,
  status        ENUM('downloaded','extracting','extracted','partial','failed','needs_review')
                NOT NULL DEFAULT 'downloaded',
  downloaded_at DATETIME      NOT NULL,
  extracted_at  DATETIME      DEFAULT NULL,
  created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_flyer_hash (chain_id, file_hash),
  KEY idx_validity (chain_id, valid_from, valid_to),
  KEY idx_flyer_status (status),
  CONSTRAINT fk_flyer_chain FOREIGN KEY (chain_id) REFERENCES chains(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lo stesso volantino copre spesso piu' punti vendita.
CREATE TABLE IF NOT EXISTS flyer_stores (
  flyer_id INT UNSIGNED NOT NULL,
  store_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (flyer_id, store_id),
  KEY idx_store_flyer (store_id, flyer_id),
  CONSTRAINT fk_fs_flyer FOREIGN KEY (flyer_id) REFERENCES flyers(id) ON DELETE CASCADE,
  CONSTRAINT fk_fs_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS flyer_pages (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  flyer_id     INT UNSIGNED NOT NULL,
  page_number  SMALLINT UNSIGNED NOT NULL,
  image_path   VARCHAR(512) NOT NULL,
  image_hash   CHAR(64)     NOT NULL,
  width_px     SMALLINT UNSIGNED DEFAULT NULL,
  height_px    SMALLINT UNSIGNED DEFAULT NULL,
  status       ENUM('pending','queued','extracted','failed','needs_review')
               NOT NULL DEFAULT 'pending',
  attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error   TEXT DEFAULT NULL,
  batch_id     INT UNSIGNED DEFAULT NULL,
  extracted_at DATETIME DEFAULT NULL,
  UNIQUE KEY uq_page (flyer_id, page_number),
  KEY idx_page_status (status),
  CONSTRAINT fk_page_flyer FOREIGN KEY (flyer_id) REFERENCES flyers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- OFFERTE
-- ============================================================

CREATE TABLE IF NOT EXISTS offers (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  flyer_page_id    INT UNSIGNED NOT NULL,
  flyer_id         INT UNSIGNED NOT NULL COMMENT 'denormalizzato per le query della dashboard',
  raw_name         VARCHAR(255) NOT NULL COMMENT 'testo come letto dal volantino',
  brand            VARCHAR(120) DEFAULT NULL,
  description      VARCHAR(500) DEFAULT NULL,
  category_raw     VARCHAR(120) DEFAULT NULL,
  quantity_value   DECIMAL(10,3) DEFAULT NULL,
  quantity_unit    ENUM('g','kg','ml','l','pz','conf') DEFAULT NULL,
  price            DECIMAL(10,2) NOT NULL,
  price_original   DECIMAL(10,2) DEFAULT NULL,
  discount_percent DECIMAL(5,2)
      GENERATED ALWAYS AS (
        ROUND((price_original - price) / NULLIF(price_original, 0) * 100, 2)
      ) STORED,
  price_per_kg     DECIMAL(10,4) DEFAULT NULL COMMENT 'calcolato dal normalizzatore',
  price_per_l      DECIMAL(10,4) DEFAULT NULL,
  promo_type       ENUM('sconto','prezzo_fisso','3x2','2x1','sottocosto','fedelta','bundle','altro')
                   NOT NULL DEFAULT 'sconto',
  promo_text       VARCHAR(255) DEFAULT NULL,
  requires_loyalty TINYINT(1) NOT NULL DEFAULT 0,
  valid_from       DATE DEFAULT NULL COMMENT 'puo differire dalle date del volantino',
  valid_to         DATE DEFAULT NULL,
  confidence       DECIMAL(4,3) DEFAULT NULL COMMENT 'confidenza dichiarata dal modello',
  bbox             JSON DEFAULT NULL COMMENT 'posizione sulla pagina, per la revisione',
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_offer_flyer (flyer_id),
  KEY idx_offer_discount (discount_percent),
  KEY idx_offer_ppk (price_per_kg),
  KEY idx_offer_category (category_raw),
  FULLTEXT KEY ft_offer_name (raw_name, brand, description),
  CONSTRAINT fk_offer_page  FOREIGN KEY (flyer_page_id) REFERENCES flyer_pages(id) ON DELETE CASCADE,
  CONSTRAINT fk_offer_flyer FOREIGN KEY (flyer_id)      REFERENCES flyers(id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CATALOGO CANONICO
-- ============================================================

CREATE TABLE IF NOT EXISTS categories (
  id        SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug      VARCHAR(60)  NOT NULL,
  name      VARCHAR(120) NOT NULL,
  parent_id SMALLINT UNSIGNED DEFAULT NULL,
  UNIQUE KEY uq_cat_slug (slug),
  CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products_canonical (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug           VARCHAR(160) NOT NULL,
  name           VARCHAR(200) NOT NULL,
  brand          VARCHAR(120) DEFAULT NULL,
  category_id    SMALLINT UNSIGNED DEFAULT NULL,
  reference_unit ENUM('kg','l','pz') NOT NULL DEFAULT 'kg',
  ean            VARCHAR(14) DEFAULT NULL,
  aliases        JSON DEFAULT NULL,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_prod_slug (slug),
  KEY idx_prod_cat (category_id),
  FULLTEXT KEY ft_prod_name (name, brand),
  CONSTRAINT fk_prod_cat FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_product_match (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  offer_id    BIGINT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED NOT NULL,
  score       DECIMAL(5,4) NOT NULL,
  method      ENUM('exact','fuzzy_sql','fuzzy_python','llm','manual') NOT NULL,
  is_primary  TINYINT(1) NOT NULL DEFAULT 0,
  status      ENUM('auto','pending_review','confirmed','rejected') NOT NULL DEFAULT 'auto',
  reviewed_by VARCHAR(120) DEFAULT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_match (offer_id, product_id),
  KEY idx_match_primary (product_id, is_primary),
  CONSTRAINT fk_m_offer FOREIGN KEY (offer_id)   REFERENCES offers(id) ON DELETE CASCADE,
  CONSTRAINT fk_m_prod  FOREIGN KEY (product_id) REFERENCES products_canonical(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- OSSERVABILITA', COSTI, QUALITA'
-- ============================================================

CREATE TABLE IF NOT EXISTS crawl_log (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id      CHAR(36) NOT NULL COMMENT 'uuid dell esecuzione settimanale',
  chain_id    SMALLINT UNSIGNED DEFAULT NULL,
  store_id    INT UNSIGNED DEFAULT NULL,
  phase       ENUM('discover','download','rasterize') NOT NULL,
  status      ENUM('ok','skipped_duplicate','robots_denied','http_error','timeout','parse_error','blocked')
              NOT NULL,
  url         VARCHAR(1000) DEFAULT NULL,
  http_status SMALLINT UNSIGNED DEFAULT NULL,
  bytes       INT UNSIGNED DEFAULT NULL,
  duration_ms INT UNSIGNED DEFAULT NULL,
  message     TEXT DEFAULT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_run (run_id),
  KEY idx_chain_time (chain_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_batches (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_batch_id VARCHAR(120) NOT NULL,
  purpose           ENUM('extraction','matching') NOT NULL,
  model             VARCHAR(80) NOT NULL,
  requests_count    INT UNSIGNED NOT NULL DEFAULT 0,
  status            ENUM('submitted','in_progress','completed','failed','expired')
                    NOT NULL DEFAULT 'submitted',
  submitted_at      DATETIME NOT NULL,
  completed_at      DATETIME DEFAULT NULL,
  cost_usd          DECIMAL(12,6) DEFAULT NULL,
  UNIQUE KEY uq_batch (provider_batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_calls (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id           INT UNSIGNED DEFAULT NULL,
  custom_id          VARCHAR(120) DEFAULT NULL COMMENT 'correla richiesta batch <-> pagina',
  purpose            ENUM('extraction','matching') NOT NULL,
  model              VARCHAR(80) NOT NULL,
  flyer_page_id      INT UNSIGNED DEFAULT NULL,
  offer_id           BIGINT UNSIGNED DEFAULT NULL,
  input_tokens       INT UNSIGNED DEFAULT 0,
  output_tokens      INT UNSIGNED DEFAULT 0,
  cache_read_tokens  INT UNSIGNED DEFAULT 0,
  cache_write_tokens INT UNSIGNED DEFAULT 0,
  cost_usd           DECIMAL(12,6) DEFAULT NULL COMMENT 'stimato dal listino in config/settings.yaml',
  status             ENUM('ok','invalid_json','schema_error','api_error') NOT NULL,
  created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_call_page (flyer_page_id),
  KEY idx_call_batch (batch_id),
  CONSTRAINT fk_call_batch FOREIGN KEY (batch_id) REFERENCES api_batches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS review_queue (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type ENUM('flyer_page','offer','match') NOT NULL,
  entity_id   BIGINT UNSIGNED NOT NULL,
  reason      VARCHAR(160) NOT NULL COMMENT 'invalid_json, schema_error, low_confidence, ...',
  payload     JSON DEFAULT NULL COMMENT 'output grezzo del modello, per il debug del prompt',
  status      ENUM('open','resolved','discarded') NOT NULL DEFAULT 'open',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME DEFAULT NULL,
  KEY idx_rq (entity_type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
