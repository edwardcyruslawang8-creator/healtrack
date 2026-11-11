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

   Optional per-input settings (all off by default, so existing date
   fields behave exactly as before):
     data-hc-alt-format="m/d/Y"   visible format (default "M j, Y")
     data-hc-alt-class="…"        class(es) for the visible box instead
                                  of flatpickr's default "form-control input"
     data-hc-allow-input          the visible box can be typed in (in the
                                  visible format) and cleared by deleting
                                  its text — e.g. a filter that can be reset
     data-hc-range-start="key"    pairs a Start/End date: the End can't be
     data-hc-range-end="key"      before the Start, nor the Start after the
                                  End (same "key" on both inputs). Every
                                  From-To range in the app uses this, and a
                                  range input also defaults to the MM/DD/YYYY
                                  format + placeholder and allows typing or
                                  clearing (data-hc-alt-format / placeholder
                                  still override). Inside .hc-daterange it's
                                  shown as the standard two-box range
                                  (assets/css/hc-datepicker.css).
   The input's own placeholder attribute is shown in the visible box.
   ============================================================= */
(function () {
    // Start/End pairs waiting to be linked, by data-hc-range-* key.
    const ranges = {};

    function linkRange(key) {
        const pair = ranges[key];
        if (!pair || !pair.start || !pair.end) return;
        const start = pair.start;
        const end = pair.end;
        const startMax = start.input.getAttribute('max') || null; // the inputs' own limits still apply
        const endMin = end.input.getAttribute('min') || null;

        function apply() {
            end.fp.set('minDate', start.fp.selectedDates[0] || endMin);
            start.fp.set('maxDate', end.fp.selectedDates[0] || startMax);
        }
        start.fp.config.onChange.push(apply);
        end.fp.config.onChange.push(apply);
        apply(); // dates already chosen when the page loaded (e.g. a submitted filter)

        // A form reset (e.g. a "Clear filters" button) empties the real
        // inputs but not flatpickr's own state — clear both calendars
        // quietly (no change event) and lift the Start/End limits again.
        const form = start.input.form;
        if (form && !form.dataset.hcRangeResetBound) {
            form.dataset.hcRangeResetBound = '1';
            form.addEventListener('reset', function () {
                setTimeout(function () {
                    Object.keys(ranges).forEach(function (k) {
                        const p = ranges[k];
                        if (!p.start || !p.end || p.start.input.form !== form) return;
                        [p.start, p.end].forEach(function (side) {
                            if (side.input.value === '') side.fp.clear(false);
                        });
                        p.end.fp.set('minDate', p.start.fp.selectedDates[0] || p.end.input.getAttribute('min') || null);
                        p.start.fp.set('maxDate', p.end.fp.selectedDates[0] || p.start.input.getAttribute('max') || null);
                    });
                }, 0);
            });
        }
    }

    function init() {
        if (typeof flatpickr === 'undefined') return; // library failed to load — native picker still works
        const touchedRanges = [];
        document.querySelectorAll('input[data-hc-datepicker]:not([data-hc-datepicker-ready])').forEach(function (input) {
            input.dataset.hcDatepickerReady = '1';
            const isRange = !!(input.dataset.hcRangeStart || input.dataset.hcRangeEnd);
            if (isRange && !input.getAttribute('placeholder')) input.setAttribute('placeholder', 'MM/DD/YYYY');
            const fp = flatpickr(input, {
                dateFormat: 'Y-m-d',       // the real value, same as a native date input
                altInput: true,            // the visible box
                altFormat: input.dataset.hcAltFormat || (isRange ? 'm/d/Y' : 'M j, Y'),
                // Inside the standard two-box range the visible box is
                // .hc-date-input; otherwise flatpickr's usual form-control.
                altInputClass: input.dataset.hcAltClass || (input.closest('.hc-date-field') ? 'hc-date-input' : 'form-control input'),
                allowInput: isRange || input.hasAttribute('data-hc-allow-input'),
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

            // Every visible box is tagged .hc-date-alt (flatpickr doesn't
            // give it flatpickr-input) — assets/css/hc-datepicker.css keys
            // its calendar icon / focus ring on that.
            if (fp.altInput) fp.altInput.classList.add('hc-date-alt');

            // Screen readers announce the visible box by the input's own label.
            if (fp.altInput && input.getAttribute('aria-label')) {
                fp.altInput.setAttribute('aria-label', input.getAttribute('aria-label'));
            }

            const startKey = input.dataset.hcRangeStart;
            const endKey = input.dataset.hcRangeEnd;
            if (startKey || endKey) {
                const key = startKey || endKey;
                ranges[key] = ranges[key] || {};
                ranges[key][startKey ? 'start' : 'end'] = { input: input, fp: fp };
                touchedRanges.push(key);
            }
        });
        Array.from(new Set(touchedRanges)).forEach(linkRange);
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
