// dashboard-v2 Reflector Activity module. Recent history (Talker start/
// stop, Node joined/left, TG selection changes) from the same
// /var/log/svxlink source api/talkgroup.php already proved out --
// answers "what just happened" as a scrollable list, distinct from
// Talkgroup's "what's happening right now" glance.
class ReflectorActivityPanel extends HTMLElement {
  connectedCallback() {
    this.apiUrl = this.getAttribute('api') || 'api/reflector-activity.php';
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
      const msg = loading ? 'Loading&hellip;' : 'Activity data unavailable';
      this.innerHTML =
        '<div class="panel"><div class="panel-head"><div class="panel-title">Reflector Activity</div></div>' +
        '<div class="panel-body"><div class="kv-row"><span class="k">' + msg + '</span></div></div></div>';
      return;
    }

    const rows = data.events.length
      ? data.events.map((e) => this.eventRow(e)).join('')
      : '<div class="kv-row" style="padding:14px 16px;"><span class="k">No recent activity</span></div>';

    this.innerHTML =
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">Reflector Activity</div>' +
          '<span style="font-family:var(--mono); font-size:11px; color:var(--text-faint);">' + data.events.length + ' events</span>' +
        '</div>' +
        '<div class="activity-list">' + rows + '</div>' +
        '<div class="panel-foot">Parsed from /var/log/svxlink</div>' +
      '</div>';
  }

  eventRow(e) {
    const desc = this.describe(e);
    return (
      '<div class="activity-row">' +
        '<span class="activity-dot ' + desc.dotClass + '"></span>' +
        '<span class="activity-text">' + desc.text + '</span>' +
        '<span class="activity-ago">' + this.formatAgo(e.seconds_ago) + '</span>' +
      '</div>'
    );
  }

  describe(e) {
    switch (e.type) {
      case 'talker_start':
        return { dotClass: 'ok', text: '<b>' + this.esc(e.callsign) + '</b> started talking on TG ' + e.tg };
      case 'talker_stop':
        return { dotClass: 'warn', text: '<b>' + this.esc(e.callsign) + '</b> stopped on TG ' + e.tg };
      case 'node_joined':
        return { dotClass: 'ok', text: '<b>' + this.esc(e.callsign) + '</b> joined the reflector' };
      case 'node_left':
        return { dotClass: 'crit', text: '<b>' + this.esc(e.callsign) + '</b> left the reflector' };
      case 'tg_selected':
        return { dotClass: 'cyan', text: 'This node linked to TG ' + e.tg };
      default:
        return { dotClass: 'warn', text: 'Unknown event' };
    }
  }

  formatAgo(sec) {
    if (sec < 60) return sec + 's ago';
    const min = Math.floor(sec / 60);
    if (min < 60) return min + 'm ago';
    const hr = Math.floor(min / 60);
    return hr + 'h ' + (min % 60) + 'm ago';
  }

  esc(s) {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }
}

customElements.define('reflector-activity-panel', ReflectorActivityPanel);
