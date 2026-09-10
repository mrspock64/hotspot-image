<?php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/buttons_store.php';

$message = null;
$error = null;

if (isset($_POST['btnSave'])) {
    $labels = $_POST['label'] ?? [];
    $dtmfs = $_POST['dtmf'] ?? [];
    $colors = $_POST['color'] ?? [];

    $buttons = [];
    foreach ($labels as $i => $label) {
        $label = trim($label);
        $dtmf = trim($dtmfs[$i] ?? '');
        $color = $colors[$i] ?? 'green';
        if ($label === '' && $dtmf === '') {
            continue; // dropped row
        }
        if (!in_array($color, BUTTON_COLORS, true)) {
            $color = 'green';
        }
        $buttons[] = ['label' => $label, 'dtmf' => $dtmf, 'color' => $color];
    }

    $invalid = array_filter($buttons, fn($b) => $b['label'] === '' || $b['dtmf'] === '');
    if ($invalid) {
        $error = 'Every button needs both a label and a DTMF command — remove empty rows instead of leaving them blank.';
    } else {
        try {
            saveButtons($buttons);
            $message = 'Buttons saved.';
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
}

$buttons = loadButtons();
if (isset($_POST['btnSave']) && !$error) {
    // reflect what was actually saved, not a stale disk read
    $buttons = array_values(array_filter(
        array_map(function ($label, $dtmf, $color) {
            $label = trim($label);
            $dtmf = trim($dtmf);
            if ($label === '' && $dtmf === '') return null;
            return ['label' => $label, 'dtmf' => $dtmf, 'color' => in_array($color, BUTTON_COLORS, true) ? $color : 'green'];
        }, $_POST['label'] ?? [], $_POST['dtmf'] ?? [], $_POST['color'] ?? []),
        fn($b) => $b !== null
    ));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link href="/css/css.php" type="text/css" rel="stylesheet" />
<link href="/css/modern.css" type="text/css" rel="stylesheet" />
<title>Buttons</title>
</head>
<body style="background: var(--mx-bg);">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>

<div class="mx-card">
  <h1>Front-page Buttons</h1>
  <p class="mx-sub">These are the DTMF quick-action buttons shown at the top of the Dashboard and Talk Groups pages.</p>

<?php if ($message): ?><div class="mx-msg mx-msg-ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="mx-msg mx-msg-err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="mx-preview">
<?php foreach ($buttons as $btn): ?>
    <span class="<?php echo htmlspecialchars($btn['color']); ?>"><?php echo htmlspecialchars($btn['label'] ?: '(empty)'); ?></span>
<?php endforeach; ?>
<?php if (!$buttons): ?><span style="color: var(--mx-text-dim); font-size: 13px;">No buttons configured.</span><?php endif; ?>
  </div>

  <form method="post" id="buttonsForm">
    <table class="mx-table">
      <tr><th style="width:36%">Label</th><th style="width:34%">DTMF command</th><th style="width:16%">Color</th><th style="width:14%"></th></tr>
      <tbody id="buttonRows">
<?php foreach ($buttons as $btn): ?>
      <tr>
        <td><input type="text" name="label[]" value="<?php echo htmlspecialchars($btn['label']); ?>" placeholder="TG4"></td>
        <td><input type="text" name="dtmf[]" value="<?php echo htmlspecialchars($btn['dtmf']); ?>" placeholder="914#"></td>
        <td>
          <select name="color[]">
<?php foreach (BUTTON_COLORS as $c): ?>
            <option value="<?php echo $c; ?>" <?php echo $c === $btn['color'] ? 'selected' : ''; ?>><?php echo ucfirst($c); ?></option>
<?php endforeach; ?>
          </select>
        </td>
        <td><button type="button" class="mx-btn mx-btn-ghost" onclick="this.closest('tr').remove()">Remove</button></td>
      </tr>
<?php endforeach; ?>
      </tbody>
    </table>

    <p>
      <button type="button" class="mx-btn mx-btn-ghost" onclick="addRow()">+ Add button</button>
      <button type="button" class="mx-btn mx-btn-ghost" onclick="addSm0to7()">+ Insert SM0–SM7 + utility shortcuts</button>
    </p>

    <p style="margin-top:18px;">
      <button type="submit" name="btnSave" class="mx-btn">Save</button>
    </p>
  </form>
</div>

<template id="rowTemplate">
  <tr>
    <td><input type="text" name="label[]" value="" placeholder="TG4"></td>
    <td><input type="text" name="dtmf[]" value="" placeholder="914#"></td>
    <td>
      <select name="color[]">
<?php foreach (BUTTON_COLORS as $c): ?>
        <option value="<?php echo $c; ?>"><?php echo ucfirst($c); ?></option>
<?php endforeach; ?>
      </select>
    </td>
    <td><button type="button" class="mx-btn mx-btn-ghost" onclick="this.closest('tr').remove()">Remove</button></td>
  </tr>
</template>
<script>
function addRow() {
  const tpl = document.getElementById('rowTemplate');
  document.getElementById('buttonRows').appendChild(tpl.content.cloneNode(true));
}

// "91" + TG + "#" is the verified ReflectorLogic select command (this
// hotspot's NetLink command prefix is "9", "1" is SvxLink's own "select
// talk group" sub-command) -- confirmed against SvxLink 1.10.1's own
// ReflectorLogic.cpp source and tested live. TG 2400-2407 are the Swedish
// district talkgroups (Stockholm, Gotland, ...).
//
// TALKGROUP/STATUS/PARROT/IPADRESS are the existing utility commands
// already saved on this node (D911# is the custom IP-readout command in
// this project's own Logic.tcl) -- reused as-is, not re-derived.
function addQuickButtonRow(label, dtmf, color) {
  const tpl = document.getElementById('rowTemplate');
  const row = tpl.content.cloneNode(true);
  row.querySelector('input[name="label[]"]').value = label;
  row.querySelector('input[name="dtmf[]"]').value = dtmf;
  row.querySelector('select[name="color[]"]').value = color;
  document.getElementById('buttonRows').appendChild(row);
}

function addSm0to7() {
  addQuickButtonRow('SM', '91240#', 'green');
  for (let i = 0; i <= 7; i++) {
    addQuickButtonRow('SM' + i, '91' + (2400 + i) + '#', 'green');
  }
  addQuickButtonRow('TALKGROUP', '9*#', 'red');
  addQuickButtonRow('STATUS', '*#', 'red');
  addQuickButtonRow('PARROT', 'D1#', 'red');
  addQuickButtonRow('IPADRESS', 'D911#', 'red');
}
</script>
</body>
</html>
