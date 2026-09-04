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

$recordings = listQsoRecordings();

/** Parses the timestamp SvxLink embeds in "qsorec_<Logic>_<YYYY-MM-DD>_<HHMMSS>.ogg". */
function qsoRecordingLabel(string $file): string
{
    if (preg_match('/qsorec_(.+?)_(\d{4}-\d{2}-\d{2})_(\d{6})\.(?:ogg|wav)$/', $file, $m)) {
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
