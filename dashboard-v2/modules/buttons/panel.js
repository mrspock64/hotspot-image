// dashboard-v2 Quick Buttons module. Production's front-page macro
// buttons (dashboard/include/buttons.php's button_0..N + the Buttons
// admin page), reused as-is via api/buttons.php's thin wrapper around
// buttons_store.php -- no restart anywhere in this module, pressing a
// button and editing the list are both just a DTMF shell_exec / a JSON
// write. Deliberately no free-text "send any DTMF" box here -- that's a
// different module (arbitrary input vs. a saved, reviewable button
// list), out of scope for this one.
//
// Colors are stored using production's own vocabulary (green/blue/red/
// orange/purple -- see api/buttons.php's header comment for why) but
// remapped to this theme's real palette for display via the .qbtn-<color>
// classes in tokens.css -- never written back in the new vocabulary.
//
// Edit mode (pencil button) replaces production's separate Buttons admin
// page: add/rename/re-color/reorder/delete, each change saves the whole
// list immediately (?action=save) since nothing here needs a restart --
// unlike Monitored Talkgroups' batched monitor-list save. Polling still
// refreshes this.lastData in the background while editing but skips
// re-rendering, same principle as that module's own edit mode.
class ButtonsPanel extends HTMLElement {
  connectedCallback() {
    this.apiUrl = this.getAttribute('api') || 'api/buttons.php';
    this.refreshMs = parseInt(this.getAttribute('refresh-ms'), 10) || 15000;
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
    if (this.editMode) return;
    const btn = e.target.closest('.qbtn[data-index]');
    if (!btn) return;
    this.pressButton(btn);
  }

  async pressButton(btn) {
    const index = btn.getAttribute('data-index');
    btn.classList.add('pressed');
    btn.disabled = true;
    try {
      await fetch(this.apiUrl + '?action=press&index=' + encodeURIComponent(index), { cache: 'no-store' });
    } catch (e) {
      // No feedback needed beyond the button re-enabling -- this fires a
      // single DTMF send, same as production's own macro buttons.
    }
    setTimeout(() => {
      btn.classList.remove('pressed');
      btn.disabled = false;
    }, 400);
  }

  render({ loading, error, data }) {
    if (loading || error || !data) {
      const msg = loading ? 'Loading&hellip;' : 'Buttons unavailable';
      this.innerHTML =
        '<div class="panel"><div class="panel-head"><div class="panel-title">Quick Buttons</div></div>' +
        '<div class="panel-body"><div class="kv-row"><span class="k">' + msg + '</span></div></div></div>';
      return;
    }

    const btns = data.buttons.length
      ? data.buttons.map((b, i) => this.buttonPill(b, i)).join('')
      : '<span style="font-family:var(--mono); font-size:12px; color:var(--text-faint);">No buttons configured</span>';

    this.innerHTML =
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">Quick Buttons</div>' +
          '<button type="button" class="btn" id="qb-edit-toggle" title="Edit buttons">&#9998;</button>' +
        '</div>' +
        '<div class="panel-body" style="display:flex; flex-wrap:wrap; gap:8px;">' + btns + '</div>' +
        '<div class="panel-foot">Sends DTMF via the same relay production&#39;s buttons use</div>' +
      '</div>';

    this.querySelector('#qb-edit-toggle').addEventListener('click', () => this.enterEdit());
  }

  buttonPill(b, i) {
    return '<button type="button" class="qbtn qbtn-' + this.esc(b.color) + '" data-index="' + i + '">' + this.esc(b.label) + '</button>';
  }

  enterEdit() {
    this.editMode = true;
    this.renderEdit(this.lastData || { buttons: [] });
  }

  exitEdit() {
    this.editMode = false;
    this.render({ data: this.lastData });
  }

  renderEdit(data) {
    const rows = data.buttons.map((b, i) => this.buttonEditRow(b, i, data.buttons.length)).join('');

    this.innerHTML =
      '<div class="panel">' +
        '<div class="panel-head"><div class="panel-title">Quick Buttons &mdash; editing</div>' +
          '<button type="button" class="btn active" id="qb-edit-done">Done</button>' +
        '</div>' +
        '<div class="activity-list" id="qb-edit-list">' + (rows || '<div class="kv-row" style="padding:14px 16px;"><span class="k">No buttons yet -- add one below</span></div>') + '</div>' +
        '<div class="panel-body" style="display:flex; gap:8px; align-items:center; padding-top:12px; border-top:1px solid var(--border-soft);">' +
          '<input type="text" id="qb-add-label" placeholder="Label" style="width:110px;">' +
          '<input type="text" id="qb-add-dtmf" placeholder="DTMF, e.g. 91240#" style="flex:1; min-width:0;">' +
          this.colorSelect('green', 'qb-add-color') +
          '<button type="button" class="btn" id="qb-add-btn">Add</button>' +
        '</div>' +
        '<div class="panel-foot"><span id="qb-save-msg" style="font-family:var(--mono); font-size:11px; color:var(--text-faint);"></span></div>' +
      '</div>';

    // Values set via property assignment, not baked into the row HTML --
    // avoids the value="..." attribute-escaping trap Monitored
    // Talkgroups' edit mode already worked around for the same reason.
    data.buttons.forEach((b, i) => {
      const row = this.querySelector('.qb-edit-row[data-index="' + i + '"]');
      if (!row) return;
      row.querySelector('.qb-label-input').value = b.label;
      row.querySelector('.qb-dtmf-input').value = b.dtmf;
    });

    this.querySelector('#qb-edit-done').addEventListener('click', () => this.exitEdit());
    this.querySelector('#qb-add-btn').addEventListener('click', () => this.addButton());
    this.querySelectorAll('.qb-label-input, .qb-dtmf-input').forEach((el) => {
      el.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          el.blur();
        }
      });
      el.addEventListener('blur', () => this.saveAll());
    });
    this.querySelectorAll('.qb-color-select').forEach((el) => {
      el.addEventListener('change', () => this.saveAll());
    });
    this.querySelectorAll('.qb-up-btn').forEach((el) => el.addEventListener('click', () => this.moveButton(el, -1)));
    this.querySelectorAll('.qb-down-btn').forEach((el) => el.addEventListener('click', () => this.moveButton(el, 1)));
    this.querySelectorAll('.qb-delete-btn').forEach((el) => el.addEventListener('click', () => this.deleteButton(el)));
  }

  buttonEditRow(b, i, total) {
    return (
      '<div class="activity-row qb-edit-row" data-index="' + i + '" style="gap:8px;">' +
        '<input type="text" class="qb-label-input" style="width:110px;">' +
        '<input type="text" class="qb-dtmf-input" style="flex:1; min-width:0;">' +
        this.colorSelect(b.color, '', 'qb-color-select') +
        '<button type="button" class="btn qb-up-btn" title="Move up" style="padding:4px 9px;"' + (i === 0 ? ' disabled' : '') + '>&uarr;</button>' +
        '<button type="button" class="btn qb-down-btn" title="Move down" style="padding:4px 9px;"' + (i === total - 1 ? ' disabled' : '') + '>&darr;</button>' +
        '<button type="button" class="btn qb-delete-btn" title="Delete" style="padding:4px 9px;">&times;</button>' +
      '</div>'
    );
  }

  colorSelect(selected, id, extraClass) {
    const colors = ['green', 'blue', 'red', 'orange', 'purple'];
    const options = colors
      .map((c) => '<option value="' + c + '"' + (c === selected ? ' selected' : '') + '>' + c + '</option>')
      .join('');
    return '<select class="field' + (extraClass ? ' ' + extraClass : '') + '"' + (id ? ' id="' + id + '"' : '') + '>' + options + '</select>';
  }

  readRowsFromDom() {
    return Array.from(this.querySelectorAll('.qb-edit-row'))
      .map((row) => ({
        label: row.querySelector('.qb-label-input').value.trim(),
        dtmf: row.querySelector('.qb-dtmf-input').value.trim(),
        color: row.querySelector('.qb-color-select').value,
      }))
      .filter((b) => b.label && b.dtmf);
  }

  async persist(buttons) {
    const res = await fetch(this.apiUrl + '?action=save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ buttons }),
      cache: 'no-store',
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    this.lastData = { buttons };
  }

  async saveAll() {
    const msg = this.querySelector('#qb-save-msg');
    try {
      await this.persist(this.readRowsFromDom());
      if (msg) {
        msg.textContent = 'Saved';
        msg.style.color = 'var(--ok)';
        setTimeout(() => {
          if (msg) msg.textContent = '';
        }, 1500);
      }
    } catch (e) {
      if (msg) {
        msg.textContent = 'Failed to save';
        msg.style.color = 'var(--crit)';
      }
    }
  }

  async addButton() {
    const labelInput = this.querySelector('#qb-add-label');
    const dtmfInput = this.querySelector('#qb-add-dtmf');
    const colorSelect = this.querySelector('#qb-add-color');
    const msg = this.querySelector('#qb-save-msg');
    const label = labelInput.value.trim();
    const dtmf = dtmfInput.value.trim();
    if (!label || !dtmf) {
      msg.textContent = 'Label and DTMF are both required';
      msg.style.color = 'var(--crit)';
      return;
    }
    const buttons = this.readRowsFromDom();
    buttons.push({ label, dtmf, color: colorSelect.value });
    try {
      await this.persist(buttons);
      this.renderEdit(this.lastData);
    } catch (e) {
      msg.textContent = 'Failed to add button';
      msg.style.color = 'var(--crit)';
    }
  }

  async moveButton(el, dir) {
    const row = el.closest('.qb-edit-row');
    const index = parseInt(row.getAttribute('data-index'), 10);
    const buttons = this.readRowsFromDom();
    const target = index + dir;
    if (target < 0 || target >= buttons.length) return;
    [buttons[index], buttons[target]] = [buttons[target], buttons[index]];
    await this.persistAndRerender(buttons);
  }

  async deleteButton(el) {
    const row = el.closest('.qb-edit-row');
    const index = parseInt(row.getAttribute('data-index'), 10);
    const label = row.querySelector('.qb-label-input').value || 'this button';
    if (!window.confirm('Delete "' + label + '"?')) {
      return;
    }
    const buttons = this.readRowsFromDom();
    buttons.splice(index, 1);
    await this.persistAndRerender(buttons);
  }

  async persistAndRerender(buttons) {
    const msg = this.querySelector('#qb-save-msg');
    try {
      await this.persist(buttons);
      this.renderEdit(this.lastData);
    } catch (e) {
      if (msg) {
        msg.textContent = 'Failed to save';
        msg.style.color = 'var(--crit)';
      }
    }
  }

  esc(s) {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }
}

customElements.define('buttons-panel', ButtonsPanel);
