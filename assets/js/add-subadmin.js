/* =============================================================
   ADD SUB-ADMINISTRATOR MODAL JS (admin/subadmin_management.php)
   ---------------------------------------------------------------
   Opens the modal, validates the form (including a client-side
   password-match check for instant feedback), then POSTs to
   api/create-subadmin.php, which creates a real Firebase Auth account
   + Firestore users/{uid} profile doc. On success the page reloads so
   the table/stat cards re-render from that live Firestore data (see
   includes/subadmin_management_data.php) instead of patching the DOM
   with a guess at what the row should look like.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('addSubadminModal');
    const form = document.getElementById('addSubadminForm');
    if (!modalEl || !form || typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalEl);
    const errorBox = document.getElementById('addSubadminError');
    const submitBtn = document.getElementById('subadminSubmitBtn');
    const submitBtnDefaultHtml = submitBtn.innerHTML;
    const passwordInput = document.getElementById('subadminPassword');
    const confirmInput = document.getElementById('subadminConfirmPassword');

    document.querySelectorAll('#btnAddSubadmin').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.reset();
            form.classList.remove('was-validated');
            confirmInput.setCustomValidity('');
            errorBox.classList.add('d-none');
            modal.show();
        });
    });

    function checkPasswordsMatch() {
        confirmInput.setCustomValidity(
            passwordInput.value && confirmInput.value && passwordInput.value !== confirmInput.value
                ? 'mismatch'
                : ''
        );
    }
    passwordInput.addEventListener('input', checkPasswordsMatch);
    confirmInput.addEventListener('input', checkPasswordsMatch);

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        e.stopPropagation();

        checkPasswordsMatch();
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        const payload = {
            username: document.getElementById('subadminUsername').value.trim(),
            email: document.getElementById('subadminEmail').value.trim(),
            facilityId: document.getElementById('subadminFacility').value,
            password: passwordInput.value,
            confirmPassword: confirmInput.value,
        };

        errorBox.classList.add('d-none');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding…';

        fetch('../api/create-subadmin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.error || 'Something went wrong. Please try again.');
                }
                // Reload so the table/stat cards re-render from the fresh
                // Firestore data instead of guessing what the new row looks like.
                window.location.reload();
            })
            .catch(function (err) {
                errorBox.textContent = err.message;
                errorBox.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtnDefaultHtml;
            });
    });
});
