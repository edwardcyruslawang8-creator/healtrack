/* =============================================================
   SUB-ADMINISTRATOR ROW ACTIONS JS (admin/subadmin_management.php)
   ---------------------------------------------------------------
   Every row has one "Manage Account" button (data-manage-trigger) that
   opens #manageSubadminModal — a single shared entry point (replacing
   the old row of bare View/Edit/Unlock/Delete icons). Shows this
   account's real details right there (Name/Email/Facility/Role/
   Status/Last Login — folded in from what used to be a separate View
   modal) plus the real management actions below them: Edit Account is
   ALWAYS offered regardless of status (previously hidden for a locked/
   pending account, the one real gap — an admin needs to be able to
   manage those too, not just an already-active account), Unlock
   Account only when actually locked. Picking Edit/Unlock/Delete closes
   this modal and opens the real Edit modal or runs the real
   confirm+POST flow — same endpoints as before, just reached through
   one button instead of four.

   Edit POSTs to api/update-subadmin.php, Unlock (only offered on a
   "locked" account — see includes/shared/login_lockout.php) POSTs to
   api/unlock-subadmin.php, Delete POSTs to api/delete-subadmin.php;
   all three reload the page on success so the table/stat cards
   re-render from the fresh Firestore data. A sub-admin's own account
   can only be unlocked here, by an admin — subadmin/user_management.php
   deliberately has no Unlock action for a subadmin-role row.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof bootstrap === 'undefined') return;
    const data = window.SUBADMINS_DATA || {};

    /** Runs callback() once modalEl has fully finished hiding (its Bootstrap transition), or immediately if it's already hidden. */
    function afterHidden(modalEl, callback) {
        if (!modalEl || !modalEl.classList.contains('show')) {
            callback();
            return;
        }
        modalEl.addEventListener('hidden.bs.modal', function handler() {
            modalEl.removeEventListener('hidden.bs.modal', handler);
            callback();
        });
    }

    /* ---------- Edit ---------- */
    const editModalEl = document.getElementById('editSubadminModal');
    const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
    const editForm = document.getElementById('editSubadminForm');
    const editError = document.getElementById('editSubadminError');
    const editSubmitBtn = document.getElementById('editSubadminSubmitBtn');
    const editSubmitDefaultHtml = editSubmitBtn ? editSubmitBtn.innerHTML : '';

    function openEditModal(id) {
        const s = data[id];
        if (!s || !editModal) return;

        editError.classList.add('d-none');
        document.getElementById('editSubadminId').value = id;
        document.getElementById('editSubadminName').value = s.name;
        document.getElementById('editSubadminFacility').value = s.facilityId;
        document.getElementById('editSubadminRole').value = s.role;
        document.getElementById('editSubadminStatus').value = s.status;
        editModal.show();
    }

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
    function unlockAccount(id) {
        const s = data[id];
        if (!s) return;

        HCDialog.confirm(
            'Unlock sub-administrator "' + s.name + '"? They\'ll be able to sign in again with their existing password.'
        ).then(function (confirmed) {
            if (!confirmed) return;

            fetch('../api/unlock-subadmin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id }),
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
                });
        });
    }

    /* ---------- Delete ---------- */
    function deleteAccount(id) {
        const s = data[id];
        if (!s) return;

        HCDialog.confirm(
            'Delete sub-administrator "' + s.name + '"? This permanently removes their account and cannot be undone.'
        ).then(function (confirmed) {
            if (!confirmed) return;

            fetch('../api/delete-subadmin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id }),
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
                });
        });
    }

    /* ---------- Manage Account (single entry point) ---------- */
    const manageModalEl = document.getElementById('manageSubadminModal');
    const manageModal = manageModalEl ? new bootstrap.Modal(manageModalEl) : null;
    const manageUnlockRow = manageModalEl ? manageModalEl.querySelector('[data-manage-action="continue"]') : null;

    document.querySelectorAll('[data-manage-trigger]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = btn.dataset.id;
            const s = data[id];
            if (!s || !manageModal) return;

            manageModalEl.dataset.currentId = id;
            document.getElementById('manageSubadminName').textContent = s.name;
            document.getElementById('manageSubadminEmail').textContent = s.email;
            document.getElementById('manageSubadminFacility').textContent = s.facility;
            document.getElementById('manageSubadminRole').textContent = s.role;
            document.getElementById('manageSubadminStatus').textContent = s.statusLabel;
            document.getElementById('manageSubadminLastLogin').textContent = s.lastLogin || '—';

            // Edit is always offered regardless of status — Unlock only
            // when actually locked (it resets the lockout counter too,
            // which Edit's own Status field alone doesn't do).
            if (manageUnlockRow) manageUnlockRow.hidden = s.status !== 'locked';

            manageModal.show();
        });
    });

    if (manageModalEl) {
        manageModalEl.querySelectorAll('[data-manage-action]').forEach(function (actionBtn) {
            actionBtn.addEventListener('click', function () {
                const action = actionBtn.dataset.manageAction;
                const id = manageModalEl.dataset.currentId;
                if (!id) return;

                manageModal.hide();
                afterHidden(manageModalEl, function () {
                    if (action === 'edit') openEditModal(id);
                    else if (action === 'continue') unlockAccount(id);
                    else if (action === 'delete') deleteAccount(id);
                });
            });
        });
    }
});
