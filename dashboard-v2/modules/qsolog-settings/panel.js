// dashboard-v2 QSO Log Settings module -- step 2 of the QSO Log work
// (play/delete came first). Reuses production's own dashboard/qsolog/
// index.php settings form directly through api/qsolog-settings.php's
// thin wrappers around getQsoRecorderSettings()/saveQsoRecorderSettings()
// /getLoadMonitorAutoPause() -- see that file's header comment.
//
// No polling, unlike every other module here -- this is a form, not a
// live readout, and production's own equivalent page doesn't poll
// either (a plain PHP page, fresh on each load). Refetching mid-edit
// would only ever fight the person filling it in; reload the page (or
// come back later) to see a change made from elsewhere, same as
// production's page already requires.
class QsologSettingsPanel extends HTMLElement {
  connectedCallback() {
    this.apiUrl = this.getAttribute('api') || 'api/qsolog-settings.php';
    this.render({ loading: true });
    this.load();
  }

  async load() {
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
      const msg = loading ? 'Loading&hellip;' : 'QSO Log settings unavailable';
      this.innerHTML =
        '<div class="panel"><div class="panel-head"><div class="panel-title">QSO Log Settings</div></div>' +
        '<div class="panel-body"><div class="kv-row"><span class="k">' + msg + '</span></div></div></div>';
      return;
    }

    this.data = data;
    const recordOnlySet = new Set((data.record_only_tgs || []).map(String));
    const allChecked = recordOnlySet.size === 0;

    const tgCheckboxes = Object.keys(data.tg_names || {})
      .sort((a, b) => (parseInt(a, 10) || 0) - (parseInt(b, 10) || 0))
      .map((tg) => {
        const name = data.tg_names[tg];
        const label = name && name !== tg ? tg + ' (' + this.esc(name) + ')' : tg;
        return (
          '<label style="display:flex; align-items:center; gap:5px; font-size:12.5px; background:var(--surface-2); padding:4px 9px; border-radius:6px; border:1px solid var(--border);">' +
            '<input type="checkbox" class="qls-record-only-tg" value="' + this.esc(tg) + '"' +
              (recordOnlySet.has(tg) ? ' checked' : '') + (allChecked ? ' disabled' : '') + '>' +
            label +
          '</label>'
        );
      })
      .join('');

    this.innerHTML =
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">QSO Log Settings</div></div>' +
        '<div class="panel-body" style="display:flex; flex-direction:column; gap:14px;">' +
          '<div style="font-family:var(--disp); font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--text-faint);">Recording</div>' +
          '<label style="display:flex; align-items:center; gap:8px; font-size:13px;">' +
            '<input type="checkbox" id="qls-active"' + (data.active ? ' checked' : '') + '>' +
            'Record every transmission' +
          '</label>' +
          '<div style="display:flex; gap:16px; flex-wrap:wrap;">' +
            this.field('qls-max-dirsize', 'Disk limit (MB)', data.max_dirsize) +
            this.field('qls-qso-timeout', 'New file after (sec of silence)', data.qso_timeout) +
            this.field('qls-max-recordings', 'Max recordings to keep', data.max_recordings) +
          '</div>' +
          '<p style="font-family:var(--mono); font-size:11px; color:var(--text-faint); margin:0;">A gap of at least this long between transmissions starts a new recording. Max recordings keeps only the newest N (0 = no limit), on top of the disk limit.</p>' +
          '<div>' +
            '<div style="font-size:12.5px; font-weight:600; color:var(--text); margin-bottom:6px;">Record only these talkgroups</div>' +
            '<div style="display:flex; flex-wrap:wrap; gap:6px;">' +
              '<label style="display:flex; align-items:center; gap:5px; font-size:12.5px; background:var(--surface-2); padding:4px 9px; border-radius:6px; border:1px solid var(--copper-dim);">' +
                '<input type="checkbox" id="qls-record-only-all"' + (allChecked ? ' checked' : '') + '>' +
                'All talkgroups' +
              '</label>' +
              tgCheckboxes +
            '</div>' +
          '</div>' +
          '<div style="display:flex; align-items:center; gap:12px;">' +
            // Two "Save" buttons in one compact form is exactly what caused
            // a real reported bug: clicking this section's Save while
            // aiming for it is easy to mix up with the auto-pause section's
            // own Save just below -- that one genuinely succeeds (it saves
            // its own field), but silently never sends *this* section's
            // changes, so a checked box here looks saved and then reverts
            // on reload. Explicit, distinct labels instead of two bare
            // "Save" buttons close together.
            '<button type="button" class="btn" id="qls-save-btn">Save recording settings</button>' +
            '<span id="qls-save-msg" style="font-family:var(--mono); font-size:11px; color:var(--text-faint);"></span>' +
          '</div>' +
          '<div style="border-top:1px solid var(--border-soft); padding-top:14px;">' +
            '<div style="font-family:var(--disp); font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--text-faint); margin-bottom:8px;">Auto-pause under load</div>' +
            '<label style="display:flex; align-items:center; gap:8px; font-size:13px;">' +
              '<input type="checkbox" id="qls-load-pause"' + (data.load_monitor_auto_pause ? ' checked' : '') + '>' +
              'Automatically pause recording under sustained high load' +
            '</label>' +
            '<p style="font-family:var(--mono); font-size:11px; color:var(--text-faint); margin:6px 0 10px;">Only ever turns recording off, never back on -- and only if it was already on.</p>' +
            '<div style="display:flex; align-items:center; gap:12px;">' +
              '<button type="button" class="btn" id="qls-load-save-btn">Save auto-pause setting</button>' +
              '<span id="qls-load-save-msg" style="font-family:var(--mono); font-size:11px; color:var(--text-faint);"></span>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</div>';

    this.querySelector('#qls-record-only-all').addEventListener('change', (e) => {
      this.querySelectorAll('.qls-record-only-tg').forEach((cb) => {
        cb.disabled = e.target.checked;
      });
    });
    this.querySelector('#qls-save-btn').addEventListener('click', () => this.save());
    this.querySelector('#qls-load-save-btn').addEventListener('click', () => this.saveLoadMonitor());
  }

  field(id, label, value) {
    return (
      '<div>' +
        '<label for="' + id + '" style="display:block; font-size:11px; text-transform:uppercase; letter-spacing:0.6px; color:var(--text-faint); margin-bottom:4px;">' + label + '</label>' +
        '<input type="text" id="' + id + '" value="' + this.esc(String(value)) + '" style="width:110px;">' +
      '</div>'
    );
  }

  async save() {
    const msg = this.querySelector('#qls-save-msg');
    const allChecked = this.querySelector('#qls-record-only-all').checked;
    const recordOnlyTgs = allChecked
      ? []
      : Array.from(this.querySelectorAll('.qls-record-only-tg:checked')).map((cb) => cb.value);

    const body = {
      active: this.querySelector('#qls-active').checked,
      max_dirsize: this.querySelector('#qls-max-dirsize').value.trim(),
      qso_timeout: this.querySelector('#qls-qso-timeout').value.trim(),
      max_recordings: this.querySelector('#qls-max-recordings').value.trim(),
      record_only_tgs: recordOnlyTgs,
    };

    msg.textContent = 'Saving…';
    msg.style.color = 'var(--text-faint)';
    try {
      const res = await fetch(this.apiUrl + '?action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
        cache: 'no-store',
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'HTTP ' + res.status);
      msg.textContent = data.message || 'Saved.';
      msg.style.color = 'var(--ok)';
    } catch (e) {
      msg.textContent = e.message || 'Failed to save';
      msg.style.color = 'var(--crit)';
    }
  }

  async saveLoadMonitor() {
    const msg = this.querySelector('#qls-load-save-msg');
    const autoPause = this.querySelector('#qls-load-pause').checked;
    msg.textContent = 'Saving…';
    msg.style.color = 'var(--text-faint)';
    try {
      const res = await fetch(this.apiUrl + '?action=save_load_monitor', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ auto_pause: autoPause }),
        cache: 'no-store',
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      msg.textContent = 'Saved.';
      msg.style.color = 'var(--ok)';
    } catch (e) {
      msg.textContent = 'Failed to save';
      msg.style.color = 'var(--crit)';
    }
  }

  esc(s) {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }
}

customElements.define('qsolog-settings-panel', QsologSettingsPanel);
