#!/usr/bin/env bash
# Prova end-to-end della dashboard su un'istanza gia' avviata.
#
#   php -S 127.0.0.1:8080 -t dashboard/public &
#   dashboard/tests/smoke.sh
#
# Verifica che ogni pagina risponda, che i moduli salvino davvero e che una
# POST senza token CSRF venga rifiutata.
#
# ATTENZIONE: scrive righe reali nel database a cui la dashboard e' collegata.
# Eseguire solo contro un'istanza usa e getta, mai in produzione. Per ripulire:
#   mariadb osservatorio_promo < db/migrations/... e ricaricare i seed.

set -uo pipefail

BASE="${BASE:-http://127.0.0.1:8080}"
COOKIE=$(mktemp)
FALLITI=0

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
  if curl -s -b "$COOKIE" -c "$COOKIE" "$BASE$percorso" | grep -qF "$atteso"; then
    printf '  OK   %s\n' "$descrizione"
  else
    printf '  FAIL %s (testo "%s" non trovato in %s)\n' "$descrizione" "$atteso" "$percorso"
    FALLITI=$((FALLITI + 1))
  fi
}

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

echo
if [ "$FALLITI" -eq 0 ]; then
  echo "Tutte le prove superate."
else
  echo "$FALLITI prove fallite."
fi
exit $((FALLITI > 0))
