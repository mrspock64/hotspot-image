<?php
/**
 * AJAX companion to index.php's log textarea. Used while a check/upgrade
 * action is running so the page can poll for new log content without a
 * full page reload -- index.php used to do that via header('Refresh: 3'),
 * which re-runs every script and re-renders every asset on the page every
 * 3 seconds. Confirmed live (2026-09-10): individual HTTP responses were
 * fast even with a background apt upgrade running (~0.2s), so the
 * perceived slowness wasn't the server -- it was the browser doing a full
 * navigation (CSS, every page script including the RX meter poller and
 * RX Monitor auto-resume, images, ...) every 3 seconds instead of just
 * fetching new log text.
 */
header('Content-Type: application/json');

define('UPDATER_SCREEN_LOG', '/var/cache/hotspot-image/updater-screen.log');

$screen = [];
if (is_file(UPDATER_SCREEN_LOG) && filesize(UPDATER_SCREEN_LOG) > 0) {
    exec('tail -n 500 ' . escapeshellarg(UPDATER_SCREEN_LOG) . ' 2>&1', $screen);
}
$running = !empty($screen) && ($screen[count($screen) - 1] ?? '') !== '###-FINISH-####';

echo json_encode([
    'log' => implode("\n", $screen),
    'running' => $running,
]);
