/* =============================================================
   MEDICINE & VACCINE AVAILABILITY PAGE JS (patient/medicine.php)
   ---------------------------------------------------------------
   Loaded only by patient/medicine.php, after base.js. The Medicines/
   Vaccines tabs and filters work via normal page navigation (?tab=...
   and GET form submits), which keeps things simple and fully
   functional without JS. The filter/sort dropdowns' styled lists come
   from assets/js/styled-select.js (each <select data-styled-select>).
   The Medicines tab's sort select (id="medicineSortSelect") submits
   its form="medicineFilterToolbar" on change, same pattern
   assets/js/search.js already uses for search.php's own sort select —
   real sort logic lives in includes/public/medicine-data.php.

   One real piece of interactivity: the top search box is shared by
   BOTH tabs (assets/js/quick-search.js, ?scope=medicines — which
   covers real medicines AND real vaccines, see
   api/quick-search-patient.php's own comment on that). Clicking a
   suggested vaccine while sitting on the Medicines tab would otherwise
   search for it within medicines-only results and find nothing — so
   this listens for quick-search.js's 'quick-search:fill' event (fired
   with the exact clicked item, including its real "type") and flips
   the search form's own hidden ?tab= field to match BEFORE the form
   submits, landing on the tab that actually has that item.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {

    const searchInput = document.querySelector('.search-page-bar [data-quick-search]');
    const tabField = document.querySelector('.search-page-bar input[name="tab"]');
    if (searchInput && tabField) {
        searchInput.addEventListener('quick-search:fill', function (e) {
            const item = e.detail;
            if (item && item.type) {
                tabField.value = item.type === 'vaccine' ? 'vaccines' : 'medicines';
            }
        });
    }

    const sortSelect = document.getElementById('medicineSortSelect');
    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            const form = document.getElementById(sortSelect.getAttribute('form'));
            if (form) form.submit();
        });
    }

});
