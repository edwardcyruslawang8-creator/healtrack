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

     "pcg-" prefixed, styled to the same modal conventions every other
     modal in this app already uses (header/body/footer
     padding+borders, form-label/form-control, a gray outline Close +
     a colored primary action in the footer) — see
     assets/css/add_medicine_modal.css's .am-modal/.am-form-*/.am-btn-*
     for the pattern this mirrors, and
     assets/css/consent_management.css's .csm-details-patient for the
     patient-identity header row this reuses. Self-contained in its
     own <style> block (not those two stylesheets) since
     patient_management.php doesn't load either.
     ============================================================= -->
<style>
.pcg-modal .modal-content{ border: none; border-radius: var(--adm-radius-lg, 16px); box-shadow: var(--adm-shadow-hover, 0 12px 32px rgba(0,0,0,.14)); }
.pcg-modal .modal-header{ border-bottom: 1px solid var(--adm-border, #ECEDF3); padding: 20px 24px; }
.pcg-modal .modal-title{ font-size: 17px; font-weight: 700; color: var(--adm-text, #1F2937); }
.pcg-modal .modal-body{ padding: 22px 24px; }
.pcg-modal .modal-footer{ border-top: 1px solid var(--adm-border, #ECEDF3); padding: 16px 24px; gap: 10px; }

/* Patient identity row — same shape as .csm-details-patient
   (Consent Details modal), avatar + name, so this reads as "about a
   real patient", not a generic security dialog. */
.pcg-patient-row{ display: flex; align-items: center; gap: 14px; padding-bottom: 16px; margin-bottom: 16px; border-bottom: 1px solid var(--adm-border, #ECEDF3); }
.pcg-avatar{ width: 44px; height: 44px; min-width: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 19px; background: #FCE4E4; color: #D93025; }
.pcg-patient-name{ font-size: 15.5px; font-weight: 700; color: var(--adm-text, #1F2937); }
.pcg-patient-sub{ font-size: 12px; color: var(--adm-text-muted, #6B7280); display: flex; align-items: center; gap: 5px; margin-top: 2px; }

.pcg-status-banner{ display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; padding: 10px 14px; border-radius: 10px; margin-bottom: 16px; }
.pcg-status-banner.pending{ background: #FDF3DC; color: #B7791F; }
.pcg-status-banner.declined{ background: #FCE4E4; color: #D93025; }
.pcg-status-banner.sent{ background: #E4F7EA; color: #1E8E3E; }

.pcg-form-label{ font-size: 12.5px; font-weight: 600; color: var(--adm-text, #1F2937); margin-bottom: 6px; display: block; }
.pcg-form-control{ background: var(--adm-bg, #F7F8FA); border: 1px solid var(--adm-border, #ECEDF3); color: var(--adm-text, #1F2937); border-radius: 10px; padding: 10px 14px; font-size: 13px; width: 100%; resize: vertical; }
.pcg-form-control:focus{ outline: none; border-color: #5B0A0A; box-shadow: 0 0 0 3px rgba(91, 10, 10, 0.12); }

.pcg-btn-cancel{ background: var(--adm-card, #fff); border: 1px solid var(--adm-border, #ECEDF3); color: var(--adm-text-muted, #6B7280); border-radius: 10px; padding: 10px 20px; font-size: 13px; font-weight: 600; }
.pcg-btn-cancel:hover{ background: var(--adm-hover-bg, #F3F4F6); }
.pcg-btn-submit{ display: flex; align-items: center; gap: 8px; background: var(--adm-red-bright, #5B0A0A); color: #fff; border: none; border-radius: 10px; padding: 10px 20px; font-size: 13px; font-weight: 600; transition: background .15s ease; }
.pcg-btn-submit:hover{ background: var(--adm-red-bright-hover, #7A1515); color: #fff; }
.pcg-btn-submit:disabled{ opacity: .7; }
</style>
<div class="modal fade pcg-modal" id="pcgModal" tabindex="-1" aria-labelledby="pcgModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="pcgModalLabel"><i class="bi bi-shield-lock-fill me-2"></i>Request Access to Patient Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="pcg-patient-row">
          <span class="pcg-avatar"><i class="bi bi-person-fill"></i></span>
          <div>
            <div class="pcg-patient-name" id="pcgPatientName">—</div>
            <div class="pcg-patient-sub"><i class="bi bi-lock-fill"></i> Private — this patient's consent is required to view their full profile</div>
          </div>
        </div>

        <div class="pcg-status-banner pending d-none" id="pcgPendingStatus">
          <i class="bi bi-hourglass-split"></i>
          <span id="pcgPendingText"></span>
        </div>
        <div class="pcg-status-banner declined d-none" id="pcgDeclinedStatus">
          <i class="bi bi-x-circle-fill"></i>
          <span id="pcgDeclinedText"></span>
        </div>

        <div id="pcgError" class="alert alert-danger py-2 px-3 d-none" style="font-size:13px;"></div>

        <form id="pcgForm">
          <label for="pcgReason" class="pcg-form-label">Why do you need to view this patient's details?</label>
          <textarea class="pcg-form-control" id="pcgReason" rows="3" required placeholder="e.g. Reviewing the patient's medical history before consultation"></textarea>
        </form>

        <div id="pcgSentState" class="d-none">
          <div class="pcg-status-banner sent">
            <i class="bi bi-check-circle-fill"></i>
            Consent request sent — you'll be able to view this profile once the patient responds.
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="pcg-btn-cancel" data-bs-dismiss="modal">Close</button>
        <button type="submit" form="pcgForm" class="pcg-btn-submit" id="pcgSubmitBtn">
          <i class="bi bi-send-fill"></i> Send Consent Request
        </button>
      </div>
    </div>
  </div>
</div>
