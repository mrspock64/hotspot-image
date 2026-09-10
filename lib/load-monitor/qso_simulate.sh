#!/bin/bash
#
# Simulates a real QSO's rhythm -- repeated transmissions with pauses --
# by triggering SvxLink's own built-in D911# command (spells out the
# node's IP address, see dtmf_cmd_received in Logic.tcl) via the same
# DTMF relay every dashboard button already uses, instead of directly
# manipulating GPIO/audio ourselves.
#
# That earlier approach (raw gpioset + aplay, stopping svxlink to free
# the hardware) turned out fragile in practice: two separate live
# incidents where Abort didn't actually stop the transmission, because
# killing our own wrapper process doesn't automatically kill children it
# spawned (a backgrounded aplay loop, a backgrounded gpioset process) --
# they kept running orphaned. This version sidesteps the whole problem
# class: it never holds the GPIO line or the audio device itself, so
# there is nothing of ours to orphan. SvxLink stays running throughout
# and owns the entire PTT/timing/audio lifecycle for every transmission,
# the same proven mechanism it already uses for real traffic -- and
# because of that, this also safely coexists with actual reflector
# traffic and shows up on the portal like a real transmission would,
# unlike the old approach which had to stop svxlink (and the reflector
# connection with it) for the whole test.
#
# Abort is now just "stop asking for a new one" -- there is no process
# to forcibly kill. Whatever SvxLink is doing right now finishes on its
# own, bounded, within a few seconds, exactly as it always does.
#
# Usage: qso_simulate.sh <exchanges> <min_pause_s> <max_pause_s>
set -u

EXCHANGES="${1:-12}"
MIN_PAUSE="${2:-3}"
MAX_PAUSE="${3:-8}"

# D920# plays the dashboard's saved custom message (Radio Test page) if
# one has been generated; D911# (IP readout) is the fallback when none
# has been saved yet -- see events.d/Logic.tcl and dashboard/include/
# tts_message.php.
if [ -f /etc/svxlink/radiotest_message.wav ]; then
    DTMF_CMD='D920#'
else
    DTMF_CMD='D911#'
fi
DTMF_SETTLE_S=8   # observed real duration of these announcements

PID_FILE=/var/cache/hotspot-image/qso_sim.pid
LOCK_FILE=/var/cache/hotspot-image/qso_sim.lock
LOG_FILE=/var/cache/hotspot-image/qso_sim_log
RESULT_FILE=/var/cache/hotspot-image/qso_sim_result

mkdir -p "$(dirname "$LOG_FILE")"

# Same defense-in-depth as before: an unconditional, kernel-enforced
# exclusive lock so a second instance can never run concurrently with
# this one, no matter what caused a double-launch.
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') Another instance is already running -- refusing to start." >> "$LOG_FILE"
    exit 1
fi

echo $$ > "$PID_FILE"

STOP_REQUESTED=0
on_signal() {
    STOP_REQUESTED=1
}
trap on_signal INT TERM

cleanup() {
    rm -f "$PID_FILE"
}
trap cleanup EXIT

log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') $*" >> "$LOG_FILE"
}

read_temp_c() {
    if [ -r /sys/class/thermal/thermal_zone0/temp ]; then
        awk '{print int($1 / 1000)}' /sys/class/thermal/thermal_zone0/temp
    else
        echo 0
    fi
}

: > "$LOG_FILE"
log "###-START-###"

start_temp=$(read_temp_c)
peak_temp=$start_temp
log "Starting QSO simulation: $EXCHANGES exchanges via SvxLink's own $DTMF_CMD command, pause ${MIN_PAUSE}-${MAX_PAUSE}s"
log "Start temp: ${start_temp}C"

for i in $(seq 1 "$EXCHANGES"); do
    if [ "$STOP_REQUESTED" = 1 ]; then
        log "Stop requested -- ending after $((i - 1))/$EXCHANGES exchanges"
        break
    fi

    pause_len=$(( RANDOM % (MAX_PAUSE - MIN_PAUSE + 1) + MIN_PAUSE ))
    temp_before=$(read_temp_c)
    log "Exchange $i/$EXCHANGES: sending $DTMF_CMD (temp ${temp_before}C)"

    # RF.Guru's own DTMF relay occasionally drops leading digits -- same
    # known race documented for TG select (dashboard/include/buttons.php)
    # and confirmed live for this command too. A resend a moment later
    # reliably lands; a double-play if both happen to land is a minor
    # annoyance, not a wrong second effect.
    /usr/sbin/hotspot_dtmf "$DTMF_CMD" >> "$LOG_FILE" 2>&1
    sleep 0.3
    /usr/sbin/hotspot_dtmf "$DTMF_CMD" >> "$LOG_FILE" 2>&1
    sleep "$DTMF_SETTLE_S"

    temp_now=$(read_temp_c)
    [ "$temp_now" -gt "$peak_temp" ] && peak_temp=$temp_now
    log "Exchange $i/$EXCHANGES done (temp ${temp_now}C, peak so far ${peak_temp}C) -- pausing ${pause_len}s"

    if [ "$STOP_REQUESTED" = 1 ]; then
        log "Stop requested -- ending after $i/$EXCHANGES exchanges"
        break
    fi
    sleep "$pause_len"
done

end_temp=$(read_temp_c)
[ "$end_temp" -gt "$peak_temp" ] && peak_temp=$end_temp
log "Simulation complete. Start ${start_temp}C, peak ${peak_temp}C, end ${end_temp}C"
printf '{"start_temp":%d,"peak_temp":%d,"end_temp":%d,"exchanges":%d}\n' \
    "$start_temp" "$peak_temp" "$end_temp" "$EXCHANGES" > "$RESULT_FILE"
log "###-FINISH-####"
