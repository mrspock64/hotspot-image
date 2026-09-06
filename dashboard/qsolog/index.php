<?php
require_once __DIR__ . '/../include/qso_recorder.php';
require_once __DIR__ . '/../include/tgdb_store.php';

const RECORDINGS_PER_PAGE = 25;

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all'])) {
    try {
        $n = deleteAllQsoRecordings();
        $message = $n === 1 ? '1 recording deleted.' : "$n recordings deleted.";
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_page'])) {
    try {
        $n = deleteQsoRecordings($_POST['page_files'] ?? []);
        $message = $n === 1 ? '1 recording deleted.' : "$n recordings deleted.";
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $active = isset($_POST['active']);
    $maxDirsize = trim($_POST['max_dirsize'] ?? '');
    $qsoTimeout = trim($_POST['qso_timeout'] ?? '');
    $maxRecordings = trim($_POST['max_recordings'] ?? '0');
    $recordOnly = array_filter($_POST['record_only'] ?? [], fn($tg) => ctype_digit($tg));
    if (!ctype_digit($maxDirsize) || (int)$maxDirsize < 100) {
        $error = 'Disk limit must be a number of megabytes, at least 100.';
    } elseif (!ctype_digit($qsoTimeout) || (int)$qsoTimeout < 1) {
        $error = 'QSO gap must be a number of seconds, at least 1.';
    } elseif (!ctype_digit($maxRecordings)) {
        $error = 'Max recordings must be a number (0 for no limit).';
    } else {
        try {
            saveQsoRecorderSettings($active, (int)$maxDirsize, (int)$qsoTimeout, (int)$maxRecordings, array_values($recordOnly));
            $message = 'Settings saved. On/off, the talkgroup filter, and max recordings apply immediately -- the disk limit and QSO gap need a SvxLink restart (Power page) to take effect.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$settings = getQsoRecorderSettings();
$recordings = listQsoRecordings();
$tgDb = loadTgDb();
ksort($tgDb, SORT_NUMERIC);

$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, (int)ceil(count($recordings['finished']) / RECORDINGS_PER_PAGE));
$page = min($page, $totalPages);
$pageItems = array_slice($recordings['finished'], ($page - 1) * RECORDINGS_PER_PAGE, RECORDINGS_PER_PAGE);

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
  <p class="mx-sub">Records transmissions when switched on (SvxLink's built-in QSO Recorder) -- off by default, useful to turn on if there's ever a question about interference or unauthorized use.</p>

<?php if ($message): ?><div class="mx-msg mx-msg-ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="mx-msg mx-msg-err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if (!empty($settings['record_only_tgs'])): ?>
  <div class="mx-msg" style="background:#fef3c7;border:1px solid #fbbf24;color:#92400e;">
    &#9888; Recording is filtered — only TG <?php echo htmlspecialchars(implode(', ', $settings['record_only_tgs'])); ?> will be kept. Everything else is recorded then immediately discarded. Select "All talkgroups" below and Save if that's not intended.
  </div>
<?php endif; ?>

  <form method="post" style="padding:14px; background:var(--mx-bg); border-radius:8px; margin-bottom:16px;">
    <div style="display:flex; align-items:flex-end; gap:20px; flex-wrap:wrap;">
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
      <div>
        <label for="max_recordings" style="font-weight:600; font-size:12.5px; display:block; margin-bottom:4px;">Max recordings to keep</label>
        <input type="text" id="max_recordings" name="max_recordings" value="<?php echo htmlspecialchars((string)$settings['max_recordings']); ?>" style="width:100px; margin:0;">
      </div>
    </div>
    <p class="mx-hint" style="margin:8px 0 4px;">A gap of at least this long between transmissions starts a new recording -- shorter means one file per transmission, longer groups a whole back-and-forth exchange into one file. Max recordings keeps only the newest N (0 = no limit) -- on top of, not instead of, the disk limit above.</p>

    <label style="font-weight:600; font-size:12.5px; display:block; margin:12px 0 4px;">Record only these talkgroups</label>
    <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:4px;">
      <label style="display:flex;align-items:center;gap:4px;font-size:13px;background:#fff;padding:3px 8px;border-radius:6px;border:1px solid var(--mx-border, #ddd);">
        <input type="checkbox" id="record_only_all" <?php echo empty($settings['record_only_tgs']) ? 'checked' : ''; ?>
          onchange="document.querySelectorAll('.mx-record-only-tg').forEach(c => c.disabled = this.checked)">
        All talkgroups
      </label>
<?php foreach ($tgDb as $tg => $name): $tg = (string)$tg; ?>
      <label style="display:flex;align-items:center;gap:4px;font-size:13px;background:#f1f1f1;padding:3px 8px;border-radius:6px;">
        <input type="checkbox" class="mx-record-only-tg" name="record_only[]" value="<?php echo htmlspecialchars($tg); ?>"
          <?php echo in_array($tg, $settings['record_only_tgs'], true) ? 'checked' : ''; ?>
          <?php echo empty($settings['record_only_tgs']) ? 'disabled' : ''; ?>>
        <?php echo htmlspecialchars($tg); ?><?php echo ($name !== '' && $name !== $tg) ? ' (' . htmlspecialchars($name) . ')' : ''; ?>
      </label>
<?php endforeach; ?>
    </div>
    <p class="mx-hint" style="margin:4px 0 12px;">Recordings whose talkgroup can't be determined (local-only transmissions) are always kept, regardless of this filter. Applies to the next recording immediately -- no restart needed.</p>

    <button type="submit" name="save_settings" class="mx-btn">Save</button>
  </form>

<?php if ($recordings['inProgress']): ?>
  <div class="mx-msg" style="background:#fef3c7;border:1px solid #fbbf24;color:#92400e;">
    ● Recording now — <?php echo htmlspecialchars(qsoRecordingInfo($recordings['inProgress']['file'])['when']); ?>
    (<?php echo formatBytes($recordings['inProgress']['size']); ?> so far)
  </div>
<?php endif; ?>

  <audio id="qsoPlayer" controls style="width:100%; margin-bottom:14px; display:none;"></audio>

<?php if (!$recordings['finished']): ?>
  <p style="color: var(--mx-text-dim); font-size: 13px;">No recordings yet.</p>
<?php else: ?>
  <div style="display:flex; align-items:center; justify-content:space-between; margin-top:0; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
    <p class="mx-hint" style="margin:0;"><?php echo count($recordings['finished']); ?> recording(s) total.</p>
    <div style="display:flex; gap:8px;">
      <form method="post" style="margin:0;" onsubmit="return confirm('Delete the <?php echo count($pageItems); ?> recording(s) on this page? This cannot be undone.');">
<?php foreach ($pageItems as $rec): ?>
        <input type="hidden" name="page_files[]" value="<?php echo htmlspecialchars($rec['file']); ?>">
<?php endforeach; ?>
        <button type="submit" name="clear_page" class="mx-btn mx-btn-ghost" style="font-size:11px; padding:4px 10px;">Clear this page (<?php echo count($pageItems); ?>)</button>
      </form>
      <form method="post" style="margin:0;" onsubmit="return confirm('Delete ALL <?php echo count($recordings['finished']); ?> recordings? This cannot be undone.');">
        <button type="submit" name="clear_all" class="mx-btn mx-btn-danger" style="font-size:11px; padding:4px 10px;">Clear all (<?php echo count($recordings['finished']); ?>)</button>
      </form>
    </div>
  </div>
  <table class="mx-table">
    <tr><th>When</th><th>TG</th><th>Callsign</th><th>Size</th><th></th><th></th><th></th></tr>
<?php foreach ($pageItems as $rec): $info = qsoRecordingInfo($rec['file']); ?>
    <tr>
      <td><?php echo htmlspecialchars($info['when']); ?></td>
      <td><?php
        if ($info['tg'] === null) {
            echo '<span style="color:var(--mx-text-dim);">&mdash;</span>';
        } else {
            $tgName = $tgDb[$info['tg']] ?? null;
            echo htmlspecialchars($info['tg']) . ($tgName && $tgName !== $info['tg'] ? ' (' . htmlspecialchars($tgName) . ')' : '');
        }
      ?></td>
      <td><?php echo $info['callsign'] !== null ? htmlspecialchars($info['callsign']) : '<span style="color:var(--mx-text-dim);">&mdash;</span>'; ?></td>
      <td><?php echo formatBytes($rec['size']); ?></td>
      <td>
        <button type="button" class="mx-btn mx-btn-ghost"
          onclick="playRecording(<?php echo htmlspecialchars(json_encode($rec['file']), ENT_QUOTES); ?>)">▶ Play</button>
      </td>
      <td>
        <a class="mx-btn mx-btn-ghost" style="text-decoration:none; display:inline-block;"
          href="play.php?file=<?php echo urlencode($rec['file']); ?>&amp;dl=1">⬇ Download</a>
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
<?php if ($totalPages > 1): ?>
  <div style="display:flex; gap:6px; justify-content:center; margin-top:14px;">
<?php for ($p = 1; $p <= $totalPages; $p++): ?>
    <a href="?page=<?php echo $p; ?>" class="mx-btn <?php echo $p === $page ? '' : 'mx-btn-ghost'; ?>" style="padding:4px 10px; font-size:12px; text-decoration:none;"><?php echo $p; ?></a>
<?php endfor; ?>
  </div>
<?php endif; ?>
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
