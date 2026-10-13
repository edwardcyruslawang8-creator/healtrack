/**
 * Stock Requests — Approve / Reject / Release / View / Print / Delete
 * ---------------------------------------------------------------------
 * Shared between subadmin/stock_requests.php and
 * pharmacist/stock_requests.php.
 *   - Approve/Reject/Release: [data-request-approve]/[data-request-reject]/
 *     [data-request-release], each carrying the real Firestore doc id in
 *     data-request-id, POST to api/update-stock-request-status.php.
 *   - View Request: .srq-view-request ("View Items" link + the
 *     three-dot menu's "View Request" item) populates
 *     includes/subadmin/stock_request_view_modal.php purely from the
 *     row's own data-request-* attributes — no network request.
 *   - Print Request: .srq-print-request populates the SAME modal's
 *     hidden #srqPrintArea twin (readRequestFields() below feeds both)
 *     and calls window.print() directly; the modal's own "Print"
 *     footer button reuses whatever the modal is already showing.
 *   - Delete Request: .srq-delete-request, confirm() + POST to
 *     api/delete-stock-request.php.
 * The three writes reload the page on success so the row's status
 * badge and the summary cards' pending count both reflect the real
 * change — same reload-on-success pattern as
 * assets/js/medicine_vaccine_inventory_pharmacist.js.
 */

/** Reads one row's data-request-* attributes into a plain object — shared by the modal and the print area. */
function readRequestFields(row) {
    const d = row.dataset;
    let items = [];
    try { items = JSON.parse(d.requestItems || '[]'); } catch (err) { items = []; }

    return {
        pharmacist: d.requestPharmacist,
        date: d.requestDate + ' ' + d.requestTime,
        type: d.requestType,
        status: d.requestStatus,
        dateNeeded: d.requestDateNeeded,
        facility: d.requestFacility || '—',
        items: items,
    };
}

/** Fills the item-list <tbody> shared by the modal (name/qty cells) and the print area (same markup, different ids). */
function fillItemsBody(tbody, items) {
    tbody.innerHTML = '';
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="2" class="text-muted">No items listed.</td></tr>';
        return;
    }
    items.forEach(function (item) {
        const tr = document.createElement('tr');
        const nameTd = document.createElement('td');
        nameTd.textContent = item.name || '(unnamed)';
        const qtyTd = document.createElement('td');
        qtyTd.className = 'text-end';
        qtyTd.textContent = item.qty != null ? item.qty : '—';
        tr.appendChild(nameTd);
        tr.appendChild(qtyTd);
        tbody.appendChild(tr);
    });
}

/** Populates #srqPrintArea (assets/css/add_medicine_modal.css's @media print rule) and opens the browser print dialog. */
function printRequest(fields) {
    document.getElementById('prPharmacist').textContent = fields.pharmacist;
    document.getElementById('prDate').textContent = fields.date;
    document.getElementById('prType').textContent = fields.type;
    document.getElementById('prStatus').textContent = fields.status;
    document.getElementById('prDateNeeded').textContent = fields.dateNeeded;
    document.getElementById('prFacility').textContent = fields.facility;
    fillItemsBody(document.getElementById('prItemsBody'), fields.items);
    window.print();
}

document.addEventListener('DOMContentLoaded', function () {
    // Each row's three-dot menu lives inside .adm-table-wrap, which has
    // overflow-x:auto for horizontal scrolling on small screens — but per
    // the CSS overflow spec, setting only one axis to auto forces the
    // OTHER axis to auto too, so the dropdown-menu (Popper-positioned
    // absolute, still inside that wrapper) gets clipped into a tiny
    // scrollable box for every row except ones near the bottom of the
    // table (where Popper's flip happens to keep it in view). Pre-creating
    // each dropdown's Bootstrap instance with popperConfig strategy
    // "fixed" positions it relative to the viewport instead, so it
    // escapes the wrapper's clipping — Bootstrap's own data-api handler
    // (data-bs-toggle="dropdown") reuses this same instance via
    // getOrCreateInstance() rather than creating a fresh unconfigured one.
    if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
        document.querySelectorAll('.srq-more-btn').forEach(function (btn) {
            bootstrap.Dropdown.getOrCreateInstance(btn, { popperConfig: { strategy: 'fixed' } });
        });
    }

    document.querySelectorAll('[data-request-approve], [data-request-reject], [data-request-release]').forEach(function (btn) {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            if (btn.classList.contains('disabled')) return;

            var docId = btn.getAttribute('data-request-id');
            var status = btn.hasAttribute('data-request-approve') ? 'approved'
                : btn.hasAttribute('data-request-reject') ? 'rejected'
                : 'released';
            var verb = status === 'approved' ? 'approve' : status === 'rejected' ? 'reject' : 'release items for';

            if (!docId) return;
            // Releasing also adds real stock for any item that matched a
            // real inventory item when the request was made — see
            // api/update-stock-request-status.php's own header comment.
            var confirmMessage = status === 'released'
                ? 'Release items for this stock request? The requested quantities will be added to inventory for any matching items.'
                : 'Are you sure you want to ' + verb + ' this stock request?';
            var confirmed = await HCDialog.confirm(confirmMessage);
            if (!confirmed) return;

            fetch('../api/update-stock-request-status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ docId: docId, status: status }),
            })
                .then(function (res) {
                    return res.json().then(function (data) { return { ok: res.ok, data: data }; });
                })
                .then(async function (result) {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.error || 'Something went wrong. Please try again.');
                    }
                    var skipped = result.data.skippedItems || [];
                    if (status === 'released' && skipped.length > 0) {
                        await HCDialog.alert(
                            'Released. These item(s) had no matching inventory entry, so stock was NOT auto-added for them — add manually if needed: ' + skipped.join(', ') + '.',
                            { title: 'Partially Matched' }
                        );
                    }
                    window.location.reload();
                })
                .catch(function (err) {
                    HCDialog.alert(err.message);
                });
        });
    });

    // -------------------------------------------------------------
    // View Request — pure client-side, no request.
    // -------------------------------------------------------------
    const viewModalEl = document.getElementById('viewRequestModal');
    let currentRequestFields = null;

    if (viewModalEl && typeof bootstrap !== 'undefined') {
        const viewModal = new bootstrap.Modal(viewModalEl);
        document.querySelectorAll('.srq-view-request').forEach(function (link) {
            const row = link.closest('tr');
            if (!row) return;
            link.addEventListener('click', function (e) {
                e.preventDefault();
                const fields = readRequestFields(row);
                currentRequestFields = fields;

                document.getElementById('vrPharmacist').textContent = fields.pharmacist;
                document.getElementById('vrDate').textContent = fields.date;
                document.getElementById('vrType').textContent = fields.type;
                document.getElementById('vrStatus').textContent = fields.status;
                document.getElementById('vrDateNeeded').textContent = fields.dateNeeded;
                document.getElementById('vrFacility').textContent = fields.facility;
                fillItemsBody(document.getElementById('vrItemsBody'), fields.items);

                viewModal.show();
            });
        });

        const printBtn = document.getElementById('vrPrintBtn');
        if (printBtn) {
            printBtn.addEventListener('click', function () {
                if (currentRequestFields) printRequest(currentRequestFields);
            });
        }
    }

    // -------------------------------------------------------------
    // Print Request — populates #srqPrintArea directly and opens the
    // print dialog, without requiring the modal to be open first.
    // -------------------------------------------------------------
    document.querySelectorAll('.srq-print-request').forEach(function (link) {
        const row = link.closest('tr');
        if (!row) return;
        link.addEventListener('click', function (e) {
            e.preventDefault();
            printRequest(readRequestFields(row));
        });
    });

    // -------------------------------------------------------------
    // Delete Request — confirm() + POST to api/delete-stock-request.php.
    // -------------------------------------------------------------
    document.querySelectorAll('.srq-delete-request').forEach(function (link) {
        const row = link.closest('tr');
        if (!row) return;
        link.addEventListener('click', async function (e) {
            e.preventDefault();
            const d = row.dataset;
            var confirmed = await HCDialog.confirm('Delete this stock request from ' + d.requestPharmacist + '? This cannot be undone.');
            if (!confirmed) return;

            fetch('../api/delete-stock-request.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ docId: d.requestId }),
            })
                .then(function (res) {
                    return res.json().then(function (data) { return { ok: res.ok, data: data }; });
                })
                .then(function (result) {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.error || 'Something went wrong. Please try again.');
                    }
                    window.location.reload();
                })
                .catch(function (err) {
                    HCDialog.alert(err.message);
                });
        });
    });
});
