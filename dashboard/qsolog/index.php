<?php
require_once __DIR__ . '/../include/qso_recorder.php';

$error = null;
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    try {
        deleteQsoRecording($_POST['delete_file']);
        $message = 'Recording deleted.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $active = isset($_POST['active']);
    $maxDirsize = trim($_POST['max_dirsize'] ?? '');
    $qsoTimeout = trim($_POST['qso_timeout'] ?? '');
    if (!ctype_digit($maxDirsize) || (int)$maxDirsize < 100) {
        $error = 'Disk limit must be a number of megabytes, at least 100.';
    } elseif (!ctype_digit($qsoTimeout) || (int)$qsoTimeout < 1) {
        $error = 'QSO gap must be a number of seconds, at least 1.';
    } else {
        try {
            saveQsoRecorderSettings($active, (int)$maxDirsize, (int)$qsoTimeout);
            $message = 'Settings saved. On/off applied immediately -- the disk limit and QSO gap need a SvxLink restart (Power page) to take effect.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$settings = getQsoRecorderSettings();
$recordings = listQsoRecordings();

/**
 * Parses the timestamp SvxLink embeds in the filename -- either just a
 * start time ("qsorec_<Logic>_<YYYY-MM-DD>_<HHMMSS>.mp3") or start+end
 * ("qsorec_<Logic>_<start>_<end>.mp3"); the non-greedy logic-name match
 * naturally stops at the first timestamp either way.
 */
function qsoRecordingLabel(string $file): string
{
    if (preg_match('/^qsorec_(.+?)_(\d{4}-\d{2}-\d{2})_(\d{6})(?:_\d{4}-\d{2}-\d{2}_\d{6})?\.(?:mp3|ogg|wav)$/', $file, $m)) {
        [, $logic, $ymd, $his] = $m;
        $dt = DateTime::createFromFormat('Y-m-d His', $ymd . ' ' . $his);
        if ($dt) {
            return $dt->format('Y-m-d H:i:s') . ' (' . $logic . ')';
        }
    }
    return $file;
}

function formatBytes(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / (1024 * 1024), 1) . ' MB';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>QSO Log</title>
<link href="/css/css.php" type="text/css" rel="stylesheet" />
<link href="/css/modern.css" type="text/css" rel="stylesheet" />
</head>
<body style="background: var(--mx-bg);">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>

<div class="mx-card">
  <h1>QSO Log</h1>
  <p class="mx-sub">Every transmission is recorded automatically (SvxLink's built-in QSO Recorder) -- useful history if there's ever a question about interference or unauthorized use.</p>

<?php if ($message): ?><div class="mx-msg mx-msg-ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="mx-msg mx-msg-err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <form method="post" style="display:flex; align-items:flex-end; gap:20px; flex-wrap:wrap; padding:14px; background:var(--mx-bg); border-radius:8px; margin-bottom:16px;">
    <div>
      <label style="font-weight:600; font-size:12.5px; display:block; margin-bottom:4px;">Logging</label>
      <label style="font-size:13px; font-weight:normal;">
        <input type="checkbox" name="active" <?php echo $settings['active'] ? 'checked' : ''; ?> style="width:auto; vertical-align:middle;">
        Record every transmission
      </label>
    </div>
    <div>
      <label for="max_dirsize" style="font-weight:600; font-size:12.5px; display:block; margin-bottom:4px;">Disk limit (MB)</label>
      <input type="text" id="max_dirsize" name="max_dirsize" value="<?php echo htmlspecialchars((string)$settings['max_dirsize']); ?>" style="width:100px; margin:0;">
    </div>
    <div>
      <label for="qso_timeout" style="font-weight:600; font-size:12.5px; display:block; margin-bottom:4px;">New file after (sec of silence)</label>
      <input type="text" id="qso_timeout" name="qso_timeout" value="<?php echo htmlspecialchars((string)$settings['qso_timeout']); ?>" style="width:100px; margin:0;">
    </div>
    <button type="submit" name="save_settings" class="mx-btn">Save</button>
  </form>
  <p class="mx-hint" style="margin-top:-10px;">A gap of at least this long between transmissions starts a new recording -- shorter means one file per transmission, longer groups a whole back-and-forth exchange into one file.</p>

<?php if ($recordings['inProgress']): ?>
  <div class="mx-msg" style="background:#fef3c7;border:1px solid #fbbf24;color:#92400e;">
    ● Recording now — <?php echo htmlspecialchars(qsoRecordingLabel($recordings['inProgress']['file'])); ?>
    (<?php echo formatBytes($recordings['inProgress']['size']); ?> so far)
  </div>
<?php endif; ?>

  <audio id="qsoPlayer" controls style="width:100%; margin-bottom:14px; display:none;"></audio>

<?php if (!$recordings['finished']): ?>
  <p style="color: var(--mx-text-dim); font-size: 13px;">No recordings yet.</p>
<?php else: ?>
  <table class="mx-table">
    <tr><th>When</th><th>Size</th><th></th><th></th></tr>
<?php foreach ($recordings['finished'] as $rec): ?>
    <tr>
      <td><?php echo htmlspecialchars(qsoRecordingLabel($rec['file'])); ?></td>
      <td><?php echo formatBytes($rec['size']); ?></td>
      <td>
        <button type="button" class="mx-btn mx-btn-ghost"
          onclick="playRecording(<?php echo json_encode($rec['file']); ?>)">▶ Play</button>
      </td>
      <td>
        <form method="post" style="margin:0;" onsubmit="return confirm('Delete this recording? This cannot be undone.');">
          <input type="hidden" name="delete_file" value="<?php echo htmlspecialchars($rec['file']); ?>">
          <button type="submit" class="mx-btn mx-btn-danger" style="font-size:11px;padding:4px 10px;">Delete</button>
        </form>
      </td>
    </tr>
<?php endforeach; ?>
  </table>
<?php endif; ?>
</div>

<script>
function playRecording(file) {
  const player = document.getElementById('qsoPlayer');
  player.src = '/qsolog/play.php?file=' + encodeURIComponent(file);
  player.style.display = 'block';
  player.play();
}
</script>
</body>
</html>
