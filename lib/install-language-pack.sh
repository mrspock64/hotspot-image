#!/bin/bash
#
# Installs the Swedish sound-clip pack (sv_SE, voice "Elin") for SvxLink's
# own stock announcements -- manual identification ("*"), periodic ID, time,
# digit readouts, etc. -- and switches DEFAULT_LANG to it. Without this,
# SvxLink falls back to whatever language pack ships on RF.Guru's image
# (en_US only, confirmed live on svxlinkuhf), so every stock announcement
# is in English even for a Swedish-registered callsign.
#
# Pack source: https://github.com/sm0svx/svxlink-sounds-sv_SE-elin --
# maintained by SvxLink's own author, real human voice (Acapela "Elin"),
# not a TTS synthesis. Pinned to the 16k release (25.05) that was verified
# live against this project's own 16kHz mono clip convention -- confirmed
# by inspecting an actual en_US clip (Parrot/help.wav) already installed on
# svxlinkuhf. Bump the version below deliberately, not automatically, and
# re-verify sample rate/format before doing so.
#
# D911/D920/D921 (hotspot-image's own dtmf_cmd_received additions in
# events.d/Logic.tcl) are unaffected either way -- they don't use langdir.
#
set -euo pipefail

PACK_VERSION="25.05"
PACK_URL="https://github.com/sm0svx/svxlink-sounds-sv_SE-elin/releases/download/${PACK_VERSION}/svxlink-sounds-sv_SE-elin-16k-${PACK_VERSION}.tar.bz2"
SOUNDS_DIR="/usr/share/svxlink/sounds/sv_SE"
SVXLINK_CONF="/etc/svxlink/svxlink.conf"

if [ -d "$SOUNDS_DIR" ]; then
  echo "--- Swedish sound pack already installed at $SOUNDS_DIR -- skipping download ---"
else
  echo "--- Downloading Swedish sound pack ($PACK_VERSION) ---"
  TMP_DIR="$(mktemp -d)"
  curl -sL -o "$TMP_DIR/pack.tar.bz2" "$PACK_URL"
  tar -xjf "$TMP_DIR/pack.tar.bz2" -C "$TMP_DIR"

  EXTRACTED_DIR="$(find "$TMP_DIR" -maxdepth 1 -type d -name 'sv_SE-elin-*')"
  if [ -z "$EXTRACTED_DIR" ]; then
    echo "Unexpected archive layout -- no sv_SE-elin-* directory found after extraction." >&2
    rm -rf "$TMP_DIR"
    exit 1
  fi

  mv "$EXTRACTED_DIR" "$SOUNDS_DIR"
  chown -R root:root "$SOUNDS_DIR"
  find "$SOUNDS_DIR" -type d -exec chmod 755 {} \;
  find "$SOUNDS_DIR" -type f -exec chmod 644 {} \;
  rm -rf "$TMP_DIR"
  echo "--- Installed to $SOUNDS_DIR ---"
fi

echo "--- Setting DEFAULT_LANG=sv_SE in [SimplexLogic] ---"
if grep -q '^DEFAULT_LANG=' "$SVXLINK_CONF"; then
  cp "$SVXLINK_CONF" "${SVXLINK_CONF}.bak-$(date +%Y%m%d-%H%M%S)"
  sed -i 's/^DEFAULT_LANG=.*/DEFAULT_LANG=sv_SE/' "$SVXLINK_CONF"
else
  cp "$SVXLINK_CONF" "${SVXLINK_CONF}.bak-$(date +%Y%m%d-%H%M%S)"
  sed -i '/^\[SimplexLogic\]/a DEFAULT_LANG=sv_SE' "$SVXLINK_CONF"
fi

echo "--- Done -- restart svxlink (Power page, or 'service svxlink restart') to apply ---"
