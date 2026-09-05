<?php
/**
 * Shared helpers for the QSO Log page and RX Monitor, both of which read
 * from SvxLink's own built-in QSO Recorder (see svxlink.conf(5)'s "QSO
 * Recorder Section" -- RF.Guru's stock config already ships a fully
 * configured [QsoRecorder] section, just never wired up via QSO_RECORDER=
 * in [SimplexLogic]/[ReflectorLogic]).
 *
 * SvxLink writes each recording as a hidden placeholder
 * (".qsorec_<Logic>.wav", 0 bytes) the instant a QSO starts, keeps writing
 * to a visible "qsorec_<Logic>_<timestamp>.wav" once real audio arrives,
 * then on close hands it to ENCODER_CMD (lame in this config), which
 * converts it to .mp3 and removes the .wav. So: any *.wav file present is
 * (by construction) the one currently being recorded; *.mp3 files are
 * finished recordings.
 *
 * MP3, not the originally-used Ogg Vorbis: Safari (macOS and iOS) has no
 * Vorbis decoder at all, so <audio src="...ogg"> silently does nothing
 * there -- found the hard way when Play did nothing. MP3 plays natively
 * everywhere.
 */

require_once __DIR__ . '/inisync.php';

define('QSO_RECORDER_DEFAULT_DIR', '/var/spool/svxlink/qso_recorder');
define('QSO_RECORDER_SVX_CONF', '/etc/svxlink/svxlink.conf');
// Matches QSO_RECORDER=8:QsoRecorder in [SimplexLogic] -- "81#" activates,
// "80#" deactivates, live, no svxlink restart needed (svxlink.conf(5)).
define('QSO_RECORDER_DTMF_CMD', '8');

function qsoRecorderDir(): string
{
    $conf = @parse_ini_file(QSO_RECORDER_SVX_CONF, true, INI_SCANNER_RAW);
    return $conf['QsoRecorder']['REC_DIR'] ?? QSO_RECORDER_DEFAULT_DIR;
}

/** @return array{active: bool, max_dirsize: int, qso_timeout: int, record_only_tgs: list<string>} */
function getQsoRecorderSettings(): array
{
    $conf = @parse_ini_file(QSO_RECORDER_SVX_CONF, true, INI_SCANNER_RAW) ?: [];
    $raw = $conf['QsoRecorder']['RECORD_ONLY_TGS'] ?? '';
    $recordOnly = $raw === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $raw)), fn($tg) => $tg !== ''));
    return [
        'active'          => ($conf['QsoRecorder']['DEFAULT_ACTIVE'] ?? '0') === '1',
        'max_dirsize'     => (int)($conf['QsoRecorder']['MAX_DIRSIZE'] ?? 2000),
        'qso_timeout'     => (int)($conf['QsoRecorder']['QSO_TIMEOUT'] ?? 5),
        'record_only_tgs' => $recordOnly,
    ];
}

/**
 * Persists all settings (so they survive a reboot/restart) and, for the
 * on/off switch specifically, also applies it immediately via SvxLink's
 * own DTMF control for the recorder -- QSO_RECORDER=8:QsoRecorder in
 * [SimplexLogic] means "81#" turns it on and "80#" off right now, without
 * needing a service restart. QSO_TIMEOUT has no such live control
 * (svxlink.conf(5) documents no DTMF command for it) -- it's only read at
 * startup, so a change here needs a restart (Power page) to take effect,
 * same as most Setup page fields.
 *
 * RECORD_ONLY_TGS is a key SvxLink itself never reads -- it's read fresh
 * off disk by tag_and_encode.py (this project's ENCODER_CMD) every time a
 * recording finishes, so a change here applies to the very next recording
 * with no restart needed at all.
 *
 * @param list<string> $recordOnlyTgs
 */
function saveQsoRecorderSettings(bool $active, int $maxDirsizeMb, int $qsoTimeoutSec, array $recordOnlyTgs = []): void
{
    iniSyncUpdateSection(QSO_RECORDER_SVX_CONF, 'QsoRecorder', [
        'DEFAULT_ACTIVE'   => $active ? '1' : '0',
        'MAX_DIRSIZE'      => (string)$maxDirsizeMb,
        'QSO_TIMEOUT'      => (string)$qsoTimeoutSec,
        'RECORD_ONLY_TGS'  => implode(',', $recordOnlyTgs),
    ]);
    shell_exec('/usr/sbin/hotspot_dtmf ' . escapeshellarg(QSO_RECORDER_DTMF_CMD . ($active ? '1' : '0') . '#'));
}

/**
 * @return array{finished: list<array{file:string,mtime:int,size:int}>, inProgress: ?array{file:string,mtime:int,size:int}}
 */
function listQsoRecordings(): array
{
    $dir = qsoRecorderDir();
    $finished = [];
    $inProgress = null;

    foreach (glob($dir . '/*') ?: [] as $path) {
        $name = basename($path);
        if (!is_file($path)) {
            continue;
        }
        if (preg_match('/^\.?qsorec_.*\.wav$/', $name)) {
            // A dot-prefixed placeholder with 0 bytes means "waiting for a
            // transmission to actually start" -- not useful to show as
            // "recording" until it has content. A .wav with an old mtime
            // is a leftover, not something still being written -- SvxLink
            // writes to an active recording continuously, so anything
            // untouched for more than a few seconds has either finished
            // and is stuck (ENCODER_CMD failed -- this exact bug bit us
            // once already, see install-rx-monitor.sh) or is otherwise
            // orphaned. Never show a stale file as "recording now".
            if (filesize($path) > 44 && (time() - filemtime($path)) < 10) {
                $inProgress = ['file' => $name, 'mtime' => filemtime($path), 'size' => filesize($path)];
            }
        } elseif (preg_match('/^qsorec_.*\.mp3$/', $name)) {
            $finished[] = ['file' => $name, 'mtime' => filemtime($path), 'size' => filesize($path)];
        }
    }

    usort($finished, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

    return ['finished' => $finished, 'inProgress' => $inProgress];
}

/**
 * Parses the timestamp (and, for recordings made since tag_and_encode.py,
 * the talkgroup/callsign) SvxLink/our own ENCODER_CMD embed in the
 * filename. Two formats:
 *   - Tagged (current):  qsorec_<Logic>_TG<n|none>_<CALL|none>_<start>[_<end>].mp3
 *   - Untagged (older, from before this feature): qsorec_<Logic>_<start>[_<end>].mp3
 * Recordings made before this feature shipped only ever match the second
 * form and simply have no tg/callsign -- there's no reliable way to
 * attribute them after the fact, so they're left as-is.
 *
 * @return array{when:string, logic:string, tg:?string, callsign:?string}
 */
function qsoRecordingInfo(string $file): array
{
    if (preg_match('/^qsorec_(.+?)_TG(\d+|none)_([A-Za-z0-9]+|none)_(\d{4}-\d{2}-\d{2})_(\d{6})(?:_\d{4}-\d{2}-\d{2}_\d{6})?\.(?:mp3|ogg|wav)$/', $file, $m)) {
        [, $logic, $tg, $call, $ymd, $his] = $m;
        $dt = DateTime::createFromFormat('Y-m-d His', $ymd . ' ' . $his);
        return [
            'when'     => $dt ? $dt->format('Y-m-d H:i:s') : $file,
            'logic'    => $logic,
            'tg'       => $tg === 'none' ? null : $tg,
            'callsign' => $call === 'none' ? null : $call,
        ];
    }
    if (preg_match('/^qsorec_(.+?)_(\d{4}-\d{2}-\d{2})_(\d{6})(?:_\d{4}-\d{2}-\d{2}_\d{6})?\.(?:mp3|ogg|wav)$/', $file, $m)) {
        [, $logic, $ymd, $his] = $m;
        $dt = DateTime::createFromFormat('Y-m-d His', $ymd . ' ' . $his);
        return [
            'when'     => $dt ? $dt->format('Y-m-d H:i:s') : $file,
            'logic'    => $logic,
            'tg'       => null,
            'callsign' => null,
        ];
    }
    return ['when' => $file, 'logic' => '', 'tg' => null, 'callsign' => null];
}

/** Only ever accepts a bare filename matching the recorder's own naming pattern -- never a path. */
function isValidQsoRecordingName(string $name): bool
{
    return (bool)preg_match('/^qsorec_[A-Za-z0-9._-]+\.mp3$/', $name);
}

function deleteQsoRecording(string $name): void
{
    if (!isValidQsoRecordingName($name)) {
        throw new InvalidArgumentException('Not a recognized recording filename.');
    }
    $path = qsoRecorderDir() . '/' . $name;
    if (!is_file($path)) {
        throw new RuntimeException('Recording not found.');
    }
    // The recorder directory is owned by the svxlink user, not www-data --
    // same reason every other config/file mutation in this dashboard goes
    // through sudo rather than a direct unlink().
    exec('sudo rm -f ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        throw new RuntimeException('Failed to delete ' . $name . ': ' . implode(' ', $out));
    }
}
