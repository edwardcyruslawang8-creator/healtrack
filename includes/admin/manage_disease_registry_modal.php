<!-- =============================================================
     MANAGE DISEASE REGISTRY ENTRY MODAL
     -------------------------------------------------------------
     Shared by admin/disease_registry.php's "Manage" button (existing
     entries) and "Add Condition" button (new entries from scratch).

     One form, one endpoint — name/synonyms/classification/GIS
     eligibility/public visibility/preventable/3 preventive-measure
     fields is all disease-level metadata upkeep
     (api/update-disease-registry-entry.php for existing entries,
     api/add-disease-registry-entry.php for new ones), no reason
     required — this is never a per-case decision. A brand-new entry
     always starts unclassified/gisEligible=false/publicVisibility=false
     server-side regardless of what's submitted here.
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
            <label for="mdrSynonyms" class="form-label fw-semibold">Synonyms / Alternative Names</label>
            <input type="text" class="form-control" id="mdrSynonyms" name="synonyms" required placeholder="comma-separated, e.g. dengue, dengue fever, dengue hemorrhagic fever">
            <div class="form-text">Matched case-insensitively as a substring against a consultation's documented diagnosis. Comma-separated.</div>
          </div>
          <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="mdrPreventable" name="isPreventable">
            <label class="form-check-label" for="mdrPreventable">Practical preventive actions exist for this condition</label>
          </div>

          <div id="mdrClassificationSection">
            <div class="mb-3">
              <label for="mdrClassification" class="form-label fw-semibold">Classification <span class="text-muted fw-normal">(required for GIS eligibility)</span></label>
              <select class="form-select" id="mdrClassification" name="classification">
                <option value="">Unclassified — excluded from GIS/public map</option>
                <option value="vector_borne">Vector-Borne Diseases</option>
                <option value="waterborne_foodborne">Waterborne and Foodborne Diseases</option>
                <option value="respiratory_contact">Respiratory and Contact-Transmitted Diseases</option>
                <option value="environment_zoonotic">Environment-Related Zoonotic Diseases</option>
                <option value="animal_exposure">Animal Exposure Incidents</option>
              </select>
            </div>
            <div class="row">
              <div class="col-6 mb-3">
                <div class="form-check">
                  <input type="checkbox" class="form-check-input" id="mdrGisEligible" name="gisEligible">
                  <label class="form-check-label" for="mdrGisEligible">GIS Eligible <span class="text-muted">(internal + public aggregation)</span></label>
                </div>
              </div>
              <div class="col-6 mb-3">
                <div class="form-check">
                  <input type="checkbox" class="form-check-input" id="mdrPublicVisibility" name="publicVisibility">
                  <label class="form-check-label" for="mdrPublicVisibility">Public Visibility <span class="text-muted">(shown on the public map)</span></label>
                </div>
              </div>
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
