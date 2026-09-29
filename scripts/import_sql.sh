#!/usr/bin/env bash
set -euo pipefail
cd ~/projects/OC/p15-refactor-portfolio

# shellcheck source=/dev/null
set -a && source .env && set +a

# psql (libpq) rejects Doctrine-only DSN params (serverVersion, charset)
PG_URL="${DATABASE_URL%%\?*}"

psql "$PG_URL" --file backup_DB/user.sql
psql "$PG_URL" --file backup_DB/album.sql
psql "$PG_URL" --file backup_DB/media.sql