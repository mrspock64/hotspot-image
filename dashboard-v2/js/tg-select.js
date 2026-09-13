// Shared client-side helper for triggering a TG switch -- any module
// with a "jump to this TG" affordance (Monitored Talkgroups, Reflector
// Activity, ...) calls this instead of hand-rolling its own fetch to
// api/tg-select.php. See that file's own header comment for the
// server-side validation story (deliberately just "digits only", not
// "must be a named TG" -- Reflector Activity's rows are often dynamic
// reflector regroupings that never get a name).
window.dv2SelectTg = async function (tg) {
  try {
    await fetch('/api/tg-select.php?tg=' + encodeURIComponent(tg), { cache: 'no-store' });
  } catch (e) {
    // Caller re-polls its own data regardless -- this just fires the
    // DTMF command, the result is whatever SvxLink's log ends up saying.
  }
};
