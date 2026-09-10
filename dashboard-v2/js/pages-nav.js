// Renders the Dashboard/QSO Log/... nav row into #pages-nav. One shared
// list here rather than duplicated markup per page -- adding a future
// page (RX Monitor, then eventually the write-heavy ones like Setup)
// means adding one entry here, not editing every page's HTML.
const DASHBOARD_V2_PAGES = [
  { href: '/', label: 'Dashboard' },
  { href: '/qsolog/', label: 'QSO Log' },
];

(function () {
  const nav = document.getElementById('pages-nav');
  if (!nav) return;
  const here = window.location.pathname;
  nav.innerHTML = DASHBOARD_V2_PAGES.map((p) => {
    const active = here === p.href || (p.href !== '/' && here.startsWith(p.href));
    return '<a href="' + p.href + '"' + (active ? ' class="active"' : '') + '>' + p.label + '</a>';
  }).join('');
})();
