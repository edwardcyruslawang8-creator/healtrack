/* =============================================================
   CONSULTATION REPORTS JS (subadmin/consultation_reports.php)
   ---------------------------------------------------------------
   Renders every Chart.js instance on the page from the data PHP
   printed into window.CONSULTATION_REPORTS_DATA (see the inline
   <script> in subadmin/consultation_reports.php) — same pattern as
   assets/js/analytics_reports.js / assets/js/stock_request_reports.js.
   The mobile sidebar toggle lives in assets/js/admin-sidebar-toggle.js
   instead, since it's shared by every admin/subadmin page, not just
   this one.

   Wrapped in window.initReportPageCharts() rather than run inline, so
   assets/js/ajax-report-nav.js's whole-page smooth content swap can
   re-invoke it after replacing <main> — same convention every sibling
   report page's own JS file follows. Previously-created Chart
   instances are destroyed first, since the swap leaves their old
   <canvas> elements orphaned.
   ============================================================= */
var __consultationReportsCharts = []; // var, not let: ajax-tab-nav.js re-injects this file on each tab visit

function initReportPageCharts() {
    __consultationReportsCharts.forEach(function (c) { c.destroy(); });
    __consultationReportsCharts = [];

    // This page's charts only: assets/js/ajax-tab-nav.js re-fires
    // DOMContentLoaded after every tab swap, and every report page's
    // init stays registered — without this, e.g. the Overview's would
    // draw into Consultation Reports' same-id consultationsByTypeChart
    // and make this page's own init fail with "Canvas is already in use".
    if (!document.getElementById('consultationsTrendChart')) return;

    const data = window.CONSULTATION_REPORTS_DATA;
    if (!data || typeof Chart === 'undefined') return;

    const rootStyle = getComputedStyle(document.documentElement);
    const gridColor = rootStyle.getPropertyValue('--adm-border').trim() || '#ECEDF3';
    const tickColor = rootStyle.getPropertyValue('--adm-text-muted').trim() || '#6B7280';

    /* ---------- Reusable doughnut renderer (type + status) ---------- */
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
        __consultationReportsCharts.push(chart);
    }

    renderDoughnut('consultationsByTypeChart', data.consultationsByType);
    renderDoughnut('consultationsByStatusChart', data.consultationsByStatus);

    /* ---------- Consultations Trend (smooth line, gradient fill) ---------- */
    const trendCanvas = document.getElementById('consultationsTrendChart');
    if (trendCanvas) {
        const ctx = trendCanvas.getContext('2d');
        // A flat fill color would look heavy under a whole line this tall —
        // a top-to-bottom gradient fading to transparent reads as a much
        // lighter "area under the curve" instead, matching the reference.
        const gradient = ctx.createLinearGradient(0, 0, 0, trendCanvas.clientHeight || 220);
        gradient.addColorStop(0, 'rgba(26, 115, 232, 0.25)');
        gradient.addColorStop(1, 'rgba(26, 115, 232, 0)');

        const trendChart = new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: data.consultationsTrend.labels,
                datasets: [{
                    label: 'Consultations',
                    data: data.consultationsTrend.values,
                    borderColor: '#1A73E8',
                    backgroundColor: gradient,
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#1A73E8',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 700, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: false },
                    tooltip: { mode: 'index', intersect: false },
                },
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: 100,
                        ticks: { color: tickColor, stepSize: 20 },
                        grid: { color: gridColor },
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: tickColor },
                    },
                },
            },
        });
        __consultationReportsCharts.push(trendChart);
    }
}

window.initReportPageCharts = initReportPageCharts;
document.addEventListener('DOMContentLoaded', initReportPageCharts);
