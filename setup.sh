#!/bin/bash
#
# hotspot-image setup.sh — UPGRADES an existing RF.Guru Analog-HotSPOT-
# SVXLink node to stock SvxLink (never RF.Guru's buggy Logic.tcl, the root
# cause of a cluster of bugs documented in
# github.com/Guru-RF/Analog-HotSPOT-SVXLink/issues/2) plus our own
# dashboard fork. This is an upgrade path, not a from-scratch installer --
# it needs RF.Guru's own image already on the SD card and already booted,
# since it relies on files that image provides and this project has never
# needed to generate itself: /etc/svxlink/svxlink.conf, /etc/asound.conf,
# /etc/svxlink/node_info.json, and a signed reflector certificate under
# /var/lib/svxlink/pki/ (which requires registering with your reflector's
# admin -- nothing here can automate that part). Building a true
# blank-Raspberry-Pi-OS installer is future work, not what this does today.
#
# This is the exact path svxlinkuhf (the project's test node) went
# through, verified live on real hardware.
#
# Unlike RF.Guru's own hotspot-config, this script does NOT ask
# interactive questions about callsign/band/frequency/CTCSS/location --
# all of that is configured afterwards from the dashboard's Setup page
# once the device is reachable on the network.
#
# Run as root, on top of an already-provisioned RF.Guru node.
#
set -euo pipefail

if [ "$EUID" -ne 0 ]; then
  echo "Please run as root (sudo bash setup.sh)"
  exit 1
fi

if [ ! -f /etc/svxlink/svxlink.conf ]; then
  echo "No /etc/svxlink/svxlink.conf found -- this script upgrades an" >&2
  echo "existing RF.Guru node, it doesn't provision one from scratch." >&2
  echo "Boot RF.Guru's own image first, then run this on top of it." >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "=== 1/6: base packages ==="
apt-get update -y
apt-get install -y avahi-daemon avahi-utils network-manager

echo "=== 2/6: audio (WM8960) + GPIO PTT ==="
bash "$SCRIPT_DIR/lib/audio-gpio.sh"

echo "=== 3/6: building SvxLink (stock) + installing our Logic.tcl ==="
bash "$SCRIPT_DIR/lib/build-svxlink.sh"

echo "=== 4/6: dashboard ==="
bash "$SCRIPT_DIR/lib/install-dashboard.sh"

echo "=== 5/6: watchdog ==="
bash "$SCRIPT_DIR/lib/install-watchdog.sh"

echo "=== 6/6: RX Monitor audio streaming + QSO Log ==="
bash "$SCRIPT_DIR/lib/install-rx-monitor.sh"

echo
echo "=== Done ==="
echo "1. Join this device's WiFi (or connect via ethernet) and open http://$(hostname).local/"
echo "2. Use the WiFi page to join your real network, if not already on it."
echo "3. Use the new Setup page to configure callsign, reflector, radio, and location."
echo "4. Restart svxlink from the Power page once configured."
echo "5. See the dashboard's Help page (/help/) for what everything does."
