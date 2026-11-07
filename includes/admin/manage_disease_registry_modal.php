<!-- =============================================================
     MANAGE DISEASE REGISTRY ENTRY MODAL
     -------------------------------------------------------------
     Shared by admin/disease_registry.php's "Manage" button (existing
     entries) and "Add to Registry" button (candidate conditions — see
     assets/js/disease-registry.js for how each populates this same
     modal differently).

     Two separate, independently-submitted sections, matching the two
     separate endpoints behind them:
       - Name/Keywords/Preventable — routine catalog maintenance, posts
         to api/update-disease-registry-entry.php (existing entries) or
         api/add-disease-registry-entry.php (new entries, from a
         candidate or from scratch) — no reason required, this never
         changes public visibility by itself (a new entry always starts
         Pending regardless of what's submitted here).
       - Classification — the real, audited, gated decision. Only shown
         for an EXISTING entry (a brand-new one is always Pending until
         saved once, then classified separately afterward). Posts to
         api/update-disease-classification.php, requires a documented
         reason (>=10 characters) and, when choosing Public Awareness,
         benefits from real preventive-measures text so the public map
         has something to show alongside the geographic data.
     ============================================================= -->
<div class="modal fade adm-modal" id="manageDiseaseRegistryModal" tabindex="-1" aria-labelledby="manageDiseaseRegistryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="manageDiseaseRegistryModalLabel">Manage Registry Entry</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <!-- ===== Name / Keywords / Preventable (routine maintenance) ===== -->
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
          <div id="mdrEntryError" class="alert alert-danger py-2 px-3 d-none" style="font-size:13px;"></div>
          <button type="submit" class="btn btn-outline-dark btn-sm" id="mdrEntrySaveBtn">Save Name / Keywords</button>
        </form>

        <hr class="my-4">

        <!-- ===== Classification decision (existing entries only) ===== -->
        <div id="mdrClassificationSection">
          <h6 class="fw-bold mb-2">Public-Visibility Classification</h6>
          <p class="text-muted" style="font-size:13px;">A real, documented decision — every change is permanently recorded with your name and reason.</p>
          <form id="mdrClassificationForm">
            <input type="hidden" id="mdrClassificationEntryId" name="id" value="">
            <div class="mb-3">
              <label for="mdrClassification" class="form-label fw-semibold">New Classification</label>
              <select class="form-select" id="mdrClassification" name="classification" required>
                <option value="public_awareness">Public Awareness — shown freely on the public GIS map</option>
                <option value="conditional_public_display">Conditional Public Display — shown, under stricter privacy suppression</option>
                <option value="internal_surveillance_only">Internal Surveillance Only — never shown publicly</option>
                <option value="pending_classification">Pending Classification — not yet reviewed</option>
              </select>
            </div>
            <div class="mb-3" id="mdrPreventiveMeasuresWrap">
              <label for="mdrPreventiveMeasures" class="form-label fw-semibold">Preventive Measures <span class="text-muted fw-normal">(shown on the public map for Public Awareness conditions)</span></label>
              <textarea class="form-control" id="mdrPreventiveMeasures" name="preventiveMeasures" rows="2" maxlength="1000" placeholder="e.g. Eliminate stagnant water, use mosquito repellent, wear long sleeves during peak hours."></textarea>
            </div>
            <div class="mb-3">
              <label for="mdrReason" class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
              <textarea class="form-control" id="mdrReason" name="reason" rows="3" required minlength="10" maxlength="1000" placeholder="Document the clinical/privacy basis for this classification (at least 10 characters)."></textarea>
              <div class="form-text">Becomes part of this condition's permanent classification history and cannot be edited afterward.</div>
            </div>
            <div id="mdrClassificationError" class="alert alert-danger py-2 px-3 d-none" style="font-size:13px;"></div>
          </form>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="adm-modal-btn-cancel" data-bs-dismiss="modal">Close</button>
        <button type="submit" form="mdrClassificationForm" class="adm-modal-btn-submit" id="mdrClassificationSaveBtn">
            <i class="bi bi-check-lg"></i> Save Classification
        </button>
      </div>
    </div>
  </div>
</div>
