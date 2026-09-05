#!/bin/bash
#
# Installs RX Monitor (live audio) and enables SvxLink's built-in QSO
# Recorder that both RX Monitor and the dashboard's QSO Log page read from.
#
# The dashboard's "RX Monitor" button (see dashboard/index.php) has always
# shipped in this codebase's lineage but never had anything behind it --
# it's gated on a Node.js process being present (isProcessRunning('node'))
# and expects a WebSocket PCM stream on ws://<host>:8080.
#
# The bridge itself is DVSwitch's own Web_Proxy (proxy.js, vendored below
# unmodified with its original license header) -- a small Node.js service
# that takes raw PCM over UDP and rebroadcasts it to every connected
# WebSocket client. The audio *source* is SvxLink's own QSO Recorder (see
# svxlink.conf(5)'s "QSO Recorder Section") rather than a raw ALSA capture
# tap: the recorder writes audio from receivers, MODULES, and LOGIC LINKS,
# so this also carries reflector-relayed QSOs being sent to the local
# transmitter -- a plain capture tap only ever sees this node's own local
# RX, which turned out to be a real gap once tested against a live QSO.
#
#  - QSO_RECORDER=8:QsoRecorder is enabled in [SimplexLogic] (RF.Guru's
#    stock config already ships a fully configured [QsoRecorder] section,
#    just commented out).
#  - tail_qso_recorder.py watches REC_DIR for the currently-open recording
#    (SvxLink writes exactly one *.wav at a time while a QSO is active;
#    finished recordings get converted to *.mp3 by ENCODER_CMD and the wav
#    removed) and forwards its raw PCM to proxy.js over UDP. No open file
#    -> nothing forwarded -> RX Monitor is silent, which is correct.
#  - rx-monitor-proxy.service: proxy.js itself.
#
# Idempotent: safe to re-run. If svxlink.conf doesn't exist yet (a truly
# blank system, before the Setup page has ever run), the QSO Recorder step
# is skipped with a warning instead of failing the whole install -- it can
# be re-run later once svxlink-server is actually configured.
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SVX_CONF="/etc/svxlink/svxlink.conf"

echo "--- RX Monitor / QSO Log: packages ---"
apt-get install -y nodejs npm lame

echo "--- RX Monitor / QSO Log: vendored files ---"
mkdir -p /opt/rx-monitor /var/log/dvswitch
cp "$SCRIPT_DIR/rx-monitor/proxy.js" /opt/rx-monitor/proxy.js
cp "$SCRIPT_DIR/rx-monitor/tail_qso_recorder.py" /opt/rx-monitor/tail_qso_recorder.py
cp "$SCRIPT_DIR/rx-monitor/tag_and_encode.py" /opt/rx-monitor/tag_and_encode.py
chmod +x /opt/rx-monitor/tail_qso_recorder.py /opt/rx-monitor/tag_and_encode.py
(cd /opt/rx-monitor && npm install ws --no-fund --no-audit --loglevel=error)

echo "--- RX Monitor / QSO Log: SvxLink QSO Recorder ---"
if [ ! -f "$SVX_CONF" ]; then
  echo "$SVX_CONF doesn't exist yet -- skipping. Re-run this script after" \
       "the Setup page has created it." >&2
elif ! grep -q '^\[QsoRecorder\]$' "$SVX_CONF"; then
  echo "No [QsoRecorder] section found in $SVX_CONF (RF.Guru's usual stock" \
       "config ships one) -- not adding one automatically, since the rest" \
       "of this depends on REC_DIR/ENCODER_CMD already being sane." >&2
else
  cp "$SVX_CONF" "$SVX_CONF.bak-qsorec-$(date +%Y%m%d-%H%M%S)"
  RESTART_NEEDED=0

  # RF.Guru's stock ENCODER_CMD uses oggenc, which (a) is broken as shipped
  # -- missing a space between -Q and the quoted filename, so oggenc treats
  # "-Q<path>" as one malformed argument and exits 1 on every recording,
  # discovered live on svxlinkuhf: five real QSO recordings sat as .wav
  # forever, never converted, and the QSO Log page showed a permanent
  # (stale) "recording now" because of it -- and (b) even fixed, produces
  # Ogg Vorbis, which Safari (macOS and iOS) cannot play at all, so the
  # dashboard's Play button silently did nothing there.
  #
  # tag_and_encode.py replaces a plain lame invocation: it still encodes to
  # mp3 (nice/ionice'd, for the same real-time-thread-contention reason a
  # bare lame command needed them), but also correlates the recording's
  # start time against SvxLink's own log to tag the output filename with
  # which talkgroup/callsign was talking, and enforces an optional
  # RECORD_ONLY_TGS filter (see qso_recorder.php). Matches any of: RF.Guru's
  # original oggenc, an earlier lame-only version of this script, or an
  # already-niced lame version -- all migrate to the same script.
  if grep -qE '^ENCODER_CMD=(nice -n 19 ionice -c3 )?/usr/bin/(oggenc|lame)' "$SVX_CONF"; then
    sed -i 's|^ENCODER_CMD=.*$|ENCODER_CMD=/usr/bin/python3 /opt/rx-monitor/tag_and_encode.py \\"%f\\"|' "$SVX_CONF"
    echo "Replaced ENCODER_CMD with tag_and_encode.py (tags recordings with TG/callsign, enforces RECORD_ONLY_TGS)."
    RESTART_NEEDED=1
  fi

  if grep -q '^#QSO_RECORDER=8:QsoRecorder$' "$SVX_CONF"; then
    sed -i 's/^#QSO_RECORDER=8:QsoRecorder$/QSO_RECORDER=8:QsoRecorder/' "$SVX_CONF"
    echo "Enabled QSO_RECORDER in [SimplexLogic]."
    RESTART_NEEDED=1
  elif grep -q '^QSO_RECORDER=' "$SVX_CONF"; then
    echo "QSO_RECORDER already enabled -- leaving it as-is."
  else
    echo "No QSO_RECORDER= line found (commented or otherwise) in" \
         "[SimplexLogic] -- not adding one automatically." >&2
  fi

  # Per-QSO files (not one giant recording) and a sane disk cap. Only added
  # if missing, so a value someone already tuned via the Setup page (which
  # writes MAX_DIRSIZE) is never overwritten here.
  #
  # DEFAULT_ACTIVE=0: recording starts OFF. QSO_RECORDER=8:QsoRecorder above
  # still wires the recorder into the audio path so the dashboard's QSO Log
  # on/off toggle (DTMF 80#/81#) works -- it's just not running from boot.
  # Off-by-default because (a) it has a real, measured CPU cost even when
  # idle-active (see the svxlinkuhf performance notes) and (b) silently
  # recording every transmission isn't something this project should turn
  # on for someone by default -- that's a per-node decision to opt into.
  for kv in "MIN_TIME=1500" "QSO_TIMEOUT=5" "DEFAULT_ACTIVE=0" "MAX_DIRSIZE=2000"; do
    key="${kv%%=*}"
    if ! grep -q "^${key}=" "$SVX_CONF"; then
      sed -i "/^\[QsoRecorder\]$/a ${kv}" "$SVX_CONF"
      echo "Added ${kv} to [QsoRecorder]."
      RESTART_NEEDED=1
    fi
  done

  if [ "$RESTART_NEEDED" -eq 1 ] && systemctl is-active --quiet svxlink; then
    systemctl restart svxlink
    echo "Restarted svxlink to apply the QSO Recorder config."
  fi
fi

echo "--- RX Monitor / QSO Log: systemd services ---"
cp "$SCRIPT_DIR/rx-monitor/rx-monitor-proxy.service" /etc/systemd/system/rx-monitor-proxy.service
cp "$SCRIPT_DIR/rx-monitor/rx-monitor-feed.service" /etc/systemd/system/rx-monitor-feed.service
systemctl daemon-reload
systemctl enable --now rx-monitor-proxy.service
systemctl enable --now rx-monitor-feed.service

echo "--- RX Monitor / QSO Log: done ---"
echo "Note: port 8080 is now owned by rx-monitor-proxy.service (the dashboard's"
echo "RX Monitor button expects it there). Don't run a dev/test server on 8080"
echo "on a node with this installed."
