/* =============================================================
   INVENTORY REPORTS JS (subadmin/inventory_reports.php)
   ---------------------------------------------------------------
   Renders every Chart.js instance on the page from the data PHP
   printed into window.INVENTORY_REPORTS_DATA (see the inline <script>
   in subadmin/inventory_reports.php) — same pattern as
   assets/js/analytics_reports.js / assets/js/patient_reports.js. The
   mobile sidebar toggle lives in assets/js/admin-sidebar-toggle.js
   instead, since it's shared by every admin/subadmin page, not just
   this one.

   Wrapped in window.initReportPageCharts() rather than run inline, so
   assets/js/ajax-report-nav.js's whole-page smooth content swap can
   re-invoke it after replacing <main> — same convention every sibling
   report page's own JS file follows. Previously-created Chart
   instances are destroyed first, since the swap leaves their old
   <canvas> elements orphaned.
   ============================================================= */
var __inventoryReportsCharts = []; // var, not let: ajax-tab-nav.js re-injects this file on each tab visit

function initReportPageCharts() {
    __inventoryReportsCharts.forEach(function (c) { c.destroy(); });
    __inventoryReportsCharts = [];

    // This page's charts only: assets/js/ajax-tab-nav.js re-fires
    // DOMContentLoaded after every tab swap, and every report page's
    // init stays registered — without this, e.g. the Overview's would
    // draw into Consultation Reports' same-id consultationsByTypeChart
    // and make this page's own init fail with "Canvas is already in use".
    if (!document.getElementById('inventoryStatusOverviewChart')) return;

    const data = window.INVENTORY_REPORTS_DATA;
    if (!data || typeof Chart === 'undefined') return;

    /* ---------- Reusable doughnut renderer (status overview + by category) ---------- */
    function renderDoughnut(canvasId, dataset) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        const chart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: dataset.labels,
                datasets: [{
                    data: dataset.values,
                    backgroundColor: dataset.colors,
                    borderWidth: 0,
                    hoverOffset: 4,
                }],
            },
            options: {
                cutout: '68%',
                plugins: {
                    legend: { display: false }, // custom legend rendered in HTML instead
                    tooltip: {
                        callbacks: {
                            label: function (ctx) { return ctx.label + ': ' + ctx.parsed; },
                        },
                    },
                },
            },
        });
        __inventoryReportsCharts.push(chart);
    }

    renderDoughnut('inventoryStatusOverviewChart', data.inventoryStatusOverview);
    renderDoughnut('inventoryByCategoryChart', data.inventoryByCategory);

    initStockMovementCard(data);
    initExpiryMonitorCard(data);
}

/* =============================================================
   STOCK MOVEMENT SUMMARY CARD
   ---------------------------------------------------------------
   Date-range filter (7/30/90/custom) re-fetches real Opening/Received/
   Issued/Closing figures from api/get-stock-movement.php
   (includes/shared/inventory_movement_monitor.php's own
   hc_compute_stock_movement_summary() — same function the page's own
   default render already used). "View Stock Movements" fetches the
   real underlying transaction list for whatever range is currently
   selected, fresh, each time the modal opens.
   ============================================================= */
function initStockMovementCard(data) {
    const rangeSelect = document.getElementById('irMovementRange');
    if (!rangeSelect) return;

    const customRangeBox = document.getElementById('irMovementCustomRange');
    const startInput = document.getElementById('irMovementStartDate');
    const endInput = document.getElementById('irMovementEndDate');
    const applyBtn = document.getElementById('irMovementApplyRange');
    const tableBody = document.getElementById('irMovementTableBody');
    const totalReceivedEl = document.getElementById('irMovementTotalReceived');
    const totalIssuedEl = document.getElementById('irMovementTotalIssued');
    const closingStockEl = document.getElementById('irMovementClosingStock');
    const footOpening = document.getElementById('irMovementFootOpening');
    const footReceived = document.getElementById('irMovementFootReceived');
    const footIssued = document.getElementById('irMovementFootIssued');
    const footClosing = document.getElementById('irMovementFootClosing');

    const movementsModalEl = document.getElementById('irMovementsModal');
    const movementsTableBody = document.getElementById('irMovementsModalTableBody');
    const movementsEmpty = document.getElementById('irMovementsModalEmpty');

    const apiUrl = data.stockMovementApiUrl;
    let currentRangeParams = 'days=30';

    function renderSummary(summary) {
        if (tableBody) {
            tableBody.innerHTML = '';
            summary.byCategory.forEach(function (row) {
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>' + escapeHtmlIR(row.category) + '</td>' +
                    '<td>' + row.opening.toLocaleString() + '</td>' +
                    '<td>' + row.received.toLocaleString() + '</td>' +
                    '<td>' + row.issued.toLocaleString() + '</td>' +
                    '<td>' + row.closing.toLocaleString() + '</td>';
                tableBody.appendChild(tr);
            });
        }
        if (totalReceivedEl) totalReceivedEl.textContent = summary.total.received.toLocaleString();
        if (totalIssuedEl) totalIssuedEl.textContent = summary.total.issued.toLocaleString();
        if (closingStockEl) closingStockEl.textContent = summary.total.closing.toLocaleString();
        if (footOpening) footOpening.textContent = summary.total.opening.toLocaleString();
        if (footReceived) footReceived.textContent = summary.total.received.toLocaleString();
        if (footIssued) footIssued.textContent = summary.total.issued.toLocaleString();
        if (footClosing) footClosing.textContent = summary.total.closing.toLocaleString();
    }

    function fetchSummary(rangeParams) {
        currentRangeParams = rangeParams;
        fetch(apiUrl + '?action=summary&' + rangeParams, { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (json) { if (json && json.success) renderSummary(json.summary); })
            .catch(function () {
                // A real network hiccup on a secondary filter — leave whatever's already on screen.
            });
    }

    rangeSelect.addEventListener('change', function () {
        if (rangeSelect.value === 'custom') {
            if (customRangeBox) customRangeBox.hidden = false;
            return;
        }
        if (customRangeBox) customRangeBox.hidden = true;
        fetchSummary('days=' + encodeURIComponent(rangeSelect.value));
    });
    if (applyBtn) {
        applyBtn.addEventListener('click', function () {
            const start = startInput ? startInput.value : '';
            const end = endInput ? endInput.value : '';
            if (!start || !end || start > end) return;
            fetchSummary('start=' + encodeURIComponent(start) + '&end=' + encodeURIComponent(end));
        });
    }

    if (movementsModalEl) {
        movementsModalEl.addEventListener('shown.bs.modal', function () {
            fetch(apiUrl + '?action=transactions&' + currentRangeParams, { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    if (!json || !json.success || !movementsTableBody) return;
                    movementsTableBody.innerHTML = '';
                    if (json.transactions.length === 0) {
                        if (movementsEmpty) movementsEmpty.style.display = '';
                        return;
                    }
                    if (movementsEmpty) movementsEmpty.style.display = 'none';
                    json.transactions.forEach(function (t) {
                        const tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td>' + escapeHtmlIR(t.date) + '</td>' +
                            '<td><span class="ir-movement-type-badge ir-movement-type-' + t.type + '">' + (t.type === 'received' ? 'Received' : 'Issued') + '</span></td>' +
                            '<td>' + escapeHtmlIR(t.itemName) + '</td>' +
                            '<td>' + escapeHtmlIR(t.category) + '</td>' +
                            '<td>' + t.quantity.toLocaleString() + ' ' + escapeHtmlIR(t.unit) + '</td>' +
                            '<td>' + escapeHtmlIR(t.detail) + '</td>';
                        movementsTableBody.appendChild(tr);
                    });
                })
                .catch(function () {});
        });
    }
}

/* =============================================================
   INVENTORY EXPIRY MONITOR CARD
   ---------------------------------------------------------------
   The full real per-batch list (data.expiryAllItems —
   hc_compute_inventory_expiry_monitor()'s own output) was already
   embedded by PHP, so every filter/search/sort/pagination here stays
   entirely client-side — no extra fetch needed for a dataset this
   small (one row per real batch across the whole real inventory).
   ============================================================= */
function initExpiryMonitorCard(data) {
    const filterSeg = document.getElementById('irExpiryFilterSeg');
    const list = document.getElementById('irExpiryList');
    if (!filterSeg || !list) return;

    const allItems = data.expiryAllItems || [];
    const relevantStatuses = ['expired', 'today', 'expiring_soon'];

    function renderCardList(filter) {
        let rows = allItems.filter(function (r) { return relevantStatuses.indexOf(r.status) !== -1; });
        if (filter === 'expired') {
            rows = rows.filter(function (r) { return r.status === 'expired'; });
        } else if (filter === 'expiring_soon') {
            rows = rows.filter(function (r) { return r.status === 'today' || r.status === 'expiring_soon'; });
        }
        rows = rows.slice(0, 5);

        list.innerHTML = '';
        if (rows.length === 0) {
            list.innerHTML = '<div class="ir-empty"><i class="bi bi-check-circle-fill"></i><p>No expired or soon-to-expire batches right now.</p></div>';
            return;
        }
        rows.forEach(function (r) {
            const row = document.createElement('div');
            row.className = 'ir-expiry-row';
            row.innerHTML =
                '<div class="ir-expiry-row-main">' +
                    '<span class="ir-expiry-row-name">' + escapeHtmlIR(r.name) + '</span>' +
                    '<span class="ir-expiry-row-meta">' + escapeHtmlIR(r.category) + ' &middot; Batch: ' + escapeHtmlIR(r.batchNo) + '</span>' +
                '</div>' +
                '<div class="ir-expiry-row-side">' +
                    '<span class="ir-expiry-status-badge ir-expiry-status-' + r.status + '">' + expiryStatusLabel(r) + '</span>' +
                    '<span class="ir-expiry-row-qty">' + r.quantity.toLocaleString() + ' ' + escapeHtmlIR(r.unit) + '</span>' +
                '</div>';
            list.appendChild(row);
        });
    }

    filterSeg.addEventListener('click', function (e) {
        const btn = e.target.closest('.ir-expiry-filter-btn');
        if (!btn || !filterSeg.contains(btn)) return;
        Array.prototype.forEach.call(filterSeg.querySelectorAll('.ir-expiry-filter-btn'), function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        renderCardList(btn.getAttribute('data-filter') || 'relevant');
    });

    /* ---------- "View All Items" modal ---------- */
    const modalEl = document.getElementById('irExpiryAllModal');
    const modalSearch = document.getElementById('irExpiryAllSearch');
    const modalFilterSeg = document.getElementById('irExpiryAllFilterSeg');
    const modalSort = document.getElementById('irExpiryAllSort');
    const modalTableBody = document.getElementById('irExpiryAllTableBody');
    const modalEmpty = document.getElementById('irExpiryAllEmpty');
    const modalPagination = document.getElementById('irExpiryAllPagination');
    if (!modalEl) return;

    const PAGE_SIZE = 10;
    let modalFilter = 'all';
    let modalPage = 1;
    const statusOrder = { expired: 0, today: 1, expiring_soon: 2, ok: 3, no_expiry: 4 };

    function renderModalTable() {
        const term = modalSearch ? modalSearch.value.trim().toLowerCase() : '';
        let rows = allItems.slice();
        if (modalFilter === 'expired') {
            rows = rows.filter(function (r) { return r.status === 'expired'; });
        } else if (modalFilter === 'expiring_soon') {
            rows = rows.filter(function (r) { return r.status === 'today' || r.status === 'expiring_soon'; });
        }
        if (term) {
            rows = rows.filter(function (r) { return r.name.toLowerCase().indexOf(term) !== -1; });
        }

        const sortValue = modalSort ? modalSort.value : 'urgency';
        if (sortValue === 'name-asc') {
            rows.sort(function (a, b) { return a.name.localeCompare(b.name); });
        } else if (sortValue === 'expiry-asc') {
            rows.sort(function (a, b) { return (a.expiryDateRaw || '9999-99-99').localeCompare(b.expiryDateRaw || '9999-99-99'); });
        } else if (sortValue === 'qty-desc') {
            rows.sort(function (a, b) { return b.quantity - a.quantity; });
        } else {
            rows.sort(function (a, b) {
                const rank = statusOrder[a.status] - statusOrder[b.status];
                if (rank !== 0) return rank;
                if (a.daysFromToday === null || b.daysFromToday === null) return 0;
                return a.daysFromToday - b.daysFromToday;
            });
        }

        const totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
        if (modalPage > totalPages) modalPage = totalPages;
        if (modalPage < 1) modalPage = 1;
        const pageRows = rows.slice((modalPage - 1) * PAGE_SIZE, modalPage * PAGE_SIZE);

        if (modalTableBody) modalTableBody.innerHTML = '';
        if (pageRows.length === 0) {
            if (modalEmpty) modalEmpty.style.display = '';
        } else {
            if (modalEmpty) modalEmpty.style.display = 'none';
            pageRows.forEach(function (r) {
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>' + escapeHtmlIR(r.name) + '</td>' +
                    '<td>' + escapeHtmlIR(r.category) + '</td>' +
                    '<td>' + escapeHtmlIR(r.batchNo) + '</td>' +
                    '<td>' + escapeHtmlIR(r.expiryDate) + '</td>' +
                    '<td>' + r.quantity.toLocaleString() + ' ' + escapeHtmlIR(r.unit) + '</td>' +
                    '<td><span class="ir-expiry-status-badge ir-expiry-status-' + r.status + '">' + expiryStatusLabel(r) + '</span></td>';
                if (modalTableBody) modalTableBody.appendChild(tr);
            });
        }

        if (modalPagination) {
            modalPagination.innerHTML = '';
            const info = document.createElement('span');
            info.className = 'ir-page-info';
            info.textContent = rows.length + ' item' + (rows.length === 1 ? '' : 's');
            if (totalPages > 1) {
                info.textContent += ' — page ' + modalPage + ' of ' + totalPages;
                const prevBtn = document.createElement('button');
                prevBtn.type = 'button'; prevBtn.className = 'ir-page-btn'; prevBtn.textContent = 'Prev';
                prevBtn.disabled = modalPage <= 1;
                prevBtn.addEventListener('click', function () { modalPage--; renderModalTable(); });
                const nextBtn = document.createElement('button');
                nextBtn.type = 'button'; nextBtn.className = 'ir-page-btn'; nextBtn.textContent = 'Next';
                nextBtn.disabled = modalPage >= totalPages;
                nextBtn.addEventListener('click', function () { modalPage++; renderModalTable(); });
                modalPagination.appendChild(prevBtn);
                modalPagination.appendChild(info);
                modalPagination.appendChild(nextBtn);
            } else {
                modalPagination.appendChild(info);
            }
        }
    }

    if (modalFilterSeg) {
        modalFilterSeg.addEventListener('click', function (e) {
            const btn = e.target.closest('.ir-expiry-filter-btn');
            if (!btn || !modalFilterSeg.contains(btn)) return;
            Array.prototype.forEach.call(modalFilterSeg.querySelectorAll('.ir-expiry-filter-btn'), function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            modalFilter = btn.getAttribute('data-filter') || 'all';
            modalPage = 1;
            renderModalTable();
        });
    }
    if (modalSearch) modalSearch.addEventListener('input', function () { modalPage = 1; renderModalTable(); });
    if (modalSort) modalSort.addEventListener('change', function () { modalPage = 1; renderModalTable(); });
    modalEl.addEventListener('shown.bs.modal', renderModalTable);
}

function expiryStatusLabel(r) {
    if (r.status === 'expired') return 'Expired ' + Math.abs(r.daysFromToday) + 'd ago';
    if (r.status === 'today') return 'Expires Today';
    if (r.status === 'expiring_soon') return r.daysFromToday + 'd left';
    if (r.status === 'no_expiry') return 'No Expiry Recorded';
    return 'OK';
}

function escapeHtmlIR(s) {
    const div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
}

window.initReportPageCharts = initReportPageCharts;
document.addEventListener('DOMContentLoaded', initReportPageCharts);
