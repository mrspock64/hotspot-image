// dashboard-v2 Radio Status module. TX/RX/idle state of the radio
// itself (SvxLink's own "Tx1: Turning the transmitter ON/OFF" and "Rx1:
// The squelch is OPEN/CLOSED" log lines -- see api/radio-status.php's
// header comment), not the reflector network -- that's Talkgroup/
// Reflector Activity. Polled every 2s (its own manifest's refresh_ms):
// TX/RX transitions happen on the order of seconds, and the underlying
// tail+regex scan is cheap, same cost class as the other log-based
// modules.
class RadioStatusPanel extends HTMLElement {
  connectedCallback() {
    this.apiUrl = this.getAttribute('api') || 'api/radio-status.php';
    this.refreshMs = parseInt(this.getAttribute('refresh-ms'), 10) || 2000;
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
      const msg = loading ? 'Loading&hellip;' : 'Radio status unavailable';
      this.innerHTML =
        '<div class="panel"><div class="panel-head"><div class="panel-title">Radio Status</div></div>' +
        '<div class="panel-body"><div class="kv-row"><span class="k">' + msg + '</span></div></div></div>';
      return;
    }

    const stateInfo = {
      tx: { label: 'TRANSMITTING', color: 'var(--crit)', glow: 'rgba(242,102,78,0.5)' },
      rx: { label: 'RECEIVING', color: 'var(--ok)', glow: 'rgba(94,214,140,0.5)' },
      idle: { label: 'LISTENING', color: 'var(--cyan)', glow: 'rgba(77,216,224,0.35)' },
    }[data.state] || { label: 'UNKNOWN', color: 'var(--text-faint)', glow: 'transparent' };

    const rxSignal = data.rx && data.rx.signal ? ' <span style="color:var(--text-faint); font-size:11px;">(' + this.esc(data.rx.signal) + ')</span>' : '';

    this.innerHTML =
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">Radio Status</div></div>' +
        '<div class="panel-body" style="text-align:center; padding-top:20px; padding-bottom:20px;">' +
          '<div style="width:72px; height:72px; margin:0 auto 12px; border-radius:50%; background:' + stateInfo.color + '; ' +
            'box-shadow: 0 0 0 8px ' + stateInfo.glow + ', 0 0 30px 4px ' + stateInfo.glow + ';"></div>' +
          '<div style="font-family:var(--disp); font-weight:700; font-size:20px; letter-spacing:1.5px; color:' + stateInfo.color + ';">' + stateInfo.label + '</div>' +
        '</div>' +
        '<div class="panel-body" style="padding-top:0;"><div class="kv">' +
          this.kvRow('TX', data.tx ? (data.tx.on ? 'keyed up' : 'off &middot; ' + this.formatAgo(data.tx.seconds_ago) + ' since last') : '&mdash;') +
          this.kvRow('Squelch', data.rx ? (data.rx.open ? 'open' + rxSignal : 'closed &middot; ' + this.formatAgo(data.rx.seconds_ago) + ' since last') : '&mdash;') +
        '</div></div>' +
        '<div class="panel-foot">Parsed from /var/log/svxlink (Tx1/Rx1)</div>' +
      '</div>';
  }

  kvRow(label, value) {
    return '<div class="kv-row"><span class="k">' + label + '</span><span class="v">' + value + '</span></div>';
  }

  formatAgo(sec) {
    if (sec < 60) return sec + 's';
    const min = Math.floor(sec / 60);
    if (min < 60) return min + 'm';
    return Math.floor(min / 60) + 'h ' + (min % 60) + 'm';
  }

  esc(s) {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }
}

customElements.define('radio-status-panel', RadioStatusPanel);
