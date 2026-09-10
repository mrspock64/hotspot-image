#!/bin/bash
#
# dashboard-v2 Node/vitals module's collector -- runs every 20s (see
# dashboard-v2-collect-node.timer). Everything here is either a cheap
# instant read (CPU load%, memory%) or something too slow/disk-heavy to
# do per HTTP request:
#
#  - I/O wait needs two /proc/stat samples a second apart (same technique
#    lib/load-monitor/monitor.sh's read_iowait() already uses for the
#    header's overload badge -- ported here rather than reimplemented
#    differently) -- blocking an API request on that would make the panel
#    visibly stall once a second.
#  - QSO disk use is `du -sm` over the whole recordings directory --
#    exactly the kind of disk I/O this hardware is sensitive to (see
#    monitor.sh's own extensive comments on the 2026-09-09 iowait
#    incident), so it belongs on a slow periodic timer, not fired on every
#    panel refresh.
#
# api/node.php merges this snapshot with a few genuinely-instant fields
# (hardware model, firmware version, locator, perf mode, temp, load avg,
# uptime) computed live per-request instead -- see that file for why those
# don't need to go through this collector at all.
#
set -euo pipefail

STATE_DIR="/var/cache/hotspot-image"
STATE_FILE="$STATE_DIR/dashboard-v2-node.json"
REPO_QSO_RECORDER_PHP="/opt/hotspot-image/dashboard/include/qso_recorder.php"

mkdir -p "$STATE_DIR"

# --- CPU load %, same formula as dashboard/include/system.php's $load ---
cpu_load1=$(awk '{print $1}' /proc/loadavg)
core_count=$(grep -c '^processor' /proc/cpuinfo)
cpu_load_pct=$(awk -v l="$cpu_load1" -v c="$core_count" 'BEGIN{printf "%.0f", (l/(c+1))*100}')

# --- Memory %, same formula as system.php's $free_mem ---
mem_used_pct=$(free -m | awk 'NR==2{printf "%.0f", $3*100/$2}')

# --- I/O wait %, ported from lib/load-monitor/monitor.sh's read_iowait() ---
read -r _ u1 n1 s1 i1 io1 irq1 sirq1 _ < /proc/stat
sleep 1
read -r _ u2 n2 s2 i2 io2 irq2 sirq2 _ < /proc/stat
total1=$((u1 + n1 + s1 + i1 + io1 + irq1 + sirq1))
total2=$((u2 + n2 + s2 + i2 + io2 + irq2 + sirq2))
diff_total=$((total2 - total1))
diff_io=$((io2 - io1))
if [ "$diff_total" -le 0 ]; then
  iowait_pct=0
else
  iowait_pct=$((diff_io * 100 / diff_total))
fi

# --- QSO disk use %: real REC_DIR size vs the dashboard's own configured
# cap, via the exact same functions the QSO Log page and monitor.sh
# already use (qsoRecorderDir()/getQsoRecorderSettings() in
# dashboard/include/qso_recorder.php) -- not re-parsed from svxlink.conf
# here a second time.
qso_disk_pct=0
if [ -f "$REPO_QSO_RECORDER_PHP" ]; then
  read -r rec_dir max_dirsize_mb <<< "$(php -r "
    require '$REPO_QSO_RECORDER_PHP';
    \$s = getQsoRecorderSettings();
    echo qsoRecorderDir() . ' ' . \$s['max_dirsize'];
  " 2>/dev/null || echo "")"
  if [ -n "${rec_dir:-}" ] && [ -d "$rec_dir" ] && [ "${max_dirsize_mb:-0}" -gt 0 ] 2>/dev/null; then
    used_mb=$(du -sm "$rec_dir" 2>/dev/null | awk '{print $1}')
    qso_disk_pct=$(awk -v u="${used_mb:-0}" -v m="$max_dirsize_mb" 'BEGIN{printf "%.0f", (u/m)*100}')
  fi
fi

now_iso=$(date -u -Is)

python3 -c "
import json, sys
json.dump({
    'cpu_load_pct': int('$cpu_load_pct'),
    'mem_used_pct': int('$mem_used_pct'),
    'iowait_pct': int('$iowait_pct'),
    'qso_disk_pct': int('$qso_disk_pct'),
    'updated_at': '$now_iso',
}, open('$STATE_FILE', 'w'))
"
