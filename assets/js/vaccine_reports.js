/* =============================================================
   VACCINE REPORTS JS (subadmin/vaccine_reports.php)
   ---------------------------------------------------------------
   Renders every Chart.js instance on the page from the data PHP
   printed into window.VACCINE_REPORTS_DATA (see the inline <script>
   in subadmin/vaccine_reports.php) — same pattern as
   assets/js/analytics_reports.js / assets/js/consultation_reports.js.
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
let __vaccineReportsCharts = [];

function initReportPageCharts() {
    __vaccineReportsCharts.forEach(function (c) { c.destroy(); });
    __vaccineReportsCharts = [];

    const data = window.VACCINE_REPORTS_DATA;
    if (!data || typeof Chart === 'undefined') return;

    const rootStyle = getComputedStyle(document.documentElement);
    const gridColor = rootStyle.getPropertyValue('--adm-border').trim() || '#ECEDF3';
    const tickColor = rootStyle.getPropertyValue('--adm-text-muted').trim() || '#6B7280';
    const textColor = rootStyle.getPropertyValue('--adm-text').trim() || '#1F2430';

    /* ---------- Doses Administered by Vaccine Type (doughnut) ---------- */
    const typeCanvas = document.getElementById('dosesByVaccineTypeChart');
    if (typeCanvas) {
        const chart = new Chart(typeCanvas, {
            type: 'doughnut',
            data: {
                labels: data.dosesByVaccineType.labels,
                datasets: [{
                    data: data.dosesByVaccineType.values,
                    backgroundColor: data.dosesByVaccineType.colors,
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
        __vaccineReportsCharts.push(chart);
    }

    /* ---------- Doses Administered by Age Group (vertical bar) ----------
       Real dose COUNTS per age bucket, not a coverage % — that would
       need a target population per bucket this app's data doesn't have
       (see includes/subadmin/vaccine_reports_firestore.php's own header
       comment). */
    const ageCanvas = document.getElementById('coverageByAgeGroupChart');
    if (ageCanvas) {
        // Chart.js has no built-in data-label support — draws each bar's
        // count just above its top, same approach as the horizontal
        // value-label plugin used on the other report pages' bar charts.
        const barTopLabelPlugin = {
            id: 'barTopLabel',
            afterDatasetsDraw(chart) {
                const { ctx } = chart;
                const meta = chart.getDatasetMeta(0);
                ctx.save();
                ctx.fillStyle = textColor;
                ctx.font = '700 12px Poppins, Inter, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'bottom';
                meta.data.forEach((bar, i) => {
                    const value = chart.data.datasets[0].data[i];
                    ctx.fillText(String(value), bar.x, bar.y - 8);
                });
                ctx.restore();
            },
        };

        const chart = new Chart(ageCanvas, {
            type: 'bar',
            data: {
                labels: data.coverageByAgeGroup.labels,
                datasets: [{
                    data: data.coverageByAgeGroup.values,
                    backgroundColor: data.coverageByAgeGroup.colors,
                    borderRadius: 8,
                    maxBarThickness: 46,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                // Headroom above the tallest bar for the count label the
                // plugin above draws, so it never gets clipped.
                layout: { padding: { top: 22 } },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function (ctx) { return ctx.parsed.y + ' dose(s)'; } } },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: tickColor, precision: 0 },
                        grid: { color: gridColor },
                        title: { display: true, text: 'Doses Administered', color: tickColor, font: { size: 11 } },
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: tickColor },
                    },
                },
            },
            plugins: [barTopLabelPlugin],
        });
        __vaccineReportsCharts.push(chart);
    }

    /* ---------- Doses Administered Trend (smooth line, gradient fill) ---------- */
    const trendCanvas = document.getElementById('dosesAdministeredTrendChart');
    if (trendCanvas) {
        const ctx = trendCanvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, trendCanvas.clientHeight || 220);
        gradient.addColorStop(0, 'rgba(26, 115, 232, 0.25)');
        gradient.addColorStop(1, 'rgba(26, 115, 232, 0)');

        // Draws each point's raw dose count just above it, matching the
        // reference design's on-chart value labels.
        const linePointLabelPlugin = {
            id: 'linePointLabel',
            afterDatasetsDraw(chart) {
                const { ctx } = chart;
                const meta = chart.getDatasetMeta(0);
                ctx.save();
                ctx.fillStyle = textColor;
                ctx.font = '700 12px Poppins, Inter, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'bottom';
                meta.data.forEach((point, i) => {
                    const value = chart.data.datasets[0].data[i];
                    ctx.fillText(String(value), point.x, point.y - 10);
                });
                ctx.restore();
            },
        };

        const chart = new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: data.dosesAdministeredTrend.labels,
                datasets: [{
                    label: 'Doses Administered',
                    data: data.dosesAdministeredTrend.values,
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
                // Headroom above the highest point for the value label the
                // plugin above draws, so it never gets clipped.
                layout: { padding: { top: 24 } },
                plugins: {
                    legend: { display: false },
                    tooltip: { mode: 'index', intersect: false },
                },
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: tickColor, stepSize: 200 },
                        grid: { color: gridColor },
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: tickColor },
                    },
                },
            },
            plugins: [linePointLabelPlugin],
        });
        __vaccineReportsCharts.push(chart);
    }
}

window.initReportPageCharts = initReportPageCharts;
document.addEventListener('DOMContentLoaded', initReportPageCharts);
