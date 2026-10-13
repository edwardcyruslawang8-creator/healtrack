/* =============================================================
   ADD NEW FACILITY MODAL JS
   ---------------------------------------------------------------
   Shared by admin_dashboard.php and facility_management.php — both
   pages load this file alongside includes/add_facility_modal.php.
   Wires up every "Add Facility" trigger button to open the modal,
   validates the form, then POSTs to api/create-facility.php, which
   writes a real Firestore "facilities" document. On success the page
   reloads so the card grid / Facility Overview table re-render from
   that live data instead of guessing what the new row should look
   like.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('addFacilityModal');
    const form = document.getElementById('addFacilityForm');
    if (!modalEl || !form || typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalEl);
    const submitBtn = form.querySelector('.adm-modal-btn-submit');
    const submitBtnDefaultHtml = submitBtn ? submitBtn.innerHTML : '';

    let errorBox = document.getElementById('addFacilityError');
    if (!errorBox) {
        errorBox = document.createElement('div');
        errorBox.id = 'addFacilityError';
        errorBox.className = 'alert alert-danger py-2 px-3 small d-none';
        errorBox.setAttribute('role', 'alert');
        form.querySelector('.modal-body').prepend(errorBox);
    }

    document.querySelectorAll('#btnAddFacility, .btn-add-facility').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.reset();
            form.classList.remove('was-validated');
            errorBox.classList.add('d-none');
            modal.show();
        });
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        const payload = {
            name: document.getElementById('facilityName').value.trim(),
            location: document.getElementById('facilityLocation').value.trim(),
            status: document.getElementById('facilityStatus').value,
        };

        errorBox.classList.add('d-none');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding…';
        }

        fetch('../api/create-facility.php', {
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
                window.location.reload();
            })
            .catch(function (err) {
                errorBox.textContent = err.message;
                errorBox.classList.remove('d-none');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtnDefaultHtml;
                }
            });
    });
});
