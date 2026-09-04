<?php
require_once __DIR__ . '/lib.php';

$errors = [];
$log = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup'])) {
    $upload = $_FILES['backup'];
    if ($upload['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload failed (error code ' . $upload['error'] . ').';
    } else {
        try {
            $log = restoreFromZip($upload['tmp_name']);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup_path'])) {
    try {
        $log = [restoreConfigBackup($_POST['restore_backup_path'])];
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>Backup / Restore</title>
    <link href="/css/css.php" type="text/css" rel="stylesheet" />
    <link href="/css/modern.css" type="text/css" rel="stylesheet" />
<style type="text/css">
.msg-ok { background:#d7f5da; border:1px solid #4aa361; padding:8px; border-radius:6px; margin-bottom:10px; white-space:pre-line; }
.msg-err { background:#f7d7d7; border:1px solid #c33; padding:8px; border-radius:6px; margin-bottom:10px; white-space:pre-line; }
p.hint { color: var(--mx-text-dim); font-size: 12px; }
</style>
  </head>
  <body style="background: var(--mx-bg);">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>
<div class="mx-card">
  <h1>Backup / Restore</h1>

<p class="hint">
Bundles the reflector certificate (<code>/var/lib/svxlink/pki/</code>),
<code>svxlink.conf</code>, <code>node_info.json</code>, and the
<a href="/buttons/">front-page buttons</a> config (if you've customized
it) — everything that's specific to this node and would otherwise mean
waiting on the sysop to re-sign a certificate after a reinstall. Keep the
downloaded file somewhere private: it contains the node's private key.
</p>

<?php foreach ($errors as $err): ?>
  <div class="msg-err"><?php echo htmlspecialchars($err); ?></div>
<?php endforeach; ?>

<?php if (!empty($log)): ?>
  <div class="msg-ok"><?php echo htmlspecialchars(implode("\n", $log)); ?>

Restart SvxLink from the <a href="/power/">Power</a> page for changes to take effect.</div>
<?php endif; ?>

<div style="display:flex; gap:24px; flex-wrap:wrap; margin-bottom:10px;">
  <div style="flex:1; min-width:220px;">
    <div class="mx-section" style="margin-top:0;">Backup</div>
    <a href="download.php"><button type="button" class="mx-btn">Download backup</button></a>
  </div>
  <div style="flex:1; min-width:220px;">
    <div class="mx-section" style="margin-top:0;">Restore from file</div>
    <form method="post" enctype="multipart/form-data" style="margin:0;">
      <input type="file" name="backup" accept=".zip" required style="display:block; margin-bottom:8px;">
      <button type="submit" class="mx-btn">Restore from file</button>
    </form>
  </div>
</div>

<div class="mx-section">Previous versions</div>
<p class="hint">
Every save from the <a href="/setup/">Setup</a> page (and every restore above) automatically
keeps a timestamped copy of what it replaced. Nothing new to configure — this just lists them.
</p>
<?php $backups = listConfigBackups(); ?>
<?php if (empty($backups)): ?>
  <p class="hint">No automatic backups yet — they appear here after the first Setup save.</p>
<?php else: ?>
  <table class="mx-table">
    <tr><th>File</th><th>Saved</th><th>Size</th><th></th></tr>
    <?php foreach ($backups as $b): ?>
      <tr>
        <td><?php echo htmlspecialchars(basename($b['file'])); ?></td>
        <td><?php echo htmlspecialchars($b['timestamp']->format('Y-m-d H:i:s')); ?></td>
        <td><?php echo htmlspecialchars((string)$b['size']); ?> B</td>
        <td>
          <form method="post" style="margin:0;" onsubmit="return confirm('Restore this version of ' + <?php echo json_encode(basename($b['file'])); ?> + '? The current version will itself be backed up first.');">
            <input type="hidden" name="restore_backup_path" value="<?php echo htmlspecialchars($b['backup']); ?>">
            <button type="submit" class="mx-btn mx-btn-danger" style="font-size:11px;padding:4px 10px;">Restore</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

</div>
  </body>
</html>
