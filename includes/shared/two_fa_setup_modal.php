<!-- =============================================================
     TWO-FACTOR AUTHENTICATION MODALS
     -------------------------------------------------------------
     Required verbatim by all 6 roles' profile_settings.php pages
     (admin, subadmin, doctor, medical-staff, medical_technician,
     pharmacist) — same "one shared include" convention as
     includes/pharmacist/add_medicine_modal.php. Driven by
     assets/js/two-fa.js, styled by assets/css/profile_settings.css
     (already loaded by all 6 pages).

     #twoFaSetupModal — "Enable 2FA": api/generate-2fa-secret.php hands
     back a secret + otpauth:// URL; the QR is rendered CLIENT-SIDE from
     that URL into the <canvas> below (a CDN QR library, loaded only on
     this modal's host pages) — no server-side image generation
     (this environment has no GD extension). Entering the resulting
     6-digit code confirms setup via api/confirm-2fa-setup.php.

     #twoFaDisableModal — "Disable 2FA": requires the CURRENT password
     (api/disable-2fa.php re-verifies it server-side, same Identity
     Toolkit pattern api/update-admin-password.php already uses) —
     matches this page's own "Password Change" form's security bar,
     not a bare confirm().
     ============================================================= -->
<div class="modal fade" id="twoFaSetupModal" tabindex="-1" aria-labelledby="twoFaSetupModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="twoFaSetupModalLabel">Enable Two-Factor Authentication</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="twoFaSetupError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>

        <p class="prf-2fa-step-desc">1. Scan this QR code with an authenticator app (Google Authenticator, Authy, Microsoft Authenticator, etc.).</p>
        <div class="prf-2fa-qr-wrap">
          <canvas id="twoFaQrCanvas"></canvas>
        </div>

        <p class="prf-2fa-step-desc">Can't scan it? Enter this key manually instead:</p>
        <div class="prf-2fa-secret-box" id="twoFaSecretText"></div>

        <p class="prf-2fa-step-desc">2. Enter the 6-digit code your app is showing now:</p>
        <input type="text" class="form-control prf-2fa-code-input" id="twoFaSetupCode" placeholder="000000" inputmode="numeric" maxlength="6" autocomplete="one-time-code">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="prf-btn-primary" id="twoFaSetupConfirmBtn">Verify &amp; Enable</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="twoFaDisableModal" tabindex="-1" aria-labelledby="twoFaDisableModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="twoFaDisableModalLabel">Disable Two-Factor Authentication</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="twoFaDisableError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>
        <p class="prf-2fa-step-desc">Enter your current password to confirm — this removes the extra sign-in step from your account.</p>
        <input type="password" class="form-control" id="twoFaDisablePassword" placeholder="Current password">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="prf-btn-danger" id="twoFaDisableConfirmBtn">Disable 2FA</button>
      </div>
    </div>
  </div>
</div>
