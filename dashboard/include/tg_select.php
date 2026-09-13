<?php
// Shared talkgroup-select DTMF helper -- extracted out of buttons.php so
// dashboard-v2's Monitored Talkgroups module can reuse the exact same
// function without pulling in buttons.php's own POST-handling and HTML
// output (that file is a page template, not a pure logic include, so
// require()ing it directly would echo its markup into a JSON response).
//
// RF.Guru's own DTMF relay (hotspot_dtmf -> nc -lk 10000 -> svxlink's
// stdin) occasionally drops the first character(s) of a command after the
// connection has sat idle -- confirmed live on svxlinkuhf: SvxLink's own
// log showed only the tail of a sent command (e.g. "91231#" arriving as
// just "231#"), which SvxLink then announces as "operation failed" since
// the truncated digits don't match a valid command. Roughly 1 in 10
// attempts in testing. This is a race in RF.Guru's netcat-based relay
// itself, not something fixable from here without replacing that
// mechanism -- but a resend a moment later reliably lands once the
// connection is "warmed up", confirmed by repeated testing.
//
// Only safe for TG select (jmpto/jmptoA/jmptoM): selecting the same TG
// twice is a no-op, so a blind resend is safe here. NOT a general-purpose
// DTMF sender -- an arbitrary or toggle-style command sent twice could
// have a real (and wrong) second effect.
function sendTgSelectDtmf(string $digits): void
{
    shell_exec('/usr/sbin/hotspot_dtmf ' . escapeshellarg($digits));
    usleep(300000);
    shell_exec('/usr/sbin/hotspot_dtmf ' . escapeshellarg($digits));
}
