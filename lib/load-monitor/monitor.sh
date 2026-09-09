#!/bin/bash
#
# Watches load average, I/O wait, and available memory for the pattern
# that caused a real live incident on svxlinkuhf (2026-09-09): 48.5%
# iowait correlated directly with ReflectorLogic UDP frame loss on the
# reflector connection, and disabling the QSO Recorder (which costs ~13
# points of CPU just being active, independent of actual disk I/O -- see
# the 2026-09-05 tuning notes) brought iowait back to 0% within seconds.
# This hardware (Pi Zero 2 W, 2 cores, 416MB RAM) has very little
# headroom, so "is it currently overloaded" is worth watching for on its
# own, not just diagnosing after the fact.
#
# Always logs sustained overload to this service's own journal and sets
# STATE_FILE so the dashboard header can show a warning badge -- both
# unconditional, no configuration needed. Optionally (LOAD_MONITOR_
# AUTO_PAUSE_QSO=1 in svxlink.conf's [Dashboard] section, off by default,
# toggle on the QSO Log page) also disables the QSO Recorder once overload
# has been sustained for SUSTAINED_CHECKS consecutive checks -- reusing
# the dashboard's own saveQsoRecorderSettings() via a one-line `php -r`
# rather than reimplementing its two-step "rewrite config + live DTMF"
# logic here. Never re-enables it automatically: that's a decision this
# script deliberately leaves to the sysop.
set -u

SVX_CONF=/etc/svxlink/svxlink.conf
STATE_FILE=/var/cache/hotspot-image/load_warning
CHECK_INTERVAL=30
SUSTAINED_CHECKS=4   # 4 * ~30s = ~2 minutes of sustained overload before acting
# Confirmed live 2026-09-09: the real incident was iowait, not load1 --
# svxlink alone routinely sits around load1=2.2-2.3 with iowait near 0%
# during completely normal reflector traffic on this 2-core box (it's
# genuinely using both cores, not stuck waiting on I/O), so a load1
# threshold anywhere near that would fire constantly on healthy days.
# Set well above that normal baseline -- iowait/memory are the more
# reliable signals for the actual danger pattern.
LOAD_THRESHOLD=3.5
IOWAIT_THRESHOLD=30
MEM_AVAILABLE_THRESHOLD_MB=40

mkdir -p "$(dirname "$STATE_FILE")"

overload_count=0

# %iowait since boot isn't useful on its own -- sample /proc/stat twice,
# a second apart, and diff the "iowait" jiffies field against total
# jiffies elapsed in that window.
read_iowait() {
    read -r _ u1 n1 s1 i1 io1 irq1 sirq1 _ < /proc/stat
    sleep 1
    read -r _ u2 n2 s2 i2 io2 irq2 sirq2 _ < /proc/stat
    total1=$((u1 + n1 + s1 + i1 + io1 + irq1 + sirq1))
    total2=$((u2 + n2 + s2 + i2 + io2 + irq2 + sirq2))
    diff_total=$((total2 - total1))
    diff_io=$((io2 - io1))
    if [ "$diff_total" -le 0 ]; then
        echo 0
    else
        echo $((diff_io * 100 / diff_total))
    fi
}

auto_pause_enabled() {
    grep -E '^[ \t]*LOAD_MONITOR_AUTO_PAUSE_QSO[ \t]*=' "$SVX_CONF" 2>/dev/null \
        | tail -n1 | cut -d'=' -f2 | tr -d '[:space:]' | grep -q '^1$'
}

qso_recorder_active() {
    grep -A20 '^\[QsoRecorder\]' "$SVX_CONF" 2>/dev/null \
        | grep -E '^[ \t]*DEFAULT_ACTIVE[ \t]*=' | head -n1 \
        | cut -d'=' -f2 | tr -d '[:space:]' | grep -q '^1$'
}

pause_qso_recorder() {
    php -r '
        require "/var/www/html/include/qso_recorder.php";
        $s = getQsoRecorderSettings();
        saveQsoRecorderSettings(false, $s["max_dirsize"], $s["qso_timeout"], $s["max_recordings"], $s["record_only_tgs"]);
        echo "done";
    ' 2>&1
}

while true; do
    load1=$(awk '{print $1}' /proc/loadavg)
    iowait=$(read_iowait)
    mem_available_mb=$(awk '/MemAvailable/ {print int($2 / 1024)}' /proc/meminfo)

    over_load=$(awk -v l="$load1" -v t="$LOAD_THRESHOLD" 'BEGIN{print (l > t) ? 1 : 0}')
    over_iowait=0
    [ "$iowait" -gt "$IOWAIT_THRESHOLD" ] && over_iowait=1
    over_mem=0
    [ "$mem_available_mb" -lt "$MEM_AVAILABLE_THRESHOLD_MB" ] && over_mem=1

    if [ "$over_load" = 1 ] || [ "$over_iowait" = 1 ] || [ "$over_mem" = 1 ]; then
        overload_count=$((overload_count + 1))
        echo "$(date '+%Y-%m-%d %H:%M:%S') overload check ${overload_count}/${SUSTAINED_CHECKS} -- load1=$load1 iowait=${iowait}% mem_available=${mem_available_mb}MB"
    else
        if [ "$overload_count" -gt 0 ]; then
            echo "$(date '+%Y-%m-%d %H:%M:%S') load back to normal -- load1=$load1 iowait=${iowait}% mem_available=${mem_available_mb}MB"
        fi
        overload_count=0
        rm -f "$STATE_FILE"
    fi

    if [ "$overload_count" -ge "$SUSTAINED_CHECKS" ]; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') SUSTAINED OVERLOAD -- load1=$load1 iowait=${iowait}% mem_available=${mem_available_mb}MB"
        echo "sustained overload since $(date '+%Y-%m-%d %H:%M:%S')" > "$STATE_FILE"

        if auto_pause_enabled && qso_recorder_active; then
            echo "$(date '+%Y-%m-%d %H:%M:%S') LOAD_MONITOR_AUTO_PAUSE_QSO is on -- pausing QSO Recorder"
            result=$(pause_qso_recorder)
            echo "$(date '+%Y-%m-%d %H:%M:%S') pause_qso_recorder: $result"
        fi
    fi

    sleep "$CHECK_INTERVAL"
done
