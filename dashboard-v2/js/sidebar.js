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

  // Mini DTMF keypad -- reachable from every page, not just /dtmf/'s own
  // full module. Deliberately pared down from that module's 16 keys to
  // the classic 12-key phone layout (1-9, *, 0, #): A-D are rare enough
  // that "go to the full DTMF page" is a fine answer for them, and 12
  // keys actually fits the sidebar's 220px width as something worth
  // calling "mini". No free-text send box here either -- same reasoning,
  // that's what the full page is for. Hits the same api/dtmf.php
  // ?action=key endpoint the module itself uses, not a separate code
  // path. Built here (not as its own module) because the sidebar isn't
  // part of the module-grid system modules render into.
  //
  // On/off switch: a plain localStorage flag (dv2SidebarDtmf, default
  // on), same per-browser pattern as the topbar RX/TX indicator's own
  // switch -- Settings page toggle, picked up live across tabs via the
  // storage event. The element is always built (so the toggle can act on
  // it without a page reload) but hidden via style.display when off --
  // same "author display rule beats the UA [hidden] stylesheet"
  // reasoning js/rxtx-indicator.js's own comment already documents, so
  // this uses the same style.display approach rather than the attribute.
  const footer = document.querySelector('.sidebar-footer');
  if (footer) {
    const DTMF_STORAGE_KEY = 'dv2SidebarDtmf';
    const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '*', '0', '#'];
    const dtmf = document.createElement('div');
    dtmf.className = 'sidebar-dtmf';
    dtmf.id = 'sidebar-dtmf';
    dtmf.innerHTML =
      '<div class="sidebar-dtmf-label">DTMF</div>' +
      '<div class="sidebar-dtmf-keypad">' +
      keys.map((k) => '<button type="button" class="dtmf-key" data-digit="' + k + '">' + k + '</button>').join('') +
      '</div>';
    footer.parentNode.insertBefore(dtmf, footer);

    dtmf.querySelectorAll('.dtmf-key').forEach((btn) => {
      btn.addEventListener('click', () => {
        const digit = btn.getAttribute('data-digit');
        btn.classList.add('pressed');
        setTimeout(() => btn.classList.remove('pressed'), 200);
        fetch('/api/dtmf.php?action=key&digit=' + encodeURIComponent(digit), { cache: 'no-store' }).catch(() => {});
      });
    });

    function dtmfEnabled() {
      try {
        return localStorage.getItem(DTMF_STORAGE_KEY) !== '0';
      } catch (e) {
        return true;
      }
    }
    function applyDtmfVisibility() {
      dtmf.style.display = dtmfEnabled() ? '' : 'none';
    }
    applyDtmfVisibility();
    window.addEventListener('storage', (e) => {
      if (e.key === DTMF_STORAGE_KEY) applyDtmfVisibility();
    });
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
