/* =============================================================
   ANNOUNCEMENTS (Sub-Admin) JS
   (subadmin/announcements.php)
   ---------------------------------------------------------------
   Drives the "+ New Announcement" modal
   (includes/subadmin/add_announcement_modal.php). Submitting POSTs to
   api/create-announcement.php, which writes a real doc to the
   "announcements" Firestore collection patient/announcements.php
   already reads — on success this page reloads so the list re-renders
   from fresh Firestore data, same reload-on-success pattern as
   assets/js/medicine_vaccine_inventory_pharmacist.js.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('addAnnouncementModal');
    const form = document.getElementById('addAnnouncementForm');
    if (!modalEl || !form || typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalEl);
    const errorBox = document.getElementById('addAnnouncementError');
    const submitBtn = document.getElementById('anSubmitBtn');
    const submitBtnDefaultHtml = submitBtn.innerHTML;

    const addBtn = document.getElementById('btnAddAnnouncement');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            form.reset();
            form.classList.remove('was-validated');
            errorBox.classList.add('d-none');
            submitBtn.disabled = false;
            submitBtn.innerHTML = submitBtnDefaultHtml;
            modal.show();
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        const payload = {
            title: document.getElementById('anTitle').value.trim(),
            desc: document.getElementById('anDesc').value.trim(),
            category: document.getElementById('anCategory').value,
            important: document.getElementById('anImportant').checked,
        };

        errorBox.classList.add('d-none');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Posting…';

        fetch('../api/create-announcement.php', {
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
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtnDefaultHtml;
            });
    });
});
