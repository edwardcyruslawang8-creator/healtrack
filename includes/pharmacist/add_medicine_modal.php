<!-- =============================================================
     ADD/EDIT MEDICINE / ADD/EDIT VACCINE MODAL (shared form)
     -------------------------------------------------------------
     Triggered by pharmacist/medicine_vaccine_inventory.php's
     "+ Add Medicine" (#btnAddMedicine) and "+ Add Vaccine"
     (#btnAddVaccine) buttons, AND each row's Edit (pencil) button —
     one shared form for all four, since medicines and vaccines are
     the same real "medicines" Firestore collection, split only by a
     "type" field (see includes/firestore/medicines.php's header
     comment), and editing an existing item needs the exact same
     fields as creating one. assets/js/medicine_vaccine_inventory_pharmacist.js
     sets the hidden #amFormType/#amFormId fields and adjusts
     labels/title based on which button was clicked, then POSTs to
     api/create-medicine.php (Add, #amFormId empty) or
     api/update-medicine.php (Edit, #amFormId set to the item's real
     doc id). "Status" (In Stock/Low Stock/Out of Stock) isn't a form
     field either way — it's computed server-side from Current Stock
     vs Minimum Level.

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
            <input type="text" class="form-control am-form-control" id="amName" placeholder="e.g. Amoxicillin 500 mg Capsule" required>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="am-form-label" for="amCategory">Category<span class="text-danger">*</span></label>
              <input type="text" class="form-control am-form-control" id="amCategory" placeholder="e.g. Antibiotic" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="am-form-label" for="amUnit">Unit<span class="text-danger">*</span></label>
              <input type="text" class="form-control am-form-control" id="amUnit" placeholder="e.g. Tablet, Capsule, Dose, Bottle" required>
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
              <input type="date" class="form-control am-form-control" id="amExpiryDate" min="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="am-form-label" for="amCurrentStock">Current Stock<span class="text-danger">*</span></label>
              <input type="number" class="form-control am-form-control" id="amCurrentStock" min="0" step="1" placeholder="0" required>
            </div>
          </div>
          <!-- Edit-mode-only read-only summary, replacing the row above. -->
          <div class="am-form-help d-none" id="amStockExpiryReadonlyNote"></div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="am-form-label" for="amMinLevel">Minimum Level<span class="text-danger">*</span></label>
              <input type="number" class="form-control am-form-control" id="amMinLevel" min="0" step="1" placeholder="0" required>
              <div class="am-form-help">Stock at or below this is flagged "Low Stock".</div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="am-form-label" for="amReorderLevel">Reorder Level<span class="text-danger">*</span></label>
              <input type="number" class="form-control am-form-control" id="amReorderLevel" min="0" step="1" placeholder="0" required>
            </div>
          </div>

          <div class="row">
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

          <div class="mb-0">
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
