<!-- =============================================================
     UPLOAD DOCUMENT MODAL (shared)
     -------------------------------------------------------------
     Triggered by assets/js/document-upload.js once a valid file has
     been picked/dropped on the Upload Document panel below it on
     doctor/documents.php, medical-staff/documents.php, and
     subadmin/documents.php — a category is required on every real
     patient_documents/{id} doc already read back by this page's own
     table, so this is the first real place one gets collected before
     api/upload-document.php's real multipart POST. "am-" (Add Modal)
     styling, same shared form-modal look every other modal in this
     app uses.
     ============================================================= -->
<div class="modal fade am-modal" id="uploadDocumentModal" tabindex="-1" aria-labelledby="uploadDocumentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="uploadDocumentModalLabel">Upload Document</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="udmError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>

        <div class="mb-3">
          <label class="am-form-label">File</label>
          <div class="am-form-control" id="udmFileName" style="display:flex; align-items:center; gap:8px;"></div>
        </div>

        <div class="mb-3">
          <label class="am-form-label" for="udmCategory">Category<span class="text-danger">*</span></label>
          <select class="form-select am-form-control" id="udmCategory" required>
            <option value="" selected disabled>Select category</option>
            <option value="laboratory_result">Laboratory Result</option>
            <option value="imaging">Imaging</option>
            <option value="prescription">Prescription</option>
            <option value="medical_certificate">Medical Certificate</option>
            <option value="vaccination_record">Vaccination Record</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="am-form-label" for="udmDescription">Description</label>
          <textarea class="form-control am-form-control" id="udmDescription" rows="3" placeholder="Optional notes about this document"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="am-btn-cancel" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="am-btn-submit" id="udmSubmitBtn"><i class="bi bi-cloud-arrow-up-fill"></i> <span id="udmSubmitLabel">Upload</span></button>
      </div>
    </div>
  </div>
</div>
