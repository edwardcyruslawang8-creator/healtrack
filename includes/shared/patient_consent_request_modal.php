<!-- =============================================================
     PATIENT ACCESS RESTRICTED MODAL (shared partial)
     ---------------------------------------------------------------
     Included once by subadmin/medical-staff/doctor's own
     patient_management.php. Populated and opened entirely by
     assets/js/patient-consent-gate.js the instant a "View Profile"
     click (or Quick Search result click) resolves to "no access yet"
     via api/get-patient-consent-status.php — real content, not a
     placeholder, same data patient_profile.php's own full-page
     "Patient Details Restricted" card renders when reached directly.
     Submitting the form here posts to the SAME api/request-patient-consent.php
     endpoint that page's own form does.
     ============================================================= -->
<style>
/* Self-contained (not assets/css/patient_profile.css — patient_management.php
   doesn't load that stylesheet) so this modal's "restricted" styling
   renders correctly regardless of which role's page included it.
   Same rules as that stylesheet's own .ppf-restricted* block
   (patient_profile.php's full-page version of this same state). */
#pcgModal .ppf-restricted{ text-align: center; }
#pcgModal .ppf-restricted-icon{ font-size: 40px; color: var(--adm-text-soft, #9CA3AF); display: block; margin-bottom: 12px; }
#pcgModal .ppf-restricted h2{ font-size: 20px; font-weight: 800; color: var(--adm-text, #1F2937); margin: 0 0 6px; }
#pcgModal .ppf-restricted-sub{ font-size: 13.5px; color: var(--adm-text-muted, #6B7280); margin: 0 0 18px; }
#pcgModal .ppf-restricted-status{
  display: flex;
  align-items: center;
  gap: 8px;
  justify-content: center;
  font-size: 13px;
  font-weight: 600;
  padding: 10px 14px;
  border-radius: 10px;
  margin-bottom: 14px;
}
#pcgModal .ppf-restricted-status.pending{ background: #FDF3DC; color: #B7791F; }
#pcgModal .ppf-restricted-status.declined{ background: #FCE4E4; color: #D93025; }
#pcgModal .ppf-restricted-btn{
  display: inline-flex;
  align-items: center;
  gap: 8px;
  justify-content: center;
  width: 100%;
  background: var(--adm-red-bright, #5B0A0A);
  color: #fff;
  border: none;
  border-radius: 10px;
  padding: 11px 20px;
  font-size: 14px;
  font-weight: 700;
}
#pcgModal .ppf-restricted-btn:disabled{ opacity: .7; }
</style>
<div class="modal fade" id="pcgModal" tabindex="-1" aria-labelledby="pcgModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="pcgModalLabel">Patient Details Restricted</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="ppf-restricted" style="padding:0;">
          <i class="bi bi-shield-lock-fill ppf-restricted-icon"></i>
          <h2 id="pcgPatientName">—</h2>
          <p class="ppf-restricted-sub" id="pcgSub">This patient's full details are private. You need their consent before you can view this profile.</p>

          <div class="ppf-restricted-status pending d-none" id="pcgPendingStatus">
            <i class="bi bi-hourglass-split"></i>
            <span id="pcgPendingText"></span>
          </div>
          <div class="ppf-restricted-status declined d-none" id="pcgDeclinedStatus">
            <i class="bi bi-x-circle-fill"></i>
            <span id="pcgDeclinedText"></span>
          </div>

          <div id="pcgError" class="alert alert-danger py-2 px-3 d-none" style="font-size:13px;"></div>

          <form id="pcgForm">
            <div class="mb-3 text-start">
              <label for="pcgReason" class="form-label fw-semibold">Why do you need to view this patient's details?</label>
              <textarea class="form-control" id="pcgReason" rows="3" required placeholder="e.g. Following up on today's walk-in visit"></textarea>
            </div>
            <button type="submit" class="ppf-restricted-btn" id="pcgSubmitBtn">
              <i class="bi bi-send-fill"></i> Send Consent Request
            </button>
          </form>

          <div id="pcgSentState" class="d-none">
            <div class="ppf-restricted-status pending">
              <i class="bi bi-check-circle-fill"></i>
              Consent request sent — you'll be able to view this profile once the patient responds.
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="csm-modal-btn-outline" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
