echo "###-START-###"

# Snapshot exactly which package versions were installed right before this
# upgrade -- not a real backup (no way to roll a kernel/eeprom flash back),
# but cheap (a few KB of text) and gives something concrete to diff against
# if a package upgrade regresses something: `diff <old-snapshot> <(dpkg-query
# -W -f='${Package}\t${Version}\n')` shows exactly what changed, and a
# specific package can be pinned back with `apt install pkg=version`.
SNAPSHOT_DIR=/var/backups/hotspot-image/pkg-snapshots
mkdir -p "$SNAPSHOT_DIR"
SNAPSHOT_FILE="$SNAPSHOT_DIR/$(date +%Y%m%d-%H%M%S).txt"
dpkg-query -W -f='${Package}\t${Version}\n' > "$SNAPSHOT_FILE"
echo "Saved installed-package snapshot: $SNAPSHOT_FILE"

dpkg --configure -a
apt upgrade -y

# Safe to auto-remove orphaned old kernels here specifically -- unlike a
# GRUB system, the Pi's bootloader has no menu to select an older kernel
# at boot time; it always just loads whatever the newest linux-image
# package's postinst most recently wrote to the fixed /boot/firmware/
# kernel8.img (and kernel_2712.img). So an old linux-image-*/linux-headers-*
# package sitting around after an upgrade gives no real rollback path,
# just wasted disk space -- confirmed via kernel8.img's fixed filename.
apt autoremove -y

echo "###-FINISH-####"
