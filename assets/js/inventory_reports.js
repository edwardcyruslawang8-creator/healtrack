/* =============================================================
   INVENTORY REPORTS JS (subadmin/inventory_reports.php)
   ---------------------------------------------------------------
   Renders every Chart.js instance on the page from the data PHP
   printed into window.INVENTORY_REPORTS_DATA (see the inline <script>
   in subadmin/inventory_reports.php) — same pattern as
   assets/js/analytics_reports.js / assets/js/patient_reports.js. The
   mobile sidebar toggle lives in assets/js/admin-sidebar-toggle.js
   instead, since it's shared by every admin/subadmin page, not just
   this one.

   Wrapped in window.initReportPageCharts() rather than run inline, so
   assets/js/ajax-report-nav.js's whole-page smooth content swap can
   re-invoke it after replacing <main> — same convention every sibling
   report page's own JS file follows. Previously-created Chart
   instances are destroyed first, since the swap leaves their old
   <canvas> elements orphaned.
   ============================================================= */
let __inventoryReportsCharts = [];

function initReportPageCharts() {
    __inventoryReportsCharts.forEach(function (c) { c.destroy(); });
    __inventoryReportsCharts = [];

    const data = window.INVENTORY_REPORTS_DATA;
    if (!data || typeof Chart === 'undefined') return;

    /* ---------- Reusable doughnut renderer (status overview + by category) ---------- */
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
        __inventoryReportsCharts.push(chart);
    }

    renderDoughnut('inventoryStatusOverviewChart', data.inventoryStatusOverview);
    renderDoughnut('inventoryByCategoryChart', data.inventoryByCategory);
}

window.initReportPageCharts = initReportPageCharts;
document.addEventListener('DOMContentLoaded', initReportPageCharts);
