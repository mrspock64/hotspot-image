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

# Note the missing ':' -- ${VAR:-default} treats an explicitly-empty value
# the same as unset, which defeats DASHBOARD_REPO_URL="" as a way to force
# the local-copy fallback below (e.g. while the repo is still private).
# ${VAR-default} only defaults when truly unset.
DASHBOARD_REPO_URL="${DASHBOARD_REPO_URL-https://github.com/mrspock64/hotspot-image.git}"
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
  # -c user.{email,name} so this works even when the account running setup
  # (typically root, via sudo) has no git identity configured anywhere --
  # this commit is just a local install marker, not attributed to anyone.
  git -C "$REPO_DIR" -c user.email="setup@hotspot-image.local" -c user.name="hotspot-image setup" commit -q -m "Initial install (no remote configured yet)"
fi

echo "--- Syncing dashboard/ into /var/www/html ---"
if [ -d /var/www/html ]; then
  BACKUP_DIR="/var/backups/hotspot-image/www-html-$(date +%Y%m%d_%H%M%S)"
  echo "Backing up existing /var/www/html to $BACKUP_DIR first"
  mkdir -p "$(dirname "$BACKUP_DIR")"
  cp -a /var/www/html "$BACKUP_DIR"
fi
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

echo "--- Tuning Apache for this hardware ---"
# Debian's prefork MPM defaults (5/5/10/150) assume a real server. Measured
# live on svxlinkuhf: 10-12 idle workers at ~15MB RSS each ate ~167MB, over
# a third of a 416MB board's RAM, contributing to swap pressure and TX-time
# audio stutter. This is a single-operator dashboard, not a public site.
MPM_CONF=/etc/apache2/mods-enabled/mpm_prefork.conf
if [ -f "$MPM_CONF" ]; then
  cat > "$MPM_CONF" <<'EOF'
# prefork MPM
# StartServers: number of server processes to start
# MinSpareServers: minimum number of server processes which are kept spare
# MaxSpareServers: maximum number of server processes which are kept spare
# MaxRequestWorkers: maximum number of server processes allowed to start
# MaxConnectionsPerChild: maximum number of requests a server process serves

# Tuned down from Debian's defaults (5/5/10/150) -- this is a single-user
# dashboard on RF.Guru's hardware (as little as 416MB RAM), not a public
# server. See hotspot-image's svxlinkuhf performance notes.
#
# MaxRequestWorkers is a ceiling, not something kept pre-spawned (that's
# MinSpareServers/MaxSpareServers, which control idle RAM) -- it only
# limits how far Apache can grow under a burst. 10 turned out too low in
# practice: the dashboard's own pages poll several things every few
# seconds (status, talk groups, system info), so even one open tab can
# hold multiple connections at once (KeepAliveTimeout 5s keeps each one
# reserved briefly after use), and "MaxRequestWorkers setting reached"
# started showing up in the log with just light real use. 20 gives real
# headroom for a couple of simultaneous viewers without raising idle RAM.
StartServers            1
MinSpareServers         1
MaxSpareServers         3
MaxRequestWorkers       20
MaxConnectionsPerChild  0
EOF
fi

echo "--- Enabling Apache ---"
systemctl enable apache2
systemctl restart apache2

echo "--- Dashboard installed. Reachable at http://$(hostname).local/ ---"
