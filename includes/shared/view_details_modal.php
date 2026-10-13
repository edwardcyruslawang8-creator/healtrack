<!-- =============================================================
     GENERIC "VIEW DETAILS" MODAL (shared)
     -------------------------------------------------------------
     One reusable read-only detail modal for any table row across the
     Patient Profile family (subadmin/medical_records.php and friends),
     instead of a bespoke modal per page/record-type. Pure client-side
     — assets/js/view-details-action.js reads whichever row's
     ".vdm-trigger" was clicked, via two data attributes set server-side
     on that <tr>:
       - data-vdm-title  — the modal's heading (e.g. the record's title)
       - data-vdm-fields — a JSON object of {"Label": "value", ...} in
         display order; a null/empty value is skipped (see that JS
         file), so a page doesn't need to pre-filter its own optional
         fields before encoding them.
     No network request — every field needed is already rendered
     server-side elsewhere on the same row, just not necessarily visible
     as its own table column.
     ============================================================= -->
<div class="modal fade vdm-modal" id="viewDetailsModal" tabindex="-1" aria-labelledby="viewDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewDetailsModalLabel">Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <dl class="vdm-detail-list" id="vdmFields"></dl>
      </div>
      <div class="modal-footer">
        <button type="button" class="vdm-btn-close" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
