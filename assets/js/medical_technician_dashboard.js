/* =============================================================
   MEDICAL TECHNICIAN DASHBOARD JS (medical_technician/dashboard.php)
   ---------------------------------------------------------------
   Renders every Chart.js instance on the page from the data PHP
   printed into window.MEDICAL_TECHNICIAN_DASHBOARD_DATA (see the
   inline <script> in medical_technician/dashboard.php). The mobile
   sidebar toggle lives in assets/js/admin-sidebar-toggle.js instead,
   since it's shared by every admin/subadmin/medical-staff/pharmacist/
   medical_technician page, not just this one. Structurally identical
   to assets/js/pharmacist_dashboard.js (same chart types, different
   data keys) — kept separate rather than shared since the two
   dashboards' data shapes (and any future chart tweaks) belong to
   different roles.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const data = window.MEDICAL_TECHNICIAN_DASHBOARD_DATA;
    if (!data || typeof Chart === 'undefined') return;

    // Read the current theme's actual grid/tick colors instead of
    // hardcoding light-mode values (see subadmin_dashboard.js for the
    // same pattern).
    const rootStyle = getComputedStyle(document.documentElement);
    const gridColor = rootStyle.getPropertyValue('--adm-border').trim() || '#ECEDF3';
    const tickColor = rootStyle.getPropertyValue('--adm-text-muted').trim() || '#6B7280';

    // Exposes the 2 range-filterable charts (Tests Processed Trend,
    // Tests by Type) so assets/js/dashboard-range-filters.js can
    // live-update them in place (chart.data = ...; chart.update())
    // instead of a full page reload when their dropdown filter changes.
    window.HC_DASHBOARD_CHARTS = window.HC_DASHBOARD_CHARTS || {};

    /* ---------- Tests Processed Trend (line) ---------- */
    const trendCanvas = document.getElementById('testsTrendChart');
    if (trendCanvas) {
        window.HC_DASHBOARD_CHARTS.testsTrend = new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: data.testsTrend.labels,
                datasets: [{
                    data: data.testsTrend.values,
                    borderColor: '#1A73E8',
                    backgroundColor: 'rgba(26, 115, 232, 0.12)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: '#1A73E8',
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
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor } },
                    x: { grid: { display: false }, ticks: { color: tickColor } },
                },
            },
        });
    }

    /* ---------- Tests by Type (bar) ---------- */
    const typeCanvas = document.getElementById('testsByTypeChart');
    if (typeCanvas) {
        window.HC_DASHBOARD_CHARTS.testsByType = new Chart(typeCanvas, {
            type: 'bar',
            data: {
                labels: data.testsByType.labels,
                datasets: [{
                    data: data.testsByType.values,
                    backgroundColor: '#1A73E8',
                    borderRadius: 6,
                    maxBarThickness: 34,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 700, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { title: function (items) { return items[0].label; } } },
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor } },
                    x: { grid: { display: false }, ticks: { color: tickColor } },
                },
            },
        });
    }

    /* ---------- Reusable doughnut renderer (result status + review status) ---------- */
    function renderDoughnut(canvasId, dataset) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: dataset.labels,
                datasets: [{
                    data: dataset.values,
                    backgroundColor: dataset.colors,
                    borderWidth: 0,
                    hoverOffset: 6,
                }],
            },
            options: {
                cutout: '68%',
                animation: { duration: 700, easing: 'easeOutQuart' },
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
    }

    renderDoughnut('resultStatusChart', data.resultStatus);
    renderDoughnut('requestTypeChart', data.requestType);
});
