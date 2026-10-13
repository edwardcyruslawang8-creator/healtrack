/* =============================================================
   SUB-ADMINISTRATOR ROW ACTIONS JS (admin/subadmin_management.php)
   ---------------------------------------------------------------
   Wires up each row's View/Edit/Unlock/Delete buttons (data-id
   attributes, looked up in window.SUBADMINS_DATA — see the inline
   <script> in admin/subadmin_management.php). Edit POSTs to
   api/update-subadmin.php, Unlock (only rendered on a "locked" row —
   see includes/shared/login_lockout.php) POSTs to
   api/unlock-subadmin.php, Delete POSTs to api/delete-subadmin.php;
   all three reload the page on success so the table/stat cards
   re-render from the fresh Firestore data. A sub-admin's own account
   can only be unlocked here, by an admin — subadmin/user_management.php
   deliberately has no Unlock action for a subadmin-role row.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof bootstrap === 'undefined') return;
    const data = window.SUBADMINS_DATA || {};

    /* ---------- View ---------- */
    const viewModalEl = document.getElementById('viewSubadminModal');
    const viewModal = viewModalEl ? new bootstrap.Modal(viewModalEl) : null;

    document.querySelectorAll('.adm-action-btn.view').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const s = data[btn.dataset.id];
            if (!s || !viewModal) return;

            document.getElementById('viewSubadminName').textContent = s.name;
            document.getElementById('viewSubadminEmail').textContent = s.email;
            document.getElementById('viewSubadminFacility').textContent = s.facility;
            document.getElementById('viewSubadminRole').textContent = s.role;
            document.getElementById('viewSubadminStatus').textContent = s.statusLabel;
            document.getElementById('viewSubadminLastLogin').textContent = s.lastLogin || '—';
            viewModal.show();
        });
    });

    /* ---------- Edit ---------- */
    const editModalEl = document.getElementById('editSubadminModal');
    const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
    const editForm = document.getElementById('editSubadminForm');
    const editError = document.getElementById('editSubadminError');
    const editSubmitBtn = document.getElementById('editSubadminSubmitBtn');
    const editSubmitDefaultHtml = editSubmitBtn ? editSubmitBtn.innerHTML : '';

    document.querySelectorAll('.adm-action-btn.edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const s = data[btn.dataset.id];
            if (!s || !editModal) return;

            editError.classList.add('d-none');
            document.getElementById('editSubadminId').value = btn.dataset.id;
            document.getElementById('editSubadminName').value = s.name;
            document.getElementById('editSubadminFacility').value = s.facilityId;
            document.getElementById('editSubadminRole').value = s.role;
            document.getElementById('editSubadminStatus').value = s.status;
            editModal.show();
        });
    });

    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const payload = {
                id: document.getElementById('editSubadminId').value,
                name: document.getElementById('editSubadminName').value.trim(),
                facilityId: document.getElementById('editSubadminFacility').value,
                role: document.getElementById('editSubadminRole').value,
                status: document.getElementById('editSubadminStatus').value,
            };

            if (!payload.name) {
                editError.textContent = 'Please enter a username.';
                editError.classList.remove('d-none');
                return;
            }

            editError.classList.add('d-none');
            editSubmitBtn.disabled = true;
            editSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving…';

            fetch('../api/update-subadmin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
                .then(function (res) {
                    return res.json().then(function (d) { return { ok: res.ok, data: d }; });
                })
                .then(function (result) {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.error || 'Something went wrong. Please try again.');
                    }
                    window.location.reload();
                })
                .catch(function (err) {
                    editError.textContent = err.message;
                    editError.classList.remove('d-none');
                    editSubmitBtn.disabled = false;
                    editSubmitBtn.innerHTML = editSubmitDefaultHtml;
                });
        });
    }

    /* ---------- Unlock ---------- */
    document.querySelectorAll('.adm-action-btn.continue').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const s = data[btn.dataset.id];
            if (!s) return;

            const confirmed = await HCDialog.confirm(
                'Unlock sub-administrator "' + s.name + '"? They\'ll be able to sign in again with their existing password.'
            );
            if (!confirmed) return;

            btn.disabled = true;

            fetch('../api/unlock-subadmin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: btn.dataset.id }),
            })
                .then(function (res) {
                    return res.json().then(function (d) { return { ok: res.ok, data: d }; });
                })
                .then(function (result) {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.error || 'Something went wrong. Please try again.');
                    }
                    window.location.reload();
                })
                .catch(function (err) {
                    HCDialog.alert(err.message);
                    btn.disabled = false;
                });
        });
    });

    /* ---------- Delete ---------- */
    document.querySelectorAll('.adm-action-btn.delete').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const s = data[btn.dataset.id];
            if (!s) return;

            const confirmed = await HCDialog.confirm(
                'Delete sub-administrator "' + s.name + '"? This permanently removes their account and cannot be undone.'
            );
            if (!confirmed) return;

            btn.disabled = true;

            fetch('../api/delete-subadmin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: btn.dataset.id }),
            })
                .then(function (res) {
                    return res.json().then(function (d) { return { ok: res.ok, data: d }; });
                })
                .then(function (result) {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.error || 'Something went wrong. Please try again.');
                    }
                    window.location.reload();
                })
                .catch(function (err) {
                    HCDialog.alert(err.message);
                    btn.disabled = false;
                });
        });
    });
});
