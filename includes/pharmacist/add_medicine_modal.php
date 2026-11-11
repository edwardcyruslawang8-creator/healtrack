<!-- =============================================================
     ADD/EDIT MEDICINE / ADD/EDIT VACCINE MODAL (shared form)
     -------------------------------------------------------------
     Triggered by pharmacist/medicine_vaccine_inventory.php's
     "+ Add Medicine" (#btnAddMedicine) and "+ Add Vaccine"
     (#btnAddVaccine) buttons, AND each row's Edit (pencil) button —
     one shared form for all four, since medicines and vaccines are
     the same real "medicines" Firestore collection, split only by a
     "type" field (see includes/firestore/medicines.php's header
     comment). assets/js/medicine_vaccine_inventory_pharmacist.js sets
     the hidden #amFormType/#amFormId fields and adjusts labels/title
     based on which button was clicked, then POSTs to
     api/create-medicine.php (Add, #amFormId empty) or
     api/update-medicine.php (Edit, #amFormId set to the item's real
     doc id). "Status" (In Stock/Low Stock/Out of Stock) isn't a form
     field either way — it's computed server-side from Current Stock
     vs Minimum Level.

     ADDING a new item now only collects its catalog info — Name/
     Category/Unit/Description. Expiry Date, Current Stock, Minimum
     Level, Reorder Level, Maximum Level, and Dosage Information are
     all hidden in this mode (per explicit request) — a brand-new item
     is created with 0 real stock and no thresholds, then a pharmacist
     adds its real first batch (with its own real expiry date and
     Dosage Information) through Restock on pharmacist/manage_medicine.php,
     which also auto-computes Minimum/Reorder Level from whatever
     quantity results (includes/firestore/medicines.php's
     hc_auto_compute_thresholds()) — one real place real stock enters
     the system, instead of two.

     EDITING an existing item still shows Minimum Level/Reorder Level/
     Maximum Level/Dosage Information (a sub-admin's own capability,
     unaffected by the above) — just not Expiry Date/Current Stock,
     which were already a read-only rollup-over-batches summary instead
     (#amStockExpiryReadonlyNote) since more than one batch with a
     different expiry can exist per item. See
     assets/js/medicine_vaccine_inventory_pharmacist.js's
     openModalAs()/openModalForEdit() for exactly which rows toggle
     per mode.

     No separate "Form" (Tablet/Capsule/Syrup/...) field — it asked for
     the same thing "Unit" already does (their own example placeholders
     literally overlapped, e.g. "Capsule"), so api/create-medicine.php
     and api/update-medicine.php now derive the stored "form" field
     from this same Unit value server-side instead of collecting it
     twice; every other page that reads "form" (patient/medicine.php's
     own Form column/filter, Inventory Reports) keeps working unchanged.
     ============================================================= -->
<div class="modal fade am-modal" id="addMedicineModal" tabindex="-1" aria-labelledby="addMedicineModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="addMedicineForm" novalidate>
        <input type="hidden" id="amFormType" value="medicine">
        <input type="hidden" id="amFormId" value="">
        <div class="modal-header">
          <h5 class="modal-title" id="addMedicineModalLabel">Add Medicine</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="addMedicineError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>

          <div class="mb-3">
            <label class="am-form-label" for="amName"><span id="amNameLabel">Medicine Name</span><span class="text-danger">*</span></label>
            <input type="text" class="form-control am-form-control" id="amName" placeholder="e.g. Amoxicillin 500 mg Capsule" required autocomplete="off">
            <!-- Live spelling check against includes/shared/medicine_name_reference.php's
                 known drug/vaccine names (api/check-medicine-name.php) — see
                 this file's own script below. Only ever suggests a
                 correction for a likely typo of a well-known name; a name
                 it doesn't recognize at all is left alone. -->
            <div class="am-form-help text-danger d-none" id="amNameSpellWarning">
                Checking spelling…
            </div>
            <div class="am-name-suggestion d-none" id="amNameSuggestionBox">
                Did you mean <strong id="amNameSuggestionText"></strong>?
                <button type="button" class="am-name-suggestion-btn" id="amNameUseSuggestion">Use this spelling</button>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="am-form-label" for="amCategory">Category<span class="text-danger">*</span></label>
              <input type="text" class="form-control am-form-control" id="amCategory" placeholder="e.g. Antibiotic" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="am-form-label" for="amUnitSelect">Dispensing Unit<span class="text-danger">*</span></label>
              <!-- #amUnit (hidden) is the one value every script reads/
                   writes (medicine_vaccine_inventory_pharmacist.js,
                   manage_medicine.js); this dropdown + the "Other" box
                   below set it — see this file's own script. -->
              <select class="form-select am-form-control" id="amUnitSelect" required>
                <option value="">Select unit</option>
                <option value="Tablet">Tablet</option>
                <option value="Capsule">Capsule</option>
                <option value="Bottle">Bottle</option>
                <option value="Inhaler">Inhaler</option>
                <option value="Vial">Vial</option>
                <option value="Ampule">Ampule</option>
                <option value="Sachet">Sachet</option>
                <option value="Dose" data-vaccine-only="1">Dose</option>
                <option value="__other">Other</option>
              </select>
              <input type="hidden" id="amUnit" value="">
              <input type="text" class="form-control am-form-control mt-2 d-none" id="amUnitOther" placeholder="Specify unit, e.g. Tube, Pack, Nebule" maxlength="30">
              <div class="am-form-help">The unit stock is counted, monitored and dispensed in.</div>
              <div class="am-form-help text-danger d-none" id="amUnitChangeWarning"></div>
            </div>
          </div>
          <!-- Optional box packaging over the dispensing unit — see
               includes/firestore/medicines.php's hc_box_breakdown(). -->
          <div class="row" id="amPiecesPerBoxRow">
            <div class="col-md-6 mb-3">
              <label class="am-form-label" for="amPiecesPerBox"><span id="amPiecesPerBoxLabel">Tablets/Capsules per Box</span> <span class="text-muted fw-normal" id="amPiecesPerBoxOptional">(Optional)</span><span class="text-danger d-none" id="amPiecesPerBoxRequired">*</span></label>
              <input type="number" class="form-control am-form-control" id="amPiecesPerBox" min="1" step="1" placeholder="e.g. 50">
              <div class="am-form-help" id="amPiecesPerBoxHelp">How many come in one box of THIS product, as printed on its box — used to convert boxes received into individual pieces.</div>
            </div>
          </div>

          <!-- Add only: the item's first stock, received exactly like a
               "new batch" Restock (includes/pharmacist/medicine_action_modals.php's
               #restockModal) — batch number + expiry + quantity in boxes
               or pieces, converted with the per-box count above.
               Required (by explicit request — every new item must be
               added with real starting stock, no "add with 0 stock,
               restock later" path any more). api/create-medicine.php
               creates the batch and computes Minimum/Reorder Level the
               same way api/restock-medicine.php does. -->
          <div class="am-initial-stock d-none" id="amInitialStockSection">
            <div class="am-initial-stock-title">Initial Stock</div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <!-- "Lot Number" for vaccines, "Batch Number" for medicines — set by openAddModal() in assets/js/medicine_vaccine_inventory_pharmacist.js. -->
                <label class="am-form-label" for="amIsBatchNumber"><span id="amIsBatchLabel">Batch Number</span><span class="text-danger">*</span></label>
                <input type="text" class="form-control am-form-control" id="amIsBatchNumber" placeholder="e.g. BATCH-2026-045" maxlength="50" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="am-form-label" for="amIsExpiry">Expiry Date<span class="text-danger">*</span></label>
                <input type="date" data-hc-datepicker class="form-control am-form-control" id="amIsExpiry" min="<?= date('Y-m-d') ?>" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="am-form-label" for="amIsQty">Quantity Received<span class="text-danger">*</span></label>
              <div class="rs-qty-row">
                <input type="number" class="form-control am-form-control" id="amIsQty" min="1" step="1" placeholder="e.g. 100" required>
                <select class="form-select am-form-control d-none" id="amIsQtyUnit" aria-label="Quantity unit">
                  <option value="box">Boxes</option>
                  <option value="unit" id="amIsQtyUnitOption">Pieces</option>
                </select>
                <span class="rs-qty-unit-label" id="amIsQtyUnitLabel"></span>
              </div>
              <div class="am-form-help rs-qty-preview d-none" id="amIsPreview"></div>
              <div class="am-form-help">Minimum Level and Reorder Level are calculated automatically from this stock, the same as a restock.</div>
            </div>
          </div>

          <!-- Only shown when ADDING a new item — an existing item's
               stock/expiry are a real rollup over its own batches now
               (includes/firestore/medicine_batches.php), not a single
               free-form pair any edit could honestly overwrite once
               more than one batch with a different expiry exists.
               Real stock changes for an EXISTING item go through
               Adjust Stock (its own batch-aware modal) or a stock
               request release instead — see assets/js/medicine_vaccine_inventory_pharmacist.js's
               openModalForEdit(). -->
          <div class="row" id="amStockExpiryRow">
            <div class="col-md-6 mb-3">
              <label class="am-form-label" for="amExpiryDate">Expiry Date<span class="text-danger">*</span></label>
              <input type="date" data-hc-datepicker class="form-control am-form-control" id="amExpiryDate" min="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="am-form-label" for="amCurrentStock">Current Stock<span class="text-danger">*</span></label>
              <input type="number" class="form-control am-form-control" id="amCurrentStock" min="0" step="1" placeholder="0" required>
            </div>
          </div>
          <!-- Edit-mode-only read-only summary, replacing the row above. -->
          <div class="am-form-help d-none" id="amStockExpiryReadonlyNote"></div>

          <!-- Edit-only — see this file's own header comment. -->
          <div class="row" id="amThresholdsRow">
            <div class="col-md-6 mb-3">
              <label class="am-form-label" for="amMinLevel">Minimum Level<span class="text-danger">*</span></label>
              <input type="number" class="form-control am-form-control" id="amMinLevel" min="0" step="1" placeholder="0">
              <div class="am-form-help">Stock at or below this is flagged "Low Stock".</div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="am-form-label" for="amReorderLevel">Reorder Level<span class="text-danger">*</span></label>
              <input type="number" class="form-control am-form-control" id="amReorderLevel" min="0" step="1" placeholder="0">
            </div>
          </div>

          <!-- Edit-only — see this file's own header comment. -->
          <div class="row" id="amMaxLevelRow">
            <div class="col-md-6 mb-3">
              <label class="am-form-label" for="amMaxLevel">Maximum Level <span class="text-muted fw-normal">(Optional)</span></label>
              <input type="number" class="form-control am-form-control" id="amMaxLevel" min="0" step="1" placeholder="0">
              <div class="am-form-help">Stock above this is flagged "Overstocked". Leave blank for no ceiling.</div>
            </div>
          </div>

          <div class="mb-3">
            <label class="am-form-label" for="amDesc">Description <span class="text-muted fw-normal">(Optional)</span></label>
            <textarea class="form-control am-form-control" id="amDesc" rows="2" placeholder="Brief note about this item"></textarea>
          </div>

          <!-- Edit-only — see this file's own header comment. -->
          <div class="mb-0" id="amDosageRow">
            <label class="am-form-label" for="amDosage">Dosage Information <span class="text-muted fw-normal">(Optional)</span></label>
            <textarea class="form-control am-form-control" id="amDosage" rows="2" placeholder="e.g. Adults: 500mg every 8 hours for 7 days"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="am-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="am-btn-submit" id="amSubmitBtn"><i class="bi bi-plus-lg"></i> <span id="amSubmitLabel">Add Medicine</span></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Dispensing Unit dropdown + its packaging field — same rules as
// includes/firestore/medicines.php's hc_unit_allows_boxes()/
// hc_unit_requires_pieces_per_box(), which api/create-medicine.php and
// api/update-medicine.php enforce again server-side:
//   Tablet/Capsule  -> "Tablets/Capsules per Box", required
//   Bottle/Sachet/Other -> "<unit>s per Box", optional (bulk receiving only)
//   Inhaler/Vial/Ampule/Dose -> no packaging field at all
// The dispensing unit itself is always what stock is counted and
// dispensed in; packaging only converts boxes at Restock.
(function () {
    const modalEl = document.getElementById('addMedicineModal');
    const unitSelect = document.getElementById('amUnitSelect');
    const unitHidden = document.getElementById('amUnit');
    const unitOther = document.getElementById('amUnitOther');
    const ppbInput = document.getElementById('amPiecesPerBox');
    const warning = document.getElementById('amUnitChangeWarning');
    if (!modalEl || !unitSelect || !unitHidden || !ppbInput) return;

    let originalUnit = '';

    function plural(unit) {
        return /s$/i.test(unit) ? unit : (/(x|ch|sh)$/i.test(unit) ? unit + 'es' : unit + 's');
    }

    function syncPackaging() {
        const unit = unitHidden.value.trim();
        const key = unit.toLowerCase().replace(/s$/, '');
        const requiresBox = key === 'tablet' || key === 'capsule';
        const noBoxes = unit === '' || ['inhaler', 'vial', 'ampule', 'dose'].indexOf(key) !== -1;

        document.getElementById('amPiecesPerBoxRow').classList.toggle('d-none', noBoxes);
        if (noBoxes) ppbInput.value = '';
        ppbInput.required = requiresBox;
        ppbInput.placeholder = requiresBox ? 'e.g. 100' : 'e.g. 12';
        document.getElementById('amPiecesPerBoxRequired').classList.toggle('d-none', !requiresBox);
        document.getElementById('amPiecesPerBoxOptional').classList.toggle('d-none', requiresBox);
        document.getElementById('amPiecesPerBoxLabel').textContent = plural(unit || 'Piece') + ' per Box';
        document.getElementById('amPiecesPerBoxHelp').textContent = requiresBox
            ? 'How many ' + plural(key) + ' come in one box of THIS product, as printed on its box. Boxes received are converted into individual ' + plural(key) + '; stock is counted and dispensed per ' + key + '.'
            : 'Only if ' + plural(key) + ' are received in boxes (bulk receiving). Stock is still counted and dispensed per ' + key + '.';

        // Edit only: stock already on hand is counted in the old unit and
        // is never converted — say so before it's saved.
        const isEdit = document.getElementById('amFormId').value !== '';
        const changed = isEdit && originalUnit !== '' && unit !== '' && unit.toLowerCase() !== originalUnit.toLowerCase();
        warning.classList.toggle('d-none', !changed);
        if (changed) {
            warning.textContent = 'Existing stock was counted in ' + plural(originalUnit).toLowerCase() + ' and will NOT be converted — only change this to correct a wrong unit.';
        }

        syncInitialStock(noBoxes, requiresBox);
    }

    // Add only — Initial Stock, same receiving rules as Restock: boxes
    // (converted with THIS form's per-box count) or loose pieces, never
    // boxes for inhalers/vials/ampules/doses. Quantity/Batch Number/
    // Expiry all carry the HTML required attribute directly now (see
    // this file's markup) — no more conditional toggling based on
    // whether a quantity was typed.
    const isQty = document.getElementById('amIsQty');
    const isQtyUnit = document.getElementById('amIsQtyUnit');
    const isQtyUnitLabel = document.getElementById('amIsQtyUnitLabel');
    const isPreview = document.getElementById('amIsPreview');
    let lastBoxRule = null;

    function syncInitialStock(noBoxes, requiresBox) {
        const unit = unitHidden.value.trim();
        if (noBoxes === undefined) {
            const key = unit.toLowerCase().replace(/s$/, '');
            noBoxes = unit === '' || ['inhaler', 'vial', 'ampule', 'dose'].indexOf(key) !== -1;
            requiresBox = key === 'tablet' || key === 'capsule';
        }
        const unitLabel = plural(unit || 'Piece');
        isQtyUnit.classList.toggle('d-none', noBoxes);
        isQtyUnitLabel.classList.toggle('d-none', !noBoxes || unit === '');
        isQtyUnitLabel.textContent = unitLabel;
        document.getElementById('amIsQtyUnitOption').textContent = unitLabel;
        // Default to Boxes for tablets/capsules, pieces otherwise — only
        // when the unit's rule changes, so a manual choice isn't undone
        // on every keystroke.
        const rule = noBoxes ? 'none' : (requiresBox ? 'box' : 'unit');
        if (rule !== lastBoxRule) {
            isQtyUnit.value = rule === 'box' ? 'box' : 'unit';
            lastBoxRule = rule;
        }

        const qty = parseInt(isQty.value, 10);
        const perBox = parseInt(ppbInput.value, 10);
        const inBoxes = !noBoxes && isQtyUnit.value === 'box';
        let text = '';
        if (qty > 0 && inBoxes) {
            text = perBox > 0
                ? qty.toLocaleString() + ' ' + (qty === 1 ? 'box' : 'boxes') + ' × ' + perBox.toLocaleString() + ' ' + plural(unit).toLowerCase() + ' = ' + (qty * perBox).toLocaleString() + ' ' + plural(unit).toLowerCase() + ' added to stock'
                : 'Enter ' + plural(unit) + ' per Box above to convert boxes into ' + plural(unit).toLowerCase() + '.';
        } else if (qty > 0 && unit !== '') {
            text = qty.toLocaleString() + ' ' + (qty === 1 ? unit : plural(unit)).toLowerCase() + ' added to stock';
        }
        isPreview.textContent = text;
        isPreview.classList.toggle('d-none', text === '');
    }
    isQty.addEventListener('input', function () { syncInitialStock(); });
    isQtyUnit.addEventListener('change', function () { syncInitialStock(); });
    ppbInput.addEventListener('input', function () { syncInitialStock(); });

    // Dropdown/Other box -> hidden #amUnit (the value every script submits).
    function applySelection() {
        const isOther = unitSelect.value === '__other';
        unitOther.classList.toggle('d-none', !isOther);
        unitOther.required = isOther;
        unitHidden.value = isOther ? unitOther.value.trim() : unitSelect.value;
        syncPackaging();
    }

    unitSelect.addEventListener('change', function () {
        // A different unit means a different package — never carry an old
        // per-box count over into the new unit's conversion.
        ppbInput.value = '';
        if (unitSelect.value === '__other') unitOther.value = '';
        applySelection();
        if (unitSelect.value === '__other') unitOther.focus();
    });
    unitOther.addEventListener('input', applySelection);

    // Hidden #amUnit -> dropdown, whenever the modal opens (Add resets it
    // to blank, or "Dose" for a vaccine; Edit fills the item's own unit).
    // A unit that isn't one of the listed options (e.g. "Nebule") shows
    // as Other with that exact unit, so it's never silently replaced.
    modalEl.addEventListener('show.bs.modal', function () {
        const isVaccine = document.getElementById('amFormType').value === 'vaccine';
        unitSelect.querySelectorAll('[data-vaccine-only]').forEach(function (opt) {
            opt.hidden = !isVaccine;
            opt.disabled = !isVaccine;
        });
        const current = unitHidden.value.trim();
        const isEdit = document.getElementById('amFormId').value !== '';
        originalUnit = isEdit ? current : '';
        // Initial Stock is Add-only (an existing item's stock changes go
        // through Restock); start each Add from a clean slate.
        document.getElementById('amInitialStockSection').classList.toggle('d-none', isEdit);
        if (!isEdit) {
            ['amIsBatchNumber', 'amIsExpiry', 'amIsQty'].forEach(function (id) { document.getElementById(id).value = ''; });
            lastBoxRule = null;
        }
        const match = Array.prototype.find.call(unitSelect.options, function (opt) {
            return !opt.disabled && opt.value !== '__other' && opt.value !== '' && opt.value.toLowerCase() === current.toLowerCase();
        });
        unitSelect.value = current === '' ? '' : (match ? match.value : '__other');
        unitOther.value = current !== '' && !match ? current : '';
        applySelection();
    });
})();

// "Medicine Name" live spelling check — api/check-medicine-name.php,
// backed by includes/shared/medicine_name_reference.php's known
// generic drug/vaccine names. Only ever flags a likely TYPO of a
// well-known name (never "this drug doesn't exist") and only blocks
// submission while that suggestion is unresolved — accepting it (or
// editing the name into something that no longer looks like a typo)
// clears the block immediately. api/create-medicine.php and
// api/update-medicine.php re-run the exact same check server-side, so
// this can't be bypassed by skipping the live check.
(function () {
    const nameInput = document.getElementById('amName');
    const warningEl = document.getElementById('amNameSpellWarning');
    const suggestionBox = document.getElementById('amNameSuggestionBox');
    const suggestionText = document.getElementById('amNameSuggestionText');
    const useSuggestionBtn = document.getElementById('amNameUseSuggestion');
    const form = document.getElementById('addMedicineForm');
    const submitBtn = document.getElementById('amSubmitBtn');
    if (!nameInput || !form || !suggestionBox) return;

    let checkTimer = null;
    let lastCheckedValue = null;
    let hasUnresolvedSuggestion = false;

    function setBlocked(blocked) {
        hasUnresolvedSuggestion = blocked;
        if (submitBtn) submitBtn.disabled = blocked;
    }

    function clearSuggestion() {
        suggestionBox.classList.add('d-none');
        setBlocked(false);
    }

    function showSuggestion(suggestion) {
        suggestionText.textContent = suggestion;
        suggestionBox.classList.remove('d-none');
        setBlocked(true);
    }

    function runCheck() {
        const value = nameInput.value.trim();
        if (value === '' || value === lastCheckedValue) {
            if (value === '') clearSuggestion();
            return;
        }
        lastCheckedValue = value;
        warningEl.textContent = 'Checking spelling…';
        warningEl.classList.remove('d-none', 'text-danger');
        warningEl.classList.add('text-muted');
        fetch('../api/check-medicine-name.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: value }),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                warningEl.classList.add('d-none');
                // A newer keystroke may have started a fresher check
                // since this request went out — never apply a stale result.
                if (nameInput.value.trim() !== value) return;
                if (data.success && data.status === 'suggest' && data.suggestion) {
                    showSuggestion(data.suggestion);
                } else {
                    clearSuggestion();
                }
            })
            .catch(function () { warningEl.classList.add('d-none'); /* best-effort — never blocks the form on a network hiccup */ });
    }

    nameInput.addEventListener('input', function () {
        clearSuggestion();
        lastCheckedValue = null;
        if (checkTimer) clearTimeout(checkTimer);
        checkTimer = setTimeout(runCheck, 500);
    });
    nameInput.addEventListener('blur', function () {
        if (checkTimer) clearTimeout(checkTimer);
        runCheck();
    });
    useSuggestionBtn.addEventListener('click', function () {
        nameInput.value = suggestionText.textContent;
        lastCheckedValue = nameInput.value;
        clearSuggestion();
        nameInput.focus();
    });

    // Never carry a stale suggestion from a previous open of this shared
    // modal (Add or Edit, medicine or vaccine) into the next one.
    const modalEl = document.getElementById('addMedicineModal');
    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', function () {
            lastCheckedValue = null;
            clearSuggestion();
        });
    }

    // Registered here, inline — parsed (and so attached to #addMedicineForm)
    // before any external script's own DOMContentLoaded submit listener,
    // which is what lets stopImmediatePropagation() here actually stop
    // those listeners from building/sending a payload at all.
    form.addEventListener('submit', function (e) {
        if (hasUnresolvedSuggestion) {
            e.preventDefault();
            e.stopImmediatePropagation();
        }
    });
})();
</script>
