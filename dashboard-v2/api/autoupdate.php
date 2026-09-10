<?php
// Auto-update module. Pure reuse, no new state of its own -- three
// existing production sources, same ones dashboard/update/index.php and
// dashboard/include/site_header.php already read:
//  - getAutoUpdateDashboard() (inisync.php): is the hourly auto-updater on
//  - isDashboardUpdateAvailable() (update_check.php): 6h-cached "is there
//    a newer commit on origin" check
//  - last_dashboard_update.json: when a real update last actually applied
//    (written by dashboard/update/update.dashboard.sh on any genuine
//    old!=new run, whether triggered by the auto-updater or the manual
//    button -- see that script's own comment)
header('Content-Type: application/json');

require_once __DIR__ . '/../../dashboard/include/inisync.php';
require_once __DIR__ . '/../../dashboard/include/update_check.php';

const LAST_UPDATE_FILE = '/var/cache/hotspot-image/last_dashboard_update.json';

$lastUpdate = null;
if (is_readable(LAST_UPDATE_FILE)) {
    $raw = json_decode((string)@file_get_contents(LAST_UPDATE_FILE), true);
    if (is_array($raw) && !empty($raw['timestamp'])) {
        $lastUpdate = [
            'from' => substr((string)($raw['from'] ?? ''), 0, 7),
            'to' => substr((string)($raw['to'] ?? ''), 0, 7),
            'timestamp' => $raw['timestamp'],
        ];
    }
}

echo json_encode([
    'enabled' => getAutoUpdateDashboard(),
    'update_available' => isDashboardUpdateAvailable()['available'],
    'last_update' => $lastUpdate,
]);
