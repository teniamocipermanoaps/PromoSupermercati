#!/usr/bin/env bash
# Controlla che un'installazione di OsservatorioPromo sia messa in opera bene.
#
#   ops/scripts/verifica_produzione.sh https://osservatorio.esempio.it
#
# Non tocca il database e non scrive nulla: fa solo richieste dall'esterno,
# come le farebbe un curioso. Va eseguito dopo ogni messa in opera e dopo ogni
# modifica alla configurazione del server.
#
# Quello che controlla e' la superficie esposta: che si entri solo in HTTPS,
# che nessuna pagina si apra senza accesso, che .env non sia scaricabile, che
# il cookie di sessione sia protetto. Non sostituisce dashboard/tests/smoke.sh,
# che invece prova che l'applicazione funzioni.

set -uo pipefail

BASE="${1:-}"
if [ -z "$BASE" ]; then
  echo "Uso: $0 https://osservatorio.esempio.it" >&2
  exit 2
fi
BASE="${BASE%/}"

# L'applicazione puo' stare in una sottocartella
# (https://gestionaletpmo.it/promosupermercati): l'origine serve per le prove
# che riguardano il dominio e non l'applicazione.
ORIGINE=$(sed -E 's#^(https?://[^/]+).*#\1#' <<<"$BASE")
if [ "$ORIGINE" != "$BASE" ]; then
  echo "Applicazione in sottocartella: ${BASE#"$ORIGINE"}"
fi

FALLITI=0
AVVISI=0

ok()      { printf '  OK      %s\n' "$1"; }
fallito() { printf '  FALLITO %s\n' "$1"; FALLITI=$((FALLITI + 1)); }
avviso()  { printf '  AVVISO  %s\n' "$1"; AVVISI=$((AVVISI + 1)); }

codice()      { curl -s -o /dev/null -w '%{http_code}' "$1"; }
intestazioni(){ curl -s -D - -o /dev/null "$1"; }

# Percorsi che non devono aprirsi senza accesso. Se ne aggiungi uno alla
# dashboard, aggiungilo anche qui.
PROTETTI=(/ /punti-vendita /punti-vendita/1 /campagne /banchetti)

echo "Verifica di $BASE"
echo

echo "== trasporto cifrato =="
case "$BASE" in
  https://*)
    dominio="${ORIGINE#https://}"
    destinazione=$(curl -s -o /dev/null -w '%{redirect_url}' "http://$dominio/")
    case "$destinazione" in
      https://*) ok "http reindirizza a $destinazione" ;;
      "")        fallito "http non reindirizza a https: la password viaggia in chiaro" ;;
      *)         fallito "http reindirizza altrove ($destinazione)" ;;
    esac

    if intestazioni "$BASE/accesso" | grep -qi '^strict-transport-security:'; then
      ok "Strict-Transport-Security presente"
    else
      fallito "manca Strict-Transport-Security: un primo accesso in chiaro resta possibile"
    fi
    ;;
  http://*)
    avviso "indirizzo in chiaro: le prove sul cifrato non si possono fare"
    avviso "in produzione la password viaggerebbe leggibile sulla rete"
    ;;
esac

echo
echo "== niente si apre senza accesso =="
for percorso in "${PROTETTI[@]}"; do
  stato=$(codice "$BASE$percorso")
  destinazione=$(curl -s -o /dev/null -w '%{redirect_url}' "$BASE$percorso")
  case "$stato:$destinazione" in
    30?:*/accesso*) ok "$percorso chiuso" ;;
    200:*)          fallito "$percorso APERTO senza accesso: dati dei referenti esposti" ;;
    *)              fallito "$percorso risponde $stato (atteso 302 verso /accesso)" ;;
  esac
done

stato=$(codice "$BASE/accesso")
[ "$stato" = "200" ] && ok "/accesso raggiungibile" || fallito "/accesso risponde $stato"

echo
echo "== segreti non scaricabili =="
# Quello che viene pubblicato deve essere dashboard/public e nient'altro. Se e'
# la radice del progetto, .env si scarica e con esso le credenziali del
# database: si prova sia sotto l'applicazione sia alla radice del dominio,
# perche' il clone potrebbe essere finito in un punto qualsiasi.
controlla_segreto() {
  local indirizzo="$1"
  local stato
  stato=$(codice "$indirizzo")
  if [ "$stato" = "200" ]; then
    fallito "$indirizzo SCARICABILE (e' pubblicato piu' di dashboard/public)"
  else
    ok "${indirizzo#"$ORIGINE"} non raggiungibile ($stato)"
  fi
}

for segreto in /.env /.env.example /.git/config /../.env /composer.json; do
  controlla_segreto "$BASE$segreto"
done
if [ "$ORIGINE" != "$BASE" ]; then
  for segreto in /.env /.git/config; do
    controlla_segreto "$ORIGINE$segreto"
  done
fi

echo
echo "== cookie di sessione =="
cookie=$(intestazioni "$BASE/accesso" | grep -i '^set-cookie:' | head -1)
if [ -z "$cookie" ]; then
  avviso "nessun cookie impostato su /accesso"
else
  grep -qi 'httponly'      <<<"$cookie" && ok "HttpOnly"      || fallito "manca HttpOnly: il cookie e' leggibile da JavaScript"
  grep -qi 'samesite'      <<<"$cookie" && ok "SameSite"      || fallito "manca SameSite"
  case "$BASE" in
    https://*)
      grep -qi 'secure'    <<<"$cookie" && ok "Secure"        || fallito "manca Secure: il cookie puo' uscire in chiaro" ;;
    *)
      avviso "Secure non verificabile su http" ;;
  esac
fi

echo
echo "== il modulo non si invia da fuori =="
stato=$(curl -s -o /dev/null -w '%{http_code}' -X POST \
  --data-urlencode "email=prova@esempio.it" --data-urlencode "password=prova" "$BASE/accesso")
[ "$stato" = "419" ] && ok "POST senza token CSRF rifiutata (419)" \
                     || fallito "POST senza token CSRF risponde $stato (atteso 419)"

echo
echo "== l'applicazione non si racconta =="
if intestazioni "$BASE/accesso" | grep -qi '^x-powered-by:'; then
  avviso "X-Powered-By espone la versione di PHP (expose_php = Off in php.ini)"
else
  ok "X-Powered-By assente"
fi

echo
if [ "$FALLITI" -eq 0 ] && [ "$AVVISI" -eq 0 ]; then
  echo "Tutto a posto."
elif [ "$FALLITI" -eq 0 ]; then
  echo "Nessun errore, $AVVISI avvisi da guardare."
else
  echo "$FALLITI controlli falliti, $AVVISI avvisi."
  echo "Finche' ce n'e' anche uno solo, l'indirizzo non va dato alle segretarie."
fi
exit $((FALLITI > 0))
