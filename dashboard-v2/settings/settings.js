// Layout settings page. Fetches api/layout.php's effective layout
// (already merged with each module's title, and back-filled with any
// module on disk that isn't in a saved/default layout yet -- see that
// file's own comment), renders one draggable card per module inside
// three column boxes. Dragging a card within a column reorders it;
// dragging it into a different column changes which column it belongs
// to. The `layout` array is only the source of truth for the initial
// render and for Save -- while dragging, the DOM itself is the live
// state (classic "insert before the closest element" pattern), then
// dragend reads the DOM back into `layout`.
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
  const cols = { 1: document.querySelector('.drag-col[data-col="1"]'), 2: document.querySelector('.drag-col[data-col="2"]'), 3: document.querySelector('.drag-col[data-col="3"]') };
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

    cols[entry.col].appendChild(card);
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
  const res = await fetch('../api/layout.php', { cache: 'no-store' }).then((r) => r.json());
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

// Both handlers are async and mutate/read the shared `layout` variable --
// disable both buttons for the duration of either one so a fast second
// click (e.g. Save right after Reset, before its fetches resolve) can't
// race and save a stale `layout` instead of the just-reset one.
function withButtonsDisabled(fn) {
  return async () => {
    saveBtn.disabled = true;
    resetBtn.disabled = true;
    try {
      await fn();
    } finally {
      saveBtn.disabled = false;
      resetBtn.disabled = false;
    }
  };
}

const saveBtn = document.getElementById('save-btn');
const resetBtn = document.getElementById('reset-btn');
saveBtn.addEventListener('click', withButtonsDisabled(save));
resetBtn.addEventListener('click', withButtonsDisabled(resetToDefault));
load();
