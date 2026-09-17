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
db/          4 migration + seed; 900_demo.sql contiene dati INVENTATI
crawler/     pacchetto Python: adapter, gate robots, normalizzatore, analisi
dashboard/   MVC PHP vanilla + PDO, nessun framework
ops/         check_robots.py (gate legale), demo.sh (avvio dimostrativo),
             crea_utente.php (accessi), verifica_produzione.sh (controllo
             della messa in opera), deploy/ (configurazioni del server web)
docs/        traccia per le segretarie e messa in opera
```

## Avvio

```bash
ops/scripts/demo.sh                       # database + dati finti + accesso + server
cd crawler && python3 -m pytest -q        # 63 test

# La dashboard e' chiusa: smoke.sh ha bisogno di credenziali valide.
# demo.sh ne stampa una coppia usa e getta a ogni avvio.
SMOKE_EMAIL=... SMOKE_PASSWORD=... dashboard/tests/smoke.sh

php dashboard/tests/percorsi_test.php     # 40 prove, senza database ne' server

# Con SMOKE_DB prova anche che la revoca chiuda una sessione gia' aperta.
SMOKE_DB=osservatorio_promo_demo SMOKE_EMAIL=... SMOKE_PASSWORD=... \
  dashboard/tests/smoke.sh
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

**Il cancello dell'autenticazione e' chiuso per definizione.** L'elenco
`ROTTE_PUBBLICHE` in `dashboard/public/index.php` ha una sola voce, `/accesso`,
e il controllo sta li' dentro, prima del router: una rotta nuova nasce protetta
perche' non e' in quell'elenco, non perche' qualcuno si e' ricordato di
proteggerla. Non spostare il controllo dentro i controller, e non allungare
l'elenco senza una ragione scritta.

Subito prima del cancello, **ogni richiesta rilegge l'utente dal database**.
Fidarsi della sola sessione significa che `is_active = 0` vale solo dal
prossimo accesso: chi e' gia' dentro resta dentro, e le sessioni sono file su
disco che dal database non si possono cancellare. In sessione sta anche
un'impronta dell'hash della password, cosi' reimpostarla chiude le sessioni
aperte con quella vecchia. `dashboard/tests/smoke.sh` con `SMOKE_DB` fa
fallire la build se questo controllo sparisce.

**I percorsi passano tutti da `App\Core\Percorsi`.** La dashboard puo' stare
alla radice di un dominio o in una sottocartella
(`gestionaletpmo.it/promosupermercati`), e lo dice `APP_BASE_PATH` nel `.env`.
Dentro l'applicazione i percorsi restano quelli di sempre (`/campagne`): il
prefisso si aggiunge solo sul confine, con `Percorsi::a()` quando si scrive un
indirizzo in una pagina o in un `Location`, e si toglie con
`Percorsi::interno()` quando ne arriva uno dal browser. Scrivere `href="/..."`
a mano in una vista rompe l'installazione in sottocartella, e il sintomo e' un
giro di redirect a vuoto, non un errore leggibile.

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
| Schema DB | migration 001-004 applicate e verificate su MariaDB 10.11 |
| Dashboard | 4 pagine piu' l'accesso, usabile da telefono |
| Crawler | contratto adapter, registro, gate robots, rate limiter, normalizzatore |
| Gate legale | robots.txt verificato il 2026-09-17: tutte e tre consentono. **Termini d'uso non ancora letti** |
| Adapter Conad / Carrefour / Lidl | **non scritti**: le catene restano `enabled: false` finche' i ToS non sono letti |
| Autenticazione | accesso con email e password, ogni pagina protetta |
| Messa in opera | `docs/messa-in-opera.md`, configurazioni in `ops/deploy/`, controllo con `verifica_produzione.sh` |

## Cosa fare per primo

1. **Mettere online la dashboard** su `gestionaletpmo.it/promosupermercati`,
   seguendo `docs/messa-in-opera.md`. Il certificato non e' facoltativo: su
   HTTP la password della segretaria viaggia in chiaro.
2. **I Termini d'uso** di Conad, Carrefour e Lidl. Il `robots.txt` e' gia'
   verificato e consente (2026-09-17, copie in `storage/legal/`), ma e' solo
   meta' del gate: i ToS vietano spesso la raccolta automatica anche dove il
   robots tace. Finche' non sono letti e annotati, `enabled` resta `false`.
3. **Il riscontro delle segretarie** dopo la sessione (vedi
   `docs/sessione-segretarie.md`): attesi campi mancanti nella scheda del punto
   vendita e stati della richiesta diversi dai sei ipotizzati.

## Dati personali

I referenti in `store_contacts` sono dati personali: raccogliere il minimo che
serve a gestire il rapporto con il punto vendita e cancellarli quando il
rapporto si chiude. I recapiti generali di filiale stanno su `stores` e non
pongono lo stesso problema. Il database non va mai committato.

Anche le volontarie in `users` sono dati personali: li' sta solo il minimo per
far entrare una persona e chiamarla per nome, nessun recapito. Un accesso che
non serve piu' si mette a `is_active = 0`.
