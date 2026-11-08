<!-- =============================================================
     MANAGE DISEASE REGISTRY ENTRY MODAL
     -------------------------------------------------------------
     Shared by admin/disease_registry.php's "Manage" button (existing
     entries) and "Add Condition" button (new entries from scratch).

     One form, one endpoint per action — name/synonyms/preventable/3
     preventive-measure fields (api/update-disease-registry-entry.php
     for existing entries, api/add-disease-registry-entry.php for new
     ones). There is no separate classification/GIS-eligibility/
     public-visibility step: saving a condition here — whether adding
     it or managing an existing one — makes it visible on the public
     GIS map immediately and automatically. The #mdrAddReminder notice
     below is shown only when adding (see assets/js/disease-registry.js)
     so an admin can't miss that before submitting.
     ============================================================= -->
<div class="modal fade adm-modal" id="manageDiseaseRegistryModal" tabindex="-1" aria-labelledby="manageDiseaseRegistryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="manageDiseaseRegistryModalLabel">Add Condition</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <div id="mdrAddReminder" class="alert alert-warning py-2 px-3 d-none" style="font-size:13px;">
          <i class="bi bi-exclamation-triangle-fill"></i> This condition will appear on the public GIS Mapping map as soon as you save it.
        </div>

        <form id="mdrEntryForm">
          <input type="hidden" id="mdrEntryId" name="id" value="">
          <div class="mb-3">
            <label for="mdrName" class="form-label fw-semibold">Condition Name</label>
            <input type="text" class="form-control" id="mdrName" name="name" required placeholder="e.g. Dengue">
          </div>
          <div class="mb-3">
            <label for="mdrSynonyms" class="form-label fw-semibold">Synonyms / Alternative Names</label>
            <input type="text" class="form-control" id="mdrSynonyms" name="synonyms" required placeholder="comma-separated, e.g. dengue, dengue fever, dengue hemorrhagic fever">
            <div class="form-text">Matched case-insensitively as a substring against a consultation's documented diagnosis. Comma-separated.</div>
          </div>
          <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="mdrPreventable" name="isPreventable">
            <label class="form-check-label" for="mdrPreventable">Practical preventive actions exist for this condition</label>
          </div>
          <div class="mb-3">
            <label for="mdrBehavioral" class="form-label fw-semibold">Behavioral Preventive Measures</label>
            <textarea class="form-control" id="mdrBehavioral" name="behavioralMeasures" rows="2" maxlength="500" placeholder="Actions individuals can take, e.g. use mosquito repellent."></textarea>
          </div>
          <div class="mb-3">
            <label for="mdrEnvironmental" class="form-label fw-semibold">Environmental Preventive Measures</label>
            <textarea class="form-control" id="mdrEnvironmental" name="environmentalMeasures" rows="2" maxlength="500" placeholder="Sanitation/drainage/vector-control improvements, e.g. eliminate stagnant water."></textarea>
          </div>
          <div class="mb-3">
            <label for="mdrCommunity" class="form-label fw-semibold">Community-Based Preventive Measures</label>
            <textarea class="form-control" id="mdrCommunity" name="communityMeasures" rows="2" maxlength="500" placeholder="Coordinated programs, e.g. organize cleanup drives and mosquito-control campaigns."></textarea>
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
