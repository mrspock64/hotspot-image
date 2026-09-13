<?php
// Talkgroup directory + switcher. Two tiers in one list: this node's own
// "plan" (MONITOR_TGS in svxlink.conf's [ReflectorLogic], with priority
// markers), each cross-referenced with its own most recent activity from
// /var/log/svxlink -- same as before; plus every other *named* TG from
// the TG Names database (dashboard/include/tgdb_store.php's loadTgDb(),
// the same list the production TG Names page manages) that isn't on the
// monitor list, e.g. "0 Idle" or "91 World Wide". This module only shows
// activity for the monitored ones (directory rows stay activity-free by
// choice, not because the shared parser lacks the data) -- confirmed live
// 2026-09-14 the directory itself was a real gap: the production TG
// page lists every named TG, but this module only ever showed the
// monitored subset, so there was no way to reach Idle/World Wide from
// here at all. Distinct from Reflector Activity (whole reflector, not
// this node's own plan).
//
// Reuses dashboard/include/tgdb_store.php's loadTgDb()/
// loadMonitoredTgNumbers()/loadMonitoredTgPriorities() directly (a real
// require(), not duplicated logic) -- unlike Setup's readCurrent(), this
// lives in a proper dashboard/include/*.php file already, so there's
// nothing to duplicate. Adding a new named TG, or changing which ones are
// monitored, still only happens on the production TG Names/TG pages --
// out of scope here, this endpoint only reads that data.
//
// Per-TG activity and the currently-selected TG both come from the
// shared dashboard/include/svxlink_log_state.php parser (one tail +
// regex pass shared across every log-based module) rather than this
// file's own independent scan -- see that file's header comment.
//
// ?action=select&tg=<n>: switches the node's active talkgroup, reusing
// production's own sendTgSelectDtmf() (dashboard/include/tg_select.php,
// extracted from buttons.php specifically so this endpoint could require
// it without pulling in that file's page template) -- same "91<tg>#" DTMF
// command and double-send workaround the production TG page's own "A"
// (cell_tower) button sends. Accepts any TG number that has a name in
// the TG Names database (monitored or not), not an arbitrary string.
header('Content-Type: application/json');

require_once __DIR__ . '/../../dashboard/include/tgdb_store.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if ($action === 'select') {
    require_once __DIR__ . '/../../dashboard/include/tg_select.php';
    $tg = $_GET['tg'] ?? ($_POST['tg'] ?? '');
    $names = loadTgDb();
    if (!ctype_digit((string)$tg) || !array_key_exists((string)$tg, $names)) {
        http_response_code(400);
        echo json_encode(['error' => 'tg must be a number in the TG Names database']);
        exit;
    }
    sendTgSelectDtmf('91' . $tg . '#');
    echo json_encode(['selected' => (string)$tg]);
    exit;
}

require_once __DIR__ . '/../../dashboard/include/svxlink_log_state.php';

$monitoredTgs = loadMonitoredTgNumbers();
$priorities = loadMonitoredTgPriorities();
$names = loadTgDb();

$s = getSvxlinkLogState();
$selectedTg = $s['selected_tg']; // this node's currently active TG, same value api/talkgroup.php reads

$now = $s['now'];
$result = [];
foreach ($monitoredTgs as $tg) {
    $activity = $s['talker_by_tg'][$tg] ?? null;
    $result[] = [
        'tg' => $tg,
        'name' => $names[$tg] ?? null,
        'priority' => $priorities[$tg] ?? 0,
        'monitored' => true,
        'activity' => $activity ? [
            'callsign' => $activity['callsign'],
            'active' => $activity['type'] === 'start',
            'seconds_ago' => max(0, $now - $activity['at']),
        ] : null,
    ];
}

// Sorted by priority (highest first) then TG number -- a stable order
// that only changes if the actual MONITOR_TGS config changes, not on
// every poll. Used to sort by most-recently-active instead, but
// confirmed live (2026-09-14) that reordering rows out from under the
// cursor every refresh_ms made them hard to actually click -- worse than
// losing the "what's busy right now" at-a-glance ordering, since the
// activity text/dot on each row already shows that anyway.
usort($result, function ($a, $b) {
    if ($a['priority'] !== $b['priority']) {
        return $b['priority'] <=> $a['priority'];
    }
    return (int)$a['tg'] <=> (int)$b['tg'];
});

// Everything else named in the TG Names database but not on the monitor
// list -- no activity tracking (the log scan above never looked for
// these), just a name and a switch target. Sorted numerically by TG#,
// same convention the production TG page's own table uses.
$monitoredSet = array_flip($monitoredTgs);
$directory = [];
foreach ($names as $tg => $name) {
    $tg = (string)$tg;
    if (isset($monitoredSet[$tg])) {
        continue;
    }
    $directory[] = ['tg' => $tg, 'name' => $name, 'priority' => 0, 'monitored' => false, 'activity' => null];
}
usort($directory, fn($a, $b) => (int)$a['tg'] <=> (int)$b['tg']);

echo json_encode(['talkgroups' => array_merge($result, $directory), 'selected_tg' => $selectedTg]);
