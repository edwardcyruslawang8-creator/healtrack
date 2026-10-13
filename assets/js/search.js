/* =============================================================
   SEARCH PAGE JS (patient/search.php)
   ---------------------------------------------------------------
   Loaded only by patient/search.php, after base.js.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {

    /* ---------- Category filters: "All Categories" is mutually
       exclusive with the specific category checkboxes, and every
       checkbox auto-applies immediately — no separate "Apply Filters"
       button anymore. The mutual-exclusivity correction runs first
       (same listeners as before), THEN the form submits, so a stale
       checkbox state is never sent — submitting straight from an
       inline onchange= attribute instead would race this correction,
       since an inline handler fires before a DOMContentLoaded-deferred
       addEventListener one. ---------- */
    const allCheckbox = document.getElementById('cat-all');
    const otherCheckboxes = document.querySelectorAll(
        '.filter-checkbox-item input[type="checkbox"]:not(#cat-all)'
    );
    const filtersForm = document.getElementById('filtersForm');

    if (allCheckbox) {
        allCheckbox.addEventListener('change', function () {
            if (allCheckbox.checked) {
                otherCheckboxes.forEach(function (cb) { cb.checked = false; });
            }
            if (filtersForm) filtersForm.submit();
        });
    }

    otherCheckboxes.forEach(function (cb) {
        cb.addEventListener('change', function () {
            if (cb.checked && allCheckbox) {
                allCheckbox.checked = false;
            }
            if (filtersForm) filtersForm.submit();
        });
    });

});
