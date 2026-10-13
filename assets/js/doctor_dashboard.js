/* =============================================================
   DOCTOR DASHBOARD JS (doctor/dashboard.php)
   ---------------------------------------------------------------
   Renders every Chart.js instance on the page from the data PHP
   printed into window.DOCTOR_DASHBOARD_DATA (see the inline <script>
   in doctor/dashboard.php). The mobile sidebar toggle lives in
   assets/js/admin-sidebar-toggle.js instead, since it's shared by
   every admin/subadmin/medical-staff/doctor page, not just this one.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const data = window.DOCTOR_DASHBOARD_DATA;
    if (!data || typeof Chart === 'undefined') return;

    // Read the current theme's actual grid/tick colors instead of
    // hardcoding light-mode values (see subadmin_dashboard.js for the
    // same pattern).
    const rootStyle = getComputedStyle(document.documentElement);
    const gridColor = rootStyle.getPropertyValue('--adm-border').trim() || '#ECEDF3';
    const tickColor = rootStyle.getPropertyValue('--adm-text-muted').trim() || '#6B7280';

    /* ---------- Consultations Trend (line) ---------- */
    const trendCanvas = document.getElementById('consultationsTrendChart');
    if (trendCanvas) {
        new Chart(trendCanvas, {
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

    /* ---------- Patients by Age Group (bar) ---------- */
    const ageCanvas = document.getElementById('patientsByAgeChart');
    if (ageCanvas) {
        new Chart(ageCanvas, {
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

    /* ---------- Reusable doughnut renderer (gender + consultation status) ---------- */
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

    renderDoughnut('patientsByGenderChart', data.patientsByGender);
    renderDoughnut('consultationStatusChart', data.consultationStatus);
});
