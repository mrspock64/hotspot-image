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

$disposition = isset($_GET['dl']) ? 'attachment' : 'inline';
header('Content-Type: audio/mpeg');
header('Content-Disposition: ' . $disposition . '; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
header('Accept-Ranges: none');
readfile($path);
