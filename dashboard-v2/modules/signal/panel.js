// dashboard-v2 Signal module panel. Light-DOM custom element (no Shadow
// DOM) -- css/tokens.css's .panel/.signal-card classes are shared, global
// styling straight from docs/dashboard-redesign-concept.html, and every
// module panel is meant to look the same way that mockup does without each
// one re-declaring its own copy of those rules.
class SignalPanel extends HTMLElement {
  connectedCallback() {
    this.apiUrl = this.getAttribute('api') || 'api/signal.php';
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
    if (loading) {
      this.innerHTML = this.panelShell('<div class="caption">Loading&hellip;</div>');
      return;
    }
    if (error || !data || data.error) {
      this.innerHTML = this.panelShell(
        '<div class="caption">' + (data && data.error ? data.error : 'Signal data unavailable') + '</div>'
      );
      return;
    }

    const dbmClass = data.dbm >= -70 ? 'ok' : (data.dbm >= -80 ? 'warn' : 'crit');
    const history = Array.isArray(data.history) ? data.history : [];
    const delta = history.length >= 2 ? data.dbm - history[0].dbm : null;
    const deltaHtml = delta === null ? '' :
      '<span class="delta" style="color:var(--' + (delta >= 0 ? 'ok' : 'crit') + ');">' +
      (delta >= 0 ? '&uarr;' : '&darr;') + ' ' + Math.abs(delta) + '&nbsp;dB over ' + this.windowLabel(history) + '</span>';

    this.innerHTML = this.panelShell(
      '<div class="headline">' +
        '<span class="big" style="color:var(--' + dbmClass + ');">' + data.dbm + '&nbsp;dBm</span>' +
        deltaHtml +
      '</div>' +
      '<div class="caption">Link quality ' + data.quality + '/' + data.quality_max +
        ' &middot; packet loss ' + data.loss_pct + '% (last ' + data.loss_probes + ' probes)</div>' +
      this.sparkline(history, dbmClass) +
      '<div class="panel-foot">' + data.iface + ' &middot; updated ' +
        new Date(data.updated_at).toLocaleTimeString() +
        ' &middot; measured via <span style="color:var(--text-dim);">/proc/net/wireless</span></div>'
    );
  }

  windowLabel(history) {
    const spanSec = history[history.length - 1].t - history[0].t;
    if (spanSec >= 3600) return (spanSec / 3600).toFixed(1) + 'h';
    return Math.round(spanSec / 60) + 'm';
  }

  panelShell(bodyHtml) {
    return (
      '<div class="panel signal-card">' +
        '<div class="panel-head"><div class="panel-title">WiFi link quality</div></div>' +
        '<div class="panel-body">' + bodyHtml + '</div>' +
      '</div>'
    );
  }

  // Same path-building idea as the mockup's static SVG (docs/dashboard-
  // redesign-concept.html), just computed from real samples instead of
  // hand-drawn points -- dBm mapped to the 0-64 viewBox height, oldest
  // sample at x=0.
  sparkline(history, colorClass) {
    if (history.length < 2) {
      return '<svg class="spark" viewBox="0 0 560 64" preserveAspectRatio="none"></svg>';
    }
    const w = 560, h = 64;
    const dbms = history.map((p) => p.dbm);
    const min = Math.min(...dbms), max = Math.max(...dbms);
    const range = Math.max(1, max - min); // avoid div/0 on a dead-flat signal
    const t0 = history[0].t, t1 = history[history.length - 1].t;
    const tSpan = Math.max(1, t1 - t0);
    const points = history.map((p) => {
      const x = ((p.t - t0) / tSpan) * w;
      // Inverted: higher (less negative) dBm draws higher on the chart.
      const y = h - 4 - ((p.dbm - min) / range) * (h - 8);
      return [x, y];
    });
    const line = points.map(([x, y], i) => (i === 0 ? 'M' : 'L') + x.toFixed(1) + ',' + y.toFixed(1)).join(' ');
    const fill = line + ' L' + w + ',' + h + ' L0,' + h + ' Z';
    const [lastX, lastY] = points[points.length - 1];
    const gradId = 'sigfill-' + Math.random().toString(36).slice(2, 8);
    return (
      '<svg class="spark" viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none">' +
        '<defs><linearGradient id="' + gradId + '" x1="0" y1="0" x2="0" y2="1">' +
          '<stop offset="0%" stop-color="var(--' + colorClass + ')" stop-opacity="0.35"/>' +
          '<stop offset="100%" stop-color="var(--' + colorClass + ')" stop-opacity="0"/>' +
        '</linearGradient></defs>' +
        '<path d="' + fill + '" fill="url(#' + gradId + ')"/>' +
        '<path d="' + line + '" fill="none" stroke="var(--' + colorClass + ')" stroke-width="2"/>' +
        '<circle cx="' + lastX.toFixed(1) + '" cy="' + lastY.toFixed(1) + '" r="3.5" fill="var(--' + colorClass + ')"/>' +
      '</svg>'
    );
  }
}

customElements.define('signal-panel', SignalPanel);
