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
