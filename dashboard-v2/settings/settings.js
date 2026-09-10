// Layout settings page. `page` comes from ?page=<id> in the URL
// (defaulting to "dashboard"), so one settings page serves every page's
// module layout and page metadata -- must stay in sync with
// js/pages-nav.js's page list.
//
// Two independent things get edited here:
//  - Page metadata (name shown in the nav, column count) via
//    api/page-meta.php -- its own small form + Save button.
//  - Module layout (which modules, which column, what order) via
//    api/layout.php, rendered as draggable cards -- see that section's
//    own comments below. The drag area's column count follows whatever
//    was last saved/loaded for the page metadata, re-rendering if you
//    change and save the column count.
const PAGE_META = {
  dashboard: { href: '/' },
  qsolog: { href: '/qsolog/' },
  rxmonitor: { href: '/rxmonitor/' },
};

const page = new URLSearchParams(window.location.search).get('page') || 'dashboard';
const pageHref = (PAGE_META[page] || PAGE_META.dashboard).href;
document.getElementById('back-link').href = pageHref;

let layout = [];
let columns = 3;

async function loadPageMeta() {
  const res = await fetch('/api/page-meta.php?page=' + encodeURIComponent(page), { cache: 'no-store' }).then((r) => r.json());
  columns = res.columns;
  document.getElementById('page-label-input').value = res.label;
  document.getElementById('page-columns-input').value = String(res.columns);
  document.getElementById('page-source-tag').innerHTML = res.using_saved
    ? '<span class="dot ok"></span>saved'
    : '<span class="dot warn"></span>shipped default';
  document.getElementById('page-sub').textContent = 'dashboard-v2 preview · ' + res.label + ' · which modules, where';
  document.getElementById('panel-title').textContent = res.label + ' modules';
}

async function savePageMeta() {
  const msg = document.getElementById('page-save-msg');
  msg.className = 'save-msg';
  msg.textContent = 'Saving…';
  try {
    const body = {
      label: document.getElementById('page-label-input').value.trim(),
      columns: parseInt(document.getElementById('page-columns-input').value, 10),
    };
    const res = await fetch('/api/page-meta.php?page=' + encodeURIComponent(page), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || ('HTTP ' + res.status));
    msg.className = 'save-msg ok';
    msg.textContent = 'Saved. Reload any page for the new name/columns to show.';
    await loadPageMeta();
    buildDragCols();
    render();
  } catch (e) {
    msg.className = 'save-msg err';
    msg.textContent = 'Could not save: ' + e.message;
  }
}

/** (Re)builds the empty column boxes to match the current `columns`
 * count -- called on load and whenever the page's column count changes,
 * since the drag area itself has no fixed 3-column assumption anymore. */
function buildDragCols() {
  const wrap = document.getElementById('drag-cols');
  wrap.style.gridTemplateColumns = 'repeat(' + columns + ', 1fr)';
  wrap.innerHTML = '';
  for (let n = 1; n <= columns; n++) {
    const col = document.createElement('div');
    col.className = 'drag-col';
    col.dataset.col = n;
    col.innerHTML = '<div class="drag-col-label">Column ' + n + '</div>';
    wrap.appendChild(col);
  }
  wireDragEvents();
}

async function loadLayout() {
  const res = await fetch('/api/layout.php?page=' + encodeURIComponent(page), { cache: 'no-store' }).then((r) => r.json());
  layout = res.layout;
  document.getElementById('source-tag').innerHTML = res.using_saved
    ? '<span class="dot ok"></span>saved layout'
    : '<span class="dot warn"></span>shipped default';
  render();
}

function render() {
  const cols = {};
  document.querySelectorAll('.drag-col').forEach((el) => { cols[el.dataset.col] = el; });
  Object.values(cols).forEach((col) => {
    col.querySelectorAll('.mod-card').forEach((c) => c.remove());
  });

  layout.forEach((entry) => {
    const card = document.createElement('div');
    card.className = 'mod-card' + (entry.enabled ? '' : ' disabled');
    card.draggable = true;
    card.dataset.id = entry.id;
    card.innerHTML =
      '<span class="grip">&#8942;&#8942;</span>' +
      '<input type="checkbox" ' + (entry.enabled ? 'checked' : '') + '>' +
      '<span class="name">' + entry.title + '</span>';

    card.querySelector('input').addEventListener('change', (e) => {
      const target = layout.find((l) => l.id === entry.id);
      target.enabled = e.target.checked;
      card.classList.toggle('disabled', !e.target.checked);
    });

    card.addEventListener('dragstart', () => {
      card.classList.add('dragging');
    });
    card.addEventListener('dragend', () => {
      card.classList.remove('dragging');
      syncLayoutFromDom();
    });

    // A module saved against a column beyond the page's current column
    // count (e.g. columns lowered since) still needs somewhere to render
    // here -- clamp to the last real column instead of disappearing.
    const targetCol = cols[entry.col] || cols[columns] || cols[1];
    targetCol.appendChild(card);
  });
}

function draggableCardAfterPoint(container, y) {
  const cards = [...container.querySelectorAll('.mod-card:not(.dragging)')];
  return cards.reduce((closest, child) => {
    const box = child.getBoundingClientRect();
    const offset = y - box.top - box.height / 2;
    if (offset < 0 && offset > closest.offset) {
      return { offset, element: child };
    }
    return closest;
  }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
}

function wireDragEvents() {
  document.querySelectorAll('.drag-col').forEach((col) => {
    col.addEventListener('dragover', (e) => {
      e.preventDefault();
      col.classList.add('drag-over');
      const dragging = document.querySelector('.mod-card.dragging');
      if (!dragging) return;
      const after = draggableCardAfterPoint(col, e.clientY);
      if (after == null) {
        col.appendChild(dragging);
      } else {
        col.insertBefore(dragging, after);
      }
    });
    col.addEventListener('dragleave', (e) => {
      if (!col.contains(e.relatedTarget)) col.classList.remove('drag-over');
    });
    col.addEventListener('drop', (e) => {
      e.preventDefault();
      col.classList.remove('drag-over');
    });
  });
}

/** Rebuilds the `layout` array from the DOM's current card order/column
 * placement -- the actual result of a drag, since dragover already moved
 * the real elements around live rather than just tracking indices. */
function syncLayoutFromDom() {
  document.querySelectorAll('.drag-col').forEach((col) => col.classList.remove('drag-over'));
  const newLayout = [];
  document.querySelectorAll('.drag-col').forEach((col) => {
    const colNum = parseInt(col.dataset.col, 10);
    col.querySelectorAll('.mod-card').forEach((card) => {
      const existing = layout.find((l) => l.id === card.dataset.id);
      newLayout.push({ id: card.dataset.id, col: colNum, enabled: existing.enabled, title: existing.title });
    });
  });
  layout = newLayout;
}

async function save() {
  const msg = document.getElementById('save-msg');
  msg.className = 'save-msg';
  msg.textContent = 'Saving…';
  try {
    const payload = layout.map(({ id, col, enabled }) => ({ id, col, enabled }));
    const res = await fetch('/api/layout.php?page=' + encodeURIComponent(page), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || ('HTTP ' + res.status));
    msg.className = 'save-msg ok';
    msg.textContent = 'Saved. Reload the console to see it take effect.';
    await loadLayout();
  } catch (e) {
    msg.className = 'save-msg err';
    msg.textContent = 'Could not save: ' + e.message;
  }
}

async function resetToDefault() {
  const msg = document.getElementById('save-msg');
  const shipped = await fetch('/pages/' + encodeURIComponent(page) + '/layout.json', { cache: 'no-store' }).then((r) => r.json());
  const res = await fetch('/api/layout.php?page=' + encodeURIComponent(page), { cache: 'no-store' }).then((r) => r.json());
  // Fold in any module the shipped default doesn't know about yet, same
  // back-fill api/layout.php does server-side, so resetting never hides a
  // newer module added after this default was written.
  const knownIds = shipped.map((e) => e.id);
  const extra = res.layout.filter((e) => !knownIds.includes(e.id)).map((e) => ({ id: e.id, col: e.col, enabled: e.enabled }));
  layout = [...shipped, ...extra].map((e) => ({
    ...e,
    title: (res.layout.find((r) => r.id === e.id) || {}).title || e.id,
  }));
  msg.className = 'save-msg';
  msg.textContent = 'Reset in the form below -- click Save to make it permanent.';
  render();
}

// All four async handlers mutate/read shared state (`layout` or the page
// meta form) -- disable the relevant button(s) for the duration of each
// so a fast second click can't race and save something stale.
function withButtonsDisabled(buttons, fn) {
  return async () => {
    buttons.forEach((b) => { b.disabled = true; });
    try {
      await fn();
    } finally {
      buttons.forEach((b) => { b.disabled = false; });
    }
  };
}

const saveBtn = document.getElementById('save-btn');
const resetBtn = document.getElementById('reset-btn');
const pageSaveBtn = document.getElementById('page-save-btn');
saveBtn.addEventListener('click', withButtonsDisabled([saveBtn, resetBtn], save));
resetBtn.addEventListener('click', withButtonsDisabled([saveBtn, resetBtn], resetToDefault));
pageSaveBtn.addEventListener('click', withButtonsDisabled([pageSaveBtn], savePageMeta));

(async function init() {
  await loadPageMeta();
  buildDragCols();
  await loadLayout();
})();
