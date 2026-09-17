#!/usr/bin/env bash
# Prepara e avvia la dashboard con dati dimostrativi, per mostrarla a qualcuno.
#
#   ops/scripts/demo.sh
#
# Ricrea da zero il database indicato, carica punti vendita e campagne finti e
# avvia il server. Serve per una dimostrazione, non per l'uso reale: i punti
# vendita di db/seeds/900_demo.sql sono inventati.

set -euo pipefail

RADICE="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$RADICE"

DB="${DB_NAME:-osservatorio_promo_demo}"
UTENTE_ADMIN="${DB_ADMIN_USER:-root}"
PORTA="${PORTA:-8080}"

CLIENT=$(command -v mariadb || command -v mysql || true)
if [ -z "$CLIENT" ]; then
  echo "Serve il client mariadb o mysql nel PATH." >&2
  exit 1
fi
if ! command -v php >/dev/null; then
  echo "Serve PHP 8.2 o superiore nel PATH." >&2
  exit 1
fi

echo "==> Ricreo il database '$DB'"
"$CLIENT" -u "$UTENTE_ADMIN" -e "
  DROP DATABASE IF EXISTS \`$DB\`;
  CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "==> Applico migration e seed"
for file in db/migrations/*.sql db/seeds/*.sql; do
  printf '    %s\n' "$(basename "$file")"
  "$CLIENT" -u "$UTENTE_ADMIN" "$DB" < "$file"
done

if [ ! -f .env ]; then
  echo "==> Creo .env puntando al database dimostrativo"
  {
    echo "DB_HOST=127.0.0.1"
    echo "DB_PORT=3306"
    echo "DB_NAME=$DB"
    echo "DB_USER=${DB_USER:-root}"
    echo "DB_PASSWORD=${DB_PASSWORD:-}"
  } > .env
else
  echo "==> .env esiste gia': controlla che DB_NAME valga '$DB'"
fi

cat <<FINE

Pronto. Apri:  http://127.0.0.1:$PORTA

  Agenda          le campagne in arrivo e i giorni da proporre
  Punti vendita   la scheda di ogni negozio
  Campagne        per aggiungere a mano le date di un volantino
  Banchetti       per registrare quanto si e' raccolto

I dati sono inventati. Ferma il server con Ctrl+C.

FINE

exec php -S "127.0.0.1:$PORTA" -t dashboard/public
