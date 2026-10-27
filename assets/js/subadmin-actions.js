/* =============================================================
   SUB-ADMINISTRATOR ROW ACTIONS JS (admin/subadmin_management.php)
   ---------------------------------------------------------------
   Every row has one "Manage Account" button (data-manage-trigger) that
   opens #manageSubadminModal — a single shared entry point. Shows this
   account's real details (Name/Email/Facility/Role/Status/Last Login)
   plus the real management actions below them: a real Active/Inactive
   switch (POSTs to api/update-subadmin-status.php on change, reloads
   on success), Unlock Account (only when actually locked — it resets
   the real lockout counter too, which the status switch alone
   wouldn't), and Delete Account. There's no "Edit Account" any more —
   username/facility/role are permanent once a sub-administrator is
   created (see includes/admin/subadmin_view_edit_modals.php's header
   comment); status is the one thing an admin can still change, via the
   switch instead of a separate edit form.

   Unlock POSTs to api/unlock-subadmin.php, Delete POSTs to
   api/delete-subadmin.php; both reload the page on success so the
   table/stat cards re-render from the fresh Firestore data. A sub-
   admin's own account can only be unlocked here, by an admin —
   subadmin/user_management.php deliberately has no Unlock action for a
   subadmin-role row.
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
    const statusToggle = document.getElementById('manageSubadminStatusToggle');
    const statusToggleLabel = document.getElementById('manageSubadminStatusToggleLabel');

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

            // Unlock only when actually locked (it resets the lockout
            // counter too, which the status switch below can't do).
            if (manageUnlockRow) manageUnlockRow.hidden = s.status !== 'locked';

            // The Active/Inactive switch only ever applies to an
            // account that's actually active or inactive — a locked or
            // pending account goes through Unlock Account / the
            // pending-invitation flow instead (see
            // api/update-subadmin-status.php's own header comment).
            if (statusToggle) {
                const canToggle = s.status === 'active' || s.status === 'inactive';
                statusToggle.checked = s.status === 'active';
                statusToggle.disabled = !canToggle;
                statusToggle.title = canToggle ? '' : 'This account is currently ' + s.statusLabel.toLowerCase() + ' — use Unlock Account instead, or wait for the invitation to be accepted.';
                if (statusToggleLabel) statusToggleLabel.textContent = canToggle ? 'Active' : s.statusLabel;
            }

            manageModal.show();
        });
    });

    if (statusToggle) {
        statusToggle.addEventListener('change', function () {
            const id = manageModalEl.dataset.currentId;
            const s = id ? data[id] : null;
            if (!id || !s) return;

            const wasChecked = !statusToggle.checked;
            const newStatus = statusToggle.checked ? 'active' : 'inactive';
            statusToggle.disabled = true;

            fetch('../api/update-subadmin-status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, status: newStatus }),
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
                    statusToggle.checked = wasChecked;
                    statusToggle.disabled = false;
                    HCDialog.alert(err.message);
                });
        });
    }

    if (manageModalEl) {
        manageModalEl.querySelectorAll('[data-manage-action]').forEach(function (actionBtn) {
            actionBtn.addEventListener('click', function () {
                const action = actionBtn.dataset.manageAction;
                const id = manageModalEl.dataset.currentId;
                if (!id) return;

                manageModal.hide();
                afterHidden(manageModalEl, function () {
                    if (action === 'continue') unlockAccount(id);
                    else if (action === 'delete') deleteAccount(id);
                });
            });
        });
    }
});
