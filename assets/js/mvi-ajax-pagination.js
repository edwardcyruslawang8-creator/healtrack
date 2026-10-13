/* =============================================================
   MEDICINE & VACCINE INVENTORY — AJAX PAGINATION
   (subadmin/medicine_vaccine_inventory.php only)
   ---------------------------------------------------------------
   Intercepts a click on any real Previous/page-number/Next link inside
   .mvi-ajax-pagination (a plain <span class="page-btn disabled"> isn't
   a link, so a disabled edge is naturally never handled here) and
   fetches just that tab's table+pagination HTML from
   api/get-medicine-inventory-table.php instead of letting the browser
   do a full page navigation — only the .mvi-table-card swaps, with a
   short crossfade (assets/css/medicine_vaccine_inventory.css's
   .mvi-table-fade-out/-in) instead of a hard reload flash.

   The clicked link's own href already carries every current filter
   (q/category/status/page or vq/vcategory/vstatus/vpage — see
   includes/subadmin/medicine_vaccine_inventory_firestore.php's
   hc_mvi_medicines_query()/hc_mvi_vaccines_query()), so this just reuses
   that querystring verbatim against the AJAX endpoint, plus a `tab`
   param telling it which of the two partials to render.

   history.replaceState keeps the visible URL (and a bookmark/refresh)
   in sync with whichever page is currently showing, without adding a
   new browser-history entry per page flip (Back would otherwise have to
   be clicked once per page turned, which reads as broken, not "smooth").

   Delegated on document (not bound per-link) since the pagination nav
   itself gets replaced by every successful fetch — the same reason
   assets/js/medicine_vaccine_inventory_pharmacist.js's row-action
   listeners were converted to delegation too.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const CARD_ID_BY_TAB = { medicines: 'mvi-medicines-table-card', vaccines: 'mvi-vaccines-table-card' };

    document.addEventListener('click', function (e) {
        const link = e.target.closest('.mvi-ajax-pagination a.page-btn');
        if (!link) return;
        e.preventDefault();

        const nav = link.closest('.mvi-ajax-pagination');
        const tab = nav ? nav.dataset.mviTab : 'medicines';
        const cardId = CARD_ID_BY_TAB[tab] || CARD_ID_BY_TAB.medicines;
        const cardEl = document.getElementById(cardId);
        if (!cardEl) return;

        const targetUrl = new URL(link.href, window.location.href); // link.href is already absolute; new URL() here just gives easy access to .search/.pathname
        const ajaxParams = new URLSearchParams(targetUrl.search);
        ajaxParams.set('tab', tab);

        cardEl.classList.add('mvi-table-fade-out');

        fetch('../api/get-medicine-inventory-table.php?' + ajaxParams.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (res) {
                if (!res.ok) throw new Error('Request failed');
                return res.text();
            })
            .then(function (html) {
                const wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                const newCard = wrapper.firstElementChild;
                if (!newCard) return;

                newCard.classList.add('mvi-table-fade-in');
                cardEl.replaceWith(newCard);

                // One frame later so the browser actually registers the
                // opacity:0 starting point before animating away from it
                // — setting both in the same tick would just skip straight
                // to the end state with no visible transition at all.
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        newCard.classList.remove('mvi-table-fade-in');
                    });
                });

                history.replaceState(null, '', targetUrl.pathname + targetUrl.search);
                newCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            })
            .catch(function () {
                // A real navigation is still a correct (if less smooth)
                // fallback if the AJAX request itself failed for any reason.
                window.location.href = link.href;
            })
            .finally(function () {
                cardEl.classList.remove('mvi-table-fade-out');
            });
    });
});
