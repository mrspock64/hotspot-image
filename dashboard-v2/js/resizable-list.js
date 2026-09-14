// Persists a viewer-dragged height for a module's resizable list (CSS
// `resize: vertical`, see tokens.css's shared .activity-list/.qso-list
// rule) across poll-driven re-renders and future visits. The native
// resize handle already gives free-form dragging; this only adds
// memory -- without it, every render() rebuild (this.innerHTML = ...)
// would throw away the drag and snap back to the default height, which
// defeats the point.
//
// Call once per render, right after the new list element exists in the
// DOM (same call site the scrollTop-preservation fix already uses) --
// the element gets destroyed and recreated on every rebuild, so there's
// nothing to persist across otherwise.
//
// selector defaults to '.activity-list' (Reflector Activity, Monitored
// Talkgroups, Quick Buttons edit mode); QSO Log passes '.qso-list'
// explicitly -- confirmed live 2026-09-14 that hardcoding just the one
// class silently no-op'd for any module using a different list class,
// no error, just a resize handle that never remembered anything.
window.dv2PersistResizableList = function (container, storageKey, selector) {
  const list = container.querySelector(selector || '.activity-list');
  if (!list) return;

  let saved;
  try {
    saved = localStorage.getItem(storageKey);
  } catch (e) {
    saved = null;
  }
  if (saved) {
    list.style.height = saved + 'px';
  }

  // A fresh node every render means a fresh observer every render too --
  // the old one has nothing left to observe once its target is replaced,
  // and is garbage collected along with it.
  const observer = new ResizeObserver(() => {
    try {
      localStorage.setItem(storageKey, String(Math.round(list.getBoundingClientRect().height)));
    } catch (e) {
      // Private browsing etc. -- the resize handle still works, it just
      // won't be remembered next time.
    }
  });
  observer.observe(list);
};
