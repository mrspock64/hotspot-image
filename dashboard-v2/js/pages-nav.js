// Renders the Dashboard/QSO Log/... nav row into #pages-nav. The route
// list (href + page id) is static and deliberately not renameable from
// the UI -- renaming a URL breaks bookmarks/links for no real benefit --
// but each chip's *label* is fetched live from api/page-meta.php, so
// renaming a page from /settings/?page=<id> actually changes what shows
// up here. Adding a future page (once the write-heavy ones like Setup
// get attempted) means adding one entry to this list, not editing every
// page's HTML.
const DASHBOARD_V2_PAGES = [
  { href: '/', id: 'dashboard' },
  { href: '/qsolog/', id: 'qsolog' },
  { href: '/rxmonitor/', id: 'rxmonitor' },
  { href: '/setup/', id: 'setup' },
];

(async function () {
  const nav = document.getElementById('pages-nav');
  if (!nav) return;
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
    return '<a href="' + p.href + '"' + (active ? ' class="active"' : '') + '>' + p.label + '</a>';
  }).join('');
})();
