<?php
// Server-Sent Events log stream -- a real tail -f, not polling: pipes
// from an actual `tail -f` subprocess straight to the browser over one
// held-open HTTP response.
//
// Bounded to TIMEOUT_SECONDS: this ties up one Apache worker for as long
// as it runs, and MaxRequestWorkers is already a tight resource on this
// hardware (see the svxlinkuhf performance notes) -- a tab left open
// overnight shouldn't hold a worker forever. index.php reconnects
// automatically up to a point, then asks the user to click Reconnect,
// same reasoning as the Talk Groups/QSO Log auto-refresh removals this
// session: don't poll/stream forever for no one watching.
set_time_limit(0);
ignore_user_abort(false);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // in case a reverse proxy ever sits in front of this
while (ob_get_level() > 0) {
    ob_end_flush();
}

define('TIMEOUT_SECONDS', 600); // 10 minutes
define('KEEPALIVE_SECONDS', 15); // detect a closed client without spamming output
$logFile = '/var/log/svxlink';

$process = proc_open(
    ['tail', '-n', '200', '-f', $logFile],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes
);
if (!is_resource($process)) {
    echo "event: error\ndata: Could not start tail on the server.\n\n";
    exit;
}
stream_set_blocking($pipes[1], false);

$start = time();
$lastOutput = time();

while (true) {
    if (connection_aborted()) {
        break;
    }
    if (time() - $start > TIMEOUT_SECONDS) {
        echo "event: timeout\ndata: Stream timed out after " . TIMEOUT_SECONDS . "s of being open.\n\n";
        flush();
        break;
    }

    $line = fgets($pipes[1]);
    if ($line !== false && $line !== '') {
        echo 'data: ' . str_replace(["\r", "\n"], '', $line) . "\n\n";
        flush();
        $lastOutput = time();
        continue;
    }

    if (time() - $lastOutput >= KEEPALIVE_SECONDS) {
        echo ": keepalive\n\n";
        flush();
        $lastOutput = time();
    }
    usleep(200000);
}

proc_terminate($process);
proc_close($process);
