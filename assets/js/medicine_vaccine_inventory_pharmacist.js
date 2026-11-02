/* =============================================================
   MEDICINE & VACCINE INVENTORY JS — Add Medicine / Add Vaccine
   (pharmacist/medicine_vaccine_inventory.php ONLY)
   ---------------------------------------------------------------
   The one real capability left on this page that still needs its own
   JS: "+ Add Medicine"/"+ Add Vaccine" (#btnAddMedicine/#btnAddVaccine)
   open includes/pharmacist/add_medicine_modal.php's shared form empty
   and submit it to api/create-medicine.php (pharmacist-allowed —
   registering a brand-new catalog entry is a different operation from
   editing an existing one's data, which stays subadmin-only). Reloads
   the page on success so the table/summary cards re-render from fresh
   Firestore data.

   Everything else this file used to drive — Manage Medicine's old
   modal, View Details, Adjust Stock, Dispense, Archive, Delete, View
   Stock History — has moved to a real per-item detail page
   (pharmacist/manage_medicine.php, subadmin/manage_medicine.php,
   admin/manage_medicine.php — assets/js/manage_medicine.js), and
   subadmin's own list page (includes/subadmin/_mvi_medicines_table.php /
   _mvi_vaccines_table.php) has no row-level trigger left at all now
   (not even Archive, removed per request) — its own "Manage Medicine"
   is a plain link needing no JS of its own, so this file is no longer
   loaded there either.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('addMedicineModal');
    const form = document.getElementById('addMedicineForm');

    if (!modalEl || !form || typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalEl);
    const errorBox = document.getElementById('addMedicineError');
    const submitBtn = document.getElementById('amSubmitBtn');
    // NOT cached as a const here — openAddModal() below restores
    // submitBtn's innerHTML (undoing a previous "Saving…" spinner
    // state), which replaces the #amSubmitLabel span with a brand new
    // DOM node each time; a reference captured once up here would go
    // stale the first time that happens, silently stop reflecting
    // what's on screen, and leave the button reading "Add Medicine"
    // even while adding a vaccine (confirmed from a live screenshot).
    const submitBtnDefaultHtml = submitBtn.innerHTML;

    const typeInput = document.getElementById('amFormType');
    const modalTitle = document.getElementById('addMedicineModalLabel');
    const nameLabel = document.getElementById('amNameLabel');
    const nameInput = document.getElementById('amName');
    const expiryInput = document.getElementById('amExpiryDate');
    const stockInput = document.getElementById('amCurrentStock');
    const stockExpiryRow = document.getElementById('amStockExpiryRow');
    const stockExpiryReadonlyNote = document.getElementById('amStockExpiryReadonlyNote');
    const thresholdsRow = document.getElementById('amThresholdsRow');
    const minLevelInput = document.getElementById('amMinLevel');
    const reorderLevelInput = document.getElementById('amReorderLevel');
    const maxLevelRow = document.getElementById('amMaxLevelRow');
    const dosageRow = document.getElementById('amDosageRow');
    const todayIso = expiryInput.min; // set server-side to today (see add_medicine_modal.php)

    function openAddModal(type) {
        form.reset();
        form.classList.remove('was-validated');
        errorBox.classList.add('d-none');
        typeInput.value = type;
        expiryInput.min = todayIso;
        // Reset the submit button back to its normal icon+label state
        // (undoing a previous "Saving…" spinner) BEFORE the type-
        // specific label below, not after — innerHTML replaces
        // #amSubmitLabel with a fresh node, so doing this first and
        // re-querying it fresh (rather than reusing a reference from
        // before this reset) is what keeps the label actually visible.
        submitBtn.disabled = false;
        submitBtn.innerHTML = submitBtnDefaultHtml;
        const submitLabel = document.getElementById('amSubmitLabel');
        // Adding a brand-new item now only collects its catalog info
        // (Name/Category/Unit/Description) — Expiry Date/Current
        // Stock/Minimum Level/Reorder Level/Maximum Level/Dosage
        // Information are all hidden here (per explicit request — see
        // add_medicine_modal.php's own header comment). The item is
        // created with 0 real stock; its real first batch (with a real
        // expiry date) and Dosage Information are added afterward via
        // Restock on pharmacist/manage_medicine.php, which also auto-
        // computes Minimum/Reorder Level from whatever quantity
        // results.
        stockExpiryRow.classList.add('d-none');
        expiryInput.required = false;
        stockInput.required = false;
        stockExpiryReadonlyNote.classList.add('d-none');
        thresholdsRow.classList.add('d-none');
        minLevelInput.required = false;
        reorderLevelInput.required = false;
        maxLevelRow.classList.add('d-none');
        // Dosage Information is collected here too now, alongside the
        // optional Initial Stock — same fields a Restock asks for.
        dosageRow.classList.remove('d-none');

        if (type === 'vaccine') {
            modalTitle.textContent = 'Add Vaccine';
            nameLabel.textContent = 'Vaccine Name';
            nameInput.placeholder = 'e.g. Influenza Vaccine';
            submitLabel.textContent = 'Add Vaccine';
            document.getElementById('amCategory').value = 'Vaccine';
            document.getElementById('amUnit').value = 'Dose';
        } else {
            // #amUnit is a hidden input (add_medicine_modal.php's unit
            // dropdown) — form.reset() doesn't clear those.
            document.getElementById('amUnit').value = '';
            modalTitle.textContent = 'Add Medicine';
            nameLabel.textContent = 'Medicine Name';
            nameInput.placeholder = 'e.g. Amoxicillin 500 mg Capsule';
            submitLabel.textContent = 'Add Medicine';
        }

        modal.show();
    }

    const addMedicineBtn = document.getElementById('btnAddMedicine');
    if (addMedicineBtn) addMedicineBtn.addEventListener('click', function () { openAddModal('medicine'); });

    const addVaccineBtn = document.getElementById('btnAddVaccine');
    if (addVaccineBtn) addVaccineBtn.addEventListener('click', function () { openAddModal('vaccine'); });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        const payload = {
            type: typeInput.value,
            name: nameInput.value.trim(),
            category: document.getElementById('amCategory').value.trim(),
            unit: document.getElementById('amUnit').value.trim(),
            piecesPerBox: document.getElementById('amPiecesPerBox').value,
            desc: document.getElementById('amDesc').value.trim(),
            dosage: document.getElementById('amDosage').value.trim(),
        };

        // Required first stock — received like a "new batch" Restock
        // (add_medicine_modal.php's #amInitialStockSection, whose
        // Quantity/Batch Number/Expiry all carry the HTML required
        // attribute, already enforced by the form.checkValidity() check
        // above); the server converts boxes with this form's own
        // per-box count. The two checks below are defense-in-depth —
        // step/min on the Quantity input already guarantee a whole
        // number >= 1 by this point, but piecesPerBox isn't tied to
        // native validation the same way.
        const initialQty = document.getElementById('amIsQty').value.trim();
        const qtyUnitSelect = document.getElementById('amIsQtyUnit');
        const inBoxes = !qtyUnitSelect.classList.contains('d-none') && qtyUnitSelect.value === 'box';
        if (!(parseInt(initialQty, 10) >= 1)) {
            errorBox.textContent = 'Please enter a valid initial quantity.';
            errorBox.classList.remove('d-none');
            return;
        }
        if (inBoxes && !(parseInt(payload.piecesPerBox, 10) >= 1)) {
            errorBox.textContent = 'Enter how many ' + payload.unit.toLowerCase() + 's come in one box, or receive the initial stock in ' + payload.unit.toLowerCase() + 's instead.';
            errorBox.classList.remove('d-none');
            return;
        }
        payload.initialStock = {
            batchNumber: document.getElementById('amIsBatchNumber').value.trim(),
            expiryDate: document.getElementById('amIsExpiry').value,
            quantity: parseInt(initialQty, 10),
            quantityUnit: inBoxes ? 'box' : 'unit',
        };

        // Captured HERE, not reused from submitBtnDefaultHtml — that's
        // the button's very first page-load markup ("Add Medicine"),
        // not necessarily this attempt's own label ("Add Vaccine"). A
        // failed submission needs to restore to what was actually
        // showing before "Saving…", not revert to a different mode's
        // text — the exact same bug already caught and fixed on
        // subadmin's Edit flow (assets/js/manage_medicine.js).
        const idleHtml = submitBtn.innerHTML;
        errorBox.classList.add('d-none');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving…';

        fetch('../api/create-medicine.php', {
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
                submitBtn.innerHTML = idleHtml;
            });
    });

    // Search validation (clicking "Search" with an empty box) lives in
    // assets/js/search-form-guard.js, shared across every page's
    // search toolbar instead of duplicated per page.
});
