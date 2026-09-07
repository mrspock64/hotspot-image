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

echo "--- Re-syncing dashboard/ into /var/www/html ---"
rm -rf /var/www/html
cp -r "$REPO_DIR/dashboard" /var/www/html
chown -R www-data:www-data /var/www/html

echo "--- SVXlink service restart ---"
sudo service svxlink restart

echo "###-FINISH-####"
