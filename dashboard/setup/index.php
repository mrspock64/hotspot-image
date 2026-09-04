<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>Node Setup</title>
    <link href="/css/css.php" type="text/css" rel="stylesheet" />
<style type="text/css">
body {
  background-color: #eee;
  font-size: 15px;
  font-family: Arial;
  color: #444;
}
fieldset.form {
  border:#3083b8 2px groove;
  box-shadow:5px 5px 20px #999;
  background-color:#f1f1f1;
  width:560px;
  margin:15px auto;
  padding: 12px 20px 20px 20px;
  border-radius: 10px;
}
h1 {
  color:#00aee8;
  font: 18pt arial, sans-serif;
  font-weight:bold;
  text-shadow: 0.25px 0.25px gray;
}
label { display:inline-block; width: 220px; font-weight:bold; }
input[type=text], input[type=email], input[type=number] { width: 260px; padding:3px; }
.row { margin-bottom: 8px; }
.hint { color:#777; font-size: 11px; margin: -4px 0 8px 220px; }
.msg-ok { background:#d7f5da; border:1px solid #4aa361; padding:8px; border-radius:6px; margin-bottom:10px; }
.msg-err { background:#f7d7d7; border:1px solid #c33; padding:8px; border-radius:6px; margin-bottom:10px; }
.section-title { font-weight:bold; color:#00aee8; margin: 16px 0 6px 0; border-bottom: 1px solid #ccc; }
</style>
  </head>
  <body>
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
        'rx_freq'      => $qth['rx']['A']['freq'] ?? '',
        'tx_freq'      => $qth['tx']['A']['freq'] ?? '',
        'tx_power'     => $qth['tx']['A']['pwr'] ?? '',
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
    $current['lat']         = trim($in['lat'] ?? '');
    $current['long']        = trim($in['long'] ?? '');
    $current['gridsquare']  = trim($in['gridsquare'] ?? '');
    $current['rx_freq']     = trim($in['rx_freq'] ?? '');
    $current['tx_freq']     = trim($in['tx_freq'] ?? '');
    $current['tx_power']    = trim($in['tx_power'] ?? '');

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
            writeNodeInfoJson(
                NODE_INFO,
                $current['location'],
                $current['sysop'] !== '' ? $current['sysop'] : $current['callsign'],
                $current['hidden'],
                $current['qth_name'],
                $current['lat'],
                $current['long'],
                $current['gridsquare'],
                (float)($current['rx_freq'] ?: 0),
                (float)($current['tx_freq'] ?: 0),
                $current['tx_power']
            );
            $saved = true;
        } catch (Throwable $e) {
            $errors[] = 'Failed to write config: ' . $e->getMessage();
        }
    }
}

?>
<fieldset class="form">
<center><h1>Node Setup</h1></center>

<?php if ($saved): ?>
  <div class="msg-ok">
    Saved. Restart SvxLink from the <a href="/power/">Power</a> page for changes to take effect.
    <br>Note: RX/TX frequency here only updates <code>node_info.json</code> (what the SvxReflector
    portal shows) — it does not retune the radio module itself. That's a separate step, not yet
    wired into this page.
  </div>
<?php endif; ?>

<?php foreach ($errors as $err): ?>
  <div class="msg-err"><?php echo htmlspecialchars($err); ?></div>
<?php endforeach; ?>

<form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">

  <div class="section-title">Identity</div>
  <div class="row"><label for="callsign">Callsign</label>
    <input type="text" id="callsign" name="callsign" value="<?php echo htmlspecialchars($current['callsign']); ?>"></div>
  <div class="row"><label for="sysop">Sysop</label>
    <input type="text" id="sysop" name="sysop" value="<?php echo htmlspecialchars($current['sysop']); ?>"></div>
  <div class="hint">Shown on the SvxReflector portal; defaults to the callsign if left blank.</div>

  <div class="section-title">Reflector</div>
  <div class="row"><label for="domain">SvxLink domain</label>
    <input type="text" id="domain" name="domain" value="<?php echo htmlspecialchars($current['domain']); ?>"></div>
  <div class="row"><label for="cert_email">Certificate email</label>
    <input type="email" id="cert_email" name="cert_email" value="<?php echo htmlspecialchars($current['cert_email']); ?>"></div>
  <div class="hint">Must match your SM callbook email — the reflector checks this.</div>
  <div class="row"><label for="monitor_tgs">Monitored talkgroups</label>
    <input type="text" id="monitor_tgs" name="monitor_tgs" value="<?php echo htmlspecialchars($current['monitor_tgs']); ?>"></div>
  <div class="hint">Comma-separated, e.g. "240,2400". Prefix with + for priority.</div>

  <div class="section-title">Radio</div>
  <div class="row"><label for="ctcss_to_tg">CTCSS-to-TG mapping</label>
    <input type="text" id="ctcss_to_tg" name="ctcss_to_tg" value="<?php echo htmlspecialchars($current['ctcss_to_tg']); ?>"></div>
  <div class="hint">e.g. "88.5:0,82.5:240" — tone:talkgroup pairs.</div>
  <div class="row"><label for="rx_freq">RX frequency (MHz)</label>
    <input type="text" id="rx_freq" name="rx_freq" value="<?php echo htmlspecialchars((string)$current['rx_freq']); ?>"></div>
  <div class="row"><label for="tx_freq">TX frequency (MHz)</label>
    <input type="text" id="tx_freq" name="tx_freq" value="<?php echo htmlspecialchars((string)$current['tx_freq']); ?>"></div>
  <div class="row"><label for="tx_power">TX power (W)</label>
    <input type="text" id="tx_power" name="tx_power" value="<?php echo htmlspecialchars((string)$current['tx_power']); ?>"></div>

  <div class="section-title">Identification timing</div>
  <div class="row"><label for="short_ident">Short ident interval (min)</label>
    <input type="text" id="short_ident" name="short_ident" value="<?php echo htmlspecialchars((string)$current['short_ident']); ?>"></div>
  <div class="row"><label for="long_ident">Long ident interval (min)</label>
    <input type="text" id="long_ident" name="long_ident" value="<?php echo htmlspecialchars((string)$current['long_ident']); ?>"></div>
  <div class="hint">0 disables. Minimum spacing between idents is hard-coded to 2 minutes in Logic.tcl regardless of this setting.</div>

  <div class="section-title">Location</div>
  <div class="row"><label for="location">Location name</label>
    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($current['location']); ?>"></div>
  <div class="row"><label for="qth_name">QTH name</label>
    <input type="text" id="qth_name" name="qth_name" value="<?php echo htmlspecialchars($current['qth_name']); ?>"></div>
  <div class="row"><label for="lat">Latitude</label>
    <input type="text" id="lat" name="lat" value="<?php echo htmlspecialchars((string)$current['lat']); ?>"></div>
  <div class="row"><label for="long">Longitude</label>
    <input type="text" id="long" name="long" value="<?php echo htmlspecialchars((string)$current['long']); ?>"></div>
  <div class="row"><label for="gridsquare">Gridsquare</label>
    <input type="text" id="gridsquare" name="gridsquare" value="<?php echo htmlspecialchars($current['gridsquare']); ?>"></div>
  <div class="hint">Decimal degrees with a "." separator, e.g. 59.3628802.</div>
  <div class="row"><label for="hidden">Hide from portal</label>
    <input type="checkbox" id="hidden" name="hidden" <?php echo $current['hidden'] ? 'checked' : ''; ?>></div>

  <br>
  <center><button type="submit" class="red" style="height:34px;width:160px;">Save</button></center>
</form>

</fieldset>
  </body>
</html>
