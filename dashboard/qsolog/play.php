<?php
require_once __DIR__ . '/../include/qso_recorder.php';

$file = $_GET['file'] ?? '';
if (!isValidQsoRecordingName($file)) {
    http_response_code(400);
    exit('Invalid filename.');
}

$path = qsoRecorderDir() . '/' . $file;
if (!is_file($path)) {
    http_response_code(404);
    exit('Not found.');
}

header('Content-Type: audio/ogg');
header('Content-Disposition: inline; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
header('Accept-Ranges: none');
readfile($path);
