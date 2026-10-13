/* =============================================================
   MEDICATION DETAILS MODAL JS
   ---------------------------------------------------------------
   Shared by doctor/medications.php, medical-staff/medications.php,
   and subadmin/medications.php. Each row's "View Details" button
   (.med-view-details) opens #medDetailsModal
   (includes/shared/medication_details_modal.php), populated purely
   from that row's own data-med-* attributes — no network request,
   since the page already rendered every field server-side.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('medDetailsModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;
    const modal = new bootstrap.Modal(modalEl);

    function setText(id, value) {
        document.getElementById(id).textContent = value && value.trim() !== '' ? value : '—';
    }

    document.querySelectorAll('.med-view-details').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const row = btn.closest('tr');
            if (!row) return;
            const d = row.dataset;

            setText('mdvName', d.medName);
            setText('mdvCategory', d.medCategory);
            setText('mdvDosage', d.medDosage);
            setText('mdvFrequency', d.medFrequency);
            setText('mdvRoute', d.medRoute);
            setText('mdvIndication', d.medIndication);
            setText('mdvStartDate', d.medStartDate);
            setText('mdvEndDate', d.medEndDate ? d.medEndDate + (d.medEndDateNote ? ' (' + d.medEndDateNote + ')' : '') : '');
            setText('mdvPrescribedBy', d.medPrescribedBy);
            setText('mdvStatus', d.medStatus);
            setText('mdvNotes', d.medNotes);

            modal.show();
        });
    });
});
