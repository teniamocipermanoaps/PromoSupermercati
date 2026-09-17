-- DATI DIMOSTRATIVI - non sono punti vendita reali.
-- Servono a provare la dashboard prima di avere dati veri.
-- Non caricare in produzione: `mariadb osservatorio_promo < db/seeds/900_demo.sql`

SET NAMES utf8mb4;

-- ---------- punti vendita ----------
INSERT INTO stores (chain_id, banner_id, external_id, name, address, phone, email,
                    opening_hours, has_parking, postal_code, city, province, region)
SELECT c.id, b.id, v.ext, v.nome, v.via, v.tel, NULL, v.orari, v.parcheggio,
       v.cap, v.citta, v.prov, v.regione
FROM (
  SELECT 'conad' chain,'spazio-conad' banner,'NA-001' ext,'Spazio Conad Fuorigrotta' nome,'Via Giulio Cesare 120' via,'081 5551001' tel,'08:30-21:00' orari,1 parcheggio,'80125' cap,'Napoli' citta,'NA' prov,'Campania' regione UNION ALL
  SELECT 'conad','conad-city','NA-002','Conad City Vomero','Via Luca Giordano 45','081 5551002','08:00-20:30',0,'80129','Napoli','NA','Campania' UNION ALL
  SELECT 'carrefour','carrefour-market','NA-003','Carrefour Market Chiaia','Riviera di Chiaia 210','081 5551003','08:30-21:00',0,'80121','Napoli','NA','Campania' UNION ALL
  SELECT 'lidl','lidl','NA-004','Lidl Poggioreale','Via Nuova Poggioreale 15','081 5551004','08:00-21:00',1,'80143','Napoli','NA','Campania' UNION ALL
  SELECT 'conad','conad-superstore','RM-001','Conad Superstore Tuscolana','Via Tuscolana 850','06 5552001','08:00-21:00',1,'00174','Roma','RM','Lazio' UNION ALL
  SELECT 'carrefour','carrefour-iper','RM-002','Carrefour Iper Laurentina','Via Laurentina 865','06 5552002','09:00-21:30',1,'00143','Roma','RM','Lazio' UNION ALL
  SELECT 'lidl','lidl','RM-003','Lidl Prenestina','Via Prenestina 320','06 5552003','08:00-21:00',1,'00177','Roma','RM','Lazio' UNION ALL
  SELECT 'carrefour','carrefour-express','MI-001','Carrefour Express Navigli','Ripa di Porta Ticinese 55','02 5553001','07:30-22:00',0,'20143','Milano','MI','Lombardia' UNION ALL
  SELECT 'carrefour','carrefour-iper','MI-002','Carrefour Iper Assago','Via Milanofiori 2','02 5553002','09:00-22:00',1,'20090','Milano','MI','Lombardia' UNION ALL
  SELECT 'lidl','lidl','MI-003','Lidl Bicocca','Viale Sarca 210','02 5553003','08:00-21:00',1,'20126','Milano','MI','Lombardia' UNION ALL
  SELECT 'conad','conad','TO-001','Conad Crocetta','Corso Einaudi 30','011 5554001','08:30-20:30',0,'10129','Torino','TO','Piemonte' UNION ALL
  SELECT 'lidl','lidl','TO-002','Lidl Lingotto','Via Nizza 290','011 5554002','08:00-21:00',1,'10126','Torino','TO','Piemonte' UNION ALL
  SELECT 'conad','spazio-conad','BA-001','Spazio Conad Japigia','Via Caldarola 12','080 5555001','08:30-21:00',1,'70126','Bari','BA','Puglia' UNION ALL
  SELECT 'carrefour','carrefour-market','BA-002','Carrefour Market Murat','Via Sparano 88','080 5555002','08:30-21:00',0,'70121','Bari','BA','Puglia'
) v
JOIN chains c ON c.slug = v.chain
LEFT JOIN store_banners b ON b.chain_id = c.id AND b.slug = v.banner;

-- ---------- referenti ----------
INSERT INTO store_contacts (store_id, full_name, role, phone, preferred_channel)
SELECT s.id, v.nome, v.ruolo, v.tel, v.canale
FROM (
  SELECT 'NA-001' ext,'M. Esposito' nome,'direttore' ruolo,'081 5551001' tel,'telefono' canale UNION ALL
  SELECT 'RM-002','L. Bianchi','responsabile CSR','06 5552002','email' UNION ALL
  SELECT 'MI-002','A. Ferrari','direttore','02 5553002','di_persona' UNION ALL
  SELECT 'BA-001','G. Lorusso','vicedirettore','080 5555001','telefono'
) v JOIN stores s ON s.external_id = v.ext;

-- ---------- campagne promozionali ----------
INSERT INTO flyers (chain_id, source_kind, external_id, title, valid_from, valid_to,
                    source_url, file_type, file_path, file_hash, downloaded_at, status)
SELECT c.id, 'structured', v.ext, v.titolo, v.dal, v.al,
       CONCAT('https://esempio.it/volantino/', v.ext), 'json', NULL,
       SHA2(v.ext, 256), NOW(), 'extracted'
FROM (
  SELECT 'conad'     chain,'CONAD-2638' ext,'Bassi e Fissi'        titolo,'2026-09-17' dal,'2026-09-23' al UNION ALL
  SELECT 'conad',         'CONAD-2639','Sottocosto d''autunno',       '2026-09-24','2026-10-07' UNION ALL
  SELECT 'carrefour',     'CRF-4412',  'Ribassi da Carrefour',        '2026-09-18','2026-09-30' UNION ALL
  SELECT 'carrefour',     'CRF-4413',  'Speciale convenienza',        '2026-10-01','2026-10-14' UNION ALL
  SELECT 'lidl',          'LIDL-3801', 'Offerte della settimana',     '2026-09-21','2026-09-27' UNION ALL
  SELECT 'lidl',          'LIDL-3802', 'La settimana italiana',       '2026-09-28','2026-10-04' UNION ALL
  SELECT 'conad',         'CONAD-2635','Spesa di settembre',          '2026-09-03','2026-09-16'
) v JOIN chains c ON c.slug = v.chain;

-- ogni campagna copre tutti i PDV della sua catena
INSERT INTO flyer_stores (flyer_id, store_id)
SELECT f.id, s.id FROM flyers f JOIN stores s ON s.chain_id = f.chain_id;

-- ---------- richieste di autorizzazione ----------
INSERT INTO outreach_requests (store_id, store_contact_id, flyer_id, requested_by,
       target_date_from, target_date_to, channel, status, contacted_at, response_at,
       authorized_date, next_follow_up, refusal_reason, notes)
SELECT s.id,
       (SELECT id FROM store_contacts WHERE store_id = s.id LIMIT 1),
       (SELECT id FROM flyers WHERE external_id = v.campagna),
       v.segretaria, v.dal, v.al, v.canale, v.stato,
       v.contattato, v.risposta, v.autorizzato, v.richiamo, v.motivo, v.note
FROM (
  SELECT 'NA-001' ext,'CONAD-2638' campagna,'Anna R.' segretaria,'2026-09-17' dal,'2026-09-23' al,'telefono' canale,'autorizzato' stato,'2026-09-08 10:15:00' contattato,'2026-09-09 16:00:00' risposta,'2026-09-19' autorizzato,NULL richiamo,NULL motivo,'Concesso spazio esterno vicino ingresso' note UNION ALL
  SELECT 'RM-002','CRF-4412','Giulia M.','2026-09-18','2026-09-30','email','in_attesa','2026-09-11 09:00:00',NULL,NULL,'2026-09-19',NULL,'Inviata richiesta al referente CSR' UNION ALL
  SELECT 'MI-002','CRF-4412','Giulia M.','2026-09-18','2026-09-30','di_persona','contattato','2026-09-14 11:30:00',NULL,NULL,'2026-09-21',NULL,'Il direttore chiede di risentirsi la prossima settimana' UNION ALL
  SELECT 'NA-002','CONAD-2638','Anna R.','2026-09-17','2026-09-23','telefono','rifiutato','2026-09-10 15:00:00','2026-09-10 15:20:00',NULL,NULL,'Spazio esterno insufficiente','Riprovare per lo Spazio Conad' UNION ALL
  SELECT 'BA-001','CONAD-2639','Chiara V.','2026-09-24','2026-10-07','telefono','da_contattare',NULL,NULL,NULL,'2026-09-18',NULL,NULL
) v JOIN stores s ON s.external_id = v.ext;

-- ---------- banchetti svolti ----------
INSERT INTO stall_events (store_id, event_date, start_time, end_time, volunteers_count,
                          donations_eur, promo_active, footfall_rating, notes)
SELECT s.id, v.data, '09:00', '13:00', v.volontari, v.raccolto, v.promo, v.affluenza, v.note
FROM (
  SELECT 'NA-001' ext,'2026-09-05' data,4 volontari,612.50 raccolto,1 promo,5 affluenza,'Sabato di lancio, ottima affluenza' note UNION ALL
  SELECT 'NA-001','2026-08-22',3,318.00,0,3,'Nessuna promozione attiva' UNION ALL
  SELECT 'NA-002','2026-08-29',2,96.50,1,2,'Negozio piccolo, poco transito' UNION ALL
  SELECT 'RM-001','2026-09-12',4,540.00,1,4,NULL UNION ALL
  SELECT 'RM-001','2026-08-08',3,275.00,0,3,NULL UNION ALL
  SELECT 'MI-002','2026-09-05',5,724.00,1,5,'Ipermercato, flusso continuo' UNION ALL
  SELECT 'MI-002','2026-07-18',4,402.00,0,3,NULL UNION ALL
  SELECT 'TO-002','2026-09-06',2,210.00,1,3,NULL UNION ALL
  SELECT 'BA-001','2026-09-12',3,455.00,1,4,NULL
) v JOIN stores s ON s.external_id = v.ext;
