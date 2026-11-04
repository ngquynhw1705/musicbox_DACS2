#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
# A separate disposable database, with the same least-privileged application user.
docker compose exec -T db sh -eu -c '
case "$MARIADB_USER" in *[!a-zA-Z0-9_]*) echo "Unsupported DB username for test runner" >&2; exit 2;; esac
mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS musicbox_test CHARACTER SET utf8mb4; GRANT ALL ON musicbox_test.* TO '\''$MARIADB_USER'\''@'\''%'\'';"
'
docker compose exec -T -e APP_ENV=test -e DB_DATABASE=musicbox_test app php tests/integration.php
