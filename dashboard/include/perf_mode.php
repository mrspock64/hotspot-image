<?php
/**
 * "Guru mode" vs "Turbo mode" -- toggles the underclock/undervolt/core-cap
 * settings RF.Guru's own hotspot-options ("Temperature Tuning") applies,
 * without running that script (board-agnostic, no per-model check at
 * all, and touches things beyond just these two files -- see the
 * svxlinkuhf_performance_tuning investigation, 2026-09-10). Turbo mode is
 * this project's own name for "off": confirmed live on svxlinkuhf --
 * 4 real cores instead of 2 (maxcpus=2 in cmdline.txt), 1000MHz instead
 * of a hard 700MHz cap, no thermal throttling either way at 49-51°C.
 *
 * Requires a reboot to take effect -- config.txt/cmdline.txt are only
 * read at boot, not live.
 */

define('PERF_CONFIG_TXT', '/boot/firmware/config.txt');
define('PERF_CMDLINE_TXT', '/boot/firmware/cmdline.txt');

// Exactly the lines RF.Guru's own hotspot-options adds/removes for its
// "Temperature Tuning: Yes/No" choice -- matched verbatim so this stays
// a clean toggle against whatever that script (or a sysop by hand) last
// left behind, not a second, slightly-different set of throttle values.
const PERF_GURU_CONFIG_LINES = [
    'hdmi_blanking=1',
    'arm_freq=700',
    'arm_freq_min=500',
    'over_voltage=-6',
    'gpu_freq=150',
    'core_freq=150',
];
const PERF_GURU_CMDLINE_SETTING = 'maxcpus=2';

/**
 * 'guru' if every one of PERF_GURU_CONFIG_LINES is present in config.txt,
 * 'turbo' otherwise -- a partial/mixed state (someone hand-edited only
 * some of them) reads as 'turbo' since "assume throttled" is the wrong
 * default to guess wrong in the other direction.
 */
function getPerfMode(): string
{
    $content = @file_get_contents(PERF_CONFIG_TXT);
    if ($content === false) {
        return 'turbo';
    }
    foreach (PERF_GURU_CONFIG_LINES as $line) {
        if (!preg_match('/^' . preg_quote($line, '/') . '$/m', $content)) {
            return 'turbo';
        }
    }
    return 'guru';
}

/** @param 'guru'|'turbo' $mode */
function setPerfMode(string $mode): void
{
    if (!in_array($mode, ['guru', 'turbo'], true)) {
        throw new InvalidArgumentException("Unknown performance mode: $mode");
    }

    $configContent = @file_get_contents(PERF_CONFIG_TXT);
    $cmdlineContent = @file_get_contents(PERF_CMDLINE_TXT);
    if ($configContent === false || $cmdlineContent === false) {
        throw new RuntimeException('Could not read ' . PERF_CONFIG_TXT . ' or ' . PERF_CMDLINE_TXT . '.');
    }

    // Always leave a way back -- these two files are read at boot, a bad
    // edit here means a node that won't come back up cleanly.
    exec('sudo cp ' . escapeshellarg(PERF_CONFIG_TXT) . ' ' . escapeshellarg(PERF_CONFIG_TXT . '.bak-' . date('Ymd-His')));
    exec('sudo cp ' . escapeshellarg(PERF_CMDLINE_TXT) . ' ' . escapeshellarg(PERF_CMDLINE_TXT . '.bak-' . date('Ymd-His')));

    $configLines = array_values(array_filter(
        preg_split('/\r?\n/', $configContent),
        fn($l) => !in_array(trim($l), PERF_GURU_CONFIG_LINES, true)
    ));
    if ($mode === 'guru') {
        $configLines = array_merge($configLines, PERF_GURU_CONFIG_LINES);
    }
    $newConfig = implode("\n", $configLines);
    if (!str_ends_with($newConfig, "\n")) {
        $newConfig .= "\n";
    }

    $cmdline = trim($cmdlineContent);
    $cmdline = trim(preg_replace('/\bmaxcpus=2\b/', '', $cmdline));
    $cmdline = preg_replace('/\s+/', ' ', $cmdline);
    if ($mode === 'guru') {
        $cmdline .= ' ' . PERF_GURU_CMDLINE_SETTING;
    }

    $tmpConfig = tempnam(sys_get_temp_dir(), 'perf-config-');
    $tmpCmdline = tempnam(sys_get_temp_dir(), 'perf-cmdline-');
    file_put_contents($tmpConfig, $newConfig);
    file_put_contents($tmpCmdline, $cmdline . "\n");

    exec('sudo cp ' . escapeshellarg($tmpConfig) . ' ' . escapeshellarg(PERF_CONFIG_TXT) . ' 2>&1', $o1, $c1);
    exec('sudo cp ' . escapeshellarg($tmpCmdline) . ' ' . escapeshellarg(PERF_CMDLINE_TXT) . ' 2>&1', $o2, $c2);
    unlink($tmpConfig);
    unlink($tmpCmdline);

    if ($c1 !== 0 || $c2 !== 0) {
        throw new RuntimeException('Failed to write config: ' . implode(' ', array_merge($o1, $o2)));
    }
}
