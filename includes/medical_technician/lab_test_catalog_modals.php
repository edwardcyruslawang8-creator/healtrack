<!-- =============================================================
     LAB TEST CATALOG — ADD LABORATORY / UPLOAD TEMPLATE MODALS
     -------------------------------------------------------------
     Reused by medical_technician/lab_test_catalog.php only.
     assets/js/lab_test_catalog.js wires:
       - "+ Add Laboratory" (#btnAddLabTest) -> this Add modal ->
         api/add-lab-test.php.
       - Each row's trash button (data-ltc-delete) -> confirm
         (HCDialog) -> api/delete-lab-test.php.
       - Each row's upload/replace button (data-ltc-upload) -> the
         Upload Template modal (a real file, multipart/form-data) ->
         api/upload-lab-test-template.php. Available for every row,
         including a built-in test (see
         includes/medical_technician/lab_test_catalog_firestore.php's
         header comment) — it's a reference attachment, not the test's
         actual result-entry form.
       - Each row's "Remove Template" button (data-ltc-delete-template)
         -> confirm (HCDialog) -> api/delete-lab-test-template.php.
     ============================================================= -->

<!-- ============ ADD LABORATORY MODAL ============ -->
<div class="modal fade adm-modal" id="ltcAddModal" tabindex="-1" aria-labelledby="ltcAddModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="ltcAddForm" novalidate>
        <div class="modal-header">
          <h5 class="modal-title" id="ltcAddModalLabel">Add Laboratory</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="ltcAddError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>

          <div class="mb-3">
            <label for="ltcAddTestName" class="form-label">Test Name</label>
            <input type="text" class="form-control" id="ltcAddTestName" placeholder="e.g. Lipid Profile" required>
          </div>
          <div class="mb-0">
            <label for="ltcAddCategory" class="form-label">Category</label>
            <input type="text" class="form-control" id="ltcAddCategory" list="ltcCategoryList" placeholder="e.g. Clinical Chemistry" required>
            <datalist id="ltcCategoryList">
              <?php foreach (array_keys($catalogByCategory) as $categoryName): ?>
              <option value="<?= htmlspecialchars($categoryName) ?>">
              <?php endforeach; ?>
            </datalist>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="adm-modal-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="adm-modal-btn-submit" id="ltcAddSubmitBtn"><i class="bi bi-plus-lg"></i> Add Laboratory</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============ UPLOAD TEMPLATE MODAL ============ -->
<div class="modal fade adm-modal" id="ltcUploadModal" tabindex="-1" aria-labelledby="ltcUploadModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="ltcUploadForm" novalidate>
        <input type="hidden" id="ltcUploadTestId">
        <div class="modal-header">
          <h5 class="modal-title" id="ltcUploadModalLabel">Upload Template</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="ltcUploadError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>
          <p class="adm-page-subtitle" style="margin-bottom:14px;">Upload the real paper form for this test (PDF, JPG, or PNG, up to 10MB) as a reference — the medical technologist filling in a result for this test will see a "View Template" link to it.</p>

          <label for="ltcUploadFileInput" class="ltc-dropzone" id="ltcUploadDropzone">
            <i class="bi bi-cloud-arrow-up"></i>
            <span id="ltcUploadDropzoneText">Click to choose a file, or drag one here</span>
            <div class="ltc-dropzone-filename d-none" id="ltcUploadFileNameLabel"></div>
          </label>
          <input type="file" id="ltcUploadFileInput" accept=".pdf,.jpg,.jpeg,.png" class="d-none" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="adm-modal-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="adm-modal-btn-submit" id="ltcUploadSubmitBtn"><i class="bi bi-upload"></i> Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>
