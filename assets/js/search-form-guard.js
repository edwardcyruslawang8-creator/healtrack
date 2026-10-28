/* =============================================================
   SEARCH FORM GUARD (shared, loaded on every page with a real
   "Search" toolbar — see each page's own <script> tag for this file)
   ---------------------------------------------------------------
   Blocks submitting a search with nothing typed — previously each of
   these toolbar forms (method="get", reloading the page with the
   typed term as a query param) just silently submitted an empty
   filter, which looked identical to "View All" rather than erroring.
   Same fix originally built one-off for
   subadmin/medicine_vaccine_inventory.php, generalized here so every
   page's search bar gets it without hand-editing each one.

   Auto-detects which <form>s on the page are real search bars rather
   than needing a class name or data attribute on every one of them
   (dozens of different toolbar class names exist across this app —
   pmg-toolbar, usm-toolbar, srq-toolbar, aud-search-bar, mvi-toolbar,
   hero-search, search-page-bar, ...): a form counts if it has a real
   action="..." (so a JS-intercepted modal form like "Dispense Without
   Prescription", which has no action attribute and its own
   preventDefault()+fetch() submit handler, is never touched) AND
   contains a bi-search icon (this app's universal visual marker for
   "this is a search box") AND has EXACTLY ONE text input (if a form
   has zero or more than one, which input is "the" search field is
   ambiguous, so it's skipped rather than guessed at — no such form
   exists in this app today, but a future one wouldn't be silently
   mishandled).

   Validates on two real triggers, not the form's generic "submit"
   event: the Enter key inside the search input itself, and a click on
   the form's own Search button (if it has one — some pages, e.g.
   subadmin/user_management.php, only ever submit via Enter, no
   separate button). NOT a submit-event listener, specifically so a
   filter <select> elsewhere in the same form (onchange="this.form.submit()")
   is never caught by this — changing a category/status filter with an
   empty search box is completely normal, not an error.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[action]').forEach(function (form) {
        if (!form.querySelector('i.bi-search')) return;
        // Live-search bars (e.g. assets/js/audit-logs-live-search.js) filter
        // as you type — an empty box just means "show everything" there.
        if (form.hasAttribute('data-live-search')) return;

        var textInputs = form.querySelectorAll('input[type="text"], input[type="search"]');
        if (textInputs.length !== 1) return;
        var input = textInputs[0];

        function blockIfEmpty(e) {
            if (input.value.trim() === '') {
                e.preventDefault();
                if (window.HCDialog) {
                    HCDialog.alert('Please type something to search for.');
                } else {
                    window.alert('Please type something to search for.');
                }
                input.focus();
            }
        }

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') blockIfEmpty(e);
        });

        var searchBtn = form.querySelector('button[type="submit"]');
        if (searchBtn) {
            searchBtn.addEventListener('click', blockIfEmpty);
        }
    });
});
