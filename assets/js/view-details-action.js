/* =============================================================
   GENERIC "VIEW DETAILS" MODAL TRIGGER
   (includes/shared/view_details_modal.php)
   ---------------------------------------------------------------
   Delegated click on any ".vdm-trigger" element — reads that row's
   data-vdm-title/data-vdm-fields (see that modal partial's own header
   comment for the exact shape) and renders them into the one shared
   #viewDetailsModal. Delegated rather than bound per-row so it keeps
   working after any AJAX row-swap (same reasoning as
   assets/js/medicine_vaccine_inventory_pharmacist.js's Edit/View
   Details handlers).
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('viewDetailsModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalEl);
    const titleEl = document.getElementById('viewDetailsModalLabel');
    const fieldsEl = document.getElementById('vdmFields');

    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('.vdm-trigger');
        if (!trigger) return;
        e.preventDefault();

        titleEl.textContent = trigger.dataset.vdmTitle || 'Details';

        fieldsEl.innerHTML = '';
        let fields = {};
        try {
            fields = JSON.parse(trigger.dataset.vdmFields || '{}');
        } catch (err) {
            fields = {};
        }

        Object.keys(fields).forEach(function (label) {
            const value = fields[label];
            if (value === null || value === undefined || value === '') return;

            const row = document.createElement('div');
            row.className = 'vdm-detail-row';
            const dt = document.createElement('dt');
            dt.textContent = label;
            const dd = document.createElement('dd');
            dd.textContent = value;
            row.appendChild(dt);
            row.appendChild(dd);
            fieldsEl.appendChild(row);
        });

        if (!fieldsEl.children.length) {
            const row = document.createElement('div');
            row.className = 'vdm-detail-row';
            const dd = document.createElement('dd');
            dd.style.textAlign = 'left';
            dd.textContent = 'No additional details recorded.';
            row.appendChild(dd);
            fieldsEl.appendChild(row);
        }

        modal.show();
    });
});
