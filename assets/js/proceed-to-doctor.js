/* =============================================================
   PROCEED TO DOCTOR (Medical Technologist's result-entry forms)
   ---------------------------------------------------------------
   Shared by serology_rapid_test_form.php, urinalysis_form.php,
   cd4_count_form.php, gram_stain_form.php, lab_result_form.php, and
   xray_result_form.php — all live in medical_technician/, same depth
   as every other page that already uses a relative "../api/..." path
   (see assets/js/two-fa.js), so one shared script works for all of
   them without needing BASE_URL.

   #btnProceedToDoctor only exists in the page's own markup when this
   patient has at least one completed result for that test AND it
   hasn't already been routed (see each *_form_firestore.php's
   $routedToDoctorName) — so this script no-ops entirely once routed.
   Reads which test/patient from the button's own data attributes
   rather than near-identical copies of this file. recordId is only
   set by the two generic forms (lab_result_form.php/xray_result_form.php)
   — the 4 named forms search by patientId+testName instead, since a
   patient can only ever have one pending order for a given named test
   at a time (see api/proceed-lab-result-to-doctor.php's per-testType
   lookup).
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btnProceedToDoctor');
    if (!btn) return;

    const patientId = btn.dataset.patientId;
    const testType = btn.dataset.testType;
    const recordId = btn.dataset.recordId;
    const defaultHtml = btn.innerHTML;

    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Routing…';

        fetch('../api/proceed-lab-result-to-doctor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ patientId: patientId, testType: testType, recordId: recordId }),
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.error || 'Something went wrong.');
                }
                window.location.reload();
            })
            .catch(function (err) {
                btn.disabled = false;
                btn.innerHTML = defaultHtml;
                HCDialog.alert(err.message || 'Something went wrong. Please try again.');
            });
    });
});
