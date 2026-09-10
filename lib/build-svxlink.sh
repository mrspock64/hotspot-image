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
  espeak-ng ffmpeg

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

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "--- Restoring the DTMF-relay systemd unit (never the stock one) ---"
# `make install` (WITH_SYSTEMD=ON above) installs upstream's own generic
# svxlink.service -- ExecStart=/usr/bin/svxlink ..., nothing else -- which
# silently overwrites RF.Guru's own heavily customized unit. That unit is
# not cosmetic: its ExecStart is
#   nc -lk 10000 | svxlink --config=... --runasuser=... | hotspot_logger ...
# -- the nc listener on 127.0.0.1:10000 IS the DTMF relay every dashboard
# button, TG select, and D911/D920/D921 command depends on
# (/usr/sbin/hotspot_dtmf connects to it). Confirmed live on svxlinkmobile
# (2026-09-10, its first-ever full setup.sh run): with the stock unit
# installed, nothing was listening on port 10000 at all -- every DTMF-
# based feature in the dashboard was silently broken, while svxlinkuhf
# (never subjected to this exact full rebuild path since its systemd unit
# was hand-fixed) kept working and masked the bug all along.
#
# Vendored here (lib/svxlink.service, a straight copy of RF.Guru's own
# unit as confirmed working on svxlinkuhf) rather than trusting `make
# install` or a fetch-fresh-from-RF.Guru step, for the same reason
# install-bluetooth.sh vendors its own installer: a build step should not
# get to silently delete a working DTMF relay again.
install -D -m 644 "$SCRIPT_DIR/lib/svxlink.service" /lib/systemd/system/svxlink.service
systemctl daemon-reload

echo "--- Installing our own local/Logic.tcl (never RF.Guru's) ---"
install -D -m 644 "$SCRIPT_DIR/events.d/Logic.tcl" /usr/share/svxlink/events.d/local/Logic.tcl
# Stashed at a stable path so update.svxlink.sh (run later from the
# dashboard's Update page) can reinstall it after every future rebuild too.
install -D -m 644 "$SCRIPT_DIR/events.d/Logic.tcl" /etc/svxlink/hotspot-image-Logic.tcl

echo "--- svxlink installed: $(svxlink --version | head -n1) ---"
