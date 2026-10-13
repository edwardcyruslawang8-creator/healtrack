/* =============================================================
   MEDICINE & VACCINE INVENTORY JS
   (pharmacist/medicine_vaccine_inventory.php AND
   subadmin/medicine_vaccine_inventory.php — role-agnostic, both pages
   load this same file)
   ---------------------------------------------------------------
   Drives three shared modals:
     - Add/Edit (includes/pharmacist/add_medicine_modal.php)
     - View Details + Adjust Stock (includes/pharmacist/medicine_action_modals.php)
   One form for Add AND Edit, since medicines and vaccines are the same
   real "medicines" Firestore collection split by a "type" field (see
   includes/firestore/medicines.php's header comment).

   Every row's data lives in data-med-* attributes on the <tr> itself
   (set server-side by whichever page rendered it) — Edit/View
   Details/Adjust Stock/Delete all read from the clicked row's
   closest('tr').dataset rather than each carrying its own copy.

   Add: "+ Add Medicine"/"+ Add Vaccine" open the form empty
   (#amFormId blank) and submitting POSTs to api/create-medicine.php.
   Edit: posts to api/update-medicine.php instead. Delete: HCDialog.confirm() +
   POST to api/delete-medicine.php. Adjust Stock: POSTs to
   api/adjust-medicine-stock.php. Every one of these reloads the page
   on success so the table/summary cards re-render from fresh
   Firestore data.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
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
        const todayIso = expiryInput.min; // set server-side to today (see add_medicine_modal.php)

        var openModalAs = function (type) {
            form.reset();
            form.classList.remove('was-validated');
            errorBox.classList.add('d-none');
            typeInput.value = type;
            idInput.value = '';
            expiryInput.min = todayIso;

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
            // Already-expired items (real inventory can age past its expiry
            // date) must stay editable for their OTHER fields without the
            // date picker rejecting their own unchanged value — so the min
            // here relaxes to the item's existing expiry when that's
            // earlier than today, instead of always requiring today+.
            expiryInput.min = d.medExpiry < todayIso ? d.medExpiry : todayIso;
            expiryInput.value = d.medExpiry;
            document.getElementById('amCurrentStock').value = d.medStock;
            document.getElementById('amMinLevel').value = d.medMinLevel;
            document.getElementById('amReorderLevel').value = d.medReorderLevel;
            document.getElementById('amDesc').value = d.medDesc || '';

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
                desc: document.getElementById('amDesc').value.trim(),
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
            adjustModal.show();
        });

        document.getElementById('asMinusBtn').addEventListener('click', function () {
            asDeltaInput.value = (parseInt(asDeltaInput.value, 10) || 0) - 1;
        });
        document.getElementById('asPlusBtn').addEventListener('click', function () {
            asDeltaInput.value = (parseInt(asDeltaInput.value, 10) || 0) + 1;
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

            asErrorBox.classList.add('d-none');
            asSubmitBtn.disabled = true;
            asSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving…';

            fetch('../api/adjust-medicine-stock.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: asIdInput.value, delta: delta }),
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

        // Delegated — see the Edit button's own comment above for why.
        document.addEventListener('click', function (e) {
            const link = e.target.closest('.mvi-dispense-no-rx');
            if (!link) return;
            const row = link.closest('tr');
            if (!row) return;
            e.preventDefault();
            const d = row.dataset;
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
    document.addEventListener('click', async function (e) {
        const link = e.target.closest('.mvi-archive');
        if (!link) return;
        const row = link.closest('tr');
        if (!row) return;
        e.preventDefault();
        const d = row.dataset;
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

        document.addEventListener('click', function (e) {
            const link = e.target.closest('.mvi-stock-history');
            if (!link) return;
            const row = link.closest('tr');
            if (!row) return;
            e.preventDefault();
            const d = row.dataset;

            document.getElementById('shItemName').textContent = d.medName;
            shLoading.classList.remove('d-none');
            shEmpty.classList.add('d-none');
            shList.classList.add('d-none');
            shList.innerHTML = '';
            stockHistoryModal.show();

            fetch('../api/get-medicine-stock-history.php?name=' + encodeURIComponent(d.medName))
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    shLoading.classList.add('d-none');
                    if (!data.success) throw new Error(data.error || 'Could not load stock history.');
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
        });
    }

    // Search validation (clicking "Search" with an empty box) now
    // lives in assets/js/search-form-guard.js, shared across every
    // page's search toolbar instead of duplicated per page — see that
    // file's own header comment.
});
