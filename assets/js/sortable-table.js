/* =============================================================
   SORTABLE TABLE
   ---------------------------------------------------------------
   Adds click-to-sort column headers to any <table data-sortable="true">
   on the page — shared by the subadmin Analytics & Reports report
   pages (Patient/Inventory/Stock Request/Consultation/Vaccine Reports
   + Overview), so their data tables don't each need their own copy.
   Purely client-side (re-ordering the rows already rendered — these
   tables show a page's worth of real data at a time, not a full
   dataset), so it has no interaction with the real server-side
   pagination/filtering those same pages also use — sorting only
   re-orders whatever page of rows is currently on screen.

   Opt-in per table (data-sortable="true") and per column
   (<th data-no-sort> on any column that shouldn't be sortable, e.g. an
   actions column). Rows meant to stay pinned regardless of sort order
   (a table's own trailing "Total" row) are marked
   <tr data-no-sort> and always re-appended last.

   A cell can override what gets compared via data-sort-value="123" —
   used for anything whose visible text isn't the raw sortable value
   (e.g. a progress-bar percentage, a badge). Otherwise the cell's own
   text content is used, compared numerically when every value being
   compared in that column looks like a plain number, else
   alphabetically.

   Wrapped in window.initSortableTables() rather than run inline, so
   assets/js/ajax-report-nav.js's whole-page smooth content swap can
   re-invoke it after replacing <main> — the fresh <th>/<tr> elements
   that swap brings in have no listeners of their own yet, so simply
   re-running this is always safe (no risk of double-binding a stale
   element, since the old ones were destroyed with the old <main>).
   ============================================================= */
function initSortableTables() {
    document.querySelectorAll('table[data-sortable="true"]').forEach(function (table) {
        const headerRow = table.querySelector('thead tr');
        const tbody = table.querySelector('tbody');
        if (!headerRow || !tbody) return;

        Array.from(headerRow.children).forEach(function (th, colIndex) {
            if (th.hasAttribute('data-no-sort')) return;

            th.classList.add('sortable-th');
            const iconSpan = document.createElement('i');
            iconSpan.className = 'bi bi-arrow-down-up sort-icon';
            th.appendChild(iconSpan);

            th.addEventListener('click', function () {
                sortByColumn(table, tbody, colIndex, th, headerRow);
            });
        });
    });

    function cellSortText(row, colIndex) {
        const cell = row.children[colIndex];
        if (!cell) return '';
        if (cell.dataset && cell.dataset.sortValue !== undefined) return cell.dataset.sortValue;
        return cell.textContent.trim();
    }

    function sortByColumn(table, tbody, colIndex, th, headerRow) {
        const allRows = Array.from(tbody.querySelectorAll('tr'));
        const pinnedRows = allRows.filter(function (r) { return r.hasAttribute('data-no-sort'); });
        const rows = allRows.filter(function (r) { return !r.hasAttribute('data-no-sort'); });

        const nextDir = th.getAttribute('data-sort-dir') === 'asc' ? 'desc' : 'asc';

        Array.from(headerRow.children).forEach(function (h) {
            h.removeAttribute('data-sort-dir');
            h.classList.remove('sorted-asc', 'sorted-desc');
        });
        th.setAttribute('data-sort-dir', nextDir);
        th.classList.add(nextDir === 'asc' ? 'sorted-asc' : 'sorted-desc');

        const allNumeric = rows.every(function (r) {
            const v = cellSortText(r, colIndex).replace(/[^0-9.\-]/g, '');
            return v !== '' && !isNaN(parseFloat(v));
        });

        rows.sort(function (a, b) {
            const rawA = cellSortText(a, colIndex);
            const rawB = cellSortText(b, colIndex);
            let cmp;
            if (allNumeric) {
                cmp = parseFloat(rawA.replace(/[^0-9.\-]/g, '')) - parseFloat(rawB.replace(/[^0-9.\-]/g, ''));
            } else {
                cmp = rawA.localeCompare(rawB, undefined, { numeric: true, sensitivity: 'base' });
            }
            return nextDir === 'asc' ? cmp : -cmp;
        });

        rows.forEach(function (r) { tbody.appendChild(r); });
        pinnedRows.forEach(function (r) { tbody.appendChild(r); });
    }
}

window.initSortableTables = initSortableTables;
document.addEventListener('DOMContentLoaded', initSortableTables);
