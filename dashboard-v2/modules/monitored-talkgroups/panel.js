// dashboard-v2 Monitored Talkgroups module. This node's own TG "plan"
// (MONITOR_TGS in svxlink.conf, same list/priority the production Setup
// page manages), each row cross-referenced with its most recent activity
// from api/monitored-talkgroups.php's own log scan. Distinct from
// Reflector Activity (whole reflector) and Talkgroup (just the single
// most recent event anywhere) -- this is "how are the TGs I actually
// monitor doing".
//
// Rows are also the TG switcher: click one to make it this node's active
// TG (?action=select, which shells out the same "91<tg>#" DTMF command
// the production TG page's own "A" button sends -- see
// api/monitored-talkgroups.php's header comment). Replaces that page's
// separate admin table + button column entirely for this data set; no
// new visual element, the list that was already on screen just became
// actionable. The currently-active TG (selected_tg, from the same log
// scan) gets a copper left-border highlight so "where am I" reads at a
// glance instead of needing a separate status line.
class MonitoredTalkgroupsPanel extends HTMLElement {
  connectedCallback() {
    this.apiUrl = this.getAttribute('api') || 'api/monitored-talkgroups.php';
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
    try {
      await fetch(this.apiUrl + '?action=select&tg=' + encodeURIComponent(tg), { cache: 'no-store' });
    } catch (e) {
      // Fall through to the next poll either way -- it reflects whatever
      // SvxLink's log actually shows, not this request's own success.
    }
    this.selectingTg = null;
    this.poll();
  }

  render({ loading, error, data }) {
    if (loading || error || !data) {
      const msg = loading ? 'Loading&hellip;' : 'Monitored talkgroup data unavailable';
      this.innerHTML =
        '<div class="panel"><div class="panel-head"><div class="panel-title">Monitored Talkgroups</div></div>' +
        '<div class="panel-body"><div class="kv-row"><span class="k">' + msg + '</span></div></div></div>';
      return;
    }

    const rows = data.talkgroups.length
      ? data.talkgroups.map((t) => this.tgRow(t, data.selected_tg)).join('')
      : '<div class="kv-row" style="padding:14px 16px;"><span class="k">No monitored talkgroups configured</span></div>';

    this.innerHTML =
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">Monitored Talkgroups</div>' +
          '<span style="font-family:var(--mono); font-size:11px; color:var(--text-faint);">' + data.talkgroups.length + ' TGs</span>' +
        '</div>' +
        '<div class="activity-list">' + rows + '</div>' +
        '<div class="panel-foot">Click a talkgroup to switch to it &middot; MONITOR_TGS in svxlink.conf &middot; activity parsed from /var/log/svxlink</div>' +
      '</div>';
  }

  tgRow(t, selectedTg) {
    const nameLabel = t.name && t.name !== t.tg ? ' <span style="color:var(--text-faint);">(' + this.esc(t.name) + ')</span>' : '';
    const priority = t.priority > 0 ? ' <span style="color:var(--copper);">' + '+'.repeat(t.priority) + '</span>' : '';

    let activityText, dotClass, ago;
    if (t.activity) {
      dotClass = t.activity.active ? 'ok' : 'warn';
      activityText = '<b>' + this.esc(t.activity.callsign) + '</b>' + (t.activity.active ? ' talking now' : ' last heard');
      ago = this.formatAgo(t.activity.seconds_ago);
    } else {
      dotClass = 'crit';
      activityText = 'No activity seen';
      ago = '';
    }

    const isCurrent = selectedTg !== null && String(selectedTg) === String(t.tg);
    const rowClass = 'activity-row selectable' + (isCurrent ? ' current' : '');

    return (
      '<div class="' + rowClass + '" data-tg="' + this.esc(t.tg) + '" title="Switch to TG ' + this.esc(t.tg) + '">' +
        '<span class="activity-dot ' + dotClass + '"></span>' +
        '<span class="activity-text"><span class="tg-num">TG ' + this.esc(t.tg) + '</span>' + priority + nameLabel + ' &mdash; ' + activityText + '</span>' +
        '<span class="activity-ago">' + ago + '</span>' +
      '</div>'
    );
  }

  formatAgo(sec) {
    if (sec < 60) return sec + 's ago';
    const min = Math.floor(sec / 60);
    if (min < 60) return min + 'm ago';
    const hr = Math.floor(min / 60);
    if (hr < 24) return hr + 'h ' + (min % 60) + 'm ago';
    return Math.floor(hr / 24) + 'd ago';
  }

  esc(s) {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }
}

customElements.define('monitored-talkgroups-panel', MonitoredTalkgroupsPanel);
