<?php
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
    @copy($filePath, $filePath . '.bak-' . date('Ymd-His'));

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
        file_put_contents($filePath, implode("\n", $lines) . "\n");
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

    file_put_contents($filePath, implode("\n", $lines) . "\n");
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

    $data = [
        'nodeLocation' => $opts['nodeLocation'],
        'nodeClass' => $opts['nodeClass'] !== '' ? $opts['nodeClass'] : 'hotspot',
        'hidden' => $opts['hidden'],
        'sysop' => $opts['sysop'],
        'toneToTalkgroup' => parseToneToTalkgroup($opts['ctcssToTg'] ?? ''),
        'qth' => [[
            'name' => $opts['qthName'],
            'pos' => [
                'lat' => $opts['lat'],
                'long' => $opts['long'],
                'loc' => $opts['gridsquare'],
            ],
            'rx' => ['A' => $rx],
            'tx' => ['A' => $tx],
        ]],
    ];

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Failed to encode node_info.json: ' . json_last_error_msg());
    }

    if (is_readable($filePath)) {
        @copy($filePath, $filePath . '.bak-' . date('Ymd-His'));
    }
    file_put_contents($filePath, $json . "\n");
}
