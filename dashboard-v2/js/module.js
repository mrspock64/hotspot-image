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

  // One-shot auto-size to the module's real rendered content, not a
  // fixed guess -- modules are very different heights (Radio Status vs.
  // a long QSO Log list), so shell.js's window.open only picks a
  // starting size. 500ms gives the module's own first poll (its
  // connectedCallback fetches async, nothing here to await) time to
  // replace its "Loading..." placeholder with real content before
  // measuring; resizeTo() is a no-op in browsers on a window that wasn't
  // opened via window.open (e.g. someone just navigated here directly),
  // so this is safe to always attempt. Deliberately one-shot, not a
  // ResizeObserver kept running forever -- once sized, further growth
  // (e.g. QSO Log rows changing) just scrolls within the window rather
  // than fighting a size the user may have since adjusted by hand.
  if (window.opener) {
    setTimeout(() => {
      const chromeWidth = window.outerWidth - window.innerWidth;
      const chromeHeight = window.outerHeight - window.innerHeight;
      const contentWidth = Math.min(720, document.documentElement.scrollWidth);
      const contentHeight = Math.min(900, document.documentElement.scrollHeight);
      try {
        window.resizeTo(contentWidth + chromeWidth, contentHeight + chromeHeight);
      } catch (e) {
        // Some browsers refuse resizeTo entirely depending on window
        // focus/permissions -- the guessed starting size stands as-is.
      }
    }, 500);
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
