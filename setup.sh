#!/bin/bash
#
# hotspot-image setup.sh — provisions a fresh Raspberry Pi OS install into a
# working SvxLink hotspot, using stock SvxLink (never RF.Guru's buggy
# Logic.tcl) and our own dashboard fork.
#
# Unlike RF.Guru's hotspot-config, this script does NOT ask interactive
# questions about callsign/band/frequency/CTCSS/location — all of that is
# configured afterwards from the dashboard's Setup page once the device is
# reachable on the network. This script only gets the system itself into a
# working, blank-but-functional state.
#
# Run as root on a fresh Raspberry Pi OS Lite/Bookworm install.
#
set -euo pipefail

if [ "$EUID" -ne 0 ]; then
  echo "Please run as root (sudo bash setup.sh)"
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "=== 1/5: base packages ==="
apt-get update -y
apt-get install -y avahi-daemon avahi-utils network-manager

echo "=== 2/5: audio (WM8960) + GPIO PTT ==="
bash "$SCRIPT_DIR/lib/audio-gpio.sh"

echo "=== 3/5: building SvxLink (stock) + installing our Logic.tcl ==="
bash "$SCRIPT_DIR/lib/build-svxlink.sh"

echo "=== 4/5: dashboard ==="
bash "$SCRIPT_DIR/lib/install-dashboard.sh"

echo "=== 5/5: watchdog ==="
bash "$SCRIPT_DIR/lib/install-watchdog.sh"

echo
echo "=== Done ==="
echo "1. Join this device's WiFi (or connect via ethernet) and open http://$(hostname).local/"
echo "2. Use the WiFi page to join your real network, if not already on it."
echo "3. Use the new Setup page to configure callsign, reflector, radio, and location."
echo "4. Restart svxlink from the Power page once configured."
