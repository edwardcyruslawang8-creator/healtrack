/* =============================================================
   ACCOUNT MONITORING — AJAX FILTER/SEARCH/PAGINATION
   (admin/account_monitoring.php's "All User Accounts" card only)
   ---------------------------------------------------------------
   Submitting the search box, changing the Role/Status <select>, or
   clicking a Previous/page-number/Next link inside #acmAccountsResults
   all fetch just that table+pagination fragment from
   api/get-account-rows-table.php (the same markup
   includes/admin/_account_rows_table.php renders on a normal page
   load) instead of letting the browser do a full page reload — only
   #acmAccountsResults' own innerHTML swaps, with a short crossfade;
   every other card on the page (stat cards, charts, Accounts Needing
   Attention) never re-renders at all.

   The Role/Status <select>s can't rely on onchange="this.form.submit()"
   here — a real form.submit() call never fires the form's own 'submit'
   event (same reason assets/js/gis.js's own date-range <select> has to
   call gisFilterForm.submit() directly rather than dispatching one),
   so this listens for 'change' on each select and 'submit' on the form
   and routes both into the same fetchAndSwap().

   history.replaceState keeps the visible URL (bookmarkable, and correct
   on a manual refresh) in sync without adding a new browser-history
   entry per filter/page change.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('acmAccountsForm');
    const results = document.getElementById('acmAccountsResults');
    if (!form || !results) return;

    function fetchAndSwap(params) {
        results.classList.add('acm-results-fade-out');

        fetch('../api/get-account-rows-table.php?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (res) {
                if (!res.ok) throw new Error('Request failed');
                return res.text();
            })
            .then(function (html) {
                results.innerHTML = html;
                results.classList.add('acm-results-fade-in');

                // One frame later so the browser actually registers the
                // opacity:0 starting point before animating away from it.
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        results.classList.remove('acm-results-fade-in');
                    });
                });

                const url = new URL(window.location.href);
                url.search = params.toString();
                history.replaceState(null, '', url.pathname + url.search);
                results.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            })
            .catch(function () {
                // A real navigation is still a correct (if less smooth)
                // fallback if the AJAX request itself failed for any reason.
                window.location.href = '?' + params.toString();
            })
            .finally(function () {
                results.classList.remove('acm-results-fade-out');
            });
    }

    function currentParams(overrides) {
        const data = new FormData(form);
        const params = new URLSearchParams();
        params.set('acct_q', (data.get('acct_q') || '').toString().trim());
        params.set('acct_role', (data.get('acct_role') || 'All Roles').toString());
        params.set('acct_status', (data.get('acct_status') || 'All Statuses').toString());
        params.set('acct_page', '1');
        if (overrides) {
            Object.keys(overrides).forEach(function (key) { params.set(key, overrides[key]); });
        }
        return params;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        fetchAndSwap(currentParams());
    });

    form.querySelectorAll('select[name="acct_role"], select[name="acct_status"]').forEach(function (select) {
        select.addEventListener('change', function () {
            fetchAndSwap(currentParams());
        });
    });

    // Delegated (not bound per-link) since #acmAccountsResults' own
    // content — including its pagination nav — gets replaced on every
    // successful fetch.
    results.addEventListener('click', function (e) {
        const link = e.target.closest('.adm-pagination a.page-btn');
        if (!link) return;
        e.preventDefault();

        const targetUrl = new URL(link.href, window.location.href);
        fetchAndSwap(new URLSearchParams(targetUrl.search));
    });
});
