<?php
require_once __DIR__ . '/lib.php';

try {
    $tarball = buildBackupTarball();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Backup failed: ' . $e->getMessage();
    exit;
}

$filename = 'hotspot-backup-' . gethostname() . '-' . date('Ymd-His') . '.tgz';

header('Content-Type: application/gzip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tarball));
readfile($tarball);
// Owned by root (built via sudo tar) -- plain unlink() would fail under /tmp's sticky bit.
exec('sudo rm -f ' . escapeshellarg($tarball));
