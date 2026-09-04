#!/bin/bash
#
# Installs the RX Monitor audio-streaming backend: the dashboard's "RX
# Monitor" button (see dashboard/index.php) has always shipped in this
# codebase's lineage but never had anything behind it -- it's gated on a
# Node.js process being present (isProcessRunning('node')) and expects a
# WebSocket PCM stream on ws://<host>:8080. Both were missing from every
# node this project has provisioned until now.
#
# The bridge itself is DVSwitch's own Web_Proxy (proxy.js, vendored below
# unmodified with its original license header) -- a small Node.js service
# that takes raw PCM over UDP and rebroadcasts it to every connected
# WebSocket client. What it doesn't provide is an actual audio *source* for
# a plain SvxLink hotspot (DVSwitch's own deployments feed it from
# Analog_Bridge, which doesn't apply here), so this script also sets up:
#
#  - An ALSA dsnoop/dmix "asym" device in /etc/asound.conf (RF.Guru's own
#    stock image already ships this file, unused, presumably intended for
#    exactly this -- SvxLink's own AUDIO_DEV normally opens the hardware
#    exclusively, which would otherwise block a second reader entirely).
#  - Switching svxlink.conf's AUDIO_DEV from alsa:plughw:0 to alsa:default
#    so SvxLink itself goes through that shared layer instead of holding
#    the hardware exclusively. Verified on svxlinkuhf: SvxLink starts
#    clean, connects to the reflector, and both RX and TX (via the D911#
#    self-test) work exactly as before through the new device.
#  - rx-monitor-feed.service: arecord (via the shared "array" dsnoop
#    device) | sox (stereo->mono, keeping AUDIO_CHANNEL's channel) | a
#    small Python script chunking stdin into UDP datagrams for proxy.js.
#    Runs as the "svxlink" user specifically because dsnoop's IPC
#    semaphore is only accessible to whichever user created it -- that's
#    svxlink.service, so this has to match.
#  - rx-monitor-proxy.service: the Node.js proxy.js itself.
#
# Idempotent: safe to re-run. If svxlink.conf doesn't exist yet (a truly
# blank system, before the Setup page has ever run), the AUDIO_DEV step is
# skipped with a warning instead of failing the whole install -- it can be
# re-run later once svxlink-server is actually configured.
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SVX_CONF="/etc/svxlink/svxlink.conf"

echo "--- RX Monitor: packages ---"
apt-get install -y nodejs npm sox alsa-utils

echo "--- RX Monitor: vendored files ---"
mkdir -p /opt/rx-monitor /var/log/dvswitch
cp "$SCRIPT_DIR/rx-monitor/proxy.js" /opt/rx-monitor/proxy.js
cp "$SCRIPT_DIR/rx-monitor/pcm_udp_forward.py" /opt/rx-monitor/pcm_udp_forward.py
chmod +x /opt/rx-monitor/pcm_udp_forward.py
(cd /opt/rx-monitor && npm install ws --no-fund --no-audit --loglevel=error)

echo "--- RX Monitor: shared ALSA capture device ---"
if [ ! -f /etc/asound.conf ]; then
  echo "No /etc/asound.conf found -- this step expects RF.Guru's stock" \
       "dsnoop/dmix asym device definition to already be present." \
       "Skipping; RX Monitor will have no audio source without it." >&2
else
  chown svxlink:svxlink /var/log/dvswitch 2>/dev/null || true
  if [ -f "$SVX_CONF" ]; then
    if grep -q '^AUDIO_DEV=alsa:plughw:0$' "$SVX_CONF"; then
      cp "$SVX_CONF" "$SVX_CONF.bak-rxmonitor-$(date +%Y%m%d-%H%M%S)"
      sed -i 's/^AUDIO_DEV=alsa:plughw:0$/AUDIO_DEV=alsa:default/' "$SVX_CONF"
      echo "Switched AUDIO_DEV to alsa:default in $SVX_CONF (backup kept alongside it)."
    else
      echo "AUDIO_DEV in $SVX_CONF is not the expected alsa:plughw:0 -- leaving it" \
           "untouched. If it's not already alsa:default (or another shared" \
           "device), RX Monitor will have no audio to stream." >&2
    fi
  else
    echo "$SVX_CONF doesn't exist yet -- skipping the AUDIO_DEV switch." \
         "Re-run this script after the Setup page has created it." >&2
  fi
fi

echo "--- RX Monitor: systemd services ---"
cp "$SCRIPT_DIR/rx-monitor/rx-monitor-proxy.service" /etc/systemd/system/rx-monitor-proxy.service
cp "$SCRIPT_DIR/rx-monitor/rx-monitor-feed.service" /etc/systemd/system/rx-monitor-feed.service
systemctl daemon-reload
systemctl enable --now rx-monitor-proxy.service
systemctl enable --now rx-monitor-feed.service

echo "--- RX Monitor: done ---"
echo "Note: port 8080 is now owned by rx-monitor-proxy.service (the dashboard's"
echo "RX Monitor button expects it there). Don't run a dev/test server on 8080"
echo "on a node with this installed."
