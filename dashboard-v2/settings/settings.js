// Layout settings page. Fetches api/layout.php's effective layout
// (already merged with each module's title, and back-filled with any
// module on disk that isn't in a saved/default layout yet -- see that
// file's own comment), renders one row per module with a checkbox
// (enabled), a column select, and up/down reorder buttons (plain
// buttons, not drag-and-drop -- reliable with no extra library, and
// order only matters within a column so up/down is unambiguous), then
// POSTs the edited array back on Save.
let layout = [];

async function load() {
  const res = await fetch('../api/layout.php', { cache: 'no-store' }).then((r) => r.json());
  layout = res.layout;
  document.getElementById('source-tag').innerHTML = res.using_saved
    ? '<span class="dot ok"></span>saved layout'
    : '<span class="dot warn"></span>shipped default';
  render();
}

function render() {
  const rows = document.getElementById('layout-rows');
  rows.innerHTML = '';
  // Group by column, in-column order preserved, so reorder buttons only
  // ever move a row within its own column -- matches how the grid itself
  // works (array order = display order within that column).
  layout.forEach((entry, i) => {
    const row = document.createElement('div');
    row.className = 'layout-row' + (entry.enabled ? '' : ' disabled');

    const colSiblings = layout.filter((e) => e.col === entry.col);
    const posInCol = colSiblings.indexOf(entry);
    const isFirst = posInCol === 0;
    const isLast = posInCol === colSiblings.length - 1;

    row.innerHTML =
      '<input type="checkbox" ' + (entry.enabled ? 'checked' : '') + ' data-idx="' + i + '" class="enable-toggle">' +
      '<span class="name">' + entry.title + '</span>' +
      '<select data-idx="' + i + '" class="col-select">' +
        [1, 2, 3].map((c) => '<option value="' + c + '"' + (c === entry.col ? ' selected' : '') + '>Column ' + c + '</option>').join('') +
      '</select>' +
      '<span class="reorder">' +
        '<button data-idx="' + i + '" class="move-up" ' + (isFirst ? 'disabled' : '') + '>&uarr;</button>' +
        '<button data-idx="' + i + '" class="move-down" ' + (isLast ? 'disabled' : '') + '>&darr;</button>' +
      '</span>';
    rows.appendChild(row);
  });

  rows.querySelectorAll('.enable-toggle').forEach((el) => el.addEventListener('change', (e) => {
    layout[+e.target.dataset.idx].enabled = e.target.checked;
    render();
  }));
  rows.querySelectorAll('.col-select').forEach((el) => el.addEventListener('change', (e) => {
    layout[+e.target.dataset.idx].col = parseInt(e.target.value, 10);
    render();
  }));
  rows.querySelectorAll('.move-up').forEach((el) => el.addEventListener('click', (e) => moveWithinColumn(+e.target.dataset.idx, -1)));
  rows.querySelectorAll('.move-down').forEach((el) => el.addEventListener('click', (e) => moveWithinColumn(+e.target.dataset.idx, 1)));
}

function moveWithinColumn(idx, dir) {
  const entry = layout[idx];
  const colIndices = layout.map((e, i) => (e.col === entry.col ? i : -1)).filter((i) => i !== -1);
  const pos = colIndices.indexOf(idx);
  const swapWith = colIndices[pos + dir];
  if (swapWith === undefined) return;
  [layout[idx], layout[swapWith]] = [layout[swapWith], layout[idx]];
  render();
}

async function save() {
  const msg = document.getElementById('save-msg');
  msg.className = 'save-msg';
  msg.textContent = 'Saving…';
  try {
    const payload = layout.map(({ id, col, enabled }) => ({ id, col, enabled }));
    const res = await fetch('../api/layout.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || ('HTTP ' + res.status));
    msg.className = 'save-msg ok';
    msg.textContent = 'Saved. Reload the console to see it take effect.';
    await load();
  } catch (e) {
    msg.className = 'save-msg err';
    msg.textContent = 'Could not save: ' + e.message;
  }
}

async function resetToDefault() {
  const msg = document.getElementById('save-msg');
  const shipped = await fetch('../layout.json', { cache: 'no-store' }).then((r) => r.json());
  layout = shipped.map((e) => ({ ...e }));
  // Fold in any module the shipped default doesn't know about yet, same
  // back-fill api/layout.php does server-side, so resetting never hides a
  // newer module that was added after this default was written.
  const knownIds = layout.map((e) => e.id);
  const res = await fetch('../api/layout.php', { cache: 'no-store' }).then((r) => r.json());
  res.layout.forEach((e) => {
    if (!knownIds.includes(e.id)) layout.push({ id: e.id, col: e.col, enabled: e.enabled });
  });
  layout = layout.map((e) => ({ ...e, title: (res.layout.find((r) => r.id === e.id) || {}).title || e.id }));
  msg.className = 'save-msg';
  msg.textContent = 'Reset in the form below -- click Save to make it permanent.';
  render();
}

document.getElementById('save-btn').addEventListener('click', save);
document.getElementById('reset-btn').addEventListener('click', resetToDefault);
load();
