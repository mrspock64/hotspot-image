// dashboard-v2 Monitored Talkgroups module. This node's own TG "plan"
// (MONITOR_TGS in svxlink.conf, same list/priority the production Setup
// page manages), each row cross-referenced with its most recent activity
// from api/monitored-talkgroups.php's own log scan -- plus, below a thin
// divider, every other *named* TG (production's TG Names database) that
// isn't on that plan, e.g. "0 Idle" or "91 World Wide". Confirmed live
// 2026-09-14 this was a real gap: those were only reachable from the old
// TG admin table, not from here, even though this module is otherwise
// the switcher now (see below). No activity tracking for the directory
// rows -- api/monitored-talkgroups.php's own log scan never looked for
// them -- just a name and a switch target. Distinct from Reflector
// Activity (whole reflector) and Talkgroup (just the single most recent
// event anywhere) -- this is "how are the TGs I actually monitor doing,
// plus everywhere else I could go".
//
// Rows are also the TG switcher: click one to make it this node's active
// TG (?action=select, which shells out the same "91<tg>#" DTMF command
// the production TG page's own "A" button sends -- see
// api/monitored-talkgroups.php's header comment). Replaces that page's
// separate admin table + button column entirely; no new visual element,
// the list that was already on screen just became actionable. The
// currently-active TG (selected_tg, from the same log scan) gets a
// copper left-border highlight so "where am I" reads at a glance instead
// of needing a separate status line.
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

    const monitored = data.talkgroups.filter((t) => t.monitored);
    const directory = data.talkgroups.filter((t) => !t.monitored);

    const rows =
      (monitored.length
        ? monitored.map((t) => this.tgRow(t, data.selected_tg)).join('')
        : '<div class="kv-row" style="padding:14px 16px;"><span class="k">No monitored talkgroups configured</span></div>') +
      (directory.length
        ? '<div class="kv-row" style="padding:8px 16px 6px; font-family:var(--mono); font-size:10.5px; text-transform:uppercase; letter-spacing:1px; color:var(--text-faint);">More talkgroups</div>' +
          directory.map((t) => this.tgRow(t, data.selected_tg)).join('')
        : '');

    this.innerHTML =
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">Monitored Talkgroups</div>' +
          '<span style="font-family:var(--mono); font-size:11px; color:var(--text-faint);">' + monitored.length + ' monitored, ' + directory.length + ' more</span>' +
        '</div>' +
        '<div class="activity-list">' + rows + '</div>' +
        '<div class="panel-foot">Click a talkgroup to switch to it &middot; MONITOR_TGS + TG Names in svxlink.conf &middot; activity parsed from /var/log/svxlink</div>' +
      '</div>';
  }

  tgRow(t, selectedTg) {
    const nameLabel = t.name && t.name !== t.tg ? ' <span style="color:var(--text-faint);">(' + this.esc(t.name) + ')</span>' : '';
    const priority = t.priority > 0 ? ' <span style="color:var(--copper);">' + '+'.repeat(t.priority) + '</span>' : '';

    let activityText = '', dotClass = '', ago = '';
    if (!t.monitored) {
      // Directory row: never tracked by the log scan, so "no activity"
      // would misleadingly imply something's wrong -- just a plain,
      // unlit dot and no activity line at all.
    } else if (t.activity) {
      dotClass = 'activity-dot ' + (t.activity.active ? 'ok' : 'warn');
      activityText = ' &mdash; <b>' + this.esc(t.activity.callsign) + '</b>' + (t.activity.active ? ' talking now' : ' last heard');
      ago = this.formatAgo(t.activity.seconds_ago);
    } else {
      dotClass = 'activity-dot crit';
      activityText = ' &mdash; No activity seen';
      ago = '';
    }

    const isCurrent = selectedTg !== null && String(selectedTg) === String(t.tg);
    const rowClass = 'activity-row selectable' + (isCurrent ? ' current' : '');
    const dot = t.monitored
      ? '<span class="' + dotClass + '"></span>'
      : '<span class="activity-dot" style="background:var(--border);"></span>';

    return (
      '<div class="' + rowClass + '" data-tg="' + this.esc(t.tg) + '" title="Switch to TG ' + this.esc(t.tg) + '">' +
        dot +
        '<span class="activity-text"><span class="tg-num">TG ' + this.esc(t.tg) + '</span>' + priority + nameLabel + activityText + '</span>' +
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
