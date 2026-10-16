/* =============================================================
   QUICK SEARCH DROPDOWN (assets/js/quick-search.js)
   ---------------------------------------------------------------
   Generic, reusable live-typeahead dropdown — attaches to any
   <input data-quick-search="…endpoint.php" data-quick-search-view=
   "target.php?id={id}"> on the page. Currently used by:
     - patientSearchInput on subadmin/, medical-staff/, and doctor/'s
       patient_management.php (api/quick-search-patients.php — a
       PATIENT here meaning a medical record, not a site visitor),
       data-quick-search-view set -> each result is a link to that
       patient's profile page.
     - the Medicines/Vaccines search boxes on subadmin/ and
       pharmacist/'s medicine_vaccine_inventory.php
       (api/quick-search-medicines.php), NO data-quick-search-view —
       there's no per-medicine detail page in this app, so each result
       instead fills the exact medicine name into the search box and
       submits the page's own filtered search (same as clicking "View
       all results", just pre-aimed at one exact item).
     - the PUBLIC side — index.php's hero search, patient/medicine.php,
       patient/announcements.php, patient/search.php (this "patient/"
       is the public site visitor role, api/quick-search-patient.php,
       no login/session involved at all). Also no data-quick-search-view
       (no per-item detail pages here either) — same fill-and-submit
       behavior. These pages wrap just their icon+input in a ".qs-field"
       div rather than handing quick-search.js their whole <form> (see
       assets/css/quick-search.css's header comment for why).
   Nothing here is patient- or medicine-specific — any endpoint
   returning {results: [{id?, name, meta, icon?, iconBg?, iconColor?}],
   total} works against this same file (id is only needed when
   data-quick-search-view or data-quick-search-select is set).

   A third mode — data-quick-search-select="someHiddenInputId" — picks a
   result INTO the page instead of navigating or submitting: clicking a
   row fills the search input with its name, fills the named hidden
   input with its real id, and fires a "quick-search:select" CustomEvent
   on the search input (detail: the whole result object) so the calling
   page's own JS can react (e.g. enable a submit button, show the
   picked patient's details). Used by pharmacist/medicine_vaccine_inventory.php's
   "Dispense Without Prescription" modal to pick a real patient without
   leaving the modal. Takes priority over data-quick-search-view if
   both were somehow set.

   Every endpoint's fuzzy matching (includes/shared/fuzzy_search.php's
   hc_fuzzy_match()) is the SAME matching each page's full-page table
   search already uses — this file only adds a live dropdown UI on
   top of matching that already exists; it doesn't add or change any
   matching logic itself.

   Doesn't touch Enter/submit behavior at all: the input is still a
   normal <input name="q"> inside its page's <form>, so pressing Enter
   or clicking the page's own "Search" button submits the full
   server-side filtered search exactly as before. This dropdown is a
   supplementary quick-glance layer on top, not a replacement for it.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-quick-search]').forEach(initQuickSearch);

    function initQuickSearch(input) {
        const endpoint = input.dataset.quickSearch;
        const viewTemplate = input.dataset.quickSearchView || '';
        const selectTargetId = input.dataset.quickSearchSelect || '';
        const selectTarget = selectTargetId ? document.getElementById(selectTargetId) : null;
        const wrapper = input.closest('.qs-field') || input.closest('.pmg-search') || input.parentElement;
        if (!wrapper) return;

        wrapper.classList.add('qs-wrapper');

        // Clear ("x") button — only visible once there's text.
        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'qs-clear-btn';
        clearBtn.setAttribute('aria-label', 'Clear search');
        clearBtn.innerHTML = '<i class="bi bi-x-circle-fill"></i>';
        clearBtn.style.display = 'none';
        wrapper.appendChild(clearBtn);

        // Dropdown panel, positioned under the input.
        const dropdown = document.createElement('div');
        dropdown.className = 'qs-dropdown';
        dropdown.hidden = true;
        wrapper.appendChild(dropdown);

        let debounceTimer = null;
        let abortController = null;
        let lastQuery = '';

        function updateClearBtn() {
            clearBtn.style.display = input.value.length > 0 ? 'flex' : 'none';
        }

        function closeDropdown() {
            dropdown.hidden = true;
            dropdown.innerHTML = '';
            wrapper.classList.remove('qs-active');
        }

        function renderResults(query, data) {
            const results = data.results || [];
            const total = data.total || 0;

            wrapper.classList.add('qs-active');

            if (results.length === 0) {
                dropdown.innerHTML =
                    '<div class="qs-header">Quick Search Results</div>' +
                    '<div class="qs-empty">No results for "' + escapeHtml(query) + '"</div>';
                dropdown.hidden = false;
                return;
            }

            const form = input.closest('form');

            let html = '<div class="qs-header">Quick Search Results</div><div class="qs-results-list">';
            results.forEach(function (item, index) {
                const meta = item.meta || '';
                const tag = selectTarget ? 'button' : (viewTemplate ? 'a' : 'button');
                const attrs = selectTarget
                    ? ' type="button" data-select-index="' + index + '"'
                    : (viewTemplate
                        ? ' href="' + viewTemplate.replace('{id}', encodeURIComponent(item.id)) + '"'
                        : ' type="button" data-fill="' + escapeHtml(item.name) + '"');
                html +=
                    '<' + tag + ' class="qs-result-row" data-index="' + index + '"' + attrs + '>' +
                        '<span class="qs-result-body">' +
                            '<span class="qs-result-name">' + escapeHtml(item.name) + '</span>' +
                            '<span class="qs-result-meta">' + meta + '</span>' +
                        '</span>' +
                        '<span class="qs-result-view">' + (selectTarget ? 'Select' : 'View') + '</span>' +
                    '</' + tag + '>';
            });
            html += '</div>';

            // "View all results" only makes sense for the navigate/fill-and-submit
            // modes — a select-mode field (a modal, not a filtered page) has
            // nowhere for that link to go.
            if (!selectTarget) {
                html +=
                    '<button type="button" class="qs-view-all" data-query="' + escapeHtml(query) + '">' +
                        '<span>View all results for "' + escapeHtml(query) + '"' + (total > results.length ? ' (' + total + ')' : '') + '</span>' +
                        '<i class="bi bi-chevron-right"></i>' +
                    '</button>';
            }

            dropdown.innerHTML = html;
            dropdown.hidden = false;

            if (selectTarget) {
                dropdown.querySelectorAll('.qs-result-row[data-select-index]').forEach(function (row) {
                    row.addEventListener('click', function () {
                        const item = results[parseInt(row.dataset.selectIndex, 10)];
                        if (!item) return;
                        input.value = item.name;
                        selectTarget.value = item.id;
                        closeDropdown();
                        input.dispatchEvent(new CustomEvent('quick-search:select', { detail: item, bubbles: true }));
                    });
                });
            } else if (!viewTemplate && form) {
                dropdown.querySelectorAll('.qs-result-row[data-fill]').forEach(function (row) {
                    row.addEventListener('click', function () {
                        const item = results[parseInt(row.dataset.index, 10)];
                        input.value = row.dataset.fill;
                        // Fired BEFORE submit (synchronous) so a page's own JS
                        // can react to which exact result was picked — e.g.
                        // patient/medicine.php's shared search box uses this
                        // to route to the right tab for the clicked item.
                        input.dispatchEvent(new CustomEvent('quick-search:fill', { detail: item, bubbles: true }));
                        form.submit();
                    });
                });
            }

            const viewAllBtn = dropdown.querySelector('.qs-view-all');
            if (viewAllBtn && form) {
                viewAllBtn.addEventListener('click', function () {
                    input.value = viewAllBtn.dataset.query;
                    form.submit();
                });
            }
        }

        function fetchResults(query) {
            if (abortController) abortController.abort();
            abortController = new AbortController();

            const separator = endpoint.indexOf('?') === -1 ? '?' : '&';
            fetch(endpoint + separator + 'q=' + encodeURIComponent(query), { signal: abortController.signal })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (query !== lastQuery) return; // stale response, a newer keystroke has since fired
                    renderResults(query, data);
                })
                .catch(function (err) {
                    if (err.name !== 'AbortError') closeDropdown();
                });
        }

        input.addEventListener('input', function () {
            updateClearBtn();
            // In select mode, once picked, the hidden id only stays valid for
            // as long as the visible text still matches it — any further real
            // typing invalidates that pick (this only fires on genuine user
            // input, not the programmatic input.value set a selection makes),
            // so a stale id can't silently ride along to submission.
            if (selectTarget) selectTarget.value = '';

            const query = input.value.trim();
            lastQuery = query;

            clearTimeout(debounceTimer);
            if (query === '') {
                closeDropdown();
                return;
            }
            debounceTimer = setTimeout(function () { fetchResults(query); }, 250);
        });

        input.addEventListener('focus', function () {
            if (input.value.trim() !== '' && dropdown.innerHTML !== '') {
                dropdown.hidden = false;
                wrapper.classList.add('qs-active');
            }
        });

        clearBtn.addEventListener('click', function () {
            input.value = '';
            if (selectTarget) selectTarget.value = '';
            updateClearBtn();
            closeDropdown();
            input.focus();
        });

        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) closeDropdown();
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeDropdown();
        });

        updateClearBtn();
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
