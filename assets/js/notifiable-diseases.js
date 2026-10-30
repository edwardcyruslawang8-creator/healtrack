/* =============================================================
   POTENTIAL NOTIFIABLE DISEASE CASES CARD
   (admin/facility_analytics.php, subadmin/predictive_analytics.php)
   ---------------------------------------------------------------
   window.HCNotifiableDiseases.init(idPrefix, config) drives the whole
   "Potential Notifiable Disease Cases" card: a date-range filter
   (7/30/90 days or a custom range), 2 summary indicators, a top-5
   horizontal-bar list, a "View Trends" modal (weekly/monthly
   aggregated line chart), and a "View All" modal (searchable/sortable
   table of every real category, including zero-count ones).

   config = {
     facilityId: '...',
     defaultDays: 90,
     defaultSummary: {...},   // includes/shared/predictive_analytics_core.php's hc_notifiable_disease_summary() — {categories: [{category, count, firstDate, lastDate}, ...], totalCases, categoryCount}
     defaultTrend: {...},     // hc_notifiable_disease_trend_aggregated() — {labels, datasets: [{label, data, color}, ...]}
     apiUrl: '.../api/get-disease-trends.php',
   }

   All real numbers — card never fabricates a category/count that
   api/get-disease-trends.php (includes/shared/predictive_analytics_core.php's
   own hc_notifiable_disease_summary()/hc_notifiable_disease_trend_aggregated())
   didn't actually compute from real medical_records diagnosis/chief-
   complaint text.
   ============================================================= */
window.HCNotifiableDiseases = (function () {
    function escapeHtml(s) {
        const div = document.createElement('div');
        div.textContent = s == null ? '' : String(s);
        return div.innerHTML;
    }

    function formatDate(d) {
        if (!d) return '—';
        const dt = new Date(d + 'T00:00:00');
        if (isNaN(dt.getTime())) return d;
        return dt.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function init(idPrefix, config) {
        const rangeSelect = document.getElementById(idPrefix + 'PNDRange');
        if (!rangeSelect) return; // card not on this page

        const customRangeBox = document.getElementById(idPrefix + 'PNDCustomRange');
        const startInput = document.getElementById(idPrefix + 'PNDStartDate');
        const endInput = document.getElementById(idPrefix + 'PNDEndDate');
        const applyBtn = document.getElementById(idPrefix + 'PNDApplyRange');
        const summaryBox = document.getElementById(idPrefix + 'PNDSummary');
        const top5List = document.getElementById(idPrefix + 'PNDTop5List');
        const emptyBox = document.getElementById(idPrefix + 'PNDEmpty');

        const trendsModalEl = document.getElementById(idPrefix + 'PNDTrendsModal');
        const granularitySeg = document.getElementById(idPrefix + 'PNDGranularitySeg');
        const trendsCanvas = document.getElementById(idPrefix + 'PNDTrendsChart');
        const trendsEmpty = document.getElementById(idPrefix + 'PNDTrendsEmpty');

        const allModalEl = document.getElementById(idPrefix + 'PNDAllModal');
        const allSearch = document.getElementById(idPrefix + 'PNDAllSearch');
        const allSort = document.getElementById(idPrefix + 'PNDAllSort');
        const allTableBody = document.getElementById(idPrefix + 'PNDAllTableBody');
        const allEmpty = document.getElementById(idPrefix + 'PNDAllEmpty');

        const apiUrl = config.apiUrl;
        const facilityId = config.facilityId;

        const defaultRangeParams = 'days=' + (config.defaultDays || 90);
        let currentRangeParams = defaultRangeParams;
        let currentSummary = config.defaultSummary || { categories: [], totalCases: 0, categoryCount: 0 };
        let currentGranularity = 'week';
        let trendChart = null;

        // ---------------- summary + top5 ----------------
        function renderSummary(summary) {
            currentSummary = summary;
            if (summaryBox) {
                summaryBox.innerHTML =
                    '<div class="opf-stat"><span class="opf-stat-value">' + summary.totalCases.toLocaleString() + '</span><span class="opf-stat-label">Total Cases</span></div>' +
                    '<div class="opf-stat"><span class="opf-stat-value">' + summary.categoryCount.toLocaleString() + '</span><span class="opf-stat-label">Disease Categories</span></div>';
            }

            const top5 = summary.categories.filter(function (c) { return c.count > 0; }).slice(0, 5);
            const maxCount = top5.reduce(function (m, c) { return Math.max(m, c.count); }, 0);
            const colors = ['#D93025', '#E8710A', '#7C3AED', '#1A73E8', '#0E9CA8'];

            if (top5List) top5List.innerHTML = '';
            if (top5.length === 0) {
                if (top5List) top5List.style.display = 'none';
                if (emptyBox) emptyBox.style.display = '';
                return;
            }
            if (top5List) top5List.style.display = '';
            if (emptyBox) emptyBox.style.display = 'none';

            top5.forEach(function (c, idx) {
                const pct = maxCount > 0 ? Math.max(4, Math.round((c.count / maxCount) * 100)) : 0;
                const row = document.createElement('div');
                row.className = 'opf-top5-row';
                row.innerHTML =
                    '<div class="opf-top5-row-head">' +
                        '<span class="opf-top5-row-name">' + escapeHtml(c.category) + '</span>' +
                        '<span class="opf-top5-row-value">' + c.count + '</span>' +
                    '</div>' +
                    '<div class="opf-top5-row-track"><div class="opf-top5-row-fill" style="width:' + pct + '%;background:' + colors[idx % colors.length] + ';"></div></div>';
                if (top5List) top5List.appendChild(row);
            });
        }

        function fetchSummary(rangeParams) {
            currentRangeParams = rangeParams;
            fetch(apiUrl + '?facility_id=' + encodeURIComponent(facilityId) + '&action=summary&' + rangeParams, { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    if (!json || !json.success) return;
                    renderSummary(json.summary);
                })
                .catch(function () {
                    // A real network hiccup — leave whatever's already on screen.
                });
        }

        if (rangeSelect) {
            rangeSelect.addEventListener('change', function () {
                if (rangeSelect.value === 'custom') {
                    if (customRangeBox) customRangeBox.hidden = false;
                    return;
                }
                if (customRangeBox) customRangeBox.hidden = true;
                fetchSummary('days=' + encodeURIComponent(rangeSelect.value));
            });
        }
        if (applyBtn) {
            applyBtn.addEventListener('click', function () {
                const start = startInput ? startInput.value : '';
                const end = endInput ? endInput.value : '';
                if (!start || !end || start > end) return;
                fetchSummary('start=' + encodeURIComponent(start) + '&end=' + encodeURIComponent(end));
            });
        }

        // ---------------- View Trends modal ----------------
        function renderTrend(trend) {
            if (!trendsCanvas || typeof Chart === 'undefined') return;

            if (!trend.labels.length || !trend.datasets.length) {
                trendsCanvas.style.display = 'none';
                if (trendsEmpty) trendsEmpty.style.display = '';
                return;
            }
            trendsCanvas.style.display = '';
            if (trendsEmpty) trendsEmpty.style.display = 'none';

            const rootStyle = getComputedStyle(document.documentElement);
            const gridColor = rootStyle.getPropertyValue('--adm-border').trim() || '#ECEDF3';
            const tickColor = rootStyle.getPropertyValue('--adm-text-muted').trim() || '#6B7280';

            const datasets = trend.datasets.map(function (ds) {
                return {
                    label: ds.label, data: ds.data,
                    borderColor: ds.color, backgroundColor: ds.color,
                    borderWidth: 2, tension: 0.3,
                    // Avoid unnecessary markers for zero-case buckets — the
                    // zero itself stays in the data (the line still dips to
                    // it), only its visible point dot is suppressed.
                    pointRadius: ds.data.map(function (v) { return v === 0 ? 0 : 3; }),
                    pointBackgroundColor: ds.color,
                };
            });

            if (trendChart) {
                trendChart.data.labels = trend.labels;
                trendChart.data.datasets = datasets;
                trendChart.update();
                return;
            }

            trendChart = new Chart(trendsCanvas, {
                type: 'line',
                data: { labels: trend.labels, datasets: datasets },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true, position: 'bottom', labels: { color: tickColor, boxWidth: 10, font: { size: 11 } } },
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 } },
                        x: { grid: { display: false }, ticks: { color: tickColor } },
                    },
                },
            });
        }

        function fetchTrend(granularity) {
            currentGranularity = granularity;
            fetch(apiUrl + '?facility_id=' + encodeURIComponent(facilityId) + '&action=trend&granularity=' + encodeURIComponent(granularity) + '&' + currentRangeParams, { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    if (!json || !json.success) return;
                    renderTrend(json.trend);
                })
                .catch(function () {});
        }

        if (trendsModalEl) {
            // The very first open, with the page's own default range/
            // granularity still selected, reuses the detail PHP already
            // computed (config.defaultTrend) instead of a redundant
            // fetch. Every other open (range/granularity changed since,
            // or the chart already exists) fetches fresh.
            trendsModalEl.addEventListener('shown.bs.modal', function () {
                if (!trendChart && config.defaultTrend && currentRangeParams === defaultRangeParams && currentGranularity === 'week') {
                    renderTrend(config.defaultTrend);
                    return;
                }
                fetchTrend(currentGranularity);
                if (trendChart) trendChart.resize();
            });
        }
        if (granularitySeg) {
            granularitySeg.addEventListener('click', function (e) {
                const btn = e.target.closest('.opf-seg-btn');
                if (!btn || !granularitySeg.contains(btn)) return;
                Array.prototype.forEach.call(granularitySeg.querySelectorAll('.opf-seg-btn'), function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                fetchTrend(btn.getAttribute('data-granularity') || 'week');
            });
        }

        // ---------------- View All modal ----------------
        function renderAllTable() {
            if (!allTableBody) return;
            const term = allSearch ? allSearch.value.trim().toLowerCase() : '';
            let rows = currentSummary.categories.slice();
            if (term) {
                rows = rows.filter(function (c) { return c.category.toLowerCase().indexOf(term) !== -1; });
            }
            const sortValue = allSort ? allSort.value : 'count-desc';
            if (sortValue === 'count-asc') {
                rows.sort(function (a, b) { return a.count - b.count || a.category.localeCompare(b.category); });
            } else if (sortValue === 'name-asc') {
                rows.sort(function (a, b) { return a.category.localeCompare(b.category); });
            } else {
                rows.sort(function (a, b) { return b.count - a.count || a.category.localeCompare(b.category); });
            }

            allTableBody.innerHTML = '';
            if (rows.length === 0) {
                if (allEmpty) allEmpty.style.display = '';
            } else {
                if (allEmpty) allEmpty.style.display = 'none';
                rows.forEach(function (c) {
                    const tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + escapeHtml(c.category) + '</td>' +
                        '<td class="opf-total-cell">' + c.count + '</td>' +
                        '<td>' + escapeHtml(formatDate(c.firstDate)) + '</td>' +
                        '<td>' + escapeHtml(formatDate(c.lastDate)) + '</td>';
                    allTableBody.appendChild(tr);
                });
            }
        }

        if (allModalEl) {
            allModalEl.addEventListener('shown.bs.modal', renderAllTable);
        }
        if (allSearch) allSearch.addEventListener('input', renderAllTable);
        if (allSort) allSort.addEventListener('change', renderAllTable);

        // ---------------- initial render ----------------
        if (config.defaultSummary) renderSummary(config.defaultSummary);
    }

    return { init: init };
})();
