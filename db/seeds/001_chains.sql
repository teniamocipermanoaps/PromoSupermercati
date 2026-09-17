-- Catene dell'MVP. is_active = 0: nessuna catena e' raccoglibile finche' il
-- gate legale (robots.txt + Termini d'uso) non e' superato e documentato.
INSERT INTO chains (slug, name, website_url, adapter, is_active, legal_notes) VALUES
  ('conad',     'Conad',           'https://www.conad.it',     'conad',     0, 'robots.txt e ToS da verificare'),
  ('carrefour', 'Carrefour Italia','https://www.carrefour.it',  'carrefour', 0, 'robots.txt e ToS da verificare'),
  ('lidl',      'Lidl Italia',     'https://www.lidl.it',       'lidl',      0, 'robots.txt e ToS da verificare')
ON DUPLICATE KEY UPDATE name = VALUES(name), website_url = VALUES(website_url);
