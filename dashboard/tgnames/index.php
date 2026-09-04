<?php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/tgdb_store.php';

$message = null;
$error = null;

if (isset($_POST['btnSave'])) {
    $tgNumbers = $_POST['tg'] ?? [];
    $tgNames = $_POST['name'] ?? [];

    $tgdb = [];
    $invalidNumber = false;
    $invalidName = false;
    foreach ($tgNumbers as $i => $tg) {
        $tg = trim($tg);
        $name = trim($tgNames[$i] ?? '');
        if ($tg === '' && $name === '') {
            continue; // dropped row
        }
        // Validated here, before $tg becomes an array key below -- PHP
        // silently coerces numeric string keys ("240") to real integers,
        // which broke ctype_digit() when it ran on array_keys($tgdb) after
        // the fact instead of on the original strings.
        if (!ctype_digit($tg)) {
            $invalidNumber = true;
        }
        if ($name === '') {
            $invalidName = true;
        }
        $tgdb[$tg] = $name;
    }

    if ($invalidNumber) {
        $error = 'TG numbers must be plain numbers (e.g. 240, not "TG240").';
    } elseif ($invalidName) {
        $error = 'Every talkgroup needs a name — remove empty rows instead of leaving them blank.';
    } else {
        try {
            saveTgDb($tgdb);
            $message = 'Talk group names saved.';
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
}

$tgdb = loadTgDb();
if (isset($_POST['btnSave']) && !$error) {
    // reflect what was actually saved, not a stale disk read
    $tgdb = [];
    foreach (($_POST['tg'] ?? []) as $i => $tg) {
        $tg = trim($tg);
        $name = trim(($_POST['name'] ?? [])[$i] ?? '');
        if ($tg === '' && $name === '') continue;
        $tgdb[$tg] = $name;
    }
    uksort($tgdb, fn($a, $b) => (int)$a <=> (int)$b);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link href="/css/css.php" type="text/css" rel="stylesheet" />
<link href="/css/modern.css" type="text/css" rel="stylesheet" />
<title>Talk Group Names</title>
</head>
<body style="background: var(--mx-bg);">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>

<div class="mx-card">
  <h1>Talk Group Names</h1>
  <p class="mx-sub">Labels shown next to each TG number on the <a href="/tg.php">Talk Groups</a> page.
    <b>M</b> there means "monitor" (listen to that talkgroup without switching to it), <b>A</b> means
    "activate" (switch to it). This just names the numbers — it doesn't change what your reflector
    actually carries.</p>

<?php if ($message): ?><div class="mx-msg mx-msg-ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="mx-msg mx-msg-err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <form method="post" id="tgForm">
    <table class="mx-table">
      <tr><th style="width:30%">TG #</th><th style="width:55%">Name</th><th style="width:15%"></th></tr>
      <tbody id="tgRows">
<?php foreach ($tgdb as $tg => $name): ?>
      <tr>
        <td><input type="text" name="tg[]" value="<?php echo htmlspecialchars((string)$tg); ?>" placeholder="240"></td>
        <td><input type="text" name="name[]" value="<?php echo htmlspecialchars((string)$name); ?>" placeholder="SM Repeaters"></td>
        <td><button type="button" class="mx-btn mx-btn-ghost" onclick="this.closest('tr').remove()">Remove</button></td>
      </tr>
<?php endforeach; ?>
      </tbody>
    </table>

    <p>
      <button type="button" class="mx-btn mx-btn-ghost" onclick="addRow()">+ Add talkgroup</button>
    </p>

    <p style="margin-top:18px;">
      <button type="submit" name="btnSave" class="mx-btn">Save</button>
    </p>
  </form>
</div>

<template id="rowTemplate">
  <tr>
    <td><input type="text" name="tg[]" value="" placeholder="240"></td>
    <td><input type="text" name="name[]" value="" placeholder="SM Repeaters"></td>
    <td><button type="button" class="mx-btn mx-btn-ghost" onclick="this.closest('tr').remove()">Remove</button></td>
  </tr>
</template>
<script>
function addRow() {
  const tpl = document.getElementById('rowTemplate');
  document.getElementById('tgRows').appendChild(tpl.content.cloneNode(true));
}
</script>
</body>
</html>
