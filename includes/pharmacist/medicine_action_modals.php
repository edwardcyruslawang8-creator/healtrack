<!-- =============================================================
     MANAGE MEDICINE / MANAGE VACCINE MODAL (pharmacist only)
     -------------------------------------------------------------
     Triggered by each row's own "Manage Medicine"/"Manage Vaccine"
     button on pharmacist/medicine_vaccine_inventory.php — a
     pharmacist's ONE real entry point for an existing item, deliberately
     more limited than subadmin's pencil+dropdown below: every field is
     shown (same data-med-* attributes the old View Details modal read,
     no network request) but NONE are editable here — a pharmacist
     can't change name/category/unit/expiry date/current stock/minimum
     level/reorder level (api/update-medicine.php and
     api/adjust-medicine-stock.php both refuse the pharmacist role
     server-side now, not just a hidden button) and can't permanently
     delete an item (api/delete-medicine.php, also subadmin-only).
     What's left: Dispense (No Prescription) as the one real mutating
     action from here (hands off to #dispenseNoRxModal below, same
     endpoint subadmin's dropdown uses), and View Stock History/Archive
     as two icon buttons in this modal's own header, upper-right, next
     to Close — secondary/occasional actions, not what a pharmacist
     usually opens this for. Each hands off to its own
     modal/confirmation with the row's data carried along (inline
     <script> in pharmacist/medicine_vaccine_inventory.php), same
     hand-off pattern subadmin/user_management.php's "Manage User"
     modal already established. Not used on
     subadmin/medicine_vaccine_inventory.php, which keeps the fuller
     pencil+dropdown layout below.
     ============================================================= -->
<div class="modal fade am-modal" id="mviManageModal" tabindex="-1" aria-labelledby="mviManageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mviManageModalLabel">Manage Medicine</h5>
        <div class="mvi-manage-header-actions">
          <button type="button" class="mvi-manage-header-btn" id="mmHistoryTrigger"><i class="bi bi-clock-history"></i> History</button>
          <button type="button" class="mvi-manage-header-btn" id="mmArchiveTrigger"><i class="bi bi-archive"></i> Archive</button>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body">
        <div class="mvi-manage-header">
          <span class="mvi-manage-thumb" id="mmThumb"><i class="bi bi-capsule" id="mmThumbIcon"></i></span>
          <div class="mvi-manage-identity">
            <h5 class="mvi-manage-name" id="mmName"></h5>
            <div class="mvi-manage-badges">
              <span class="mvi-badge" id="mmStatusBadge"></span>
              <span class="mvi-badge mvi-expiry-badge d-none" id="mmExpiryBadge"></span>
            </div>
          </div>
        </div>

        <dl class="am-detail-list">
          <div class="am-detail-row"><dt>Category</dt><dd id="mmCategory"></dd></div>
          <div class="am-detail-row"><dt>Unit</dt><dd id="mmUnit"></dd></div>
          <div class="am-detail-row"><dt>Current Stock</dt><dd id="mmStock"></dd></div>
          <div class="am-detail-row"><dt>Minimum Level</dt><dd id="mmMinLevel"></dd></div>
          <div class="am-detail-row"><dt>Reorder Level</dt><dd id="mmReorderLevel"></dd></div>
          <div class="am-detail-row"><dt>Expiry Date</dt><dd id="mmExpiry"></dd></div>
          <div class="am-detail-row" id="mmDescRow"><dt>Description</dt><dd id="mmDesc"></dd></div>
        </dl>

        <!-- Only shown once the row's own data-med-expiry-status is
             "expired" — an expired item can never be dispensed
             (api/dispense-without-prescription.php re-checks this
             server-side too), so the Dispense button below is disabled
             rather than left clickable just to fail after. -->
        <p class="mvi-manage-expired-note d-none" id="mmExpiredNote"><i class="bi bi-exclamation-circle"></i> This item has expired and can no longer be dispensed.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="am-btn-cancel" data-bs-dismiss="modal">Close</button>
        <button type="button" class="mvi-manage-dispense-btn" id="mmDispenseTrigger"><i class="bi bi-capsule"></i> Dispense (No Prescription)</button>
      </div>
    </div>
  </div>
</div>

<!-- =============================================================
     VIEW DETAILS + ADJUST STOCK MODALS (shared, read-only + quick stock change)
     -------------------------------------------------------------
     Companions to add_medicine_modal.php's Add/Edit form, triggered by
     each row's three-dot menu ("View Details"/"Adjust Stock") on
     subadmin/medicine_vaccine_inventory.php (pharmacist's own
     equivalent read-only view lives inside #mviManageModal above now).
       - View Details: populated purely from the clicked row's
         data-med-* attributes (assets/js/medicine_vaccine_inventory_pharmacist.js)
         — no network request, since the page already rendered every
         field server-side.
       - Adjust Stock: a focused +/- amount, POSTs to
         api/adjust-medicine-stock.php (NOT the full Edit form/
         api/update-medicine.php) since changing just the stock count
         is the routine day-to-day action. Subadmin-only now — see
         that endpoint's own header comment.
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
     STOCK HISTORY MODAL (read-only)
     -------------------------------------------------------------
     Triggered by each row's three-dot menu ("View Stock History").
     Real data — api/get-medicine-stock-history.php, which reuses the
     "audit_logs" collection every mutating api/*-medicine.php endpoint
     already writes to (see that API's own header comment for how it's
     matched to one item). Fetched on click, not server-rendered with
     the rest of the page, since it's a per-row detail nobody needs
     until they actually open it.
     ============================================================= -->
<div class="modal fade am-modal" id="stockHistoryModal" tabindex="-1" aria-labelledby="stockHistoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="stockHistoryModalLabel">Stock History — <span id="shItemName"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="shLoading" class="am-form-help">Loading…</div>
        <div id="shEmpty" class="am-form-help d-none">No recorded history for this item yet.</div>
        <ul class="am-history-list d-none" id="shList"></ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="am-btn-cancel" data-bs-dismiss="modal">Close</button>
      </div>
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
