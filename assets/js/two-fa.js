/* =============================================================
   TWO-FACTOR AUTHENTICATION JS (Profile / Settings, all 6 roles)
   ---------------------------------------------------------------
   Loaded by all 6 roles' profile_settings.php pages alongside their
   existing profile-settings.js / subadmin-profile-settings.js (kept
   separate rather than merged into those two already-large files).
   Drives includes/shared/two_fa_setup_modal.php's two modals:

     "Enable 2FA" (#btnEnable2fa, only present when 2FA is currently
     OFF — see each role's own includes/<role>/profile_settings_*_firestore.php
     file's $securitySettings) -> api/generate-2fa-secret.php -> renders the
     returned otpauth:// URL as a QR code CLIENT-SIDE (the "qrcode" CDN
     library each profile_settings.php loads — this environment has no
     GD extension, so there's no server-side image generation option)
     -> the 6-digit code from that QR gets verified by
     api/confirm-2fa-setup.php, which is what actually turns 2FA on.

     "Disable 2FA" (#btnDisable2fa, only present when 2FA is currently
     ON) -> a password-confirm modal -> api/disable-2fa.php.

   Both success paths reload the page — same "re-render from fresh
   Firestore data" pattern used everywhere else in this app — so the
   Security Settings row flips to the other button/state automatically.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {

    function postJson(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        }).then(function (res) {
            return res.json().then(function (data) { return { ok: res.ok, data: data }; });
        }).then(function (result) {
            if (!result.ok || !result.data.success) {
                throw new Error(result.data.error || 'Something went wrong. Please try again.');
            }
            return result.data;
        });
    }

    /* ---------- Enable 2FA ---------- */
    const btnEnable2fa = document.getElementById('btnEnable2fa');
    const setupModalEl = document.getElementById('twoFaSetupModal');
    if (btnEnable2fa && setupModalEl && typeof bootstrap !== 'undefined') {
        const setupModal = new bootstrap.Modal(setupModalEl);
        const setupError = document.getElementById('twoFaSetupError');
        const qrCanvas = document.getElementById('twoFaQrCanvas');
        const secretText = document.getElementById('twoFaSecretText');
        const codeInput = document.getElementById('twoFaSetupCode');
        const confirmBtn = document.getElementById('twoFaSetupConfirmBtn');
        const confirmBtnDefaultHtml = confirmBtn.innerHTML;
        let pendingSecret = '';

        btnEnable2fa.addEventListener('click', function () {
            setupError.classList.add('d-none');
            codeInput.value = '';
            secretText.textContent = '';
            pendingSecret = '';
            btnEnable2fa.disabled = true;

            postJson('../api/generate-2fa-secret.php', {})
                .then(function (data) {
                    pendingSecret = data.secret;
                    secretText.textContent = data.secret;
                    setupModal.show();
                    // qrcode CDN library (loaded by this page's own
                    // <script> tag, see admin/profile_settings.php etc.) —
                    // renders straight into the <canvas> from the real
                    // otpauth:// URL, no server round-trip needed for the
                    // image itself.
                    if (typeof QRCode !== 'undefined') {
                        QRCode.toCanvas(qrCanvas, data.otpauthUrl, { width: 220 }, function (err) {
                            if (err) console.error(err);
                        });
                    }
                })
                .catch(function (err) {
                    HCDialog.alert(err.message);
                })
                .finally(function () {
                    btnEnable2fa.disabled = false;
                });
        });

        confirmBtn.addEventListener('click', function () {
            const code = codeInput.value.trim();
            if (!code) {
                setupError.textContent = 'Please enter the 6-digit code from your authenticator app.';
                setupError.classList.remove('d-none');
                return;
            }

            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Verifying…';

            postJson('../api/confirm-2fa-setup.php', { secret: pendingSecret, code: code })
                .then(function () {
                    window.location.reload();
                })
                .catch(function (err) {
                    setupError.textContent = err.message;
                    setupError.classList.remove('d-none');
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = confirmBtnDefaultHtml;
                });
        });
    }

    /* ---------- Disable 2FA ---------- */
    const btnDisable2fa = document.getElementById('btnDisable2fa');
    const disableModalEl = document.getElementById('twoFaDisableModal');
    if (btnDisable2fa && disableModalEl && typeof bootstrap !== 'undefined') {
        const disableModal = new bootstrap.Modal(disableModalEl);
        const disableError = document.getElementById('twoFaDisableError');
        const passwordInput = document.getElementById('twoFaDisablePassword');
        const confirmBtn = document.getElementById('twoFaDisableConfirmBtn');
        const confirmBtnDefaultHtml = confirmBtn.innerHTML;

        btnDisable2fa.addEventListener('click', function () {
            disableError.classList.add('d-none');
            passwordInput.value = '';
            disableModal.show();
        });

        confirmBtn.addEventListener('click', function () {
            const currentPassword = passwordInput.value;
            if (!currentPassword) {
                disableError.textContent = 'Please enter your current password.';
                disableError.classList.remove('d-none');
                return;
            }

            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Disabling…';

            postJson('../api/disable-2fa.php', { currentPassword: currentPassword })
                .then(function () {
                    window.location.reload();
                })
                .catch(function (err) {
                    disableError.textContent = err.message;
                    disableError.classList.remove('d-none');
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = confirmBtnDefaultHtml;
                });
        });
    }
});
