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
       #addMedicineModal) — no Dispense trigger is ever rendered there
       (dispensing stays a pharmacist-only clinical action; see
       subadmin/manage_medicine.php's own header comment), and no
       Delete trigger either (removed per request).
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
/** "Tablet" -> "Tablets", "Box" -> "Boxes" for count != 1 — same rule as includes/firestore/medicines.php's hc_unit_plural(). */
function unitPlural(unit, count) {
    if (count === 1 || !unit || /s$/i.test(unit)) return unit;
    return /(x|ch|sh)$/i.test(unit) ? unit + 'es' : unit + 's';
}

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

        // Patient picker — quick-search.js (SELECT mode) fills the hidden
        // #dnrPatientId and fires "quick-search:select" when a real
        // patient row is picked; this swaps the search field for a
        // selected-patient card, and "Change" swaps it back.
        const dnrPatientInput = document.getElementById('dnrPatientSearch');
        const dnrPatientIdInput = document.getElementById('dnrPatientId');
        const dnrPatientField = document.getElementById('dnrPatientSearchField');
        const dnrSelectedCard = document.getElementById('dnrSelectedPatient');
        const dnrPatientError = document.getElementById('dnrPatientError');

        function dnrSetPatientError(show) {
            dnrPatientError.classList.toggle('d-none', !show);
            dnrPatientField.classList.toggle('is-invalid', show);
        }

        function dnrShowSelectedPatient(patient) {
            document.getElementById('dnrSelectedName').textContent = patient.name;
            // 'meta' arrives as server-built HTML ("28 years &bull; Female
            // &bull; 0917...") — parsed inertly to plain text, then each
            // part rendered as its own chip via textContent.
            const metaText = new DOMParser().parseFromString(patient.meta || '', 'text/html').body.textContent;
            const metaEl = document.getElementById('dnrSelectedMeta');
            metaEl.innerHTML = '';
            metaText.split('•').map(function (part) { return part.trim(); }).filter(Boolean).forEach(function (part) {
                const chip = document.createElement('span');
                chip.textContent = part;
                metaEl.appendChild(chip);
            });
            dnrPatientField.classList.add('d-none');
            dnrSelectedCard.classList.remove('d-none');
            dnrSetPatientError(false);
        }

        // Back to an empty search field: dispatching "input" with an empty
        // value lets quick-search.js clear the hidden id, hide its clear
        // button and close any open dropdown through its own handler.
        function dnrResetPatientPicker() {
            dnrPatientInput.value = '';
            dnrPatientInput.dispatchEvent(new Event('input', { bubbles: true }));
            dnrPatientIdInput.value = '';
            dnrSelectedCard.classList.add('d-none');
            dnrPatientField.classList.remove('d-none');
        }

        dnrPatientInput.addEventListener('quick-search:select', function (e) {
            if (e.detail && dnrPatientIdInput.value !== '') dnrShowSelectedPatient(e.detail);
        });
        dnrPatientInput.addEventListener('input', function () {
            if (dnrPatientInput.value.trim() !== '') dnrSetPatientError(false);
        });
        document.getElementById('dnrChangePatientBtn').addEventListener('click', function () {
            dnrResetPatientPicker();
            dnrPatientInput.focus();
        });

        document.querySelectorAll('.mm-item-dispense').forEach(function (btn) {
            btn.addEventListener('click', function () {
                // Gate on real non-expired batch stock, not item.expiryStatus
                // (the item's soonest-expiry rollup, which reads "expired"
                // as soon as ONE batch expires, even while a later, valid
                // batch still has stock).
                const dispensable = typeof item.dispensableStock === 'number' ? item.dispensableStock : item.currentStock;
                if (dispensable <= 0) {
                    HCDialog.alert(item.name + ' has no non-expired stock left and can no longer be dispensed.');
                    return;
                }
                dnrForm.reset();
                dnrErrorBox.classList.add('d-none');
                dnrResetPatientPicker();
                dnrSetPatientError(false);
                document.getElementById('dnrItemId').value = item.id;
                document.getElementById('dnrItemType').value = item.type;
                document.getElementById('dnrItemName').textContent = item.name;
                // Show/cap at what can really be dispensed — expired stock
                // still in currentStock can't be.
                document.getElementById('dnrCurrentStock').textContent = dispensable;
                document.getElementById('dnrUnit').textContent = unitPlural(item.unit, dispensable);
                // Always the dispensing unit — individual tablets/capsules
                // for box-stocked items, never boxes.
                document.getElementById('dnrQtyUnit').textContent = unitPlural(item.unit, 2);
                document.getElementById('dnrQty').max = dispensable;
                dnrSubmitBtn.disabled = false;
                dnrSubmitBtn.innerHTML = dnrSubmitBtnDefaultHtml;
                dnrModal.show();
            });
        });

        dnrForm.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const patientId = dnrPatientIdInput.value;
            if (patientId === '') {
                // Typed text that was never picked from the results isn't a
                // registered patient — same message either way.
                dnrErrorBox.classList.add('d-none');
                dnrSetPatientError(true);
                dnrPatientInput.focus();
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
    // Archive Expired Batch — only on an expired batch's own row.
    // Archives just THAT batch (api/archive-medicine-batch.php), so a
    // still-valid batch of the same item keeps its stock; the item's
    // Total Current Stock drops by the expired quantity. Reloads so the
    // table and info row reflect it.
    // -------------------------------------------------------------
    document.querySelectorAll('.mm-batch-archive').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const d = btn.dataset;
            const lotWord = item.type === 'vaccine' ? 'lot' : 'batch'; // vaccines are tracked by lot
            const confirmed = await HCDialog.confirm('Archive expired ' + lotWord + ' ' + d.batchNumber + ' of "' + item.name + '"? Its ' + d.batchQty + ' will be removed from stock. Other ' + (lotWord === 'lot' ? 'lots' : 'batches') + ' are not affected.');
            if (!confirmed) return;

            btn.disabled = true;
            fetch('../api/archive-medicine-batch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ batchId: d.batchId }),
            })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (result) {
                    if (!result.ok || !result.data.success) throw new Error(result.data.error || 'Something went wrong. Please try again.');
                    window.location.reload();
                })
                .catch(function (err) {
                    btn.disabled = false;
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
        const rsBatchNumberInput = document.getElementById('rsBatchNumber');
        const rsNewExpiryInput = document.getElementById('rsNewExpiry');
        const rsQtyInput = document.getElementById('rsQty');
        const rsQtyUnitSelect = document.getElementById('rsQtyUnit');
        const rsQtyUnitLabel = document.getElementById('rsQtyUnitLabel');
        const rsQtyPreview = document.getElementById('rsQtyPreview');
        const rsPpbWrap = document.getElementById('rsPpbWrap');
        const rsPpbInput = document.getElementById('rsPiecesPerBox');
        const rsPpbChangeBtn = document.getElementById('rsPpbChangeBtn');
        const rsPpbHelp = document.getElementById('rsPpbHelp');
        // This medicine's OWN configured count per box (0 = not set yet)
        // — never a fixed number. Inhalers/vials/ampules/doses are never
        // received in boxes; bottles/sachets/custom units may be, as boxes
        // of whole bottles/sachets (same rule as
        // includes/firestore/medicines.php's hc_unit_allows_boxes()).
        const configuredPiecesPerBox = item.piecesPerBox || 0;
        const unitKey = (item.unit || '').trim().toLowerCase().replace(/s$/, '');
        const allowsBoxes = ['inhaler', 'vial', 'ampule', 'dose'].indexOf(unitKey) === -1;
        const requiresBoxes = unitKey === 'tablet' || unitKey === 'capsule';

        function inBoxes() {
            return allowsBoxes && rsQtyUnitSelect.value === 'box';
        }

        // Preview only — api/restock-medicine.php does the real boxes ->
        // pieces conversion (and saves a new/corrected per-box count).
        function syncQtyPreview() {
            const qty = parseInt(rsQtyInput.value, 10);
            const perBox = parseInt(rsPpbInput.value, 10);
            rsPpbWrap.classList.toggle('d-none', !inBoxes());
            const show = inBoxes() && qty > 0 && perBox > 0;
            rsQtyPreview.classList.toggle('d-none', !show);
            if (show) {
                const pieces = qty * perBox;
                rsQtyPreview.textContent = qty.toLocaleString() + ' ' + unitPlural('box', qty).toLowerCase() + ' × ' + perBox.toLocaleString() + ' ' +
                    unitPlural(item.unit, perBox).toLowerCase() + ' = ' + pieces.toLocaleString() + ' ' + unitPlural(item.unit, pieces).toLowerCase() + ' added to stock';
            }
        }
        rsQtyInput.addEventListener('input', syncQtyPreview);
        rsQtyUnitSelect.addEventListener('change', syncQtyPreview);
        rsPpbInput.addEventListener('input', syncQtyPreview);

        function resetPiecesPerBoxField() {
            const label = unitPlural(item.unit, 2) + ' per Box';
            document.getElementById('rsPpbLabel').textContent = label;
            rsPpbInput.value = configuredPiecesPerBox > 0 ? configuredPiecesPerBox : '';
            rsPpbInput.readOnly = configuredPiecesPerBox > 0;
            rsPpbChangeBtn.classList.toggle('d-none', configuredPiecesPerBox === 0);
            rsPpbHelp.textContent = configuredPiecesPerBox > 0
                ? 'Configured for ' + item.name + '. Use Change only if this product\'s box holds a different amount.'
                : 'Not set for ' + item.name + ' yet — enter the count printed on its box. It will be saved, so you won\'t need to enter it again.';
        }
        rsPpbChangeBtn.addEventListener('click', function () {
            rsPpbInput.readOnly = false;
            rsPpbChangeBtn.classList.add('d-none');
            rsPpbHelp.textContent = 'The new count will be saved to ' + item.name + ' and used for every future restock.';
            rsPpbInput.focus();
            rsPpbInput.select();
        });

        restockBtn.addEventListener('click', function () {
            restockForm.reset();
            restockForm.classList.remove('was-validated');
            rsErrorBox.classList.add('d-none');
            document.getElementById('rsItemId').value = item.id;
            document.getElementById('rsItemName').textContent = item.name;
            document.getElementById('rsDosage').value = item.dosage || '';

            // Boxes or loose pieces for anything that can come boxed;
            // bottles/inhalers are entered in their own unit only.
            rsQtyUnitSelect.classList.toggle('d-none', !allowsBoxes);
            rsQtyUnitLabel.classList.toggle('d-none', allowsBoxes);
            rsQtyUnitLabel.textContent = unitPlural(item.unit, 2);
            document.getElementById('rsQtyUnitOption').textContent = unitPlural(item.unit, 2);
            rsQtyUnitSelect.value = allowsBoxes && (configuredPiecesPerBox > 0 || requiresBoxes) ? 'box' : 'unit';
            resetPiecesPerBoxField();
            syncQtyPreview();

            rsSubmitBtn.disabled = false;
            rsSubmitBtn.innerHTML = rsSubmitBtnDefaultHtml;
            restockModal.show();
        });

        restockForm.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const quantity = parseInt(rsQtyInput.value, 10);
            if (!Number.isInteger(quantity) || quantity < 1) {
                rsErrorBox.textContent = 'Please enter a valid quantity.';
                rsErrorBox.classList.remove('d-none');
                return;
            }

            const payload = {
                id: item.id,
                mode: 'new', // every restock is its own new batch
                quantity: quantity,
                quantityUnit: inBoxes() ? 'box' : 'unit',
                dosage: document.getElementById('rsDosage').value.trim(),
            };
            if (inBoxes()) {
                const perBox = parseInt(rsPpbInput.value, 10);
                if (!Number.isInteger(perBox) || perBox < 1) {
                    rsErrorBox.textContent = 'Please enter how many ' + unitPlural(item.unit, 2).toLowerCase() + ' come in one box.';
                    rsErrorBox.classList.remove('d-none');
                    rsPpbInput.focus();
                    return;
                }
                payload.piecesPerBox = perBox;
            }
            if (rsBatchNumberInput.value.trim() === '') {
                rsErrorBox.textContent = item.type === 'vaccine'
                    ? 'Please enter the lot number for this new stock.'
                    : 'Please enter the batch number for this new batch.';
                rsErrorBox.classList.remove('d-none');
                rsBatchNumberInput.focus();
                return;
            }
            if (!rsNewExpiryInput.value) {
                rsErrorBox.textContent = item.type === 'vaccine'
                    ? 'Please enter the expiry date for this new lot.'
                    : 'Please enter the expiry date for this new batch.';
                rsErrorBox.classList.remove('d-none');
                rsNewExpiryInput.focus();
                return;
            }
            payload.batchNumber = rsBatchNumberInput.value.trim();
            payload.expiryDate = rsNewExpiryInput.value;

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
            // Reset the submit button back to its normal state (undoing
            // a previous "Saving…" spinner) BEFORE setting "Save
            // Changes" below, not after — innerHTML replaces
            // #amSubmitLabel with a fresh node, so doing this reset
            // afterward would silently stomp "Save Changes" back to
            // whatever text was in the button's very first page-load
            // markup ("Add Medicine") — the same bug confirmed on the
            // Add Vaccine flow (assets/js/medicine_vaccine_inventory_pharmacist.js).
            editSubmitBtn.disabled = false;
            editSubmitBtn.innerHTML = editSubmitBtnDefaultHtml;

            document.getElementById('amFormType').value = item.type;
            document.getElementById('amFormId').value = item.id;
            document.getElementById('addMedicineModalLabel').textContent = item.type === 'vaccine' ? 'Edit Vaccine' : 'Edit Medicine';
            document.getElementById('amNameLabel').textContent = item.type === 'vaccine' ? 'Vaccine Name' : 'Medicine Name';
            document.getElementById('amSubmitLabel').textContent = 'Save Changes';

            document.getElementById('amName').value = item.name;
            document.getElementById('amCategory').value = item.category;
            document.getElementById('amUnit').value = item.unit;
            document.getElementById('amPiecesPerBox').value = item.piecesPerBox > 0 ? item.piecesPerBox : '';

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

            editModal.show();
        });

        editForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (!editForm.checkValidity()) {
                editForm.classList.add('was-validated');
                return;
            }

            // Stock on hand stays counted in the old unit — make a unit
            // change on a stocked item a deliberate choice
            // (api/update-medicine.php refuses it without confirmUnitChange).
            const newUnit = document.getElementById('amUnit').value.trim();
            const unitChanged = newUnit.toLowerCase() !== (item.unit || '').toLowerCase();
            if (unitChanged && item.currentStock > 0) {
                const confirmed = await HCDialog.confirm(
                    'Change the dispensing unit from ' + item.unit + ' to ' + newUnit + '? The ' + item.currentStock + ' ' +
                    unitPlural(item.unit, item.currentStock).toLowerCase() + ' already in stock will NOT be converted — only do this to correct a wrong unit.'
                );
                if (!confirmed) return;
            }

            const payload = {
                confirmUnitChange: unitChanged,
                id: item.id,
                type: item.type,
                name: document.getElementById('amName').value.trim(),
                category: document.getElementById('amCategory').value.trim(),
                unit: document.getElementById('amUnit').value.trim(),
                piecesPerBox: document.getElementById('amPiecesPerBox').value,
                minLevel: document.getElementById('amMinLevel').value,
                reorderLevel: document.getElementById('amReorderLevel').value,
                maxLevel: document.getElementById('amMaxLevel').value,
                desc: document.getElementById('amDesc').value.trim(),
                dosage: document.getElementById('amDosage').value.trim(),
            };

            // Captured HERE, not reused from editSubmitBtnDefaultHtml —
            // that's the button's very first page-load markup ("Add
            // Medicine"), not "Save Changes". A failed save needs to
            // restore to THIS edit modal's own idle state, not revert
            // the label back to a completely different mode's text.
            const idleHtml = editSubmitBtn.innerHTML;
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
                    editSubmitBtn.innerHTML = idleHtml;
                });
        });
    }

});
