# Mettere la dashboard online

Finche' la dashboard gira su un computer solo, basta `ops/scripts/demo.sh`.
Questo documento serve per l'altra cosa: darne l'indirizzo alle segretarie.

Cambia tutto, perche' cambia chi puo' bussare alla porta. La dashboard contiene
nomi e telefoni dei referenti dei punti vendita, che sono dati personali: da
qui in avanti si risponde anche di come sono custoditi.

> **Prima di cominciare.** `ops/scripts/demo.sh` **cancella e ricrea** il
> database indicato, e carica dati inventati. Non eseguirlo mai sul server
> vero, nemmeno per provare.

## Cosa serve

| | |
|---|---|
| Un server | Linux, con accesso amministrativo |
| PHP | 8.2 o superiore, con `pdo_mysql` |
| MariaDB | 10.6 o superiore |
| Un dominio | che punta all'indirizzo IP del server |
| Un certificato | Let's Encrypt va benissimo, e' gratuito |

Il dominio serve davvero: senza, non c'e' certificato, e senza certificato le
password delle segretarie viaggiano leggibili su ogni rete che attraversano.

## 1. Il codice sul server

```bash
sudo mkdir -p /srv/osservatoriopromo
sudo chown "$USER" /srv/osservatoriopromo
git clone https://github.com/teniamocipermanoaps/PromoSupermercati.git /srv/osservatoriopromo
```

## 2. Il database e l'utente applicativo

L'utente con cui si collega la dashboard ha **solo tre privilegi**. Non e' un
dettaglio formale: se un giorno qualcuno riuscisse a infilare una query, con
questi privilegi non puo' cancellare il registro dei contatti ne' modificare lo
schema.

```sql
CREATE DATABASE osservatorio_promo
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'osservatorio'@'127.0.0.1'
  IDENTIFIED BY 'una-password-lunga-e-casuale';

GRANT SELECT, INSERT, UPDATE ON osservatorio_promo.* TO 'osservatorio'@'127.0.0.1';
FLUSH PRIVILEGES;
```

La password si genera, non si inventa:

```bash
head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n'; echo
```

## 3. Le migration

Da eseguire con un utente amministrativo, non con quello applicativo (che
infatti non puo' creare tabelle).

```bash
cd /srv/osservatoriopromo
for f in db/migrations/*.sql; do
  echo "$f"; sudo mariadb osservatorio_promo < "$f"
done
sudo mariadb osservatorio_promo < db/seeds/001_chains.sql
sudo mariadb osservatorio_promo < db/seeds/002_categories.sql
sudo mariadb osservatorio_promo < db/seeds/003_banners.sql
```

**Non caricare `db/seeds/900_demo.sql`**: contiene punti vendita inventati.
Se finisce in produzione, le segretarie telefonano a negozi che non esistono.

## 4. Il file .env

```bash
cd /srv/osservatoriopromo
cp .env.example .env
nano .env          # DB_USER, DB_PASSWORD e APP_BASE_PATH
chmod 600 .env
sudo chown www-data:www-data .env
```

`APP_BASE_PATH` dice all'applicazione da dove pende dentro il dominio:

| Indirizzo | Valore |
|---|---|
| `https://osservatorio.esempio.it/` | `APP_BASE_PATH=` (vuoto) |
| `https://gestionaletpmo.it/promosupermercati` | `APP_BASE_PATH=/promosupermercati` |

Deve corrispondere a come il server web la espone. Se non corrisponde, la
pagina di accesso rimanda a un indirizzo che non esiste e non si entra: il
sintomo e' un giro di redirect a vuoto, non un errore leggibile.

Il `600` conta: `.env` contiene la password del database, e su un server
condiviso con altri utenti un `644` la rende leggibile a tutti loro.

## 5. Il server web

Le configurazioni pronte stanno in `ops/deploy/`. Copiare quella che serve e
adattare `server_name` e i percorsi.

**Se la dashboard ha un dominio o un sottodominio tutto suo:**

- `nginx-osservatoriopromo.conf` — nginx con PHP-FPM
- `apache-osservatoriopromo.conf` — Apache su server proprio
- `htaccess-per-hosting-condiviso` — da copiare come `.htaccess` dentro
  `dashboard/public/` quando la configurazione del server non si tocca

**Se sta dentro un sito che esiste gia'**, per esempio
`https://gestionaletpmo.it/promosupermercati`:

- `nginx-sottocartella.conf`
- `htaccess-sottocartella`

In questo caso il clone sta **fuori** dalla radice web e dentro la radice web
si mette solo un collegamento simbolico alla cartella pubblica:

```bash
sudo ln -s /srv/osservatoriopromo/dashboard/public \
           /var/www/gestionaletpmo.it/promosupermercati
```

Cosi' `.env`, `db/`, `crawler/` e la cartella `.git` sono irraggiungibili dal
browser perche' stanno altrove, non perche' una regola li nasconde. Le regole
si dimenticano quando si cambia server; la disposizione dei file no.

E ricordarsi `APP_BASE_PATH=/promosupermercati` nel `.env`: il server web e
l'applicazione devono dire la stessa cosa.

**Due cose vanno giuste o non funziona niente.**

La **radice web e' `dashboard/public`**, non la radice del progetto. Puntandola
alla radice del progetto, chiunque scarica `.env` con le credenziali del
database scrivendo `/.env` nella barra degli indirizzi. E' l'errore piu' comune
e il piu' silenzioso: la dashboard sembra funzionare benissimo.

Il **router e' scritto a mano**, quindi ogni percorso che non corrisponde a un
file vero deve arrivare a `index.php` (`try_files` su nginx, `RewriteRule` su
Apache). Senza, la pagina di accesso si apre ma `/punti-vendita/1` da' 404 e
sembra che l'applicazione sia rotta.

Se l'hosting condiviso **non permette** di puntare il dominio su
`dashboard/public`, non installare: cercare un altro hosting. Non esiste un
modo affidabile di nascondere `.env` lasciandolo dentro la radice web.

## 6. Il certificato

```bash
sudo certbot --nginx -d gestionaletpmo.it     # oppure --apache
```

Certbot rinnova da solo. Verificare che il rinnovo funzioni **prima** che
scada:

```bash
sudo certbot renew --dry-run
```

## 7. PHP in produzione

In `php.ini`:

```ini
display_errors = Off        ; gli errori vanno nei log, non davanti all'utente
log_errors = On
expose_php = Off            ; non annunciare la versione di PHP a chi bussa
session.cookie_httponly = 1
session.cookie_samesite = Lax
session.use_strict_mode = 1
```

L'applicazione imposta comunque questi parametri di sessione da sola
(`dashboard/app/Core/Auth.php`), ma averli anche in `php.ini` protegge se un
domani qualcuno aggiunge un altro punto d'ingresso.

## 8. Gli accessi delle segretarie

```bash
cd /srv/osservatoriopromo
php ops/scripts/crea_utente.php maria@esempio.it "Maria Rossi"
php ops/scripts/crea_utente.php anna@esempio.it  "Anna Bianchi" amministratrice
```

La password si digita a schermo spento e non viene scritta da nessuna parte:
va consegnata a voce, non per email o messaggio. Lo stesso comando reimposta la
password di un accesso esistente: e' la via di recupero, perche' non c'e' un
"password dimenticata".

### Senza accesso SSH (pannello Plesk o simili)

Lo script e' da riga di comando, quindi serve un modo di eseguirlo. Su Plesk
si usa **Strumenti e impostazioni -> Attivita' pianificate**, con "Esegui un
comando".

La password **non va scritta nella definizione dell'attivita'**: resta li',
visibile a chiunque apra il pannello, e Plesk puo' spedirne l'esito per email.
Si passa da un file temporaneo:

1. Con il File Manager, crea un file (per esempio `segreto.txt`) nella cartella
   del dominio ma **fuori da `httpdocs`**, con dentro la sola password.
2. Dai al file i permessi **600**. Lo script si rifiuta di leggere un file che
   altri utenti del server possono aprire.
3. Crea l'attivita' pianificata, eseguila una volta sola:

   ```
   UTENTE_PASSWORD_FILE=/var/www/vhosts/<dominio>/segreto.txt \
     /opt/plesk/php/8.2/bin/php \
     /var/www/vhosts/<dominio>/osservatoriopromo/ops/scripts/crea_utente.php \
     maria@esempio.it "Maria Rossi"
   ```

4. **Cancella il file** e l'attivita'. Lo script te lo ricorda a lavoro finito.

Adattare la versione di PHP nel percorso a quella scelta per il dominio.

Verificare che **non** sia rimasto l'accesso dimostrativo:

```sql
SELECT email, is_active FROM users;
-- segretaria@example.org non deve comparire.
-- Se c'e':  UPDATE users SET is_active = 0 WHERE email = 'segretaria@example.org';
```

## 9. La verifica

Non fidarsi: controllare.

```bash
ops/scripts/verifica_produzione.sh https://gestionaletpmo.it/promosupermercati
```

Controlla che l'indirizzo in chiaro reindirizzi a quello cifrato, che nessuna
pagina si apra senza accesso, che `.env` e `.git` non siano scaricabili, che il
cookie di sessione abbia `HttpOnly`, `Secure` e `SameSite`, e che una POST
senza token CSRF venga rifiutata.

**Finche' anche un solo controllo fallisce, l'indirizzo non si da' a nessuno.**

Va rieseguito dopo ogni modifica alla configurazione del server: e' li' che si
rompono queste cose, non nel codice.

## Manutenzione

**Copia di sicurezza.** Il registro dei contatti e' lavoro di mesi e non sta da
nessun'altra parte.

```bash
mariadb-dump --single-transaction osservatorio_promo | gzip > /var/backups/osservatorio-$(date +%F).sql.gz
```

Il file contiene dati personali: va custodito come tale e cancellato quando non
serve piu'. E non va mai messo nel repository.

**Revocare un accesso** quando una volontaria smette:

```sql
UPDATE users SET is_active = 0 WHERE email = 'maria@esempio.it';
```

Si disattiva, non si cancella: l'utente applicativo non ha il permesso di
cancellare, e la riga serve a capire chi aveva preso in carico quali contatti.

Ha effetto **subito**, anche su una sessione gia' aperta. Lo stesso vale per la
reimpostazione della password: se sospetti che qualcuno sia entrato, rieseguire
`crea_utente.php` su quell'indirizzo chiude anche le sessioni in corso.

**Cancellare i referenti** quando il rapporto con un punto vendita si chiude:
sono dati personali raccolti per uno scopo, e finito lo scopo vanno tolti.
Questo richiede un utente amministrativo del database, di proposito.

## Aggiornare

```bash
cd /srv/osservatoriopromo
git pull
for f in db/migrations/*.sql; do sudo mariadb osservatorio_promo < "$f"; done
ops/scripts/verifica_produzione.sh https://gestionaletpmo.it/promosupermercati
```

Le migration sono scritte per poter essere rieseguite senza danno
(`CREATE TABLE IF NOT EXISTS`, `CREATE OR REPLACE VIEW`). Fare comunque la
copia di sicurezza prima.
