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
 * Write a valid node_info.json matching the schema SvxLink's ReflectorLogic
 * actually expects (verified against a live 1.10.1@26.05.1 node — this is
 * NOT the same field layout as include/functions.php's older createjson(),
 * which used a different, unverified shape).
 *
 * A previous version of this file (and every RF.Guru hotspot we've seen)
 * shipped with a stray trailing comma after the "rx" object that makes the
 * file invalid JSON — silently tolerated by SvxLink itself, but a problem
 * for anything else (e.g. the SvxReflector portal) that parses it strictly.
 * json_encode() can't produce that mistake, so writing through this function
 * fixes it structurally rather than needing a one-off patch after the fact.
 */
function writeNodeInfoJson(
    string $filePath,
    string $nodeLocation,
    string $sysop,
    bool $hidden,
    string $qthName,
    string $lat,
    string $long,
    string $gridsquare,
    float $rxFreq,
    float $txFreq,
    string $txPower
): void {
    $data = [
        'nodeLocation' => $nodeLocation,
        'nodeClass' => 'hotspot',
        'hidden' => $hidden,
        'sysop' => $sysop,
        'qth' => [[
            'name' => $qthName,
            'pos' => [
                'lat' => $lat,
                'long' => $long,
                'loc' => $gridsquare,
            ],
            'rx' => [
                'A' => ['name' => 'Rx1', 'freq' => $rxFreq],
            ],
            'tx' => [
                'A' => ['name' => 'Tx1', 'freq' => $txFreq, 'pwr' => $txPower],
            ],
        ]],
    ];

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Failed to encode node_info.json: ' . json_last_error_msg());
    }
    file_put_contents($filePath, $json . "\n");
}
