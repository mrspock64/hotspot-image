<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>Backup / Restore</title>
    <link href="/css/css.php" type="text/css" rel="stylesheet" />
<style type="text/css">
body { background-color: #eee; font-size: 15px; font-family: Arial; color: #444; }
fieldset.form {
  border:#3083b8 2px groove; box-shadow:5px 5px 20px #999; background-color:#f1f1f1;
  max-width:560px; width:95%; box-sizing:border-box; margin:15px auto; padding: 12px 20px 20px 20px;
  border-radius: 10px;
}
h1 { color:#00aee8; font: 18pt arial, sans-serif; font-weight:bold; text-shadow: 0.25px 0.25px gray; }
.section-title { font-weight:bold; color:#00aee8; margin: 16px 0 6px 0; border-bottom: 1px solid #ccc; }
.msg-ok { background:#d7f5da; border:1px solid #4aa361; padding:8px; border-radius:6px; margin-bottom:10px; white-space:pre-line; }
.msg-err { background:#f7d7d7; border:1px solid #c33; padding:8px; border-radius:6px; margin-bottom:10px; white-space:pre-line; }
p.hint { color:#777; font-size: 12px; }
</style>
  </head>
  <body>
<?php require_once __DIR__ . '/lib.php'; ?>
<fieldset class="form">
<center><h1>Backup / Restore</h1></center>

<p class="hint">
Bundles the reflector certificate (<code>/var/lib/svxlink/pki/</code>) plus
<code>svxlink.conf</code> and <code>node_info.json</code> — everything that's
specific to this node and would otherwise mean waiting on the sysop to
re-sign a certificate after a reinstall. Keep the downloaded file somewhere
private: it contains the node's private key.
</p>

<div class="section-title">Backup</div>
<p><a href="download.php"><button type="button" class="green" style="height:34px;width:220px;">Download backup</button></a></p>

<div class="section-title">Restore</div>
<?php
$errors = [];
$log = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup'])) {
    $upload = $_FILES['backup'];
    if ($upload['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload failed (error code ' . $upload['error'] . ').';
    } else {
        try {
            $log = restoreFromTarball($upload['tmp_name']);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>

<?php foreach ($errors as $err): ?>
  <div class="msg-err"><?php echo htmlspecialchars($err); ?></div>
<?php endforeach; ?>

<?php if (!empty($log)): ?>
  <div class="msg-ok"><?php echo htmlspecialchars(implode("\n", $log)); ?>

Restart SvxLink from the <a href="/power/">Power</a> page for changes to take effect.</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <input type="file" name="backup" accept=".tgz,.tar.gz" required>
  <br><br>
  <button type="submit" class="red" style="height:34px;width:220px;">Restore from file</button>
</form>

</fieldset>
  </body>
</html>
