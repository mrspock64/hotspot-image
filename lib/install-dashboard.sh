#!/bin/bash
#
# Install the hotspot-image dashboard (our fork of Guru-RF/SVXLink-Dash-V2).
#
# The whole hotspot-image repo (setup.sh, lib/, events.d/, dashboard/) is
# cloned into /opt/hotspot-image -- NOT directly into /var/www/html, since
# only the dashboard/ subdirectory is meant to be web-served. The dashboard's
# own Update page pulls new commits into /opt/hotspot-image and re-syncs
# dashboard/ into /var/www/html; see update/update.dashboard.sh.
#
set -euo pipefail

DASHBOARD_REPO_URL="${DASHBOARD_REPO_URL:-https://github.com/mrspock64/hotspot-image.git}"
REPO_DIR="/opt/hotspot-image"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "--- Installing Apache + PHP ---"
apt-get install -y apache2 php php-cli libapache2-mod-php git

echo "--- Cloning hotspot-image into $REPO_DIR ---"
rm -rf "$REPO_DIR"
if [ -n "$DASHBOARD_REPO_URL" ]; then
  git clone "$DASHBOARD_REPO_URL" "$REPO_DIR"
else
  echo "WARNING: DASHBOARD_REPO_URL is not set — copying the local checkout"
  echo "         instead. The dashboard's Update page will have nothing to"
  echo "         pull from until this repo has a real remote."
  cp -r "$SCRIPT_DIR" "$REPO_DIR"
  git -C "$REPO_DIR" init -q
  git -C "$REPO_DIR" add -A
  git -C "$REPO_DIR" commit -q -m "Initial install (no remote configured yet)"
fi

echo "--- Syncing dashboard/ into /var/www/html ---"
rm -rf /var/www/html
cp -r "$REPO_DIR/dashboard" /var/www/html
chown -R www-data:www-data /var/www/html

echo "--- Granting www-data passwordless sudo ---"
# Matches RF.Guru's own install-svxlink-dashboard.sh design: the dashboard's
# power/update/wifi/network pages all need to run privileged commands
# (shutdown, service restart, apt upgrade, rebuilding svxlink, git pull as
# root in /opt/hotspot-image). Scoping this to individual commands would
# mean rewriting most of the dashboard's existing pages; the user has
# explicitly accepted this risk model for a home-LAN, single-operator hobby
# node.
echo "www-data ALL=(ALL) NOPASSWD: ALL" > /etc/sudoers.d/010_www-data-nopasswd
chmod 440 /etc/sudoers.d/010_www-data-nopasswd
chsh -s /bin/bash www-data

echo "--- Enabling Apache ---"
systemctl enable apache2
systemctl restart apache2

echo "--- Dashboard installed. Reachable at http://$(hostname).local/ ---"
