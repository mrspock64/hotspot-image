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

echo "--- Load monitor: systemd service ---"
cp "$SCRIPT_DIR/load-monitor/hotspot-load-monitor.service" /etc/systemd/system/hotspot-load-monitor.service
systemctl daemon-reload
systemctl enable --now hotspot-load-monitor.service

echo "--- Load monitor: done ($(systemctl is-active hotspot-load-monitor.service)) ---"
echo "Auto-pausing QSO recording under sustained overload is off by default --"
echo "toggle it on the QSO Log page if wanted."
