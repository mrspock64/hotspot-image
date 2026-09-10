#!/bin/bash
#
# dashboard-v2 Signal module's collector -- runs every 60s (see
# dashboard-v2-collect-signal.timer). Two real, independently-sourced
# numbers, per docs/dashboard-v2-brief.md's "real numbers, not fake data":
#
#  - dBm + link quality: /proc/net/wireless (the interface's own driver-
#    reported figures -- see the format comment below, this is NOT derived
#    from anything else).
#  - Packet loss: a single ping to the default gateway per run, kept as a
#    ring buffer of the last 20 results ("last 20 probes", matching the
#    concept mockup's own wording) rather than firing 20 pings back-to-back
#    every cycle, which would both be slower and load the link itself right
#    as we're trying to measure it.
#
# Output is one JSON snapshot -- api/signal.php in dashboard-v2/ just reads
# it straight through (see that file for why a thin read, not fresh work
# per HTTP request, is the right shape here). History is kept inline in the
# same snapshot (read-modify-write each run) rather than a separate log --
# at one sample/minute for 6h that's 360 small entries, not worth a second
# file to manage.
#
set -euo pipefail

IFACE="wlan0"
STATE_DIR="/var/cache/hotspot-image"
STATE_FILE="$STATE_DIR/dashboard-v2-signal.json"
HISTORY_WINDOW_SECONDS=$((6 * 3600))
LOSS_RING_SIZE=20

mkdir -p "$STATE_DIR"

# --- dBm + link quality, straight from the kernel's own wireless stats ---
# Format (see /usr/src/linux/net/wireless/wext-proc.c upstream):
#   Inter-| sta-|   Quality        |   Discarded packets               | Missed | WE
#    face | tus | link level noise |  nwid  crypt   frag  retry   misc | beacon | XX
#   wlan0: 0000   43.  -67.  -256        0      0      0     32      0        0
# link/level/noise carry a trailing "." from the kernel's %d. format, not a
# decimal point -- stripped below. noise is commonly a fixed sentinel
# (-256 here) on drivers that don't implement it; not used.
line=$(grep "^ *${IFACE}:" /proc/net/wireless 2>/dev/null || true)
if [ -z "$line" ]; then
  echo "No /proc/net/wireless entry for $IFACE -- not connected or wrong interface name?" >&2
  exit 1
fi
quality=$(echo "$line" | awk '{print $3}' | tr -d '.')
dbm=$(echo "$line" | awk '{print $4}' | tr -d '.')

# --- one gateway ping this cycle, folded into a 20-run ring buffer ---
gateway=$(ip route 2>/dev/null | awk '/^default/ {print $3; exit}')
ping_ok=0
if [ -n "$gateway" ] && ping -c 1 -W 1 "$gateway" >/dev/null 2>&1; then
  ping_ok=1
fi

now=$(date +%s)
now_iso=$(date -u -Is)

# python3 does the actual JSON read-modify-write -- correct JSON handling
# (escaping, etc.) without reaching for jq, which isn't guaranteed present
# on this image (see other lib/ scripts' own python3-over-jq choices, e.g.
# tag_and_encode.py).
python3 - "$STATE_FILE" "$dbm" "$quality" "$ping_ok" "$now" "$now_iso" "$HISTORY_WINDOW_SECONDS" "$LOSS_RING_SIZE" "$IFACE" <<'PYEOF'
import json
import sys

state_file, dbm, quality, ping_ok, now, now_iso, window, ring_size, iface = sys.argv[1:10]
dbm = int(dbm)
quality = int(quality)
ping_ok = int(ping_ok)
now = int(now)
window = int(window)
ring_size = int(ring_size)

try:
    with open(state_file) as f:
        state = json.load(f)
except (OSError, ValueError):
    state = {}

history = state.get("history", [])
history.append({"t": now, "dbm": dbm})
cutoff = now - window
history = [h for h in history if h["t"] >= cutoff]

loss_ring = state.get("loss_ring", [])
loss_ring.append(ping_ok)
loss_ring = loss_ring[-ring_size:]
loss_pct = round(100 * (1 - sum(loss_ring) / len(loss_ring))) if loss_ring else 0

out = {
    "iface": iface,
    "dbm": dbm,
    "quality": quality,
    "quality_max": 70,  # driver's own scale (wext), not derived
    "loss_pct": loss_pct,
    "loss_probes": len(loss_ring),
    "history": history,
    "updated_at": now_iso,
}
with open(state_file, "w") as f:
    json.dump(out, f)
PYEOF
