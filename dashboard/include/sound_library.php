<?php
/**
 * A small library of saved D920#/D921# messages (text-to-speech or an
 * uploaded recording), so more than one message can exist at a time and
 * be picked between -- tts_message.php's TTS_OUTPUT_CUSTOM/TTS_OUTPUT_ALERT
 * only ever held a single, unnamed, overwrite-on-save file with no history.
 *
 * "Activating" a library entry for a slot (d920/custom or d921/alert)
 * copies its wav to that fixed path -- events.d/Logic.tcl's
 * dtmf_cmd_received still just plays whatever's at TTS_OUTPUT_CUSTOM/
 * TTS_OUTPUT_ALERT, unchanged. The library only adds a naming/history/
 * selection layer in front of that, it doesn't touch Logic.tcl at all.
 */

require_once __DIR__ . '/tts_message.php';

const LIBRARY_DIR = '/etc/svxlink/sound-library';
const LIBRARY_INDEX = LIBRARY_DIR . '/index.json';

const LIBRARY_SLOTS = [
    'd920' => TTS_OUTPUT_CUSTOM,
    'd921' => TTS_OUTPUT_ALERT,
];

function loadLibrary(): array
{
    if (!is_readable(LIBRARY_INDEX)) {
        return ['entries' => [], 'active' => ['d920' => null, 'd921' => null]];
    }
    $decoded = json_decode((string)file_get_contents(LIBRARY_INDEX), true);
    if (!is_array($decoded)) {
        return ['entries' => [], 'active' => ['d920' => null, 'd921' => null]];
    }
    $decoded['entries'] = $decoded['entries'] ?? [];
    $decoded['active'] = array_merge(['d920' => null, 'd921' => null], $decoded['active'] ?? []);
    return $decoded;
}

function saveLibraryIndex(array $data): void
{
    $tmp = tempnam(sys_get_temp_dir(), 'soundlib-index-');
    file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    exec('sudo mkdir -p ' . escapeshellarg(LIBRARY_DIR) . ' 2>&1', $out0, $code0);
    if ($code0 !== 0) {
        @unlink($tmp);
        throw new RuntimeException('Failed to create library directory: ' . implode(' ', $out0));
    }
    exec('sudo cp ' . escapeshellarg($tmp) . ' ' . escapeshellarg(LIBRARY_INDEX) . ' 2>&1', $out1, $code1);
    @unlink($tmp);
    if ($code1 !== 0) {
        throw new RuntimeException('Failed to write library index: ' . implode(' ', $out1));
    }
    exec('sudo chmod 644 ' . escapeshellarg(LIBRARY_INDEX) . ' 2>&1');
}

function clipDurationSeconds(string $path): ?float
{
    $out = trim((string)shell_exec('soxi -D ' . escapeshellarg($path) . ' 2>/dev/null'));
    return is_numeric($out) ? round((float)$out, 1) : null;
}

/**
 * Installs an already-generated 16kHz mono wav (at $tmpWavPath) into the
 * library under a fresh id, records it in the index, and returns the new
 * entry. Shared by the TTS and upload creation paths below.
 */
function installLibraryFile(string $tmpWavPath, string $name, array $meta): array
{
    $id = bin2hex(random_bytes(6));
    $destPath = LIBRARY_DIR . '/' . $id . '.wav';

    exec('sudo mkdir -p ' . escapeshellarg(LIBRARY_DIR) . ' 2>&1', $out0, $code0);
    if ($code0 !== 0) {
        throw new RuntimeException('Failed to create library directory: ' . implode(' ', $out0));
    }
    exec('sudo cp ' . escapeshellarg($tmpWavPath) . ' ' . escapeshellarg($destPath) . ' 2>&1', $out1, $code1);
    if ($code1 !== 0) {
        throw new RuntimeException('Failed to install library clip: ' . implode(' ', $out1));
    }
    exec('sudo chmod 644 ' . escapeshellarg($destPath) . ' 2>&1');

    $entry = array_merge([
        'id'         => $id,
        'name'       => $name,
        'created_at' => date('c'),
        'duration'   => clipDurationSeconds($tmpWavPath),
    ], $meta);

    $library = loadLibrary();
    $library['entries'][] = $entry;
    saveLibraryIndex($library);

    return $entry;
}

function addLibraryEntryFromTts(string $name, string $text, string $voice): array
{
    $name = trim($name);
    $text = trim($text);
    if ($name === '') {
        throw new InvalidArgumentException('Name is required.');
    }
    if ($text === '') {
        throw new InvalidArgumentException('Message text is empty.');
    }
    if (!array_key_exists($voice, TTS_VOICES)) {
        throw new InvalidArgumentException('Unknown voice.');
    }

    $tmpRaw = tempnam(sys_get_temp_dir(), 'tts-raw-') . '.wav';
    $tmpResampled = tempnam(sys_get_temp_dir(), 'tts-16k-') . '.wav';

    exec('espeak-ng -v ' . escapeshellarg($voice) . ' -w ' . escapeshellarg($tmpRaw) . ' ' . escapeshellarg($text) . ' 2>&1', $out1, $code1);
    if ($code1 !== 0) {
        @unlink($tmpRaw);
        throw new RuntimeException('espeak-ng failed: ' . implode(' ', $out1));
    }

    exec('sox ' . escapeshellarg($tmpRaw) . ' -r 16000 -c 1 ' . escapeshellarg($tmpResampled) . ' 2>&1', $out2, $code2);
    @unlink($tmpRaw);
    if ($code2 !== 0) {
        @unlink($tmpResampled);
        throw new RuntimeException('sox resample failed: ' . implode(' ', $out2));
    }

    $entry = installLibraryFile($tmpResampled, $name, [
        'source' => 'tts',
        'text'   => $text,
        'voice'  => TTS_VOICES[$voice],
    ]);
    @unlink($tmpResampled);
    return $entry;
}

/**
 * $uploadedTmpPath is expected to already be a valid uploaded-file tmp
 * path (i.e. the caller has done is_uploaded_file() checks) -- this
 * function only handles format conversion and library installation.
 * ffmpeg (not sox -- sox can't decode m4a/mp3, the formats a phone voice
 * memo is actually likely to be in) does the format-agnostic decode.
 */
function addLibraryEntryFromUpload(string $name, string $uploadedTmpPath): array
{
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Name is required.');
    }

    $tmpResampled = tempnam(sys_get_temp_dir(), 'upload-16k-') . '.wav';
    exec('ffmpeg -y -i ' . escapeshellarg($uploadedTmpPath)
        . ' -ar 16000 -ac 1 ' . escapeshellarg($tmpResampled) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        @unlink($tmpResampled);
        throw new RuntimeException('Could not read/convert the uploaded audio file: ' . implode(' ', array_slice($out, -5)));
    }

    $entry = installLibraryFile($tmpResampled, $name, ['source' => 'upload']);
    @unlink($tmpResampled);
    return $entry;
}

function deleteLibraryEntry(string $id): void
{
    $library = loadLibrary();
    $found = false;
    $entries = [];
    foreach ($library['entries'] as $entry) {
        if ($entry['id'] === $id) {
            $found = true;
            continue;
        }
        $entries[] = $entry;
    }
    if (!$found) {
        throw new InvalidArgumentException('No such library entry.');
    }
    $library['entries'] = $entries;
    foreach ($library['active'] as $slot => $activeId) {
        if ($activeId === $id) {
            // The file already copied to the slot's live path (e.g.
            // radiotest_message.wav) is left untouched -- it plays until
            // something else is activated or saved over it. Only the
            // library's own bookkeeping forgets this was its source.
            $library['active'][$slot] = null;
        }
    }
    saveLibraryIndex($library);

    exec('sudo rm -f ' . escapeshellarg(LIBRARY_DIR . '/' . $id . '.wav') . ' 2>&1');
}

function activateLibraryEntry(string $slot, string $id): void
{
    if (!array_key_exists($slot, LIBRARY_SLOTS)) {
        throw new InvalidArgumentException('Unknown slot.');
    }
    $library = loadLibrary();
    $entryExists = false;
    foreach ($library['entries'] as $entry) {
        if ($entry['id'] === $id) {
            $entryExists = true;
            break;
        }
    }
    if (!$entryExists) {
        throw new InvalidArgumentException('No such library entry.');
    }

    $srcPath = LIBRARY_DIR . '/' . $id . '.wav';
    $destPath = LIBRARY_SLOTS[$slot];
    exec('sudo cp ' . escapeshellarg($srcPath) . ' ' . escapeshellarg($destPath) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        throw new RuntimeException('Failed to activate: ' . implode(' ', $out));
    }
    exec('sudo chmod 644 ' . escapeshellarg($destPath) . ' 2>&1');

    $library['active'][$slot] = $id;
    saveLibraryIndex($library);
}
