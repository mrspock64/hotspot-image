<?php
/**
 * Generates a spoken audio file from arbitrary text via espeak-ng, for
 * SvxLink's D920#/D921# custom-message commands (see events.d/Logic.tcl).
 * SvxLink itself has no free-text speech of its own -- only pre-recorded
 * word/digit clips stitched together (see D911#'s IP readout, which
 * spells the address out digit by digit using its own sound library) --
 * so this is the only way to get it to say something genuinely new.
 * Resampled to 16kHz mono to match SvxLink's own sound clips (confirmed
 * live: every existing Core/*.wav is encoded that way; espeak-ng's own
 * default output, 22050Hz, doesn't match and plays back distorted-ish
 * if used as-is).
 */

const TTS_OUTPUT_CUSTOM = '/etc/svxlink/radiotest_message.wav';
const TTS_OUTPUT_ALERT = '/etc/svxlink/alert_message.wav';

const TTS_VOICES = [
    'sv'    => 'Svenska',
    'en-us' => 'English (US)',
    'en-gb' => 'English (UK)',
];

/**
 * RF.Guru's own DTMF relay (hotspot_dtmf -> nc -lk 10000 -> svxlink's
 * stdin) occasionally drops the first character(s) of a command --
 * confirmed live for D920#/D921# too (two failed attempts in a row while
 * testing this). Same mitigation already used for TG select
 * (dashboard/include/buttons.php's sendTgSelectDtmf()): a resend a
 * moment later reliably lands once the connection is "warmed up". A
 * double-play of the same short message if both sends happen to land is
 * a minor annoyance, not a wrong second effect -- an acceptable
 * trade-off for actually working.
 */
function sendDtmfReliable(string $digits): void
{
    shell_exec('/usr/sbin/hotspot_dtmf ' . escapeshellarg($digits));
    usleep(300000);
    shell_exec('/usr/sbin/hotspot_dtmf ' . escapeshellarg($digits));
}

function generateTtsMessage(string $text, string $voice, string $outputPath): void
{
    $text = trim($text);
    if ($text === '') {
        throw new InvalidArgumentException('Message text is empty.');
    }
    if (!array_key_exists($voice, TTS_VOICES)) {
        throw new InvalidArgumentException('Unknown voice.');
    }

    $tmpRaw = tempnam(sys_get_temp_dir(), 'tts-raw-') . '.wav';
    $tmpResampled = tempnam(sys_get_temp_dir(), 'tts-16k-') . '.wav';

    exec('espeak-ng -v ' . escapeshellarg($voice) . ' -w ' . escapeshellarg($tmpRaw) . ' ' . escapeshellarg($text) . ' 2>&1', $out1, $code1);
    if ($code1 !== 0) {
        @unlink($tmpRaw);
        throw new RuntimeException('espeak-ng failed: ' . implode(' ', $out1));
    }

    exec('sox ' . escapeshellarg($tmpRaw) . ' -r 16000 -c 1 ' . escapeshellarg($tmpResampled) . ' 2>&1', $out2, $code2);
    @unlink($tmpRaw);
    if ($code2 !== 0) {
        @unlink($tmpResampled);
        throw new RuntimeException('sox resample failed: ' . implode(' ', $out2));
    }

    // /etc/svxlink is root-owned -- same reason every other write there
    // in this dashboard goes through sudo rather than a direct write.
    exec('sudo cp ' . escapeshellarg($tmpResampled) . ' ' . escapeshellarg($outputPath) . ' 2>&1', $out3, $code3);
    @unlink($tmpResampled);
    if ($code3 !== 0) {
        throw new RuntimeException('Failed to install message file: ' . implode(' ', $out3));
    }
    exec('sudo chmod 644 ' . escapeshellarg($outputPath) . ' 2>&1');
}
