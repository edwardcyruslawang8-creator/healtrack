/* =============================================================
   CONSENT MANAGEMENT JS (subadmin/consent_management.php)
   ---------------------------------------------------------------
   Each row's "View" (eye) button populates and opens a real Consent
   Details modal from that row's data-* attributes (see this page's
   header comment). The Accept/Reject buttons and the Add Consent
   modal/form were removed from the page — they were decorative with
   no write endpoint behind them — so this file no longer handles
   either.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.getElementById('csmTableBody');
    const detailsModalEl = document.getElementById('csmDetailsModal');
    if (!tableBody || !detailsModalEl) return;

    const statusMeta = {
        pending:  { label: 'Pending',  bg: '#FDF3DC', color: '#B7791F', icon: 'bi-clock-fill' },
        accepted: { label: 'Accepted', bg: '#E4F7EA', color: '#1E8E3E', icon: 'bi-check-circle-fill' },
        rejected: { label: 'Rejected', bg: '#FCE4E4', color: '#D93025', icon: 'bi-x-circle-fill' },
        expired:  { label: 'Expired',  bg: '#EEF0F3', color: '#6B7280', icon: 'bi-hourglass-split' },
    };

    /* ---------- View -> populate Consent Details modal ---------- */
    tableBody.addEventListener('click', function (e) {
        const viewBtn = e.target.closest('.csm-view-btn');
        if (!viewBtn) return;
        const row = viewBtn.closest('.csm-row');
        if (!row) return;

        const set = function (id, value, fallback) {
            const el = document.getElementById(id);
            if (el) el.textContent = value && value.trim() !== '' ? value : (fallback || '—');
        };

        set('csmDetailPatientName', row.dataset.patientName);
        set('csmDetailPatientId', row.dataset.patientId);
        set('csmDetailType', row.dataset.typeLabel);
        set('csmDetailPurpose', row.dataset.purpose);

        const requestedByRow = document.getElementById('csmDetailRequestedByRow');
        if (requestedByRow) {
            if (row.dataset.requestedByName) {
                set('csmDetailRequestedBy', row.dataset.requestedByName + (row.dataset.requestedByRole ? ' (' + row.dataset.requestedByRole + ')' : ''));
                requestedByRow.style.display = '';
            } else {
                requestedByRow.style.display = 'none';
            }
        }
        set('csmDetailGivenOn', row.dataset.givenOn);
        set('csmDetailExpiration', row.dataset.expiration, 'No expiration on record');
        set('csmDetailApprovedBy', row.dataset.approvedBy);
        set('csmDetailSignature', row.dataset.signature, 'Not signed');
        set('csmDetailNotes', row.dataset.notes);

        const meta = statusMeta[row.dataset.status] || statusMeta.pending;
        const statusEl = document.getElementById('csmDetailStatus');
        if (statusEl) {
            statusEl.textContent = meta.label;
            statusEl.style.background = meta.bg;
            statusEl.style.color = meta.color;
        }

        const attachedEl = document.getElementById('csmDetailAttached');
        const attachedWrap = document.getElementById('csmDetailAttachedWrap');
        if (attachedEl && attachedWrap) {
            if (row.dataset.attachedFile) {
                attachedEl.textContent = row.dataset.attachedFile;
                attachedWrap.style.display = '';
            } else {
                attachedEl.textContent = 'No document attached.';
                attachedWrap.style.display = '';
            }
        }
    });
});
