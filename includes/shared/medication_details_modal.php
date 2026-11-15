<!-- =============================================================
     VIEW MEDICATION DETAILS MODAL (shared)
     -------------------------------------------------------------
     Triggered by each row's "View Details" button on
     doctor/medications.php, medical-staff/medications.php, and
     subadmin/medications.php (assets/js/medication-details.js).
     Populated purely from the clicked row's data-med JSON — no
     network request, since the page already rendered every field
     server-side.

     Read-only for every role except a doctor ($canManageMedications,
     set by includes/doctor/medications_doctor_firestore.php), who also
     gets Extend / Renew, Discontinue, and Confirm Completed — posted to
     api/update-medication.php, which re-checks the doctor role itself.
     Moving a medication to Past when its period ends needs no button:
     that's automatic (see includes/firestore/medications.php).
     ============================================================= -->
<?php $hcCanManageMeds = !empty($canManageMedications); ?>
<div class="modal fade am-modal" id="medDetailsModal" tabindex="-1" aria-labelledby="medDetailsModalLabel" aria-hidden="true"
     <?php if ($hcCanManageMeds): ?>data-manage-url="<?= htmlspecialchars(BASE_URL . '/api/update-medication.php') ?>"<?php endif; ?>>
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
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
          <div class="am-detail-row mdc-detail-block"><dt>Indication / Reason</dt><dd id="mdvIndication"></dd></div>
          <div class="am-detail-row"><dt>Prescribed Duration</dt><dd id="mdvDuration"></dd></div>
          <div class="am-detail-row"><dt>Start Date</dt><dd id="mdvStartDate"></dd></div>
          <div class="am-detail-row"><dt>End Date</dt><dd id="mdvEndDate"></dd></div>
          <div class="am-detail-row" id="mdvOriginalEndRow" hidden><dt>Original End Date</dt><dd id="mdvOriginalEndDate"></dd></div>
          <div class="am-detail-row"><dt>Status</dt><dd><span id="mdvStatus" class="mdc-status-pill"></span></dd></div>
          <div class="am-detail-row" id="mdvStatusDetailRow" hidden><dt id="mdvStatusDetailLabel"></dt><dd id="mdvStatusDetail"></dd></div>
          <div class="am-detail-row" id="mdvExtensionsRow" hidden><dt>Extensions</dt><dd><ul id="mdvExtensions" class="mdc-extension-list"></ul></dd></div>
          <div class="am-detail-row"><dt>Prescribed By</dt><dd id="mdvPrescribedBy"></dd></div>
          <div class="am-detail-row"><dt>Consultation</dt><dd id="mdvConsultation"></dd></div>
          <div class="am-detail-row mdc-detail-block"><dt>Notes</dt><dd id="mdvNotes"></dd></div>
        </dl>
        <p class="mdc-ended-hint" id="mdvEndedHint" hidden>
          The prescribed treatment period has ended. This does not by itself confirm the patient completed the medication.
        </p>

        <?php if ($hcCanManageMeds): ?>
        <!-- Doctor-only action forms — one shown at a time. -->
        <div class="mdc-manage-form" id="mdvExtendForm" hidden>
          <h6 class="mdc-manage-title" id="mdvExtendTitle">Extend Medication</h6>
          <label class="form-label" for="mdvNewEndDate">New end date</label>
          <input type="date" class="form-control" id="mdvNewEndDate">
          <label class="form-label mt-2" for="mdvExtendReason">Reason <span class="text-muted fw-normal">(optional)</span></label>
          <textarea class="form-control" id="mdvExtendReason" rows="2" maxlength="500"></textarea>
        </div>
        <div class="mdc-manage-form" id="mdvDiscontinueForm" hidden>
          <h6 class="mdc-manage-title">Discontinue Medication</h6>
          <label class="form-label" for="mdvDiscontinueReason">Reason for discontinuing</label>
          <textarea class="form-control" id="mdvDiscontinueReason" rows="2" maxlength="500" placeholder="e.g. Adverse reaction, symptoms resolved, switched medication"></textarea>
        </div>
        <div class="mdc-manage-form" id="mdvConfirmForm" hidden>
          <h6 class="mdc-manage-title">Confirm Treatment Completed</h6>
          <p class="mdc-manage-hint">Confirm that the patient actually finished this course.</p>
          <label class="form-label" for="mdvConfirmNote">Note <span class="text-muted fw-normal">(optional)</span></label>
          <textarea class="form-control" id="mdvConfirmNote" rows="2" maxlength="500"></textarea>
        </div>
        <div class="mdc-manage-error" id="mdvManageError" role="alert" hidden></div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <?php if ($hcCanManageMeds): ?>
        <div class="mdc-manage-actions me-auto" id="mdvManageActions">
          <button type="button" class="mdc-manage-btn" data-mdv-open="extend" id="mdvBtnExtend" hidden>Extend</button>
          <button type="button" class="mdc-manage-btn" data-mdv-open="confirm" id="mdvBtnConfirm" hidden>Confirm Completed</button>
          <button type="button" class="mdc-manage-btn mdc-manage-btn-danger" data-mdv-open="discontinue" id="mdvBtnDiscontinue" hidden>Discontinue</button>
        </div>
        <button type="button" class="am-btn-submit" id="mdvSubmit" hidden>Save</button>
        <?php endif; ?>
        <button type="button" class="am-btn-cancel" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
