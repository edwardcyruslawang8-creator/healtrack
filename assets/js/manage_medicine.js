/* =============================================================
   MANAGE MEDICINE / MANAGE VACCINE DETAIL PAGE
   (pharmacist/manage_medicine.php)
   ---------------------------------------------------------------
   Self-contained wiring for this one page — unlike
   assets/js/medicine_vaccine_inventory_pharmacist.js (which drives the
   LIST page's own row-dataset-based triggers, shared with subadmin's
   page too), this file only ever has ONE real item to act on, already
   server-rendered into window.MM_ITEM/window.MM_BATCHES (see the
   inline <script> in pharmacist/manage_medicine.php) — no AJAX fetch
   needed for either.

   Reuses the SAME real modals/endpoints the old #mviManageModal used
   (#dispenseNoRxModal/api/dispense-without-prescription.php,
   #restockModal/api/restock-medicine.php — both still rendered by
   includes/pharmacist/medicine_action_modals.php, required by this
   page too) — only how they're triggered changed, not what they do.
   Archive reloads via api/archive-medicine.php and a plain
   HCDialog.confirm(), same pattern as the list page's own row-level
   Archive button. No History trigger here — removed per request.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const item = window.MM_ITEM;
    const batches = window.MM_BATCHES || [];
    if (!item || typeof bootstrap === 'undefined') return;

    // -------------------------------------------------------------
    // Batch number search — client-side filter over the already-
    // rendered Batch Inventory rows (every real batch for this one
    // item was server-rendered, nothing left to fetch).
    // -------------------------------------------------------------
    const searchInput = document.getElementById('mmBatchSearch');
    const batchTable = document.getElementById('mmBatchTable');
    const noMatch = document.getElementById('mmBatchNoMatch');
    if (searchInput && batchTable) {
        const rows = Array.prototype.slice.call(batchTable.querySelectorAll('tbody tr'));
        searchInput.addEventListener('input', function () {
            const q = searchInput.value.trim().toLowerCase();
            let visible = 0;
            rows.forEach(function (row) {
                const match = q === '' || (row.dataset.batchNumber || '').indexOf(q) !== -1;
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            if (noMatch) noMatch.classList.toggle('d-none', visible > 0);
        });
    }

    // -------------------------------------------------------------
    // Dispense (No Prescription) — same endpoint/flow the old
    // #mviManageModal's footer button used. FEFO still governs which
    // real batch actually gets decremented
    // (hc_decrement_medicine_stock_fefo()) regardless of which row's
    // own Dispense button was clicked — there's no "dispense from
    // exactly this batch" operation, this item-level action is just
    // repeated on every row for convenience.
    // -------------------------------------------------------------
    const dnrModalEl = document.getElementById('dispenseNoRxModal');
    if (dnrModalEl) {
        const dnrModal = new bootstrap.Modal(dnrModalEl);
        const dnrForm = document.getElementById('dispenseNoRxForm');
        const dnrErrorBox = document.getElementById('dispenseNoRxError');
        const dnrSubmitBtn = document.getElementById('dnrSubmitBtn');
        const dnrSubmitBtnDefaultHtml = dnrSubmitBtn.innerHTML;

        document.querySelectorAll('.mm-item-dispense').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (item.expiryStatus === 'expired') {
                    HCDialog.alert(item.name + ' has expired and can no longer be dispensed.');
                    return;
                }
                dnrForm.reset();
                dnrErrorBox.classList.add('d-none');
                document.getElementById('dnrItemId').value = item.id;
                document.getElementById('dnrItemType').value = item.type;
                document.getElementById('dnrPatientId').value = '';
                document.getElementById('dnrItemName').textContent = item.name;
                document.getElementById('dnrCurrentStock').textContent = item.currentStock;
                document.getElementById('dnrUnit').textContent = item.unit;
                document.getElementById('dnrQty').max = item.currentStock;
                dnrSubmitBtn.disabled = false;
                dnrSubmitBtn.innerHTML = dnrSubmitBtnDefaultHtml;
                dnrModal.show();
            });
        });

        dnrForm.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const patientId = document.getElementById('dnrPatientId').value;
            if (patientId === '') {
                dnrErrorBox.textContent = 'Please search for and select a real patient.';
                dnrErrorBox.classList.remove('d-none');
                return;
            }
            const quantity = parseInt(document.getElementById('dnrQty').value, 10);
            const stockAvailable = parseInt(document.getElementById('dnrQty').max, 10);
            if (!Number.isInteger(quantity) || quantity < 1) {
                dnrErrorBox.textContent = 'Please enter a valid quantity.';
                dnrErrorBox.classList.remove('d-none');
                return;
            }
            if (quantity > stockAvailable) {
                dnrErrorBox.textContent = 'Only ' + stockAvailable + ' in stock — cannot dispense ' + quantity + '.';
                dnrErrorBox.classList.remove('d-none');
                return;
            }

            dnrErrorBox.classList.add('d-none');
            dnrSubmitBtn.disabled = true;
            dnrSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Dispensing…';

            fetch('../api/dispense-without-prescription.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    itemId: item.id,
                    itemType: item.type,
                    patientId: patientId,
                    quantity: quantity,
                    notes: document.getElementById('dnrNotes').value.trim(),
                }),
            })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (result) {
                    if (!result.ok || !result.data.success) throw new Error(result.data.error || 'Something went wrong. Please try again.');
                    window.location.reload();
                })
                .catch(function (err) {
                    dnrErrorBox.textContent = err.message;
                    dnrErrorBox.classList.remove('d-none');
                    dnrSubmitBtn.disabled = false;
                    dnrSubmitBtn.innerHTML = dnrSubmitBtnDefaultHtml;
                });
        });
    }

    // -------------------------------------------------------------
    // Archive — same whole-item action the list page's own row button
    // uses, repeated on every batch row for convenience here too.
    // -------------------------------------------------------------
    document.querySelectorAll('.mm-item-archive').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const confirmed = await HCDialog.confirm('Archive "' + item.name + '"? It will stop appearing in the inventory list and on the public site.');
            if (!confirmed) return;

            fetch('../api/archive-medicine.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: item.id }),
            })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (result) {
                    if (!result.ok || !result.data.success) throw new Error(result.data.error || 'Something went wrong. Please try again.');
                    window.location.href = 'medicine_vaccine_inventory.php';
                })
                .catch(function (err) {
                    HCDialog.alert(err.message);
                });
        });
    });

    // -------------------------------------------------------------
    // Restock — adds real stock a pharmacist actually received, either
    // into an existing batch (window.MM_BATCHES — already on the
    // page, no fetch) or as a brand new one, plus an optional Dosage
    // Information update (pre-filled from item.dosage). POSTs to
    // api/restock-medicine.php, which also recalculates Minimum
    // Level/Reorder Level from the item's new total stock
    // (hc_auto_compute_thresholds()) — then reloads on success so the
    // Batch Inventory table and info row reflect the real new
    // quantity/thresholds.
    // -------------------------------------------------------------
    const restockBtn = document.getElementById('mmRestockBtn');
    const restockModalEl = document.getElementById('restockModal');
    if (restockBtn && restockModalEl) {
        const restockModal = new bootstrap.Modal(restockModalEl);
        const restockForm = document.getElementById('restockForm');
        const rsErrorBox = document.getElementById('restockError');
        const rsSubmitBtn = document.getElementById('rsSubmitBtn');
        const rsSubmitBtnDefaultHtml = rsSubmitBtn.innerHTML;
        const rsBatchSelect = document.getElementById('rsBatchSelect');
        const rsExistingWrap = document.getElementById('rsExistingWrap');
        const rsNewWrap = document.getElementById('rsNewWrap');
        const rsBatchNumberInput = document.getElementById('rsBatchNumber');
        const rsNewExpiryInput = document.getElementById('rsNewExpiry');
        const rsQtyInput = document.getElementById('rsQty');

        function syncModeVisibility() {
            const isExisting = document.getElementById('rsModeExisting').checked;
            rsExistingWrap.classList.toggle('d-none', !isExisting);
            rsNewWrap.classList.toggle('d-none', isExisting);
            rsNewExpiryInput.required = !isExisting;
        }
        document.getElementById('rsModeExisting').addEventListener('change', syncModeVisibility);
        document.getElementById('rsModeNew').addEventListener('change', syncModeVisibility);

        restockBtn.addEventListener('click', function () {
            restockForm.reset();
            restockForm.classList.remove('was-validated');
            rsErrorBox.classList.add('d-none');
            document.getElementById('rsItemId').value = item.id;
            document.getElementById('rsItemName').textContent = item.name;
            document.getElementById('rsDosage').value = item.dosage || '';

            rsBatchSelect.innerHTML = '';
            if (batches.length === 0) {
                rsBatchSelect.innerHTML = '<option value="">No existing batches — add as a new batch instead</option>';
                document.getElementById('rsModeNew').checked = true;
            } else {
                batches.forEach(function (batch) {
                    const opt = document.createElement('option');
                    opt.value = batch.id;
                    opt.textContent = batch.displayBatchNumber + ' — expires ' + batch.expiryDate + ' — ' + batch.quantity + ' ' + item.unit + ' on hand';
                    rsBatchSelect.appendChild(opt);
                });
                document.getElementById('rsModeExisting').checked = true;
            }
            syncModeVisibility();

            rsSubmitBtn.disabled = false;
            rsSubmitBtn.innerHTML = rsSubmitBtnDefaultHtml;
            restockModal.show();
        });

        restockForm.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const isExisting = document.getElementById('rsModeExisting').checked;
            const quantity = parseInt(rsQtyInput.value, 10);
            if (!Number.isInteger(quantity) || quantity < 1) {
                rsErrorBox.textContent = 'Please enter a valid quantity.';
                rsErrorBox.classList.remove('d-none');
                return;
            }

            const payload = {
                id: item.id,
                mode: isExisting ? 'existing' : 'new',
                quantity: quantity,
                dosage: document.getElementById('rsDosage').value.trim(),
            };
            if (isExisting) {
                if (!rsBatchSelect.value) {
                    rsErrorBox.textContent = 'Please select which batch this stock belongs to.';
                    rsErrorBox.classList.remove('d-none');
                    return;
                }
                payload.batchId = rsBatchSelect.value;
            } else {
                if (!rsNewExpiryInput.value) {
                    rsErrorBox.textContent = 'Please enter the expiry date for this new batch.';
                    rsErrorBox.classList.remove('d-none');
                    return;
                }
                payload.batchNumber = rsBatchNumberInput.value.trim();
                payload.expiryDate = rsNewExpiryInput.value;
            }

            rsErrorBox.classList.add('d-none');
            rsSubmitBtn.disabled = true;
            rsSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving…';

            fetch('../api/restock-medicine.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (result) {
                    if (!result.ok || !result.data.success) throw new Error(result.data.error || 'Something went wrong. Please try again.');
                    window.location.reload();
                })
                .catch(function (err) {
                    rsErrorBox.textContent = err.message;
                    rsErrorBox.classList.remove('d-none');
                    rsSubmitBtn.disabled = false;
                    rsSubmitBtn.innerHTML = rsSubmitBtnDefaultHtml;
                });
        });
    }
});
