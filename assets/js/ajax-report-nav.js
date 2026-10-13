/* =============================================================
   AJAX REPORT NAV (smooth, no-reload page swap)
   ---------------------------------------------------------------
   Shared by every subadmin Analytics & Reports report page (Overview,
   Patient/Inventory/Stock Request/Consultation/Vaccine Reports).

   Every filter dropdown link and pagination link on these pages is a
   real "?param=value" link to the SAME page (see
   includes/shared/query_helpers.php's hc_url_with_params() and
   includes/shared/pagination_helpers.php's hc_render_pagination_nav() —
   both only ever emit that exact shape), so a click never needs to
   leave the page at all: this fetches that same URL, then swaps just
   <main class="adm-main"> (everything below the sidebar — filter bar,
   KPI cards, charts, tables, pagination) in place instead of letting
   the browser do a full navigation. No sidebar/topbar flash, no white
   blank frame, scroll position stays put. Links to a genuinely
   different page (report tabs, "View All", breadcrumbs) are untouched
   real navigations, since none of those are written as a bare "?..."
   href — only same-page filter/pagination controls are.

   A single card's own more-specific AJAX handler (e.g. the
   Consultations Over Time range switch in assets/js/analytics_reports.js,
   which updates just its own chart via Chart.js's .update() instead of
   reloading the whole page) always wins over this: it's bound directly
   on its own <a> and calls preventDefault() first, so by the time this
   delegated document-level listener sees the click, e.defaultPrevented
   is already true and it steps aside.

   After a successful swap, this re-invokes the same three
   already-idempotent init functions a real page load itself calls once
   on DOMContentLoaded — window.initReportPageCharts()
   (assets/js/{page}_reports.js — rebuilds every Chart.js instance from
   fresh data, destroying the old ones first) and
   window.initSortableTables() (assets/js/sortable-table.js) — so the
   swapped-in content ends up in exactly the same state a full reload
   would have produced. The page's own inline "window.SOME_DATA = {...}"
   <script id="reportDataScript"> sits just above </body>, not inside
   <main>, so it's swapped separately: mutating a <script> tag's text
   never re-runs it, so a freshly-created <script> element is used
   instead, which browsers do execute on insert.

   Falls back to a real navigation (setting window.location.href) if
   the fetch fails for any reason — this is a pure enhancement on top of
   already-working real links, never a dependency for the pages to work.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const main = document.querySelector('main.adm-main');
    if (!main) return;

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

                const newDataScript = doc.getElementById('reportDataScript');
                const oldDataScript = document.getElementById('reportDataScript');
                if (newDataScript && oldDataScript) {
                    const freshScript = document.createElement('script');
                    freshScript.id = 'reportDataScript';
                    freshScript.textContent = newDataScript.textContent;
                    oldDataScript.replaceWith(freshScript);
                }

                if (typeof window.initReportPageCharts === 'function') window.initReportPageCharts();
                if (typeof window.initSortableTables === 'function') window.initSortableTables();

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

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented) return; // a more specific handler (e.g. a single card's own AJAX) already took this click

        const link = e.target.closest('a');
        if (!link || !main.contains(link)) return;

        const href = link.getAttribute('href');
        if (!href || href.charAt(0) !== '?') return; // only same-page "?query" links are smooth-swapped

        e.preventDefault();
        swapTo(href, true);
    });

    // The "Custom Range" mini date-picker form inside the date filter
    // dropdown (includes/shared/date_range_filter_dropdown.php) is a
    // real GET <form>, not a link — smooth-swapped the same way.
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !main.contains(form)) return;
        if ((form.method || 'get').toLowerCase() !== 'get') return;

        e.preventDefault();
        const params = new URLSearchParams(new FormData(form));
        swapTo('?' + params.toString(), true);
    });
});
