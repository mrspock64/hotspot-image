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
#
# Separately watches CPU temperature -- relevant now that Turbo mode
# (dashboard/include/perf_mode.php) runs this hardware at full clock/core
# count inside RF.Guru's own plastic hotspot case, which traps heat more
# than the open-air testing that confirmed Turbo was safe. Same pattern:
# always logged with its own STATE_FILE_TEMP badge, optionally (LOAD_
# MONITOR_AUTO_STOP_SVXLINK=1, off by default, toggle on the Power page)
# stops the svxlink service itself once sustained -- a more severe
# response than pausing QSO recording, reserved for actual thermal
# danger rather than the load/iowait/memory pattern above. Never
# restarts it automatically either.
#
# Also optionally (LOAD_MONITOR_AUTO_ALERT_TX=1, off by default, toggle
# on the Power page) transmits D921# -- the Sound Library's alert-message
# slot (dashboard/include/sound_library.php) -- once when sustained high
# temperature is first detected, so anyone monitoring the frequency
# actually hears why the node went quiet. Fired before auto-stop, not
# after, so the alert goes out even when both are enabled. Deliberately
# fires only once per episode (not every ~30s while still hot) rather
# than repeatedly keying up the radio, which would itself keep the PA
# warm during the exact condition being warned about.
#
# Swap usage is folded into the same overload bucket as load/iowait/mem
# above (same badge, same SUSTAINED_CHECKS/auto-pause-QSO response) --
# found live on svxlinkmobile (2026-09-10): this hardware's stock ~200MB
# swap filled completely (100%) during setup.sh's from-source SvxLink
# build, with the compiler stuck in D-state (blocked on I/O) rather than
# actually progressing. That specific case is now handled by setup.sh's
# own temporary build-time swapfile, but the same exhaustion pattern
# could plausibly happen during normal operation too (e.g. QSO Recorder
# plus a load spike), so it's worth the same ongoing watch as the other
# three metrics, not just a one-off fix.
set -u

SVX_CONF=/etc/svxlink/svxlink.conf
STATE_FILE=/var/cache/hotspot-image/load_warning
STATE_FILE_TEMP=/var/cache/hotspot-image/temp_warning
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
# 90% leaves some headroom below "completely full" (the observed failure
# state) while still catching genuine exhaustion rather than routine use.
SWAP_USED_PCT_THRESHOLD=90
# Pi firmware soft-throttles at 80°C, hard-throttles/underclocks at 85°C
# -- this default (75°C) leaves real margin below either, configurable
# on the Power page. Confirmed live 2026-09-10: 51.5°C after 22 minutes
# in the real case at full Turbo clock, so this is nowhere close under
# normal conditions -- it's a genuine safety net, not a routine trigger.
TEMP_THRESHOLD_DEFAULT_C=75
TEMP_SUSTAINED_CHECKS=4

mkdir -p "$(dirname "$STATE_FILE")"

overload_count=0
temp_overload_count=0
alert_tx_sent=0

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

# Percentage of configured swap currently in use -- 0 if no swap is
# configured at all (SwapTotal=0), which never exceeds any real
# threshold and so never triggers, rather than dividing by zero.
read_swap_used_pct() {
    awk '
        /SwapTotal:/ { total = $2 }
        /SwapFree:/  { free = $2 }
        END {
            if (total <= 0) { print 0; exit }
            print int((total - free) * 100 / total)
        }
    ' /proc/meminfo
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

# Millidegrees in sysfs -- same source dashboard/include/perf_mode.php's
# sibling tools (and the health.php this project doesn't use but shares
# a node with) already read. 0 if unreadable, which never exceeds any
# sane threshold -- fails safe (no action) rather than fails loud.
read_temp_c() {
    if [ -r /sys/class/thermal/thermal_zone0/temp ]; then
        awk '{print int($1 / 1000)}' /sys/class/thermal/thermal_zone0/temp
    else
        echo 0
    fi
}

get_temp_threshold() {
    local v
    v=$(grep -E '^[ \t]*LOAD_MONITOR_TEMP_THRESHOLD_C[ \t]*=' "$SVX_CONF" 2>/dev/null \
        | tail -n1 | cut -d'=' -f2 | tr -d '[:space:]')
    case "$v" in
        ''|*[!0-9]*) echo "$TEMP_THRESHOLD_DEFAULT_C" ;;
        *) echo "$v" ;;
    esac
}

auto_stop_svxlink_enabled() {
    grep -E '^[ \t]*LOAD_MONITOR_AUTO_STOP_SVXLINK[ \t]*=' "$SVX_CONF" 2>/dev/null \
        | tail -n1 | cut -d'=' -f2 | tr -d '[:space:]' | grep -q '^1$'
}

stop_svxlink() {
    sudo service svxlink stop 2>&1
}

auto_alert_tx_enabled() {
    grep -E '^[ \t]*LOAD_MONITOR_AUTO_ALERT_TX[ \t]*=' "$SVX_CONF" 2>/dev/null \
        | tail -n1 | cut -d'=' -f2 | tr -d '[:space:]' | grep -q '^1$'
}

# Same double-send mitigation as everywhere else this DTMF relay is used
# (dashboard/include/tts_message.php's sendDtmfReliable(), qso_simulate.sh)
# -- RF.Guru's own nc-based relay occasionally drops leading digits.
send_alert_tx() {
    if [ ! -f /etc/svxlink/alert_message.wav ]; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') LOAD_MONITOR_AUTO_ALERT_TX is on but no alert message is saved (Sound Library) -- nothing to send"
        return
    fi
    /usr/sbin/hotspot_dtmf 'D921#' >/dev/null 2>&1
    sleep 0.3
    /usr/sbin/hotspot_dtmf 'D921#' >/dev/null 2>&1
    echo "$(date '+%Y-%m-%d %H:%M:%S') sent D921# temperature alert"
}

while true; do
    load1=$(awk '{print $1}' /proc/loadavg)
    iowait=$(read_iowait)
    mem_available_mb=$(awk '/MemAvailable/ {print int($2 / 1024)}' /proc/meminfo)
    swap_used_pct=$(read_swap_used_pct)
    temp_c=$(read_temp_c)
    temp_threshold=$(get_temp_threshold)

    over_load=$(awk -v l="$load1" -v t="$LOAD_THRESHOLD" 'BEGIN{print (l > t) ? 1 : 0}')
    over_iowait=0
    [ "$iowait" -gt "$IOWAIT_THRESHOLD" ] && over_iowait=1
    over_mem=0
    [ "$mem_available_mb" -lt "$MEM_AVAILABLE_THRESHOLD_MB" ] && over_mem=1
    over_swap=0
    [ "$swap_used_pct" -gt "$SWAP_USED_PCT_THRESHOLD" ] && over_swap=1
    over_temp=0
    [ "$temp_c" -gt "$temp_threshold" ] && over_temp=1

    if [ "$over_load" = 1 ] || [ "$over_iowait" = 1 ] || [ "$over_mem" = 1 ] || [ "$over_swap" = 1 ]; then
        overload_count=$((overload_count + 1))
        echo "$(date '+%Y-%m-%d %H:%M:%S') overload check ${overload_count}/${SUSTAINED_CHECKS} -- load1=$load1 iowait=${iowait}% mem_available=${mem_available_mb}MB swap=${swap_used_pct}% temp=${temp_c}C"
    else
        if [ "$overload_count" -gt 0 ]; then
            echo "$(date '+%Y-%m-%d %H:%M:%S') load back to normal -- load1=$load1 iowait=${iowait}% mem_available=${mem_available_mb}MB swap=${swap_used_pct}% temp=${temp_c}C"
        fi
        overload_count=0
        rm -f "$STATE_FILE"
    fi

    if [ "$overload_count" -ge "$SUSTAINED_CHECKS" ]; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') SUSTAINED OVERLOAD -- load1=$load1 iowait=${iowait}% mem_available=${mem_available_mb}MB swap=${swap_used_pct}% temp=${temp_c}C"
        echo "sustained overload since $(date '+%Y-%m-%d %H:%M:%S')" > "$STATE_FILE"

        if auto_pause_enabled && qso_recorder_active; then
            echo "$(date '+%Y-%m-%d %H:%M:%S') LOAD_MONITOR_AUTO_PAUSE_QSO is on -- pausing QSO Recorder"
            result=$(pause_qso_recorder)
            echo "$(date '+%Y-%m-%d %H:%M:%S') pause_qso_recorder: $result"
        fi
    fi

    if [ "$over_temp" = 1 ]; then
        temp_overload_count=$((temp_overload_count + 1))
        echo "$(date '+%Y-%m-%d %H:%M:%S') temp check ${temp_overload_count}/${TEMP_SUSTAINED_CHECKS} -- temp=${temp_c}C (threshold ${temp_threshold}C)"
    else
        if [ "$temp_overload_count" -gt 0 ]; then
            echo "$(date '+%Y-%m-%d %H:%M:%S') temp back to normal -- temp=${temp_c}C"
        fi
        temp_overload_count=0
        alert_tx_sent=0
        rm -f "$STATE_FILE_TEMP"
    fi

    if [ "$temp_overload_count" -ge "$TEMP_SUSTAINED_CHECKS" ]; then
        echo "$(date '+%Y-%m-%d %H:%M:%S') SUSTAINED HIGH TEMPERATURE -- temp=${temp_c}C (threshold ${temp_threshold}C)"
        echo "sustained high temperature since $(date '+%Y-%m-%d %H:%M:%S')" > "$STATE_FILE_TEMP"

        if [ "$alert_tx_sent" = 0 ] && auto_alert_tx_enabled; then
            send_alert_tx
            alert_tx_sent=1
        fi

        if auto_stop_svxlink_enabled; then
            svxlink_status=$(systemctl is-active svxlink 2>/dev/null || true)
            if [ "$svxlink_status" = "active" ]; then
                echo "$(date '+%Y-%m-%d %H:%M:%S') LOAD_MONITOR_AUTO_STOP_SVXLINK is on -- stopping svxlink"
                result=$(stop_svxlink)
                echo "$(date '+%Y-%m-%d %H:%M:%S') stop_svxlink: $result"
            fi
        fi
    fi

    sleep "$CHECK_INTERVAL"
done
