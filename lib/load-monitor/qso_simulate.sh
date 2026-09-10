#!/bin/bash
#
# Simulates a real QSO's transmit pattern -- variable-length transmissions
# separated by pauses -- by directly keying PTT (the exact same GPIO line
# svxlink.conf's own [Tx1] PTT_GPIOD_LINE uses, active-low) and playing a
# real spoken voice clip through the same audio device SvxLink itself
# transmits through. Not a novel risky operation: this is the identical
# GPIO+audio pathway SvxLink already exercises every time it plays an
# announcement, just driven directly and on a simulated conversational
# schedule instead of SvxLink's own event scheduler.
#
# Exists to answer a specific question: how much does the radio module's
# own PA contribute to heat inside RF.Guru's plastic case, separate from
# CPU/SoC heat (which Turbo mode already measured safe in open air) --
# the working theory being that's *why* RF.Guru's own underclock/
# undervolt exists as a default in the first place.
#
# Stops svxlink for the duration by default (it would otherwise be
# holding the same GPIO line and audio device) and restarts it on the
# way out -- trap-based, so a kill/abort/crash mid-run still brings the
# radio back rather than leaving it down.
#
# KEEP_SVXLINK=1 skips both the stop and the restart, leaving svxlink
# running throughout -- for measuring combined real-world heat (CPU load
# from svxlink itself + this script's own TX) rather than isolating the
# radio module's own contribution. Real tradeoff, not just a formality:
# svxlink and this script then both reach for the same GPIO PTT line and
# audio device at once, so if a real transmission happens to land at the
# same moment (reflector traffic, svxlink's own periodic ID), behavior
# between the two is not guaranteed to be clean -- expect possible audio
# glitches or PTT flicker, not hardware damage. Off by default.
#
# Usage: qso_simulate.sh <exchanges> <min_tx_s> <max_tx_s> <min_pause_s> <max_pause_s> [keep_svxlink]
set -u

EXCHANGES="${1:-12}"
MIN_TX="${2:-5}"
MAX_TX="${3:-20}"
MIN_PAUSE="${4:-2}"
MAX_PAUSE="${5:-5}"
KEEP_SVXLINK="${6:-0}"

PTT_CHIP=gpiochip0
PTT_LINE=16
VOICE_CLIP=/usr/share/svxlink/sounds/en_US/Core/please_identify.wav
PID_FILE=/var/cache/hotspot-image/qso_sim.pid
LOG_FILE=/var/cache/hotspot-image/qso_sim_log
RESULT_FILE=/var/cache/hotspot-image/qso_sim_result

mkdir -p "$(dirname "$LOG_FILE")"
echo $$ > "$PID_FILE"

PTT_PID=""
LOOP_PID=""

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

cleanup() {
    [ -n "$LOOP_PID" ] && kill "$LOOP_PID" 2>/dev/null
    [ -n "$PTT_PID" ] && kill "$PTT_PID" 2>/dev/null
    wait 2>/dev/null
    if [ "$KEEP_SVXLINK" != "1" ]; then
        log "Restarting svxlink"
        service svxlink restart >> "$LOG_FILE" 2>&1
    fi
    rm -f "$PID_FILE"
}
trap cleanup EXIT INT TERM

: > "$LOG_FILE"
log "###-START-###"

start_temp=$(read_temp_c)
peak_temp=$start_temp
log "Starting QSO simulation: $EXCHANGES exchanges, TX ${MIN_TX}-${MAX_TX}s, pause ${MIN_PAUSE}-${MAX_PAUSE}s"
log "Start temp: ${start_temp}C"

if [ "$KEEP_SVXLINK" = "1" ]; then
    log "Keeping svxlink running for this test (combined-load mode)"
else
    log "Stopping svxlink"
    service svxlink stop >> "$LOG_FILE" 2>&1
    sleep 2
fi

for i in $(seq 1 "$EXCHANGES"); do
    tx_len=$(( RANDOM % (MAX_TX - MIN_TX + 1) + MIN_TX ))
    pause_len=$(( RANDOM % (MAX_PAUSE - MIN_PAUSE + 1) + MIN_PAUSE ))

    temp_before=$(read_temp_c)
    log "Exchange $i/$EXCHANGES: TX ${tx_len}s (temp ${temp_before}C)"

    gpioset -l "$PTT_CHIP" "$PTT_LINE"=1 &
    PTT_PID=$!
    sleep 0.9   # TX_DELAY, matching svxlink.conf's own [Tx1] TX_DELAY

    ( while true; do aplay -q "$VOICE_CLIP" 2>/dev/null; done ) &
    LOOP_PID=$!
    sleep "$tx_len"
    kill "$LOOP_PID" 2>/dev/null
    wait "$LOOP_PID" 2>/dev/null
    LOOP_PID=""

    kill "$PTT_PID" 2>/dev/null
    wait "$PTT_PID" 2>/dev/null
    PTT_PID=""

    temp_now=$(read_temp_c)
    [ "$temp_now" -gt "$peak_temp" ] && peak_temp=$temp_now
    log "Exchange $i/$EXCHANGES done (temp ${temp_now}C, peak so far ${peak_temp}C) -- pausing ${pause_len}s"

    sleep "$pause_len"
done

end_temp=$(read_temp_c)
[ "$end_temp" -gt "$peak_temp" ] && peak_temp=$end_temp
log "Simulation complete. Start ${start_temp}C, peak ${peak_temp}C, end ${end_temp}C"
printf '{"start_temp":%d,"peak_temp":%d,"end_temp":%d,"exchanges":%d}\n' \
    "$start_temp" "$peak_temp" "$end_temp" "$EXCHANGES" > "$RESULT_FILE"
log "###-FINISH-####"
