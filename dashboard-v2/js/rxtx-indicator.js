// Lightweight RX/TX pulse for the topbar, present on every page (not just
// the RX Monitor module) -- see docs/dashboard-v2-brief.md's "ambient
// waterfall in the topbar" idea. Deliberately NOT the real FFT waterfall
// (modules/rxmonitor/panel.js): no WebSocket, no audio, just 4 CSS bars
// driven by the same idle/rx/tx state api/radio-status.php already
// computes for the Radio Status module -- reused as-is, no new backend.
//
// Off switch lives on the Settings page as a plain localStorage flag
// (STORAGE_KEY below), same pattern as sidebar.js's own collapse state:
// per-browser, no server round-trip. Visibility is toggled via
// style.display, not the `hidden` attribute -- an author stylesheet rule
// that sets `display` (as .rxtx does) always wins over the UA
// stylesheet's `[hidden]{display:none}` regardless of selector
// specificity, so `el.hidden = true` alone would silently not hide it.
(function () {
  const STORAGE_KEY = 'dv2RxtxIndicator';
  const POLL_MS = 2000;

  const clockBlock = document.querySelector('.clock-block');
  if (!clockBlock) return;

  function enabled() {
    try {
      return localStorage.getItem(STORAGE_KEY) !== '0';
    } catch (e) {
      return true;
    }
  }

  const el = document.createElement('div');
  el.className = 'rxtx idle';
  el.id = 'rxtx-indicator';
  el.innerHTML =
    '<span class="bars"><span></span><span></span><span></span><span></span></span>' +
    '<span id="rxtx-label">&middot;</span>';
  clockBlock.parentNode.insertBefore(el, clockBlock);

  const label = el.querySelector('#rxtx-label');

  function applyVisibility() {
    el.style.display = enabled() ? '' : 'none';
  }

  function setState(state) {
    // "offline" (svxlink not running -- see api/radio-status.php's own
    // comment on why this check exists) reuses the dim idle look, just
    // with a different label so it doesn't read as "quiet but running".
    el.classList.remove('idle', 'rx', 'tx');
    el.classList.add(state === 'offline' ? 'idle' : state);
    label.textContent = state === 'tx' ? 'TX' : state === 'rx' ? 'RX' : state === 'offline' ? 'off' : '·';
  }

  async function poll() {
    if (!enabled()) return;
    try {
      const data = await fetch('/api/radio-status.php', { cache: 'no-store' }).then((r) => r.json());
      setState(data.state || 'idle');
    } catch (e) {
      // Leave the last known state showing rather than flicker to idle
      // on a single missed poll.
    }
  }

  applyVisibility();
  poll();
  setInterval(() => {
    applyVisibility();
    poll();
  }, POLL_MS);

  // Flipping the Settings-page switch in another tab fires a storage
  // event here -- picks it up live instead of only on this page's next
  // load.
  window.addEventListener('storage', (e) => {
    if (e.key === STORAGE_KEY) applyVisibility();
  });
})();
