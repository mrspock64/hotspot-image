#!/bin/bash
#
# Installs an hourly timer that auto-pulls and applies a new dashboard
# commit if one exists -- scoped to the dashboard only, never OS or
# SvxLink (see lib/dashboard-autoupdate.sh's own header for why those two
# stay manual/opt-in). On by default for a fresh install; toggle off on
# the Update page (AUTO_UPDATE_DASHBOARD in svxlink.conf's [Dashboard]
# section).
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SVX_CONF=/etc/svxlink/svxlink.conf

echo "--- Dashboard auto-update: vendored script + systemd units ---"
install -D -m 755 "$SCRIPT_DIR/dashboard-autoupdate.sh" /opt/dashboard-autoupdate.sh
install -D -m 644 "$SCRIPT_DIR/hotspot-dashboard-autoupdate.service" /etc/systemd/system/hotspot-dashboard-autoupdate.service
install -D -m 644 "$SCRIPT_DIR/hotspot-dashboard-autoupdate.timer" /etc/systemd/system/hotspot-dashboard-autoupdate.timer
systemctl daemon-reload
systemctl enable --now hotspot-dashboard-autoupdate.timer

echo "--- Dashboard auto-update: default setting ---"
# Only set a default if the key genuinely isn't there yet -- never
# overwrite a value someone already chose via the Update page toggle on
# a re-run of this script.
if [ -f "$SVX_CONF" ] && ! grep -q '^[ \t]*AUTO_UPDATE_DASHBOARD[ \t]*=' "$SVX_CONF"; then
  if grep -q '^\[Dashboard\]$' "$SVX_CONF"; then
    sed -i '/^\[Dashboard\]$/a AUTO_UPDATE_DASHBOARD=1' "$SVX_CONF"
  else
    printf '\n[Dashboard]\nAUTO_UPDATE_DASHBOARD=1\n' >> "$SVX_CONF"
  fi
  echo "Set AUTO_UPDATE_DASHBOARD=1 (on by default)."
fi

echo "--- Dashboard auto-update: done ---"
echo "Checks hourly (plus up to 5 minutes' random delay) for a new commit"
echo "and applies it automatically. Toggle off on the Update page if wanted."
