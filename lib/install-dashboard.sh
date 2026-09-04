#!/bin/bash
#
# Install the hotspot-image dashboard (our fork of Guru-RF/SVXLink-Dash-V2)
# as a git checkout in /var/www/html, so the dashboard's own Update page can
# self-update with a plain `git pull` instead of the zip-swap dance the
# original scripts did (and, in two cases, pointed at a completely
# unrelated GitHub repo — see update/{check,update}.dashboard.sh).
#
set -euo pipefail

# Set this once this repo has an actual remote to clone from. Until then,
# this script copies the local dashboard/ directory instead and git-inits
# it in place, so setup still works end-to-end for local testing — but the
# dashboard's own "Update Dashboard" button has nothing to pull from until
# DASHBOARD_REPO_URL points at a real remote.
DASHBOARD_REPO_URL="${DASHBOARD_REPO_URL:-}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "--- Installing Apache + PHP ---"
apt-get install -y apache2 php php-cli libapache2-mod-php

echo "--- Installing dashboard into /var/www/html ---"
rm -rf /var/www/html
if [ -n "$DASHBOARD_REPO_URL" ]; then
  git clone "$DASHBOARD_REPO_URL" /var/www/html
else
  echo "WARNING: DASHBOARD_REPO_URL is not set — copying the local dashboard/"
  echo "         directory instead. The dashboard's Update page will have"
  echo "         nothing to pull from until this repo has a real remote."
  cp -r "$SCRIPT_DIR/dashboard" /var/www/html
  git -C /var/www/html init -q
  git -C /var/www/html add -A
  git -C /var/www/html commit -q -m "Initial install (no remote configured yet)"
fi
chown -R www-data:www-data /var/www/html

echo "--- Granting www-data passwordless sudo ---"
# Matches RF.Guru's own install-svxlink-dashboard.sh design: the dashboard's
# power/update/wifi/network pages all need to run privileged commands
# (shutdown, service restart, apt upgrade, rebuilding svxlink, git pull as
# root in /var/www/html). Scoping this to individual commands would mean
# rewriting most of the dashboard's existing pages; the user has explicitly
# accepted this risk model for a home-LAN, single-operator hobby node.
echo "www-data ALL=(ALL) NOPASSWD: ALL" > /etc/sudoers.d/010_www-data-nopasswd
chmod 440 /etc/sudoers.d/010_www-data-nopasswd
chsh -s /bin/bash www-data

echo "--- Enabling Apache ---"
systemctl enable apache2
systemctl restart apache2

echo "--- Dashboard installed. Reachable at http://$(hostname).local/ ---"
