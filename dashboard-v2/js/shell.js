// dashboard-v2 shell: reads modules.json (just a list of module ids -- see
// docs/dashboard-v2-brief.md's "huvuddashboarden blir bara en lista"),
// fetches each one's manifest.json, loads its script, and mounts its
// custom element into the grid column its manifest asks for. Adding a
// module later means adding its id here plus its own three files -- this
// file shouldn't need to change.
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

  let moduleIds;
  try {
    moduleIds = await fetch('modules.json', { cache: 'no-store' }).then((r) => r.json());
  } catch (e) {
    grid.innerHTML = '<div class="panel"><div class="panel-body">Could not load modules.json</div></div>';
    return;
  }

  for (const id of moduleIds) {
    try {
      const manifest = await fetch('modules/' + id + '/manifest.json', { cache: 'no-store' }).then((r) => r.json());
      await loadScript(manifest.script);
      const el = document.createElement(manifest.element);
      el.setAttribute('api', manifest.api);
      el.setAttribute('refresh-ms', String(manifest.refresh_ms));
      const col = cols[manifest.col] || cols[1];
      col.appendChild(el);
    } catch (e) {
      console.error('dashboard-v2: failed to load module', id, e);
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
