// dashboard-v2 Power status module -- compact, read-only glance for the
// main Dashboard grid: SvxLink running/stopped, temperature, current
// performance mode. No controls here on purpose -- the actual Power
// page (module "power") has the full control surface, including two
// genuinely irreversible actions (Restart Device, Power OFF); this card
// exists so you don't have to leave the dashboard just to check whether
// the radio is up. Shares api/power.php's default (no-action) response
// with the Power module -- same data, two different views of it.
class PowerStatusPanel extends HTMLElement {
  connectedCallback() {
    this.apiUrl = this.getAttribute('api') || 'api/power.php';
    this.refreshMs = parseInt(this.getAttribute('refresh-ms'), 10) || 10000;
    this.render({ loading: true });
    this.poll();
    this._timer = setInterval(() => this.poll(), this.refreshMs);
  }

  disconnectedCallback() {
    clearInterval(this._timer);
  }

  async poll() {
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
      const msg = loading ? 'Loading&hellip;' : 'Power status unavailable';
      this.innerHTML =
        '<div class="panel"><div class="panel-head"><div class="panel-title">Power</div></div>' +
        '<div class="panel-body"><div class="kv-row"><span class="k">' + msg + '</span></div></div></div>';
      return;
    }

    const chip = data.svxlink_active
      ? '<span class="status-chip" style="padding:3px 9px;"><span class="dot ok"></span>running</span>'
      : '<span class="status-chip" style="padding:3px 9px;"><span class="dot crit"></span>stopped</span>';

    let tempColor = 'var(--text-faint)';
    if (data.temp_c !== null) {
      if (data.temp_c >= data.temp_threshold) tempColor = 'var(--crit)';
      else if (data.temp_c >= data.temp_threshold - 10) tempColor = 'var(--warn)';
      else tempColor = 'var(--ok)';
    }

    this.innerHTML =
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">Power</div>' + chip + '</div>' +
        '<div class="panel-body"><div class="kv">' +
          this.kvRow('SvxLink', data.svxlink_active ? 'running' : 'stopped') +
          this.kvRow('Temperature', data.temp_c !== null ? '<span style="color:' + tempColor + ';">' + data.temp_c + '&deg;C</span>' : '&mdash;') +
          this.kvRow('Perf mode', data.perf_mode === 'guru' ? 'Guru' : 'Turbo') +
        '</div></div>' +
        '<div class="panel-foot">Full controls on the Power page</div>' +
      '</div>';
  }

  kvRow(label, value) {
    return '<div class="kv-row"><span class="k">' + label + '</span><span class="v">' + value + '</span></div>';
  }
}

customElements.define('power-status-panel', PowerStatusPanel);
