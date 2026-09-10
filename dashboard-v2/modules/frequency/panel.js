// dashboard-v2 Frequency module. The mockup's big frequency readout,
// shrunk to fit the module grid's column width -- api/frequency.php's own
// comment explains why there's no live talkgroup-in-use field here yet.
class FrequencyPanel extends HTMLElement {
  connectedCallback() {
    this.apiUrl = this.getAttribute('api') || 'api/frequency.php';
    this.refreshMs = parseInt(this.getAttribute('refresh-ms'), 10) || 30000;
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
      const msg = loading ? 'Loading&hellip;' : 'Frequency data unavailable';
      this.innerHTML =
        '<div class="panel"><div class="panel-head"><div class="panel-title">Frequency</div></div>' +
        '<div class="panel-body"><div class="kv-row"><span class="k">' + msg + '</span></div></div></div>';
      return;
    }

    const freq = data.freq_mhz ? data.freq_mhz + '<span class="freq-unit">MHz</span>' : '&mdash;';
    const statusChip = data.svxlink_active
      ? '<span class="status-chip" style="padding:3px 9px;"><span class="dot ok"></span>on air</span>'
      : '<span class="status-chip" style="padding:3px 9px;"><span class="dot warn"></span>stopped</span>';

    this.innerHTML =
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">Frequency</div>' + statusChip + '</div>' +
        '<div class="panel-body">' +
          '<div class="freq-readout">' + freq + '</div>' +
          '<div class="kv">' +
            this.kvRow('Callsign', data.callsign) +
            this.kvRow('Network', data.network || '&mdash;') +
            this.kvRow('Mode', data.mode) +
          '</div>' +
        '</div>' +
      '</div>';
  }

  kvRow(label, value, valueStyle) {
    return '<div class="kv-row"><span class="k">' + label + '</span>' +
      '<span class="v"' + (valueStyle ? ' style="' + valueStyle + '"' : '') + '>' + value + '</span></div>';
  }
}

customElements.define('frequency-panel', FrequencyPanel);
