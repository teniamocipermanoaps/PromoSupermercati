#!/usr/bin/env bash
# Prepara e avvia la dashboard con dati dimostrativi, per mostrarla a qualcuno.
#
#   ops/scripts/demo.sh
#
# Ricrea da zero il database indicato, carica punti vendita e campagne finti,
# crea un accesso dimostrativo e avvia il server. Serve per una dimostrazione,
# non per l'uso reale: i punti vendita di db/seeds/900_demo.sql sono inventati.

set -euo pipefail

RADICE="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$RADICE"

DB="${DB_NAME:-osservatorio_promo_demo}"
UTENTE_ADMIN="${DB_ADMIN_USER:-root}"
PORTA="${PORTA:-8080}"

# L'utente applicativo non e' root: ha solo SELECT, INSERT, UPDATE, come in
# produzione. Non deve poter modificare lo schema ne' cancellare righe.
APP_UTENTE="${DB_USER:-osservatorio}"
APP_HOST="${DB_APP_HOST:-127.0.0.1}"

# Password generate qui e mai scritte nel repository: un segreto versionato
# e' un segreto pubblico.
genera_segreto() { head -c 18 /dev/urandom | od -An -tx1 | tr -d ' \n'; }
APP_PASSWORD="${DB_PASSWORD:-$(genera_segreto)}"

EMAIL_DEMO="${UTENTE_DEMO_EMAIL:-segretaria@example.org}"
NOME_DEMO="${UTENTE_DEMO_NOME:-Segretaria dimostrativa}"
PASSWORD_DEMO="${UTENTE_DEMO_PASSWORD:-$(genera_segreto)}"

CLIENT=$(command -v mariadb || command -v mysql || true)
if [ -z "$CLIENT" ]; then
  echo "Serve il client mariadb o mysql nel PATH." >&2
  exit 1
fi
if ! command -v php >/dev/null; then
  echo "Serve PHP 8.2 o superiore nel PATH." >&2
  exit 1
fi
if ! php -m | grep -qi '^pdo_mysql$'; then
  echo "Serve l'estensione pdo_mysql di PHP." >&2
  exit 1
fi

# Meglio fermarsi che sovrascrivere il .env di un'installazione vera.
if [ -f .env ] && ! grep -qx "DB_NAME=$DB" .env; then
  echo "Il file .env punta a un database diverso da '$DB'." >&2
  echo "Spostalo o cancellalo prima di lanciare la dimostrazione." >&2
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

echo "==> Creo l'utente applicativo '$APP_UTENTE' con i soli SELECT, INSERT, UPDATE"
"$CLIENT" -u "$UTENTE_ADMIN" -e "
  CREATE USER IF NOT EXISTS '$APP_UTENTE'@'$APP_HOST' IDENTIFIED BY '$APP_PASSWORD';
  ALTER USER '$APP_UTENTE'@'$APP_HOST' IDENTIFIED BY '$APP_PASSWORD';
  GRANT SELECT, INSERT, UPDATE ON \`$DB\`.* TO '$APP_UTENTE'@'$APP_HOST';
  FLUSH PRIVILEGES;"

echo "==> Scrivo .env puntando al database dimostrativo"
{
  echo "DB_HOST=$APP_HOST"
  echo "DB_PORT=${DB_PORT:-3306}"
  echo "DB_NAME=$DB"
  echo "DB_USER=$APP_UTENTE"
  echo "DB_PASSWORD=$APP_PASSWORD"
  # La dimostrazione gira alla radice del server locale.
  echo "APP_BASE_PATH="
} > .env
chmod 600 .env   # contiene una password: non deve essere leggibile da tutti

echo "==> Creo l'accesso dimostrativo"
UTENTE_PASSWORD="$PASSWORD_DEMO" php ops/scripts/crea_utente.php \
  "$EMAIL_DEMO" "$NOME_DEMO" amministratrice >/dev/null

cat <<FINEMESSAGGIO

Pronto. Apri:  http://127.0.0.1:$PORTA

  Accesso     $EMAIL_DEMO
  Password    $PASSWORD_DEMO

  Agenda          le campagne in arrivo e i giorni da proporre
  Punti vendita   la scheda di ogni negozio
  Campagne        per aggiungere a mano le date di un volantino
  Banchetti       per registrare quanto si e' raccolto

I dati sono inventati e la password vale solo per questa dimostrazione:
viene rigenerata a ogni avvio. Ferma il server con Ctrl+C.

FINEMESSAGGIO

exec php -S "127.0.0.1:$PORTA" -t dashboard/public
