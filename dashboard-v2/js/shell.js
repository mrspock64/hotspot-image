// dashboard-v2 shell: reads layout.json -- the single place that decides
// which modules show, in which column, and in what order within that
// column (array order = display order). A module's own manifest.json
// never says where it lives; moving a module or switching it off means
// editing one entry in layout.json, not touching the module's own files.
// Adding a brand-new module means adding its three files plus one entry
// here -- this file itself shouldn't need to change either way.
(async function () {
  const grid = document.getElementById('module-grid');
  const cols = { 1: null, 2: null, 3: null };
  for (const n of [1, 2, 3]) {
    const col = document.createElement('div');
    col.className = 'col';
    col.dataset.col = n;
    grid.appendChild(col);
    cols[n] = col;
  }

  let layout;
  try {
    layout = await fetch('layout.json', { cache: 'no-store' }).then((r) => r.json());
  } catch (e) {
    grid.innerHTML = '<div class="panel"><div class="panel-body">Could not load layout.json</div></div>';
    return;
  }

  for (const entry of layout) {
    if (entry.enabled === false) continue; // turned off -- skip entirely, not just hidden
    try {
      const manifest = await fetch('modules/' + entry.id + '/manifest.json', { cache: 'no-store' }).then((r) => r.json());
      await loadScript(manifest.script);
      const el = document.createElement(manifest.element);
      // api/refresh_ms/element/script/id are the shell's own known fields;
      // anything else in a manifest (e.g. rxmonitor's ws_port) passes
      // straight through as a kebab-case attribute, so a module needing an
      // extra setting doesn't require touching this file.
      const known = new Set(['id', 'title', 'element', 'script']);
      for (const [key, value] of Object.entries(manifest)) {
        if (!known.has(key)) {
          el.setAttribute(key.replace(/_/g, '-'), String(value));
        }
      }
      const col = cols[entry.col] || cols[1];
      col.appendChild(el);
    } catch (e) {
      console.error('dashboard-v2: failed to load module', entry.id, e);
    }
  }
})();

function loadScript(src) {
  return new Promise((resolve, reject) => {
    const s = document.createElement('script');
    s.src = src;
    s.onload = resolve;
    s.onerror = reject;
    document.head.appendChild(s);
  });
}
