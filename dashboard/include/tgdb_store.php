<?php
/**
 * The Talk Groups table (include/tg.php) shows a name next to each TG
 * number, but that name list used to be a hardcoded PHP array
 * (include/tgdb.php) shipped with the dashboard code itself -- generic
 * placeholder names (Idle, 4m Repeaters, Talkgroup 0..5) never customized
 * for whatever talkgroups this node's own reflector network actually
 * uses. This stores it as editable, node-specific JSON instead, same
 * pattern as buttons_store.php.
 */
define('TGDB_CONFIG_FILE', '/etc/svxlink/dashboard_tgdb.json');

/** @return array<string,string> TG number => name, in whatever order they're stored */
function loadTgDb(): array
{
    if (file_exists(TGDB_CONFIG_FILE)) {
        $json = @file_get_contents(TGDB_CONFIG_FILE);
        $decoded = $json !== false ? json_decode($json, true) : null;
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    // Fall back to the old hardcoded list so existing installs keep
    // showing their current names until someone actually edits them.
    $legacyFile = __DIR__ . '/tgdb.php';
    if (file_exists($legacyFile)) {
        include $legacyFile; // defines $tgdb_array
        if (isset($tgdb_array) && is_array($tgdb_array)) {
            return $tgdb_array;
        }
    }
    return [];
}

/**
 * The Setup page's "Monitored talkgroups" field (MONITOR_TGS in
 * [ReflectorLogic]) is the reflector-side list of TGs this node listens to
 * even when not actively switched to one -- exactly the TG numbers worth
 * having a name for. Used by the "Import from monitored talkgroups" button
 * on the TG Names page so numbers don't have to be retyped by hand.
 *
 * @return list<string> TG numbers, "+" priority markers stripped
 */
function loadMonitoredTgNumbers(): array
{
    // Priority markers are trailing plus signs (svxlink.conf(5): "112++"),
    // so this must rtrim, not ltrim -- ltrim was a no-op here and would
    // silently drop any prioritized entry from the returned list, since
    // e.g. "2403+" isn't all-digit and ctype_digit() would reject it.
    $conf = @parse_ini_file('/etc/svxlink/svxlink.conf', true, INI_SCANNER_RAW) ?: [];
    $raw = $conf['ReflectorLogic']['MONITOR_TGS'] ?? '';
    $numbers = [];
    foreach (explode(',', $raw) as $tg) {
        $tg = rtrim(trim($tg), '+');
        if ($tg !== '' && ctype_digit($tg)) {
            $numbers[] = $tg;
        }
    }
    return $numbers;
}

// Same source as loadMonitoredTgNumbers(), but keyed by TG number with its
// priority level: 0 = none, 1 = "+", 2 = "++", and so on.
function loadMonitoredTgPriorities(): array
{
    $conf = @parse_ini_file('/etc/svxlink/svxlink.conf', true, INI_SCANNER_RAW) ?: [];
    $raw = $conf['ReflectorLogic']['MONITOR_TGS'] ?? '';
    $priorities = [];
    foreach (explode(',', $raw) as $entry) {
        $entry = trim($entry);
        $tg = rtrim($entry, '+');
        if ($tg !== '' && ctype_digit($tg)) {
            $priorities[$tg] = strlen($entry) - strlen($tg);
        }
    }
    return $priorities;
}

function saveTgDb(array $tgdb): void
{
    uksort($tgdb, fn($a, $b) => (int)$a <=> (int)$b);
    $json = json_encode($tgdb, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (file_exists(TGDB_CONFIG_FILE)) {
        exec('sudo cp ' . escapeshellarg(TGDB_CONFIG_FILE) . ' '
            . escapeshellarg(TGDB_CONFIG_FILE . '.bak-' . date('Ymd-His')) . ' 2>&1');
    }

    $tmp = tempnam(sys_get_temp_dir(), 'dashboard-tgdb-');
    file_put_contents($tmp, $json);
    exec('sudo cp ' . escapeshellarg($tmp) . ' ' . escapeshellarg(TGDB_CONFIG_FILE) . ' 2>&1', $out, $code);
    exec('sudo chmod 644 ' . escapeshellarg(TGDB_CONFIG_FILE) . ' 2>&1');
    unlink($tmp);

    if ($code !== 0) {
        throw new RuntimeException('Failed to save ' . TGDB_CONFIG_FILE . ': ' . implode(' ', $out));
    }
}
