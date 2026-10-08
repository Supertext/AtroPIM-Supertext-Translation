#!/bin/bash
# AtroPIM demo start: persistent data on the volume, database, installation, demo content and
# accounts (only what is missing), AtroCore's job runner, then Apache.
set -euo pipefail

APP=/var/www/atro
STORE="${ATRO_DATA_DIR:-/data}"
log() { echo "[demo] $*"; }

# Apache must not load more than one MPM (Railway lesson).
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*

# data/ (config, languages, encryption key) and upload/ live on the volume.
mkdir -p "$STORE/data" "$STORE/upload"
if [ ! -f "$STORE/data/config.php" ]; then
  log "First start: copying AtroPIM's data directory to $STORE"
  cp -a "$APP/data.dist/." "$STORE/data/"
  cp -a "$APP/upload.dist/." "$STORE/upload/"
fi
# The module list comes from this image; the cache from the previous image is stale.
cp "$APP/data.dist/modules.json" "$STORE/data/modules.json"
rm -rf "$STORE/data/cache" && mkdir -p "$STORE/data/cache" "$STORE/data/logs"
ln -sfn "$STORE/data" "$APP/data"
ln -sfn "$STORE/upload" "$APP/upload"
chown -R www-data:www-data "$STORE" "$APP/public"

as_www() { runuser -u www-data -- "$@"; }

as_www php /usr/local/lib/supertext-demo/install.php
# Database columns for fields added by a newer module version.
as_www php console.php "sql diff --run" >/dev/null 2>&1 || log "sql diff --run failed (see data/logs)"
# Separate processes: a new language changes the metadata the next steps need.
# The demo starts even if this fails (the reason is in the log).
as_www php /usr/local/lib/supertext-demo/setup.php model || log "Demo setup (model) failed"
as_www php /usr/local/lib/supertext-demo/setup.php content || log "Demo setup (content) failed"

# AtroCore runs background jobs (mass actions, "Execute in background") from its cron command.
( while true; do as_www php "$APP/console.php" cron >/dev/null 2>&1 || true; sleep 60; done ) &

log "Ready on port ${PORT}"
exec apache2-foreground
