// Persistent, collapsible sidebar shared by every dashboard-v2 page --
// replaces the old horizontal "pages-nav" chip row (a flat row of chips
// doesn't scale once more pages get added, e.g. a future write-capable
// Setup/WiFi/Power/Backup). Renders into #sidebar-nav (link list) and
// wires up #sidebar-toggle (collapse/expand, state kept in localStorage
// so it stays how a viewer last left it). The route list (href + page id
// + icon) is static and deliberately not renameable from the UI --
// renaming a URL breaks bookmarks/links for no real benefit -- but each
// link's *label* is fetched live from api/page-meta.php, so renaming a
// page from /settings/?page=<id> actually changes what shows up here.
// Adding a future page means adding one entry to this list plus one to
// settings.js's PAGE_META, not editing every page's HTML.
const DASHBOARD_V2_PAGES = [
  { href: '/', id: 'dashboard', icon: '▦' },
  { href: '/qsolog/', id: 'qsolog', icon: '≡' },
  { href: '/rxmonitor/', id: 'rxmonitor', icon: '◉' },
  { href: '/dtmf/', id: 'dtmf', icon: '⌨' },
  { href: '/setup/', id: 'setup', icon: '⚙' },
];

(async function () {
  const nav = document.getElementById('sidebar-nav');
  if (nav) {
    const here = window.location.pathname;
    const withLabels = await Promise.all(DASHBOARD_V2_PAGES.map(async (p) => {
      try {
        const meta = await fetch('/api/page-meta.php?page=' + encodeURIComponent(p.id), { cache: 'no-store' }).then((r) => r.json());
        return { ...p, label: meta.label || p.id };
      } catch (e) {
        return { ...p, label: p.id };
      }
    }));

    nav.innerHTML = withLabels.map((p) => {
      const active = here === p.href || (p.href !== '/' && here.startsWith(p.href));
      return '<a href="' + p.href + '"' + (active ? ' class="active"' : '') +
        '><span class="sidebar-icon">' + p.icon + '</span><span class="sidebar-label">' + p.label + '</span></a>';
    }).join('');
  }

  // Collapse/expand -- a plain width toggle, not a hide/show overlay: this
  // is meant to sit open most of the time on a screen left running, not
  // be opened-closed like a mobile drawer. State persists per browser/
  // viewer, same pattern as every other per-viewer preference in this
  // project (never sent to the server).
  const sidebar = document.getElementById('sidebar');
  const toggle = document.getElementById('sidebar-toggle');
  if (!sidebar || !toggle) return;
  const STORAGE_KEY = 'dv2SidebarCollapsed';

  function applyState(collapsed) {
    sidebar.classList.toggle('collapsed', collapsed);
    toggle.textContent = collapsed ? '›' : '‹';
    try { localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0'); } catch (e) { /* private mode etc -- just won't remember */ }
  }

  toggle.addEventListener('click', () => applyState(!sidebar.classList.contains('collapsed')));

  let startCollapsed = false;
  try { startCollapsed = localStorage.getItem(STORAGE_KEY) === '1'; } catch (e) { /* default to expanded */ }
  applyState(startCollapsed);
})();
