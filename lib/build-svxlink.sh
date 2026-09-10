#!/bin/bash
#
# Build and install stock SvxLink from source (sm0svx/svxlink, maint branch).
# This installs upstream's own events.d/ files — including upstream's own
# Logic.tcl, LogicBase.tcl and locale.tcl — into /usr/share/svxlink/events.d/.
#
# Crucially: it does NOT download or install RF.Guru's Logic.tcl. Our own
# minimal override (events.d/Logic.tcl in this repo) gets installed
# separately, into events.d/local/, by install-dashboard.sh's caller
# (setup.sh) — never overwriting the stock base file.
#
set -euo pipefail

echo "--- Installing SvxLink build prerequisites ---"
apt-get install -y \
  libssl-dev ladspa-sdk moreutils build-essential g++ make cmake \
  libsigc++-2.0-dev php libgsm1-dev libudev-dev libpopt-dev tcl-dev \
  libgpiod-dev gpiod libgcrypt20-dev libspeex-dev libasound2-dev \
  alsa-utils libjsoncpp-dev libopus-dev rtl-sdr libcurl4-openssl-dev \
  libogg-dev librtlsdr-dev groff doxygen graphviz python3-serial toilet \
  sox bc avahi-daemon avahi-utils jq \
  espeak-ng

echo "--- Building SvxLink (stock, sm0svx/svxlink maint branch) ---"
BUILD_DIR="$(mktemp -d)"
git clone --branch maint https://github.com/sm0svx/svxlink.git "$BUILD_DIR/svxlink"
mkdir -p "$BUILD_DIR/svxlink/src/build"
(
  cd "$BUILD_DIR/svxlink/src/build"
  cmake -DUSE_QT=OFF -DCMAKE_INSTALL_PREFIX=/usr -DSYSCONF_INSTALL_DIR=/etc \
        -DLOCAL_STATE_DIR=/var -DWITH_SYSTEMD=ON ..
  make -j1
  make install
  ldconfig
)
rm -rf "$BUILD_DIR"

echo "--- Installing our own local/Logic.tcl (never RF.Guru's) ---"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
install -D -m 644 "$SCRIPT_DIR/events.d/Logic.tcl" /usr/share/svxlink/events.d/local/Logic.tcl
# Stashed at a stable path so update.svxlink.sh (run later from the
# dashboard's Update page) can reinstall it after every future rebuild too.
install -D -m 644 "$SCRIPT_DIR/events.d/Logic.tcl" /etc/svxlink/hotspot-image-Logic.tcl

echo "--- svxlink installed: $(svxlink --version | head -n1) ---"
