<?php
/**
 * Toggle for hotspot-bluetooth's characteristic security mode. RF.Guru's
 * own BLE.md documents this exact approach for a mobile/public node:
 * "switch the characteristic flags in hotspot-bluetooth to
 * encrypt-authenticated-write and bond the phone" -- this patches the
 * vendor script's two write-characteristic flag lists in place, the same
 * change their docs describe making by hand. Round-trips byte-identical
 * to the original file when toggled back off (verified before shipping).
 *
 * Deliberately not tracked in any state file of ours -- the installed
 * script IS the state, read back by checking which flag list is present.
 * Caveat: re-running install-bluetooth.sh re-downloads hotspot-bluetooth
 * fresh from GitHub and silently resets this back to open/unbonded --
 * there's no way around that short of maintaining our own fork of it.
 */

define('HOTSPOT_BLUETOOTH_BIN', '/usr/sbin/hotspot-bluetooth');
define('BLE_OPEN_FLAGS', '["write", "write-without-response"]');
define('BLE_BONDED_FLAGS', '["encrypt-authenticated-write"]');

function bleInstalled(): bool
{
    return is_file(HOTSPOT_BLUETOOTH_BIN);
}

function bleBondedModeEnabled(): bool
{
    $content = @file_get_contents(HOTSPOT_BLUETOOTH_BIN);
    return $content !== false && str_contains($content, BLE_BONDED_FLAGS);
}

/** Patches both write characteristics' flags in place and, if the service is currently running, restarts it to pick up the change. */
function setBleBondedMode(bool $enabled): void
{
    if (!bleInstalled()) {
        throw new RuntimeException("Bluetooth isn't installed on this node.");
    }
    if ($enabled === bleBondedModeEnabled()) {
        return;
    }
    [$fromPattern, $to] = $enabled
        ? ['\["write", "write-without-response"\]', BLE_BONDED_FLAGS]
        : ['\["encrypt-authenticated-write"\]', BLE_OPEN_FLAGS];

    $sedExpr = 's/' . $fromPattern . '/' . str_replace('/', '\/', $to) . '/g';
    exec('sudo sed -i -E ' . escapeshellarg($sedExpr) . ' ' . escapeshellarg(HOTSPOT_BLUETOOTH_BIN) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        throw new RuntimeException('Failed to update Bluetooth security mode: ' . implode(' ', $out));
    }

    // Only restart if it's actually running now -- if it's off, the next
    // "Turn on" on the Bluetooth page picks up the new file with no extra
    // step needed.
    exec('sudo systemctl try-restart hotspot-bluetooth > /dev/null 2>&1 &');
}
