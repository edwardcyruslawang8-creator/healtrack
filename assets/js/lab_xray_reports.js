/* =============================================================
   LAB & X-RAY REPORTS JS (subadmin/lab_xray_reports.php)
   ---------------------------------------------------------------
   Renders every Chart.js instance on the page from the data PHP
   printed into window.LAB_XRAY_REPORTS_DATA (see the inline <script>
   in subadmin/lab_xray_reports.php) — same pattern as
   assets/js/vaccine_reports.js.

   Wrapped in window.initReportPageCharts() rather than run inline, so
   assets/js/ajax-report-nav.js's whole-page smooth content swap can
   re-invoke it after replacing <main> — same convention every sibling
   report page's own JS file follows. Previously-created Chart
   instances are destroyed first, since the swap leaves their old
   <canvas> elements orphaned.
   ============================================================= */
var __labXrayReportsCharts = []; // var, not let: ajax-tab-nav.js re-injects this file on each tab visit

function initReportPageCharts() {
    __labXrayReportsCharts.forEach(function (c) { c.destroy(); });
    __labXrayReportsCharts = [];

    // This page's charts only — see vaccine_reports.js for why.
    if (!document.getElementById('labXrayTrendChart')) return;

    const data = window.LAB_XRAY_REPORTS_DATA;
    if (!data || typeof Chart === 'undefined') return;

    const rootStyle = getComputedStyle(document.documentElement);
    const gridColor = rootStyle.getPropertyValue('--adm-border').trim() || '#ECEDF3';
    const tickColor = rootStyle.getPropertyValue('--adm-text-muted').trim() || '#6B7280';

    /* ---------- Requests Trend (two lines: Laboratory, X-ray) ---------- */
    const trendCanvas = document.getElementById('labXrayTrendChart');
    if (trendCanvas) {
        const line = function (label, values, color) {
            return {
                label: label,
                data: values,
                borderColor: color,
                backgroundColor: color,
                borderWidth: 2.5,
                tension: 0.4,
                pointRadius: 3,
                pointBackgroundColor: color,
            };
        };
        __labXrayReportsCharts.push(new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: data.trend.labels,
                datasets: [
                    line('Laboratory', data.trend.lab, '#7C3AED'),
                    line('X-ray', data.trend.xray, '#1A73E8'),
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 } },
                    x: { grid: { display: false }, ticks: { color: tickColor, autoSkip: true, maxTicksLimit: 7 } },
                },
            },
        }));
    }

    /* ---------- Requests by Type (doughnut) ---------- */
    const typeCanvas = document.getElementById('requestsByTypeChart');
    if (typeCanvas) {
        __labXrayReportsCharts.push(new Chart(typeCanvas, {
            type: 'doughnut',
            data: {
                labels: data.requestsByType.labels,
                datasets: [{
                    data: data.requestsByType.values,
                    backgroundColor: data.requestsByType.colors,
                    borderWidth: 0,
                    hoverOffset: 4,
                }],
            },
            options: {
                cutout: '68%',
                plugins: {
                    legend: { display: false }, // custom legend rendered in HTML instead
                    tooltip: { callbacks: { label: function (ctx) { return ctx.label + ': ' + ctx.parsed; } } },
                },
            },
        }));
    }

    /* ---------- Completion Status by Type (grouped bar) ---------- */
    const statusCanvas = document.getElementById('statusByTypeChart');
    if (statusCanvas) {
        __labXrayReportsCharts.push(new Chart(statusCanvas, {
            type: 'bar',
            data: {
                labels: data.statusByType.labels,
                datasets: [
                    { label: 'Completed', data: data.statusByType.completed, backgroundColor: '#1E8E3E', borderRadius: 6, maxBarThickness: 34 },
                    { label: 'Pending',   data: data.statusByType.pending,   backgroundColor: '#F59E0B', borderRadius: 6, maxBarThickness: 34 },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 } },
                    x: { grid: { display: false }, ticks: { color: tickColor } },
                },
            },
        }));
    }
}

window.initReportPageCharts = initReportPageCharts;
document.addEventListener('DOMContentLoaded', initReportPageCharts);
