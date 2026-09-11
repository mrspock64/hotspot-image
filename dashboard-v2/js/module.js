// /module/?id=<module-id> -- a detached single module in its own popup
// window (opened by shell.js's pop-out button on the main dashboard
// pages). Deliberately a separate, minimal loader rather than reusing
// shell.js's multi-column logic: no page-meta, no layout, no nav --
// just "load this one module's manifest, mount its element, done". The
// module itself doesn't know or care that it's detached; it's the exact
// same manifest.json/panel.js/api used everywhere else.
(async function () {
  const mount = document.getElementById('module-mount');
  const id = new URLSearchParams(window.location.search).get('id');
  if (!id) {
    mount.innerHTML = '<div class="panel"><div class="panel-body">No module id given.</div></div>';
    return;
  }

  let manifest;
  try {
    manifest = await fetch('/modules/' + id + '/manifest.json', { cache: 'no-store' }).then((r) => r.json());
  } catch (e) {
    mount.innerHTML = '<div class="panel"><div class="panel-body">Could not load module "' + id + '".</div></div>';
    return;
  }

  document.title = manifest.title + ' -- Hotspot Console';

  await loadScript(manifest.script);
  const el = document.createElement(manifest.element);
  const known = new Set(['id', 'title', 'element', 'script']);
  for (const [key, value] of Object.entries(manifest)) {
    if (!known.has(key)) {
      el.setAttribute(key.replace(/_/g, '-'), String(value));
    }
  }
  mount.innerHTML = '';
  mount.appendChild(el);
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
