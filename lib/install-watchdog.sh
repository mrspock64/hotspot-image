#!/bin/bash
#
# Install RF.Guru's svxlink-watchdog. Unlike Logic.tcl, this script has no
# known bugs — it just monitors GPIO16 (PTT) and restarts the svxlink
# *service* (not the device) if the line gets stuck low for 360s (6 min).
# Reused as-is, verified working on svxlinkuhf.
#
set -euo pipefail

wget -qO- https://raw.githubusercontent.com/Guru-RF/Analog-HotSPOT-SVXLink/master/install-svxlink-watchdog.sh | bash

echo "--- svxlink-watchdog: $(systemctl is-active svxlink-watchdog) / $(systemctl is-enabled svxlink-watchdog) ---"
