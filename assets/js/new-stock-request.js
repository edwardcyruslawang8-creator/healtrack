/* =============================================================
   NEW / EDIT STOCK REQUEST JS
   (pharmacist/stock_requests.php)
   ---------------------------------------------------------------
   Drives the "+ New Stock Request" / "Edit Request" modal
   (includes/pharmacist/new_stock_request_modal.php) — one shared form
   for both, same convention as
   assets/js/medicine_vaccine_inventory_pharmacist.js's Add/Edit
   modal. Each item row's name is a free-text <input> (NOT a locked
   <select>) for flexibility — a pharmacist can request anything, not
   only items that already exist in inventory — paired with a
   <datalist> (#nsrMedicineList/#nsrVaccineList, rendered server-side
   by that same include) for autocomplete convenience only; switching
   Request Type just repoints each row's input at the other datalist,
   it never clears or validates against it.

   New: submitting POSTs {type, dateNeeded, items} to
   api/create-stock-request.php. Edit: each pending row's "Edit
   Request" menu item opens the SAME form pre-filled from that row's
   data-request-* attributes, with #nsrFormId set to the request's real
   doc id, and submitting POSTs to api/update-stock-request.php
   instead (docId included). Either way, on success the page reloads
   so the table/summary cards re-render from fresh Firestore data.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('newStockRequestModal');
    const form = document.getElementById('newStockRequestForm');
    if (!modalEl || !form || typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalEl);
    const errorBox = document.getElementById('newStockRequestError');
    const submitBtn = document.getElementById('nsrSubmitBtn');
    const submitBtnDefaultHtml = submitBtn.innerHTML;
    const submitLabel = document.getElementById('nsrSubmitLabel');
    const modalTitle = document.getElementById('newStockRequestModalLabel');

    const idInput = document.getElementById('nsrFormId');
    const typeSelect = document.getElementById('nsrType');
    const dateNeededInput = document.getElementById('nsrDateNeeded');
    const rowsContainer = document.getElementById('nsrItemRows');
    const addItemBtn = document.getElementById('nsrAddItemBtn');
    const todayIso = dateNeededInput.min; // set server-side to today (see new_stock_request_modal.php)

    function datalistIdFor(type) {
        return type === 'vaccine' ? 'nsrVaccineList' : 'nsrMedicineList';
    }

    function buildNameInput(value) {
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control am-form-control nsr-item-name';
        input.setAttribute('list', datalistIdFor(typeSelect.value));
        input.placeholder = 'Item name — type anything, or pick a suggestion';
        input.autocomplete = 'off';
        input.required = true;
        if (value) input.value = value;
        return input;
    }

    function updateRemoveButtons() {
        const rows = rowsContainer.querySelectorAll('.nsr-item-row');
        rows.forEach(function (row) {
            row.querySelector('.nsr-remove-item-btn').disabled = rows.length <= 1;
        });
    }

    function addItemRow(selectedName, selectedQty) {
        const row = document.createElement('div');
        row.className = 'nsr-item-row';

        row.appendChild(buildNameInput(selectedName));

        const qtyInput = document.createElement('input');
        qtyInput.type = 'number';
        qtyInput.className = 'form-control am-form-control nsr-item-qty';
        qtyInput.min = '1';
        qtyInput.step = '1';
        qtyInput.placeholder = 'Qty';
        qtyInput.required = true;
        if (selectedQty != null) qtyInput.value = selectedQty;
        row.appendChild(qtyInput);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'nsr-remove-item-btn';
        removeBtn.setAttribute('aria-label', 'Remove item');
        removeBtn.innerHTML = '<i class="bi bi-trash"></i>';
        removeBtn.addEventListener('click', function () {
            if (rowsContainer.querySelectorAll('.nsr-item-row').length <= 1) return;
            row.remove();
            updateRemoveButtons();
        });
        row.appendChild(removeBtn);

        rowsContainer.appendChild(row);
        updateRemoveButtons();
    }

    function resetRows(items) {
        rowsContainer.innerHTML = '';
        if (items && items.length > 0) {
            items.forEach(function (item) { addItemRow(item.name, item.qty); });
        } else {
            addItemRow(null, null);
        }
    }

    typeSelect.addEventListener('change', function () {
        // Just repoint every existing row's input at the other type's
        // datalist (suggestions only) — whatever was already typed stays,
        // since free text was never validated against either list.
        const newListId = datalistIdFor(typeSelect.value);
        rowsContainer.querySelectorAll('.nsr-item-name').forEach(function (input) {
            input.setAttribute('list', newListId);
        });
    });

    addItemBtn.addEventListener('click', function () { addItemRow(null, null); });

    const openBtn = document.getElementById('btnNewStockRequest');
    if (openBtn) {
        openBtn.addEventListener('click', function () {
            form.reset();
            form.classList.remove('was-validated');
            errorBox.classList.add('d-none');
            idInput.value = '';
            modalTitle.textContent = 'New Stock Request';
            submitLabel.textContent = 'Submit Request';
            dateNeededInput.min = todayIso;
            resetRows(null);
            submitBtn.disabled = false;
            submitBtn.innerHTML = submitBtnDefaultHtml;
            modal.show();
        });
    }

    document.querySelectorAll('.srq-edit-request').forEach(function (link) {
        const row = link.closest('tr');
        if (!row) return;
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const d = row.dataset;

            form.reset();
            form.classList.remove('was-validated');
            errorBox.classList.add('d-none');
            idInput.value = d.requestId;
            modalTitle.textContent = 'Edit Stock Request';
            submitLabel.textContent = 'Save Changes';

            typeSelect.value = d.requestTypeRaw;
            // Already-overdue pending requests (real dateNeeded already in
            // the past) must stay editable for their OTHER fields without
            // the date picker rejecting their own unchanged value — so the
            // min here relaxes to the request's existing date when that's
            // earlier than today, instead of always requiring today+.
            dateNeededInput.min = d.requestDateNeededRaw < todayIso ? d.requestDateNeededRaw : todayIso;
            dateNeededInput.value = d.requestDateNeededRaw;

            let items = [];
            try { items = JSON.parse(d.requestItems || '[]'); } catch (err) { items = []; }
            resetRows(items);

            submitBtn.disabled = false;
            submitBtn.innerHTML = submitBtnDefaultHtml;
            modal.show();
        });
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const items = [];
        rowsContainer.querySelectorAll('.nsr-item-row').forEach(function (row) {
            const name = row.querySelector('.nsr-item-name').value;
            const qty = row.querySelector('.nsr-item-qty').value;
            if (name && qty) items.push({ name: name, qty: qty });
        });

        if (!form.checkValidity() || items.length === 0) {
            form.classList.add('was-validated');
            if (items.length === 0) {
                errorBox.textContent = 'Please add at least one item with a name and quantity.';
                errorBox.classList.remove('d-none');
            }
            return;
        }

        const isEdit = idInput.value !== '';
        const payload = {
            type: typeSelect.value,
            dateNeeded: dateNeededInput.value,
            items: items,
        };
        if (isEdit) payload.docId = idInput.value;

        errorBox.classList.add('d-none');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + (isEdit ? 'Saving…' : 'Submitting…');

        fetch(isEdit ? '../api/update-stock-request.php' : '../api/create-stock-request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
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
                errorBox.textContent = err.message;
                errorBox.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtnDefaultHtml;
            });
    });
});
