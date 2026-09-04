#!/bin/bash
#
# Install RF.Guru's svxlink-watchdog. Unlike Logic.tcl, this script has no
# known bugs — it just monitors GPIO16 (PTT) and restarts svxlink if the
# line gets stuck low for >150s. Reused as-is, verified working tonight on
# svxlinkuhf.
#
set -euo pipefail

wget -qO- https://raw.githubusercontent.com/Guru-RF/Analog-HotSPOT-SVXLink/master/install-svxlink-watchdog.sh | bash

echo "--- svxlink-watchdog: $(systemctl is-active svxlink-watchdog) / $(systemctl is-enabled svxlink-watchdog) ---"
