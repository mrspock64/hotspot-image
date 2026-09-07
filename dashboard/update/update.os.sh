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

# full-upgrade, not plain upgrade: plain upgrade refuses to install or
# remove any package as a side effect of resolving an upgrade, even a
# harmless new dependency -- confirmed live, it silently "kept back"
# rpi-eeprom every run instead of upgrading it. full-upgrade is the
# standard, recommended way to fully patch a Raspberry Pi OS install; it
# can in principle remove a package to resolve a conflict, but won't
# invent anything to install that wasn't already a dependency of something
# already here.
apt full-upgrade -y

# Safe to auto-remove orphaned old kernels here specifically -- unlike a
# GRUB system, the Pi's bootloader has no menu to select an older kernel
# at boot time; it always just loads whatever the newest linux-image
# package's postinst most recently wrote to the fixed /boot/firmware/
# kernel8.img (and kernel_2712.img). So an old linux-image-*/linux-headers-*
# package sitting around after an upgrade gives no real rollback path,
# just wasted disk space -- confirmed via kernel8.img's fixed filename.
apt autoremove -y

echo "###-FINISH-####"
