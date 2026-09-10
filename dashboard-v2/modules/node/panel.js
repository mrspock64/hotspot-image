// dashboard-v2 Node/vitals module. Renders the mockup's two stacked
// panels (Node info + System vitals, docs/dashboard-redesign-concept.html
// col 1) as one custom element/module, since api/node.php already merges
// both into a single response -- splitting them into two elements would
// just mean fetching (and polling) the same endpoint twice.
class NodePanel extends HTMLElement {
  connectedCallback() {
    this.apiUrl = this.getAttribute('api') || 'api/node.php';
    this.refreshMs = parseInt(this.getAttribute('refresh-ms'), 10) || 15000;
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
      const msg = loading ? 'Loading&hellip;' : 'Node data unavailable';
      this.innerHTML =
        '<div class="panel"><div class="panel-head"><div class="panel-title">Node</div></div>' +
        '<div class="panel-body"><div class="kv-row"><span class="k">' + msg + '</span></div></div></div>';
      return;
    }

    const tempClass = data.temp_c === null ? '' : (data.temp_c >= 70 ? 'crit' : (data.temp_c >= 55 ? 'warn' : 'ok'));
    const tempColor = tempClass ? 'var(--' + (tempClass === 'ok' ? 'text' : tempClass) + ')' : 'var(--text)';
    const cpuModeLabel = data.perf_mode === 'guru' ? 'Guru' : 'Turbo';
    const clockLabel = (data.cpu_cores && data.cpu_freq_mhz)
      ? cpuModeLabel + ' &middot; ' + data.cpu_cores + '&times;' + data.cpu_freq_mhz + 'MHz'
      : cpuModeLabel;

    this.innerHTML =
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">Node</div>' +
          '<span class="status-chip" style="padding:3px 9px;"><span class="dot ok"></span>up ' + (data.uptime_html || '&mdash;') + '</span>' +
        '</div>' +
        '<div class="panel-body"><div class="kv">' +
          this.kvRow('Hardware', data.hardware) +
          this.kvRow('Firmware', 'hotspot-image ' + data.firmware) +
          this.kvRow('Locator', data.locator || '&mdash;') +
          '<div class="kv-divider"></div>' +
          this.kvRow('CPU mode', clockLabel, 'color:var(--copper);') +
          this.kvRow('Temp', data.temp_c === null ? '&mdash;' : data.temp_c + '&deg;C', 'color:' + tempColor + ';') +
          this.kvRow('Load avg', data.load_avg.map((n) => n.toFixed(2)).join('&nbsp;&nbsp;')) +
        '</div></div>' +
      '</div>' +
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">System vitals</div></div>' +
        '<div class="panel-body">' +
          this.vitalBar('CPU load', data.cpu_load_pct) +
          this.vitalBar('Memory', data.mem_used_pct) +
          this.vitalBar('I/O wait', data.iowait_pct) +
          this.vitalBar('QSO disk use', data.qso_disk_pct) +
        '</div>' +
      '</div>';
  }

  kvRow(label, value, valueStyle) {
    return '<div class="kv-row"><span class="k">' + label + '</span>' +
      '<span class="v"' + (valueStyle ? ' style="' + valueStyle + '"' : '') + '>' + value + '</span></div>';
  }

  // Same rough thresholds site_header.php's own RX meter uses (60/85) --
  // not a precise science, just "green well under half, amber getting
  // busy, red actually tight" for this hardware's own known-thin margins
  // (see lib/load-monitor/monitor.sh's incident notes).
  vitalBar(name, pct) {
    const v = Math.max(0, Math.min(100, pct ?? 0));
    const cls = v >= 90 ? 'crit' : (v >= 70 ? 'warn' : 'ok');
    return (
      '<div class="vital">' +
        '<div class="vital-top"><span class="name">' + name + '</span><span class="val">' + v + '%</span></div>' +
        '<div class="bar-track"><div class="bar-fill ' + cls + '" style="width:' + v + '%;"></div></div>' +
      '</div>'
    );
  }
}

customElements.define('node-panel', NodePanel);
