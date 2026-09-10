// dashboard-v2 Setup module -- READ-ONLY (see api/setup.php's own
// comment for why). Shows the same fields production's Setup page
// manages, but nothing here writes anywhere; there is no form, no Save
// button, just a "read-only" chip so it's never mistaken for the real
// editable page. Editing config stays on the production Setup page
// (/setup/) until a full read+write version of this module is built and
// verified with its own care, per docs/dashboard-v2-brief.md.
class SetupPanel extends HTMLElement {
  connectedCallback() {
    this.apiUrl = this.getAttribute('api') || 'api/setup.php';
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
      const msg = loading ? 'Loading&hellip;' : 'Setup data unavailable';
      this.innerHTML =
        '<div class="panel"><div class="panel-head"><div class="panel-title">Setup</div></div>' +
        '<div class="panel-body"><div class="kv-row"><span class="k">' + msg + '</span></div></div></div>';
      return;
    }

    this.innerHTML =
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">Setup</div>' +
          '<span class="status-chip" style="padding:3px 9px;"><span class="dot warn"></span>read-only</span>' +
        '</div>' +
        '<div class="panel-body"><div class="kv">' +
          this.kvRow('Callsign', data.callsign || '&mdash;') +
          this.kvRow('Reflector', data.domain) +
          this.kvRow('Cert email', data.cert_email || '&mdash;') +
          '<div class="kv-divider"></div>' +
          this.kvRow('Frequency', data.freq ? Number(data.freq).toFixed(4) + ' MHz' : '&mdash;') +
          this.kvRow('TX power', data.tx_power ? data.tx_power + ' W' : '&mdash;') +
          this.kvRow('Locator', data.gridsquare || '&mdash;') +
          this.kvRow('QTH', data.qth_name || '&mdash;') +
          this.kvRow('Location', data.location || '&mdash;') +
          '<div class="kv-divider"></div>' +
          this.kvRow('Sysop', data.sysop || '&mdash;') +
          this.kvRow('Language', data.language_label) +
          this.kvRow('Node class', data.node_class) +
          this.kvRow('Hidden from list', data.hidden ? 'yes' : 'no') +
        '</div></div>' +
        '<div class="panel-foot">Edit these on the production <a href="http://' + window.location.hostname + '/setup/" style="color:var(--copper);">Setup page</a> -- this view is read-only.</div>' +
      '</div>';
  }

  kvRow(label, value) {
    return '<div class="kv-row"><span class="k">' + label + '</span><span class="v">' + value + '</span></div>';
  }
}

customElements.define('setup-panel', SetupPanel);
