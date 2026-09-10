<?php
define('HOTSPOT_SCRIPT', '/usr/sbin/hotspot');

// Every config write here (and every restore in dashboard/backup/lib.php)
// leaves a timestamped .bak-* copy behind before touching the live file --
// on purpose, that's what the Backup page's "Previous versions" list
// shows. But nothing ever removed an old one: a single Setup-page save
// alone calls iniSyncUpdateSection() four times (once per svxlink.conf
// section it touches), so the list only ever grew. Kept per file, oldest
// deleted first (FIFO) -- how many is configurable from the Backup page
// (CONFIG_BACKUP_MAX_KEEP in svxlink.conf's own [Dashboard] section, a
// key SvxLink itself never reads, same pattern as QsoRecorder's custom
// keys), this is just the fallback if that's unset/invalid.
const CONFIG_BACKUP_MAX_KEEP_DEFAULT = 50;

// update.dashboard.sh's own full-directory backups of /var/www/html (see
// dashboard/backup/lib.php's pruneDashboardBackups()) share the same
// "configurable, defaults to 50" idea via this sibling key -- read here
// too so the Backup page settings form has one place to fetch both from.
const DASHBOARD_BACKUP_MAX_KEEP_DEFAULT = 50;

/**
 * /etc/svxlink/svxlink.conf and node_info.json are root-owned (644) on a
 * normally-permissioned RF.Guru node -- www-data can read them but not
 * write, including creating a new file alongside them for a backup. This
 * whole file used to write with plain file_put_contents()/copy()/unlink(),
 * which only ever worked because svxlinkuhf's svxlink.conf happened to be
 * (unusually) 777 www-data-owned already -- every save silently did
 * nothing while still reporting success on a normally-permissioned node.
 * Confirmed live on svxlinkmobile, 2026-09-10 (Backup page's retention
 * settings, but the same bug hit every iniSyncUpdateSection()/
 * writeNodeInfoJson() caller: Setup, Power, QSO Log, Backup, ...).
 *
 * These three helpers route every actual filesystem change through sudo
 * (www-data has passwordless NOPASSWD ALL, same as the hotspot/lek shell
 * users) instead. sudoWriteFile throws on failure so a broken write is a
 * visible error instead of a silent no-op; the backup/prune helpers stay
 * best-effort (a failed backup shouldn't block an otherwise-working save)
 * but at least attempt the operation now instead of being guaranteed to
 * fail via a bare unprivileged call.
 */
function sudoWriteFile(string $filePath, string $content): void
{
    $tmp = tempnam(sys_get_temp_dir(), 'inisync-');
    file_put_contents($tmp, $content);
    exec('sudo cp ' . escapeshellarg($tmp) . ' ' . escapeshellarg($filePath) . ' 2>&1', $out, $code);
    @unlink($tmp);
    if ($code !== 0) {
        throw new RuntimeException("Failed to write $filePath: " . implode(' ', $out));
    }
}

function sudoBackupFile(string $filePath): void
{
    $backupPath = $filePath . '.bak-' . date('Ymd-His');
    exec('sudo cp ' . escapeshellarg($filePath) . ' ' . escapeshellarg($backupPath) . ' 2>&1', $out, $code);
    if ($code === 0) {
        exec('sudo chmod 644 ' . escapeshellarg($backupPath) . ' 2>&1');
    }
}

function sudoDeleteFile(string $filePath): void
{
    exec('sudo rm -f ' . escapeshellarg($filePath) . ' 2>&1');
}

// Unlike sudoBackupFile() (best-effort, a failed backup shouldn't block an
// otherwise-working save), this throws -- used where the copy itself IS
// the operation the caller asked for (e.g. restoring a chosen backup),
// so a silent no-op there would mean "Restore" reporting success while
// actually restoring nothing.
function sudoCopyFile(string $src, string $dst): void
{
    exec('sudo cp ' . escapeshellarg($src) . ' ' . escapeshellarg($dst) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        throw new RuntimeException("Failed to copy $src to $dst: " . implode(' ', $out));
    }
}

function getConfigBackupMaxKeep(): int
{
    $conf = @parse_ini_file('/etc/svxlink/svxlink.conf', true, INI_SCANNER_RAW) ?: [];
    $value = $conf['Dashboard']['CONFIG_BACKUP_MAX_KEEP'] ?? '';
    return (ctype_digit((string)$value) && (int)$value > 0) ? (int)$value : CONFIG_BACKUP_MAX_KEEP_DEFAULT;
}

function getDashboardBackupMaxKeep(): int
{
    $conf = @parse_ini_file('/etc/svxlink/svxlink.conf', true, INI_SCANNER_RAW) ?: [];
    $value = $conf['Dashboard']['DASHBOARD_BACKUP_MAX_KEEP'] ?? '';
    return (ctype_digit((string)$value) && (int)$value > 0) ? (int)$value : DASHBOARD_BACKUP_MAX_KEEP_DEFAULT;
}

// lib/load-monitor/monitor.sh reads this same key directly (bash) to
// decide whether to auto-pause the QSO Recorder under sustained
// overload -- off by default, toggled on the QSO Log page. This getter
// is just for the QSO Log page itself to show the current state.
function getLoadMonitorAutoPause(): bool
{
    $conf = @parse_ini_file('/etc/svxlink/svxlink.conf', true, INI_SCANNER_RAW) ?: [];
    return ($conf['Dashboard']['LOAD_MONITOR_AUTO_PAUSE_QSO'] ?? '0') === '1';
}

const LOAD_MONITOR_TEMP_THRESHOLD_DEFAULT_C = 75;

// Mirrors monitor.sh's own TEMP_THRESHOLD_DEFAULT_C/get_temp_threshold --
// the Power page reads these two for its "High temperature protection"
// section. LOAD_MONITOR_AUTO_STOP_SVXLINK off by default: unlike pausing
// QSO recording, this takes the radio itself offline, so it's opt-in and
// never re-enabled automatically -- same reasoning as the QSO auto-pause,
// just a more severe action reserved for actual thermal danger.
function getLoadMonitorTempThreshold(): int
{
    $conf = @parse_ini_file('/etc/svxlink/svxlink.conf', true, INI_SCANNER_RAW) ?: [];
    $value = $conf['Dashboard']['LOAD_MONITOR_TEMP_THRESHOLD_C'] ?? '';
    return (ctype_digit((string)$value) && (int)$value > 0) ? (int)$value : LOAD_MONITOR_TEMP_THRESHOLD_DEFAULT_C;
}

function getLoadMonitorAutoStopSvxlink(): bool
{
    $conf = @parse_ini_file('/etc/svxlink/svxlink.conf', true, INI_SCANNER_RAW) ?: [];
    return ($conf['Dashboard']['LOAD_MONITOR_AUTO_STOP_SVXLINK'] ?? '0') === '1';
}

function getLoadMonitorAutoAlertTx(): bool
{
    $conf = @parse_ini_file('/etc/svxlink/svxlink.conf', true, INI_SCANNER_RAW) ?: [];
    return ($conf['Dashboard']['LOAD_MONITOR_AUTO_ALERT_TX'] ?? '0') === '1';
}

function pruneOldBackups(string $filePath): void
{
    $maxKeep = getConfigBackupMaxKeep();

    // Only touch backups matching our own "Ymd-His" naming exactly (same
    // pattern listConfigBackups() filters to for display) -- a plain
    // ".bak-*" glob also catches one-off backups other scripts have left
    // over the years with their own naming (".bak-qsorec-...",
    // ".bak-restorefix-...", an older "_HHMMSS" underscore format from
    // before this convention settled), which don't sort chronologically
    // against these and aren't safe to assume are prunable at all.
    $backups = [];
    foreach (glob($filePath . '.bak-*') ?: [] as $backup) {
        if (preg_match('/\.bak-\d{8}-\d{6}$/', $backup)) {
            $backups[] = $backup;
        }
    }
    if (count($backups) <= $maxKeep) {
        return;
    }
    // The matched suffix is a fixed-width "Ymd-His" string, so plain
    // lexicographic sort() is chronological order here.
    sort($backups);
    foreach (array_slice($backups, 0, count($backups) - $maxKeep) as $old) {
        sudoDeleteFile($old);
    }
}

/**
 * Retune the physical SA818 radio module. svxlink.conf/node_info.json have
 * no concept of "operating frequency" at all -- SvxLink only cares about
 * audio/squelch/DTMF. The actual RF frequency is set by a completely
 * separate mechanism: /usr/sbin/hotspot, a root-owned script that shells
 * out to the `sa818` CLI tool over the module's serial port. It's invoked
 * as an ExecStartPre every time svxlink.service starts (see
 * /lib/systemd/system/svxlink.service), which is also how a value written
 * here survives a reboot without any extra step.
 *
 * Only the --frequency token is touched via regex substitution -- --bw,
 * --squelch, --ctcss and --tail (set once at provisioning, not exposed in
 * the Setup form) are preserved exactly as they were. This is the same
 * file that had RF.Guru's hotspot-config tool corrupt it on svxlinkuhf
 * (a `gum input --help` dump landed where the frequency value should have
 * been) -- fixed by hand there; this function is what a Setup-page save
 * should have done instead of requiring that manual SSH fix.
 */
function updateRadioFrequency(float $freq): string
{
    $content = @file_get_contents(HOTSPOT_SCRIPT);
    if ($content === false) {
        throw new RuntimeException(HOTSPOT_SCRIPT . ' not found — this node has no SA818 radio-tuning script to update.');
    }
    if (!preg_match('/sa818\s+--port\s+\S+\s+radio\s+.*--frequency\s+\S+/', $content)) {
        throw new RuntimeException(HOTSPOT_SCRIPT . " doesn't contain a recognizable sa818 radio command — not touching it.");
    }

    $freqStr = number_format($freq, 6, '.', '');
    $newContent = preg_replace(
        '/(sa818\s+--port\s+\S+\s+radio\s+.*--frequency\s+)\S+/',
        '${1}' . $freqStr,
        $content,
        1
    );

    exec('sudo cp ' . escapeshellarg(HOTSPOT_SCRIPT) . ' ' . escapeshellarg(HOTSPOT_SCRIPT . '.bak-' . date('Ymd-His')) . ' 2>&1');

    $tmp = tempnam(sys_get_temp_dir(), 'hotspot-script-');
    file_put_contents($tmp, $newContent);
    exec('sudo cp ' . escapeshellarg($tmp) . ' ' . escapeshellarg(HOTSPOT_SCRIPT) . ' 2>&1', $writeOut, $writeCode);
    exec('sudo chmod 755 ' . escapeshellarg(HOTSPOT_SCRIPT) . ' 2>&1');
    unlink($tmp);
    if ($writeCode !== 0) {
        throw new RuntimeException('Failed to update ' . HOTSPOT_SCRIPT . ': ' . implode(' ', $writeOut));
    }

    // Apply immediately -- running the script directly retunes the module
    // without needing a full svxlink service restart.
    exec('sudo ' . escapeshellarg(HOTSPOT_SCRIPT) . ' 2>&1', $runOut, $runCode);

    return $runCode === 0
        ? "Radio retuned to {$freqStr} MHz."
        : "Frequency saved to " . HOTSPOT_SCRIPT . ", but retuning failed just now (" . implode(' ', $runOut) . ") — it will still apply on the next svxlink restart.";
}

/**
 * Small helper for editing specific keys inside one [section] of an INI-style
 * config file (svxlink.conf) without disturbing anything else in the file —
 * comments, other sections, hardware-specific keys (audio device, GPIO PTT
 * lines, CTCSS detection frequency list, ...) are left exactly as they are.
 *
 * Deliberately NOT using parse_ini_file()+re-serialize for writes: svxlink.conf
 * has structure (comments, a [Macros] section, section ordering) that a naive
 * round-trip through PHP's ini array representation would not faithfully
 * reproduce, and this file is far too important to risk mangling.
 */

/**
 * Update (or insert) a set of key=value pairs inside one section of an INI
 * file, leaving every other line untouched. Commented-out lines are never
 * matched/uncommented; a new active line is inserted instead.
 *
 * @param string $filePath   Path to the INI file.
 * @param string $section    Section name, without brackets (e.g. "SimplexLogic").
 * @param array  $keyValues  key => value pairs. Values are written verbatim —
 *                            the caller is responsible for quoting if needed.
 */
function iniSyncUpdateSection(string $filePath, string $section, array $keyValues): void
{
    $lines = @file($filePath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException("Could not read $filePath");
    }

    // This edits a live radio's config -- always leave a way back.
    sudoBackupFile($filePath);
    pruneOldBackups($filePath);

    $sectionHeader = "[$section]";
    $sectionStart = null;
    $sectionEnd = null;

    foreach ($lines as $i => $line) {
        if ($sectionStart === null && trim($line) === $sectionHeader) {
            $sectionStart = $i;
            continue;
        }
        if ($sectionStart !== null && preg_match('/^\[.+\]\s*$/', trim($line))) {
            $sectionEnd = $i;
            break;
        }
    }

    if ($sectionStart === null) {
        // Section doesn't exist yet — append a new one at the end of the file.
        if (count($lines) > 0 && trim(end($lines)) !== '') {
            $lines[] = '';
        }
        $lines[] = $sectionHeader;
        foreach ($keyValues as $k => $v) {
            $lines[] = "$k=$v";
        }
        sudoWriteFile($filePath, implode("\n", $lines) . "\n");
        return;
    }

    if ($sectionEnd === null) {
        $sectionEnd = count($lines);
    }

    $remaining = $keyValues;
    for ($i = $sectionStart + 1; $i < $sectionEnd; $i++) {
        $trimmed = trim($lines[$i]);
        if ($trimmed === '' || $trimmed[0] === '#') {
            continue;
        }
        foreach ($remaining as $k => $v) {
            if (preg_match('/^' . preg_quote($k, '/') . '\s*=/', $trimmed)) {
                $lines[$i] = "$k=$v";
                unset($remaining[$k]);
                break;
            }
        }
    }

    if (!empty($remaining)) {
        $insertLines = [];
        foreach ($remaining as $k => $v) {
            $insertLines[] = "$k=$v";
        }
        array_splice($lines, $sectionEnd, 0, $insertLines);
    }

    sudoWriteFile($filePath, implode("\n", $lines) . "\n");
}

/**
 * Parse a "88.5:0,82.5:240" CTCSS_TO_TG-style string (same format used in
 * svxlink.conf) into the {tone: talkgroup} map node_info.json's
 * toneToTalkgroup field expects. svxlink.conf stays the one place this
 * mapping is actually entered -- node_info.json just mirrors it, since
 * that's the only copy the SvxReflector portal ever sees over the wire.
 */
function parseToneToTalkgroup(string $ctcssToTg): array
{
    $map = [];
    foreach (explode(',', $ctcssToTg) as $pair) {
        $pair = trim($pair);
        if ($pair === '' || strpos($pair, ':') === false) {
            continue;
        }
        [$tone, $tg] = explode(':', $pair, 2);
        $map[trim($tone)] = (int)trim($tg);
    }
    return $map;
}

/**
 * Write a valid node_info.json matching the schema SvxLink's ReflectorLogic
 * actually expects (verified against a live 1.10.1@26.05.1 node), extended
 * with the fuller field set include/functions.php's older createjson() was
 * built for (nodeClass, toneToTalkgroup, antenna info) -- that function's
 * own field layout was otherwise unverified and is not used directly.
 *
 * A previous version of this file (and every RF.Guru hotspot we've seen)
 * shipped with a stray trailing comma after the "rx" object that makes the
 * file invalid JSON — silently tolerated by SvxLink itself, but a problem
 * for anything else (e.g. the SvxReflector portal) that parses it strictly.
 * json_encode() can't produce that mistake, so writing through this function
 * fixes it structurally rather than needing a one-off patch after the fact.
 *
 * @param array $opts {
 *   nodeLocation, sysop, hidden, qthName, lat, long, gridsquare: as before.
 *   nodeClass: e.g. "hotspot" — preserved from the existing file by the
 *     caller if the user didn't change it, never silently overwritten.
 *   ctcssToTg: "88.5:0,82.5:240" string, mirrored into toneToTalkgroup.
 *   rxFreq, txFreq, txPower: as before.
 *   rxSqlType: e.g. "CTCSS".
 *   antComment, antHeight, antDir: shared antenna description (rx and tx
 *     use the same physical antenna on a simplex hotspot, matching how
 *     createjson() treated them).
 *   antGain, antType: TX-only fields (createjson() only collected these
 *     for tx).
 * }
 */
function writeNodeInfoJson(string $filePath, array $opts): void
{
    $ant = array_filter([
        'comment' => $opts['antComment'] ?? '',
        'height' => $opts['antHeight'] ?? '',
        'dir' => $opts['antDir'] ?? '',
    ], fn($v) => $v !== '');

    $rx = ['name' => 'Rx1', 'freq' => $opts['rxFreq']];
    if (!empty($opts['rxSqlType'])) {
        $rx['sqlType'] = $opts['rxSqlType'];
    }
    if (!empty($ant)) {
        $rx['ant'] = $ant;
    }

    $tx = ['name' => 'Tx1', 'freq' => $opts['txFreq'], 'pwr' => $opts['txPower']];
    $txAnt = $ant;
    if (!empty($opts['antGain'])) {
        $txAnt['gain'] = $opts['antGain'];
    }
    if (!empty($opts['antType'])) {
        $txAnt['Antenna_type'] = $opts['antType'];
    }
    if (!empty($txAnt)) {
        $tx['ant'] = $txAnt;
    }

    // Start from whatever is already on disk rather than an empty array, so
    // any key this function doesn't know about (a field a future RF.Guru
    // image or SvxLink version adds, or one this form simply hasn't grown a
    // field for yet) survives a save instead of silently vanishing. This is
    // exactly how nodeLocation/qthName/lat/long/gridsquare/txPower were lost
    // at some point before this comment was written -- an earlier version of
    // the Setup page didn't have fields for them yet, so saving with the old
    // code wiped them from a full from-scratch rebuild of this file.
    $data = [];
    if (is_readable($filePath)) {
        $raw = file_get_contents($filePath);
        $decoded = json_decode($raw, true);
        if ($decoded === null) {
            $decoded = json_decode(preg_replace('/,(\s*[}\]])/', '$1', $raw), true);
        }
        $data = $decoded ?: [];
    }

    $data['nodeLocation'] = $opts['nodeLocation'];
    $data['nodeClass'] = $opts['nodeClass'] !== '' ? $opts['nodeClass'] : 'hotspot';
    $data['hidden'] = $opts['hidden'];
    $data['sysop'] = $opts['sysop'];
    $data['toneToTalkgroup'] = parseToneToTalkgroup($opts['ctcssToTg'] ?? '');
    $data['qth'][0] = array_merge($data['qth'][0] ?? [], [
        'name' => $opts['qthName'],
        'pos' => [
            'lat' => $opts['lat'],
            'long' => $opts['long'],
            'loc' => $opts['gridsquare'],
        ],
        'rx' => ['A' => $rx],
        'tx' => ['A' => $tx],
    ]);

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Failed to encode node_info.json: ' . json_last_error_msg());
    }

    if (is_readable($filePath)) {
        sudoBackupFile($filePath);
        pruneOldBackups($filePath);
    }
    sudoWriteFile($filePath, $json . "\n");
}
