<!-- =============================================================
     VIEW STOCK REQUEST MODAL (shared, read-only) + PRINT AREA
     -------------------------------------------------------------
     Triggered by each row's "View Items" link and "View Request"
     three-dot menu item on subadmin/stock_requests.php and
     pharmacist/stock_requests.php (reused as-is — this file originated
     on the subadmin side, same cross-role reuse pattern as
     includes/pharmacist/add_medicine_modal.php). Populated purely from
     the clicked row's data-request-* attributes
     (assets/js/stock_requests.js) — no network request, since the page
     already rendered every field server-side. data-request-items is a
     JSON-encoded array of {name, qty} the real "items" field on the
     stock_requests/{id} doc carries.

     #srqPrintArea below is the actual real "Print Request" feature —
     invisible on screen (assets/css/add_medicine_modal.css's
     @media print rule hides everything else and shows only this),
     populated by the same JS function the modal uses, then
     window.print() is called. The modal's own "Print" footer button
     reuses whatever the modal is already showing rather than
     re-populating anything.
     ============================================================= -->
<div class="modal fade am-modal" id="viewRequestModal" tabindex="-1" aria-labelledby="viewRequestModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewRequestModalLabel">Stock Request Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <dl class="am-detail-list">
          <div class="am-detail-row"><dt>Requested By</dt><dd id="vrPharmacist"></dd></div>
          <div class="am-detail-row"><dt>Request Date</dt><dd id="vrDate"></dd></div>
          <div class="am-detail-row"><dt>Request Type</dt><dd id="vrType"></dd></div>
          <div class="am-detail-row"><dt>Status</dt><dd id="vrStatus"></dd></div>
          <div class="am-detail-row"><dt>Date Needed</dt><dd id="vrDateNeeded"></dd></div>
          <div class="am-detail-row"><dt>Facility</dt><dd id="vrFacility"></dd></div>
        </dl>
        <h6 class="am-form-label mt-3 mb-2">Items Requested</h6>
        <table class="table table-sm am-detail-items-table">
          <thead><tr><th>Item</th><th class="text-end">Qty</th></tr></thead>
          <tbody id="vrItemsBody"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="am-btn-cancel" data-bs-dismiss="modal">Close</button>
        <button type="button" class="am-btn-submit" id="vrPrintBtn"><i class="bi bi-printer"></i> Print</button>
      </div>
    </div>
  </div>
</div>

<!-- =============================================================
     RELEASE ITEMS MODAL
     -------------------------------------------------------------
     Triggered by a row's "Release Items" three-dot menu item in place
     of the old plain confirm() — releasing now creates a real NEW
     batch per item (includes/firestore/medicine_batches.php) instead
     of blindly adding onto a single shared currentStock number, so a
     delivery with a different real expiry date than whatever's
     already on the shelf for the same medicine is tracked honestly
     rather than silently overwriting/ignoring it. Built client-side
     from the SAME row data-request-items JSON the View Request modal
     already reads (assets/js/stock_requests.js's readRequestFields())
     — one expiry date input per item that has a real itemId (a
     matched inventory record); an item with none (see
     api/update-stock-request-status.php's own header comment on why
     that's skipped rather than failing the release) shows a plain
     note instead of an input, since there's no real medicines/{id}
     doc to create a batch against. POSTs {docId, status: 'released',
     batches: [{itemId, expiryDate}, ...]} to
     api/update-stock-request-status.php.
     ============================================================= -->
<div class="modal fade am-modal" id="releaseRequestModal" tabindex="-1" aria-labelledby="releaseRequestModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="releaseRequestForm" novalidate>
        <input type="hidden" id="rrDocId" value="">
        <div class="modal-header">
          <h5 class="modal-title" id="releaseRequestModalLabel">Release Items</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="releaseRequestError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>
          <p class="am-form-help mb-3">Enter the real expiry date printed on THIS delivery for each item — it becomes its own tracked batch, kept separate from any stock already on hand with a different expiry.</p>
          <table class="table table-sm am-detail-items-table">
            <thead><tr><th>Item</th><th class="text-end">Qty</th><th>Expiry Date</th></tr></thead>
            <tbody id="rrItemsBody"></tbody>
          </table>
        </div>
        <div class="modal-footer">
          <button type="button" class="am-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="am-btn-submit" id="rrSubmitBtn"><i class="bi bi-box-arrow-up"></i> <span id="rrSubmitLabel">Release Items</span></button>
        </div>
      </form>
    </div>
  </div>
</div>

<div id="srqPrintArea">
  <h2>Stock Request</h2>
  <table class="srq-print-table">
    <tr><th>Requested By</th><td id="prPharmacist"></td></tr>
    <tr><th>Request Date</th><td id="prDate"></td></tr>
    <tr><th>Request Type</th><td id="prType"></td></tr>
    <tr><th>Status</th><td id="prStatus"></td></tr>
    <tr><th>Date Needed</th><td id="prDateNeeded"></td></tr>
    <tr><th>Facility</th><td id="prFacility"></td></tr>
  </table>
  <h3>Items Requested</h3>
  <table class="srq-print-table srq-print-items">
    <thead><tr><th>Item</th><th>Qty</th></tr></thead>
    <tbody id="prItemsBody"></tbody>
  </table>
</div>
