#!/bin/bash
#
# Installs the dashboard-v2 module-based preview (docs/dashboard-v2-brief.md)
# on svxlinkuhf, the project's dedicated test node for this work. Runs
# ENTIRELY separately from the production dashboard:
#  - Serves dashboard-v2/ straight out of the git checkout via PHP's own
#    built-in server on :8081 -- no /var/www/html sync, no Apache vhost,
#    nothing shared with install-dashboard.sh's deploy path.
#  - The Signal module's collector (lib/dashboard-v2/collect-signal.sh) runs
#    on its own systemd timer, writing to its own cache file
#    (/var/cache/hotspot-image/dashboard-v2-signal.json) -- doesn't touch
#    any file the production dashboard reads or writes.
#
# Deliberately NOT called from setup.sh and NOT merged to main -- this is
# branch-only, per the brief ("main är produktionskoden och rörs inte").
# Run this by hand on svxlinkuhf while iterating on the signal-monitor
# branch.
#
# Idempotent: safe to re-run.
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "--- dashboard-v2 preview: packages ---"
apt-get install -y php-cli iputils-ping

echo "--- dashboard-v2 preview: Signal module collector ---"
cp "$SCRIPT_DIR/dashboard-v2/dashboard-v2-collect-signal.service" /etc/systemd/system/dashboard-v2-collect-signal.service
cp "$SCRIPT_DIR/dashboard-v2/dashboard-v2-collect-signal.timer" /etc/systemd/system/dashboard-v2-collect-signal.timer
systemctl daemon-reload
systemctl enable --now dashboard-v2-collect-signal.timer
# Run once immediately rather than waiting up to 60s for the timer's first
# fire, so the API has real data (not a 503) right after this script exits.
systemctl start dashboard-v2-collect-signal.service

echo "--- dashboard-v2 preview: PHP built-in server on :8081 ---"
cp "$SCRIPT_DIR/dashboard-v2/dashboard-v2-preview.service" /etc/systemd/system/dashboard-v2-preview.service
systemctl daemon-reload
systemctl enable --now dashboard-v2-preview.service

echo "--- dashboard-v2 preview: done ---"
echo "Serving http://$(hostname).local:8081/ from /opt/hotspot-image/dashboard-v2"
echo "(that's this git checkout directly -- 'git pull' + 'systemctl restart"
echo "dashboard-v2-preview' picks up changes, no separate sync step)."
