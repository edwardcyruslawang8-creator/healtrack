/* =============================================================
   ANNOUNCEMENTS (ADMIN) — Approve/Reject + Send a Note
   (admin/announcements.php only)
   ---------------------------------------------------------------
   Approve/Reject: real writes to api/approve-announcement.php /
   api/reject-announcement.php, same confirm-then-reload pattern
   assets/js/subadmin-actions.js's own Delete button uses.

   Send a Note: real write to api/create-admin-note.php; on success the
   form clears and the page reloads so "Notes Sent" below picks up the
   new one from the real Firestore read, rather than hand-building the
   same row markup a second time in JS.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ann-adm-btn-approve').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const row = btn.closest('[data-announcement-row]');
            const title = row ? row.querySelector('td').textContent.trim() : 'this announcement';
            const confirmed = await HCDialog.confirm('Approve "' + title + '"? It will immediately become visible to patients.');
            if (!confirmed) return;

            row.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

            fetch('../api/approve-announcement.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: btn.dataset.id }),
            })
                .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, data: d }; }); })
                .then(function (result) {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.error || 'Something went wrong. Please try again.');
                    }
                    window.location.reload();
                })
                .catch(function (err) {
                    HCDialog.alert(err.message);
                    row.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
                });
        });
    });

    document.querySelectorAll('.ann-adm-btn-reject').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const row = btn.closest('[data-announcement-row]');
            const title = row ? row.querySelector('td').textContent.trim() : 'this announcement';
            const confirmed = await HCDialog.confirm('Reject "' + title + '"? It will never be shown to patients.');
            if (!confirmed) return;

            row.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

            fetch('../api/reject-announcement.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: btn.dataset.id }),
            })
                .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, data: d }; }); })
                .then(function (result) {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.error || 'Something went wrong. Please try again.');
                    }
                    window.location.reload();
                })
                .catch(function (err) {
                    HCDialog.alert(err.message);
                    row.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
                });
        });
    });

    const noteForm = document.getElementById('adminNoteForm');
    const noteFeedback = document.getElementById('adminNoteFeedback');
    if (noteForm) {
        noteForm.addEventListener('submit', function (e) {
            e.preventDefault();
            noteFeedback.textContent = '';
            noteFeedback.className = 'ann-adm-note-feedback';

            const submitBtn = noteForm.querySelector('.ann-adm-note-submit');
            submitBtn.disabled = true;

            const payload = {
                title: noteForm.title.value.trim(),
                message: noteForm.message.value.trim(),
                facility: noteForm.facility.value,
            };

            fetch('../api/create-admin-note.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
                .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, data: d }; }); })
                .then(function (result) {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.error || 'Something went wrong. Please try again.');
                    }
                    window.location.reload();
                })
                .catch(function (err) {
                    noteFeedback.textContent = err.message;
                    noteFeedback.className = 'ann-adm-note-feedback error';
                    submitBtn.disabled = false;
                });
        });
    }
});
