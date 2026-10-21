/* =============================================================
   SET PASSWORD PAGE JS (set-password.php)
   ---------------------------------------------------------------
   Submits to api/complete-password-setup.php, which reads the pending
   uid from the PHP session (set by api/session-login.php) rather than
   anything this form sends — this page just supplies the new password.
   On success, that endpoint has already completed the real app
   session, exactly like a normal login would have, and returns the
   same {success, role} shape api/session-login.php / api/verify-2fa.php
   do — routed here the same way assets/js/login.js's routeByRole()
   does, without needing a separate Firestore lookup.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('setPasswordForm');
    const passwordInput = document.getElementById('spPassword');
    const confirmInput = document.getElementById('spConfirmPassword');
    const errorBox = document.getElementById('spError');
    const submitBtn = document.getElementById('spSubmitBtn');
    const BASE_URL = window.APP_BASE_URL || '';

    if (!form) return;

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
    }
    function hideError() {
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
    }
    function setLoading(isLoading) {
        submitBtn.disabled = isLoading;
        submitBtn.innerHTML = isLoading
            ? '<span class="spinner-border spinner-border-sm"></span> Setting password...'
            : '<i class="bi bi-check-circle-fill"></i> Set Password &amp; Continue';
    }

    function routeByRole(role) {
        switch (role) {
            case 'admin':
                window.location.href = BASE_URL + '/admin/admin_dashboard.php';
                break;
            case 'subadmin':
                window.location.href = BASE_URL + '/subadmin/dashboard.php';
                break;
            case 'healthcare_staff':
                window.location.href = BASE_URL + '/medical-staff/dashboard.php';
                break;
            case 'pharmacist':
                window.location.href = BASE_URL + '/pharmacist/dashboard.php';
                break;
            case 'medical_technician':
                window.location.href = BASE_URL + '/medical_technician/dashboard.php';
                break;
            case 'radio_technologist':
                window.location.href = BASE_URL + '/radio_technologist/dashboard.php';
                break;
            case 'doctor':
                window.location.href = BASE_URL + '/doctor/dashboard.php';
                break;
            default:
                setLoading(false);
                showError('Password set, but the dashboard for your role isn\'t built yet. Please check back soon.');
        }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideError();

        const password = passwordInput.value;
        const confirmPassword = confirmInput.value;

        if (password.length < 6) {
            showError('Password must be at least 6 characters.');
            return;
        }
        if (password !== confirmPassword) {
            showError('Passwords do not match.');
            return;
        }

        setLoading(true);

        fetch(BASE_URL + '/api/complete-password-setup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ password: password, confirmPassword: confirmPassword }),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) {
                    setLoading(false);
                    showError(data.error || 'Could not set your password. Please try again.');
                    return;
                }
                routeByRole(data.role);
            })
            .catch(function () {
                setLoading(false);
                showError('Something went wrong. Please try again.');
            });
    });
});
