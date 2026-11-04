#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
docker compose up -d --build
docker compose exec -T app php scripts/migrate.php
if [ "${1:-}" = "--demo" ]; then
  docker compose exec -T app php scripts/seed.php
fi
docker compose exec -T app php scripts/health.php
