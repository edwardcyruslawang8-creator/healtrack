<!-- =============================================================
     VIEW DETAILS + ADJUST STOCK MODALS (shared, read-only + quick stock change)
     -------------------------------------------------------------
     Companions to add_medicine_modal.php's Add/Edit form, triggered by
     each row's three-dot menu ("View Details"/"Adjust Stock") on
     pharmacist/medicine_vaccine_inventory.php and
     subadmin/medicine_vaccine_inventory.php.
       - View Details: populated purely from the clicked row's
         data-med-* attributes (assets/js/medicine_vaccine_inventory_pharmacist.js)
         — no network request, since the page already rendered every
         field server-side.
       - Adjust Stock: a focused +/- amount, POSTs to
         api/adjust-medicine-stock.php (NOT the full Edit form/
         api/update-medicine.php) since changing just the stock count
         is the routine day-to-day action.
     ============================================================= -->
<div class="modal fade am-modal" id="viewMedicineModal" tabindex="-1" aria-labelledby="viewMedicineModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewMedicineModalLabel">Item Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <dl class="am-detail-list">
          <div class="am-detail-row"><dt>Name</dt><dd id="vmName"></dd></div>
          <div class="am-detail-row"><dt>Category</dt><dd id="vmCategory"></dd></div>
          <div class="am-detail-row"><dt>Unit</dt><dd id="vmUnit"></dd></div>
          <div class="am-detail-row"><dt>Current Stock</dt><dd id="vmStock"></dd></div>
          <div class="am-detail-row"><dt>Minimum Level</dt><dd id="vmMinLevel"></dd></div>
          <div class="am-detail-row"><dt>Reorder Level</dt><dd id="vmReorderLevel"></dd></div>
          <div class="am-detail-row"><dt>Expiry Date</dt><dd id="vmExpiry"></dd></div>
          <div class="am-detail-row"><dt>Status</dt><dd id="vmStatus"></dd></div>
          <div class="am-detail-row" id="vmDescRow"><dt>Description</dt><dd id="vmDesc"></dd></div>
        </dl>
      </div>
      <div class="modal-footer">
        <button type="button" class="am-btn-cancel" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade am-modal" id="adjustStockModal" tabindex="-1" aria-labelledby="adjustStockModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="adjustStockForm" novalidate>
        <input type="hidden" id="asFormId" value="">
        <div class="modal-header">
          <h5 class="modal-title" id="adjustStockModalLabel">Adjust Stock — <span id="asItemName"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="adjustStockError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>
          <p class="am-form-help mb-3">Current stock: <strong id="asCurrentStock">0</strong> <span id="asUnit"></span></p>
          <div class="mb-0">
            <label class="am-form-label" for="asDelta">Amount<span class="text-danger">*</span></label>
            <div class="input-group">
              <button type="button" class="btn btn-outline-secondary" id="asMinusBtn">&minus;</button>
              <input type="number" class="form-control am-form-control text-center" id="asDelta" step="1" placeholder="0" required>
              <button type="button" class="btn btn-outline-secondary" id="asPlusBtn">&plus;</button>
            </div>
            <div class="am-form-help">Positive to add stock received, negative to remove stock dispensed/damaged.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="am-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="am-btn-submit" id="asSubmitBtn"><i class="bi bi-sliders"></i> <span id="asSubmitLabel">Save</span></button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =============================================================
     DISPENSE WITHOUT PRESCRIPTION MODAL
     -------------------------------------------------------------
     This health center gives out medicines/vaccines directly when a
     patient simply asks the pharmacist for one — no doctor's
     prescription behind it (unlike pharmacist/prescription_queue.php's
     Dispense action, which fulfills a real doctor-ordered
     pharmacy_orders doc). Triggered by each row's three-dot menu
     ("Dispense") on pharmacist/medicine_vaccine_inventory.php
     (assets/js/medicine_vaccine_inventory_pharmacist.js). Patient is
     picked via quick-search.js's SELECT mode (real "patients" docs,
     api/quick-search-patients.php) — required, since every dispense
     still needs a real patient on record, just not a real prescription
     behind it. POSTs to api/dispense-without-prescription.php, which
     decrements real medicines/{id} stock the exact same validated way
     api/dispense-pharmacy-order.php's stock decrement does, and writes
     a real already-dispensed pharmacy_orders doc (source: "walk_in")
     so it shows up in Prescription Queue's Dispensed tab and this
     patient's own Medications tab too — same "one real action, every
     real read surface reflects it" principle as every other write in
     this app.
     ============================================================= -->
<div class="modal fade am-modal" id="dispenseNoRxModal" tabindex="-1" aria-labelledby="dispenseNoRxModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="dispenseNoRxForm" novalidate>
        <input type="hidden" id="dnrItemId" value="">
        <input type="hidden" id="dnrItemType" value="">
        <input type="hidden" id="dnrPatientId" value="">
        <div class="modal-header">
          <h5 class="modal-title" id="dispenseNoRxModalLabel">Dispense Without Prescription — <span id="dnrItemName"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="dispenseNoRxError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>
          <p class="am-form-help mb-3">For a patient who asks for this item directly. Current stock: <strong id="dnrCurrentStock">0</strong> <span id="dnrUnit"></span></p>

          <div class="mb-3">
            <label class="am-form-label" for="dnrPatientSearch">Patient<span class="text-danger">*</span></label>
            <div class="qs-field">
              <i class="bi bi-search"></i>
              <input type="text" class="form-control am-form-control" id="dnrPatientSearch" placeholder="Search patient by name..." autocomplete="off"
                     data-quick-search="<?= BASE_URL ?>/api/quick-search-patients.php" data-quick-search-select="dnrPatientId">
            </div>
          </div>

          <div class="mb-3">
            <label class="am-form-label" for="dnrQty">Quantity<span class="text-danger">*</span></label>
            <input type="number" class="form-control am-form-control" id="dnrQty" min="1" step="1" placeholder="e.g. 10" required>
          </div>

          <div class="mb-0">
            <label class="am-form-label" for="dnrNotes">Reason / Notes (optional)</label>
            <input type="text" class="form-control am-form-control" id="dnrNotes" placeholder="e.g. Requested directly by patient for headache">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="am-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="am-btn-submit" id="dnrSubmitBtn"><i class="bi bi-capsule"></i> <span id="dnrSubmitLabel">Dispense</span></button>
        </div>
      </form>
    </div>
  </div>
</div>
