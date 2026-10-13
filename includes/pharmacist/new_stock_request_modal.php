<!-- =============================================================
     NEW STOCK REQUEST MODAL
     -------------------------------------------------------------
     Triggered by pharmacist/stock_requests.php's "+ New Stock Request"
     button (#btnNewStockRequest). assets/js/new-stock-request.js
     POSTs this form to api/create-stock-request.php, which writes a
     real doc to the "stock_requests" Firestore collection
     subadmin/stock_requests.php already reads — a submitted request
     shows up there immediately, no changes needed on that side.

     Item rows are added/removed dynamically (start with one, "+ Add
     Another Item" appends more) — the real "items" field on a
     stock_requests/{id} doc is an array, and real requests already
     carry more than one item (see includes/firestore/stock_requests.php).
     Each row's item name is a free-text <input> (not a locked <select>)
     for flexibility — a pharmacist can request anything, not only
     items that already exist in inventory (e.g. a brand-new medicine
     never stocked before) — paired with a <datalist> of real
     medicine/vaccine names ($medicineNames/$vaccineNames,
     includes/pharmacist/stock_requests_pharmacist_firestore.php) for
     autocomplete convenience only; typing something not in the list is
     still perfectly valid. assets/js/new-stock-request.js points each
     row's input at #nsrMedicineList or #nsrVaccineList depending on
     the current Request Type.

     api/create-stock-request.php re-resolves each typed name against
     real inventory server-side anyway (hc_find_inventory_item_by_name()),
     so this stays free text rather than becoming a locked <select> —
     when a typed name exactly matches a real item, api/update-stock-
     request-status.php's Release Items step can actually add the
     requested quantity onto that item's real stock; a name that
     matches nothing (the brand-new-item case) just doesn't auto-update
     anything on release.

     Reuses the "am-" (Add Modal) styling from
     assets/css/add_medicine_modal.css rather than writing new CSS —
     same generic form-modal look every other "Add" modal in this app
     uses.
     ============================================================= -->
<datalist id="nsrMedicineList">
    <?php foreach ($medicineNames as $name): ?>
    <option value="<?= htmlspecialchars($name) ?>">
    <?php endforeach; ?>
</datalist>
<datalist id="nsrVaccineList">
    <?php foreach ($vaccineNames as $name): ?>
    <option value="<?= htmlspecialchars($name) ?>">
    <?php endforeach; ?>
</datalist>

<div class="modal fade am-modal" id="newStockRequestModal" tabindex="-1" aria-labelledby="newStockRequestModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="newStockRequestForm" novalidate>
        <input type="hidden" id="nsrFormId" value="">
        <div class="modal-header">
          <h5 class="modal-title" id="newStockRequestModalLabel">New Stock Request</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="newStockRequestError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="am-form-label" for="nsrType">Request Type<span class="text-danger">*</span></label>
              <select class="form-select am-form-control" id="nsrType" required>
                <option value="medicine" selected>Medicine</option>
                <option value="vaccine">Vaccine</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="am-form-label" for="nsrDateNeeded">Date Needed<span class="text-danger">*</span></label>
              <input type="date" class="form-control am-form-control" id="nsrDateNeeded" min="<?= date('Y-m-d') ?>" required>
            </div>
          </div>

          <label class="am-form-label">Items<span class="text-danger">*</span></label>
          <div id="nsrItemRows"></div>
          <button type="button" class="am-btn-cancel mt-1" id="nsrAddItemBtn"><i class="bi bi-plus-lg"></i> Add Another Item</button>
        </div>
        <div class="modal-footer">
          <button type="button" class="am-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="am-btn-submit" id="nsrSubmitBtn"><i class="bi bi-box-seam-fill"></i> <span id="nsrSubmitLabel">Submit Request</span></button>
        </div>
      </form>
    </div>
  </div>
</div>
