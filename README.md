# OsservatorioPromo

Aiuta le segretarie di **Teniamoci per Mano APS** a organizzare i banchetti
solidali: individua **quando e dove i supermercati saranno piu' affollati**, e
tiene il registro delle richieste di autorizzazione ai punti vendita.

Il ragionamento e' semplice: quando esce un volantino promozionale il negozio
si riempie, e un banchetto in quei giorni raccoglie di piu'. Il sistema serve a
sapere in anticipo quali sono quei giorni, negozio per negozio, e a non perdere
il filo dei contatti gia' avviati.

**MVP**: 3 catene (Conad, Carrefour, Lidl) su 5 citta' (Napoli, Roma, Milano,
Torino, Bari).

## Quello che il sistema NON fa

Non estrae i prezzi delle singole offerte, non confronta i prodotti fra catene,
non costruisce un catalogo canonico. Per sapere quando un negozio sara' pieno
bastano **le date di inizio e fine della campagna promozionale**, che sono
metadati visibili senza scaricare nulla.

Questo evita di scaricare e riprocessare i volantini, azzera il costo delle
chiamate al modello AI e riduce di molto la superficie legale del progetto.
Le tabelle `offers`, `products_canonical` e `offer_product_match` restano nello
schema ma non sono sulla strada critica: si popolano solo se in futuro serve il
dettaglio delle offerte.

## Le tre domande operative

1. **Quando andare.** `v_finestre_affluenza` elenca le campagne attive per ogni
   punto vendita, con date e tipologia di negozio. Il modulo
   `analysis/footfall.py` ordina i giorni dentro quella finestra: il sabato di
   lancio di un ipermercato vale piu' del martedi' centrale di un superette.
2. **Chi contattare.** `store_contacts` tiene i referenti del punto vendita,
   `outreach_requests` registra ogni richiesta di autorizzazione con esito e
   data di richiamo. `v_agenda_contatti` e' l'agenda: campagne in arrivo con
   accanto lo stato del contatto.
3. **Cosa ha funzionato.** `stall_events` registra i banchetti svolti con il
   raccolto e se c'era una promozione attiva. `v_resa_punti_vendita` confronta
   la resa media con e senza promozione, negozio per negozio.

Il terzo punto e' quello che fa maturare il sistema: i pesi di
`analysis/footfall.py` sono un'ipotesi di partenza dichiarata, e vanno
ricalibrati sui dati veri della raccolta appena ce ne sono abbastanza.

## Dati personali

I referenti in `store_contacts` sono dati personali. Raccogliere il minimo che
serve a gestire il rapporto con il punto vendita, e cancellarli quando il
rapporto si chiude. I recapiti generali di filiale (centralino, email del
negozio) stanno su `stores` e non pongono lo stesso problema.

## Stato

| Fase | Stato |
|---|---|
| Schema DB | migration 001-003 applicate e verificate su MariaDB 10.11 |
| Configurazione YAML | catene, citta', tipologie di PDV; selettori da compilare dopo l'ispezione |
| Gate legale (robots.txt + ToS) | **da eseguire** con `ops/scripts/check_robots.py` |
| Interfaccia adapter, registro, rate limiter, gate robots | fatti, coperti da test |
| Stima di affluenza e giorni consigliati | fatto, coperto da test |
| Normalizzatore prezzi | fatto, ora fuori dalla strada critica |
| Adapter per catena | bloccati in attesa del gate legale |
| Dashboard PHP (agenda segretarie) | fatta: agenda, scheda PDV, campagne, banchetti |
| Estrattore AI delle offerte | non previsto nell'MVP |

## Gate legale: si esegue prima di scrivere qualunque adapter

Nessuna catena viene raccolta senza aver verificato `robots.txt` e i Termini
d'uso. Lo script fallisce in modo chiuso: se il robots non e' raggiungibile,
l'esito e' BLOCCATO.

```bash
python3 ops/scripts/check_robots.py
```

Lo script archivia una copia datata di ogni `robots.txt` in `storage/legal/`
come prova documentale. Se una catena risulta NEGATA, resta `enabled: false`
in `config/chains.yaml` e `is_active = 0` in tabella: non si aggira il blocco,
si valuta un accordo con la catena o si esclude dall'MVP.

`robots.txt` non sostituisce i Termini d'uso: vanno letti entrambi e l'esito
va annotato in `chains.legal_notes`.

## Setup database

```bash
mysql -u root -p -e "CREATE DATABASE osservatorio_promo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p osservatorio_promo < db/migrations/001_init.sql
mysql -u root -p osservatorio_promo < db/migrations/002_offer_centric.sql
mysql -u root -p osservatorio_promo < db/seeds/001_chains.sql
mysql -u root -p osservatorio_promo < db/seeds/002_categories.sql
mysql -u root -p osservatorio_promo < db/migrations/003_banchetti.sql
mysql -u root -p osservatorio_promo < db/seeds/003_banners.sql
```

Richiede MariaDB 10.6+ (colonne generate STORED, indici FULLTEXT su InnoDB).

## Configurazione

| File | Contenuto |
|---|---|
| `config/chains.yaml` | catene, adapter, modalita' di discovery, selettori, rate limit |
| `config/cities.yaml` | citta' e province monitorate |
| `config/stores.yaml` | punti vendita per catena |
| `config/settings.yaml` | modelli, soglie di matching, listino per la stima dei costi |

Aggiungere una citta' = una voce in `cities.yaml` piu' i PDV in `stores.yaml`.
Aggiungere una catena = una voce in `chains.yaml` piu' un file in
`crawler/src/osservatorio/adapters/`.

Le chiavi API stanno solo in variabili d'ambiente: copiare `.env.example` in
`.env` (gitignored) e compilarlo.

## Regole di raccolta

- `robots.txt` sempre rispettato, verifica registrata in `crawl_log`.
- Una richiesta ogni 3-5 secondi per dominio, con jitter.
- User-agent identificabile con indirizzo di contatto.
- Nessun aggiramento di captcha, paywall o login.
- Deduplica per hash SHA-256 del file: un volantino gia' estratto non viene
  riprocessato.

## Dashboard

Serve PHP 8.2+ con l'estensione `pdo_mysql`. Nessuna dipendenza esterna, nessun
framework: l'unico punto di accesso al database e' `app/Core/Database.php`.

```bash
php -S 127.0.0.1:8080 -t dashboard/public
```

Le credenziali si leggono da variabili d'ambiente, con `.env` come comodita' di
sviluppo. L'utente applicativo non deve poter modificare lo schema:

```sql
CREATE USER 'osservatorio'@'127.0.0.1' IDENTIFIED BY '...';
GRANT SELECT, INSERT, UPDATE ON osservatorio_promo.* TO 'osservatorio'@'127.0.0.1';
```

Quattro pagine:

| Pagina | A cosa serve |
|---|---|
| Agenda | campagne in arrivo, giorni consigliati, stato del contatto, chi richiamare |
| Punti vendita | elenco e scheda con referenti, campagne, richieste, banchetti e resa |
| Campagne | inserimento manuale delle date: fa funzionare tutto senza crawler |
| Banchetti | registro della raccolta e confronto con/senza promozione |

Per provarla con dati finti: `mariadb osservatorio_promo < db/seeds/900_demo.sql`.

## Sviluppo

```bash
cd crawler
pip install -e '.[dev]'
python3 -m pytest -q
```

Prova end-to-end della dashboard, su un'istanza avviata e con un database usa
e getta (scrive righe reali):

```bash
dashboard/tests/smoke.sh
```

I pesi del punteggio di affluenza esistono in due implementazioni, Python per
l'analisi offline e PHP per la dashboard. `test_footfall_parity.py` confronta
le due: se divergono il test fallisce.

Il normalizzatore, il registro degli adapter, il rate limiter e il gate robots
sono puri o hanno le dipendenze iniettabili: la suite gira offline e senza
database.

## Domande a cui il sistema deve saper rispondere

Sono il criterio di accettazione, prima ancora della dashboard:

- Nelle prossime tre settimane, in quali giorni conviene chiedere un banchetto
  a Napoli, e in quali punti vendita?
- Quali negozi non ho ancora contattato per la campagna che parte giovedi'?
- Chi devo richiamare questa settimana, e per quale punto vendita?
- I banchetti durante le promozioni raccolgono davvero piu' degli altri?
