// dashboard-v2 Reflector Activity module. Recent history (Talker start/
// stop, Node joined/left, TG selection changes) from the same
// /var/log/svxlink source api/talkgroup.php already proved out --
// answers "what just happened" as a scrollable list, distinct from
// Talkgroup's "what's happening right now" glance.
//
// Events that carry a TG number (talker_start, talker_stop, tg_selected)
// are also a switcher: click one to jump to that TG via
// window.dv2SelectTg() (js/tg-select.js), same "91<tg>#" DTMF command
// Monitored Talkgroups' own rows send -- see api/tg-select.php's header
// comment for why this endpoint accepts any digit TG, not just ones in
// the local TG Names database (a live QSO here is often a dynamic
// reflector regrouping, e.g. "TG 240216", that never gets a name). No
// "current TG" highlight here, unlike Monitored Talkgroups -- this is a
// rolling history, not a fixed list, and several rows could share the
// same TG at once, which would just look noisy.
class ReflectorActivityPanel extends HTMLElement {
  connectedCallback() {
    this.apiUrl = this.getAttribute('api') || 'api/reflector-activity.php';
    this.refreshMs = parseInt(this.getAttribute('refresh-ms'), 10) || 10000;
    this.selectingTg = null;
    this.render({ loading: true });
    this.poll();
    this._timer = setInterval(() => this.poll(), this.refreshMs);
    this.addEventListener('click', (e) => this.onClick(e));
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

  onClick(e) {
    const row = e.target.closest('.activity-row.selectable');
    if (!row) return;
    const tg = row.getAttribute('data-tg');
    if (!tg || tg === this.selectingTg) return;
    this.selectTg(tg, row);
  }

  async selectTg(tg, row) {
    this.selectingTg = tg;
    row.classList.add('selecting');
    await window.dv2SelectTg(tg);
    this.selectingTg = null;
    this.poll();
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

    // Same scrollTop-reset issue Monitored Talkgroups had -- every poll
    // rebuilds this.innerHTML wholesale, which silently resets
    // .activity-list's scroll position. Worth fixing here too now that
    // rows are clickable, not just cosmetic.
    const prevList = this.querySelector('.activity-list');
    const scrollTop = prevList ? prevList.scrollTop : 0;

    this.innerHTML =
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">Reflector Activity</div>' +
          '<span style="font-family:var(--mono); font-size:11px; color:var(--text-faint);">' + data.events.length + ' events</span>' +
        '</div>' +
        '<div class="activity-list">' + rows + '</div>' +
        '<div class="panel-foot">Click a talkgroup to switch to it &middot; parsed from /var/log/svxlink</div>' +
      '</div>';

    if (scrollTop) {
      this.querySelector('.activity-list').scrollTop = scrollTop;
    }
    window.dv2PersistResizableList(this, 'dv2ListHeight-reflector-activity');
  }

  eventRow(e) {
    const desc = this.describe(e);
    const hasTg = e.tg !== undefined && e.tg !== null;
    const rowClass = 'activity-row' + (hasTg ? ' selectable' : '');
    const tgAttr = hasTg ? ' data-tg="' + this.esc(e.tg) + '" title="Switch to TG ' + this.esc(e.tg) + '"' : '';
    return (
      '<div class="' + rowClass + '"' + tgAttr + '>' +
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
