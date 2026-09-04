#!/usr/bin/env python3
# Reads raw mono 16-bit PCM from stdin and forwards it as UDP datagrams to
# DVSwitch's Web_Proxy (proxy.js), which rebroadcasts each datagram to every
# connected WebSocket client (the dashboard's RX Monitor button). proxy.js
# doesn't care about chunk boundaries -- it just forwards whatever arrives --
# so this only needs to pick a chunk size that keeps latency low without
# flooding the network with tiny packets.
import socket
import sys

UDP_IP = "127.0.0.1"
UDP_PORT = 2222
CHUNK_BYTES = 1920  # 20ms of mono 16-bit audio at 48000 Hz

sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
stdin = sys.stdin.buffer

while True:
    data = stdin.read(CHUNK_BYTES)
    if not data:
        break
    sock.sendto(data, (UDP_IP, UDP_PORT))
