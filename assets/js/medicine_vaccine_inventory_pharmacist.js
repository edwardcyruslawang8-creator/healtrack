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
    const submitLabel = document.getElementById('amSubmitLabel');
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
        dosageRow.classList.add('d-none');

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
            desc: document.getElementById('amDesc').value.trim(),
        };

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
                submitBtn.innerHTML = submitBtnDefaultHtml;
            });
    });

    // Search validation (clicking "Search" with an empty box) lives in
    // assets/js/search-form-guard.js, shared across every page's
    // search toolbar instead of duplicated per page.
});
