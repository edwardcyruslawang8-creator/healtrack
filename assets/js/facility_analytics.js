/* =============================================================
   FACILITY ANALYTICS & REPORTS JS (admin/facility_analytics.php)
   ---------------------------------------------------------------
   Renders every Chart.js instance on the page from the data PHP
   printed into window.FACILITY_ANALYTICS_DATA (see the inline
   <script> in facility_analytics.php). The mobile sidebar toggle
   lives in assets/js/admin-sidebar-toggle.js instead, since it's
   shared by every admin/*.php page, not just this one.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const data = window.FACILITY_ANALYTICS_DATA;
    if (!data || typeof Chart === 'undefined') return;

    // Chart.js grid lines/tick labels are drawn in a fixed color, so a
    // light-mode gray grid would be nearly invisible on a dark card —
    // read the current theme's actual colors instead of hardcoding one.
    const rootStyle = getComputedStyle(document.documentElement);
    const gridColor = rootStyle.getPropertyValue('--adm-border').trim() || '#ECEDF3';
    const tickColor = rootStyle.getPropertyValue('--adm-text-muted').trim() || '#6B7280';

    /* ---------- Summary card sparklines ---------- */
    data.summaryCards.forEach(function (card) {
        const canvas = document.getElementById('spark-' + card.key);
        if (!canvas) return;

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: card.sparkline.map(function (_, i) { return i; }),
                datasets: [{
                    data: card.sparkline,
                    borderColor: card.color,
                    backgroundColor: card.color + '1A', // ~10% opacity fill
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                scales: {
                    x: { display: false },
                    y: { display: false },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false },
                },
            },
        });
    });

    /* ---------- Patient Trend line chart ---------- */
    const trendCanvas = document.getElementById('patientTrendChart');
    let trendChart = null;
    if (trendCanvas) {
        trendChart = new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: data.patientTrend.labels,
                datasets: [
                    {
                        label: 'New Patients',
                        data: data.patientTrend.newPatients,
                        borderColor: '#1A73E8',
                        backgroundColor: '#1A73E8',
                        borderWidth: 2,
                        tension: 0.35,
                        pointRadius: 3,
                        pointBackgroundColor: '#1A73E8',
                    },
                    {
                        label: 'Returning Patients',
                        data: data.patientTrend.returningPatients,
                        borderColor: '#1E8E3E',
                        backgroundColor: '#1E8E3E',
                        borderWidth: 2,
                        tension: 0.35,
                        pointRadius: 3,
                        pointBackgroundColor: '#1E8E3E',
                    },
                ],
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

    /* ---------- Patient Trend range switch (Daily/Weekly/Monthly/Yearly) ----------
       Intercepts the dropdown's real links (they still work as plain full-reload
       URLs if this fails or JS is off) and instead fetches just this card's data
       from api/get-patient-trend.php, updating the existing Chart instance in
       place via .update() — no page reload, so the rest of the Overview tab
       (summary cards, demographics, etc.) never re-renders for a range change
       that only ever touched this one card. */
    const trendCard = document.getElementById('patientTrendCard');
    if (trendCard && trendChart) {
        const facilityId = trendCard.dataset.facilityId;
        const rangeBtn = trendCard.querySelector('.an-range-btn');
        const rangeLinks = trendCard.querySelectorAll('.pt-range-link');

        rangeLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                if (link.classList.contains('active')) { e.preventDefault(); return; }
                e.preventDefault();

                const range = link.dataset.range;

                trendCard.classList.add('an-card-loading');

                fetch('../api/get-patient-trend.php?facility_id=' + encodeURIComponent(facilityId) + '&range=' + encodeURIComponent(range))
                    .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                    .then(function (result) {
                        if (!result.ok || !result.body.success) {
                            throw new Error(result.body.error || 'Could not load that range.');
                        }
                        const trend = result.body.patientTrend;
                        trendChart.data.labels = trend.labels;
                        trendChart.data.datasets[0].data = trend.newPatients;
                        trendChart.data.datasets[1].data = trend.returningPatients;
                        trendChart.update();

                        rangeBtn.textContent = result.body.rangeLabel;
                        rangeLinks.forEach(function (l) { l.classList.toggle('active', l === link); });

                        // Keeps a reload/share/back-button consistent with what's on
                        // screen, without triggering one itself.
                        const url = new URL(window.location.href);
                        url.searchParams.set('range', range);
                        window.history.replaceState({}, '', url);
                    })
                    .catch(function (err) {
                        HCDialog.alert(err.message || 'Could not update the chart. Please try again.');
                    })
                    .finally(function () {
                        trendCard.classList.remove('an-card-loading');
                    });
            });
        });
    }

    /* ---------- Predictive: New Patients — Actual vs. Forecast ---------- */
    const forecastCanvas = document.getElementById('forecastTrendChart');
    if (forecastCanvas && data.forecastTrend) {
        new Chart(forecastCanvas, {
            type: 'line',
            data: {
                labels: data.forecastTrend.labels,
                datasets: [
                    {
                        label: 'Actual',
                        data: data.forecastTrend.actual,
                        borderColor: '#1A73E8',
                        backgroundColor: '#1A73E8',
                        borderWidth: 2,
                        tension: 0.3,
                        pointRadius: 3,
                        pointBackgroundColor: '#1A73E8',
                        spanGaps: false,
                    },
                    {
                        label: 'Forecast',
                        data: data.forecastTrend.forecast,
                        borderColor: '#7C3AED',
                        backgroundColor: '#7C3AED',
                        borderWidth: 2,
                        borderDash: [6, 4],
                        tension: 0.3,
                        pointRadius: 3,
                        pointBackgroundColor: '#7C3AED',
                        spanGaps: false,
                    },
                ],
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

    /* ---------- Reusable doughnut renderer (demographics + stock charts) ---------- */
    function renderDoughnut(canvasId, dataset, cutout) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return null;

        return new Chart(canvas, {
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
                cutout: cutout || '68%',
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

    renderDoughnut('demographicsChart', data.demographics, '68%');
    const medicineStockChart = renderDoughnut('medicineStockChart', data.medicineStockDistribution, '70%');
    const vaccineStockChart = renderDoughnut('vaccineStockChart', data.vaccineStockDistribution, '70%');

    // Patients/Records/Services/Staff/Medicines/Vaccines/Reports tabs —
    // only the 3 that actually have a doughnut (Records/Services/Staff);
    // the others are bar lists or plain tables, rendered server-side.
    if (data.recordsByType) renderDoughnut('recordsByTypeChart', data.recordsByType, '68%');
    if (data.servicesByStatus) renderDoughnut('servicesByStatusChart', data.servicesByStatus, '68%');
    if (data.staffByRole) renderDoughnut('staffByRoleChart', data.staffByRole, '68%');

    /* ---------- Medicine/Vaccine Inventory cards' own category filter ----------
       Same in-place-refresh idea as the Patient Trend range switch above, just
       for api/get-inventory-card.php instead: intercepts the dropdown's real
       links, fetches just this one card's data for the picked category, and
       updates its pill counts, its low-stock/stock-overview list (rebuilt from
       scratch — Chart.js only owns the donut, not this plain HTML bar list),
       and its donut — no page reload, and the OTHER card + everything else on
       the tab is untouched. */
    function wireInventoryCard(cardId, itemType, chart) {
        const card = document.getElementById(cardId);
        if (!card || !chart) return;

        const links = card.querySelectorAll('.inv-category-link');
        const rangeBtn = card.querySelector('.an-range-btn');
        const listLabelEl = card.querySelector('[data-list-label]');
        const listRowsEl = card.querySelector('[data-list-rows]');
        const legendEl = card.querySelector('[data-legend]');

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        links.forEach(function (link) {
            link.addEventListener('click', function (e) {
                if (link.classList.contains('active')) { e.preventDefault(); return; }
                e.preventDefault();

                const category = link.dataset.category;
                card.classList.add('an-card-loading');

                fetch('../api/get-inventory-card.php?type=' + encodeURIComponent(itemType) + '&category=' + encodeURIComponent(category))
                    .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                    .then(function (result) {
                        if (!result.ok || !result.body.success) {
                            throw new Error(result.body.error || 'Could not load that category.');
                        }
                        const d = result.body;

                        card.querySelectorAll('[data-pill]').forEach(function (pillEl) {
                            const key = pillEl.dataset.pill;
                            pillEl.textContent = d.inventory[key].toLocaleString();
                        });

                        if (listLabelEl) listLabelEl.textContent = d.listLabel;
                        if (listRowsEl) {
                            if (d.list.length === 0) {
                                listRowsEl.innerHTML = '<p class="adm-table-empty" style="padding:10px 0;">No items in this category.</p>';
                            } else {
                                listRowsEl.innerHTML = d.list.map(function (item) {
                                    const pct = Math.round((item.stock / d.listMax) * 100);
                                    return '<div class="an-inv-row">' +
                                        '<span class="an-inv-row-name">' + escapeHtml(item.name) + '</span>' +
                                        '<div class="an-inv-row-bar-track"><div class="an-inv-row-bar-fill" style="width:' + pct + '%;"></div></div>' +
                                        '<span class="an-inv-row-value">' + item.stock + '</span>' +
                                        '</div>';
                                }).join('');
                            }
                        }

                        chart.data.labels = d.stockDistribution.map(function (s) { return s.label; });
                        chart.data.datasets[0].data = d.stockDistribution.map(function (s) { return s.pct; });
                        chart.data.datasets[0].backgroundColor = d.stockDistribution.map(function (s) { return s.color; });
                        chart.update();

                        if (legendEl) {
                            legendEl.innerHTML = d.stockDistribution.map(function (s) {
                                const pctDisplay = (Math.round(s.pct * 10) / 10).toString();
                                return '<div class="legend-row">' +
                                    '<span class="dot" style="background:' + s.color + ';"></span>' +
                                    '<span class="legend-label">' + escapeHtml(s.label) + '</span>' +
                                    '<span class="legend-value">' + pctDisplay + '%</span>' +
                                    '</div>';
                            }).join('');
                        }

                        rangeBtn.textContent = category;
                        links.forEach(function (l) { l.classList.toggle('active', l === link); });

                        const url = new URL(window.location.href);
                        url.searchParams.set(itemType === 'vaccine' ? 'vacCategory' : 'medCategory', category);
                        window.history.replaceState({}, '', url);
                    })
                    .catch(function (err) {
                        HCDialog.alert(err.message || 'Could not update this card. Please try again.');
                    })
                    .finally(function () {
                        card.classList.remove('an-card-loading');
                    });
            });
        });
    }

    wireInventoryCard('medicineInventoryCard', 'medicine', medicineStockChart);
    wireInventoryCard('vaccineInventoryCard', 'vaccine', vaccineStockChart);
});
