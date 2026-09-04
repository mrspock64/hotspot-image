<?php
/**
 * Shared helpers for the backup/restore page. Bundles exactly what's needed
 * to recreate this node's identity after a reinstall: the reflector
 * certificate (which otherwise means waiting on the sysop to re-sign) and
 * the two config files the Setup page owns. Nothing more — no attempt to
 * back up the whole filesystem or every dashboard setting.
 */

define('PKI_DIR', '/var/lib/svxlink/pki');
define('SVX_CONF_FILE', '/etc/svxlink/svxlink.conf');
define('NODE_INFO_FILE_PATH', '/etc/svxlink/node_info.json');

/**
 * Build the backup tarball into a temp file and return its path. Caller is
 * responsible for streaming it out and deleting it afterwards -- this never
 * writes into a web-accessible directory, unlike the tar-in-webroot approach
 * documented for the stock RF.Guru image (the private key would sit exposed
 * there until manually deleted).
 */
function buildBackupTarball(): string
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'hs-backup-') . '.tgz';

    // The private key is 0600, owned by svxlink -- the webserver user can't
    // read it directly, matching this dashboard's existing (accepted)
    // passwordless-sudo trust model for privileged actions.
    $cmd = sprintf(
        'sudo tar czf %s -C %s pki -C %s svxlink.conf node_info.json 2>&1 && sudo chmod 644 %s',
        escapeshellarg($tmpFile),
        escapeshellarg(dirname(PKI_DIR)),
        escapeshellarg(dirname(SVX_CONF_FILE)),
        escapeshellarg($tmpFile)
    );
    exec($cmd, $output, $exitCode);

    if ($exitCode !== 0 || !is_readable($tmpFile)) {
        exec('sudo rm -f ' . escapeshellarg($tmpFile));
        throw new RuntimeException('tar failed: ' . implode("\n", $output));
    }

    return $tmpFile;
}

/**
 * Validate an uploaded tarball actually contains what we expect before
 * touching anything, then restore pki/ + the two config files from it.
 * Existing files are backed up with a timestamp first, same as the Setup
 * page's own writes.
 *
 * @return string[] Log lines describing what happened, for display.
 */
function restoreFromTarball(string $tarballPath): array
{
    $log = [];

    $listing = [];
    exec('tar tzf ' . escapeshellarg($tarballPath) . ' 2>&1', $listing, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException("Not a valid tar.gz file:\n" . implode("\n", $listing));
    }

    $hasPki = false;
    $hasConf = false;
    $hasNodeInfo = false;
    foreach ($listing as $entry) {
        if (str_starts_with($entry, 'pki/')) $hasPki = true;
        if ($entry === 'svxlink.conf') $hasConf = true;
        if ($entry === 'node_info.json') $hasNodeInfo = true;
    }
    if (!$hasPki && !$hasConf && !$hasNodeInfo) {
        throw new RuntimeException('This does not look like a hotspot-image backup (found none of pki/, svxlink.conf, node_info.json in it).');
    }

    $stagingDir = sys_get_temp_dir() . '/hs-restore-' . uniqid();
    mkdir($stagingDir, 0700, true);
    exec('tar xzf ' . escapeshellarg($tarballPath) . ' -C ' . escapeshellarg($stagingDir) . ' 2>&1', $extractOutput, $extractCode);
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

    exec('rm -rf ' . escapeshellarg($stagingDir));

    return $log;
}
