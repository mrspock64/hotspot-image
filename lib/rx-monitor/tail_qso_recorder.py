#!/usr/bin/env python3
# Feeds RX Monitor from SvxLink's own QSO Recorder instead of a raw ALSA
# capture tap. The recorder (see svxlink.conf(5) "QSO Recorder Section")
# writes all audio from receivers, MODULES, and LOGIC LINKS -- so unlike a
# capture-only tap, this also carries reflector-relayed QSOs being sent to
# the local transmitter, not just this node's own local RX.
#
# SvxLink creates a hidden ".qsorec_<Logic>.wav" placeholder (0 bytes) the
# instant a QSO starts, renames/writes the real "qsorec_<Logic>_<ts>.wav"
# once actual audio arrives, then on close hands it to ENCODER_CMD (oggenc
# in this config), which converts it to .ogg and removes the .wav. So: any
# *.wav file present in REC_DIR is -- by construction -- the one currently
# being recorded, and there is never more than one at a time.
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
    now = time.time()
    candidates = [
        p for p in glob.glob(os.path.join(REC_DIR, "*qsorec_*.wav"))
        if os.path.getsize(p) > WAV_HEADER_BYTES and (now - os.path.getmtime(p)) < 10
    ]
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
