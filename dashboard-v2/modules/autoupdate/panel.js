// dashboard-v2 Auto-update module. Mirrors production's own two update
// surfaces (dashboard/update/index.php's "Last update applied" line and
// site_header.php's pulsing "Updated" badge) as one compact panel, backed
// by api/autoupdate.php -- pure reuse of getAutoUpdateDashboard()/
// isDashboardUpdateAvailable()/last_dashboard_update.json, no new state.
class AutoupdatePanel extends HTMLElement {
  connectedCallback() {
    this.apiUrl = this.getAttribute('api') || 'api/autoupdate.php';
    this.refreshMs = parseInt(this.getAttribute('refresh-ms'), 10) || 60000;
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
      const msg = loading ? 'Loading&hellip;' : 'Auto-update status unavailable';
      this.innerHTML =
        '<div class="panel"><div class="panel-head"><div class="panel-title">Auto-update</div></div>' +
        '<div class="panel-body"><div class="kv-row"><span class="k">' + msg + '</span></div></div></div>';
      return;
    }

    const enabledChip = data.enabled
      ? '<span class="status-chip" style="padding:3px 9px;"><span class="dot ok"></span>hourly, on</span>'
      : '<span class="status-chip" style="padding:3px 9px;"><span class="dot warn"></span>off</span>';

    let lastRow;
    if (data.last_update) {
      lastRow = this.kvRow('Last applied', this.relativeTime(data.last_update.timestamp) +
        ' <span style="color:var(--text-faint);">(' + data.last_update.from + '&rarr;' + data.last_update.to + ')</span>');
    } else {
      lastRow = this.kvRow('Last applied', '&mdash;');
    }

    const availRow = data.update_available
      ? this.kvRow('Pending', 'update available', 'color:var(--copper);')
      : this.kvRow('Pending', 'up to date', 'color:var(--ok);');

    this.innerHTML =
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">Auto-update</div>' + enabledChip + '</div>' +
        '<div class="panel-body"><div class="kv">' + lastRow + availRow + '</div></div>' +
      '</div>';
  }

  kvRow(label, value, valueStyle) {
    return '<div class="kv-row"><span class="k">' + label + '</span>' +
      '<span class="v"' + (valueStyle ? ' style="' + valueStyle + '"' : '') + '>' + value + '</span></div>';
  }

  // Same "just now / N minute(s)/hour(s)/day(s) ago" scheme production's
  // Update page uses for this exact field -- kept in step deliberately,
  // not reinvented, since it's the same underlying timestamp.
  relativeTime(iso) {
    const then = new Date(iso).getTime();
    if (Number.isNaN(then)) return '&mdash;';
    const diffSec = Math.max(0, Math.floor((Date.now() - then) / 1000));
    if (diffSec < 60) return 'just now';
    const min = Math.floor(diffSec / 60);
    if (min < 60) return min + ' minute' + (min === 1 ? '' : 's') + ' ago';
    const hr = Math.floor(min / 60);
    if (hr < 24) return hr + ' hour' + (hr === 1 ? '' : 's') + ' ago';
    const day = Math.floor(hr / 24);
    return day + ' day' + (day === 1 ? '' : 's') + ' ago';
  }
}

customElements.define('autoupdate-panel', AutoupdatePanel);
