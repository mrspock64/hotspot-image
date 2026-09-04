<?php
/**
 * Shared helpers for the backup/restore page. Bundles exactly what's needed
 * to recreate this node's identity after a reinstall: the reflector
 * certificate (which otherwise means waiting on the sysop to re-sign), the
 * two config files the Setup page owns, and any of the OPTIONAL_BACKUP_FILES
 * that actually exist. Nothing more — no attempt to back up the whole
 * filesystem or every dashboard setting.
 */

define('PKI_DIR', '/var/lib/svxlink/pki');
define('SVX_CONF_FILE', '/etc/svxlink/svxlink.conf');
define('NODE_INFO_FILE_PATH', '/etc/svxlink/node_info.json');

/**
 * Config files that only exist once someone has actually saved the page
 * that owns them at least once (before that, the dashboard falls back to
 * defaults baked into the code itself, not node-specific state worth
 * backing up) -- included if present, skipped silently otherwise. Keyed
 * by the bare filename the zip stores them under.
 */
const OPTIONAL_BACKUP_FILES = [
    'dashboard_buttons.json' => '/etc/svxlink/dashboard_buttons.json',
    'dashboard_tgdb.json' => '/etc/svxlink/dashboard_tgdb.json',
];

/**
 * Build the backup zip into a temp file and return its path. Caller is
 * responsible for streaming it out and deleting it afterwards -- this never
 * writes into a web-accessible directory, unlike the tar-in-webroot approach
 * documented for the stock RF.Guru image (the private key would sit exposed
 * there until manually deleted).
 *
 * .zip, not .tgz: macOS warns/complains about downloaded .tgz files (Archive
 * Utility + Gatekeeper), .zip has no such friction and opens natively.
 */
function buildBackupZip(): string
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'hs-backup-') . '.zip';
    $stagingDir = sys_get_temp_dir() . '/hs-backup-' . uniqid();

    // pki/ and the config files live in different directories -- stage
    // everything into one tree first, then zip that. The private key is
    // 0600, owned by svxlink, so both the staging copy AND the zip step
    // itself run via sudo (cp -a preserves that 0600 mode into the staging
    // dir, which www-data couldn't read back to zip up on its own). Only
    // the final chmod hands the finished archive back to www-data.
    $presentOptional = array_filter(OPTIONAL_BACKUP_FILES, 'is_file');

    $cmds = [
        'sudo mkdir -p ' . escapeshellarg($stagingDir),
        'sudo cp -a ' . escapeshellarg(PKI_DIR) . ' ' . escapeshellarg($stagingDir) . '/pki',
        'sudo cp ' . escapeshellarg(SVX_CONF_FILE) . ' ' . escapeshellarg(NODE_INFO_FILE_PATH) . ' ' . escapeshellarg($stagingDir) . '/',
    ];
    foreach ($presentOptional as $path) {
        $cmds[] = 'sudo cp ' . escapeshellarg($path) . ' ' . escapeshellarg($stagingDir) . '/';
    }
    $zipMembers = implode(' ', array_merge(['pki', 'svxlink.conf', 'node_info.json'], array_keys($presentOptional)));
    $cmds[] = '(cd ' . escapeshellarg($stagingDir) . ' && sudo zip -rq ' . escapeshellarg($tmpFile) . ' ' . $zipMembers . ')';
    $cmds[] = 'sudo chmod 644 ' . escapeshellarg($tmpFile);
    $cmds[] = 'sudo rm -rf ' . escapeshellarg($stagingDir);

    exec(implode(' && ', $cmds) . ' 2>&1', $output, $exitCode);

    if ($exitCode !== 0 || !is_readable($tmpFile)) {
        exec('sudo rm -rf ' . escapeshellarg($stagingDir) . ' ' . escapeshellarg($tmpFile));
        throw new RuntimeException('zip failed: ' . implode("\n", $output));
    }

    return $tmpFile;
}

/**
 * Validate an uploaded zip actually contains what we expect before
 * touching anything, then restore pki/ + the config files from it.
 * Existing files are backed up with a timestamp first, same as the Setup
 * page's own writes.
 *
 * @return string[] Log lines describing what happened, for display.
 */
function restoreFromZip(string $zipPath): array
{
    $log = [];

    // -Z1: zipinfo mode, one bare filename per line -- no header/footer to
    // parse, unlike `unzip -l`.
    $listing = [];
    exec('unzip -Z1 ' . escapeshellarg($zipPath) . ' 2>&1', $listing, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException("Not a valid zip file:\n" . implode("\n", $listing));
    }

    $hasPki = false;
    foreach ($listing as $entry) {
        if (str_starts_with($entry, 'pki/')) { $hasPki = true; break; }
    }
    $hasConf = in_array('svxlink.conf', $listing, true);
    $hasNodeInfo = in_array('node_info.json', $listing, true);
    $presentOptionalInZip = array_intersect_key(OPTIONAL_BACKUP_FILES, array_flip($listing));

    if (!$hasPki && !$hasConf && !$hasNodeInfo && !$presentOptionalInZip) {
        throw new RuntimeException('This does not look like a hotspot-image backup (found none of pki/, svxlink.conf, node_info.json, or the optional dashboard configs in it).');
    }

    $stagingDir = sys_get_temp_dir() . '/hs-restore-' . uniqid();
    mkdir($stagingDir, 0700, true);
    exec('unzip -q ' . escapeshellarg($zipPath) . ' -d ' . escapeshellarg($stagingDir) . ' 2>&1', $extractOutput, $extractCode);
    if ($extractCode !== 0) {
        throw new RuntimeException("Extraction failed:\n" . implode("\n", $extractOutput));
    }

    if ($hasPki && is_dir("$stagingDir/pki")) {
        exec('sudo cp -a ' . escapeshellarg("$stagingDir/pki/.") . ' ' . escapeshellarg(PKI_DIR) . '/ 2>&1', $o1, $c1);
        exec('sudo chown -R svxlink:svxlink ' . escapeshellarg(PKI_DIR) . ' 2>&1', $o2, $c2);
        $log[] = ($c1 === 0 && $c2 === 0) ? 'Restored pki/ (certificate + key).' : 'FAILED to restore pki/: ' . implode(' ', array_merge($o1, $o2));
    }

    if ($hasConf && is_file("$stagingDir/svxlink.conf")) {
        @copy(SVX_CONF_FILE, SVX_CONF_FILE . '.bak-' . date('Ymd-His'));
        exec('sudo cp ' . escapeshellarg("$stagingDir/svxlink.conf") . ' ' . escapeshellarg(SVX_CONF_FILE) . ' 2>&1', $o3, $c3);
        $log[] = $c3 === 0 ? 'Restored svxlink.conf (previous version backed up).' : 'FAILED to restore svxlink.conf: ' . implode(' ', $o3);
    }

    if ($hasNodeInfo && is_file("$stagingDir/node_info.json")) {
        if (json_decode(file_get_contents("$stagingDir/node_info.json")) === null) {
            $log[] = 'Skipped node_info.json: the file in the backup is not valid JSON.';
        } else {
            @copy(NODE_INFO_FILE_PATH, NODE_INFO_FILE_PATH . '.bak-' . date('Ymd-His'));
            exec('sudo cp ' . escapeshellarg("$stagingDir/node_info.json") . ' ' . escapeshellarg(NODE_INFO_FILE_PATH) . ' 2>&1', $o4, $c4);
            $log[] = $c4 === 0 ? 'Restored node_info.json (previous version backed up).' : 'FAILED to restore node_info.json: ' . implode(' ', $o4);
        }
    }

    foreach ($presentOptionalInZip as $filename => $targetPath) {
        $stagedPath = "$stagingDir/$filename";
        if (!is_file($stagedPath)) {
            continue;
        }
        if (json_decode(file_get_contents($stagedPath)) === null) {
            $log[] = "Skipped $filename: the file in the backup is not valid JSON.";
            continue;
        }
        exec('sudo cp ' . escapeshellarg($targetPath) . ' ' . escapeshellarg($targetPath . '.bak-' . date('Ymd-His')) . ' 2>&1');
        exec('sudo cp ' . escapeshellarg($stagedPath) . ' ' . escapeshellarg($targetPath) . ' 2>&1', $o5, $c5);
        exec('sudo chmod 644 ' . escapeshellarg($targetPath) . ' 2>&1');
        $log[] = $c5 === 0 ? "Restored $filename (previous version backed up)." : "FAILED to restore $filename: " . implode(' ', $o5);
    }

    exec('rm -rf ' . escapeshellarg($stagingDir));

    return $log;
}

/**
 * List the timestamped .bak-YYYYmmdd-HHMMSS copies that Setup and Restore
 * already leave behind before every write (see iniSyncUpdateSection() and
 * writeNodeInfoJson() in include/inisync.php, and the restore paths above).
 * Nothing new is being introduced here -- this just surfaces what already
 * gets saved automatically, so reverting one doesn't require SSH.
 *
 * @return array Rows of ['file' => base config path, 'backup' => full
 *   backup path, 'timestamp' => DateTime, 'size' => bytes], newest first.
 */
function listConfigBackups(): array
{
    $rows = [];
    foreach ([SVX_CONF_FILE, NODE_INFO_FILE_PATH] as $file) {
        foreach (glob($file . '.bak-*') ?: [] as $backup) {
            if (!preg_match('/\.bak-(\d{8}-\d{6})$/', $backup, $m)) {
                continue;
            }
            $ts = DateTime::createFromFormat('Ymd-His', $m[1]);
            if ($ts === false) {
                continue;
            }
            $rows[] = [
                'file' => $file,
                'backup' => $backup,
                'timestamp' => $ts,
                'size' => filesize($backup),
            ];
        }
    }
    usort($rows, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
    return $rows;
}

/**
 * Restore one config file from one of its own .bak-* copies. The backup
 * path is validated against the exact naming pattern our own code produces
 * (base path + .bak- + timestamp) rather than trusted as free-form input,
 * since it arrives from a form field.
 */
function restoreConfigBackup(string $backupPath): string
{
    $allowed = [SVX_CONF_FILE, NODE_INFO_FILE_PATH];
    $target = null;
    foreach ($allowed as $file) {
        if (preg_match('/^' . preg_quote($file, '/') . '\.bak-\d{8}-\d{6}$/', $backupPath)) {
            $target = $file;
            break;
        }
    }
    if ($target === null || !is_file($backupPath)) {
        throw new RuntimeException('Unknown or invalid backup file.');
    }
    if ($target === NODE_INFO_FILE_PATH && json_decode(file_get_contents($backupPath)) === null) {
        throw new RuntimeException('That backup is not valid JSON — refusing to restore it.');
    }

    // Back up whatever's live right now before overwriting it, same as
    // every other write path here — restoring a backup is itself an edit.
    @copy($target, $target . '.bak-' . date('Ymd-His'));
    if (!copy($backupPath, $target)) {
        throw new RuntimeException("Failed to copy $backupPath over $target.");
    }

    return basename($target) . ' restored from ' . basename($backupPath) . '.';
}
