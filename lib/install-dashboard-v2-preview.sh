#!/bin/bash
#
# Installs the dashboard-v2 module-based preview (docs/dashboard-v2-brief.md).
# Runs ENTIRELY separately from the production dashboard:
#  - Serves dashboard-v2/ straight out of its OWN git worktree
#    (WORKTREE_DIR below), not out of /opt/hotspot-image itself -- no
#    /var/www/html sync, no Apache vhost, nothing shared with
#    install-dashboard.sh's deploy path.
#  - The Signal module's collector (lib/dashboard-v2/collect-signal.sh) runs
#    on its own systemd timer, writing to its own cache file
#    (/var/cache/hotspot-image/dashboard-v2-signal.json) -- doesn't touch
#    any file the production dashboard reads or writes.
#
# The separate worktree is deliberate, not incidental: this script used to
# just serve straight out of /opt/hotspot-image with that checkout switched
# to the dashboard-v2 branch. That's the *same* checkout the production
# auto-updater (lib/dashboard-autoupdate.sh) and the manual "Update
# Dashboard" button both `git fetch` + `git reset --hard` against origin's
# default branch (main) -- confirmed live: either one running while
# /opt/hotspot-image was checked out to dashboard-v2 silently reset it back
# to main, discarding the branch switch with no warning. A worktree is a
# second, independent working directory sharing the same .git object
# store/history -- resetting one never touches the other, so production
# stays on main and gets auto-updated exactly as designed, while this
# worktree stays on dashboard-v2 until *this* script (or a manual `git
# pull` in WORKTREE_DIR) is run again.
#
# Opt-in only -- as of 2026-09-15 this script (and its systemd units) also
# lives on main, called from setup.sh only when run with
# --with-dashboard-v2, never by default. dashboard-v2 is still under
# active development (real bugs found and fixed most days -- see
# docs/dashboard-v2-brief.md), so a plain `setup.sh` on a fresh node
# stays exactly what it always was: just the stable production
# dashboard. This script itself can still be run by hand any time, on
# any node, independent of setup.sh -- and is how this branch's own
# changes reach a test node (re-run this, or `git -C
# /opt/hotspot-image-dashboard-v2 pull` + `systemctl restart
# dashboard-v2-preview` directly).
#
# Idempotent: safe to re-run.
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
WORKTREE_DIR="/opt/hotspot-image-dashboard-v2"
BRANCH="dashboard-v2"

echo "--- dashboard-v2 preview: worktree ---"
if [ -d "$WORKTREE_DIR/.git" ]; then
  echo "Worktree already exists at $WORKTREE_DIR -- pulling latest $BRANCH instead of creating it again."
  git -C "$WORKTREE_DIR" pull -q
else
  git -C "$REPO_DIR" fetch origin -q
  git -C "$REPO_DIR" worktree add "$WORKTREE_DIR" "$BRANCH"
fi

# --system, not --global: this directory gets read by whichever user runs
# `git pull` by hand while developing (root via sudo, the login user) *and*
# by www-data, since api/node.php's firmware-hash lookup shells out to git
# from inside the PHP built-in server. Confirmed live: --global only
# covers whichever single user's config it's added to, and www-data
# specifically hit this ("detected dubious ownership") even after root's
# own global config already had the exception -- --system (/etc/gitconfig)
# is the one config every user actually shares.
git config --system --add safe.directory "$WORKTREE_DIR"
chown -R www-data:www-data "$WORKTREE_DIR"

echo "--- dashboard-v2 preview: packages ---"
# ffmpeg: just for ffprobe, the QSO Log module's real (not estimated)
# per-recording duration -- see collect-qsolog.sh.
apt-get install -y php-cli iputils-ping ffmpeg

echo "--- dashboard-v2 preview: Signal module collector ---"
cp "$SCRIPT_DIR/dashboard-v2/dashboard-v2-collect-signal.service" /etc/systemd/system/dashboard-v2-collect-signal.service
cp "$SCRIPT_DIR/dashboard-v2/dashboard-v2-collect-signal.timer" /etc/systemd/system/dashboard-v2-collect-signal.timer
systemctl daemon-reload
systemctl enable --now dashboard-v2-collect-signal.timer
# Run once immediately rather than waiting up to 60s for the timer's first
# fire, so the API has real data (not a 503) right after this script exits.
systemctl start dashboard-v2-collect-signal.service

echo "--- dashboard-v2 preview: Node/vitals module collector ---"
cp "$SCRIPT_DIR/dashboard-v2/dashboard-v2-collect-node.service" /etc/systemd/system/dashboard-v2-collect-node.service
cp "$SCRIPT_DIR/dashboard-v2/dashboard-v2-collect-node.timer" /etc/systemd/system/dashboard-v2-collect-node.timer
systemctl daemon-reload
systemctl enable --now dashboard-v2-collect-node.timer
systemctl start dashboard-v2-collect-node.service

echo "--- dashboard-v2 preview: QSO Log module collector ---"
cp "$SCRIPT_DIR/dashboard-v2/dashboard-v2-collect-qsolog.service" /etc/systemd/system/dashboard-v2-collect-qsolog.service
cp "$SCRIPT_DIR/dashboard-v2/dashboard-v2-collect-qsolog.timer" /etc/systemd/system/dashboard-v2-collect-qsolog.timer
systemctl daemon-reload
systemctl enable --now dashboard-v2-collect-qsolog.timer
systemctl start dashboard-v2-collect-qsolog.service

echo "--- dashboard-v2 preview: PHP built-in server on :8081 ---"
cp "$SCRIPT_DIR/dashboard-v2/dashboard-v2-preview.service" /etc/systemd/system/dashboard-v2-preview.service
systemctl daemon-reload
systemctl enable --now dashboard-v2-preview.service

echo "--- dashboard-v2 preview: done ---"
echo "Serving http://$(hostname).local:8081/ from $WORKTREE_DIR/dashboard-v2"
echo "(its own worktree, not /opt/hotspot-image -- 'git -C $WORKTREE_DIR pull'"
echo "+ 'systemctl restart dashboard-v2-preview' picks up changes; re-running"
echo "this whole script does the same pull for you)."
