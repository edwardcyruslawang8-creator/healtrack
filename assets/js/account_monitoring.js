/* =============================================================
   ACCOUNT MONITORING JS (admin/account_monitoring.php)
   ---------------------------------------------------------------
   Renders every Chart.js instance on the page from the data PHP
   printed into window.ACCOUNT_MONITORING_DATA (see the inline
   <script> in account_monitoring.php). The mobile sidebar toggle
   lives in assets/js/admin-sidebar-toggle.js instead, since it's
   shared by every admin/*.php page, not just this one.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const data = window.ACCOUNT_MONITORING_DATA;
    if (!data || typeof Chart === 'undefined') return;

    // Chart.js grid lines/tick labels are drawn in a fixed color, so a
    // light-mode gray grid would be nearly invisible on a dark card —
    // read the current theme's actual colors instead of hardcoding one.
    const rootStyle = getComputedStyle(document.documentElement);
    const gridColor = rootStyle.getPropertyValue('--adm-border').trim() || '#ECEDF3';
    const tickColor = rootStyle.getPropertyValue('--adm-text-muted').trim() || '#6B7280';

    /* ---------- Reusable doughnut renderer (status overview + role distribution) ---------- */
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

    renderDoughnut('acmStatusDonut', data.statusOverview);
    renderDoughnut('acmRoleDonut', data.byRole);

    /* ---------- Accounts Over Time line chart ---------- */
    const trendCanvas = document.getElementById('acmAccountsOverTimeChart');
    let trendChart = null;
    if (trendCanvas) {
        trendChart = new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: data.overTime.labels,
                datasets: data.overTime.series.map(function (s) {
                    return {
                        label: s.label,
                        data: s.data,
                        borderColor: s.color,
                        backgroundColor: s.color,
                        borderWidth: 2,
                        tension: 0.35,
                        pointRadius: 3,
                        pointBackgroundColor: s.color,
                    };
                }),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }, // custom legend rendered in HTML instead
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor } },
                    x: { grid: { display: false }, ticks: { color: tickColor } },
                },
            },
        });
    }

    /* ---------- Accounts Over Time range dropdown (AJAX, no page reload) ----------
       Picking 7/30/90 Days fetches api/get-accounts-over-time.php and redraws
       trendChart in place — same real $accountsOverTime data a full page
       load would compute, just without reloading the rest of the page. */
    const rangeBtn = document.getElementById('acmTrendRangeBtn');
    const rangeMenu = document.getElementById('acmTrendRangeMenu');
    if (trendChart && rangeBtn && rangeMenu) {
        rangeMenu.addEventListener('click', function (e) {
            const link = e.target.closest('a.dropdown-item');
            if (!link) return;
            e.preventDefault();

            const range = link.dataset.range;

            fetch('../api/get-accounts-over-time.php?' + new URLSearchParams({ days: range }), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (res) {
                    if (!res.ok) throw new Error('Request failed');
                    return res.json();
                })
                .then(function (json) {
                    trendChart.data.labels = json.overTime.labels;
                    trendChart.data.datasets.forEach(function (ds, i) {
                        ds.data = json.overTime.series[i].data;
                    });
                    trendChart.update();

                    rangeBtn.textContent = json.range;
                    rangeMenu.querySelectorAll('a.dropdown-item').forEach(function (item) {
                        item.classList.toggle('active', item.dataset.range === json.range);
                    });

                    const url = new URL(window.location.href);
                    url.searchParams.set('days', json.range);
                    history.replaceState(null, '', url.pathname + url.search);
                })
                .catch(function () {
                    // A real navigation is still a correct (if less smooth)
                    // fallback if the AJAX request itself failed for any reason.
                    window.location.href = link.href;
                });
        });
    }
});
