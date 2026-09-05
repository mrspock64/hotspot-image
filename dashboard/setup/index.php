<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>Node Setup</title>
    <link href="/css/css.php" type="text/css" rel="stylesheet" />
    <link href="/css/modern.css" type="text/css" rel="stylesheet" />
  </head>
  <body style="background: var(--mx-bg);">
<?php include_once __DIR__ . '/../include/site_header.php'; ?>
<?php

require_once __DIR__ . '/../include/inisync.php';

define('SVX_CONF', '/etc/svxlink/svxlink.conf');
define('NODE_INFO', '/etc/svxlink/node_info.json');

function readCurrent(): array
{
    $conf = @parse_ini_file(SVX_CONF, true, INI_SCANNER_RAW) ?: [];
    $nodeInfo = [];
    if (is_readable(NODE_INFO)) {
        $raw = file_get_contents(NODE_INFO);
        $decoded = json_decode($raw, true);
        // Tolerate the trailing-comma-broken JSON every RF.Guru node ships
        // with until this page rewrites it once — don't blow up reading it.
        if ($decoded === null) {
            $decoded = json_decode(preg_replace('/,(\s*[}\]])/', '$1', $raw), true);
        }
        $nodeInfo = $decoded ?: [];
    }
    $qth = $nodeInfo['qth'][0] ?? [];

    return [
        'callsign'     => trim($conf['ReflectorLogic']['CALLSIGN'] ?? '', '" '),
        'domain'       => $conf['ReflectorLogic']['DNS_DOMAIN'] ?? 'sm.svxlink.org',
        'cert_email'   => trim($conf['ReflectorLogic']['CERT_EMAIL'] ?? '', '" '),
        'monitor_tgs'  => $conf['ReflectorLogic']['MONITOR_TGS'] ?? '',
        'ctcss_to_tg'  => $conf['SimplexLogic']['CTCSS_TO_TG'] ?? '',
        'short_ident'  => $conf['SimplexLogic']['SHORT_IDENT_INTERVAL'] ?? '15',
        'long_ident'   => $conf['SimplexLogic']['LONG_IDENT_INTERVAL'] ?? '60',
        'location'     => $nodeInfo['nodeLocation'] ?? '',
        'hidden'       => (bool)($nodeInfo['hidden'] ?? false),
        'sysop'        => $nodeInfo['sysop'] ?? '',
        'qth_name'     => $qth['name'] ?? '',
        'lat'          => $qth['pos']['lat'] ?? '',
        'long'         => $qth['pos']['long'] ?? '',
        'gridsquare'   => $qth['pos']['loc'] ?? '',
        'dtmf_muting'  => ($conf['Rx1']['DTMF_MUTING'] ?? '0') === '1',
        'rx_freq'      => $qth['rx']['A']['freq'] ?? '',
        'tx_freq'      => $qth['tx']['A']['freq'] ?? '',
        'tx_power'     => $qth['tx']['A']['pwr'] ?? '',
        'node_class'   => $nodeInfo['nodeClass'] ?? 'hotspot',
        'rx_sql_type'  => $qth['rx']['A']['sqlType'] ?? '',
        'ant_comment'  => $qth['tx']['A']['ant']['comment'] ?? $qth['rx']['A']['ant']['comment'] ?? '',
        'ant_height'   => $qth['tx']['A']['ant']['height'] ?? $qth['rx']['A']['ant']['height'] ?? '',
        'ant_dir'      => $qth['tx']['A']['ant']['dir'] ?? $qth['rx']['A']['ant']['dir'] ?? '',
        'ant_gain'     => $qth['tx']['A']['ant']['gain'] ?? '',
        'ant_type'     => $qth['tx']['A']['ant']['Antenna_type'] ?? '',
    ];
}

$errors = [];
$saved = false;
$current = readCurrent();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = $_POST;
    foreach (['callsign', 'domain', 'cert_email', 'location', 'sysop', 'qth_name'] as $f) {
        $current[$f] = trim($in[$f] ?? '');
    }
    $current['monitor_tgs'] = trim($in['monitor_tgs'] ?? '');
    $current['ctcss_to_tg'] = trim($in['ctcss_to_tg'] ?? '');
    $current['short_ident'] = trim($in['short_ident'] ?? '15');
    $current['long_ident']  = trim($in['long_ident'] ?? '60');
    $current['hidden']      = isset($in['hidden']);
    $current['dtmf_muting'] = isset($in['dtmf_muting']);
    $current['lat']         = trim($in['lat'] ?? '');
    $current['long']        = trim($in['long'] ?? '');
    $current['gridsquare']  = trim($in['gridsquare'] ?? '');
    $current['rx_freq']     = trim($in['rx_freq'] ?? '');
    $current['tx_freq']     = trim($in['tx_freq'] ?? '');
    $current['tx_power']    = trim($in['tx_power'] ?? '');
    $current['node_class']  = trim($in['node_class'] ?? 'hotspot');
    $current['rx_sql_type'] = trim($in['rx_sql_type'] ?? '');
    $current['ant_comment'] = trim($in['ant_comment'] ?? '');
    $current['ant_height']  = trim($in['ant_height'] ?? '');
    $current['ant_dir']     = trim($in['ant_dir'] ?? '');
    $current['ant_gain']    = trim($in['ant_gain'] ?? '');
    $current['ant_type']    = trim($in['ant_type'] ?? '');

    // Validation — this writes a live radio's config, so reject anything
    // that would leave svxlink.conf or node_info.json broken rather than
    // trying to guess a sane fallback.
    if ($current['callsign'] === '') {
        $errors[] = 'Callsign is required.';
    }
    if ($current['cert_email'] !== '' && !filter_var($current['cert_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Certificate email is not a valid email address.';
    }
    if (!is_numeric($current['short_ident']) || !is_numeric($current['long_ident'])) {
        $errors[] = 'Ident intervals must be numbers (minutes). Use 0 to disable.';
    } elseif ((int)$current['short_ident'] > 0 && (int)$current['long_ident'] > 0
              && (int)$current['long_ident'] % (int)$current['short_ident'] !== 0) {
        // svxlink.conf(5): "The LONG_IDENT_INTERVAL must be an even multiple
        // of the SHORT_IDENT_INTERVAL" -- not enforced by SvxLink itself at
        // load time, so a bad combination fails silently at runtime instead.
        $errors[] = 'Long ident interval must be an even multiple of the short ident interval (e.g. 15 and 60, not 10 and 45).';
    }
    if ($current['lat'] !== '' && !is_numeric($current['lat'])) {
        $errors[] = 'Latitude must be decimal (e.g. 59.3628802).';
    }
    if ($current['long'] !== '' && !is_numeric($current['long'])) {
        $errors[] = 'Longitude must be decimal (e.g. 17.9681359).';
    }
    if ($current['rx_freq'] !== '' && !is_numeric($current['rx_freq'])) {
        $errors[] = 'RX frequency must be numeric (MHz).';
    }
    if ($current['tx_freq'] !== '' && !is_numeric($current['tx_freq'])) {
        $errors[] = 'TX frequency must be numeric (MHz).';
    }
    if ($current['monitor_tgs'] !== '' && !preg_match('/^[0-9+,\s]*$/', $current['monitor_tgs'])) {
        $errors[] = 'Monitored talkgroups: only digits, commas and leading + for priority are allowed.';
    }
    if ($current['ctcss_to_tg'] !== '' && !preg_match('/^[0-9.:,\s]*$/', $current['ctcss_to_tg'])) {
        $errors[] = 'CTCSS-to-TG mapping: expected <tone>:<talkgroup>,... e.g. "88.5:0,82.5:240".';
    }

    if (empty($errors)) {
        try {
            iniSyncUpdateSection(SVX_CONF, 'ReflectorLogic', [
                'CALLSIGN'    => '"' . $current['callsign'] . '"',
                'DNS_DOMAIN'  => $current['domain'],
                'CERT_EMAIL'  => '"' . $current['cert_email'] . '"',
                'MONITOR_TGS' => $current['monitor_tgs'],
            ]);
            iniSyncUpdateSection(SVX_CONF, 'SimplexLogic', [
                'CALLSIGN'              => $current['callsign'],
                'CTCSS_TO_TG'           => $current['ctcss_to_tg'],
                'SHORT_IDENT_INTERVAL'  => $current['short_ident'],
                'LONG_IDENT_INTERVAL'   => $current['long_ident'],
            ]);
            iniSyncUpdateSection(SVX_CONF, 'Rx1', [
                'DTMF_MUTING' => $current['dtmf_muting'] ? '1' : '0',
            ]);
            writeNodeInfoJson(NODE_INFO, [
                'nodeLocation' => $current['location'],
                'sysop'        => $current['sysop'] !== '' ? $current['sysop'] : $current['callsign'],
                'hidden'       => $current['hidden'],
                'qthName'      => $current['qth_name'],
                'lat'          => $current['lat'],
                'long'         => $current['long'],
                'gridsquare'   => $current['gridsquare'],
                'nodeClass'    => $current['node_class'],
                'ctcssToTg'    => $current['ctcss_to_tg'],
                'rxFreq'       => (float)($current['rx_freq'] ?: 0),
                'txFreq'       => (float)($current['tx_freq'] ?: 0),
                'txPower'      => $current['tx_power'],
                'rxSqlType'    => $current['rx_sql_type'],
                'antComment'   => $current['ant_comment'],
                'antHeight'    => $current['ant_height'],
                'antDir'       => $current['ant_dir'],
                'antGain'      => $current['ant_gain'],
                'antType'      => $current['ant_type'],
            ]);

            // The physical radio module's frequency lives entirely outside
            // svxlink.conf/node_info.json (see updateRadioFrequency()'s
            // docblock) -- retune it too, or the two fields above are just
            // portal display text that don't match what's actually on air.
            $radioMsg = null;
            if ($current['rx_freq'] !== '' && $current['tx_freq'] !== ''
                && (float)$current['rx_freq'] !== (float)$current['tx_freq']) {
                $radioMsg = 'Radio NOT retuned: RX and TX frequency differ, but this hotspot\'s '
                    . 'SA818 module only supports a single simplex frequency.';
            } elseif ($current['rx_freq'] !== '') {
                try {
                    $radioMsg = updateRadioFrequency((float)$current['rx_freq']);
                } catch (Throwable $e) {
                    $radioMsg = 'Radio NOT retuned: ' . $e->getMessage();
                }
            }

            $saved = true;
        } catch (Throwable $e) {
            $errors[] = 'Failed to write config: ' . $e->getMessage();
        }
    }
}

?>
<div class="mx-card">
  <h1>Node Setup</h1>
  <p class="mx-sub">Full node configuration — callsign, reflector, radio, and portal display fields.</p>

<?php if ($saved): ?>
  <div class="mx-msg mx-msg-ok">
    Saved. Restart SvxLink from the <a href="/power/">Power</a> page for other changes (callsign,
    reflector, idents, etc.) to take effect.
    <?php if ($radioMsg !== null): ?><br><?php echo htmlspecialchars($radioMsg); ?><?php endif; ?>
  </div>
<?php endif; ?>

<?php foreach ($errors as $err): ?>
  <div class="mx-msg mx-msg-err"><?php echo htmlspecialchars($err); ?></div>
<?php endforeach; ?>

<form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">

  <div class="mx-section">Identity</div>
  <div class="mx-row"><label for="callsign">Callsign</label>
    <input type="text" id="callsign" name="callsign" value="<?php echo htmlspecialchars($current['callsign']); ?>"></div>
  <div class="mx-row"><label for="sysop">Sysop</label>
    <input type="text" id="sysop" name="sysop" value="<?php echo htmlspecialchars($current['sysop']); ?>"></div>
  <div class="mx-hint">Shown on the SvxReflector portal; defaults to the callsign if left blank.</div>
  <div class="mx-row"><label for="node_class">Node class</label>
    <input type="text" id="node_class" name="node_class" value="<?php echo htmlspecialchars($current['node_class']); ?>"></div>
  <div class="mx-hint">e.g. "hotspot" or "repeater" — preserved from the existing file, never silently overwritten.</div>

  <div class="mx-section">Reflector</div>
  <div class="mx-row"><label for="domain">SvxLink domain</label>
    <input type="text" id="domain" name="domain" value="<?php echo htmlspecialchars($current['domain']); ?>"></div>
  <div class="mx-row"><label for="cert_email">Certificate email</label>
    <input type="email" id="cert_email" name="cert_email" value="<?php echo htmlspecialchars($current['cert_email']); ?>"></div>
  <div class="mx-hint">Must match your SM callbook email — the reflector checks this.</div>
  <div class="mx-row"><label for="monitor_tgs">Monitored talkgroups</label>
    <input type="text" id="monitor_tgs" name="monitor_tgs" value="<?php echo htmlspecialchars($current['monitor_tgs']); ?>"></div>
  <div class="mx-hint">Comma-separated, e.g. "240,2400". Prefix with + for priority.</div>

  <div class="mx-section">Radio</div>
  <div class="mx-row"><label for="ctcss_to_tg">CTCSS-to-TG mapping</label>
    <input type="text" id="ctcss_to_tg" name="ctcss_to_tg" value="<?php echo htmlspecialchars($current['ctcss_to_tg']); ?>"></div>
  <div class="mx-hint">e.g. "88.5:0,82.5:240" — tone:talkgroup pairs.</div>
  <div class="mx-row"><label for="rx_freq">RX frequency (MHz)</label>
    <input type="text" id="rx_freq" name="rx_freq" value="<?php echo htmlspecialchars((string)$current['rx_freq']); ?>"></div>
  <div class="mx-row"><label for="tx_freq">TX frequency (MHz)</label>
    <input type="text" id="tx_freq" name="tx_freq" value="<?php echo htmlspecialchars((string)$current['tx_freq']); ?>"></div>
  <div class="mx-hint">Saving these actually retunes the SA818 radio module (not just the portal display) — RX and TX must match, since this is a simplex hotspot.</div>
  <div class="mx-row"><label for="dtmf_muting">Mute DTMF tones locally</label>
    <input type="checkbox" id="dtmf_muting" name="dtmf_muting" <?php echo $current['dtmf_muting'] ? 'checked' : ''; ?>></div>
  <div class="mx-hint">Stops the DTMF tones of your own commands (e.g. changing talkgroup) from being sent out to the reflector network — on by default for new installs.</div>
  <div class="mx-row"><label for="tx_power">TX power (W)</label>
    <input type="text" id="tx_power" name="tx_power" value="<?php echo htmlspecialchars((string)$current['tx_power']); ?>"></div>
  <div class="mx-row"><label for="rx_sql_type">RX squelch type</label>
    <input type="text" id="rx_sql_type" name="rx_sql_type" value="<?php echo htmlspecialchars($current['rx_sql_type']); ?>"></div>
  <div class="mx-hint">Portal display only, e.g. "CTCSS" — matches SQL_DET in svxlink.conf's [Rx1] but isn't read from it automatically.</div>

  <div class="mx-section">Antenna (portal display only)</div>
  <div class="mx-row"><label for="ant_comment">Description</label>
    <input type="text" id="ant_comment" name="ant_comment" value="<?php echo htmlspecialchars($current['ant_comment']); ?>"></div>
  <div class="mx-row"><label for="ant_height">Height (m)</label>
    <input type="text" id="ant_height" name="ant_height" value="<?php echo htmlspecialchars($current['ant_height']); ?>"></div>
  <div class="mx-row"><label for="ant_dir">Direction</label>
    <input type="text" id="ant_dir" name="ant_dir" value="<?php echo htmlspecialchars($current['ant_dir']); ?>"></div>
  <div class="mx-row"><label for="ant_gain">Gain (TX)</label>
    <input type="text" id="ant_gain" name="ant_gain" value="<?php echo htmlspecialchars($current['ant_gain']); ?>"></div>
  <div class="mx-row"><label for="ant_type">Antenna type (TX)</label>
    <input type="text" id="ant_type" name="ant_type" value="<?php echo htmlspecialchars($current['ant_type']); ?>"></div>
  <div class="mx-hint">None of this feeds SvxLink itself — it's only shown on the SvxReflector portal. Leave blank to omit.</div>

  <div class="mx-section">Identification timing</div>
  <div class="mx-row"><label for="short_ident">Short ident interval (min)</label>
    <input type="text" id="short_ident" name="short_ident" value="<?php echo htmlspecialchars((string)$current['short_ident']); ?>"></div>
  <div class="mx-row"><label for="long_ident">Long ident interval (min)</label>
    <input type="text" id="long_ident" name="long_ident" value="<?php echo htmlspecialchars((string)$current['long_ident']); ?>"></div>
  <div class="mx-hint">0 disables. Minimum spacing between idents is hard-coded to 2 minutes in Logic.tcl regardless of this setting.</div>

  <div class="mx-section">Location</div>
  <div class="mx-row"><label for="location">Location name</label>
    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($current['location']); ?>"></div>
  <div class="mx-row"><label for="qth_name">QTH name</label>
    <input type="text" id="qth_name" name="qth_name" value="<?php echo htmlspecialchars($current['qth_name']); ?>"></div>
  <div class="mx-row"><label for="lat">Latitude</label>
    <input type="text" id="lat" name="lat" value="<?php echo htmlspecialchars((string)$current['lat']); ?>"></div>
  <div class="mx-row"><label for="long">Longitude</label>
    <input type="text" id="long" name="long" value="<?php echo htmlspecialchars((string)$current['long']); ?>"></div>
  <div class="mx-row"><label for="gridsquare">Gridsquare</label>
    <input type="text" id="gridsquare" name="gridsquare" value="<?php echo htmlspecialchars($current['gridsquare']); ?>"></div>
  <div class="mx-hint">Decimal degrees with a "." separator, e.g. 59.3628802.</div>
  <div class="mx-row"><label for="hidden">Hide from portal</label>
    <input type="checkbox" id="hidden" name="hidden" <?php echo $current['hidden'] ? 'checked' : ''; ?>></div>

  <br>
  <center><button type="submit" class="mx-btn">Save</button></center>
</form>

</div>
  </body>
</html>
