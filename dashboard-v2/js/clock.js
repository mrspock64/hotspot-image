// Shared topbar clock -- two real zones (browser-local, UTC), one shown
// large ("primary"), one small ("secondary"). Extracted out of each
// page's own inline <script> (was byte-identical across all four pages)
// so the primary-clock preference only has to be implemented once.
//
// Preference is a plain localStorage flag (dv2PrimaryClock: 'local' or
// 'utc', default 'local' -- most people read their own wall clock more
// than UTC day to day), same per-browser pattern as the sidebar's
// collapse state and the RX/TX indicator's on/off switch. Settings-page
// control: settings/index.html.
(function () {
  const STORAGE_KEY = 'dv2PrimaryClock';

  const primaryZone = document.getElementById('clock-primary-zone');
  const primaryTime = document.getElementById('clock-primary-time');
  const secondaryZone = document.getElementById('clock-secondary-zone');
  const secondaryTime = document.getElementById('clock-secondary-time');
  if (!primaryZone || !primaryTime || !secondaryZone || !secondaryTime) return;

  function pad(n) {
    return String(n).padStart(2, '0');
  }

  function primaryIsUtc() {
    try {
      return localStorage.getItem(STORAGE_KEY) === 'utc';
    } catch (e) {
      return false;
    }
  }

  function applyLabels() {
    const utcFirst = primaryIsUtc();
    primaryZone.textContent = utcFirst ? 'UTC' : 'LOCAL';
    secondaryZone.textContent = utcFirst ? 'local' : 'utc';
  }

  function tick() {
    const now = new Date();
    const utc = pad(now.getUTCHours()) + ':' + pad(now.getUTCMinutes()) + ':' + pad(now.getUTCSeconds());
    const local = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
    const utcFirst = primaryIsUtc();
    primaryTime.textContent = utcFirst ? utc : local;
    secondaryTime.textContent = utcFirst ? local : utc;
  }

  applyLabels();
  tick();
  setInterval(tick, 1000);

  // Flipping the Settings-page control in another tab picks up live here
  // too, same as the RX/TX indicator's own storage listener.
  window.addEventListener('storage', (e) => {
    if (e.key === STORAGE_KEY) applyLabels();
  });
})();
