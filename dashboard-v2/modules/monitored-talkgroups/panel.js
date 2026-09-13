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
// TG via window.dv2SelectTg() (js/tg-select.js), which shells out the
// same "91<tg>#" DTMF command the production TG page's own "A" button
// sends -- see api/tg-select.php's header comment. Replaces that page's
// separate admin table + button column entirely; no new visual element,
// the list that was already on screen just became actionable. The
// currently-active TG (selected_tg, from the same log scan) gets a
// copper left-border highlight so "where am I" reads at a glance instead
// of needing a separate status line.
//
// Edit mode (pencil button) replaces the production TG page's "Talk
// Groups" table (monitor checkbox + priority, one batched "Save
// monitoring & restart SvxLink" button -- api/monitored-talkgroups.php's
// ?action=save_monitor, restarts svxlink since MONITOR_TGS is only read
// at startup, so this is deliberately NOT an auto-save per checkbox) and
// the "TG Names" page (rename/add a TG, ?action=rename, no restart --
// plain JSON write, saved instantly per field on blur/Enter). While
// editing, polling still refreshes this.lastData in the background but
// stops re-rendering (see poll()'s own comment) so an in-progress edit
// never gets silently overwritten by the next refresh_ms tick.
class MonitoredTalkgroupsPanel extends HTMLElement {
  connectedCallback() {
    this.apiUrl = this.getAttribute('api') || 'api/monitored-talkgroups.php';
    this.refreshMs = parseInt(this.getAttribute('refresh-ms'), 10) || 10000;
    this.selectingTg = null;
    this.editMode = false;
    this.lastData = null;
    this.render({ loading: true });
    this.poll();
    this._timer = setInterval(() => this.poll(), this.refreshMs);
    this.addEventListener('click', (e) => this.onClick(e));
  }

  disconnectedCallback() {
    clearInterval(this._timer);
  }

  // forceRenderInEdit: normal polling never re-renders while editMode is
  // on (would blow away in-progress checkbox/name edits out from under
  // the user, same class of problem the scrollTop fix addressed) -- only
  // an explicit action inside edit mode itself (adding a TG) asks for a
  // fresh render.
  async poll(forceRenderInEdit) {
    try {
      const res = await fetch(this.apiUrl, { cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      this.lastData = data;
      if (!this.editMode) {
        this.render({ data });
      } else if (forceRenderInEdit) {
        this.renderEdit(data);
      }
    } catch (e) {
      if (!this.editMode) this.render({ error: true });
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

    // Every poll rebuilds this.innerHTML wholesale, which silently resets
    // .activity-list's scrollTop to 0 -- confirmed live (2026-09-14):
    // scroll down toward a talkgroup further in the list, get caught by
    // the next refresh_ms tick, and the list jumps back to the top right
    // as you're about to click. Row order is already stable (see
    // api/monitored-talkgroups.php's sort comment), so the fix here is
    // just carrying the scroll position across the rebuild, not avoiding
    // the rebuild itself.
    const prevList = this.querySelector('.activity-list');
    const scrollTop = prevList ? prevList.scrollTop : 0;

    this.innerHTML =
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">Monitored Talkgroups</div>' +
          '<div style="display:flex; align-items:center; gap:8px;">' +
            '<span style="font-family:var(--mono); font-size:11px; color:var(--text-faint);">' + monitored.length + ' monitored, ' + directory.length + ' more</span>' +
            '<button type="button" class="btn" id="mtg-edit-toggle" title="Edit monitor list / names">&#9998;</button>' +
          '</div>' +
        '</div>' +
        '<div class="activity-list">' + rows + '</div>' +
        '<div class="panel-foot">Click a talkgroup to switch to it &middot; MONITOR_TGS + TG Names in svxlink.conf &middot; activity parsed from /var/log/svxlink</div>' +
      '</div>';

    if (scrollTop) {
      this.querySelector('.activity-list').scrollTop = scrollTop;
    }

    this.querySelector('#mtg-edit-toggle').addEventListener('click', () => this.enterEdit());
  }

  enterEdit() {
    this.editMode = true;
    this.renderEdit(this.lastData || { talkgroups: [], selected_tg: null });
  }

  exitEdit() {
    this.editMode = false;
    this.render({ data: this.lastData });
  }

  renderEdit(data) {
    const rows = data.talkgroups.map((t) => this.tgEditRow(t)).join('');

    const prevList = this.querySelector('.activity-list');
    const scrollTop = prevList ? prevList.scrollTop : 0;

    this.innerHTML =
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">Monitored Talkgroups &mdash; editing</div>' +
          '<button type="button" class="btn active" id="mtg-edit-done">Done</button>' +
        '</div>' +
        '<div class="activity-list">' + rows + '</div>' +
        '<div class="panel-body" style="display:flex; gap:8px; align-items:center; padding-top:12px; border-top:1px solid var(--border-soft);">' +
          '<input type="text" id="mtg-add-tg" placeholder="TG #" style="width:64px;">' +
          '<input type="text" id="mtg-add-name" placeholder="Name" style="flex:1; min-width:0;">' +
          '<button type="button" class="btn" id="mtg-add-btn">Add TG</button>' +
        '</div>' +
        '<div class="panel-foot" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">' +
          '<span id="mtg-save-msg" style="font-family:var(--mono); font-size:11px; color:var(--text-faint); flex:1;"></span>' +
          '<button type="button" class="btn" id="mtg-save-btn">Save monitor list &amp; restart SvxLink</button>' +
        '</div>' +
      '</div>';

    // Name values are set via property assignment, not baked into the
    // HTML string above -- this.esc() only escapes for text content
    // (<, >, &), not for sitting inside a value="..." attribute, so a
    // name containing a literal " would otherwise break the markup.
    data.talkgroups.forEach((t) => {
      const row = this.querySelector('.activity-row[data-tg="' + CSS.escape(t.tg) + '"]');
      const input = row && row.querySelector('.mtg-name-input');
      if (input) input.value = t.name || '';
    });

    if (scrollTop) {
      this.querySelector('.activity-list').scrollTop = scrollTop;
    }

    this.querySelector('#mtg-edit-done').addEventListener('click', () => this.exitEdit());
    this.querySelector('#mtg-save-btn').addEventListener('click', () => this.saveMonitorList());
    this.querySelector('#mtg-add-btn').addEventListener('click', () => this.addTg());
    const addTgInput = this.querySelector('#mtg-add-tg');
    const addNameInput = this.querySelector('#mtg-add-name');
    [addTgInput, addNameInput].forEach((el) => {
      el.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          this.addTg();
        }
      });
    });
    this.querySelectorAll('.mtg-name-input').forEach((el) => {
      el.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          el.blur();
        }
      });
      el.addEventListener('blur', () => this.saveName(el));
    });
  }

  tgEditRow(t) {
    const prio = t.priority || 0;
    const prioOptions = [0, 1, 2, 3]
      .map((p) => '<option value="' + p + '"' + (p === prio ? ' selected' : '') + '>' + (p === 0 ? 'none' : '+'.repeat(p)) + '</option>')
      .join('');
    return (
      '<div class="activity-row" data-tg="' + this.esc(t.tg) + '" style="gap:10px;">' +
        '<input type="checkbox" class="mtg-monitor-cb"' + (t.monitored ? ' checked' : '') + '>' +
        '<span class="tg-num" style="min-width:60px;">TG ' + this.esc(t.tg) + '</span>' +
        '<input type="text" class="mtg-name-input" style="flex:1; min-width:0;">' +
        '<select class="mtg-prio-select field">' + prioOptions + '</select>' +
        '<span class="mtg-name-status" style="width:14px; text-align:center; font-size:12px;"></span>' +
      '</div>'
    );
  }

  async saveName(el) {
    const row = el.closest('.activity-row');
    const tg = row.getAttribute('data-tg');
    const name = el.value.trim();
    const statusEl = row.querySelector('.mtg-name-status');
    if (!name) {
      return;
    }
    try {
      const res = await fetch(this.apiUrl + '?action=rename', {
        method: 'POST',
        body: new URLSearchParams({ tg, name }),
        cache: 'no-store',
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      if (statusEl) {
        statusEl.textContent = '✓';
        statusEl.style.color = 'var(--ok)';
      }
      if (this.lastData) {
        const t = this.lastData.talkgroups.find((x) => x.tg === tg);
        if (t) t.name = name;
      }
    } catch (e) {
      if (statusEl) {
        statusEl.textContent = '!';
        statusEl.style.color = 'var(--crit)';
      }
    }
  }

  async addTg() {
    const tgInput = this.querySelector('#mtg-add-tg');
    const nameInput = this.querySelector('#mtg-add-name');
    const msg = this.querySelector('#mtg-save-msg');
    const tg = tgInput.value.trim();
    const name = nameInput.value.trim();
    if (!/^\d+$/.test(tg) || !name) {
      msg.textContent = 'TG must be a number and name must not be empty';
      msg.style.color = 'var(--crit)';
      return;
    }
    try {
      const res = await fetch(this.apiUrl + '?action=rename', {
        method: 'POST',
        body: new URLSearchParams({ tg, name }),
        cache: 'no-store',
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      msg.textContent = '';
      await this.poll(true);
    } catch (e) {
      msg.textContent = 'Failed to add TG';
      msg.style.color = 'var(--crit)';
    }
  }

  async saveMonitorList() {
    const msg = this.querySelector('#mtg-save-msg');
    const saveBtn = this.querySelector('#mtg-save-btn');
    const tgs = {};
    this.querySelectorAll('.activity-row[data-tg]').forEach((row) => {
      const cb = row.querySelector('.mtg-monitor-cb');
      if (cb && cb.checked) {
        const tg = row.getAttribute('data-tg');
        const sel = row.querySelector('.mtg-prio-select');
        tgs[tg] = sel ? parseInt(sel.value, 10) || 0 : 0;
      }
    });

    msg.textContent = 'Saving…';
    msg.style.color = 'var(--text-faint)';
    saveBtn.disabled = true;
    try {
      const res = await fetch(this.apiUrl + '?action=save_monitor', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tgs }),
        cache: 'no-store',
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      msg.textContent = 'Saved — restarting SvxLink, this takes a few seconds';
      msg.style.color = 'var(--ok)';
      this.editMode = false;
      // Give svxlink a moment to actually restart before polling again --
      // an immediate poll would just read the pre-restart config/log.
      setTimeout(() => this.poll(), 4000);
    } catch (e) {
      msg.textContent = 'Failed to save';
      msg.style.color = 'var(--crit)';
      saveBtn.disabled = false;
    }
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
