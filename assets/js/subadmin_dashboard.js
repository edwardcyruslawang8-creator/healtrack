/* =============================================================
   SUB-ADMIN DASHBOARD JS (subadmin/dashboard.php)
   ---------------------------------------------------------------
   Renders every Chart.js instance on the page from the data PHP
   printed into window.SUBADMIN_DASHBOARD_DATA (see the inline
   <script> in subadmin/dashboard.php). The mobile sidebar toggle
   lives in assets/js/admin-sidebar-toggle.js instead, since it's
   shared by every admin/subadmin page, not just this one.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const data = window.SUBADMIN_DASHBOARD_DATA;
    if (!data || typeof Chart === 'undefined') return;

    // Read the current theme's actual grid/tick colors instead of
    // hardcoding light-mode values (see account_monitoring.js /
    // facility_analytics.js for the same pattern).
    const rootStyle = getComputedStyle(document.documentElement);
    const gridColor = rootStyle.getPropertyValue('--adm-border').trim() || '#ECEDF3';
    const tickColor = rootStyle.getPropertyValue('--adm-text-muted').trim() || '#6B7280';

    // Exposes the 2 range-filterable charts (Consultations Trend,
    // Patients by Age Group) so assets/js/dashboard-range-filters.js can
    // live-update them in place (chart.data = ...; chart.update())
    // instead of a full page reload when their dropdown filter changes.
    window.HC_DASHBOARD_CHARTS = window.HC_DASHBOARD_CHARTS || {};

    /* ---------- Consultations Trend (line) ---------- */
    const trendCanvas = document.getElementById('consultationsTrendChart');
    if (trendCanvas) {
        window.HC_DASHBOARD_CHARTS.consultationsTrend = new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: data.consultationsTrend.labels,
                datasets: [{
                    data: data.consultationsTrend.values,
                    borderColor: '#D93025',
                    backgroundColor: 'rgba(217, 48, 37, 0.12)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: '#D93025',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 } },
                    x: { grid: { display: false }, ticks: { color: tickColor, autoSkip: true, maxTicksLimit: 5 } },
                },
            },
        });
    }

    /* ---------- Patients by Age Group (bar) ---------- */
    const ageCanvas = document.getElementById('patientsByAgeChart');
    if (ageCanvas) {
        window.HC_DASHBOARD_CHARTS.patientsByAge = new Chart(ageCanvas, {
            type: 'bar',
            data: {
                labels: data.patientsByAge.labels,
                datasets: [{
                    data: data.patientsByAge.values,
                    backgroundColor: '#D93025',
                    borderRadius: 6,
                    maxBarThickness: 34,
                }],
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
        });
    }

    /* ---------- Lab & X-ray Requests (two lines) ----------
       Range-filterable too — dashboard-range-filters.js updates both
       datasets from the endpoint's "series" array. */
    const labXrayCanvas = document.getElementById('labXrayTrendChart');
    if (labXrayCanvas && data.labXrayTrend) {
        const line = function (label, values, color) {
            return {
                label: label,
                data: values,
                borderColor: color,
                backgroundColor: color,
                borderWidth: 2,
                tension: 0.4,
                pointRadius: 3,
                pointBackgroundColor: color,
            };
        };
        window.HC_DASHBOARD_CHARTS.labXrayTrend = new Chart(labXrayCanvas, {
            type: 'line',
            data: {
                labels: data.labXrayTrend.labels,
                datasets: [
                    line('Laboratory', data.labXrayTrend.series[0], '#7C3AED'),
                    line('X-ray', data.labXrayTrend.series[1], '#1A73E8'),
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
        });
    }

    /* ---------- Reusable doughnut renderer (gender) ---------- */
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
    }

    renderDoughnut('patientsByGenderChart', data.patientsByGender);
});
