/* =============================================================
   HC DATEPICKER (shared) — styled replacement for the browser's own
   <input type="date"> calendar popup, which can't be recolored (it
   shows the browser's blue). Any input[data-hc-datepicker] gets a
   flatpickr calendar themed in HEALTrack maroon
   (assets/css/hc-datepicker.css).

   The ORIGINAL input keeps its value in YYYY-MM-DD — exactly what a
   native date input gives — so every existing script that reads
   input.value (validation, saving, printing) works unchanged. What the
   user sees is a separate, friendlier text box ("Nov 16, 2026").
   min/max attributes on the input are honoured (e.g. no past dates).
   Requires flatpickr (loaded from cdnjs on the page).
   ============================================================= */
(function () {
    function init() {
        if (typeof flatpickr === 'undefined') return; // library failed to load — native picker still works
        document.querySelectorAll('input[data-hc-datepicker]:not([data-hc-datepicker-ready])').forEach(function (input) {
            input.dataset.hcDatepickerReady = '1';
            flatpickr(input, {
                dateFormat: 'Y-m-d',       // the real value, same as a native date input
                altInput: true,            // the visible box
                altFormat: 'M j, Y',
                minDate: input.getAttribute('min') || null,
                maxDate: input.getAttribute('max') || null,
                defaultDate: input.value || null,
                disableMobile: true,       // same maroon calendar on phones too
                monthSelectorType: 'static',
                prevArrow: '<i class="bi bi-chevron-left"></i>',
                nextArrow: '<i class="bi bi-chevron-right"></i>',
                onChange: function () {
                    // Let page scripts react the same way they would to a native input.
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                },
            });
        });
    }

    window.HCDatepicker = { init: init };
    // Registered unconditionally (not just when readyState === 'loading')
    // and left attached — assets/js/ajax-tab-nav.js/ajax-report-nav.js
    // dispatch a SYNTHETIC 'DOMContentLoaded' every time they swap in a
    // new page fragment via AJAX, specifically so every page script
    // written this way (this one included) re-runs and picks up that
    // fragment's own date inputs, not just the ones present on first
    // real page load.
    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') {
        init(); // DOMContentLoaded already fired before this script ran
    }
})();
