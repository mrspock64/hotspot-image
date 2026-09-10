<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>Sound Library</title>
    <link href="/css/modern.css" type="text/css" rel="stylesheet" />
  </head>
<body style="background: var(--mx-bg); margin: 0;">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>

<?php
require_once __DIR__ . '/../include/sound_library.php';

$error = null;
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnAddTts'])) {
    try {
        $entry = addLibraryEntryFromTts($_POST['tts_name'] ?? '', $_POST['tts_text'] ?? '', $_POST['tts_voice'] ?? 'sv');
        $message = 'Saved "' . $entry['name'] . '" to the library.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnAddUpload']) && isset($_FILES['upload_file'])) {
    $upload = $_FILES['upload_file'];
    if ($upload['error'] !== UPLOAD_ERR_OK) {
        $error = $upload['error'] === UPLOAD_ERR_NO_FILE
            ? 'Choose a file to upload first.'
            : 'Upload failed (error code ' . $upload['error'] . ').';
    } elseif (!is_uploaded_file($upload['tmp_name'])) {
        $error = 'Upload failed.';
    } else {
        try {
            $entry = addLibraryEntryFromUpload($_POST['upload_name'] ?? '', $upload['tmp_name']);
            $message = 'Saved "' . $entry['name'] . '" to the library.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnActivate'])) {
    try {
        activateLibraryEntry($_POST['slot'] ?? '', $_POST['id'] ?? '');
        $message = 'Activated.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnDelete'])) {
    try {
        deleteLibraryEntry($_POST['id'] ?? '');
        $message = 'Deleted.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnTest'])) {
    $slot = $_POST['slot'] ?? '';
    if (array_key_exists($slot, LIBRARY_SLOTS)) {
        sendDtmfReliable(strtoupper($slot) . '#');
        $message = 'Sent ' . strtoupper($slot) . '# -- listen for it.';
    }
}

$library = loadLibrary();
?>

<div class="mx-card" style="max-width: 700px;">
  <h1>Sound Library</h1>
  <p class="mx-sub">
    Named D920#/D921# messages you can create once and switch between, instead of the single
    overwrite-on-save slot each command used to be. Activating an entry copies it into that
    command's slot -- see the <a href="/radiotest/">Radio Test</a> page to actually transmit it.
  </p>

<?php if ($message): ?>
  <div class="mx-msg mx-msg-ok"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="mx-msg mx-msg-err"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

  <div class="mx-section">Add from text (TTS)</div>
  <form method="post">
    <div class="mx-row"><label for="tts_name">Name</label>
      <input type="text" id="tts_name" name="tts_name" placeholder="e.g. Weekend test announcement" style="width:100%; box-sizing:border-box;"></div>
    <div class="mx-row"><label for="tts_text">Text</label>
      <input type="text" id="tts_text" name="tts_text" placeholder="e.g. Test transmission from SA0LEK" style="width:100%; box-sizing:border-box;"></div>
    <div class="mx-row"><label for="tts_voice">Voice</label>
      <select id="tts_voice" name="tts_voice">
<?php foreach (TTS_VOICES as $code => $label): ?>
        <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($label); ?></option>
<?php endforeach; ?>
      </select>
    </div>
    <button name="btnAddTts" type="submit" class="mx-btn">Save to library</button>
  </form>

  <div class="mx-section">Add a recording</div>
  <p class="mx-hint">Any common audio format (m4a from a phone voice memo, mp3, wav, ...) -- converted automatically.
    A real recording, not TTS -- will sound far more natural than espeak-ng's synthesized voice.</p>
  <form method="post" enctype="multipart/form-data">
    <div class="mx-row"><label for="upload_name">Name</label>
      <input type="text" id="upload_name" name="upload_name" placeholder="e.g. My own recording" style="width:100%; box-sizing:border-box;"></div>
    <div class="mx-row"><label for="upload_file">File</label>
      <input type="file" id="upload_file" name="upload_file" accept="audio/*"></div>
    <button name="btnAddUpload" type="submit" class="mx-btn">Upload &amp; save to library</button>
  </form>

  <div class="mx-section">Library</div>
<?php if (empty($library['entries'])): ?>
  <p class="mx-hint">Nothing saved yet.</p>
<?php else: ?>
  <table class="mx-table">
    <tr><th>Name</th><th>Source</th><th>Length</th><th>Active for</th><th></th></tr>
<?php foreach (array_reverse($library['entries']) as $entry): ?>
    <tr>
      <td>
        <?php echo htmlspecialchars($entry['name']); ?>
<?php if (($entry['source'] ?? '') === 'tts'): ?>
        <div style="font-size:11px;color:var(--mx-text-dim);"><?php echo htmlspecialchars($entry['text'] ?? ''); ?> (<?php echo htmlspecialchars($entry['voice'] ?? ''); ?>)</div>
<?php endif; ?>
      </td>
      <td><?php echo ($entry['source'] ?? '') === 'tts' ? 'TTS' : 'Recording'; ?></td>
      <td><?php echo $entry['duration'] !== null ? htmlspecialchars((string)$entry['duration']) . 's' : '?'; ?></td>
      <td>
<?php foreach (['d920' => 'Custom (D920#)', 'd921' => 'Alert (D921#)'] as $slot => $label):
    $isActive = ($library['active'][$slot] ?? null) === $entry['id']; ?>
<?php if ($isActive): ?>
        <span style="display:inline-block;background:#dcfce7;color:#166534;border-radius:6px;padding:2px 6px;font-size:11px;margin:2px 4px 2px 0;"><?php echo htmlspecialchars($label); ?></span>
        <form method="post" style="display:inline;">
          <input type="hidden" name="slot" value="<?php echo htmlspecialchars($slot); ?>">
          <button name="btnTest" type="submit" class="mx-btn mx-btn-ghost" style="padding:2px 8px;font-size:11px;">Test</button>
        </form>
<?php else: ?>
        <form method="post" style="display:inline;">
          <input type="hidden" name="slot" value="<?php echo htmlspecialchars($slot); ?>">
          <input type="hidden" name="id" value="<?php echo htmlspecialchars($entry['id']); ?>">
          <button name="btnActivate" type="submit" class="mx-btn mx-btn-ghost" style="padding:2px 8px;font-size:11px;margin:2px 4px 2px 0;">Set as <?php echo htmlspecialchars($label); ?></button>
        </form>
<?php endif; ?>
<?php endforeach; ?>
      </td>
      <td>
        <form method="post" onsubmit="return confirm('Delete this library entry? Anything currently active in a slot keeps playing until replaced.');">
          <input type="hidden" name="id" value="<?php echo htmlspecialchars($entry['id']); ?>">
          <button name="btnDelete" type="submit" class="mx-btn mx-btn-danger" style="padding:2px 8px;font-size:11px;">Delete</button>
        </form>
      </td>
    </tr>
<?php endforeach; ?>
  </table>
<?php endif; ?>

</div>
</body>
</html>
