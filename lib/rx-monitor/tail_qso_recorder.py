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
import array
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

# The dashboard header's live RX level meter reads this -- /dev/shm is
# tmpfs (RAM-backed), deliberately not a real path on the SD card, since
# this gets rewritten several times a second while a recording is open.
LEVEL_FILE = "/dev/shm/hotspot_rx_level"
LEVEL_WRITE_INTERVAL = 0.1

sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)


def write_level(peak_fraction):
    try:
        with open(LEVEL_FILE, "w") as f:
            f.write(str(int(peak_fraction * 100)))
    except OSError:
        pass


def peak_fraction(data):
    # 16-bit signed mono PCM -- peak absolute sample value as a 0..1
    # fraction of full scale. Exact sample rate doesn't matter here (unlike
    # RX Monitor's own playback, which needed the real 16kHz vs the
    # assumed 48kHz), only the sample magnitudes do.
    samples = array.array("h")
    samples.frombytes(data[: len(data) - (len(data) % 2)])
    if not samples:
        return 0.0
    return min(1.0, max(abs(s) for s in samples) / 32768.0)


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
        last_level_write = 0.0
        try:
            while True:
                data = f.read(CHUNK_BYTES)
                if data:
                    sock.sendto(data, (UDP_IP, UDP_PORT))
                    now = time.time()
                    if now - last_level_write >= LEVEL_WRITE_INTERVAL:
                        write_level(peak_fraction(data))
                        last_level_write = now
                    continue
                # Caught up to the writer -- keep following unless this
                # exact file (by inode) has stopped being the current
                # recording.
                try:
                    if os.stat(path).st_ino != my_ino:
                        return  # a new recording has replaced this one
                except OSError:
                    return  # gone -- renamed away on close
                if find_current_recording() != path:
                    return
                time.sleep(0.05)
        finally:
            # Drop the meter back to 0 the moment this recording stops,
            # rather than leaving it stuck at its last value until the
            # level file goes stale (see rx_level.php's freshness check).
            write_level(0.0)


def main():
    while True:
        current = find_current_recording()
        if current:
            stream_file(current)
        else:
            time.sleep(POLL_INTERVAL)


if __name__ == "__main__":
    main()
