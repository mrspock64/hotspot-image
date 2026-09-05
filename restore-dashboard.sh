#!/bin/bash
#
# restore-dashboard.sh -- reverts the dashboard web UI installed by
# install-dashboard.sh back to whatever was in /var/www/html before it ran,
# using the backup install-dashboard.sh takes automatically. Run this over
# SSH, not from the dashboard itself -- a web-triggered restore would be
# replacing the very files serving the request that triggered it.
#
# What this does and doesn't revert:
#   - DOES restore /var/www/html to the pre-hotspot-image dashboard code.
#   - DOES turn SvxLink's QSO Recorder off by default (DEFAULT_ACTIVE=0):
#     the dashboard you're restoring to has no QSO Log page to see or
#     control it, so it must not keep silently recording in the background.
#   - Does NOT touch anything else this project changed: SvxLink itself
#     (stock build + Logic.tcl), the RX Monitor services, the watchdog, or
#     the Apache/journald tuning. Those stay as they are.
#
set -euo pipefail

if [ "$EUID" -ne 0 ]; then
  echo "Please run as root (sudo bash restore-dashboard.sh)"
  exit 1
fi

BACKUP_ROOT="/var/backups/hotspot-image"
SVX_CONF="/etc/svxlink/svxlink.conf"

if [ ! -d "$BACKUP_ROOT" ]; then
  echo "No backups found at $BACKUP_ROOT -- install-dashboard.sh has never" >&2
  echo "run on this node, so there's nothing to restore." >&2
  exit 1
fi

# Oldest backup = the one taken the first time install-dashboard.sh ever
# ran here, i.e. the original RF.Guru dashboard. A later backup would be an
# earlier hotspot-image version, not the real original.
ORIGINAL_BACKUP="$(find "$BACKUP_ROOT" -maxdepth 1 -type d -name 'www-html-*' | sort | head -n1)"

if [ -z "$ORIGINAL_BACKUP" ]; then
  echo "No www-html-* backups found under $BACKUP_ROOT." >&2
  exit 1
fi

echo "Restoring from: $ORIGINAL_BACKUP"
read -p "This replaces the current /var/www/html. Continue? [y/N] " CONFIRM
if [ "$CONFIRM" != "y" ] && [ "$CONFIRM" != "Y" ]; then
  echo "Aborted."
  exit 1
fi

# Back up what's currently running before overwriting it, same as
# install-dashboard.sh does -- so restoring the restore is possible too.
SAFETY_BACKUP="$BACKUP_ROOT/www-html-before-restore-$(date +%Y%m%d_%H%M%S)"
echo "Backing up the current dashboard to $SAFETY_BACKUP first"
cp -a /var/www/html "$SAFETY_BACKUP"

rm -rf /var/www/html
cp -a "$ORIGINAL_BACKUP" /var/www/html
chown -R www-data:www-data /var/www/html
systemctl restart apache2
echo "Dashboard restored."

echo "--- Turning QSO Recorder off by default ---"
if [ -f "$SVX_CONF" ] && grep -q '^\[QsoRecorder\]$' "$SVX_CONF"; then
  cp "$SVX_CONF" "$SVX_CONF.bak-restore-$(date +%Y%m%d_%H%M%S)"
  if grep -q '^DEFAULT_ACTIVE=' "$SVX_CONF"; then
    sed -i 's/^DEFAULT_ACTIVE=.*/DEFAULT_ACTIVE=0/' "$SVX_CONF"
  else
    sed -i '/^\[QsoRecorder\]$/a DEFAULT_ACTIVE=0' "$SVX_CONF"
  fi
  systemctl restart svxlink
  echo "QSO Recorder set to off by default and svxlink restarted."
else
  echo "No [QsoRecorder] section found -- nothing to turn off."
fi

echo
echo "=== Done ==="
echo "Dashboard reverted to: $ORIGINAL_BACKUP"
echo "Previous (hotspot-image) dashboard saved at: $SAFETY_BACKUP"
echo "Still in place, unchanged: SvxLink itself, RX Monitor services, the watchdog, Apache/journald tuning."
