// dashboard-v2 shell: reads api/page-meta.php?page=<page> (label, column
// count) and api/layout.php?page=<page> (which modules, where) -- the
// single places that decide what a page looks like. A module's own
// manifest.json never says where it lives; moving a module or switching
// it off means editing one entry there (by hand, or via /settings/), not
// touching the module's own files. Adding a brand-new module means
// adding its three files plus one layout entry -- this file itself
// shouldn't need to change either way.
//
// `page` comes from #module-grid's data-page attribute, defaulting to
// "dashboard" -- lets more than one page (Dashboard, QSO Log, ...) share
// this exact shell/module engine while each keeps its own module
// selection and column count. All fetch/script paths here and in every
// manifest.json are root-absolute ("/api/...", "/modules/...") rather
// than relative, on purpose: a page one directory deep (e.g. /qsolog/)
// would otherwise resolve "modules/x/panel.js" against its own path
// instead of the dashboard-v2 site root.
(async function () {
  const grid = document.getElementById('module-grid');
  const page = grid.dataset.page || 'dashboard';

  // Only the column count comes from here -- the page's *displayed name*
  // is the nav chip rendered by pages-nav.js (same api/page-meta.php
  // source), not anything in this shell, so a rename doesn't require this
  // file to also know how/where a page's title is shown.
  let columns = 3;
  try {
    const meta = await fetch('/api/page-meta.php?page=' + encodeURIComponent(page), { cache: 'no-store' }).then((r) => r.json());
    columns = meta.columns || 3;
  } catch (e) {
    // Falls back to the 3-column default below -- not worth failing the
    // whole page over this.
  }

  // The classic 3-column dashboard keeps its original asymmetric layout
  // (narrow sidebar / wide middle / sidebar, defined in css/tokens.css's
  // .grid rule) since that's the shape every existing module screenshot
  // was designed against. Any other column count gets equal-width
  // columns -- there's no "correct" asymmetric shape for e.g. 2 or 4.
  grid.style.gridTemplateColumns = columns === 3 ? '' : 'repeat(' + columns + ', 1fr)';

  const cols = {};
  for (let n = 1; n <= columns; n++) {
    const col = document.createElement('div');
    col.className = 'col';
    col.dataset.col = n;
    grid.appendChild(col);
    cols[n] = col;
  }

  let layout;
  try {
    const res = await fetch('/api/layout.php?page=' + encodeURIComponent(page), { cache: 'no-store' }).then((r) => r.json());
    layout = res.layout;
  } catch (e) {
    grid.innerHTML = '<div class="panel"><div class="panel-body">Could not load the module layout</div></div>';
    return;
  }

  for (const entry of layout) {
    if (entry.enabled === false) continue; // turned off -- skip entirely, not just hidden
    try {
      const manifest = await fetch('/modules/' + entry.id + '/manifest.json', { cache: 'no-store' }).then((r) => r.json());
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

      // Pop-out button: opens the exact same manifest/panel.js/api in its
      // own small window (/module/?id=..., see js/module.js) -- the
      // module itself never knows it's detached. Wrapping here, not
      // inside each panel.js, keeps "detachable" a shell-level concern
      // every module gets for free rather than something each one has to
      // opt into. A named window target (not "_blank") means clicking the
      // button again for the same module on the same page re-focuses the
      // existing popup instead of spawning duplicates.
      const wrap = document.createElement('div');
      wrap.className = 'module-wrap';
      const detachBtn = document.createElement('button');
      detachBtn.className = 'module-detach';
      detachBtn.title = 'Open in its own window';
      detachBtn.innerHTML = '&#8599;';
      detachBtn.addEventListener('click', () => {
        const winName = 'dv2-module-' + page + '-' + entry.id;
        window.open('/module/?id=' + encodeURIComponent(entry.id), winName, 'width=480,height=560,resizable=yes,scrollbars=yes');
      });
      wrap.appendChild(detachBtn);
      wrap.appendChild(el);

      // A module saved against a column that no longer exists on this
      // page (columns lowered after the layout was saved) falls back to
      // the last real column rather than vanishing.
      const col = cols[entry.col] || cols[columns] || cols[1];
      col.appendChild(wrap);
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
