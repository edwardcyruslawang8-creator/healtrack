/* =============================================================
   PATIENT HISTORY — RECORD HISTORY TABLE, AJAX REFRESH
   ---------------------------------------------------------------
   Shared by doctor/history.php, medical-staff/history.php, and
   subadmin/history.php. The Type/From/To filter row (.his-filter-row)
   and every pagination link inside #hisRecordTableWrap already render
   as real, working GET links/a real <form> — still real, bookmarkable
   URLs, no-JS fallback — but this intercepts both and fetches
   api/history-record-table.php instead, swapping in just the returned
   table/pagination markup so picking a filter or a page never reloads
   the whole Patient Profile shell (sidebar, topbar, etc.).

   #hisRecordTableWrap itself gets replaced on every refresh (it's the
   literal fragment api/history-record-table.php returns), so
   pagination-link clicks are handled via event delegation on a stable
   ancestor (document) rather than binding directly to links that won't
   exist anymore after the next refresh.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const filterForm = document.querySelector('.his-filter-row');
    if (!filterForm) return;

    function refresh(queryString) {
        const wrap = document.getElementById('hisRecordTableWrap');
        if (!wrap) return;
        wrap.classList.add('his-table-loading');

        fetch('../api/history-record-table.php?' + queryString)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) throw new Error(data.error || 'Could not refresh this table.');

                const temp = document.createElement('div');
                temp.innerHTML = data.html;
                const newWrap = temp.firstElementChild;
                document.getElementById('hisRecordTableWrap').replaceWith(newWrap);

                // Keeps the URL/bookmark/reload behavior consistent with what
                // the filter form/pagination links would have navigated to,
                // without actually triggering a navigation.
                if (window.history && window.history.replaceState) {
                    const url = new URL(window.location.href);
                    const params = new URLSearchParams(queryString);
                    params.forEach(function (value, key) { url.searchParams.set(key, value); });
                    window.history.replaceState(null, '', url);
                }
            })
            .catch(function (err) {
                wrap.classList.remove('his-table-loading');
                HCDialog.alert(err.message);
            });
    }

    // Filter row — the Type select and the From/To date inputs, same
    // fields the real <form method="get"> already submits; resets back
    // to page 1 since the previous page number may not exist under a
    // new filter.
    filterForm.addEventListener('change', function (e) {
        if (!e.target.matches('select, input[type="date"]')) return;
        const params = new URLSearchParams(new FormData(filterForm));
        params.set('recordPage', '1');
        refresh(params.toString());
    });
    filterForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const params = new URLSearchParams(new FormData(filterForm));
        params.set('recordPage', '1');
        refresh(params.toString());
    });

    // Pagination links inside #hisRecordTableWrap — each one is a real
    // "?...&recordPage=N" href (hc_render_pagination_nav()); reuse its
    // own query string verbatim rather than rebuilding it, so it stays
    // correct even if that helper's params ever change.
    document.addEventListener('click', function (e) {
        const link = e.target.closest('#hisRecordTableWrap .page-btn');
        if (!link || link.classList.contains('disabled')) return;
        e.preventDefault();
        const href = link.getAttribute('href') || '';
        const queryString = href.split('?')[1] || '';
        refresh(queryString);
    });
});
