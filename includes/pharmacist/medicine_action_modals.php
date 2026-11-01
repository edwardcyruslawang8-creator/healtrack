<!-- =============================================================
     MANAGE MEDICINE / MANAGE VACCINE MODAL (pharmacist only)
     -------------------------------------------------------------
     Triggered by each row's own "Manage Medicine"/"Manage Vaccine"
     button on pharmacist/medicine_vaccine_inventory.php — a
     pharmacist's detailed inventory-management view for an existing
     item: Section A (medicine information, from the row's own
     data-med-* attributes — no network request) and Section B (a live
     Batch Management table, fetched on open from
     api/get-medicine-stock-history.php, the same batches+per-batch
     status includes/firestore/medicine_batches.php's
     hc_get_batches_for_medicine() now computes — see that function's
     own header comment). A pharmacist still can't rename/recategorize
     this item or change its configured thresholds (api/update-medicine.php
     is subadmin-only) and can't permanently delete it
     (api/delete-medicine.php, also subadmin-only) — what IS real here:
     Restock (#restockModal below, api/restock-medicine.php — adds real
     stock a pharmacist actually received, either into an existing
     batch or as a brand new one) and Dispense (No Prescription) as the
     footer's one real mutating action, plus View Stock History as an
     icon button in the header next to Close. Archive moved out of this
     modal onto the row itself (a separate button next to "Manage
     Medicine" on the main table, per the main table's own simplified-
     overview redesign) rather than staying duplicated here too.
     ============================================================= -->
<div class="modal fade am-modal mvi-manage-modal" id="mviManageModal" tabindex="-1" aria-labelledby="mviManageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mviManageModalLabel">Manage Medicine</h5>
        <div class="mvi-manage-header-actions">
          <button type="button" class="mvi-manage-header-btn" id="mmHistoryTrigger"><i class="bi bi-clock-history"></i> History</button>
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

        <!-- ===== Section A: Medicine Information ===== -->
        <h6 class="mvi-manage-section-title">Medicine Information</h6>
        <dl class="am-detail-list mvi-manage-info-grid">
          <div class="am-detail-row"><dt>Medicine Name</dt><dd id="mmNameDetail"></dd></div>
          <div class="am-detail-row"><dt>Category</dt><dd id="mmCategory"></dd></div>
          <div class="am-detail-row"><dt>Unit</dt><dd id="mmUnit"></dd></div>
          <div class="am-detail-row"><dt>Total Current Stock</dt><dd id="mmStock"></dd></div>
          <div class="am-detail-row" id="mmDescRow"><dt>Description</dt><dd id="mmDesc"></dd></div>
          <div class="am-detail-row"><dt>Dosage Information</dt><dd id="mmDosage"></dd></div>
        </dl>

        <!-- Only shown once the row's own data-med-expiry-status is
             "expired" — an expired item can never be dispensed
             (api/dispense-without-prescription.php re-checks this
             server-side too), so the Dispense button below is disabled
             rather than left clickable just to fail after. -->
        <p class="mvi-manage-expired-note d-none" id="mmExpiredNote"><i class="bi bi-exclamation-circle"></i> This item has expired and can no longer be dispensed.</p>

        <!-- ===== Section B: Batch Management (FEFO — soonest-expiring first) ===== -->
        <div class="mvi-manage-section-header">
          <h6 class="mvi-manage-section-title mb-0">Batch Management</h6>
          <button type="button" class="mvi-manage-header-btn" id="mmRestockTrigger"><i class="bi bi-plus-lg"></i> Restock</button>
        </div>
        <div id="mmBatchesEmpty" class="am-form-help d-none">No stock currently on hand.</div>
        <div id="mmBatchesLoading" class="am-form-help">Loading batches…</div>
        <div class="adm-table-wrap mvi-batch-table-wrap d-none" id="mmBatchesWrap">
          <table class="table table-sm am-detail-items-table mvi-batch-table">
            <thead>
              <tr>
                <th>Batch Number</th>
                <th>Expiry Date</th>
                <th>Status</th>
                <th>Current Stock</th>
                <th>Maximum Stock Level</th>
                <th>Reorder Level</th>
              </tr>
            </thead>
            <tbody id="mmBatchesBody"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="am-btn-cancel" data-bs-dismiss="modal">Close</button>
        <button type="button" class="mvi-manage-dispense-btn" id="mmDispenseTrigger"><i class="bi bi-capsule"></i> Dispense (No Prescription)</button>
      </div>
    </div>
  </div>
</div>

<!-- =============================================================
     RESTOCK MODAL (pharmacist only)
     -------------------------------------------------------------
     Opened from #mviManageModal's own "Restock" button (hands off the
     same way #mviManageModal's History/Dispense triggers already do —
     hide this modal first, then show that one, avoiding a double
     backdrop). Two real modes mirroring api/restock-medicine.php's own
     "mode" field:
       - "An existing batch": picks one of this item's own CURRENTLY-
         HELD batches (populated from the same batch list
         #mviManageModal's own table just rendered — no second fetch)
         and adds quantity to it (hc_add_to_existing_batch()).
       - "A new batch": a real new batch with its own Batch Number
         (optional — api/restock-medicine.php falls back to a short
         label derived from the new batch's own real doc id if left
         blank, same as every pre-existing batch that predates this
         field), Quantity, and Expiry Date
         (hc_create_medicine_batch()).
     On success, re-fetches #mviManageModal's own batch list (not a
     full page reload) so the just-restocked batch's new quantity shows
     immediately while that modal stays open.
     ============================================================= -->
<div class="modal fade am-modal" id="restockModal" tabindex="-1" aria-labelledby="restockModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="restockForm" novalidate>
        <input type="hidden" id="rsItemId" value="">
        <div class="modal-header">
          <h5 class="modal-title" id="restockModalLabel">Restock — <span id="rsItemName"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="restockError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>

          <div class="mb-3">
            <label class="am-form-label d-block mb-2">This stock is...</label>
            <div class="btn-group w-100" role="group" aria-label="Restock mode">
              <input type="radio" class="btn-check" name="rsMode" id="rsModeExisting" value="existing" autocomplete="off" checked>
              <label class="btn mvi-mode-btn" for="rsModeExisting">An existing batch</label>
              <input type="radio" class="btn-check" name="rsMode" id="rsModeNew" value="new" autocomplete="off">
              <label class="btn mvi-mode-btn" for="rsModeNew">A new batch</label>
            </div>
          </div>

          <div class="mb-3" id="rsExistingWrap">
            <label class="am-form-label" for="rsBatchSelect">Batch<span class="text-danger">*</span></label>
            <select class="form-select am-form-control" id="rsBatchSelect"></select>
          </div>

          <div class="mb-3 d-none" id="rsNewWrap">
            <label class="am-form-label" for="rsBatchNumber">Batch Number <span class="text-muted fw-normal">(Optional)</span></label>
            <input type="text" class="form-control am-form-control" id="rsBatchNumber" placeholder="e.g. BATCH-2026-045">
            <div class="row mt-3">
              <div class="col-md-6">
                <label class="am-form-label" for="rsNewExpiry">Expiry Date<span class="text-danger">*</span></label>
                <input type="date" class="form-control am-form-control" id="rsNewExpiry" min="<?= date('Y-m-d') ?>">
              </div>
            </div>
          </div>

          <div class="mb-0">
            <label class="am-form-label" for="rsQty">Quantity<span class="text-danger">*</span></label>
            <input type="number" class="form-control am-form-control" id="rsQty" min="1" step="1" placeholder="e.g. 50" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="am-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="am-btn-submit" id="rsSubmitBtn"><i class="bi bi-box-seam"></i> <span id="rsSubmitLabel">Restock</span></button>
        </div>
      </form>
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
          <div class="am-detail-row" id="vmMaxLevelRow"><dt>Maximum Level</dt><dd id="vmMaxLevel"></dd></div>
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
          <!-- Only shown/required for a POSITIVE amount — stock being
               added becomes its own real batch
               (includes/firestore/medicine_batches.php), with its own
               real expiry date, rather than silently inheriting
               whatever expiry this item already happened to show
               (which may well belong to a different, older batch). A
               negative amount just draws down existing batches
               (soonest-expiring first), so it needs no expiry input at
               all. -->
          <div class="mb-0 mt-3 d-none" id="asExpiryWrap">
            <label class="am-form-label" for="asExpiryDate">Expiry Date for this stock<span class="text-danger">*</span></label>
            <input type="date" class="form-control am-form-control" id="asExpiryDate" min="<?= date('Y-m-d') ?>">
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
     Shows two real things, fetched together on click (not
     server-rendered with the rest of the page, since it's a per-row
     detail nobody needs until they actually open it):
       - Current Batches — this item's real, currently-held batches
         (includes/firestore/medicine_batches.php's
         hc_get_batches_for_medicine(), soonest-expiry first), each
         with its own real quantity/expiry/source, answering "what's
         actually on the shelf and when does each part expire" rather
         than one shared number that can't represent more than one
         expiry date.
       - Activity Log — the existing audit-log-based history
         (api/get-medicine-stock-history.php, which reuses the
         "audit_logs" collection every mutating api/*-medicine.php
         endpoint already writes to — see that API's own header
         comment for how it's matched to one item).
     ============================================================= -->
<div class="modal fade am-modal" id="stockHistoryModal" tabindex="-1" aria-labelledby="stockHistoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="stockHistoryModalLabel">Stock History — <span id="shItemName"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <h6 class="am-form-label mb-2">Current Batches</h6>
        <div id="shBatchesEmpty" class="am-form-help d-none">No stock currently on hand.</div>
        <table class="table table-sm am-detail-items-table d-none" id="shBatchesTable">
          <thead><tr><th>Quantity</th><th>Expiry Date</th><th>Source</th></tr></thead>
          <tbody id="shBatchesBody"></tbody>
        </table>

        <h6 class="am-form-label mb-2 mt-3">Activity Log</h6>
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
