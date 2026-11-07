<!-- =============================================================
     MANAGE DISEASE REGISTRY ENTRY MODAL
     -------------------------------------------------------------
     Shared by admin/disease_registry.php's "Manage" button (existing
     entries) and "Add to Registry" button (candidate conditions — see
     assets/js/disease-registry.js for how each populates this same
     modal differently).

     One form, one endpoint — name/keywords/preventable/category is all
     routine catalog maintenance (api/update-disease-registry-entry.php
     for existing entries, api/add-disease-registry-entry.php for new
     ones), no reason required. Assigning a category is ordinary upkeep,
     not a gated decision — see includes/firestore/disease_registry.php's
     header comment for why there's no separate approval step. A
     brand-new entry always starts with no category regardless of what's
     submitted here (server-enforced), so the Category field is hidden
     until the entry has been saved once.
     ============================================================= -->
<div class="modal fade adm-modal" id="manageDiseaseRegistryModal" tabindex="-1" aria-labelledby="manageDiseaseRegistryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="manageDiseaseRegistryModalLabel">Manage Registry Entry</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <form id="mdrEntryForm">
          <input type="hidden" id="mdrEntryId" name="id" value="">
          <div class="mb-3">
            <label for="mdrName" class="form-label fw-semibold">Condition Name</label>
            <input type="text" class="form-control" id="mdrName" name="name" required placeholder="e.g. Dengue">
          </div>
          <div class="mb-3">
            <label for="mdrKeywords" class="form-label fw-semibold">Match Keywords</label>
            <input type="text" class="form-control" id="mdrKeywords" name="keywords" required placeholder="comma-separated, e.g. dengue, dengue fever">
            <div class="form-text">Matched case-insensitively as a substring against a consultation's documented diagnosis. Comma-separated.</div>
          </div>
          <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="mdrPreventable" name="isPreventable">
            <label class="form-check-label" for="mdrPreventable">Practical preventive actions exist for this condition</label>
          </div>

          <div id="mdrCategorySection">
            <div class="mb-3">
              <label for="mdrCategory" class="form-label fw-semibold">Category <span class="text-muted fw-normal">(automatically public on the GIS map once set)</span></label>
              <select class="form-select" id="mdrCategory" name="category">
                <option value="">Uncategorized — excluded from the public GIS map</option>
                <option value="communicable_preventable">Communicable and Preventable Diseases</option>
                <option value="respiratory">Respiratory Health Concerns</option>
                <option value="environmental_sanitation">Environmental and Sanitation-Related Conditions</option>
                <option value="animal_bite">Animal Bite Exposures</option>
                <option value="other_approved_prevention">Other Approved Prevention-Focused Health Concerns</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="mdrPreventiveMeasures" class="form-label fw-semibold">Preventive Measures <span class="text-muted fw-normal">(shown on the public map once categorized)</span></label>
              <textarea class="form-control" id="mdrPreventiveMeasures" name="preventiveMeasures" rows="2" maxlength="1000" placeholder="e.g. Eliminate stagnant water, use mosquito repellent, wear long sleeves during peak hours."></textarea>
            </div>
          </div>

          <div id="mdrEntryError" class="alert alert-danger py-2 px-3 d-none" style="font-size:13px;"></div>
        </form>

      </div>
      <div class="modal-footer">
        <button type="button" class="adm-modal-btn-cancel" data-bs-dismiss="modal">Close</button>
        <button type="submit" form="mdrEntryForm" class="adm-modal-btn-submit" id="mdrEntrySaveBtn">
            <i class="bi bi-check-lg"></i> Save
        </button>
      </div>
    </div>
  </div>
</div>
