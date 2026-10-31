/* =============================================================
   PHARMACIST DASHBOARD JS (pharmacist/dashboard.php)
   ---------------------------------------------------------------
   Renders every Chart.js instance on the page from the data PHP
   printed into window.PHARMACIST_DASHBOARD_DATA (see the inline
   <script> in pharmacist/dashboard.php). The mobile sidebar toggle
   lives in assets/js/admin-sidebar-toggle.js instead, since it's
   shared by every admin/subadmin/medical-staff/pharmacist page, not
   just this one. Structurally identical to
   assets/js/medical_staff_dashboard.js (same chart types, different
   data keys) — kept separate rather than shared since the two
   dashboards' data shapes (and any future chart tweaks) belong to
   different roles.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const data = window.PHARMACIST_DASHBOARD_DATA;
    if (!data || typeof Chart === 'undefined') return;

    // Read the current theme's actual grid/tick colors instead of
    // hardcoding light-mode values (see subadmin_dashboard.js for the
    // same pattern).
    const rootStyle = getComputedStyle(document.documentElement);
    const gridColor = rootStyle.getPropertyValue('--adm-border').trim() || '#ECEDF3';
    const tickColor = rootStyle.getPropertyValue('--adm-text-muted').trim() || '#6B7280';

    // Exposes the 2 range-filterable charts (Dispensing Trend, Medicines
    // by Category) so assets/js/dashboard-range-filters.js can live-
    // update them in place (chart.data = ...; chart.update()) instead of
    // a full page reload when their dropdown filter changes.
    window.HC_DASHBOARD_CHARTS = window.HC_DASHBOARD_CHARTS || {};

    /* ---------- Dispensing Trend (line) ---------- */
    const trendCanvas = document.getElementById('dispensingTrendChart');
    if (trendCanvas) {
        window.HC_DASHBOARD_CHARTS.dispensingTrend = new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: data.dispensingTrend.labels,
                datasets: [{
                    data: data.dispensingTrend.values,
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
                animation: { duration: 700, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: false },
                    tooltip: { mode: 'index', intersect: false },
                },
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 } },
                    x: { grid: { display: false }, ticks: { color: tickColor } },
                },
            },
        });
    }

    /* ---------- Medicines by Category (bar) ---------- */
    const categoryCanvas = document.getElementById('medicinesByCategoryChart');
    if (categoryCanvas) {
        window.HC_DASHBOARD_CHARTS.medicinesByCategory = new Chart(categoryCanvas, {
            type: 'bar',
            data: {
                labels: data.medicinesByCategory.labels,
                datasets: [{
                    data: data.medicinesByCategory.values,
                    backgroundColor: '#D93025',
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
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 } },
                    x: { grid: { display: false }, ticks: { color: tickColor } },
                },
            },
        });
    }

    /* ---------- Reusable doughnut renderer (stock status + stock request status) ---------- */
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
                    hoverOffset: 6,
                }],
            },
            options: {
                cutout: '68%',
                // Fill the (square) canvas box exactly, with room for the
                // hovered slice's 6px offset so it never gets clipped.
                maintainAspectRatio: false,
                layout: { padding: 8 },
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

        // Clear the hovered slice + tooltip whenever the pointer leaves the
        // canvas, so a tooltip can never stay stuck on screen.
        canvas.addEventListener('mouseleave', function () {
            chart.setActiveElements([]);
            chart.tooltip.setActiveElements([], { x: 0, y: 0 });
            chart.update('none');
        });
    }

    renderDoughnut('stockStatusChart', data.stockStatus);
    renderDoughnut('stockRequestStatusChart', data.stockRequestStatus);
});
