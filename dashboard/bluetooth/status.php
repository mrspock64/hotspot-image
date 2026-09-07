<?php
// Tiny JSON endpoint for bluetooth/index.php's post-action polling -- same
// pattern as power/status.php. start/stop run backgrounded, so the page
// landed on right after clicking reflects whatever systemctl said at that
// exact instant, not the eventual result a moment later (hotspot-bluetooth
// waits on hci0 + bluetooth.service in its own ExecStartPre, so "on" isn't
// always instant).
header('Content-Type: application/json');
$active = trim((string)@shell_exec('systemctl is-active hotspot-bluetooth 2>/dev/null')) === 'active';
echo json_encode(['active' => $active]);
