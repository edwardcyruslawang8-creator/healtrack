/* =============================================================
   ADMINISTRATOR DASHBOARD JS (admin/admin_dashboard.php)
   ---------------------------------------------------------------
   Loaded only on this page. Renders the Sub-Administrator Overview
   doughnut chart (Chart.js) from data printed server-side into
   window.SUBADMIN_CHART_DATA (see the inline <script> in
   admin_dashboard.php). The mobile sidebar toggle lives in
   assets/js/admin-sidebar-toggle.js instead, since it's shared by
   every admin/*.php page, not just this one.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {

    /* ---------- Sub-Administrator Overview doughnut chart ---------- */
    const canvas = document.getElementById('subAdminDonut');
    if (canvas && typeof Chart !== 'undefined' && window.SUBADMIN_CHART_DATA) {
        const data = window.SUBADMIN_CHART_DATA;

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Inactive'],
                datasets: [{
                    data: [data.active, data.inactive],
                    backgroundColor: ['#1E8E3E', '#E8710A'],
                    borderWidth: 0,
                    hoverOffset: 4,
                }],
            },
            options: {
                cutout: '72%',
                plugins: {
                    legend: { display: false }, // custom legend is rendered in HTML instead
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ctx.label + ': ' + ctx.parsed;
                            },
                        },
                    },
                },
            },
        });
    }

    // TODO: once real facility/sub-admin/account data exists, the stat
    // cards, tables, and this chart should all be re-fetched (e.g. via
    // fetch() to an api/ endpoint) instead of relying on the PHP arrays
    // in includes/admin-data.php.

});
