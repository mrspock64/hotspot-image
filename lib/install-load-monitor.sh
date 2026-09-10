#!/bin/bash
#
# Installs the load/iowait/memory watchdog (see lib/load-monitor/monitor.sh
# for the full rationale -- a real live incident on 2026-09-09 traced
# sustained iowait directly to reflector UDP frame loss). Plain bash +
# systemd, no extra packages: awk/grep/php are already present.
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "--- Load monitor: vendored files ---"
mkdir -p /opt/load-monitor
cp "$SCRIPT_DIR/load-monitor/monitor.sh" /opt/load-monitor/monitor.sh
chmod +x /opt/load-monitor/monitor.sh
# qso_simulate.sh (Radio Test page) was never actually wired into any
# install script -- it only ever reached svxlinkuhf by hand during the
# session that wrote it. Belongs here, next to the watchdog it shares a
# directory with.
cp "$SCRIPT_DIR/load-monitor/qso_simulate.sh" /opt/load-monitor/qso_simulate.sh
chmod +x /opt/load-monitor/qso_simulate.sh

echo "--- Load monitor: systemd service ---"
cp "$SCRIPT_DIR/load-monitor/hotspot-load-monitor.service" /etc/systemd/system/hotspot-load-monitor.service
systemctl daemon-reload
systemctl enable --now hotspot-load-monitor.service

echo "--- Load monitor: default temperature alert messages (D921#) ---"
# Ships two pre-recorded (not TTS -- generated once with a natural voice,
# not on-device espeak-ng) temperature-warning clips, no callsign, so
# LOAD_MONITOR_AUTO_ALERT_TX has something to actually transmit out of
# the box instead of silently doing nothing until a sysop records one.
# Idempotent: never overwrites an existing index.json entry or an
# already-chosen active alert message on a rerun -- only fills in what's
# genuinely still empty.
apt-get install -y jq
LIBRARY_DIR=/etc/svxlink/sound-library
LIBRARY_INDEX="$LIBRARY_DIR/index.json"
mkdir -p "$LIBRARY_DIR"
[ -f "$LIBRARY_INDEX" ] || echo '{"entries":[],"active":{"d920":null,"d921":null}}' > "$LIBRARY_INDEX"

install_builtin_alert() {
    local id="$1" src="$2" name="$3"
    local dest="$LIBRARY_DIR/$id.wav"
    if [ ! -f "$dest" ]; then
        cp "$src" "$dest"
        chmod 644 "$dest"
    fi
    local duration
    duration=$(soxi -D "$dest" 2>/dev/null || echo null)
    jq --arg id "$id" --arg name "$name" --arg created "$(date -Iseconds)" --argjson duration "${duration:-null}" '
        if (.entries | map(.id) | index($id)) == null then
            .entries += [{"id": $id, "name": $name, "created_at": $created, "duration": $duration, "source": "builtin"}]
        else . end
    ' "$LIBRARY_INDEX" > "${LIBRARY_INDEX}.tmp" && mv "${LIBRARY_INDEX}.tmp" "$LIBRARY_INDEX"
}
install_builtin_alert "builtin-temp-warning-sv" "$SCRIPT_DIR/load-monitor/alerts/temp-warning-sv.wav" "Temperaturvarning (inbyggd)"
install_builtin_alert "builtin-temp-warning-en" "$SCRIPT_DIR/load-monitor/alerts/temp-warning-en.wav" "Temperature warning (built-in)"

# Only auto-activate a default if the sysop hasn't already picked
# something for the alert slot -- matches DEFAULT_LANG=sv_SE (this
# node's reflector audience is almost entirely Swedish callsigns).
if [ "$(jq -r '.active.d921' "$LIBRARY_INDEX")" = "null" ]; then
    cp "$LIBRARY_DIR/builtin-temp-warning-sv.wav" /etc/svxlink/alert_message.wav
    chmod 644 /etc/svxlink/alert_message.wav
    jq '.active.d921 = "builtin-temp-warning-sv"' "$LIBRARY_INDEX" > "${LIBRARY_INDEX}.tmp" && mv "${LIBRARY_INDEX}.tmp" "$LIBRARY_INDEX"
fi
chown -R root:root "$LIBRARY_DIR"
find "$LIBRARY_DIR" -type f -exec chmod 644 {} \;

echo "--- Load monitor: done ($(systemctl is-active hotspot-load-monitor.service)) ---"
echo "Auto-pausing QSO recording, auto-stopping SvxLink, and auto-transmitting the"
echo "temperature alert are all off by default -- toggle them on the QSO Log and"
echo "Power pages if wanted."
