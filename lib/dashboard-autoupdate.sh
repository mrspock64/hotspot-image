#!/bin/bash
#
# Runs hourly (hotspot-dashboard-autoupdate.timer). Auto-pulls and applies
# a new hotspot-image dashboard commit if one exists -- deliberately scoped
# to the dashboard only, never OS or SvxLink (those are far more invasive:
# a kernel upgrade needing a reboot, an SvxLink rebuild taking the better
# part of an hour and needing the service stopped). A dashboard update is
# just a git pull + re-sync of /var/www/html + a quick svxlink restart --
# low-risk enough to run unattended.
#
# On by default (AUTO_UPDATE_DASHBOARD unset or anything other than "0" in
# svxlink.conf's [Dashboard] section) -- toggle off on the Update page.
#
# Reuses the exact same lock file and log path the dashboard's own manual
# Check/Update buttons use (see dashboard/update/index.php's
# runUpdaterScript()), so a manual click and this timer can never run
# concurrently and corrupt each other's output -- whichever gets the lock
# first wins, the other just skips this cycle silently.
set -u

SVX_CONF=/etc/svxlink/svxlink.conf
LOCK_FILE=/var/cache/hotspot-image/updater.lock
LOG_FILE=/var/cache/hotspot-image/updater-screen.log
REPO_DIR=/opt/hotspot-image

mkdir -p "$(dirname "$LOCK_FILE")"

auto_update_enabled() {
    local v
    v=$(grep -E '^[ \t]*AUTO_UPDATE_DASHBOARD[ \t]*=' "$SVX_CONF" 2>/dev/null \
        | tail -n1 | cut -d'=' -f2 | tr -d '[:space:]')
    [ "$v" != "0" ]
}

if [ ! -f "$SVX_CONF" ] || ! auto_update_enabled; then
    exit 0
fi

if [ ! -d "$REPO_DIR/.git" ]; then
    exit 0
fi

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    # A manual check/update (or a previous timer run that's still going)
    # already holds the lock -- skip this cycle rather than wait, the
    # timer fires again in an hour regardless.
    exit 0
fi

cd "$REPO_DIR" || exit 0
if ! git fetch origin -q; then
    exit 0
fi

remote_rev=$(git rev-parse --verify -q origin/HEAD 2>/dev/null)
if [ -z "$remote_rev" ]; then
    remote_rev=$(git rev-parse --verify -q origin/main 2>/dev/null)
fi
[ -z "$remote_rev" ] && exit 0

local_rev=$(git rev-parse HEAD)
[ "$local_rev" = "$remote_rev" ] && exit 0

# An update is available -- run the exact same script a manual "Update
# Dashboard" click runs, logging to the exact same file, so the Update
# page shows what happened whether a person or the timer triggered it.
nice -n 19 bash "$REPO_DIR/dashboard/update/update.dashboard.sh" > "$LOG_FILE" 2>&1
