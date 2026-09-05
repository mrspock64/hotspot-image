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

# Defaults to empty (local-copy fallback below) while mrspock64/hotspot-image
# stays private -- setup.sh calls this script with no override, so a real
# GitHub URL default here would make every setup.sh run try to `git clone`
# a private repo and fail with "could not read Username for 'https://
# github.com'". Flip this back to the real URL once the repo goes public;
# until then, override with DASHBOARD_REPO_URL=<url> for a one-off test of
# the clone path itself. (Note the missing ':' in ${VAR-default} -- that's
# deliberate, ${VAR:-default} would treat an explicitly-empty override the
# same as unset and defeat DASHBOARD_REPO_URL="" too.)
DASHBOARD_REPO_URL="${DASHBOARD_REPO_URL-}"
REAL_REPO_URL="https://github.com/mrspock64/hotspot-image.git"
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
  echo "         instead. The dashboard's Update page won't have anything"
  echo "         new to pull until this repo goes public (git fetch needs"
  echo "         auth against a private repo), but 'origin' is still"
  echo "         pointed at the real URL so it's ready the moment it does."
  cp -r "$SCRIPT_DIR" "$REPO_DIR"
  git -C "$REPO_DIR" init -q
  git -C "$REPO_DIR" remote add origin "$REAL_REPO_URL"
  git -C "$REPO_DIR" add -A
  # -c user.{email,name} so this works even when the account running setup
  # (typically root, via sudo) has no git identity configured anywhere --
  # this commit is just a local install marker, not attributed to anyone.
  git -C "$REPO_DIR" -c user.email="setup@hotspot-image.local" -c user.name="hotspot-image setup" commit -q -m "Initial install (no remote configured yet)"
fi

# The dashboard's own Update page runs git fetch/pull here as www-data (via
# Apache), but everything above just ran as root (setup.sh needs root) --
# without this, check.dashboard.sh/update.dashboard.sh fail outright:
# www-data can't write root-owned .git/config, and even read-only git
# commands refuse to run at all ("detected dubious ownership") once the
# directory owner doesn't match the calling user. Found live on svxlinkuhf:
# the Update page's "Dashboard" check silently reported a bogus "UPDATE
# AVAILABLE" instead of the real permission failure underneath it.
chown -R www-data:www-data "$REPO_DIR"
git config --system --add safe.directory "$REPO_DIR"

# The header's "update available" badge caches its check here rather than
# /tmp -- apache2's systemd unit runs with PrivateTmp=yes, which gives it
# a private /tmp invisible to everything else, so a cache file living
# there would just silently never be reused (confirmed live: every page
# load recomputed the check from scratch instead of hitting the cache).
mkdir -p /var/cache/hotspot-image
chown www-data:www-data /var/cache/hotspot-image

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
