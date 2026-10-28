/* =============================================================
   AUDIT LOGS — LIVE SEARCH
   ---------------------------------------------------------------
   Filters the Audit Logs table as the user types (no Enter, no
   reload), on every role's audit_logs.php whose search form carries
   data-live-search. The filtering itself stays server-side — the same
   hc_filter_audit_logs() the page already runs for ?q= — so it covers
   EVERY page of results, not just the rows on screen, and keeps
   working together with the Module/date-range (and, on admin/subadmin,
   User/Action/Status/role) filters.

   On each (debounced) keystroke this fetches the same page with the
   new ?q= (page reset to 1, every other filter kept), then swaps in
   just the table card and filter toolbar from the response — the
   toolbar too, because its date form's hidden q input and dropdown
   links carry the search term, so changing a filter afterwards never
   drops what was typed. The address bar is updated with
   history.replaceState() so a refresh/bookmark keeps the search.

   Listens at the document level rather than on the input itself:
   admin/subadmin's assets/js/ajax-report-nav.js replaces all of <main>
   (search box included) on every filter/pagination click, which would
   silently drop a listener bound to the original input.
   ============================================================= */
(function () {
    const INPUT_SELECTOR = 'form.aud-search-bar[data-live-search] input[name="q"]';
    const DEBOUNCE_MS = 250;
    let debounceTimer = null;
    let inFlight = null;

    function buildUrl(term) {
        const url = new URL(window.location.href);
        if (term) {
            url.searchParams.set('q', term);
        } else {
            url.searchParams.delete('q');
        }
        url.searchParams.delete('page'); // new search -> start at page 1
        return url;
    }

    function swap(selector, doc) {
        const current = document.querySelector(selector);
        const next = doc.querySelector(selector);
        if (current && next) current.replaceWith(next);
    }

    function runSearch(input) {
        // The page was swapped out from under this keystroke (e.g. an
        // autocomplete pick on admin/subadmin navigated elsewhere) —
        // whatever is on screen now is newer than this search.
        if (!document.contains(input)) return;

        const url = buildUrl(input.value.trim());

        if (inFlight) inFlight.abort(); // a newer keystroke wins over a slower older request
        inFlight = new AbortController();

        fetch(url.toString(), { signal: inFlight.signal, headers: { 'X-Requested-With': 'fetch' } })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.text();
            })
            .then(function (html) {
                if (!document.contains(input)) return; // same reason as above
                const doc = new DOMParser().parseFromString(html, 'text/html');
                swap('.aud-table-card', doc);
                swap('.aud-filter-toolbar', doc);
                window.history.replaceState(window.history.state, '', url.pathname + url.search);
                // admin/subadmin's sortable column headers live inside the
                // swapped table — re-bind them like ajax-report-nav.js does.
                if (typeof window.initSortableTables === 'function') window.initSortableTables();
            })
            .catch(function (err) {
                if (err.name === 'AbortError') return;
                // Network/session hiccup — fall back to a normal page load so
                // the user still gets their results (or the login page).
                window.location.href = url.toString();
            });
    }

    document.addEventListener('input', function (e) {
        const input = e.target.closest ? e.target.closest(INPUT_SELECTOR) : null;
        if (!input) return;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () { runSearch(input); }, DEBOUNCE_MS);
    });

    // Searching is live, so Enter has nothing left to submit.
    document.addEventListener('submit', function (e) {
        if (e.target.matches && e.target.matches('form.aud-search-bar[data-live-search]')) {
            e.preventDefault();
        }
    });
})();
