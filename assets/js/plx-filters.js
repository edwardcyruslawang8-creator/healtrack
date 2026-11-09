/* =============================================================
   LABORATORY & X-RAY TAB FILTERS (doctor / medical-staff / subadmin
   patient_laboratory_xray.php)
   ---------------------------------------------------------------
   Each filter control carries data-plx-param (labType / labStatus /
   labQ / xrayPart / xrayStatus / xrayQ). Changing a dropdown, or
   pressing Enter in a search box, reloads the page with that value in
   the URL — every other param (patient id, the other section's
   filters, date ranges) is kept, and that section's page number is
   reset to 1. The filtering itself happens server-side
   (includes/shared/patient_lab_xray_filters.php). Delegated on
   document so it also works after a Patient Profile tab switch.
   ============================================================= */
(function () {
    if (window.__hcPlxFiltersBound) return;
    window.__hcPlxFiltersBound = true;

    const DEFAULTS = { labType: 'All Test Types', labStatus: 'All Status', xrayPart: 'All Body Part', xrayStatus: 'All Status' };

    function apply(el) {
        const param = el.dataset.plxParam;
        const value = el.value.trim();
        const url = new URL(window.location.href);
        if (value === '' || value === DEFAULTS[param]) {
            url.searchParams.delete(param);
        } else {
            url.searchParams.set(param, value);
        }
        // Back to the first page of the section being filtered.
        url.searchParams.delete(param.indexOf('lab') === 0 ? 'labPage' : 'xrayPage');
        window.location.href = url.toString();
    }

    document.addEventListener('change', function (e) {
        const el = e.target.closest('select[data-plx-param]');
        if (el) apply(el);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        const el = e.target.closest('input[data-plx-param]');
        if (!el) return;
        e.preventDefault();
        apply(el);
    });
})();
