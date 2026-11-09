/* =============================================================
   CONSULTATION DETAILS MODAL (doctor / medical-staff / subadmin
   history.php) — "View Details" on a Consultation History row shows
   the complete consultation. The row carries its own server-rendered
   details in a <template class="his-cons-details"> right after the
   button (includes/shared/_history_record_table.php), so this works
   after an AJAX filter/pagination refresh or a tab switch too.
   Delegated on document; the modal is looked up at click time.
   ============================================================= */
(function () {
    if (window.__hcConsDetailsBound) return; // ajax-tab-nav may re-run this file
    window.__hcConsDetailsBound = true;

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.his-cons-trigger');
        if (!btn) return;
        e.preventDefault();

        const modalEl = document.getElementById('consultationDetailsModal');
        const tpl = btn.parentElement && btn.parentElement.querySelector('template.his-cons-details');
        if (!modalEl || !tpl || typeof bootstrap === 'undefined') return;

        document.getElementById('consultationDetailsModalLabel').textContent = btn.dataset.consTitle || 'Consultation';
        const body = document.getElementById('consultationDetailsBody');
        body.textContent = '';
        body.appendChild(tpl.content.cloneNode(true));
        if (!body.querySelector('.cdv-section')) {
            const p = document.createElement('p');
            p.className = 'cdv-empty';
            p.textContent = 'No details were recorded for this consultation.';
            body.appendChild(p);
        }
        body.scrollTop = 0;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
})();
