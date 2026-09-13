#!/bin/bash
#
# Installs the dashboard-v2 module-based preview (docs/dashboard-v2-brief.md)
# on svxlinkuhf, the project's dedicated test node for this work. Runs
# ENTIRELY separately from the production dashboard:
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
# Deliberately NOT called from setup.sh and NOT merged to main -- this is
# branch-only, per the brief ("main är produktionskoden och rörs inte").
# Run this by hand on svxlinkuhf while iterating on the dashboard-v2
# branch.
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
