/* =============================================================
   PROFILE / SETTINGS JS (admin/profile_settings.php)
   ---------------------------------------------------------------
   - "Save Changes" (Profile Information) and "Update Password" POST
     to api/update-profile-settings.php / api/update-admin-password.php.
   - Notification toggles and Theme/Language dropdowns auto-save on
     change (no separate button — matches how most settings pages
     handle instant-apply preferences).
   - "Change Password" in Security Settings scrolls to and focuses the
     Current Password field in the Password Change form below rather
     than being a second, separate flow.
   - Password visibility toggles are purely client-side (no request).
   - "Enable 2FA" / "Disable 2FA" is handled by assets/js/two-fa.js
     instead (loaded separately by admin/profile_settings.php) — kept
     out of this already-large file.
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

    /* ---------- Password visibility toggles ---------- */
    document.querySelectorAll('.prf-toggle-visibility').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = document.getElementById(btn.dataset.target);
            const icon = btn.querySelector('i');
            if (!input || !icon) return;

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            icon.classList.toggle('bi-eye', !isHidden);
            icon.classList.toggle('bi-eye-slash', isHidden);
            btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    });

    /* ---------- Profile Information: Save Changes ---------- */
    const profileForm = document.getElementById('profileInfoForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const submitBtn = profileForm.querySelector('button[type="submit"]');
            const defaultHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving…';

            // Photo first (if one was picked — a no-op Promise otherwise,
            // see assets/js/profile-photo-preview.js), THEN the text
            // fields — same reasoning as
            // assets/js/subadmin-profile-settings.js's identical change.
            window.HCProfilePhoto.uploadIfSelected()
                .then(function () {
                    return postJson('../api/update-profile-settings.php', {
                        fullName: document.getElementById('fullName').value.trim(),
                        phone: document.getElementById('phone').value.trim(),
                        healthCenter: document.getElementById('healthCenter').value,
                    });
                })
                .then(function () {
                    window.location.reload();
                })
                .catch(function (err) {
                    HCDialog.alert(err.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = defaultHtml;
                });
        });
    }

    /* ---------- Password Change ---------- */
    const passwordForm = document.getElementById('passwordChangeForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            // window.HC_HAS_PASSWORD (set server-side, see profile_settings.php)
            // is false for a "Sign up with Google" account that has no
            // password yet — the Current Password field is hidden for
            // that case (nothing to verify), so it isn't required here either.
            if ((window.HC_HAS_PASSWORD !== false && !currentPassword) || !newPassword || !confirmPassword) {
                HCDialog.alert('Please fill out every field.');
                return;
            }
            if (newPassword !== confirmPassword) {
                HCDialog.alert('New passwords do not match.');
                return;
            }

            const submitBtn = passwordForm.querySelector('button[type="submit"]');
            const defaultHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating…';

            postJson('../api/update-admin-password.php', { currentPassword: currentPassword, newPassword: newPassword, confirmPassword: confirmPassword })
                .then(function () {
                    HCDialog.alert('Password updated successfully.');
                    passwordForm.reset();
                })
                .catch(function (err) {
                    HCDialog.alert(err.message);
                })
                .finally(function () {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = defaultHtml;
                });
        });
    }

    /* ---------- "Change Password" in Security Settings -> jump to the form above ---------- */
    const btnChangePassword = document.getElementById('btnChangePassword');
    if (btnChangePassword) {
        btnChangePassword.addEventListener('click', function () {
            const target = document.getElementById('currentPassword');
            if (!target) return;
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            target.focus();
        });
    }

    /* ---------- Notification toggles: auto-save on change ---------- */
    document.querySelectorAll('.prf-switch .form-check-input').forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            const key = toggle.id.replace('notif-', '');
            const notifications = {};
            notifications[key] = toggle.checked;

            postJson('../api/update-profile-settings.php', { notifications: notifications })
                .catch(function (err) {
                    toggle.checked = !toggle.checked; // revert on failure
                    HCDialog.alert(err.message);
                });
        });
    });

    /* ---------- Theme: auto-save + apply instantly (no reload needed) ---------- */
    const themeSelect = document.getElementById('themeSelect');
    if (themeSelect) {
        themeSelect.addEventListener('change', function () {
            const previous = document.documentElement.getAttribute('data-bs-theme');
            const value = themeSelect.value;

            if (value === 'System') {
                window.ADMIN_THEME_MODE = 'System';
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.setAttribute('data-bs-theme', prefersDark ? 'dark' : 'light');
            } else {
                window.ADMIN_THEME_MODE = value;
                document.documentElement.setAttribute('data-bs-theme', value.toLowerCase());
            }

            postJson('../api/update-profile-settings.php', { theme: value })
                .catch(function (err) {
                    document.documentElement.setAttribute('data-bs-theme', previous);
                    HCDialog.alert(err.message);
                });
        });
    }

    /* ---------- Language: auto-save, then reload so the translated
       sidebar/page text (rendered server-side) takes effect ---------- */
    const languageSelect = document.getElementById('languageSelect');
    if (languageSelect) {
        languageSelect.addEventListener('change', function () {
            postJson('../api/update-profile-settings.php', { language: languageSelect.value })
                .then(function () {
                    window.location.reload();
                })
                .catch(function (err) { HCDialog.alert(err.message); });
        });
    }
});
