/* =============================================================
   PATIENT REPORTS JS (subadmin/patient_reports.php)
   ---------------------------------------------------------------
   Renders every Chart.js instance on the page from the data PHP
   printed into window.PATIENT_REPORTS_DATA (see the inline <script>
   in subadmin/patient_reports.php) — same pattern as
   assets/js/analytics_reports.js. The mobile sidebar toggle lives in
   assets/js/admin-sidebar-toggle.js instead, since it's shared by
   every admin/subadmin page, not just this one.

   Wrapped in window.initReportPageCharts() rather than run inline, so
   assets/js/ajax-report-nav.js's whole-page smooth content swap can
   re-invoke it after replacing <main> — same convention every sibling
   report page's own JS file follows. Previously-created Chart
   instances are destroyed first, since the swap leaves their old
   <canvas> elements orphaned.
   ============================================================= */
var __patientReportsCharts = []; // var, not let: ajax-tab-nav.js re-injects this file on each tab visit

function initReportPageCharts() {
    __patientReportsCharts.forEach(function (c) { c.destroy(); });
    __patientReportsCharts = [];

    // This page's charts only: assets/js/ajax-tab-nav.js re-fires
    // DOMContentLoaded after every tab swap, and every report page's
    // init stays registered — without this, e.g. the Overview's would
    // draw into Consultation Reports' same-id consultationsByTypeChart
    // and make this page's own init fail with "Canvas is already in use".
    if (!document.getElementById('patientsByAgeGroupChart')) return;

    const data = window.PATIENT_REPORTS_DATA;
    if (!data || typeof Chart === 'undefined') return;

    /* ---------- Reusable doughnut renderer (age group + gender) ---------- */
    function renderDoughnut(canvasId, dataset) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        const chart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: dataset.labels,
                datasets: [{
                    data: dataset.values,
                    backgroundColor: dataset.colors,
                    borderWidth: 0,
                    hoverOffset: 4,
                }],
            },
            options: {
                cutout: '68%',
                plugins: {
                    legend: { display: false }, // custom legend rendered in HTML instead
                    tooltip: {
                        callbacks: {
                            label: function (ctx) { return ctx.label + ': ' + ctx.parsed; },
                        },
                    },
                },
            },
        });
        __patientReportsCharts.push(chart);
    }

    renderDoughnut('patientsByAgeGroupChart', data.patientsByAgeGroup);
    renderDoughnut('patientsByGenderChart', data.patientsByGender);

    // Patients by Barangay (Top 5) is rendered as a CSS ranked-bar list
    // (.sad-condition-list.arp-ranked-list — the same widget Overview
    // uses for Top 5 Medicines Dispensed / Top 5 Diagnoses), not a
    // Chart.js canvas, so there's no chart to initialize here for it.

    // "New Patient Registrations" card — see
    // assets/js/new-patient-registrations.js.
    if (typeof window.initNewPatientRegistrations === 'function') {
        window.initNewPatientRegistrations();
    }
}

window.initReportPageCharts = initReportPageCharts;
document.addEventListener('DOMContentLoaded', initReportPageCharts);
