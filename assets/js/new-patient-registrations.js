/* =============================================================
   NEW PATIENT REGISTRATIONS CARD (subadmin/patient_reports.php)
   ---------------------------------------------------------------
   A self-contained search tool: name search, Barangay filter, Date
   Registered filter, sortable columns, and pagination, all refreshed
   via api/get-patient-registrations.php (includes/shared/patient_registrations_query.php)
   without a full page reload. Independent of this page's own page-wide
   filter toolbar — see that shared function's own header comment for
   why.

   window.NPR_INITIAL (the page's own default render — real data, first
   paint needs no fetch) and window.NPR_API_URL are set by this page's
   own inline <script> (see subadmin/patient_reports.php).

   Each real patient's own doc id is rendered into a real
   "patient_profile.php?id=..." link in the Actions menu — intercepted
   by assets/js/patient-consent-gate.js (loaded alongside this file),
   the SAME consent-gate check every other "View Profile" link in this
   app already goes through, so this card never bypasses that
   authorization boundary just because the link was built by JS instead
   of PHP.
   ============================================================= */
function initNewPatientRegistrations() {
    const card = document.getElementById('nprCard');
    if (!card) return;

    const searchInput = document.getElementById('nprSearch');
    const barangaySelect = document.getElementById('nprBarangay');
    const dateSelect = document.getElementById('nprDateRange');
    const tableBody = document.getElementById('nprTableBody');
    const emptyEl = document.getElementById('nprEmpty');
    const showingText = document.getElementById('nprShowingText');
    const paginationEl = document.getElementById('nprPagination');
    const headerRow = card.querySelector('.npr-table thead tr');
    if (!searchInput || !tableBody) return;

    const apiUrl = window.NPR_API_URL;
    const state = { q: '', barangay: '', days: '', sort: 'dateRegistered', dir: 'desc', page: 1 };
    let debounceTimer = null;

    function escapeHtml(s) {
        const div = document.createElement('div');
        div.textContent = s == null ? '' : String(s);
        return div.innerHTML;
    }

    function renderRows(items) {
        tableBody.innerHTML = '';
        if (items.length === 0) {
            if (emptyEl) emptyEl.style.display = '';
            return;
        }
        if (emptyEl) emptyEl.style.display = 'none';
        items.forEach(function (p) {
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td class="npr-name-cell">' + escapeHtml(p.name) + '</td>' +
                '<td>' + p.age + '</td>' +
                '<td>' + escapeHtml(p.gender) + '</td>' +
                '<td>' + escapeHtml(p.barangay) + '</td>' +
                '<td>' + escapeHtml(p.dateRegistered) + '</td>' +
                '<td class="npr-col-actions">' +
                    '<div class="dropdown">' +
                        '<button class="npr-action-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>' +
                        '<ul class="dropdown-menu dropdown-menu-end">' +
                            '<li><a class="dropdown-item" href="patient_profile.php?id=' + encodeURIComponent(p.id) + '"><i class="bi bi-eye me-2"></i>View Details</a></li>' +
                        '</ul>' +
                    '</div>' +
                '</td>';
            tableBody.appendChild(tr);
        });
    }

    function pageBtn(label, targetPage, opts) {
        opts = opts || {};
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'page-btn' + (opts.active ? ' active' : '') + (opts.disabled ? ' disabled' : '');
        b.innerHTML = label;
        if (opts.ariaLabel) b.setAttribute('aria-label', opts.ariaLabel);
        if (!opts.disabled) {
            b.addEventListener('click', function () {
                state.page = targetPage;
                fetchAndRender();
            });
        }
        return b;
    }

    function renderPagination(page, totalPages) {
        if (!paginationEl) return;
        paginationEl.innerHTML = '';
        paginationEl.appendChild(pageBtn('<i class="bi bi-chevron-bar-left"></i>', 1, { disabled: page <= 1, ariaLabel: 'First page' }));
        paginationEl.appendChild(pageBtn('<i class="bi bi-chevron-left"></i>', Math.max(1, page - 1), { disabled: page <= 1, ariaLabel: 'Previous page' }));
        const windowStart = Math.max(1, Math.min(page, Math.max(1, totalPages - 2)));
        const windowEnd = Math.min(totalPages, windowStart + 2);
        for (let p = windowStart; p <= windowEnd; p++) {
            paginationEl.appendChild(pageBtn(String(p), p, { active: p === page }));
        }
        if (windowEnd < totalPages) {
            const span = document.createElement('span');
            span.className = 'page-ellipsis';
            span.textContent = '…';
            paginationEl.appendChild(span);
            paginationEl.appendChild(pageBtn(String(totalPages), totalPages));
        }
        paginationEl.appendChild(pageBtn('<i class="bi bi-chevron-right"></i>', Math.min(totalPages, page + 1), { disabled: page >= totalPages, ariaLabel: 'Next page' }));
        paginationEl.appendChild(pageBtn('<i class="bi bi-chevron-bar-right"></i>', totalPages, { disabled: page >= totalPages, ariaLabel: 'Last page' }));
    }

    function renderShowing(total, page, count) {
        if (!showingText) return;
        if (total === 0) {
            showingText.textContent = 'No entries to show';
            return;
        }
        const from = (page - 1) * 5 + 1;
        const to = from + count - 1;
        showingText.textContent = 'Showing ' + from + ' to ' + to + ' of ' + total.toLocaleString() + ' patients';
    }

    function updateSortIcons() {
        if (!headerRow) return;
        Array.prototype.forEach.call(headerRow.querySelectorAll('th[data-sort]'), function (th) {
            th.classList.remove('sorted-asc', 'sorted-desc');
            if (th.getAttribute('data-sort') === state.sort) {
                th.classList.add(state.dir === 'asc' ? 'sorted-asc' : 'sorted-desc');
            }
        });
    }

    function fetchAndRender() {
        const params = new URLSearchParams({
            q: state.q, barangay: state.barangay, days: state.days,
            sort: state.sort, dir: state.dir, page: String(state.page),
        });
        fetch(apiUrl + '?' + params.toString(), { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json || !json.success) return;
                renderRows(json.items);
                renderPagination(json.page, json.totalPages);
                renderShowing(json.total, json.page, json.items.length);
                updateSortIcons();
            })
            .catch(function () {
                // A real network hiccup — leave whatever's already on screen.
            });
    }

    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            state.q = searchInput.value.trim();
            state.page = 1;
            fetchAndRender();
        }, 300);
    });
    if (barangaySelect) {
        barangaySelect.addEventListener('change', function () {
            state.barangay = barangaySelect.value;
            state.page = 1;
            fetchAndRender();
        });
    }
    if (dateSelect) {
        dateSelect.addEventListener('change', function () {
            state.days = dateSelect.value;
            state.page = 1;
            fetchAndRender();
        });
    }
    if (headerRow) {
        Array.prototype.forEach.call(headerRow.querySelectorAll('th[data-sort]'), function (th) {
            th.addEventListener('click', function () {
                const col = th.getAttribute('data-sort');
                if (state.sort === col) {
                    state.dir = state.dir === 'asc' ? 'desc' : 'asc';
                } else {
                    state.sort = col;
                    state.dir = col === 'dateRegistered' ? 'desc' : 'asc';
                }
                state.page = 1;
                fetchAndRender();
            });
        });
    }

    // Initial pagination/showing-text render from the page's own already-
    // rendered default data — no redundant fetch on first paint.
    const initial = window.NPR_INITIAL;
    if (initial) {
        renderPagination(initial.page, initial.totalPages);
        renderShowing(initial.total, initial.page, initial.items.length);
    }
    updateSortIcons();
}

// No DOMContentLoaded listener of its own — assets/js/patient_reports.js's
// own initReportPageCharts() calls this once, at the right time, same as
// every other card wired in from that file (and correctly re-invoked
// after an AJAX tab swap, which a listener registered here never would
// be).
window.initNewPatientRegistrations = initNewPatientRegistrations;
