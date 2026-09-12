#!/usr/bin/env python3
# ENCODER_CMD replacement for SvxLink's QSO Recorder. Encodes the finished
# recording to mp3 (nice/ionice'd, same as the plain lame command it
# replaces -- see svxlinkuhf's performance notes on why) and, when
# possible, tags the output filename with which talkgroup/callsign was
# actually talking, correlated from SvxLink's own log around the
# recording's start time (the "Talker start on TG #<n>: <call>" line
# ReflectorLogic prints). A purely local transmission (no reflector
# talker) has no such line and stays tagged "none" -- that's expected,
# not a failure.
#
# Also enforces RECORD_ONLY_TGS, an optional key in svxlink.conf's
# [QsoRecorder] section that SvxLink itself never reads (a plain
# comma-separated list of TG numbers) -- if set and the detected TG isn't
# in it, the encoded file is discarded instead of kept. Recordings with
# no detected TG (local-only) are always kept regardless of this filter,
# since they can't be reliably attributed to any monitored talkgroup and
# dropping them would be a worse anti-piracy/audit gap than keeping one
# extra file.
#
# Usage: tag_and_encode.py <input.wav>   (called as ENCODER_CMD's %f)
import os
import re
import subprocess
import sys
from datetime import datetime

LOG_FILE = "/var/log/svxlink"
SVX_CONF = "/etc/svxlink/svxlink.conf"
LOG_TAIL_LINES = 20000

# Set by dashboard/include/rx_monitor_toggle.php's "start" action when it
# turns the recorder on just to feed RX Monitor's audio tap (not because
# real QSO logging was asked for) -- its mere presence, not its content,
# is what matters here. Checked once per recording, at ENCODER_CMD time,
# so a straggler that started before "stop" was clicked still gets
# discarded correctly rather than kept -- see stop_monitor_only.sh's own
# grace-delay comment for why that race is handled there, not here.
MONITOR_ONLY_FLAG = "/dev/shm/hotspot_rx_monitor_only"

TALKER_RE = re.compile(
    r'^(\w{3} \w{3} \d{2} \d{2}:\d{2}:\d{2} \d{4}): \S+: Talker start on TG #(\d+): (\S+)'
)
NAME_RE = re.compile(
    r'^qsorec_(?P<logic>.+?)_(?P<ymd>\d{4}-\d{2}-\d{2})_(?P<his>\d{6})'
    r'(?:_\d{4}-\d{2}-\d{2}_\d{6})?$'
)


def _read_conf_key(key):
    """Reads a single QsoRecorder key from svxlink.conf. [ \\t]*, not \\s*,
    around "=" -- \\s matches newlines too, so \\s* right after "=" would
    swallow the line break and, when the value is empty, greedily grab the
    *next* line's content as if it were the value. Confirmed live: with
    RECORD_ONLY_TGS= (empty, meaning "record everything") on its own line
    right before "[Rx1]", this used to capture the literal string "[Rx1]"
    as the "allowed" talkgroup -- which no real TG number ever matches, so
    every recording with a detected TG got silently deleted. Cost a whole
    evening of QSOs on svxlinkuhf (2026-09-05)."""
    try:
        with open(SVX_CONF) as f:
            text = f.read()
    except OSError:
        return None
    m = re.search(r'^[ \t]*' + re.escape(key) + r'[ \t]*=[ \t]*(.*)$', text, re.MULTILINE)
    return m.group(1).strip() if m else None


def load_record_only_tgs():
    value = _read_conf_key("RECORD_ONLY_TGS")
    if not value:
        return None
    return {v.strip() for v in value.split(",") if v.strip()}


def load_max_recordings():
    """MAX_RECORDINGS, an optional key SvxLink itself never reads (like
    RECORD_ONLY_TGS) -- caps the *count* of kept recordings, on top of
    SvxLink's own MAX_DIRSIZE (a total-megabytes cap). 0 or unset: no
    limit, matching svxlink.conf(5)'s own convention for MAX_DIRSIZE."""
    value = _read_conf_key("MAX_RECORDINGS")
    if not value or not value.isdigit():
        return 0
    return int(value)


def prune_to_max(directory, max_count):
    if max_count <= 0:
        return
    files = [
        os.path.join(directory, f) for f in os.listdir(directory)
        if f.startswith("qsorec_") and f.endswith(".mp3")
    ]
    files.sort(key=os.path.getmtime)
    for f in files[:max(0, len(files) - max_count)]:
        try:
            os.remove(f)
        except OSError:
            pass


def find_talker(start_dt):
    """Last "Talker start on TG #<n>: <call>" at or before start_dt."""
    try:
        with open(LOG_FILE, errors="replace") as f:
            lines = f.readlines()[-LOG_TAIL_LINES:]
    except OSError:
        return None, None

    tg = call = None
    for line in lines:
        m = TALKER_RE.match(line)
        if not m:
            continue
        try:
            ts = datetime.strptime(m.group(1), "%a %b %d %H:%M:%S %Y")
        except ValueError:
            continue
        if ts <= start_dt:
            tg, call = m.group(2), m.group(3)
        else:
            break
    return tg, call


def main():
    in_path = sys.argv[1]

    # Monitor-only session: RX Monitor wanted the live audio, nothing
    # about this recording was ever meant to be kept. Skip lame (the
    # actual CPU cost) and the log-tail entirely, not just the file --
    # confirmed elsewhere in this project that re-probing/re-encoding
    # "for a moment, then discard" adds up fast on this hardware (see the
    # dashboard-v2 QSO Log collector's own CPU-usage incident).
    if os.path.exists(MONITOR_ONLY_FLAG):
        os.remove(in_path)
        return

    directory, filename = os.path.split(in_path)
    base = filename.rsplit(".", 1)[0]

    m = NAME_RE.match(base)
    tg = call = None
    if m:
        try:
            start_dt = datetime.strptime(
                m.group("ymd") + m.group("his"), "%Y-%m-%d%H%M%S"
            )
            tg, call = find_talker(start_dt)
        except ValueError:
            pass
        out_base = "qsorec_{}_TG{}_{}_{}_{}".format(
            m.group("logic"), tg or "none", call or "none",
            m.group("ymd"), m.group("his"),
        )
    else:
        out_base = base

    out_path = os.path.join(directory, out_base + ".mp3")

    subprocess.run(
        ["nice", "-n", "19", "ionice", "-c3", "/usr/bin/lame", "--quiet", in_path, out_path],
        check=True,
    )
    os.remove(in_path)

    record_only = load_record_only_tgs()
    if record_only is not None and tg is not None and tg not in record_only:
        os.remove(out_path)
        return

    prune_to_max(directory, load_max_recordings())


if __name__ == "__main__":
    main()
