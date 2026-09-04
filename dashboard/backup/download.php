<?php
require_once __DIR__ . '/lib.php';

try {
    $zip = buildBackupZip();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Backup failed: ' . $e->getMessage();
    exit;
}

$filename = 'hotspot-backup-' . gethostname() . '-' . date('Ymd-His') . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($zip));
readfile($zip);
// Owned by root (built via sudo zip) -- plain unlink() would fail under /tmp's sticky bit.
exec('sudo rm -f ' . escapeshellarg($zip));
