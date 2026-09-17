-- Tipologie di punto vendita per insegna.
-- Tassonomia di partenza: va confermata durante la discovery dei PDV, perche'
-- e' l'adapter a dire quale formato dichiara ogni negozio.

UPDATE chains SET segment = 'discount' WHERE slug = 'lidl';

INSERT INTO store_banners (chain_id, slug, name, format)
SELECT c.id, v.slug, v.name, v.format
FROM chains c
JOIN (
  SELECT 'conad'     AS chain, 'conad-city'        AS slug, 'Conad City'        AS name, 'superette'    AS format UNION ALL
  SELECT 'conad',           'conad',               'Conad',                          'supermercato'  UNION ALL
  SELECT 'conad',           'conad-superstore',    'Conad Superstore',               'superstore'    UNION ALL
  SELECT 'conad',           'spazio-conad',        'Spazio Conad',                   'ipermercato'   UNION ALL
  SELECT 'carrefour',       'carrefour-express',   'Carrefour Express',              'superette'     UNION ALL
  SELECT 'carrefour',       'carrefour-market',    'Carrefour Market',               'supermercato'  UNION ALL
  SELECT 'carrefour',       'carrefour-iper',      'Carrefour Iper',                 'ipermercato'   UNION ALL
  SELECT 'lidl',            'lidl',                'Lidl',                           'discount'
) v ON v.chain = c.slug
ON DUPLICATE KEY UPDATE name = VALUES(name), format = VALUES(format);
