#!/bin/bash
# Ends a "monitor-only" RX Monitor session, started backgrounded by
# dashboard/include/rx_monitor_toggle.php's "stop" action. Delayed on
# purpose -- clicking stop mid-transmission shouldn't cut the QSO
# Recorder out from under whatever's still being captured; the grace
# period lets it finish (tag_and_encode.py itself discards the result,
# see MONITOR_ONLY_FLAG check there) before the recorder actually goes
# off.
#
# Usage: stop_monitor_only.sh <token> <delay_seconds>
set -u

TOKEN="$1"
DELAY="$2"
FLAG_FILE="/dev/shm/hotspot_rx_monitor_only"
SVX_CONF="/etc/svxlink/svxlink.conf"

sleep "$DELAY"

# If the flag is gone, or now holds a different token, a newer
# start/stop session has already taken over (or someone already turned
# real logging on/off by hand) -- leave it alone entirely rather than
# stepping on whatever that session is doing.
if [ ! -f "$FLAG_FILE" ] || [ "$(cat "$FLAG_FILE" 2>/dev/null)" != "$TOKEN" ]; then
  exit 0
fi

# Real logging may have been switched on for real (QSO Log page) while
# this session was running -- if so, that intent wins: leave the
# recorder on, just drop our own flag.
active=$(grep -E '^[ \t]*DEFAULT_ACTIVE[ \t]*=' "$SVX_CONF" 2>/dev/null | tail -n1 | cut -d'=' -f2 | tr -d '[:space:]')
if [ "$active" != "1" ]; then
  /usr/sbin/hotspot_dtmf "80#"
fi

rm -f "$FLAG_FILE"
