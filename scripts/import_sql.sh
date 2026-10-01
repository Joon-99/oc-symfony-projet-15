#!/usr/bin/env bash
set -euo pipefail
cd ~/projects/OC/p15-refactor-portfolio

# Doctrine appends "_test" to the dbname in the test env (see dbname_suffix in
# config/packages/doctrine.yaml) - nothing env-var based reflects that, so we
# replicate it here instead of trying to read it back out of Symfony.
APP_ENV="${APP_ENV:-dev}"
DB_NAME="ina_zaoui"
[ "$APP_ENV" = "test" ] && DB_NAME="ina_zaoui_test"

CONTAINER="$(docker compose ps -q database)"

for file in backup_DB/user.sql backup_DB/album.sql backup_DB/media.sql; do
    docker exec -i "$CONTAINER" psql -U postgres -d "$DB_NAME" < "$file"
done

# These imports use explicit ids, which never advances Doctrine's id-generator
# sequences (no column DEFAULT ties them together) - resync each one to its
# table's current max id so the next Doctrine-generated insert doesn't collide.
docker exec -i "$CONTAINER" psql -U postgres -d "$DB_NAME" -c "
    SELECT setval('user_id_seq', (SELECT MAX(id) FROM \"user\"));
    SELECT setval('album_id_seq', (SELECT MAX(id) FROM album));
    SELECT setval('media_id_seq', (SELECT MAX(id) FROM media));
"