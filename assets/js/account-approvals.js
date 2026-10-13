/* =============================================================
   PENDING APPROVALS JS (subadmin/user_management.php)
   ---------------------------------------------------------------
   Reject button in the "Pending Approvals" table — POSTs to
   api/reject-account.php, then reloads so the table, stat cards, and
   "Accounts Needing Attention" counts all re-render from fresh
   Firestore data. Approve is handled separately, inline in
   subadmin/user_management.php itself — it opens the "Assign
   Facility" modal (#usmApproveModal) rather than acting immediately,
   since a self-registered account has no Facility on file yet and
   api/approve-account.php now requires one.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const tbody = document.getElementById('pendingApprovalsBody');
    const errorBox = document.getElementById('pendingApprovalsError');
    if (!tbody) return;

    function showError(message) {
        if (!errorBox) return;
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
    }

    function callEndpoint(url, uid, row, btn) {
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        if (errorBox) errorBox.classList.add('d-none');

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ uid: uid }),
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
                showError(err.message);
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
    }

    tbody.addEventListener('click', async function (e) {
        const row = e.target.closest('tr[data-uid]');
        if (!row) return;
        const uid = row.dataset.uid;

        const rejectBtn = e.target.closest('.acm-reject-btn');
        if (rejectBtn) {
            const confirmed = await HCDialog.confirm('Reject this account request? This cannot be undone — the request will be deleted entirely.');
            if (!confirmed) return;
            callEndpoint('../api/reject-account.php', uid, row, rejectBtn);
        }
    });
});
