#!/usr/bin/env python3
# Feeds RX Monitor from SvxLink's own QSO Recorder instead of a raw ALSA
# capture tap. The recorder (see svxlink.conf(5) "QSO Recorder Section")
# writes all audio from receivers, MODULES, and LOGIC LINKS -- so unlike a
# capture-only tap, this also carries reflector-relayed QSOs being sent to
# the local transmitter, not just this node's own local RX.
#
# SvxLink writes to a hidden ".qsorec_<Logic>.wav" placeholder for the
# ENTIRE duration of a recording -- confirmed straight from SvxLink's own
# source (QsoRecorder.cpp): openFile() always writes to that fixed hidden
# name, and closeFile() is the ONLY place that renames it to the
# timestamped public "qsorec_<Logic>_<start>_<end>.wav" name, right before
# handing off to ENCODER_CMD. So the public name never exists while a QSO
# is actually in progress -- it appears at the very end, already finished.
#
# This used to glob for only the public name, on the (wrong) assumption
# that SvxLink renamed early. That's silent-by-design for normal
# short/spaced-out traffic (each recording closes and briefly appears
# under the public name within a second, so polling still catches most of
# it), but for anything that keeps re-triggering within QSO_TIMEOUT of
# itself (e.g. bursty digital-voice relay traffic) the recording can stay
# open under the hidden name for minutes, meaning RX Monitor goes
# completely silent for the whole thing and then, once it finally closes,
# dumps the entire backlog as one fast, out-of-time burst instead of
# playing live. Fixed by tailing the hidden name directly -- it's the
# SAME file handle SvxLink keeps appending to throughout the recording, so
# following it works exactly like tailing the public name always has;
# rename() makes the hidden path disappear the instant the QSO ends, which
# stream_file()'s existing os.path.exists() check already handles.
#
# No local RX and no active reflector talker -> no .wav file -> nothing
# forwarded -> RX Monitor is silent. That's correct, not a bug.
import glob
import os
import socket
import time

REC_DIR = "/var/spool/svxlink/qso_recorder"
UDP_IP = "127.0.0.1"
UDP_PORT = 2222
WAV_HEADER_BYTES = 44
CHUNK_BYTES = 1920  # ~20ms of mono 16-bit audio at 48000 Hz
POLL_INTERVAL = 0.5

sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)


def find_current_recording():
    # Ignore .wav files untouched for a while -- SvxLink writes to an
    # active recording continuously, so anything stale is a leftover
    # (e.g. ENCODER_CMD failed to convert+remove it), not something to
    # treat as live audio. Bit us once already: a broken ENCODER_CMD left
    # five real recordings stuck as .wav indefinitely.
    #
    # Two glob patterns, not one -- "*qsorec_*.wav" alone never matches a
    # leading dot (Python's glob follows shell semantics: a bare "*" does
    # not match a hidden file), so the in-progress ".qsorec_<Logic>.wav"
    # needs its own explicit pattern.
    now = time.time()
    paths = glob.glob(os.path.join(REC_DIR, "*qsorec_*.wav"))
    paths += glob.glob(os.path.join(REC_DIR, ".qsorec_*.wav"))
    candidates = []
    for p in paths:
        try:
            if os.path.getsize(p) > WAV_HEADER_BYTES and (now - os.path.getmtime(p)) < 10:
                candidates.append(p)
        except OSError:
            # Renamed/removed between the glob listing and this stat call
            # (e.g. ENCODER_CMD finished right as we scanned) -- not a
            # candidate anymore, just skip it rather than crash. This
            # raced tail_qso_recorder.py into a FileNotFoundError once.
            continue
    if not candidates:
        return None
    return max(candidates, key=os.path.getmtime)


def stream_file(path):
    with open(path, "rb") as f:
        f.seek(WAV_HEADER_BYTES)
        while True:
            data = f.read(CHUNK_BYTES)
            if data:
                sock.sendto(data, (UDP_IP, UDP_PORT))
                continue
            # Caught up to the writer -- keep following unless this file
            # has stopped being the current recording (QSO ended).
            if not os.path.exists(path) or find_current_recording() != path:
                return
            time.sleep(0.05)


def main():
    while True:
        current = find_current_recording()
        if current:
            stream_file(current)
        else:
            time.sleep(POLL_INTERVAL)


if __name__ == "__main__":
    main()
