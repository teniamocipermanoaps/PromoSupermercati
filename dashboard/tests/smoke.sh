#!/usr/bin/env bash
# Prova end-to-end della dashboard su un'istanza gia' avviata.
#
#   php -S 127.0.0.1:8080 -t dashboard/public &
#   SMOKE_EMAIL=... SMOKE_PASSWORD=... dashboard/tests/smoke.sh
#
# Verifica che senza accesso non si veda niente, che l'accesso funzioni, che
# ogni pagina risponda, che i moduli salvino davvero e che una POST senza token
# CSRF venga rifiutata.
#
# Le credenziali servono perche' la dashboard e' chiusa: creane una con
#   php ops/scripts/crea_utente.php prova@esempio.it "Prova"
#
# Se la dashboard sta in una sottocartella, il prefisso va messo in BASE e deve
# corrispondere a APP_BASE_PATH del .env:
#   BASE=http://127.0.0.1:8080/promosupermercati SMOKE_EMAIL=... dashboard/tests/smoke.sh
#
# ATTENZIONE: scrive righe reali nel database a cui la dashboard e' collegata.
# Eseguire solo contro un'istanza usa e getta, mai in produzione. Per ripulire:
#   mariadb osservatorio_promo < db/migrations/... e ricaricare i seed.

set -uo pipefail

BASE="${BASE:-http://127.0.0.1:8080}"
COOKIE=$(mktemp)
FALLITI=0

EMAIL="${SMOKE_EMAIL:-}"
PASSWORD="${SMOKE_PASSWORD:-}"
if [ -z "$EMAIL" ] || [ -z "$PASSWORD" ]; then
  echo "Servono le credenziali di un accesso esistente:" >&2
  echo "  SMOKE_EMAIL=... SMOKE_PASSWORD=... $0" >&2
  echo "Per crearne uno:  php ops/scripts/crea_utente.php prova@esempio.it \"Prova\"" >&2
  exit 2
fi

trap 'rm -f "$COOKIE"' EXIT

controlla() {
  local descrizione="$1" atteso="$2" ottenuto="$3"
  if [ "$atteso" = "$ottenuto" ]; then
    printf '  OK   %s\n' "$descrizione"
  else
    printf '  FAIL %s (atteso %s, ottenuto %s)\n' "$descrizione" "$atteso" "$ottenuto"
    FALLITI=$((FALLITI + 1))
  fi
}

stato() { curl -s -b "$COOKIE" -c "$COOKIE" -o /dev/null -w '%{http_code}' "$BASE$1"; }

token_di() {
  curl -s -b "$COOKIE" -c "$COOKIE" "$BASE$1" \
    | grep -o 'name="_csrf" value="[^"]*"' | head -1 | cut -d'"' -f4
}

contiene() {
  local descrizione="$1" percorso="$2" atteso="$3"
  local corpo stato
  # Il codice di risposta si guarda sempre: senza, una pagina che non e'
  # arrivata (302 verso l'accesso, 500) si confonde con una pagina arrivata
  # senza il testo cercato, e il fallimento diventa impossibile da spiegare.
  corpo=$(mktemp)
  stato=$(curl -s -b "$COOKIE" -c "$COOKIE" -o "$corpo" -w '%{http_code}' "$BASE$percorso")
  if [ "$stato" != "200" ]; then
    printf '  FAIL %s (%s ha risposto %s, non 200)\n' "$descrizione" "$percorso" "$stato"
    FALLITI=$((FALLITI + 1))
  elif grep -qF "$atteso" "$corpo"; then
    printf '  OK   %s\n' "$descrizione"
  else
    printf '  FAIL %s (testo "%s" non trovato in %s, %s byte ricevuti)\n' \
      "$descrizione" "$atteso" "$percorso" "$(wc -c < "$corpo")"
    FALLITI=$((FALLITI + 1))
  fi
  rm -f "$corpo"
}

echo "== senza accesso non si entra =="
# Il cancello e' chiuso per definizione: se una di queste risponde 200,
# i nomi e i telefoni dei referenti sono di nuovo leggibili da chiunque.
controlla "agenda chiusa"             302 "$(stato /)"
controlla "punti vendita chiusi"      302 "$(stato /punti-vendita)"
controlla "scheda PDV chiusa"         302 "$(stato /punti-vendita/1)"
controlla "campagne chiuse"           302 "$(stato /campagne)"
controlla "banchetti chiusi"          302 "$(stato /banchetti)"
controlla "pagina di accesso aperta"  200 "$(stato /accesso)"

# Un token CSRF valido si prende dalla pagina di accesso: da solo non deve
# bastare a scrivere niente.
TOKEN=$(token_di /accesso)
scrittura_anonima=$(curl -s -b "$COOKIE" -c "$COOKIE" -o /dev/null -w '%{redirect_url}' -X POST \
  --data-urlencode "_csrf=$TOKEN" --data-urlencode "chain_id=1" --data-urlencode "title=Intrusione" \
  --data-urlencode "valid_from=2026-11-01" --data-urlencode "valid_to=2026-11-07" "$BASE/campagne")
case "$scrittura_anonima" in
  */accesso*) printf '  OK   POST senza accesso respinta verso la pagina di accesso\n' ;;
  *) printf '  FAIL POST senza accesso accettata (%s)\n' "$scrittura_anonima"; FALLITI=$((FALLITI+1)) ;;
esac

echo "== accesso =="
TOKEN=$(token_di /accesso)
sbagliata=$(curl -s -b "$COOKIE" -c "$COOKIE" -o /dev/null -w '%{http_code}' -X POST \
  --data-urlencode "_csrf=$TOKEN" --data-urlencode "email=$EMAIL" \
  --data-urlencode "password=password-sbagliata-di-proposito" "$BASE/accesso")
controlla "password sbagliata rifiutata" 401 "$sbagliata"

TOKEN=$(token_di /accesso)
entrata=$(curl -s -b "$COOKIE" -c "$COOKIE" -o /dev/null -w '%{http_code}' -X POST \
  --data-urlencode "_csrf=$TOKEN" --data-urlencode "email=$EMAIL" \
  --data-urlencode "password=$PASSWORD" "$BASE/accesso")
controlla "accesso riuscito" 302 "$entrata"
if [ "$entrata" != "302" ]; then
  echo "Accesso non riuscito: le prove seguenti non hanno senso. Controlla le credenziali." >&2
  exit 1
fi

echo "== pagine =="
controlla "agenda"            200 "$(stato /)"
controlla "punti vendita"     200 "$(stato /punti-vendita)"
controlla "scheda punto vendita" 200 "$(stato /punti-vendita/1)"
controlla "campagne"          200 "$(stato /banchetti)"
controlla "banchetti"         200 "$(stato /campagne)"
controlla "404 su rotta ignota" 404 "$(stato /non-esiste)"
controlla "404 su PDV inesistente" 404 "$(stato /punti-vendita/999999)"

echo "== protezione CSRF =="
senza_token=$(curl -s -b "$COOKIE" -c "$COOKIE" -o /dev/null -w '%{http_code}' \
  -X POST -d "title=Intrusione&chain_id=1&valid_from=2026-11-01&valid_to=2026-11-07" "$BASE/campagne")
controlla "POST senza token rifiutata" 419 "$senza_token"

echo "== creazione campagna =="
TOKEN=$(token_di /campagne)
[ -n "$TOKEN" ] && printf '  OK   token CSRF presente nel modulo\n' || { printf '  FAIL token CSRF assente\n'; FALLITI=$((FALLITI+1)); }
ETICHETTA="Test automatico $(date +%s)"
creazione=$(curl -s -b "$COOKIE" -c "$COOKIE" -o /dev/null -w '%{http_code}' -X POST \
  --data-urlencode "_csrf=$TOKEN" --data-urlencode "chain_id=1" \
  --data-urlencode "title=$ETICHETTA" --data-urlencode "valid_from=2026-11-05" \
  --data-urlencode "valid_to=2026-11-18" "$BASE/campagne")
controlla "POST campagna reindirizza" 302 "$creazione"
contiene "campagna presente in elenco" /campagne "$ETICHETTA"

echo "== validazione =="
TOKEN=$(token_di /campagne)
date_invertite=$(curl -s -b "$COOKIE" -c "$COOKIE" -o /dev/null -w '%{redirect_url}' -X POST \
  --data-urlencode "_csrf=$TOKEN" --data-urlencode "chain_id=1" --data-urlencode "title=Date sbagliate" \
  --data-urlencode "valid_from=2026-12-20" --data-urlencode "valid_to=2026-12-01" "$BASE/campagne")
case "$date_invertite" in
  *date-invertite*) printf '  OK   date invertite respinte\n' ;;
  *) printf '  FAIL date invertite accettate (%s)\n' "$date_invertite"; FALLITI=$((FALLITI+1)) ;;
esac

echo "== richiesta di autorizzazione =="
TOKEN=$(token_di /punti-vendita/1)
NOTA="Richiesta di prova $(date +%s)"
richiesta=$(curl -s -b "$COOKIE" -c "$COOKIE" -o /dev/null -w '%{http_code}' -X POST \
  --data-urlencode "_csrf=$TOKEN" --data-urlencode "store_id=1" \
  --data-urlencode "target_date_from=2026-11-05" --data-urlencode "target_date_to=2026-11-18" \
  --data-urlencode "requested_by=Test" --data-urlencode "channel=telefono" \
  --data-urlencode "notes=$NOTA" "$BASE/richieste")
controlla "POST richiesta reindirizza" 302 "$richiesta"
contiene "richiesta visibile nella scheda" /punti-vendita/1 "$NOTA"

echo "== banchetto =="
TOKEN=$(token_di /banchetti)
NOTA_B="Banchetto di prova $(date +%s)"
banchetto=$(curl -s -b "$COOKIE" -c "$COOKIE" -o /dev/null -w '%{http_code}' -X POST \
  --data-urlencode "_csrf=$TOKEN" --data-urlencode "store_id=1" \
  --data-urlencode "event_date=2026-11-07" --data-urlencode "volunteers_count=3" \
  --data-urlencode "donations_eur=250,50" --data-urlencode "promo_active=1" \
  --data-urlencode "footfall_rating=4" --data-urlencode "notes=$NOTA_B" "$BASE/banchetti")
controlla "POST banchetto reindirizza" 302 "$banchetto"
contiene "banchetto visibile in elenco" /banchetti "$NOTA_B"
contiene "importo con decimali corretto" /banchetti "250,50"

# La revoca non si vede da fuori: senza questa prova, il giorno in cui il
# controllo sul database sparisse dal cancello nessuno se ne accorgerebbe, e
# disattivare un accesso smetterebbe di disattivare qualcosa. Serve un client
# mariadb e i permessi per scrivere su users, quindi e' facoltativa:
#   SMOKE_DB=osservatorio_promo_demo dashboard/tests/smoke.sh
if [ -n "${SMOKE_DB:-}" ] && command -v mariadb >/dev/null 2>&1; then
  echo "== la revoca vale subito, non dal prossimo accesso =="
  REVOCA=$(mktemp)
  tok_revoca() {
    curl -s -b "$REVOCA" -c "$REVOCA" "$BASE$1" \
      | grep -o 'name="_csrf" value="[^"]*"' | head -1 | cut -d'"' -f4
  }
  T=$(tok_revoca /accesso)
  curl -s -b "$REVOCA" -c "$REVOCA" -o /dev/null -X POST \
    --data-urlencode "_csrf=$T" --data-urlencode "email=$EMAIL" \
    --data-urlencode "password=$PASSWORD" "$BASE/accesso"
  aperta=$(curl -s -b "$REVOCA" -c "$REVOCA" -o /dev/null -w '%{http_code}' "$BASE/")
  controlla "sessione aperta prima della revoca" 200 "$aperta"

  mariadb "$SMOKE_DB" -e "UPDATE users SET is_active = 0 WHERE email = '$EMAIL';"
  chiusa=$(curl -s -b "$REVOCA" -c "$REVOCA" -o /dev/null -w '%{http_code}' "$BASE/")
  controlla "sessione gia' aperta chiusa dalla revoca" 302 "$chiusa"
  scheda=$(curl -s -b "$REVOCA" -c "$REVOCA" -o /dev/null -w '%{http_code}' "$BASE/punti-vendita/1")
  controlla "e i dati dei referenti non si leggono piu'" 302 "$scheda"

  mariadb "$SMOKE_DB" -e "UPDATE users SET is_active = 1 WHERE email = '$EMAIL';"
  rm -f "$REVOCA"
else
  echo "== la revoca vale subito: saltata (serve SMOKE_DB e il client mariadb) =="
fi

echo "== uscita =="
TOKEN=$(token_di /)
uscita=$(curl -s -b "$COOKIE" -c "$COOKIE" -o /dev/null -w '%{http_code}' -X POST \
  --data-urlencode "_csrf=$TOKEN" "$BASE/esci")
controlla "POST uscita reindirizza" 302 "$uscita"
controlla "dopo l'uscita l'agenda e' di nuovo chiusa" 302 "$(stato /)"

echo
if [ "$FALLITI" -eq 0 ]; then
  echo "Tutte le prove superate."
else
  echo "$FALLITI prove fallite."
fi
exit $((FALLITI > 0))
