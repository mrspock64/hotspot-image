// dashboard-v2 Power Controls module -- the full production Power page
// (dashboard/power/index.php), reused via api/power.php's thin wrappers
// around perf_mode.php/inisync.php. No polling, like QSO Log Settings --
// this is mostly a form, and a stopped/restarted service or a device
// that's about to reboot isn't something worth refetching every few
// seconds either.
//
// Confirmation dialogs: only Restart Device and Power OFF get one,
// matching production's own dashboard/power/index.php exactly (the only
// two confirm() calls there) -- service start/stop/restart, perf mode,
// and the temperature settings save fire immediately, same as
// production. Power OFF genuinely needs physical access to undo, which
// is why it's the one action in the whole of dashboard-v2 that gets this
// treatment.
class PowerPanel extends HTMLElement {
  connectedCallback() {
    this.apiUrl = this.getAttribute('api') || 'api/power.php';
    this.render({ loading: true });
    this.load();
  }

  async load() {
    try {
      const res = await fetch(this.apiUrl, { cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      this.render({ data: await res.json() });
    } catch (e) {
      this.render({ error: true });
    }
  }

  render({ loading, error, data }) {
    if (loading || error || !data) {
      const msg = loading ? 'Loading&hellip;' : 'Power controls unavailable';
      this.innerHTML =
        '<div class="panel"><div class="panel-head"><div class="panel-title">Power Controls</div></div>' +
        '<div class="panel-body"><div class="kv-row"><span class="k">' + msg + '</span></div></div></div>';
      return;
    }

    this.data = data;
    const chip = data.svxlink_active
      ? '<span class="status-chip" style="padding:3px 9px;"><span class="dot ok"></span>running</span>'
      : '<span class="status-chip" style="padding:3px 9px;"><span class="dot crit"></span>stopped</span>';

    // Matches production's own button set: Start when stopped, Restart +
    // Stop when running -- not all three at once.
    const svcButtons = data.svxlink_active
      ? '<button type="button" class="btn" id="pwr-restart-svx">Restart SvxLink</button>' +
        '<button type="button" class="btn" id="pwr-stop-svx" style="border-color:var(--crit); color:var(--crit);">Stop SvxLink</button>'
      : '<button type="button" class="btn" id="pwr-start-svx">Start SvxLink</button>';

    this.innerHTML =
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">Power Controls</div>' + chip + '</div>' +
        '<div class="panel-body" style="display:flex; flex-direction:column; gap:16px;">' +
          '<div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">' +
            svcButtons +
            '<span id="pwr-svc-msg" style="font-family:var(--mono); font-size:11px; color:var(--text-faint);"></span>' +
          '</div>' +

          '<div style="border-top:1px solid var(--border-soft); padding-top:14px;">' +
            '<div style="font-family:var(--disp); font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--text-faint); margin-bottom:8px;">Performance mode</div>' +
            '<div style="font-size:13px; margin-bottom:8px;">Current: <b style="color:' + (data.perf_mode === 'guru' ? 'var(--warn)' : 'var(--ok)') + ';">' + (data.perf_mode === 'guru' ? 'Guru' : 'Turbo') + '</b></div>' +
            '<div style="display:flex; align-items:center; gap:12px;">' +
              '<button type="button" class="btn" id="pwr-perf-toggle">' + (data.perf_mode === 'guru' ? 'Switch to Turbo mode' : 'Switch to Guru mode') + '</button>' +
              '<span id="pwr-perf-msg" style="font-family:var(--mono); font-size:11px; color:var(--text-faint);"></span>' +
            '</div>' +
            '<p style="font-family:var(--mono); font-size:11px; color:var(--text-faint); margin:8px 0 0;">Turbo: all real cores, full clock speed. Guru: RF.Guru&#39;s stock throttled tuning. Needs a device restart to take effect.</p>' +
          '</div>' +

          '<div style="border-top:1px solid var(--border-soft); padding-top:14px;">' +
            '<div style="font-family:var(--disp); font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--text-faint); margin-bottom:8px;">High temperature protection</div>' +
            '<div style="font-size:13px; margin-bottom:10px;">Current: <b>' + (data.temp_c !== null ? data.temp_c + '&deg;C' : '&mdash;') + '</b></div>' +
            '<div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">' +
              '<label for="pwr-threshold" style="font-size:12.5px;">Stop SvxLink above</label>' +
              '<input type="text" id="pwr-threshold" value="' + this.esc(String(data.temp_threshold)) + '" style="width:60px;">' +
              '<span style="font-size:12.5px; color:var(--text-dim);">&deg;C</span>' +
            '</div>' +
            '<label style="display:flex; align-items:center; gap:8px; font-size:13px; margin-bottom:8px;">' +
              '<input type="checkbox" id="pwr-auto-stop"' + (data.auto_stop ? ' checked' : '') + '>' +
              'Automatically stop SvxLink if it stays that hot' +
            '</label>' +
            '<label style="display:flex; align-items:center; gap:8px; font-size:13px; margin-bottom:10px;">' +
              '<input type="checkbox" id="pwr-auto-alert"' + (data.auto_alert ? ' checked' : '') + '>' +
              'Transmit alert message once if it stays that hot' +
            '</label>' +
            '<p style="font-family:var(--mono); font-size:11px; color:var(--text-faint); margin:0 0 10px;">Checked every ~30s; needs ~2 minutes sustained above the threshold before acting. The alert fires before auto-stop, so it still goes out if both are on.</p>' +
            '<div style="display:flex; align-items:center; gap:12px;">' +
              '<button type="button" class="btn" id="pwr-temp-save">Save temperature settings</button>' +
              '<span id="pwr-temp-msg" style="font-family:var(--mono); font-size:11px; color:var(--text-faint);"></span>' +
            '</div>' +
          '</div>' +

          '<div style="border-top:1px solid var(--border-soft); padding-top:14px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">' +
            '<button type="button" class="btn" id="pwr-restart-device" style="border-color:var(--crit); color:var(--crit);">Restart Device</button>' +
            '<button type="button" class="btn" id="pwr-poweroff" style="border-color:var(--crit); color:var(--crit);">Power OFF</button>' +
            '<span id="pwr-danger-msg" style="font-family:var(--mono); font-size:11px; color:var(--warn);"></span>' +
          '</div>' +
        '</div>' +
      '</div>';

    const restartSvx = this.querySelector('#pwr-restart-svx');
    if (restartSvx) restartSvx.addEventListener('click', () => this.service('restart'));
    const stopSvx = this.querySelector('#pwr-stop-svx');
    if (stopSvx) stopSvx.addEventListener('click', () => this.service('stop'));
    const startSvx = this.querySelector('#pwr-start-svx');
    if (startSvx) startSvx.addEventListener('click', () => this.service('start'));

    this.querySelector('#pwr-perf-toggle').addEventListener('click', () => this.togglePerf());
    this.querySelector('#pwr-temp-save').addEventListener('click', () => this.saveTemp());
    this.querySelector('#pwr-restart-device').addEventListener('click', () => this.restartDevice());
    this.querySelector('#pwr-poweroff').addEventListener('click', () => this.poweroff());
  }

  async service(op) {
    const msg = this.querySelector('#pwr-svc-msg');
    msg.textContent = op.charAt(0).toUpperCase() + op.slice(1) + 'ing&hellip;';
    msg.style.color = 'var(--text-faint)';
    try {
      const res = await fetch(this.apiUrl + '?action=service&op=' + encodeURIComponent(op), { cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      msg.textContent = 'Requested.';
      msg.style.color = 'var(--ok)';
      // Backgrounded server-side -- give it a moment before refetching,
      // same reasoning as the QSO Log Settings save-then-restart flow.
      setTimeout(() => this.load(), 3000);
    } catch (e) {
      msg.textContent = 'Failed';
      msg.style.color = 'var(--crit)';
    }
  }

  async togglePerf() {
    const msg = this.querySelector('#pwr-perf-msg');
    const newMode = this.data.perf_mode === 'guru' ? 'turbo' : 'guru';
    msg.textContent = 'Saving&hellip;';
    msg.style.color = 'var(--text-faint)';
    try {
      const res = await fetch(this.apiUrl + '?action=perf&mode=' + newMode, { cache: 'no-store' });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'HTTP ' + res.status);
      this.data.perf_mode = newMode;
      msg.textContent = 'Saved. Restart the device for it to take effect.';
      msg.style.color = 'var(--ok)';
    } catch (e) {
      msg.textContent = e.message || 'Failed';
      msg.style.color = 'var(--crit)';
    }
  }

  async saveTemp() {
    const msg = this.querySelector('#pwr-temp-msg');
    const threshold = this.querySelector('#pwr-threshold').value.trim();
    const autoStop = this.querySelector('#pwr-auto-stop').checked;
    const autoAlert = this.querySelector('#pwr-auto-alert').checked;
    msg.textContent = 'Saving&hellip;';
    msg.style.color = 'var(--text-faint)';
    try {
      const res = await fetch(this.apiUrl + '?action=save_temp', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ threshold, auto_stop: autoStop, auto_alert: autoAlert }),
        cache: 'no-store',
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'HTTP ' + res.status);
      msg.textContent = 'Saved.';
      msg.style.color = 'var(--ok)';
    } catch (e) {
      msg.textContent = e.message || 'Failed';
      msg.style.color = 'var(--crit)';
    }
  }

  async restartDevice() {
    if (!window.confirm('Restart the device now?')) return;
    try {
      await fetch(this.apiUrl + '?action=restart_device', { cache: 'no-store' });
    } catch (e) {
      // The device may already be going down by the time this would
      // resolve either way -- nothing useful to do with a failed fetch
      // here.
    }
    const msg = this.querySelector('#pwr-danger-msg');
    if (msg) msg.textContent = 'Restarting -- this page will go unreachable for a bit.';
  }

  async poweroff() {
    if (!window.confirm('Power off the device now? You will need physical access to turn it back on.')) return;
    try {
      await fetch(this.apiUrl + '?action=poweroff', { cache: 'no-store' });
    } catch (e) {
      // Same reasoning as restartDevice().
    }
    const msg = this.querySelector('#pwr-danger-msg');
    if (msg) {
      msg.textContent = 'Powering off -- you will need physical access to turn it back on.';
      msg.style.color = 'var(--crit)';
    }
  }

  esc(s) {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }
}

customElements.define('power-panel', PowerPanel);
