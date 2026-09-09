echo "###-START-###"

REPO_DIR="/opt/hotspot-image"

if [ ! -d "$REPO_DIR/.git" ]; then
  echo "$REPO_DIR is not a git checkout — cannot update"
  echo "###-FINISH-####"
  exit 1
fi

echo "--- Pulling latest hotspot-image ---"
cd "$REPO_DIR"
if ! git fetch origin; then
  echo "Could not fetch from origin -- aborting, nothing was changed."
  echo "###-FINISH-####"
  exit 1
fi
# Resolve the actual remote branch first, same as check.dashboard.sh --
# `git reset --hard origin/HEAD` used directly fails on any checkout
# whose origin/HEAD symbolic ref was never set (confirmed live: this repo
# was fetched into after the fact, never `git clone`d against the real
# remote), and --verify -q is what avoids rev-parse's "prints the literal
# ref name to stdout before failing" gotcha -- see check.dashboard.sh's
# fix for the full story.
remote_rev=$(git rev-parse --verify -q origin/HEAD 2>/dev/null)
if [ -z "$remote_rev" ]; then
  remote_rev=$(git rev-parse --verify -q origin/main 2>/dev/null)
fi
if [ -z "$remote_rev" ]; then
  echo "Could not resolve the remote branch after fetching -- aborting, nothing was changed."
  echo "###-FINISH-####"
  exit 1
fi
if ! git reset --hard "$remote_rev"; then
  echo "Could not reset to $remote_rev -- aborting, nothing was changed."
  echo "###-FINISH-####"
  exit 1
fi
# git reset can recreate files as whatever user ran this (root, via sudo)
# -- put ownership back so the *next* check/update, run as www-data via
# the dashboard, doesn't hit the same "dubious ownership"/permission
# problem this checkout had before install-dashboard.sh started doing
# this chown itself.
chown -R www-data:www-data "$REPO_DIR"

echo "--- Dashboard - backup ---"
if [ -d /var/www/html ]; then
  BACKUP_DIR="/var/backups/hotspot-image/www-html-$(date +%Y%m%d_%H%M%S)"
  mkdir -p "$(dirname "$BACKUP_DIR")"
  cp -a /var/www/html "$BACKUP_DIR"
  echo "Backed up existing /var/www/html to $BACKUP_DIR"
fi

# Configurable from the Backup page (DASHBOARD_BACKUP_MAX_KEEP in
# svxlink.conf's [Dashboard] section, a key SvxLink itself never reads) --
# without a cap, one full /var/www/html copy per update run just
# accumulates forever. FIFO: directory names embed a "Ymd_His" timestamp,
# so plain `sort` is chronological order. This runs via `sh` (see
# dashboard/update/index.php's runUpdaterScript()), not bash, so no
# arrays/mapfile -- `wc -l`/`head -n`/a read loop instead.
dashboard_backup_max_keep=$(grep -E '^[ \t]*DASHBOARD_BACKUP_MAX_KEEP[ \t]*=' /etc/svxlink/svxlink.conf 2>/dev/null | tail -n1 | cut -d'=' -f2 | tr -d '[:space:]')
case "$dashboard_backup_max_keep" in
  ''|*[!0-9]*) dashboard_backup_max_keep=50 ;;
esac
backup_count=$(ls -1d /var/backups/hotspot-image/www-html-* 2>/dev/null | wc -l)
if [ "$backup_count" -gt "$dashboard_backup_max_keep" ]; then
  excess=$((backup_count - dashboard_backup_max_keep))
  ls -1d /var/backups/hotspot-image/www-html-* 2>/dev/null | sort | head -n "$excess" | while IFS= read -r old_backup; do
    rm -rf "$old_backup"
  done
  echo "Pruned $excess old dashboard backup(s) beyond the ${dashboard_backup_max_keep}-deep limit."
fi

echo "--- Re-syncing dashboard/ into /var/www/html ---"
rm -rf /var/www/html
cp -r "$REPO_DIR/dashboard" /var/www/html
chown -R www-data:www-data /var/www/html

echo "--- SVXlink service restart ---"
sudo service svxlink restart

# The header's "Update available" badge is cached for 6h (see
# update_check.php) so a page load doesn't hit the network every time --
# but that means, without this, the badge would keep showing "available"
# for up to 6h after an update that already applied it, since the cached
# result predates this run. Clearing it here makes the very next page
# load recompute fresh (now correctly seeing local == remote) instead of
# serving stale true from before the update.
rm -f /var/cache/hotspot-image/update_check.json

echo "###-FINISH-####"
