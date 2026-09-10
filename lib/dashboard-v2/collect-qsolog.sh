#!/bin/bash
#
# dashboard-v2 QSO Log module's collector -- runs every 30s (see
# dashboard-v2-collect-qsolog.timer). Reuses production's own recording-
# listing/parsing logic (dashboard/include/qso_recorder.php's
# listQsoRecordings()/qsoRecordingInfo()) via `php -r`, same call pattern
# lib/load-monitor/monitor.sh's pause_qso_recorder() already uses to reach
# into that file -- not a second, separately-maintained parser for the
# "qsorec_<Logic>_TG<n>_<call>_<timestamp>.mp3" naming scheme.
#
# Duration is real, not guessed: production's own QSO Log page (dashboard/
# qsolog/index.php) has never tracked it either -- the filename scheme has
# no end-timestamp, so there was nothing to reuse here -- but ffprobe (on
# this image already, confirmed live on svxlinkuhf) reads an mp3's actual
# duration from its own header, not an estimate. Capped to the most recent
# QSOLOG_RECENT_LIMIT recordings, not all of them: on a busy day this
# directory holds dozens of files, and shelling out to ffprobe for every
# single one every 30s is exactly the kind of avoidable disk/CPU churn
# this Pi Zero 2 W can't spare (see monitor.sh's own iowait-incident
# notes) -- the mockup only ever shows a handful of recent rows anyway.
#
set -euo pipefail

STATE_DIR="/var/cache/hotspot-image"
STATE_FILE="$STATE_DIR/dashboard-v2-qsolog.json"
REPO_QSO_RECORDER_PHP="/opt/hotspot-image/dashboard/include/qso_recorder.php"
QSOLOG_RECENT_LIMIT=15

mkdir -p "$STATE_DIR"

if [ ! -f "$REPO_QSO_RECORDER_PHP" ]; then
  echo "$REPO_QSO_RECORDER_PHP not found -- nothing to collect" >&2
  exit 1
fi

# php -r does the listing + filename parsing (real reuse of production
# code); this shell script's only real job is the ffprobe duration lookup
# per file, which lives more naturally out here than shelled out from
# inside the php -r one-liner.
listing_json=$(php -r "
require '$REPO_QSO_RECORDER_PHP';
\$r = listQsoRecordings();
\$dir = qsoRecorderDir();
\$recent = array_slice(\$r['finished'], 0, $QSOLOG_RECENT_LIMIT);
\$out = ['total' => count(\$r['finished']), 'recent' => [], 'in_progress' => null];
foreach (\$recent as \$f) {
    \$info = qsoRecordingInfo(\$f['file']);
    \$out['recent'][] = [
        'path' => \$dir . '/' . \$f['file'],
        'when' => \$info['when'],
        'tg' => \$info['tg'],
        'callsign' => \$info['callsign'],
        'size' => \$f['size'],
    ];
}
if (\$r['inProgress']) {
    \$out['in_progress'] = ['since' => date('Y-m-d H:i:s', \$r['inProgress']['mtime']), 'size' => \$r['inProgress']['size']];
}
echo json_encode(\$out);
")

python3 - "$STATE_FILE" "$listing_json" <<'PYEOF'
import json
import subprocess
import sys

state_file, listing_json = sys.argv[1], sys.argv[2]
listing = json.loads(listing_json)


def probe_duration_sec(path):
    try:
        out = subprocess.run(
            ["ffprobe", "-v", "error", "-show_entries", "format=duration",
             "-of", "default=noprint_wrappers=1:nokey=1", path],
            capture_output=True, text=True, timeout=5,
        )
        return round(float(out.stdout.strip()))
    except (OSError, ValueError, subprocess.TimeoutExpired):
        return None


for rec in listing["recent"]:
    rec["duration_sec"] = probe_duration_sec(rec.pop("path"))

listing["updated_at"] = __import__("datetime").datetime.now(
    __import__("datetime").timezone.utc
).isoformat()

with open(state_file, "w") as f:
    json.dump(listing, f)
PYEOF
