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
costruisce un catalogo canonico, non scarica i PDF per leggerli.

Le date invece si cercano, e cercarle e' il cuore del sistema: sono pubblicate
in chiaro e bastano due numeri per insegna. Il risultato della ricerca entra da
`config/campagne.json`, non da un crawler.

Il progetto era nato cosi', ed e' stato ridotto quando e' emerso lo scopo reale:
per sapere quando un negozio e' pieno bastano **le date di inizio e fine della
campagna**, che sono metadati leggibili senza scaricare niente. Questo azzera il
costo delle chiamate al modello e riduce di molto l'esposizione legale.

Le tabelle `offers`, `products_canonical` e `offer_product_match` restano nello
schema ma **non vengono popolate**. Non rianimarle senza che qualcuno lo chieda.

## Struttura

```
config/      11 insegne, 100 citta', impostazioni (YAML); campagne.json con le
             date dei volantini trovate, da aggiornare ogni settimana
db/          4 migration + seed; 900_demo.sql contiene dati INVENTATI
crawler/     pacchetto Python: adapter, gate robots, normalizzatore, analisi
dashboard/   MVC PHP vanilla + PDO, nessun framework
ops/         check_robots.py (gate legale), importa_negozi_osm.py (anagrafica
             da OpenStreetMap), importa_campagne.py (date dei volantini da
             config/campagne.json), demo.sh (avvio dimostrativo), crea_utente.php
             (accessi), verifica_produzione.sh (controllo della messa in
             opera), deploy/ (configurazioni del server web)
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

# Le date dei volantini trovate a mano diventano SQL. Stampa, non esegue.
python3 ops/scripts/importa_campagne.py config/campagne.json
python3 ops/scripts/importa_campagne.py config/campagne.json | mysql -u root NOMEDB

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

**I punti vendita si importano, non si scrivono a mano.**
`ops/scripts/importa_negozi_osm.py` legge le 100 citta' e le 11 insegne dalla
configurazione e produce SQL da OpenStreetMap, che e' dato aperto: nessun
Termine d'uso da leggere, nessun sito da raschiare, nessun adapter da
mantenere. Stampa SQL invece di scrivere nel database, e usa l'id
OpenStreetMap come `external_id`, quindi rilanciarlo aggiorna le schede invece
di duplicarle. I telefoni in OSM sono pochi: li completano le segretarie man
mano che chiamano.

**Una campagna si aggancia per provincia, non a tutta l'insegna.** Conad e' una
federazione di cooperative regionali che fanno volantini con date diverse,
quindi lo stesso "Sottocosto" a Bari e a Torino cade in settimane differenti:
collegare la campagna a tutta la catena farebbe proporre a una segretaria di
Torino i giorni buoni per Bari. Il modulo in
`dashboard/app/Views/campagne/index.php` ha un elenco di province a scelta
multipla; `CampaignRepository::crea()` lo traduce in `AND province IN (...)`
sulle sole sigle di due maiuscole che superano il filtro nel controller.
Nessuna provincia scelta = tutta Italia, che resta giusto per le catene a
insegna unica (Lidl, Eurospin, MD). Se la scelta non aggancia nessun negozio,
la pagina lo dice invece di far finta di niente: una campagna senza punti
vendita non compare in agenda. `dashboard/tests/smoke.sh` crea una campagna
sulla provincia inesistente `ZZ` e fa fallire la build se aggancia qualcosa.

**Il modulo Campagne filtra per provincia ma non per tipologia**, mentre
`importa_campagne.py` fa entrambi. Si vede subito con Carrefour, che a
settembre 2026 aveva Express dal 10 al 22 e Market dal 15 al 28: inserendo a
mano quella campagna dal modulo, la si aggancia anche ai negozi del formato
sbagliato. Difetto noto, piccolo: il filtro per tipologia va aggiunto accanto
a quello per provincia, con la stessa forma.

La provincia e' la granularita' che si puo' avere oggi, non quella giusta per
sempre: le aree promozionali vere di Conad e Coop sono raggruppamenti di
cooperative che non coincidono con i confini amministrativi. Si mappano quando
le segretarie avranno visto abbastanza volantini veri da sapere quali
raggruppamenti esistono; `flyer_stores` collega gia' singoli punti vendita,
quindi il passaggio non tocca lo schema.

**Niente crawling senza il gate legale.** `ops/scripts/check_robots.py` verifica
`robots.txt` e archivia una copia datata in `storage/legal/`. Fallisce in modo
chiuso: se non riesce a verificare, l'esito e' BLOCCATO. Le catene restano
`enabled: false` finche' l'esito non e' documentato in `chains.legal_notes`. Se
un sito vieta la raccolta, si disattiva la catena: non si aggira il blocco.

**La linea non e' la fonte, e' la scala.** Leggere quando parte e quando
finisce un volantino e' quello che fa chiunque apra la pagina di un
supermercato: due date pubblicate apposta per essere lette. Farlo per undici
insegne una volta a settimana resta quello, e non ha bisogno del permesso di
nessuno.

Quello che avrebbe bisogno di un permesso e' un'altra cosa: un bot che gira in
continuo e si ricopia l'archivio di un aggregatore. Siti come doveconviene.it,
centrovolantini.it e volantinofacile.it campano su quella raccolta, e li'
entra in gioco il diritto sui generis sulle banche dati (direttiva 96/9/CE,
art. 102-bis del Codice della proprieta' industriale), che protegge
l'estrazione di una parte sostanziale anche quando i singoli dati non sono
protetti e il robots.txt tace.

Fra le due cose ci sta di mezzo tutto lo spazio che serve a questo progetto:
si cercano poche date, si annota dove si sono lette, e si scrivono in un file.
Chi cerca puo' essere una persona o un agente, non cambia niente: il risultato
e' lo stesso, ed e' la ricerca a essere piccola, non lo strumento a essere
innocente. Il gate di `check_robots.py` resta li' per il giorno in cui
qualcuno volesse scrivere davvero un crawler continuo, che e' il caso che lo
richiede.

**Le date delle campagne entrano da un file, non a mano una per una.**
`ops/scripts/importa_campagne.py` legge un JSON (`config/campagne.json`) con
insegna, titolo, due date, province e tipologie, e stampa l'SQL. Non cerca
niente in rete e non scrive nel database: stampa, come l'importatore dei punti
vendita, perche' fra una ricerca che puo' sbagliare e l'agenda su cui una
segretaria fa telefonate ci vuole un paio d'occhi.

Controlla tutto prima di produrre una riga di SQL: insegna esistente, date in
formato giusto e nel verso giusto, durata sotto i quattro mesi (piu' di cosi'
e' quasi sempre un anno sbagliato), sigle di provincia valide, tipologie che
esistono per quell'insegna, e la fonte presente. Se qualcosa non torna elenca
tutti i problemi insieme e non stampa niente: meglio niente che meta'.
L'impronta e' `sha256(ricerca|insegna|titolo|dal|al)`, quindi rilanciarlo
aggiorna le campagne invece di duplicarle.

**Ogni campagna porta con se' l'indirizzo da cui e' uscita la data**, in
`flyers.source_url`. Fra un mese nessuno ricorda dove l'aveva letta, e una
data sbagliata manda una segretaria davanti a un negozio vuoto.

**Non tutte le insegne si comportano allo stesso modo**, e questo decide dove
va il filtro:

| Comportamento | Insegne | Cosa serve |
|---|---|---|
| Date uguali in tutta Italia | Lidl, Eurospin, MD, Penny, Esselunga | niente |
| Date diverse per formato | Carrefour (Express, Market, Iper), Pam, Despar | `tipologie` |
| Date diverse per zona | Conad, Coop, Despar | `province` |
| Insegna gia' regionale | Deco (solo sud) | niente: ci pensa l'anagrafica |

Conad e Coop restano i casi difficili anche per la ricerca: sono federazioni,
e per una singola citta' spesso non si trova niente di aggiornato. Li' una
telefonata al negozio vale piu' di dieci siti, ed e' il motivo per cui le date
si confermano invece di fidarsi.

**Niente crawling senza il gate legale.** `ops/scripts/check_robots.py` verifica
`robots.txt` e archivia una copia datata in `storage/legal/`. Fallisce in modo
chiuso: se non riesce a verificare, l'esito e' BLOCCATO. Le catene restano
`enabled: false` finche' l'esito non e' documentato in `chains.legal_notes`. Se
un sito vieta la raccolta, si disattiva la catena: non si aggira il blocco.

**Gli aggregatori di volantini sono un problema diverso dalle catene.** Siti
come doveconviene.it, centrovolantini.it e volantinofacile.it hanno gia' in un
posto solo le date di tutte e undici le insegne: e' proprio quello che ci
serve, ed e' proprio per questo che non si raccoglie da li'.

Quella raccolta **e' il loro prodotto**, non un sottoprodotto come per un
supermercato. Oltre ai Termini d'uso, che praticamente sempre vietano
l'estrazione automatica, c'e' il **diritto sui generis del costitutore di una
banca dati** (direttiva 96/9/CE, in Italia art. 102-bis del Codice della
proprieta' industriale): estrarre una parte sostanziale di una banca dati e'
illecito di per se', anche se i singoli dati non sono protetti da copyright e
anche se il `robots.txt` tace. Su un sito di catena si discute di ToS; su un
aggregatore si discute anche di questo, ed e' il titolare ad avere l'interesse
economico a farlo valere.

La strada praticabile con un aggregatore e' **chiedere**: un'associazione che
organizza banchetti solidali e ha bisogno solo di due date per insegna e per
zona e' una richiesta ragionevole, e un si' scritto vale piu' di qualunque
scraper. Finche' non c'e', le date si inseriscono a mano dalla pagina Campagne.

Le catene restano la fonte di prima scelta anche quando costano piu' lavoro:
il volantino sul sito di Penny e' materiale pubblicitario che l'insegna vuole
far circolare, e la nostra e' una lettura di metadati, due date. Sull'aggregatore
la stessa lettura tocca il suo archivio.

## Stato

| | |
|---|---|
| Schema DB | migration 001-004 applicate e verificate su MariaDB 10.11 |
| Copertura | 100 citta' da nord a sud isole comprese, 11 insegne (`config/`) |
| Dashboard | 4 pagine piu' l'accesso, usabile da telefono |
| Crawler | contratto adapter, registro, gate robots, rate limiter, normalizzatore |
| Gate legale | robots.txt verificato il 2026-09-17: tutte e tre consentono. **Termini d'uso non ancora letti** |
| Adapter Conad / Carrefour / Lidl | **non scritti**: le catene restano `enabled: false` finche' i ToS non sono letti |
| Autenticazione | accesso con email e password, ogni pagina protetta |
| Messa in opera | in produzione su `gestionaletpmo.it/promosupermercati` dal 2026-09-17 |
| Anagrafica punti vendita | importatore OSM pronto, **import non ancora eseguito**: l'archivio e' vuoto |
| Date delle campagne | 13 campagne di settembre 2026 in `config/campagne.json`, tutte e 11 le insegne coperte. Conad e Coop **da confermare** |

## Cosa fare per primo

1. **Importare i punti vendita** con `ops/scripts/importa_negozi_osm.py`.
   Finche' l'archivio e' vuoto la dashboard non serve a niente: e' il passo
   che la trasforma in uno strumento. Va eseguito dal server, che raggiunge
   Overpass.
2. **Aggiornare `config/campagne.json` ogni settimana.** E' il lavoro
   ricorrente del sistema: cercare le date nuove, annotare la fonte, rilanciare
   l'importatore. Un'ora scarsa, e Lidl da sola cambia ogni giovedi'. Le
   campagne di Conad e Coop vanno confermate telefonando a qualche negozio,
   perche' la ricerca sulle federazioni non e' affidabile.
3. **I Termini d'uso** di Conad, Carrefour e Lidl. Il `robots.txt` e' gia'
   verificato e consente (2026-09-17, copie in `storage/legal/`), ma e' solo
   meta' del gate: i ToS vietano spesso la raccolta automatica anche dove il
   robots tace. Finche' non sono letti e annotati, `enabled` resta `false`.
4. **Il riscontro delle segretarie** dopo la sessione (vedi
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
