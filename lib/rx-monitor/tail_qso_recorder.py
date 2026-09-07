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
# is actually in progress -- only once it's already finished. We tail the
# hidden name directly for exactly that reason (see find_current_recording
# and stream_file below for the two bugs that took to get this right).
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
    # Only the hidden name, deliberately -- per SvxLink's own source
    # (QsoRecorder.cpp), the public timestamped name is created by
    # closeFile()'s rename() and ONLY by that, meaning a file under the
    # public name is -- always, by construction -- already finished, on
    # its way to ENCODER_CMD. An earlier version of this function also
    # matched the public name as a "maybe still live" candidate; on rapid
    # back-to-back recordings (e.g. Parrot echoing several short bursts
    # seconds apart) that let a just-finished recording get picked back up
    # and streamed a second time, in full, while it briefly sat at its
    # public name waiting to be encoded -- heard as "it plays again" right
    # after content that had already played live moments earlier.
    now = time.time()
    paths = glob.glob(os.path.join(REC_DIR, ".qsorec_*.wav"))
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
    # The hidden placeholder name is fixed per logic (".qsorec_<Logic>.wav",
    # not timestamped), so consecutive rapid-fire recordings -- e.g. Parrot
    # echoing back several short bursts seconds apart -- reuse the exact
    # same path. A path-string comparison alone can't tell "still this
    # recording" from "a brand new one that happens to have the same name",
    # so it'd either get stuck silently following a stale, already-finished
    # file descriptor forever (missing every recording after the first) or
    # -- worse -- resync onto the new file mid-stream and replay/skip
    # content. Track the inode instead: that's the OS-level ground truth
    # for "is this the same physical file".
    with open(path, "rb") as f:
        my_ino = os.fstat(f.fileno()).st_ino
        f.seek(WAV_HEADER_BYTES)
        while True:
            data = f.read(CHUNK_BYTES)
            if data:
                sock.sendto(data, (UDP_IP, UDP_PORT))
                continue
            # Caught up to the writer -- keep following unless this exact
            # file (by inode) has stopped being the current recording.
            try:
                if os.stat(path).st_ino != my_ino:
                    return  # a new recording has replaced this one
            except OSError:
                return  # gone -- renamed away on close
            if find_current_recording() != path:
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
