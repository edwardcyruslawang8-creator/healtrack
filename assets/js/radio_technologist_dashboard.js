/* =============================================================
   RADIO TECHNOLOGIST DASHBOARD JS (radio_technologist/dashboard.php)
   ---------------------------------------------------------------
   Renders every Chart.js instance on the page from the data PHP
   printed into window.RADIO_TECHNOLOGIST_DASHBOARD_DATA (see the
   inline <script> in radio_technologist/dashboard.php). Structurally
   identical to assets/js/medical_technician_dashboard.js (same chart
   types, narrower set — X-ray only, no "by type"/"request type" split
   since every exam here already is an X-ray) — kept as its own file
   since the two roles' data shapes belong to different roles.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const data = window.RADIO_TECHNOLOGIST_DASHBOARD_DATA;
    if (!data || typeof Chart === 'undefined') return;

    const rootStyle = getComputedStyle(document.documentElement);
    const gridColor = rootStyle.getPropertyValue('--adm-border').trim() || '#ECEDF3';
    const tickColor = rootStyle.getPropertyValue('--adm-text-muted').trim() || '#6B7280';

    // Exposes the one range-filterable chart (Exams Processed Trend) so
    // assets/js/dashboard-range-filters.js can live-update it in place.
    window.HC_DASHBOARD_CHARTS = window.HC_DASHBOARD_CHARTS || {};

    /* ---------- Exams Processed Trend (line) ---------- */
    const trendCanvas = document.getElementById('examsTrendChart');
    if (trendCanvas) {
        window.HC_DASHBOARD_CHARTS.examsTrend = new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: data.examsTrend.labels,
                datasets: [{
                    data: data.examsTrend.values,
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

    /* ---------- Result Status (doughnut) ---------- */
    const statusCanvas = document.getElementById('resultStatusChart');
    if (statusCanvas) {
        new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: data.resultStatus.labels,
                datasets: [{
                    data: data.resultStatus.values,
                    backgroundColor: data.resultStatus.colors,
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
});
