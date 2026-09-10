<?php
// Setup module -- READ-ONLY by design for now. Setup is a write-capable
// page in production (callsign/reflector/frequency/location, written to
// /etc/svxlink/svxlink.conf + node_info.json via inisync.php's
// iniSyncUpdateSection()), unlike QSO Log/RX Monitor/Frequency/Talkgroup/
// Node/Signal/Auto-update, which only ever read. Deliberately shipped
// display-only first rather than a full read+write module in the same
// pass -- see docs/dashboard-v2-brief.md's write-heavy-pages section.
//
// The read logic itself is duplicated from dashboard/setup/index.php's
// readCurrent(), not require()'d: that function lives inline in a page
// file (mixed with its own POST handler and HTML), not in a separate
// dashboard/include/*.php the way qso_recorder.php or inisync.php are --
// pulling it in directly would also execute that page's own request
// handling and try to emit HTML. Refactoring production's Setup page to
// extract a reusable include is out of scope for this read-only preview
// (and would be touching dashboard/, which this branch doesn't). Kept in
// lockstep with that function by hand; if it drifts, that's the file to
// re-check against.
header('Content-Type: application/json');

const SVX_CONF = '/etc/svxlink/svxlink.conf';
const NODE_INFO = '/etc/svxlink/node_info.json';

const LANGUAGE_LABELS = [
    'en_US' => 'English (US)',
    'sv_SE' => 'Svenska',
];

function readCurrent(): array
{
    $conf = @parse_ini_file(SVX_CONF, true, INI_SCANNER_RAW) ?: [];
    $nodeInfo = [];
    if (is_readable(NODE_INFO)) {
        $raw = file_get_contents(NODE_INFO);
        $decoded = json_decode($raw, true);
        if ($decoded === null) {
            $decoded = json_decode(preg_replace('/,(\s*[}\]])/', '$1', $raw), true);
        }
        $nodeInfo = $decoded ?: [];
    }
    $qth = $nodeInfo['qth'][0] ?? [];

    return [
        'callsign' => trim($conf['ReflectorLogic']['CALLSIGN'] ?? '', '" '),
        'domain' => $conf['ReflectorLogic']['DNS_DOMAIN'] ?? 'sm.svxlink.org',
        'cert_email' => trim($conf['ReflectorLogic']['CERT_EMAIL'] ?? '', '" '),
        'language' => $conf['SimplexLogic']['DEFAULT_LANG'] ?? 'en_US',
        'location' => $nodeInfo['nodeLocation'] ?? '',
        'sysop' => $nodeInfo['sysop'] ?? '',
        'qth_name' => $qth['name'] ?? '',
        'gridsquare' => $qth['pos']['loc'] ?? '',
        'freq' => $qth['rx']['A']['freq'] ?? $qth['tx']['A']['freq'] ?? '',
        'tx_power' => $qth['tx']['A']['pwr'] ?? '',
        'node_class' => $nodeInfo['nodeClass'] ?? 'hotspot',
        'hidden' => (bool)($nodeInfo['hidden'] ?? false),
    ];
}

$current = readCurrent();
$current['language_label'] = LANGUAGE_LABELS[$current['language']] ?? $current['language'];

echo json_encode($current);
