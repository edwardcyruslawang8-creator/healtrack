/* =============================================================
   ANALYTICS & REPORTS JS
   ---------------------------------------------------------------
   Shared by every role's own analytics_reports.php (subadmin,
   doctor, medical-staff, medical_technician, pharmacist) — renders
   every Chart.js instance on the page from the data PHP prints into
   window.ANALYTICS_REPORTS_DATA (each page's own inline <script>),
   same pattern as assets/js/subadmin_dashboard.js. Generic by design
   (a single optional trend line + any number of doughnuts, addressed
   by canvas id) rather than 5 near-identical copies hardcoding one
   role's own chart ids. The mobile sidebar toggle lives in
   assets/js/admin-sidebar-toggle.js instead, since it's shared by
   every admin-shell page, not just this one.

   Expected shape:
     window.ANALYTICS_REPORTS_DATA = {
       trend: { canvasId, labels: [...], values: [...] } | null,
       donuts: [ { canvasId, labels: [...], values: [...], colors: [...] }, ... ],
     }

   Optional smooth AJAX range-switch: opt-in via data attributes on the
   trend card's own wrapper element —
     data-range-endpoint="../api/get-xxx-trend.php"
     data-range-param="xxxRange"
   — so ANY role's trend card gets this same real, in-place-update
   behavior just by adding those two attributes plus .range-switch-link
   on its dropdown items, no new JS needed. True no-op on any page
   without such a card (currently: subadmin's "Consultations Over Time"
   backed by api/get-consultation-trend.php, and pharmacist's "My Stock
   Requests Over Time" backed by api/get-stock-requests-trend.php) —
   same "reuse the real pipeline, one endpoint per range-switching card"
   pattern as admin/subadmin facility_analytics.php's Patient Trend card
   (assets/js/facility_analytics.js + api/get-patient-trend.php).

   Everything below is wrapped in window.initReportPageCharts() rather
   than run inline, so assets/js/ajax-report-nav.js's whole-page smooth
   content swap (subadmin's 6 report pages only) can re-invoke it after
   replacing <main> — same convention every sibling report page's own
   JS file follows. Previously-created Chart instances are destroyed
   first, since the swap leaves their old <canvas> elements orphaned.
   ============================================================= */
let __analyticsReportsCharts = [];

function initReportPageCharts() {
    __analyticsReportsCharts.forEach(function (c) { c.destroy(); });
    __analyticsReportsCharts = [];

    const data = window.ANALYTICS_REPORTS_DATA;
    if (!data || typeof Chart === 'undefined') return;

    // Read the current theme's actual grid/tick colors instead of
    // hardcoding light-mode values (same pattern as subadmin_dashboard.js).
    const rootStyle = getComputedStyle(document.documentElement);
    const gridColor = rootStyle.getPropertyValue('--adm-border').trim() || '#ECEDF3';
    const tickColor = rootStyle.getPropertyValue('--adm-text-muted').trim() || '#6B7280';

    /* ---------- Trend line (Consultations/Tests/Requests/Patients Over Time) ---------- */
    let trendChart = null;
    let trendCanvasEl = null;
    if (data.trend) {
        const trendCanvas = document.getElementById(data.trend.canvasId);
        if (trendCanvas) {
            trendCanvasEl = trendCanvas;
            trendChart = new Chart(trendCanvas, {
                type: 'line',
                data: {
                    labels: data.trend.labels,
                    datasets: [{
                        data: data.trend.values,
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
                        y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor } },
                        x: { grid: { display: false }, ticks: { color: tickColor, autoSkip: true, maxTicksLimit: 6 } },
                    },
                },
            });
            __analyticsReportsCharts.push(trendChart);
        }
    }

    /* ---------- Trend card range switch (Daily/Weekly/Monthly) ----------
       Opt-in via data-range-endpoint/data-range-param on the trend
       card's own wrapper (see header comment) — the trend canvas itself
       is the reliable anchor to find that wrapper from, so this works
       regardless of how deep the canvas sits inside it. Intercepts the
       dropdown's real links (they still work as plain full-reload URLs
       if this fails or JS is off) and instead fetches just this card's
       data from its own endpoint, updating the existing Chart instance
       in place via .update() — no page reload. */
    const trendCard = trendCanvasEl ? trendCanvasEl.closest('[data-range-endpoint]') : null;
    if (trendCard && trendChart) {
        const endpoint = trendCard.dataset.rangeEndpoint;
        const param = trendCard.dataset.rangeParam;
        const rangeBtn = trendCard.querySelector('.sad-range-btn');
        const rangeLinks = trendCard.querySelectorAll('.range-switch-link');

        rangeLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                if (link.classList.contains('active')) { e.preventDefault(); return; }
                e.preventDefault();

                const range = link.dataset.range;

                trendCard.classList.add('arp-card-loading');

                fetch(endpoint + '?' + param + '=' + encodeURIComponent(range))
                    .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                    .then(function (result) {
                        if (!result.ok || !result.body.success) {
                            throw new Error(result.body.error || 'Could not load that range.');
                        }
                        trendChart.data.labels = result.body.labels;
                        trendChart.data.datasets[0].data = result.body.values;
                        trendChart.update();

                        if (rangeBtn) rangeBtn.textContent = result.body.rangeLabel;
                        rangeLinks.forEach(function (l) { l.classList.toggle('active', l === link); });

                        // Keeps a reload/share/back-button consistent with
                        // what's on screen, without triggering one itself.
                        const url = new URL(window.location.href);
                        url.searchParams.set(param, range);
                        window.history.replaceState({}, '', url);
                    })
                    .catch(function (err) {
                        HCDialog.alert(err.message || 'Could not update the chart. Please try again.');
                    })
                    .finally(function () {
                        trendCard.classList.remove('arp-card-loading');
                    });
            });
        });
    }

    /* ---------- Reusable doughnut renderer (by type / by status / inventory / etc.) ---------- */
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
        __analyticsReportsCharts.push(chart);
    }

    (data.donuts || []).forEach(function (d) { renderDoughnut(d.canvasId, d); });
}

window.initReportPageCharts = initReportPageCharts;
document.addEventListener('DOMContentLoaded', initReportPageCharts);

/* ---------- "My Patients Report" Print button (doctor/medical-staff/
   medical_technician) ----------
   Delegated on document (survives any future AJAX content swap,
   same reasoning as this file's header comment) — toggles a body
   class the @media print rules in analytics_reports.css key off of,
   so the printed/PDF output is just the report table, not the whole
   dashboard page. */
document.addEventListener('click', function (e) {
    if (!e.target.closest('.arp-report-print-btn')) return;
    document.body.classList.add('arp-printing-report');
    window.print();
});
window.addEventListener('afterprint', function () {
    document.body.classList.remove('arp-printing-report');
});
