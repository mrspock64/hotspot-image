<?php
/**
 * The front-page TG/DTMF buttons used to only be editable by SSH'ing in and
 * hand-editing KEY1..KEY10 constants in config.inc.php. This stores the
 * button list as JSON instead, so the Buttons admin page can add/edit/
 * remove/reorder them without touching PHP source.
 *
 * /etc/svxlink survives a dashboard reinstall/update (unlike anything under
 * the webroot), matching where node_info.json and svxlink.conf already live.
 */
define('BUTTONS_CONFIG_FILE', '/etc/svxlink/dashboard_buttons.json');

const BUTTON_COLORS = ['green', 'blue', 'red', 'orange', 'purple'];

/**
 * Falls back to the KEY1..KEY10 constants from config.inc.php (already
 * loaded via config.php by every page that calls this) if no JSON config
 * has been saved yet, so existing installs keep their current buttons
 * until someone actually edits them.
 */
function loadButtons(): array
{
    if (file_exists(BUTTONS_CONFIG_FILE)) {
        $json = @file_get_contents(BUTTONS_CONFIG_FILE);
        $decoded = $json !== false ? json_decode($json, true) : null;
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    $buttons = [];
    for ($i = 1; $i <= 10; $i++) {
        $key = constant("KEY{$i}");
        if (!$key || trim($key[0]) === '') {
            continue;
        }
        $buttons[] = [
            'label' => trim($key[0]),
            'dtmf' => $key[1],
            'color' => in_array($key[2], BUTTON_COLORS, true) ? $key[2] : 'green',
        ];
    }
    return $buttons;
}

function saveButtons(array $buttons): void
{
    $json = json_encode(array_values($buttons), JSON_PRETTY_PRINT);

    if (file_exists(BUTTONS_CONFIG_FILE)) {
        exec('sudo cp ' . escapeshellarg(BUTTONS_CONFIG_FILE) . ' '
            . escapeshellarg(BUTTONS_CONFIG_FILE . '.bak-' . date('Ymd-His')) . ' 2>&1');
    }

    $tmp = tempnam(sys_get_temp_dir(), 'dashboard-buttons-');
    file_put_contents($tmp, $json);
    exec('sudo cp ' . escapeshellarg($tmp) . ' ' . escapeshellarg(BUTTONS_CONFIG_FILE) . ' 2>&1', $out, $code);
    exec('sudo chmod 644 ' . escapeshellarg(BUTTONS_CONFIG_FILE) . ' 2>&1');
    unlink($tmp);

    if ($code !== 0) {
        throw new RuntimeException('Failed to save ' . BUTTONS_CONFIG_FILE . ': ' . implode(' ', $out));
    }
}
