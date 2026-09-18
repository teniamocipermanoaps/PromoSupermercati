-- Insegne monitorate e loro tipologie di punto vendita.
--
-- Generato da config/chains.yaml: se cambi quello, rigenera questo con
--   python3 ops/scripts/genera_seed_insegne.py > db/seeds/004_insegne.sql
--
-- is_active = 0 su tutte: nessuna catena e' raccoglibile finche' il gate
-- legale (robots.txt + Termini d'uso) non e' superato e documentato.
-- Rieseguibile senza danno.

INSERT INTO chains (slug, name, website_url, adapter, segment, is_active, legal_notes) VALUES
  ('conad', 'Conad', 'https://www.conad.it', 'conad', 'insegna_tradizionale', 0, 'robots.txt VERIFICATO 2026-09-17 su https://www.conad.it/ricerca-negozi: consentito (copia in storage/legal/). ToS NON ancora letti: enabled resta false finche'' non lo sono.'),
  ('carrefour', 'Carrefour Italia', 'https://www.carrefour.it', 'carrefour', 'insegna_tradizionale', 0, 'robots.txt VERIFICATO 2026-09-17 su https://www.carrefour.it/volantino: consentito (copia in storage/legal/). ToS NON ancora letti: enabled resta false finche'' non lo sono.'),
  ('lidl', 'Lidl Italia', 'https://www.lidl.it', 'lidl', 'discount', 0, 'robots.txt VERIFICATO 2026-09-17 su https://www.lidl.it/c/volantino/s10005610: consentito (copia in storage/legal/). ToS NON ancora letti: enabled resta false finche'' non lo sono.'),
  ('deco', 'Deco', 'https://www.decoitalia.it', 'deco', 'insegna_tradizionale', 0, 'robots.txt e ToS non ancora verificati'),
  ('coop', 'Coop', 'https://www.e-coop.it', 'coop', 'insegna_tradizionale', 0, 'robots.txt e ToS non ancora verificati'),
  ('esselunga', 'Esselunga', 'https://www.esselunga.it', 'esselunga', 'insegna_tradizionale', 0, 'robots.txt e ToS non ancora verificati'),
  ('eurospin', 'Eurospin', 'https://www.eurospin.it', 'eurospin', 'discount', 0, 'robots.txt e ToS non ancora verificati'),
  ('md', 'MD', 'https://www.mdspa.it', 'md', 'discount', 0, 'robots.txt e ToS non ancora verificati'),
  ('penny', 'Penny Market', 'https://www.pennymarket.it', 'penny', 'discount', 0, 'robots.txt e ToS non ancora verificati'),
  ('despar', 'Despar Italia', 'https://www.despar.it', 'despar', 'insegna_tradizionale', 0, 'robots.txt e ToS non ancora verificati'),
  ('pam', 'Pam Panorama', 'https://www.pampanorama.it', 'pam', 'insegna_tradizionale', 0, 'robots.txt e ToS non ancora verificati')
ON DUPLICATE KEY UPDATE
  name = VALUES(name), website_url = VALUES(website_url),
  adapter = VALUES(adapter), segment = VALUES(segment),
  legal_notes = VALUES(legal_notes);

-- Tipologie: la stessa insegna opera formati con promozioni diverse, e
-- confrontarle ignorando il formato da' numeri senza significato.

INSERT INTO store_banners (chain_id, slug, name, format)
SELECT c.id, v.slug, v.name, v.format
FROM chains c
JOIN (
  SELECT 'conad' AS chain, 'conad-city' AS slug, 'Conad City' AS name, 'superette' AS format UNION ALL
  SELECT 'conad', 'conad', 'Conad', 'supermercato' UNION ALL
  SELECT 'conad', 'conad-superstore', 'Conad Superstore', 'superstore' UNION ALL
  SELECT 'conad', 'spazio-conad', 'Spazio Conad', 'ipermercato' UNION ALL
  SELECT 'carrefour', 'carrefour-express', 'Carrefour Express', 'superette' UNION ALL
  SELECT 'carrefour', 'carrefour-market', 'Carrefour Market', 'supermercato' UNION ALL
  SELECT 'carrefour', 'carrefour-iper', 'Carrefour Iper', 'ipermercato' UNION ALL
  SELECT 'lidl', 'lidl', 'Lidl', 'discount' UNION ALL
  SELECT 'deco', 'deco', 'Deco', 'supermercato' UNION ALL
  SELECT 'deco', 'deco-maxi', 'Deco Maxi', 'superstore' UNION ALL
  SELECT 'coop', 'incoop', 'InCoop', 'superette' UNION ALL
  SELECT 'coop', 'coop', 'Coop', 'supermercato' UNION ALL
  SELECT 'coop', 'coop-superstore', 'Coop Superstore', 'superstore' UNION ALL
  SELECT 'coop', 'ipercoop', 'Ipercoop', 'ipermercato' UNION ALL
  SELECT 'esselunga', 'la-esse', 'La Esse', 'superette' UNION ALL
  SELECT 'esselunga', 'esselunga', 'Esselunga', 'supermercato' UNION ALL
  SELECT 'esselunga', 'esselunga-superstore', 'Esselunga Superstore', 'superstore' UNION ALL
  SELECT 'eurospin', 'eurospin', 'Eurospin', 'discount' UNION ALL
  SELECT 'md', 'md', 'MD', 'discount' UNION ALL
  SELECT 'penny', 'penny', 'Penny Market', 'discount' UNION ALL
  SELECT 'despar', 'despar', 'Despar', 'supermercato' UNION ALL
  SELECT 'despar', 'eurospar', 'Eurospar', 'superstore' UNION ALL
  SELECT 'despar', 'interspar', 'Interspar', 'ipermercato' UNION ALL
  SELECT 'pam', 'pam-local', 'Pam local', 'superette' UNION ALL
  SELECT 'pam', 'pam', 'Pam', 'supermercato' UNION ALL
  SELECT 'pam', 'panorama', 'Panorama', 'ipermercato'
) v ON v.chain = c.slug
ON DUPLICATE KEY UPDATE name = VALUES(name), format = VALUES(format);
