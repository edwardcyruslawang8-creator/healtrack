<!-- =============================================================
     CONSULTATION DETAILS MODAL (shared — doctor / medical-staff /
     subadmin history.php)
     -------------------------------------------------------------
     The complete record of one past consultation. Content comes from
     the clicked row's own <template class="his-cons-details">
     (includes/shared/_history_record_table.php, built by
     includes/shared/consultation_details_view.php) — no extra request.
     Lives inside <main> but outside the AJAX-swapped table card, so it
     survives a filter/pagination refresh and a Patient Profile tab
     switch. Script: assets/js/consultation-details-modal.js.
     ============================================================= -->
<div class="modal fade cdv-modal" id="consultationDetailsModal" tabindex="-1" aria-labelledby="consultationDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div class="cdv-modal-kicker">Consultation Details</div>
          <h5 class="modal-title" id="consultationDetailsModalLabel">Consultation</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="consultationDetailsBody"></div>
      <div class="modal-footer">
        <button type="button" class="vdm-btn-close" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
