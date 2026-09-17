# OsservatorioPromo

Repository: `teniamocipermanoaps/PromoSupermercati`

## A cosa serve davvero

Le segretarie di Teniamoci per Mano APS organizzano **banchetti solidali**
davanti ai supermercati. Per farlo devono chiedere l'autorizzazione al punto
vendita, e conviene chiederla per i giorni in cui il negozio sara' affollato:
piu' persone entrano, piu' si raccoglie.

Il sistema risponde a tre domande:

1. **Quando andare.** Quando parte e finisce una campagna promozionale, negozio
   per negozio, e quali giorni di quella finestra sono i migliori.
2. **Chi contattare.** Recapiti e referenti del punto vendita, con il registro
   delle richieste di autorizzazione: esito, motivo del rifiuto, quando
   richiamare.
3. **Cosa ha funzionato.** Quanto si e' raccolto a ogni banchetto, con e senza
   promozione attiva.

## Cosa il sistema NON fa, di proposito

**Non estrae i prezzi dei volantini.** Non confronta prodotti fra catene, non
costruisce un catalogo canonico, non usa l'AI per leggere i PDF.

Il progetto era nato cosi', ed e' stato ridotto quando e' emerso lo scopo reale:
per sapere quando un negozio e' pieno bastano **le date di inizio e fine della
campagna**, che sono metadati leggibili senza scaricare niente. Questo azzera il
costo delle chiamate al modello e riduce di molto l'esposizione legale.

Le tabelle `offers`, `products_canonical` e `offer_product_match` restano nello
schema ma **non vengono popolate**. Non rianimarle senza che qualcuno lo chieda.

## Struttura

```
config/      catene, citta', punti vendita, impostazioni (YAML)
db/          3 migration + seed; 900_demo.sql contiene dati INVENTATI
crawler/     pacchetto Python: adapter, gate robots, normalizzatore, analisi
dashboard/   MVC PHP vanilla + PDO, nessun framework
ops/         check_robots.py (gate legale) e demo.sh (avvio dimostrativo)
docs/        traccia per la sessione con le segretarie
```

## Avvio

```bash
ops/scripts/demo.sh                       # database + dati finti + server
cd crawler && python3 -m pytest -q        # 63 test
dashboard/tests/smoke.sh                  # prova end-to-end (scrive righe vere)
```

Richiede MariaDB 10.6+ (colonne generate STORED, FULLTEXT su InnoDB) e PHP 8.2+
con `pdo_mysql`.

## Convenzioni

- **Tutto in italiano**: nomi di funzioni, variabili, commenti, messaggi,
  interfaccia. Le colonne del database restano in inglese dove lo erano gia'.
- **Dashboard senza framework.** Router, viste e repository scritti a mano.
  `dashboard/app/Core/Database.php` e' l'unico punto di accesso al database:
  se un giorno si cambia impianto, si riscrive quel file e basta.
- **Sempre statement preparati**, escaping in ogni vista, token CSRF su ogni
  POST, validazione lato server anche dove il modulo ha gia' i vincoli HTML.
- **L'utente applicativo ha solo SELECT, INSERT, UPDATE.** Non deve poter
  modificare lo schema ne' cancellare righe.
- **I segreti solo in variabili d'ambiente.** `.env` e' escluso dal
  versionamento; `.env.example` elenca i campi vuoti.

## Punti delicati

**Il punteggio di affluenza esiste in due implementazioni**, Python
(`crawler/src/osservatorio/analysis/footfall.py`) e PHP
(`dashboard/app/Support/Footfall.php`). I pesi vanno cambiati in entrambe:
`crawler/tests/test_footfall_parity.py` fa fallire la build se divergono.
Attenzione all'arrotondamento: Python arrotonda al pari, PHP per eccesso, per
questo il codice Python usa `decimal` con `ROUND_HALF_UP`.

**I pesi del punteggio sono un'ipotesi dichiarata, non una misura.** Vanno
ricalibrati sui dati veri di `stall_events` quando ce ne saranno abbastanza:
la vista `v_resa_punti_vendita` confronta la raccolta con e senza promozione.

**La tipologia di punto vendita e' una dimensione di primo livello.** La stessa
insegna opera formati con promozioni diverse (Conad City contro Spazio Conad,
Carrefour Express contro Iper). Confrontare le insegne ignorando il formato da'
numeri senza significato.

**Niente crawling senza il gate legale.** `ops/scripts/check_robots.py` verifica
`robots.txt` e archivia una copia datata in `storage/legal/`. Fallisce in modo
chiuso: se non riesce a verificare, l'esito e' BLOCCATO. Le catene restano
`enabled: false` finche' l'esito non e' documentato in `chains.legal_notes`. Se
un sito vieta la raccolta, si disattiva la catena: non si aggira il blocco.

## Stato

| | |
|---|---|
| Schema DB | migration 001-003 applicate e verificate su MariaDB 10.11 |
| Dashboard | 4 pagine funzionanti, usabile da telefono |
| Crawler | contratto adapter, registro, gate robots, rate limiter, normalizzatore |
| Adapter Conad / Carrefour / Lidl | **non scritti**, in attesa del gate legale |
| Autenticazione | **assente** |

## Cosa fare per primo

1. **L'autenticazione.** Oggi chiunque raggiunga l'indirizzo legge nomi e
   telefoni dei referenti e puo' scrivere record. E' il blocco piu' serio:
   la dashboard non puo' uscire da un computer solo finche' non c'e'.
2. **Il gate legale** su Conad, Carrefour e Lidl, che sblocca gli adapter.
3. **Il riscontro delle segretarie** dopo la sessione (vedi
   `docs/sessione-segretarie.md`): attesi campi mancanti nella scheda del punto
   vendita e stati della richiesta diversi dai sei ipotizzati.

## Dati personali

I referenti in `store_contacts` sono dati personali: raccogliere il minimo che
serve a gestire il rapporto con il punto vendita e cancellarli quando il
rapporto si chiude. I recapiti generali di filiale stanno su `stores` e non
pongono lo stesso problema. Il database non va mai committato.
