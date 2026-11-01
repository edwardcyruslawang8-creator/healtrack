/* =============================================================
   MANAGE MEDICINE / MANAGE VACCINE DETAIL PAGE
   (pharmacist/manage_medicine.php, subadmin/manage_medicine.php, AND
   admin/manage_medicine.php — role-agnostic, all three pages load this
   same file)
   ---------------------------------------------------------------
   Self-contained wiring for this one page — unlike
   assets/js/medicine_vaccine_inventory_pharmacist.js (which drives the
   LIST page's own row-dataset-based triggers), this file only ever has
   ONE real item to act on, already server-rendered into
   window.MM_ITEM/window.MM_BATCHES (see the inline <script> each of
   those three pages' own PHP renders) — no AJAX fetch needed for
   either.

   Every block below is gated on its own trigger element actually
   existing in the DOM, so one shared file safely covers three
   different real capability sets without any role check here at all —
   the PHP template is what decides which buttons exist:
     - pharmacist: Dispense (#dispenseNoRxModal, every batch row's own
       .mm-item-dispense), Archive (.mm-item-archive), Restock
       (#mmRestockBtn).
     - subadmin: Archive, Restock, PLUS Edit (#mmEditBtn,
       #addMedicineModal) and Delete (#mmDeleteBtn) — no Dispense
       trigger is ever rendered there (dispensing stays a pharmacist-
       only clinical action; see subadmin/manage_medicine.php's own
       header comment).
     - admin: none of the above — read-only monitoring. Only the
       batch-number search filter below does anything on that page.

   Reuses the SAME real modals/endpoints the old #mviManageModal used
   (#dispenseNoRxModal/api/dispense-without-prescription.php,
   #restockModal/api/restock-medicine.php — both still rendered by
   includes/pharmacist/medicine_action_modals.php, required by the
   pages that need them) — only how they're triggered changed, not
   what they do. Archive reloads via api/archive-medicine.php and a
   plain HCDialog.confirm(), same pattern as each list page's own row-
   level Archive button. No History trigger here — removed per
   request.
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

    // -------------------------------------------------------------
    // Edit (sub-admin only — #mmEditBtn only exists on
    // subadmin/manage_medicine.php) — reuses #addMedicineModal/
    // #addMedicineForm (includes/pharmacist/add_medicine_modal.php),
    // but wired fresh here rather than via
    // assets/js/medicine_vaccine_inventory_pharmacist.js's own
    // openModalForEdit(), since that function reads a list row's
    // dataset and this page has no such row — it populates straight
    // from window.MM_ITEM instead. Always opens in "edit" shape (the
    // Expiry Date/Current Stock inputs stay hidden behind the same
    // read-only rollup summary note that form already uses for every
    // edit everywhere else; Minimum/Reorder/Maximum Level and Dosage
    // Information stay real, editable fields). POSTs to
    // api/update-medicine.php, then reloads on success.
    // -------------------------------------------------------------
    const editBtn = document.getElementById('mmEditBtn');
    const editModalEl = document.getElementById('addMedicineModal');
    if (editBtn && editModalEl) {
        const editModal = new bootstrap.Modal(editModalEl);
        const editForm = document.getElementById('addMedicineForm');
        const editErrorBox = document.getElementById('addMedicineError');
        const editSubmitBtn = document.getElementById('amSubmitBtn');
        const editSubmitBtnDefaultHtml = editSubmitBtn.innerHTML;

        editBtn.addEventListener('click', function () {
            editForm.reset();
            editForm.classList.remove('was-validated');
            editErrorBox.classList.add('d-none');

            document.getElementById('amFormType').value = item.type;
            document.getElementById('amFormId').value = item.id;
            document.getElementById('addMedicineModalLabel').textContent = item.type === 'vaccine' ? 'Edit Vaccine' : 'Edit Medicine';
            document.getElementById('amNameLabel').textContent = item.type === 'vaccine' ? 'Vaccine Name' : 'Medicine Name';
            document.getElementById('amSubmitLabel').textContent = 'Save Changes';

            document.getElementById('amName').value = item.name;
            document.getElementById('amCategory').value = item.category;
            document.getElementById('amUnit').value = item.unit;

            // Current Stock/Expiry Date are a real rollup over this
            // item's own batches — not editable here (see
            // add_medicine_modal.php's own comment on
            // #amStockExpiryRow). Shown read-only instead.
            document.getElementById('amStockExpiryRow').classList.add('d-none');
            document.getElementById('amExpiryDate').required = false;
            document.getElementById('amCurrentStock').required = false;
            const readonlyNote = document.getElementById('amStockExpiryReadonlyNote');
            readonlyNote.textContent = 'Current Stock: ' + item.currentStock + ' ' + item.unit + ' — Expiry: ' + (item.expiryDate || '—') + '. Managed via Restock, not editable here.';
            readonlyNote.classList.remove('d-none');

            document.getElementById('amThresholdsRow').classList.remove('d-none');
            document.getElementById('amMinLevel').required = true;
            document.getElementById('amReorderLevel').required = true;
            document.getElementById('amMinLevel').value = item.minLevel;
            document.getElementById('amReorderLevel').value = item.reorderLevel;
            document.getElementById('amMaxLevelRow').classList.remove('d-none');
            document.getElementById('amMaxLevel').value = item.maxLevel > 0 ? item.maxLevel : '';
            document.getElementById('amDesc').value = item.desc || '';
            document.getElementById('amDosageRow').classList.remove('d-none');
            document.getElementById('amDosage').value = item.dosage || '';

            editSubmitBtn.disabled = false;
            editSubmitBtn.innerHTML = editSubmitBtnDefaultHtml;
            editModal.show();
        });

        editForm.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (!editForm.checkValidity()) {
                editForm.classList.add('was-validated');
                return;
            }

            const payload = {
                id: item.id,
                type: item.type,
                name: document.getElementById('amName').value.trim(),
                category: document.getElementById('amCategory').value.trim(),
                unit: document.getElementById('amUnit').value.trim(),
                minLevel: document.getElementById('amMinLevel').value,
                reorderLevel: document.getElementById('amReorderLevel').value,
                maxLevel: document.getElementById('amMaxLevel').value,
                desc: document.getElementById('amDesc').value.trim(),
                dosage: document.getElementById('amDosage').value.trim(),
            };

            editErrorBox.classList.add('d-none');
            editSubmitBtn.disabled = true;
            editSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving…';

            fetch('../api/update-medicine.php', {
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
                    editErrorBox.textContent = err.message;
                    editErrorBox.classList.remove('d-none');
                    editSubmitBtn.disabled = false;
                    editSubmitBtn.innerHTML = editSubmitBtnDefaultHtml;
                });
        });
    }

    // -------------------------------------------------------------
    // Delete (sub-admin only — #mmDeleteBtn only exists on
    // subadmin/manage_medicine.php) — permanent, unlike Archive.
    // HCDialog.confirm() + POST to api/delete-medicine.php, then back
    // to the list on success (this item no longer exists to show a
    // detail page for).
    // -------------------------------------------------------------
    const deleteBtn = document.getElementById('mmDeleteBtn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', async function () {
            const confirmed = await HCDialog.confirm('Delete "' + item.name + '"? This cannot be undone.');
            if (!confirmed) return;

            fetch('../api/delete-medicine.php', {
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
    }
});
