// Topbar QSO Log recording indicator -- click to toggle armed on/off,
// present on every page (not just /qsolog/'s own modules). Two data
// sources, both already-existing endpoints, no new backend: api/
// qsolog.php's in_progress (a recording file is actually open right
// now) and api/qsolog-settings.php's active (the persisted on/off
// switch -- same one QSO Log Settings' own "Record every transmission"
// checkbox edits).
//
// Toggling calls api/qsolog-settings.php's ?action=toggle, which flips
// just 'active' server-side and never touches disk limit/QSO gap/max
// recordings/TG filter -- see that endpoint's own comment for why this
// chip specifically must not need to know or send them (it only ever
// has room to show a dot and a short label, not the whole settings
// form).
(function () {
  const POLL_MS = 5000;

  const clockBlock = document.querySelector('.clock-block');
  if (!clockBlock) return;

  const chip = document.createElement('span');
  chip.className = 'status-chip';
  chip.id = 'qsolog-indicator';
  chip.style.padding = '5px 12px';
  chip.style.cursor = 'pointer';
  chip.title = 'Click to toggle QSO Log recording';
  chip.innerHTML = '<span class="dot"></span><span id="qsolog-indicator-label">QSO log&hellip;</span>';
  clockBlock.parentNode.insertBefore(chip, clockBlock);

  const dot = chip.querySelector('.dot');
  const label = chip.querySelector('#qsolog-indicator-label');
  let toggling = false;

  function setState(state) {
    // state: 'off' | 'armed' | 'recording'
    dot.className = 'dot' + (state === 'recording' ? ' crit' : state === 'armed' ? ' warn' : '');
    label.textContent = state === 'recording' ? 'QSO log — recording' : state === 'armed' ? 'QSO log — armed' : 'QSO log — off';
  }

  async function poll() {
    try {
      const [settingsRes, logRes] = await Promise.all([
        fetch('/api/qsolog-settings.php', { cache: 'no-store' }),
        fetch('/api/qsolog.php', { cache: 'no-store' }),
      ]);
      const settings = await settingsRes.json();
      const log = await logRes.json();
      setState(log.in_progress ? 'recording' : settings.active ? 'armed' : 'off');
    } catch (e) {
      // Leave the last known state showing rather than flicker on a
      // single missed poll.
    }
  }

  chip.addEventListener('click', async () => {
    if (toggling) return;
    toggling = true;
    chip.style.opacity = '0.6';
    try {
      await fetch('/api/qsolog-settings.php?action=toggle', { cache: 'no-store' });
    } catch (e) {
      // Fall through to the next poll either way -- it reflects
      // whatever the config actually holds now, not this request's own
      // success.
    }
    chip.style.opacity = '';
    toggling = false;
    poll();
  });

  poll();
  setInterval(poll, POLL_MS);
})();
