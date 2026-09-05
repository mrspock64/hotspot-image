#!/bin/bash
#
# WM8960 audio + GPIO PTT setup. Kept deliberately minimal: the WM8960
# overlay is built into the mainline Raspberry Pi kernel these days, so no
# out-of-tree driver compilation is needed — just the right config.txt
# lines (confirmed against a live svxlinkuhf node tonight) and a reboot.
# GPIO PTT uses plain gpiod against the standard gpiochip0, also no extra
# driver needed.
#
set -euo pipefail

CONFIG_TXT="/boot/firmware/config.txt"
[ -f "$CONFIG_TXT" ] || CONFIG_TXT="/boot/config.txt"

echo "--- Installing gpiod ---"
apt-get install -y gpiod libgpiod-dev

echo "--- Enabling WM8960 overlay in $CONFIG_TXT ---"
add_line() {
  grep -qxF "$1" "$CONFIG_TXT" || echo "$1" >> "$CONFIG_TXT"
}
add_line "dtparam=i2c_arm=on"
add_line "dtparam=i2s=on"
add_line "dtparam=audio=off"
add_line "dtoverlay=vc4-kms-v3d,noaudio"
add_line "dtoverlay=i2s-mmap"
add_line "dtoverlay=wm8960-soundcard"
add_line "dtoverlay=disable-bt"

SVX_CONF="/etc/svxlink/svxlink.conf"
echo "--- Muting local DTMF tones in $SVX_CONF ---"
# DTMF_MUTING=1 stops the tones of your own commands (e.g. changing
# talkgroup) from being sent out to the reflector network -- documented in
# svxlink.conf(5), on by default here since there's no real reason a new
# install would want its own DTMF tones broadcast. Only added if the key
# isn't already present, so a value someone already set via the Setup page
# is never overwritten.
if [ -f "$SVX_CONF" ] && grep -q '^\[Rx1\]$' "$SVX_CONF" && ! grep -q '^DTMF_MUTING=' "$SVX_CONF"; then
  sed -i '/^\[Rx1\]$/a DTMF_MUTING=1' "$SVX_CONF"
  echo "Added DTMF_MUTING=1 to [Rx1]."
fi

echo "--- Done. A reboot is required for the audio overlay to take effect. ---"
