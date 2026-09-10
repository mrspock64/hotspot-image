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
header('Accept-Ranges: bytes');

$size = filesize($path);
$start = 0;
$end = $size - 1;

// Range support matters here even though these files are small -- without
// it, <audio> can't start playing until the whole file has downloaded and
// can't seek at all, which is exactly what made playback feel unresponsive
// over a lossy link (one dropped/slow response stalls the entire clip
// instead of just the part actually needed).
$range = $_SERVER['HTTP_RANGE'] ?? '';
if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', $range, $m)) {
    if ($m[1] === '' && $m[2] === '') {
        // Malformed ("bytes=-"), fall through as a full-file response.
    } else {
        if ($m[1] !== '') {
            $start = (int)$m[1];
        } else {
            // Suffix range ("bytes=-500" -- last 500 bytes).
            $start = $size - (int)$m[2];
        }
        $end = $m[2] !== '' && $m[1] !== '' ? (int)$m[2] : $size - 1;
        $start = max(0, $start);
        $end = min($size - 1, $end);
        if ($start > $end) {
            http_response_code(416);
            header("Content-Range: bytes */$size");
            exit;
        }
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }
}

$length = $end - $start + 1;
header('Content-Length: ' . $length);

$fp = fopen($path, 'rb');
fseek($fp, $start);
$remaining = $length;
while ($remaining > 0 && !feof($fp)) {
    $chunk = fread($fp, min(8192, $remaining));
    if ($chunk === false) {
        break;
    }
    echo $chunk;
    $remaining -= strlen($chunk);
}
fclose($fp);
