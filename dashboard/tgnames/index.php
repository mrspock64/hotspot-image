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

    // A TG that's currently monitored (Talk Groups page) but gets removed
    // here would keep listening -- MONITOR_TGS in svxlink.conf doesn't
    // know or care whether this list still names it -- while vanishing
    // from every page that reads this list, including Talk Groups itself.
    // Caught here as the authoritative check; the Remove button is also
    // disabled client-side for these rows so this should be rare.
    $removedMonitored = array_intersect(
        array_map('strval', array_keys(loadTgDb())),
        loadMonitoredTgNumbers()
    );
    $removedMonitored = array_diff($removedMonitored, array_keys($tgdb));

    if ($invalidNumber) {
        $error = 'TG numbers must be plain numbers (e.g. 240, not "TG240").';
    } elseif ($invalidName) {
        $error = 'Every talkgroup needs a name — remove empty rows instead of leaving them blank.';
    } elseif ($removedMonitored) {
        $error = 'Can\'t remove TG ' . implode(', ', $removedMonitored) . ' — still monitored. '
            . 'Uncheck it on the Talk Groups page first, then remove it here.';
    } else {
        try {
            saveTgDb($tgdb);
            $message = 'Talk group names saved.';
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
}

if (isset($_POST['btnImport'])) {
    // Merge into whatever's currently in the form (including unsaved
    // edits), not a fresh disk read -- Import is just another submit
    // button in the same form, so the browser sends every existing
    // tg[]/name[] row along with it.
    $tgdb = [];
    foreach (($_POST['tg'] ?? []) as $i => $tg) {
        $tg = trim($tg);
        $name = trim(($_POST['name'] ?? [])[$i] ?? '');
        if ($tg === '' && $name === '') continue;
        $tgdb[$tg] = $name;
    }
    $added = 0;
    foreach (loadMonitoredTgNumbers() as $tg) {
        if (!array_key_exists($tg, $tgdb)) {
            // Default the name to the TG number itself -- a real label
            // beats a blank one as a starting point, and it means Save
            // works right away without the "every talkgroup needs a name"
            // validation forcing a detour through every row first.
            $tgdb[$tg] = $tg;
            $added++;
        }
    }
    uksort($tgdb, fn($a, $b) => (int)$a <=> (int)$b);
    $message = $added > 0
        ? "Added $added talkgroup(s) from the Talk Groups page's monitored list, named after their numbers for now — edit the names below and Save."
        : "Nothing to add — every monitored talkgroup is already listed below.";
} elseif (isset($_POST['btnSave']) && !$error) {
    // reflect what was actually saved, not a stale disk read
    $tgdb = [];
    foreach (($_POST['tg'] ?? []) as $i => $tg) {
        $tg = trim($tg);
        $name = trim(($_POST['name'] ?? [])[$i] ?? '');
        if ($tg === '' && $name === '') continue;
        $tgdb[$tg] = $name;
    }
    uksort($tgdb, fn($a, $b) => (int)$a <=> (int)$b);
} else {
    $tgdb = loadTgDb();
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
  <p class="mx-sub">How the two pages fit together: this page is the master list of talkgroups the dashboard
    knows about. <a href="/tg.php">Talk Groups</a> is where you pick which of them SvxLink actually
    monitors. A talkgroup can't be removed from here while it's still monitored there — uncheck it on
    Talk Groups first.</p>

<?php if ($message): ?><div class="mx-msg mx-msg-ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="mx-msg mx-msg-err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <form method="post" id="tgForm">
    <table class="mx-table">
      <tr><th style="width:30%">TG #</th><th style="width:55%">Name</th><th style="width:15%"></th></tr>
      <tbody id="tgRows">
<?php
$mxMonitoredForNames = loadMonitoredTgNumbers();
foreach ($tgdb as $tg => $name):
    $isMonitored = in_array((string)$tg, $mxMonitoredForNames, true);
?>
      <tr>
        <td><input type="text" name="tg[]" value="<?php echo htmlspecialchars((string)$tg); ?>" placeholder="240" <?php echo $isMonitored ? 'readonly' : ''; ?>></td>
        <td><input type="text" name="name[]" value="<?php echo htmlspecialchars((string)$name); ?>" placeholder="SM Repeaters"></td>
        <td>
<?php if ($isMonitored): ?>
          <span class="mx-btn mx-btn-ghost" style="opacity:.5;cursor:default;" title="Monitored on the Talk Groups page — uncheck it there first">Monitored</span>
<?php else: ?>
          <button type="button" class="mx-btn mx-btn-ghost" onclick="this.closest('tr').remove()">Remove</button>
<?php endif; ?>
        </td>
      </tr>
<?php endforeach; ?>
      </tbody>
    </table>

    <p>
      <button type="button" class="mx-btn mx-btn-ghost" onclick="addRow()">+ Add talkgroup</button>
      <button type="submit" name="btnImport" class="mx-btn mx-btn-ghost">⇩ Import from monitored talkgroups</button>
    </p>
    <p class="mx-hint" style="margin-top:-6px;">Pulls the TG numbers from the <a href="/tg.php">Talk Groups</a> page's monitored list — only adds ones not already listed below; doesn't touch existing names.</p>

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
