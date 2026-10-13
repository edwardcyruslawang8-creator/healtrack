/* =============================================================
   SUB-ADMIN DASHBOARD — PER-CARD FILTER REFRESH (subadmin/dashboard.php)
   ---------------------------------------------------------------
   The 3 filterable cards (Consultations Trend, Patients by Age Group,
   Top 5 Common Health Conditions) render a real "?trendRange=.../
   ?ageRange=.../?conditionsRange=..." link for every option — still
   real, working URLs (bookmarkable, no-JS fallback) — but clicking one
   here intercepts that navigation and instead fetches just that one
   card's data from api/subadmin-dashboard-card.php and updates it in
   place, so picking a range never reloads the stat cards, the other
   charts, Recent Stock Requests, etc.

   Requires assets/js/subadmin_dashboard.js to have already run (it
   creates window.HC_SUBADMIN_DASHBOARD_CHARTS.{consultationsTrend,
   patientsByAge}) — loaded right after it in subadmin/dashboard.php.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-dashboard-card]').forEach(function (card) {
        const cardKey = card.dataset.dashboardCard;
        const rangeParam = card.dataset.rangeParam;
        const labelEl = card.querySelector('[data-range-label]');
        const items = card.querySelectorAll('.dropdown-item[data-range-value]');

        items.forEach(function (item) {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                if (item.classList.contains('active')) return; // already showing this range

                const value = item.dataset.rangeValue;
                card.classList.add('sad-card-loading');

                fetch('../api/subadmin-dashboard-card.php?card=' + encodeURIComponent(cardKey) + '&' + encodeURIComponent(rangeParam) + '=' + encodeURIComponent(value))
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (!data.success) throw new Error(data.error || 'Could not refresh this card.');

                        if (labelEl) labelEl.textContent = data.rangeLabel;
                        items.forEach(function (i) { i.classList.toggle('active', i === item); });

                        // Keeps the URL/bookmark/reload behavior consistent with
                        // the real href each item already carries, without
                        // triggering a navigation.
                        if (window.history && window.history.replaceState) {
                            const url = new URL(window.location.href);
                            url.searchParams.set(rangeParam, value);
                            window.history.replaceState(null, '', url);
                        }

                        if (cardKey === 'healthConditions') {
                            const wrap = card.querySelector('[data-condition-list-wrap]');
                            if (wrap) wrap.innerHTML = data.html;
                            return;
                        }

                        const chart = window.HC_SUBADMIN_DASHBOARD_CHARTS && window.HC_SUBADMIN_DASHBOARD_CHARTS[cardKey];
                        if (chart) {
                            chart.data.labels = data.labels;
                            chart.data.datasets[0].data = data.values;
                            chart.update();
                        }
                    })
                    .catch(function (err) {
                        HCDialog.alert(err.message);
                    })
                    .finally(function () {
                        card.classList.remove('sad-card-loading');
                    });
            });
        });
    });
});
