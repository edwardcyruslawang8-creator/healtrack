/* =============================================================
   MEDICINE & VACCINE INVENTORY JS
   (pharmacist/medicine_vaccine_inventory.php AND
   subadmin/medicine_vaccine_inventory.php — role-agnostic, both pages
   load this same file)
   ---------------------------------------------------------------
   The two pages offer DIFFERENT capability sets over the same real
   "medicines" Firestore collection (split by a "type" field — see
   includes/firestore/medicines.php's header comment):
     - subadmin/medicine_vaccine_inventory.php: the full pencil+
       three-dot layout — Edit (api/update-medicine.php), Adjust Stock
       (api/adjust-medicine-stock.php), View Details, Dispense, View
       Stock History, Archive, Delete (api/delete-medicine.php).
     - pharmacist/medicine_vaccine_inventory.php: one "Manage
       Medicine"/"Manage Vaccine" button per row (#mviManageModal,
       includes/pharmacist/medicine_action_modals.php) offering only
       Dispense, View Stock History, and Archive — no Edit/Adjust
       Stock/Delete trigger exists on this page at all, and those
       three endpoints now refuse the pharmacist role server-side too
       (see each one's own header comment), not just a hidden button.

   Every row's data lives in data-med-* attributes on the <tr> itself
   (set server-side by whichever page rendered it). Each action below
   is a named function (openModalForEdit, openViewDetailsModal,
   openAdjustStockModal, openDispenseNoRxModal, openStockHistoryModal,
   performArchive, performDelete) that takes that dataset directly —
   subadmin's row-level three-dot menu calls these via its own
   delegated '.mvi-*' click listeners (closest('tr').dataset).
   #mviManageModal's own wiring at the bottom of this file calls the
   three it needs (openDispense/openHistory/performArchive) via the
   shared mviActions object, rather than a second copy of that logic.

   Add: "+ Add Medicine"/"+ Add Vaccine" open the form empty
   (#amFormId blank) and submitting POSTs to api/create-medicine.php
   (pharmacist-allowed — registering a NEW item is a different
   operation from editing an existing one's data). Edit posts to
   api/update-medicine.php instead (subadmin-only). Every one of these
   reloads the page on success so the table/summary cards re-render
   from fresh Firestore data.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    // Shared handle so #mviManageModal's own header/footer actions
    // (pharmacist/medicine_vaccine_inventory.php only — wired at the
    // bottom of this file) can call the exact same open-functions each
    // block below already defines for its own row-level delegated
    // listener (subadmin's three-dot menu) — one real implementation
    // per action, not a second copy for the Manage modal to call.
    const mviActions = {};

    const modalEl = document.getElementById('addMedicineModal');
    const form = document.getElementById('addMedicineForm');

    if (modalEl && form && typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalEl);
        const errorBox = document.getElementById('addMedicineError');
        const submitBtn = document.getElementById('amSubmitBtn');
        const submitLabel = document.getElementById('amSubmitLabel');
        const submitBtnDefaultHtml = submitBtn.innerHTML;

        const typeInput = document.getElementById('amFormType');
        const idInput = document.getElementById('amFormId');
        const modalTitle = document.getElementById('addMedicineModalLabel');
        const nameLabel = document.getElementById('amNameLabel');
        const nameInput = document.getElementById('amName');
        const expiryInput = document.getElementById('amExpiryDate');
        const stockInput = document.getElementById('amCurrentStock');
        const stockExpiryRow = document.getElementById('amStockExpiryRow');
        const stockExpiryReadonlyNote = document.getElementById('amStockExpiryReadonlyNote');
        const todayIso = expiryInput.min; // set server-side to today (see add_medicine_modal.php)

        var openModalAs = function (type) {
            form.reset();
            form.classList.remove('was-validated');
            errorBox.classList.add('d-none');
            typeInput.value = type;
            idInput.value = '';
            expiryInput.min = todayIso;
            // Adding a brand-new item: it has no batches yet, so this IS
            // its real first batch — collected here same as always.
            stockExpiryRow.classList.remove('d-none');
            expiryInput.required = true;
            stockInput.required = true;
            stockExpiryReadonlyNote.classList.add('d-none');

            if (type === 'vaccine') {
                modalTitle.textContent = 'Add Vaccine';
                nameLabel.textContent = 'Vaccine Name';
                nameInput.placeholder = 'e.g. Influenza Vaccine';
                submitLabel.textContent = 'Add Vaccine';
                document.getElementById('amCategory').value = 'Vaccine';
                document.getElementById('amUnit').value = 'Dose';
            } else {
                modalTitle.textContent = 'Add Medicine';
                nameLabel.textContent = 'Medicine Name';
                nameInput.placeholder = 'e.g. Amoxicillin 500 mg Capsule';
                submitLabel.textContent = 'Add Medicine';
            }

            submitBtn.disabled = false;
            submitBtn.innerHTML = submitBtnDefaultHtml;
            modal.show();
        };

        var openModalForEdit = function (d) {
            form.reset();
            form.classList.remove('was-validated');
            errorBox.classList.add('d-none');

            typeInput.value = d.medType;
            idInput.value = d.medId;
            modalTitle.textContent = d.medType === 'vaccine' ? 'Edit Vaccine' : 'Edit Medicine';
            nameLabel.textContent = d.medType === 'vaccine' ? 'Vaccine Name' : 'Medicine Name';
            submitLabel.textContent = 'Save Changes';

            nameInput.value = d.medName;
            document.getElementById('amCategory').value = d.medCategory;
            document.getElementById('amUnit').value = d.medUnit;
            // Current Stock/Expiry Date are a real rollup over this
            // item's own batches now (includes/firestore/medicine_batches.php)
            // — not editable here (see add_medicine_modal.php's own
            // comment on #amStockExpiryRow). Shown read-only instead so
            // it's still clear what's currently on hand while editing
            // everything else.
            stockExpiryRow.classList.add('d-none');
            expiryInput.required = false;
            stockInput.required = false;
            stockExpiryReadonlyNote.textContent = 'Current Stock: ' + d.medStock + ' ' + d.medUnit + ' — Expiry: ' + (d.medExpiryDisplay || d.medExpiry) + '. Managed via Adjust Stock or stock requests, not editable here.';
            stockExpiryReadonlyNote.classList.remove('d-none');
            document.getElementById('amMinLevel').value = d.medMinLevel;
            document.getElementById('amReorderLevel').value = d.medReorderLevel;
            document.getElementById('amMaxLevel').value = (d.medMaxLevel && d.medMaxLevel !== '0') ? d.medMaxLevel : '';
            document.getElementById('amDesc').value = d.medDesc || '';
            document.getElementById('amDosage').value = d.medDosage || '';

            submitBtn.disabled = false;
            submitBtn.innerHTML = submitBtnDefaultHtml;
            modal.show();
        };

        const addMedicineBtn = document.getElementById('btnAddMedicine');
        if (addMedicineBtn) addMedicineBtn.addEventListener('click', function () { openModalAs('medicine'); });

        const addVaccineBtn = document.getElementById('btnAddVaccine');
        if (addVaccineBtn) addVaccineBtn.addEventListener('click', function () { openModalAs('vaccine'); });

        // Delegated (not bound per-row) so this keeps working after
        // assets/js/mvi-ajax-pagination.js swaps the table's rows in via
        // AJAX on subadmin/medicine_vaccine_inventory.php — a listener
        // bound directly to each row at page load would silently stop
        // working on any page the AJAX pagination fetched afterward.
        document.addEventListener('click', function (e) {
            // pharmacist's edit button is an icon-only '.adm-action-btn.edit';
            // subadmin's is the labeled "Manage Medicine"/"Manage Vaccine"
            // button, '.mvi-manage-btn.edit' — different markup, same .edit
            // class and same row dataset, so one shared handler covers both.
            const btn = e.target.closest('.adm-action-btn.edit, .mvi-manage-btn.edit');
            if (!btn) return;
            const row = btn.closest('tr');
            if (!row) return;
            openModalForEdit(row.dataset);
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }

            const isEdit = idInput.value !== '';
            const payload = {
                type: typeInput.value,
                name: nameInput.value.trim(),
                category: document.getElementById('amCategory').value.trim(),
                unit: document.getElementById('amUnit').value.trim(),
                expiryDate: document.getElementById('amExpiryDate').value,
                currentStock: document.getElementById('amCurrentStock').value,
                minLevel: document.getElementById('amMinLevel').value,
                reorderLevel: document.getElementById('amReorderLevel').value,
                maxLevel: document.getElementById('amMaxLevel').value,
                desc: document.getElementById('amDesc').value.trim(),
                dosage: document.getElementById('amDosage').value.trim(),
            };
            if (isEdit) payload.id = idInput.value;

            errorBox.classList.add('d-none');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving…';

            fetch(isEdit ? '../api/update-medicine.php' : '../api/create-medicine.php', {
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
    }

    // -------------------------------------------------------------
    // View Details — pure client-side, no request; reads the same
    // data-med-* attributes already on the row.
    // -------------------------------------------------------------
    const viewModalEl = document.getElementById('viewMedicineModal');
    if (viewModalEl && typeof bootstrap !== 'undefined') {
        const viewModal = new bootstrap.Modal(viewModalEl);
        // Delegated — see the Edit button's own comment above for why.
        document.addEventListener('click', function (e) {
            const link = e.target.closest('.mvi-view-details');
            if (!link) return;
            const row = link.closest('tr');
            if (!row) return;
            e.preventDefault();
            const d = row.dataset;
            document.getElementById('viewMedicineModalLabel').textContent = d.medType === 'vaccine' ? 'Vaccine Details' : 'Medicine Details';
            document.getElementById('vmName').textContent = d.medName;
            document.getElementById('vmCategory').textContent = d.medCategory;
            document.getElementById('vmUnit').textContent = d.medUnit;
            document.getElementById('vmStock').textContent = d.medStock;
            document.getElementById('vmMinLevel').textContent = d.medMinLevel;
            document.getElementById('vmReorderLevel').textContent = d.medReorderLevel;
            const vmMaxLevelRow = document.getElementById('vmMaxLevelRow');
            if (d.medMaxLevel && d.medMaxLevel !== '0') {
                document.getElementById('vmMaxLevel').textContent = d.medMaxLevel;
                vmMaxLevelRow.classList.remove('d-none');
            } else {
                vmMaxLevelRow.classList.add('d-none');
            }
            document.getElementById('vmExpiry').textContent = d.medExpiryDisplay || d.medExpiry;
            document.getElementById('vmStatus').textContent = d.medStatusLabel || '';
            const descRow = document.getElementById('vmDescRow');
            if (d.medDesc) {
                document.getElementById('vmDesc').textContent = d.medDesc;
                descRow.classList.remove('d-none');
            } else {
                descRow.classList.add('d-none');
            }
            viewModal.show();
        });
    }

    // -------------------------------------------------------------
    // Adjust Stock — focused +/- change, POSTs to
    // api/adjust-medicine-stock.php.
    // -------------------------------------------------------------
    const adjustModalEl = document.getElementById('adjustStockModal');
    if (adjustModalEl && typeof bootstrap !== 'undefined') {
        const adjustModal = new bootstrap.Modal(adjustModalEl);
        const adjustForm = document.getElementById('adjustStockForm');
        const asErrorBox = document.getElementById('adjustStockError');
        const asSubmitBtn = document.getElementById('asSubmitBtn');
        const asSubmitBtnDefaultHtml = asSubmitBtn.innerHTML;
        const asIdInput = document.getElementById('asFormId');
        const asDeltaInput = document.getElementById('asDelta');
        const asExpiryWrap = document.getElementById('asExpiryWrap');
        const asExpiryInput = document.getElementById('asExpiryDate');

        // Stock being ADDED becomes its own real batch — needs its own
        // real expiry date (includes/firestore/medicine_batches.php).
        // Stock being REMOVED just draws down existing batches
        // (soonest-expiring first), no expiry input needed.
        function syncExpiryVisibility() {
            const delta = parseInt(asDeltaInput.value, 10) || 0;
            const adding = delta > 0;
            asExpiryWrap.classList.toggle('d-none', !adding);
            asExpiryInput.required = adding;
        }

        // Delegated — see the Edit button's own comment above for why.
        document.addEventListener('click', function (e) {
            const link = e.target.closest('.mvi-adjust-stock');
            if (!link) return;
            const row = link.closest('tr');
            if (!row) return;
            e.preventDefault();
            const d = row.dataset;
            adjustForm.reset();
            adjustForm.classList.remove('was-validated');
            asErrorBox.classList.add('d-none');
            asIdInput.value = d.medId;
            document.getElementById('asItemName').textContent = d.medName;
            document.getElementById('asCurrentStock').textContent = d.medStock;
            document.getElementById('asUnit').textContent = d.medUnit;
            asSubmitBtn.disabled = false;
            asSubmitBtn.innerHTML = asSubmitBtnDefaultHtml;
            syncExpiryVisibility();
            adjustModal.show();
        });

        asDeltaInput.addEventListener('input', syncExpiryVisibility);
        document.getElementById('asMinusBtn').addEventListener('click', function () {
            asDeltaInput.value = (parseInt(asDeltaInput.value, 10) || 0) - 1;
            syncExpiryVisibility();
        });
        document.getElementById('asPlusBtn').addEventListener('click', function () {
            asDeltaInput.value = (parseInt(asDeltaInput.value, 10) || 0) + 1;
            syncExpiryVisibility();
        });

        adjustForm.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const delta = parseInt(asDeltaInput.value, 10);
            if (!delta) {
                asErrorBox.textContent = 'Please enter a non-zero amount.';
                asErrorBox.classList.remove('d-none');
                return;
            }
            if (delta > 0 && !asExpiryInput.value) {
                asErrorBox.textContent = 'Please enter the expiry date for the stock being added.';
                asErrorBox.classList.remove('d-none');
                return;
            }

            asErrorBox.classList.add('d-none');
            asSubmitBtn.disabled = true;
            asSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving…';

            fetch('../api/adjust-medicine-stock.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: asIdInput.value, delta: delta, expiryDate: delta > 0 ? asExpiryInput.value : '' }),
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
                    asErrorBox.textContent = err.message;
                    asErrorBox.classList.remove('d-none');
                    asSubmitBtn.disabled = false;
                    asSubmitBtn.innerHTML = asSubmitBtnDefaultHtml;
                });
        });
    }

    // -------------------------------------------------------------
    // Dispense (No Prescription) — a patient asking the pharmacist
    // directly, no doctor's order behind it. POSTs to
    // api/dispense-without-prescription.php. Patient is picked via
    // quick-search.js's SELECT mode (#dnrPatientSearch ->
    // #dnrPatientId) — required, so the "quick-search:select" event
    // (or its absence) is what tells us a real patient was actually
    // chosen, not just typed.
    // -------------------------------------------------------------
    const dnrModalEl = document.getElementById('dispenseNoRxModal');
    if (dnrModalEl && typeof bootstrap !== 'undefined') {
        const dnrModal = new bootstrap.Modal(dnrModalEl);
        const dnrForm = document.getElementById('dispenseNoRxForm');
        const dnrErrorBox = document.getElementById('dispenseNoRxError');
        const dnrSubmitBtn = document.getElementById('dnrSubmitBtn');
        const dnrSubmitBtnDefaultHtml = dnrSubmitBtn.innerHTML;
        const dnrItemIdInput = document.getElementById('dnrItemId');
        const dnrItemTypeInput = document.getElementById('dnrItemType');
        const dnrPatientIdInput = document.getElementById('dnrPatientId');
        const dnrPatientSearch = document.getElementById('dnrPatientSearch');
        const dnrQtyInput = document.getElementById('dnrQty');
        const dnrNotesInput = document.getElementById('dnrNotes');

        function openDispenseNoRxModal(d) {
            // An expired item can never be dispensed — re-checked
            // server-side too (api/dispense-without-prescription.php),
            // this just stops the modal from even opening for one. On
            // pharmacist/medicine_vaccine_inventory.php the Dispense
            // trigger inside #mviManageModal is already disabled for an
            // expired item (see that modal's own wiring below), so this
            // alert only ever fires via subadmin's row-level menu item,
            // which has no such disabling.
            if (d.medExpiryStatus === 'expired') {
                HCDialog.alert(d.medName + ' has expired and can no longer be dispensed.');
                return;
            }
            dnrForm.reset();
            dnrErrorBox.classList.add('d-none');
            dnrItemIdInput.value = d.medId;
            dnrItemTypeInput.value = d.medType;
            dnrPatientIdInput.value = '';
            document.getElementById('dnrItemName').textContent = d.medName;
            document.getElementById('dnrCurrentStock').textContent = d.medStock;
            document.getElementById('dnrUnit').textContent = d.medUnit;
            dnrQtyInput.max = d.medStock;
            dnrSubmitBtn.disabled = false;
            dnrSubmitBtn.innerHTML = dnrSubmitBtnDefaultHtml;
            dnrModal.show();
        }
        mviActions.openDispense = openDispenseNoRxModal;

        // Delegated — see the Edit button's own comment above for why.
        document.addEventListener('click', function (e) {
            const link = e.target.closest('.mvi-dispense-no-rx');
            if (!link) return;
            const row = link.closest('tr');
            if (!row) return;
            e.preventDefault();
            openDispenseNoRxModal(row.dataset);
        });

        dnrForm.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (dnrPatientIdInput.value === '') {
                dnrErrorBox.textContent = 'Please search for and select a real patient.';
                dnrErrorBox.classList.remove('d-none');
                return;
            }
            const quantity = parseInt(dnrQtyInput.value, 10);
            if (!Number.isInteger(quantity) || quantity < 1) {
                dnrErrorBox.textContent = 'Please enter a valid quantity.';
                dnrErrorBox.classList.remove('d-none');
                return;
            }
            const stockAvailable = parseInt(dnrQtyInput.max, 10);
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
                    itemId: dnrItemIdInput.value,
                    itemType: dnrItemTypeInput.value,
                    patientId: dnrPatientIdInput.value,
                    quantity: quantity,
                    notes: dnrNotesInput.value.trim(),
                }),
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
                    dnrErrorBox.textContent = err.message;
                    dnrErrorBox.classList.remove('d-none');
                    dnrSubmitBtn.disabled = false;
                    dnrSubmitBtn.innerHTML = dnrSubmitBtnDefaultHtml;
                });
        });
    }

    // -------------------------------------------------------------
    // Delete — HCDialog.confirm() + POST to api/delete-medicine.php.
    // -------------------------------------------------------------
    // Delegated — see the Edit button's own comment near the top of this
    // file for why (subadmin/medicine_vaccine_inventory.php's AJAX
    // pagination swaps rows in without a full page reload).
    document.addEventListener('click', async function (e) {
        const link = e.target.closest('.mvi-delete');
        if (!link) return;
        const row = link.closest('tr');
        if (!row) return;
        e.preventDefault();
        const d = row.dataset;
        const confirmed = await HCDialog.confirm('Delete "' + d.medName + '"? This cannot be undone.');
        if (!confirmed) return;

        fetch('../api/delete-medicine.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: d.medId }),
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

    // -------------------------------------------------------------
    // Archive — HCDialog.confirm() + POST to api/archive-medicine.php.
    // A soft removal: the item stops appearing in every inventory list
    // (see that API's own header comment) rather than being deleted.
    // -------------------------------------------------------------
    async function performArchive(d) {
        const confirmed = await HCDialog.confirm('Archive "' + d.medName + '"? It will stop appearing in the inventory list and on the public site.');
        if (!confirmed) return;

        fetch('../api/archive-medicine.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: d.medId }),
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
    }
    mviActions.performArchive = performArchive;

    document.addEventListener('click', function (e) {
        const link = e.target.closest('.mvi-archive');
        if (!link) return;
        const row = link.closest('tr');
        if (!row) return;
        e.preventDefault();
        performArchive(row.dataset);
    });

    // -------------------------------------------------------------
    // View Stock History — fetches api/get-medicine-stock-history.php
    // on click; not server-rendered with the rest of the page since
    // it's a per-row detail nobody needs until they open it.
    // -------------------------------------------------------------
    const stockHistoryModalEl = document.getElementById('stockHistoryModal');
    if (stockHistoryModalEl && typeof bootstrap !== 'undefined') {
        const stockHistoryModal = new bootstrap.Modal(stockHistoryModalEl);
        const shLoading = document.getElementById('shLoading');
        const shEmpty = document.getElementById('shEmpty');
        const shList = document.getElementById('shList');
        const shBatchesEmpty = document.getElementById('shBatchesEmpty');
        const shBatchesTable = document.getElementById('shBatchesTable');
        const shBatchesBody = document.getElementById('shBatchesBody');

        function openStockHistoryModal(d) {
            document.getElementById('shItemName').textContent = d.medName;
            shLoading.classList.remove('d-none');
            shEmpty.classList.add('d-none');
            shList.classList.add('d-none');
            shList.innerHTML = '';
            shBatchesEmpty.classList.add('d-none');
            shBatchesTable.classList.add('d-none');
            shBatchesBody.innerHTML = '';
            stockHistoryModal.show();

            fetch('../api/get-medicine-stock-history.php?name=' + encodeURIComponent(d.medName) + '&id=' + encodeURIComponent(d.medId))
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    shLoading.classList.add('d-none');
                    if (!data.success) throw new Error(data.error || 'Could not load stock history.');

                    const batches = data.batches || [];
                    if (batches.length === 0) {
                        shBatchesEmpty.classList.remove('d-none');
                    } else {
                        batches.forEach(function (batch) {
                            const tr = document.createElement('tr');
                            const qtyTd = document.createElement('td');
                            qtyTd.textContent = batch.quantity + ' ' + d.medUnit;
                            const expiryTd = document.createElement('td');
                            expiryTd.textContent = batch.expiryDate;
                            const sourceTd = document.createElement('td');
                            sourceTd.textContent = batch.sourceLabel;
                            tr.appendChild(qtyTd);
                            tr.appendChild(expiryTd);
                            tr.appendChild(sourceTd);
                            shBatchesBody.appendChild(tr);
                        });
                        shBatchesTable.classList.remove('d-none');
                    }

                    if (!data.history.length) {
                        shEmpty.textContent = 'No recorded history for this item yet.';
                        shEmpty.classList.remove('d-none');
                        return;
                    }
                    data.history.forEach(function (entry) {
                        const li = document.createElement('li');
                        li.className = 'am-history-item';
                        const when = entry.timestamp
                            ? new Date(entry.timestamp).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' })
                            : '';
                        const detailsEl = document.createElement('div');
                        detailsEl.className = 'am-history-details';
                        detailsEl.textContent = entry.details;
                        const metaEl = document.createElement('div');
                        metaEl.className = 'am-history-meta';
                        metaEl.textContent = entry.actorName + ' — ' + when;
                        li.appendChild(detailsEl);
                        li.appendChild(metaEl);
                        shList.appendChild(li);
                    });
                    shList.classList.remove('d-none');
                })
                .catch(function (err) {
                    shLoading.classList.add('d-none');
                    shEmpty.textContent = err.message;
                    shEmpty.classList.remove('d-none');
                });
        }
        mviActions.openHistory = openStockHistoryModal;

        document.addEventListener('click', function (e) {
            const link = e.target.closest('.mvi-stock-history');
            if (!link) return;
            const row = link.closest('tr');
            if (!row) return;
            e.preventDefault();
            openStockHistoryModal(row.dataset);
        });
    }

    // -------------------------------------------------------------
    // Manage Medicine / Manage Vaccine — pharmacist/medicine_vaccine_inventory.php's
    // detailed inventory-management view (#mviManageModal): Section A
    // (medicine info, straight from the row's own dataset) and Section
    // B (a live Batch Management table, fetched fresh on every open
    // from api/get-medicine-stock-history.php — same endpoint/data
    // #stockHistoryModal's own batch list already uses, now carrying
    // each batch's own displayBatchNumber/status/reorderLevel/maxLevel
    // too — includes/firestore/medicine_batches.php's
    // hc_get_batches_for_medicine()). Dispense and View Stock History
    // reuse mviActions.* directly, not a second copy of either. Archive
    // now lives on the row itself (a separate button next to "Manage
    // Medicine"), not in this modal. This block only does anything on
    // a page that actually renders #mviManageModal (pharmacist's);
    // subadmin's page has no such element, so this is a no-op there.
    // -------------------------------------------------------------
    const manageModalEl = document.getElementById('mviManageModal');
    if (manageModalEl && typeof bootstrap !== 'undefined') {
        const manageModal = new bootstrap.Modal(manageModalEl);
        let currentRowData = null;
        let currentBatches = []; // last-fetched batch list — #restockModal's "existing batch" dropdown reuses this, no second fetch

        const mmBatchesLoading = document.getElementById('mmBatchesLoading');
        const mmBatchesEmpty = document.getElementById('mmBatchesEmpty');
        const mmBatchesWrap = document.getElementById('mmBatchesWrap');
        const mmBatchesBody = document.getElementById('mmBatchesBody');

        function loadBatches(d) {
            currentBatches = [];
            mmBatchesLoading.classList.remove('d-none');
            mmBatchesEmpty.classList.add('d-none');
            mmBatchesWrap.classList.add('d-none');
            mmBatchesBody.innerHTML = '';

            fetch('../api/get-medicine-stock-history.php?name=' + encodeURIComponent(d.medName) + '&id=' + encodeURIComponent(d.medId) + '&includeEmpty=1')
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    mmBatchesLoading.classList.add('d-none');
                    if (!data.success) throw new Error(data.error || 'Could not load batches.');

                    currentBatches = data.batches || [];
                    if (currentBatches.length === 0) {
                        mmBatchesEmpty.classList.remove('d-none');
                        return;
                    }
                    currentBatches.forEach(function (batch) {
                        const tr = document.createElement('tr');
                        const statusBadge = '<span class="mvi-badge" style="background:' + batch.status.bg + '; color:' + batch.status.color + ';">' + batch.status.label + '</span>';
                        tr.innerHTML =
                            '<td>' + batch.displayBatchNumber + '</td>' +
                            '<td>' + batch.expiryDate + '</td>' +
                            '<td>' + statusBadge + '</td>' +
                            '<td>' + batch.quantity + ' ' + d.medUnit + '</td>' +
                            '<td>' + (batch.maxLevel > 0 ? batch.maxLevel : 'No ceiling set') + '</td>' +
                            '<td>' + batch.reorderLevel + '</td>';
                        mmBatchesBody.appendChild(tr);
                    });
                    mmBatchesWrap.classList.remove('d-none');
                })
                .catch(function (err) {
                    mmBatchesLoading.classList.add('d-none');
                    mmBatchesEmpty.textContent = err.message;
                    mmBatchesEmpty.classList.remove('d-none');
                });
        }
        mviActions.getCurrentBatches = function () { return currentBatches; };

        manageModalEl.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            const row = trigger ? trigger.closest('tr') : null;
            if (!row) return;
            const d = row.dataset;
            currentRowData = d;

            document.getElementById('mviManageModalLabel').textContent = d.medType === 'vaccine' ? 'Manage Vaccine' : 'Manage Medicine';

            const thumb = document.getElementById('mmThumb');
            thumb.style.background = d.medThumbBg || '';
            thumb.style.color = d.medThumbColor || '';
            document.getElementById('mmThumbIcon').className = 'bi ' + (d.medIcon || 'bi-capsule');

            document.getElementById('mmName').textContent = d.medName;
            document.getElementById('mmNameDetail').textContent = d.medName;
            document.getElementById('mmCategory').textContent = d.medCategory;
            document.getElementById('mmUnit').textContent = d.medUnit;
            document.getElementById('mmStock').textContent = d.medStock + ' ' + d.medUnit;
            document.getElementById('mmDosage').textContent = d.medDosage || 'Not Recorded';

            const statusBadge = document.getElementById('mmStatusBadge');
            statusBadge.textContent = d.medStatusLabel || '';
            statusBadge.style.background = d.medStatusBg || '';
            statusBadge.style.color = d.medStatusColor || '';

            const expiryBadgeEl = document.getElementById('mmExpiryBadge');
            if (d.medExpiryBadgeLabel) {
                expiryBadgeEl.textContent = d.medExpiryBadgeLabel;
                expiryBadgeEl.style.background = d.medExpiryBadgeBg || '';
                expiryBadgeEl.style.color = d.medExpiryBadgeColor || '';
                expiryBadgeEl.classList.remove('d-none');
            } else {
                expiryBadgeEl.classList.add('d-none');
            }

            const descRow = document.getElementById('mmDescRow');
            if (d.medDesc) {
                document.getElementById('mmDesc').textContent = d.medDesc;
                descRow.classList.remove('d-none');
            } else {
                descRow.classList.add('d-none');
            }

            // An expired item can never be dispensed — see
            // api/dispense-without-prescription.php's own guard. Rather
            // than letting the pharmacist click Dispense and then
            // telling them no, the trigger itself is disabled up front.
            const isExpired = d.medExpiryStatus === 'expired';
            document.getElementById('mmDispenseTrigger').disabled = isExpired;
            document.getElementById('mmExpiredNote').classList.toggle('d-none', !isExpired);

            loadBatches(d);
        });

        // Hides this modal, then hands off to the chosen action once
        // it's fully hidden — same reasoning
        // subadmin/user_management.php's own handOffTo() gives:
        // Bootstrap stacks a second modal.show() fine, but waiting
        // avoids a double backdrop.
        function handOffTo(openFn) {
            if (!openFn || !currentRowData) return;
            const data = currentRowData;
            manageModalEl.addEventListener('hidden.bs.modal', function once() {
                manageModalEl.removeEventListener('hidden.bs.modal', once);
                openFn(data);
            });
            manageModal.hide();
        }

        const historyTrigger = document.getElementById('mmHistoryTrigger');
        if (historyTrigger) historyTrigger.addEventListener('click', function () { handOffTo(mviActions.openHistory); });

        const dispenseTrigger = document.getElementById('mmDispenseTrigger');
        if (dispenseTrigger) dispenseTrigger.addEventListener('click', function () { handOffTo(mviActions.openDispense); });

        // Restock stacks ON TOP of #mviManageModal instead of replacing
        // it (Bootstrap supports a second modal.show() over the first
        // fine) — a pharmacist restocking almost always wants to see
        // the updated batch table right after, so this modal is left
        // open underneath rather than hidden-then-reshown.
        const restockTrigger = document.getElementById('mmRestockTrigger');
        if (restockTrigger) {
            restockTrigger.addEventListener('click', function () {
                if (mviActions.openRestock && currentRowData) mviActions.openRestock(currentRowData);
            });
        }
    }

    // -------------------------------------------------------------
    // Restock — adds real stock a pharmacist actually received, either
    // into an existing batch (#mviManageModal's own last-fetched list,
    // via mviActions.getCurrentBatches() — no second fetch) or as a
    // brand new one. POSTs to api/restock-medicine.php, allowed for
    // both pharmacist and subadmin (unlike the free-form Adjust Stock
    // override, this endpoint only ever ADDS stock a real delivery
    // brought in). On success, re-fetches #mviManageModal's own batch
    // table in place (mviActions.reloadBatches()) rather than a full
    // page reload, so that modal stays open with the just-restocked
    // quantity visible immediately.
    // -------------------------------------------------------------
    const restockModalEl = document.getElementById('restockModal');
    if (restockModalEl && typeof bootstrap !== 'undefined') {
        const restockModal = new bootstrap.Modal(restockModalEl);
        const restockForm = document.getElementById('restockForm');
        const rsErrorBox = document.getElementById('restockError');
        const rsSubmitBtn = document.getElementById('rsSubmitBtn');
        const rsSubmitBtnDefaultHtml = rsSubmitBtn.innerHTML;
        const rsItemIdInput = document.getElementById('rsItemId');
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

        function openRestockModal(d) {
            restockForm.reset();
            restockForm.classList.remove('was-validated');
            rsErrorBox.classList.add('d-none');
            rsItemIdInput.value = d.medId;
            document.getElementById('rsItemName').textContent = d.medName;

            const batches = mviActions.getCurrentBatches ? mviActions.getCurrentBatches() : [];
            rsBatchSelect.innerHTML = '';
            if (batches.length === 0) {
                rsBatchSelect.innerHTML = '<option value="">No existing batches — add as a new batch instead</option>';
                document.getElementById('rsModeNew').checked = true;
            } else {
                batches.forEach(function (batch) {
                    const opt = document.createElement('option');
                    opt.value = batch.id;
                    opt.textContent = batch.displayBatchNumber + ' — expires ' + batch.expiryDate + ' — ' + batch.quantity + ' ' + d.medUnit + ' on hand';
                    rsBatchSelect.appendChild(opt);
                });
                document.getElementById('rsModeExisting').checked = true;
            }
            syncModeVisibility();

            rsSubmitBtn.disabled = false;
            rsSubmitBtn.innerHTML = rsSubmitBtnDefaultHtml;
            restockModal.show();
        }
        mviActions.openRestock = openRestockModal;

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

            const payload = { id: rsItemIdInput.value, mode: isExisting ? 'existing' : 'new', quantity: quantity };
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
                .then(function (res) {
                    return res.json().then(function (data) { return { ok: res.ok, data: data }; });
                })
                .then(function (result) {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.error || 'Something went wrong. Please try again.');
                    }
                    // Full reload, same as every other mutating action in
                    // this file (Adjust Stock/Dispense/Archive/Delete/
                    // Create/Update) — the main table's own row dataset
                    // (current stock, status badge, oldest-batch label)
                    // is a server-rendered rollup, not something this
                    // modal can safely patch in place from here.
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

    // Search validation (clicking "Search" with an empty box) now
    // lives in assets/js/search-form-guard.js, shared across every
    // page's search toolbar instead of duplicated per page — see that
    // file's own header comment.
});
