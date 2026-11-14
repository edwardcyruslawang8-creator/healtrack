/* =============================================================
   ALLERGY DETAILS MODAL (doctor / medical-staff / subadmin allergies.php)
   ---------------------------------------------------------------
   The Allergies tab's View (eye) action opens the allergy's details in a
   modal instead of navigating to allergy_details.php. The modal shows
   that page's own Overview, Detailed Information and History sections —
   fetched from allergy_details.php itself and dropped in as-is — so the
   page and the modal can never show different information, and there's
   no second copy of that markup to maintain. Styles come from
   assets/css/allergy_details.css (loaded on the Allergies pages too).

   Ctrl/Cmd/Shift/middle-click still opens the full page in a new tab,
   and if the fetch fails the browser just goes to the page as before.
   ============================================================= */
(function () {
    if (window.HCAllergyDetailsModal) return; // registered once, even across AJAX tab swaps
    window.HCAllergyDetailsModal = true;

    const SECTIONS = ['.ald-overview-card', '.ald-detail-card', '.ald-history-card'];
    let modal = null;
    let modalEl = null;
    let requestId = 0;

    function buildModal() {
        modalEl = document.createElement('div');
        modalEl.className = 'modal fade ald-modal';
        modalEl.id = 'allergyDetailsModal';
        modalEl.tabIndex = -1;
        modalEl.setAttribute('aria-labelledby', 'allergyDetailsModalLabel');
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.innerHTML =
            '<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">' +
              '<div class="modal-content">' +
                '<div class="modal-header">' +
                  '<h5 class="modal-title" id="allergyDetailsModalLabel">Allergy Details</h5>' +
                  '<a class="ald-modal-fullpage" href="#" title="Open as full page"><i class="bi bi-box-arrow-up-right"></i> Full page</a>' +
                  '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
                '</div>' +
                '<div class="modal-body"></div>' +
              '</div>' +
            '</div>';
        document.body.appendChild(modalEl);
        modal = new bootstrap.Modal(modalEl);
    }

    function setBody(html) {
        modalEl.querySelector('.modal-body').innerHTML = html;
    }

    function open(href) {
        if (!modal) buildModal();
        const thisRequest = ++requestId;
        modalEl.querySelector('.modal-title').textContent = 'Allergy Details';
        modalEl.querySelector('.ald-modal-fullpage').href = href;
        setBody('<div class="ald-modal-loading"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading allergy details…</div>');
        modal.show();

        fetch(href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.text();
            })
            .then(function (html) {
                if (thisRequest !== requestId) return; // another row was opened meanwhile
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const parts = SECTIONS.map(function (sel) { return doc.querySelector(sel); }).filter(Boolean);
                if (parts.length === 0) throw new Error('No allergy details in response');

                const body = modalEl.querySelector('.modal-body');
                body.innerHTML = '';
                parts.forEach(function (part) { body.appendChild(document.importNode(part, true)); });

                const name = doc.querySelector('.ald-overview-name-row h2, .ald-overview-card h2');
                if (name && name.textContent.trim() !== '') {
                    modalEl.querySelector('.modal-title').textContent = 'Allergy Details — ' + name.textContent.trim();
                }
            })
            .catch(function () {
                if (thisRequest !== requestId) return;
                window.location.href = href; // couldn't load it here — open the page as before
            });
    }

    document.addEventListener('click', function (e) {
        const link = e.target.closest('a[href*="allergy_details.php"]');
        if (!link || link.closest('.ald-modal')) return;
        // Let the browser handle "open in new tab/window" clicks.
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button !== 0) return;
        if (typeof bootstrap === 'undefined' || typeof fetch === 'undefined' || typeof DOMParser === 'undefined') return;
        e.preventDefault();
        open(link.href);
    });
})();
