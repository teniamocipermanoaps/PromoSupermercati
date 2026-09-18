-- OsservatorioPromo - autenticazione della dashboard
--
-- Prima di questa migration chiunque raggiungesse l'indirizzo leggeva i nomi e
-- i telefoni dei referenti in store_contacts e poteva scrivere record. Da qui
-- in avanti ogni pagina richiede l'accesso, tranne quella di accesso stessa.
--
-- Applicare dopo 003_banchetti.sql.

SET NAMES utf8mb4;

-- ============================================================
-- UTENTI DELLA DASHBOARD
-- ============================================================
-- Le volontarie che usano il sistema. Sono DATI PERSONALI: qui sta il minimo
-- che serve a far entrare una persona e a chiamarla per nome, niente altro.
-- Nessun recapito: i numeri di telefono delle volontarie non servono al
-- sistema e quindi non si raccolgono.
--
-- L'utente applicativo del database ha solo SELECT, INSERT, UPDATE: un accesso
-- si revoca con is_active = 0, non cancellando la riga.

CREATE TABLE IF NOT EXISTS users (
  id              SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email           VARCHAR(160) NOT NULL
                  COMMENT 'identificativo di accesso, normalizzato in minuscolo',
  full_name       VARCHAR(160) NOT NULL COMMENT 'nome mostrato nella barra',
  password_hash   VARCHAR(255) NOT NULL
                  COMMENT 'password_hash(): la password in chiaro non entra mai qui. 255 perche un domani argon2id occupa piu di bcrypt',
  role            ENUM('segretaria','amministratrice') NOT NULL DEFAULT 'segretaria'
                  COMMENT 'registrato ma non ancora applicato: oggi ogni utente attivo vede tutto',
  is_active       TINYINT(1) NOT NULL DEFAULT 1
                  COMMENT 'revoca senza DELETE',
  failed_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0
                  COMMENT 'tentativi falliti consecutivi, azzerati dall accesso riuscito',
  locked_until    DATETIME DEFAULT NULL
                  COMMENT 'blocco temporaneo dopo troppi tentativi falliti',
  last_login_at   DATETIME DEFAULT NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nessun utente predefinito, di proposito: l'hash di una password nota dentro
-- un file versionato e' una password nota in produzione. Gli accessi si creano
-- con  php ops/scripts/crea_utente.php
