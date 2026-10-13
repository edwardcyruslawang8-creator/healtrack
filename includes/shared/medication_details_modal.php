<!-- =============================================================
     VIEW MEDICATION DETAILS MODAL (shared, read-only)
     -------------------------------------------------------------
     Triggered by each row's "View Details" button on
     doctor/medications.php, medical-staff/medications.php, and
     subadmin/medications.php (assets/js/medication-details.js).
     Populated purely from the clicked row's data-med-* attributes —
     no network request, since the page already rendered every field
     server-side (same pattern includes/subadmin/stock_request_view_modal.php
     already uses for its own View Items modal). Read-only by design —
     there's no Edit action on this list; every field already shown
     here is also visible directly in the table row, this just gives a
     clean single-column view of the same record (useful on narrower
     screens where the wide table needs horizontal scrolling).
     ============================================================= -->
<div class="modal fade am-modal" id="medDetailsModal" tabindex="-1" aria-labelledby="medDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="medDetailsModalLabel">Medication Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <dl class="am-detail-list">
          <div class="am-detail-row"><dt>Medication</dt><dd id="mdvName"></dd></div>
          <div class="am-detail-row"><dt>Category</dt><dd id="mdvCategory"></dd></div>
          <div class="am-detail-row"><dt>Dosage</dt><dd id="mdvDosage"></dd></div>
          <div class="am-detail-row"><dt>Frequency</dt><dd id="mdvFrequency"></dd></div>
          <div class="am-detail-row"><dt>Route</dt><dd id="mdvRoute"></dd></div>
          <div class="am-detail-row"><dt>Indication / Reason</dt><dd id="mdvIndication"></dd></div>
          <div class="am-detail-row"><dt>Start Date</dt><dd id="mdvStartDate"></dd></div>
          <div class="am-detail-row"><dt>End Date</dt><dd id="mdvEndDate"></dd></div>
          <div class="am-detail-row"><dt>Prescribed By</dt><dd id="mdvPrescribedBy"></dd></div>
          <div class="am-detail-row"><dt>Status</dt><dd id="mdvStatus"></dd></div>
          <div class="am-detail-row"><dt>Notes</dt><dd id="mdvNotes"></dd></div>
        </dl>
      </div>
      <div class="modal-footer">
        <button type="button" class="am-btn-cancel" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
