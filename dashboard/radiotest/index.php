<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>Radio Test</title>
    <link href="/css/modern.css" type="text/css" rel="stylesheet" />
  </head>
<body style="background: var(--mx-bg); margin: 0;">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>

<?php
require_once __DIR__ . '/../include/tts_message.php';

define('QSO_SIM_SCRIPT', '/opt/load-monitor/qso_simulate.sh');
define('QSO_SIM_PID_FILE', '/var/cache/hotspot-image/qso_sim.pid');
define('QSO_SIM_LOG_FILE', '/var/cache/hotspot-image/qso_sim_log');
define('QSO_SIM_RESULT_FILE', '/var/cache/hotspot-image/qso_sim_result');

function qsoSimRunning(): bool
{
    if (!is_file(QSO_SIM_PID_FILE)) {
        return false;
    }
    $pid = trim((string)@file_get_contents(QSO_SIM_PID_FILE));
    return ctype_digit($pid) && is_dir("/proc/$pid");
}

$error = null;
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnStart']) && !qsoSimRunning()) {
    $exchanges = max(1, min(60, (int)($_POST['exchanges'] ?? 12)));
    $minPause = max(1, min(30, (int)($_POST['min_pause'] ?? 3)));
    $maxPause = max($minPause, min(30, (int)($_POST['max_pause'] ?? 8)));

    @unlink(QSO_SIM_RESULT_FILE);
    $cmd = sprintf(
        'sudo %s %d %d %d > %s 2>&1 &',
        escapeshellarg(QSO_SIM_SCRIPT),
        $exchanges,
        $minPause,
        $maxPause,
        escapeshellarg('/dev/null')
    );
    exec($cmd);
    $message = 'QSO simulation started -- SvxLink stays running throughout, nothing to wait for it to come back.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnAbort']) && qsoSimRunning()) {
    $pid = trim((string)@file_get_contents(QSO_SIM_PID_FILE));
    exec('sudo kill ' . escapeshellarg($pid) . ' 2>&1');
    $message = 'Stopping after the current transmission finishes (a few seconds) -- nothing to forcibly kill, SvxLink was never touched.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnTestCustom']) && is_file(TTS_OUTPUT_CUSTOM)) {
    sendDtmfReliable('D920#');
    $message = 'Sent D920# -- listen for the custom message.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnTestAlert']) && is_file(TTS_OUTPUT_ALERT)) {
    sendDtmfReliable('D921#');
    $message = 'Sent D921# -- listen for the alert message.';
}

$running = qsoSimRunning();
$log = is_file(QSO_SIM_LOG_FILE) ? (string)@file_get_contents(QSO_SIM_LOG_FILE) : '';
$result = is_file(QSO_SIM_RESULT_FILE) ? json_decode((string)@file_get_contents(QSO_SIM_RESULT_FILE), true) : null;
$svxActive = trim((string)@shell_exec('systemctl is-active svxlink 2>/dev/null')) === 'active';
$hasCustomMessage = is_file(TTS_OUTPUT_CUSTOM);
$hasAlertMessage = is_file(TTS_OUTPUT_ALERT);
?>

<div class="mx-card" style="max-width: 600px;">
  <h1>Radio Test</h1>
  <p class="mx-sub">
    Simulates a real QSO's rhythm -- repeated transmissions with pauses -- by triggering SvxLink's own
    D920# command (or D911#'s IP readout if no custom message is saved below) through the same DTMF relay
    every dashboard button already uses. SvxLink stays running and owns the whole PTT/audio cycle throughout,
    the same way it does for any real transmission -- nothing here holds GPIO or the audio device directly.
    Requires a real antenna or dummy load connected -- sustained transmission into an unloaded output can
    damage the radio module.
  </p>

<?php if ($message): ?>
  <div class="mx-msg mx-msg-ok"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="mx-msg mx-msg-err"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($running): ?>
  <div class="mx-msg" style="background:#fef3c7;border:1px solid #fbbf24;color:#92400e;">
    &#9203; Simulation running -- SvxLink stays up throughout, check the reflector portal to see it live.
  </div>
  <form method="post" style="margin-bottom:16px;">
    <button name="btnAbort" type="submit" class="mx-btn mx-btn-danger" onclick="return confirm('Stop the test after the current transmission finishes?');">Stop test</button>
  </form>
<?php else: ?>
  <p style="font-weight:600; margin-bottom:14px;">SvxLink:
    <?php echo $svxActive
        ? '<span style="color:#15803d;">&#9679; Running</span>'
        : '<span style="color:var(--mx-text-dim);">&#9675; Stopped</span>'; ?>
  </p>

  <form method="post">
    <div class="mx-row"><label for="exchanges">Number of exchanges</label>
      <input type="text" id="exchanges" name="exchanges" value="12" style="width:80px;"></div>
    <div class="mx-row"><label for="min_pause">Pause between (sec)</label>
      <span><input type="text" id="min_pause" name="min_pause" value="3" style="width:60px; display:inline-block;"> to <input type="text" name="max_pause" value="8" style="width:60px; display:inline-block;"></span></div>
    <p class="mx-hint">Each exchange is one announcement (~7-8s) plus the pause above -- <?php echo $hasCustomMessage ? 'your saved custom message (below)' : "D911#'s IP readout (save a custom message below to use that instead)"; ?>. 12 exchanges with 3-8s pauses runs roughly 3-4 minutes total.</p>
    <button name="btnStart" type="submit" class="mx-btn mx-btn-danger" onclick="return confirm('Start the QSO simulation? This transmits real RF for several minutes -- make sure a real antenna or dummy load is connected.');">Start test</button>
  </form>
<?php endif; ?>

  <div class="mx-section">Custom message (D920#)</div>
  <p class="mx-hint">
<?php if ($hasCustomMessage): ?>
    Currently set from the <a href="/soundlib/">Sound Library</a>.
<?php else: ?>
    Nothing saved yet -- add and activate one from the <a href="/soundlib/">Sound Library</a>.
<?php endif; ?>
  </p>
<?php if ($hasCustomMessage): ?>
  <form method="post">
    <button name="btnTestCustom" type="submit" class="mx-btn mx-btn-ghost">Test play</button>
  </form>
<?php endif; ?>

  <div class="mx-section">Alert message (D921#)</div>
  <p class="mx-hint">A second, independent message slot reserved for automated alerts (e.g. announcing
    sustained high temperature over the air) -- not wired up to trigger automatically yet, that's a
    separate decision to make later.
<?php if ($hasAlertMessage): ?>
    Currently set from the <a href="/soundlib/">Sound Library</a>.
<?php else: ?>
    Nothing saved yet -- add and activate one from the <a href="/soundlib/">Sound Library</a>.
<?php endif; ?>
  </p>
<?php if ($hasAlertMessage): ?>
  <form method="post">
    <button name="btnTestAlert" type="submit" class="mx-btn mx-btn-ghost">Test play</button>
  </form>
<?php endif; ?>

<?php if ($result): ?>
  <div class="mx-section">Last result</div>
  <table class="mx-table">
    <tr><th>Start temp</th><td><?php echo htmlspecialchars((string)$result['start_temp']); ?>&deg;C</td></tr>
    <tr><th>Peak temp</th><td><?php echo htmlspecialchars((string)$result['peak_temp']); ?>&deg;C</td></tr>
    <tr><th>End temp</th><td><?php echo htmlspecialchars((string)$result['end_temp']); ?>&deg;C</td></tr>
    <tr><th>Exchanges</th><td><?php echo htmlspecialchars((string)$result['exchanges']); ?></td></tr>
  </table>
<?php endif; ?>

<?php if ($log !== ''): ?>
  <div class="mx-section">Log</div>
  <textarea id="radiotest-log" readonly rows="14" style="width:100%; box-sizing:border-box; background:#111; color:#0f0; border:1px solid #000; font-family: 'Courier New', monospace; font-size:11px; padding:8px; border-radius:6px;"><?php echo htmlspecialchars($log); ?></textarea>
  <script>document.getElementById('radiotest-log').scrollTop = 1e9;</script>
<?php endif; ?>

</div>
<?php if ($running): ?>
<script>setTimeout(function () { location.reload(); }, 3000);</script>
<?php endif; ?>
</body>
</html>
