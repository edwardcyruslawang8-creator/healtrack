/* =============================================================
   AJAX REPORT NAV (smooth, no-reload page swap)
   ---------------------------------------------------------------
   Shared by every subadmin Analytics & Reports report page (Overview,
   Patient/Inventory/Stock Request/Consultation/Vaccine Reports), and
   now admin/audit_logs.php + subadmin/audit_logs.php too.

   Every filter dropdown link and pagination link on the report pages
   is a real "?param=value" link to the SAME page (see
   includes/shared/query_helpers.php's hc_url_with_params() and
   includes/shared/pagination_helpers.php's hc_render_pagination_nav() —
   both only ever emit that exact shape); audit_logs.php's own filter/
   pagination links use that identical bare "?query" shape too
   (includes/{admin,subadmin}/*_audit_logs_firestore.php's
   hc_audit_query()). Either way, a click never needs to leave the page
   at all: this fetches that same URL, then swaps just
   <main class="adm-main"> (everything below the sidebar — filter bar,
   KPI cards/table, pagination) in place instead of letting the browser
   do a full navigation. No sidebar/topbar flash, no white blank frame,
   scroll position stays put.

   audit_logs.php ALSO has same-page links that live in the SIDEBAR,
   outside <main> — the Audit Logs submenu's Sub Admin/Doctor/Medical
   Staff/etc. role switch (subadmin/audit_logs.php's own $adminNavItems)
   and admin's equivalent. Those are shaped "audit_logs.php?role=..."
   (this page's own filename + query), not a bare "?query" — matched
   below by a SECOND pattern, checked anywhere on the page rather than
   just inside <main>, since the sidebar itself isn't swapped. Clicking
   one of those also updates which sidebar submenu item carries
   ".active" client-side (see syncSidebarActiveState() below), since an
   innerHTML swap of <main> alone can't touch sidebar markup that lives
   outside it. Links to a genuinely different page (report tabs, "View
   All", breadcrumbs, every other sidebar nav item) are untouched real
   navigations, since neither pattern matches them.

   A single card's own more-specific AJAX handler (e.g. the
   Consultations Over Time range switch in assets/js/analytics_reports.js,
   which updates just its own chart via Chart.js's .update() instead of
   reloading the whole page) always wins over this: it's bound directly
   on its own <a> and calls preventDefault() first, so by the time this
   delegated document-level listener sees the click, e.defaultPrevented
   is already true and it steps aside.

   After a successful swap, this re-invokes the same already-idempotent
   init functions a real page load itself calls once on DOMContentLoaded
   — window.initReportPageCharts() (assets/js/{page}_reports.js —
   rebuilds every Chart.js instance from fresh data, destroying the old
   ones first), window.initSortableTables() (assets/js/sortable-table.js),
   and window.initAuditLogsSearch() (assets/js/audit-logs-search.js —
   re-binds the search box's autocomplete to the freshly swapped-in
   #auditSearchInput/#auditSearchSuggest, since the old ones (and their
   listeners) were just discarded) — so the swapped-in content ends up
   in exactly the same state a full reload would have produced. The
   page's own inline "window.SOME_DATA = {...}" data script
   (id="reportDataScript" or id="auditUserListScript") sits inside
   <main> but needs its own handling regardless: mutating a <script>
   tag's text never re-runs it, so a freshly-created <script> element is
   used instead, which browsers do execute on insert.

   Falls back to a real navigation (setting window.location.href) if
   the fetch fails for any reason — this is a pure enhancement on top of
   already-working real links, never a dependency for the pages to work.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const main = document.querySelector('main.adm-main');
    if (!main) return;

    const currentFile = location.pathname.split('/').pop();
    const dataScriptIds = ['reportDataScript', 'auditUserListScript'];

    function syncSidebarActiveState(clickedLink) {
        // The sidebar itself is never swapped (only <main> is), so a
        // same-page link living in it (the Audit Logs submenu's role
        // switch) needs its own ".active" class toggled by hand —
        // scoped to its own <ul> so this never touches an unrelated
        // submenu elsewhere in the sidebar.
        const submenu = clickedLink.closest('ul');
        if (!submenu) return;
        submenu.querySelectorAll('a').forEach(function (a) { a.classList.remove('active'); });
        clickedLink.classList.add('active');
    }

    function swapTo(url, pushHistory) {
        main.classList.add('adm-main-loading');

        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) {
                if (!res.ok) throw new Error('Request failed');
                return res.text();
            })
            .then(function (html) {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const newMain = doc.querySelector('main.adm-main');
                if (!newMain) throw new Error('Unexpected response shape');

                main.innerHTML = newMain.innerHTML;

                dataScriptIds.forEach(function (id) {
                    const newDataScript = doc.getElementById(id);
                    const oldDataScript = document.getElementById(id);
                    if (newDataScript && oldDataScript) {
                        const freshScript = document.createElement('script');
                        freshScript.id = id;
                        freshScript.textContent = newDataScript.textContent;
                        oldDataScript.replaceWith(freshScript);
                    }
                });

                if (typeof window.initReportPageCharts === 'function') window.initReportPageCharts();
                if (typeof window.initSortableTables === 'function') window.initSortableTables();
                if (typeof window.initAuditLogsSearch === 'function') window.initAuditLogsSearch();

                if (pushHistory) window.history.replaceState({}, '', url);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .catch(function () {
                window.location.href = url; // fall back to a real navigation
            })
            .finally(function () {
                main.classList.remove('adm-main-loading');
            });
    }

    // Exposed so a page's own script (e.g. audit-logs-search.js's user
    // autocomplete, which used to force a real navigation) can reuse
    // this exact same smooth-swap instead of duplicating it.
    window.hcAjaxNavigateTo = swapTo;

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented) return; // a more specific handler (e.g. a single card's own AJAX) already took this click

        const link = e.target.closest('a');
        if (!link) return;

        const href = link.getAttribute('href');
        if (!href) return;

        const isBareQuery = href.charAt(0) === '?' && main.contains(link);
        const isSamePageLink = (href === currentFile || href.indexOf(currentFile + '?') === 0);
        if (!isBareQuery && !isSamePageLink) return; // only same-page filter/pagination/role-switch links are smooth-swapped

        e.preventDefault();
        if (!main.contains(link)) syncSidebarActiveState(link);
        swapTo(href, true);
    });

    // The "Custom Range" mini date-picker form inside the date filter
    // dropdown (includes/shared/date_range_filter_dropdown.php), and
    // audit_logs.php's own search bar / date-range forms, are real GET
    // <form>s, not links — smooth-swapped the same way.
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !main.contains(form)) return;
        if ((form.method || 'get').toLowerCase() !== 'get') return;

        e.preventDefault();
        const params = new URLSearchParams(new FormData(form));
        swapTo('?' + params.toString(), true);
    });
});
