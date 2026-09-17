-- Tassonomia canonica di primo livello per i filtri della dashboard.
INSERT INTO categories (slug, name) VALUES
  ('ortofrutta',      'Ortofrutta'),
  ('carne',           'Carne'),
  ('pesce',           'Pesce'),
  ('salumi-formaggi', 'Salumi e formaggi'),
  ('latticini',       'Latticini e uova'),
  ('pane-pasticceria','Pane e pasticceria'),
  ('dispensa',        'Dispensa e scatolame'),
  ('pasta-riso',      'Pasta, riso e cereali'),
  ('colazione-dolci', 'Colazione e dolci'),
  ('surgelati',       'Surgelati'),
  ('bevande',         'Bevande'),
  ('alcolici',        'Vini e alcolici'),
  ('igiene-persona',  'Igiene e cura della persona'),
  ('casa-pulizia',    'Casa e pulizia'),
  ('infanzia',        'Prima infanzia'),
  ('animali',         'Animali domestici'),
  ('non-food',        'Non alimentari')
ON DUPLICATE KEY UPDATE name = VALUES(name);
