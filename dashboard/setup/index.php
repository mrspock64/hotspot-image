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
define('SOUNDS_BASE_DIR', '/usr/share/svxlink/sounds');

// Friendly labels for the language packs this project knows about --
// falls back to the raw directory name for anything else installed by
// hand. Only languages with a sound pack actually present on disk are
// offered; picking one that isn't installed would leave SvxLink unable
// to find any clips at all.
const LANGUAGE_LABELS = [
    'en_US' => 'English (US)',
    'sv_SE'  => 'Svenska',
];

function getAvailableLanguages(): array
{
    $dirs = @glob(SOUNDS_BASE_DIR . '/*', GLOB_ONLYDIR) ?: [];
    $codes = array_map('basename', $dirs);
    sort($codes);
    return $codes;
}

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
        'ctcss_to_tg'  => $conf['SimplexLogic']['CTCSS_TO_TG'] ?? '',
        'short_ident'  => $conf['SimplexLogic']['SHORT_IDENT_INTERVAL'] ?? '15',
        'long_ident'   => $conf['SimplexLogic']['LONG_IDENT_INTERVAL'] ?? '60',
        'language'     => $conf['SimplexLogic']['DEFAULT_LANG'] ?? 'en_US',
        'location'     => $nodeInfo['nodeLocation'] ?? '',
        'hidden'       => (bool)($nodeInfo['hidden'] ?? false),
        'sysop'        => $nodeInfo['sysop'] ?? '',
        'qth_name'     => $qth['name'] ?? '',
        'lat'          => $qth['pos']['lat'] ?? '',
        'long'         => $qth['pos']['long'] ?? '',
        'gridsquare'   => $qth['pos']['loc'] ?? '',
        'dtmf_muting'  => ($conf['Rx1']['DTMF_MUTING'] ?? '0') === '1',
        // A single field: this hotspot's SA818 module only supports one
        // simplex frequency, so RX and TX are always the same value. Fall
        // back to whichever of the two is set, in case they were ever
        // edited apart from this page (e.g. directly in node_info.json).
        'freq'         => $qth['rx']['A']['freq'] ?? $qth['tx']['A']['freq'] ?? '',
        'tx_power'     => $qth['tx']['A']['pwr'] ?? '',
        'node_class'   => $nodeInfo['nodeClass'] ?? 'hotspot',
        'rx_sql_type'  => $qth['rx']['A']['sqlType'] ?? '',
        'ant_comment'  => $qth['tx']['A']['ant']['comment'] ?? $qth['rx']['A']['ant']['comment'] ?? '',
        'ant_height'   => $qth['tx']['A']['ant']['height'] ?? $qth['rx']['A']['ant']['height'] ?? '',
        'ant_dir'      => $qth['tx']['A']['ant']['dir'] ?? $qth['rx']['A']['ant']['dir'] ?? '',
        'ant_gain'     => $qth['tx']['A']['ant']['gain'] ?? '',
        'ant_type'     => $qth['tx']['A']['ant']['Antenna_type'] ?? '',
        // Header's RX level meter style -- a custom key SvxLink itself
        // never reads, same pattern as QsoRecorder's RECORD_ONLY_TGS.
        'meter_style'  => ($conf['Dashboard']['RX_METER_STYLE'] ?? 'bar') === 'analog' ? 'analog' : 'bar',
    ];
}

$errors = [];
$saved = false;
$current = readCurrent();
$previousFreq = $current['freq'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = $_POST;
    foreach (['callsign', 'domain', 'cert_email', 'location', 'sysop', 'qth_name'] as $f) {
        $current[$f] = trim($in[$f] ?? '');
    }
    $current['ctcss_to_tg'] = trim($in['ctcss_to_tg'] ?? '');
    $current['short_ident'] = trim($in['short_ident'] ?? '15');
    $current['long_ident']  = trim($in['long_ident'] ?? '60');
    $current['language']    = trim($in['language'] ?? 'en_US');
    $current['hidden']      = isset($in['hidden']);
    $current['dtmf_muting'] = isset($in['dtmf_muting']);
    $current['lat']         = trim($in['lat'] ?? '');
    $current['long']        = trim($in['long'] ?? '');
    $current['gridsquare']  = trim($in['gridsquare'] ?? '');
    $current['freq']        = trim($in['freq'] ?? '');
    $current['tx_power']    = trim($in['tx_power'] ?? '');
    $current['node_class']  = trim($in['node_class'] ?? 'hotspot');
    $current['rx_sql_type'] = trim($in['rx_sql_type'] ?? '');
    $current['ant_comment'] = trim($in['ant_comment'] ?? '');
    $current['ant_height']  = trim($in['ant_height'] ?? '');
    $current['ant_dir']     = trim($in['ant_dir'] ?? '');
    $current['ant_gain']    = trim($in['ant_gain'] ?? '');
    $current['ant_type']    = trim($in['ant_type'] ?? '');
    $current['meter_style'] = ($in['meter_style'] ?? 'bar') === 'analog' ? 'analog' : 'bar';

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
    if ($current['freq'] !== '' && !is_numeric($current['freq'])) {
        $errors[] = 'Frequency must be numeric (MHz).';
    }
    if ($current['ctcss_to_tg'] !== '' && !preg_match('/^[0-9.:,\s]*$/', $current['ctcss_to_tg'])) {
        $errors[] = 'CTCSS-to-TG mapping: expected <tone>:<talkgroup>,... e.g. "88.5:0,82.5:240".';
    }
    if (!in_array($current['language'], getAvailableLanguages(), true)) {
        $errors[] = 'Selected voice language has no sound pack installed on this node.';
    }

    if (empty($errors)) {
        try {
            iniSyncUpdateSection(SVX_CONF, 'ReflectorLogic', [
                'CALLSIGN'    => '"' . $current['callsign'] . '"',
                'DNS_DOMAIN'  => $current['domain'],
                'CERT_EMAIL'  => '"' . $current['cert_email'] . '"',
            ]);
            iniSyncUpdateSection(SVX_CONF, 'SimplexLogic', [
                'CALLSIGN'              => $current['callsign'],
                'CTCSS_TO_TG'           => $current['ctcss_to_tg'],
                'SHORT_IDENT_INTERVAL'  => $current['short_ident'],
                'LONG_IDENT_INTERVAL'   => $current['long_ident'],
                'DEFAULT_LANG'          => $current['language'],
            ]);
            iniSyncUpdateSection(SVX_CONF, 'Rx1', [
                'DTMF_MUTING' => $current['dtmf_muting'] ? '1' : '0',
            ]);
            iniSyncUpdateSection(SVX_CONF, 'Dashboard', [
                'RX_METER_STYLE' => $current['meter_style'],
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
                'rxFreq'       => (float)($current['freq'] ?: 0),
                'txFreq'       => (float)($current['freq'] ?: 0),
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
            // docblock) -- retune it too, or the field above is just portal
            // display text that doesn't match what's actually on air. Only
            // when it actually changed, though -- saving the rest of this
            // form (location, monitored TGs, ...) shouldn't re-key the SA818
            // module every time.
            $radioMsg = null;
            if ($current['freq'] !== '' && (float)$current['freq'] !== (float)$previousFreq) {
                try {
                    $radioMsg = updateRadioFrequency((float)$current['freq']);
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
  <div class="mx-hint">Monitored talkgroups have moved to the <a href="/tg.php">Talk Groups</a> page — a checkbox per TG instead of hand-typing a comma list.</div>

  <div class="mx-section">Radio</div>
  <div class="mx-row"><label for="ctcss_to_tg">CTCSS-to-TG mapping</label>
    <input type="text" id="ctcss_to_tg" name="ctcss_to_tg" value="<?php echo htmlspecialchars($current['ctcss_to_tg']); ?>"></div>
  <div class="mx-hint">e.g. "88.5:0,82.5:240" — tone:talkgroup pairs.</div>
  <div class="mx-row"><label for="freq">Frequency (MHz)</label>
    <input type="text" id="freq" name="freq" value="<?php echo htmlspecialchars((string)$current['freq']); ?>"></div>
  <div class="mx-hint">Saving this actually retunes the SA818 radio module (not just the portal display). One field, since this is a simplex hotspot — RX and TX are always the same frequency.</div>
  <div class="mx-row"><label>SSA suggested channels</label>
    <div>
      <div style="font-size:11px;color:#888;margin-bottom:3px;">70cm (434.450–434.500 MHz, 12.5 kHz spacing)</div>
      <?php foreach (['434.450000', '434.462500', '434.475000', '434.487500', '434.500000'] as $f): ?>
      <button type="button" class="mx-btn mx-btn-ghost" style="padding:2px 8px;font-size:12px;margin:0 4px 4px 0;" onclick="setSuggestedFreq('<?php echo $f; ?>')"><?php echo number_format((float)$f, 4); ?></button>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:4px;">
      <div style="font-size:11px;color:#888;margin-bottom:3px;">2m (144.8250–144.8625 MHz, 12.5 kHz spacing)</div>
      <?php foreach (['144.825000', '144.837500', '144.850000', '144.862500'] as $f): ?>
      <button type="button" class="mx-btn mx-btn-ghost" style="padding:2px 8px;font-size:12px;margin:0 4px 4px 0;" onclick="setSuggestedFreq('<?php echo $f; ?>')"><?php echo number_format((float)$f, 4); ?></button>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="mx-hint">SSA's recommended channels for DV/analog internet gateways ("hotspots") — click one to fill in the field above, then Save to actually retune the radio.</div>
  <script>
    function setSuggestedFreq(f) {
      document.getElementById('freq').value = f;
    }
  </script>
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
  <div class="mx-row"><label for="language">Voice language</label>
    <select id="language" name="language" style="padding:6px; border-radius:6px; border:1px solid var(--mx-border);">
<?php foreach (getAvailableLanguages() as $code): ?>
      <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $current['language'] === $code ? 'selected' : ''; ?>><?php echo htmlspecialchars(LANGUAGE_LABELS[$code] ?? $code); ?></option>
<?php endforeach; ?>
    </select>
  </div>
  <div class="mx-hint">Controls every stock SvxLink announcement — manual/periodic identification, time, digit readouts. Only languages with a sound pack actually installed on this node are listed; D911#/D920#/D921# (Radio Test page) are unaffected.</div>
  <div class="mx-row"><label for="short_ident">Short ident interval (min)</label>
    <input type="text" id="short_ident" name="short_ident" value="<?php echo htmlspecialchars((string)$current['short_ident']); ?>"></div>
  <div class="mx-row"><label for="long_ident">Long ident interval (min)</label>
    <input type="text" id="long_ident" name="long_ident" value="<?php echo htmlspecialchars((string)$current['long_ident']); ?>"></div>
  <div class="mx-hint">0 disables. Minimum spacing between idents is hard-coded to 2 minutes in Logic.tcl regardless of this setting.</div>

  <div class="mx-section">Dashboard</div>
  <div class="mx-row"><label for="meter_style">Header RX meter</label>
    <select id="meter_style" name="meter_style" style="padding:6px; border-radius:6px; border:1px solid var(--mx-border);">
      <option value="bar" <?php echo $current['meter_style'] === 'bar' ? 'selected' : ''; ?>>Bar</option>
      <option value="analog" <?php echo $current['meter_style'] === 'analog' ? 'selected' : ''; ?>>Analog needle</option>
    </select>
  </div>
  <div class="mx-hint">Only shown while QSO Log recording is on (see <a href="/qsolog/">QSO Log</a>) -- that's what feeds it.</div>

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
