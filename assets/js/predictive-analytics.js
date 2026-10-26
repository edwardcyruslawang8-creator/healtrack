/* =============================================================
   PREDICTIVE ANALYTICS JS (admin/facility_analytics.php)
   ---------------------------------------------------------------
   window.HCPredictiveCharts.renderAll(data, idPrefix) renders all 6
   Predictive Analytics charts (Tab 1's forecast + disease-trend, Tab
   2's stock-projection + order-frequency, Tab 3's request-volume, Tab
   4's forecast) from one data object shaped like
   {t1ForecastTrend, t1DiseaseTrend, t2StockProjection, t2OrderFrequency,
   t2ShortageTrend, t3RequestVolume, t4ForecastTrend}. $idPrefix is prepended to each
   canvas id (…T1ForecastChart, …T1DiseaseTrendChart, etc.) so the
   function stays reusable rather than hardcoding one page's ids — kept
   as a parameter (not inlined) since it's exercised by more than one
   caller within admin/facility_analytics.php itself.

   admin/facility_analytics.php calls this from its own inline
   <script> (after this file loads) for
   window.FACILITY_ANALYTICS_DATA.predictive with idPrefix "faPred" —
   see that page's own <script> block.

   Same conventions as assets/js/facility_analytics.js throughout
   (theme-aware grid/tick colors via CSS custom properties, the
   dashed-second-dataset technique for a forecast line, no chart
   plugins).
   ============================================================= */
window.HCPredictiveCharts = (function () {
    function renderAll(data, idPrefix) {
        if (!data || typeof Chart === 'undefined') return;
        idPrefix = idPrefix || '';

        const rootStyle = getComputedStyle(document.documentElement);
        const gridColor = rootStyle.getPropertyValue('--adm-border').trim() || '#ECEDF3';
        const tickColor = rootStyle.getPropertyValue('--adm-text-muted').trim() || '#6B7280';

        /** Shared actual-vs-forecast line chart (Tab 1's own forecast, Tab 4's all-barangays combined). */
        function renderForecastChart(canvasId, trend) {
            const canvas = document.getElementById(canvasId);
            if (!canvas || !trend) return;

            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: trend.labels,
                    datasets: [
                        {
                            label: 'Actual', data: trend.actual,
                            borderColor: '#1A73E8', backgroundColor: '#1A73E8',
                            borderWidth: 2, tension: 0.3, pointRadius: 3, pointBackgroundColor: '#1A73E8', spanGaps: false,
                        },
                        {
                            label: 'Forecast', data: trend.forecast,
                            borderColor: '#7C3AED', backgroundColor: '#7C3AED',
                            borderWidth: 2, borderDash: [6, 4], tension: 0.3, pointRadius: 3, pointBackgroundColor: '#7C3AED', spanGaps: false,
                        },
                    ],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } }, // custom legend in HTML
                    scales: {
                        y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 } },
                        x: { grid: { display: false }, ticks: { color: tickColor } },
                    },
                },
            });
        }

        /** Shared multi-line trend chart (Tab 1 disease categories, Tab 2 order frequency). */
        function renderMultiLineChart(canvasId, series) {
            const canvas = document.getElementById(canvasId);
            if (!canvas || !series || !series.datasets.length) return;

            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: series.labels,
                    datasets: series.datasets.map(function (ds) {
                        return {
                            label: ds.label, data: ds.data,
                            borderColor: ds.color, backgroundColor: ds.color,
                            borderWidth: 2, tension: 0.3, pointRadius: 2,
                        };
                    }),
                },
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

        /* ---------- Tab 1: consultation forecast ---------- */
        renderForecastChart(idPrefix + 'T1ForecastChart', data.t1ForecastTrend);
        /* ---------- Tab 1: top notifiable-disease categories, daily ---------- */
        renderMultiLineChart(idPrefix + 'T1DiseaseTrendChart', data.t1DiseaseTrend);

        /* ---------- Tab 2: most urgent item's projected stock decline ---------- */
        const stockCanvas = document.getElementById(idPrefix + 'T2StockProjectionChart');
        if (stockCanvas && data.t2StockProjection && data.t2StockProjection.labels.length) {
            const proj = data.t2StockProjection;
            new Chart(stockCanvas, {
                type: 'line',
                data: {
                    labels: proj.labels,
                    datasets: [
                        {
                            label: proj.itemName + ' — projected stock', data: proj.forecast,
                            borderColor: '#D93025', backgroundColor: '#D9302533',
                            borderWidth: 2, borderDash: [6, 4], tension: 0.2, pointRadius: 0, fill: true,
                        },
                        {
                            label: 'Reorder level', data: proj.labels.map(function () { return proj.reorderLevel; }),
                            borderColor: '#E8710A', borderWidth: 1.5, borderDash: [3, 3], pointRadius: 0, fill: false,
                        },
                    ],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } }, // custom legend in HTML
                    scales: {
                        y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 } },
                        x: { grid: { display: false }, ticks: { color: tickColor, maxTicksLimit: 8 } },
                    },
                },
            });
        }
        /* ---------- Tab 2: order/prescription frequency, top items ---------- */
        renderMultiLineChart(idPrefix + 'T2OrderFreqChart', data.t2OrderFrequency);
        /* ---------- Tab 2: projected stock decline, EVERY at-risk item (vs. the single most-urgent-item chart above) ---------- */
        renderMultiLineChart(idPrefix + 'T2ShortageTrendChart', data.t2ShortageTrend);

        /* ---------- Tab 3: weekly stock-request volume by status ---------- */
        const requestCanvas = document.getElementById(idPrefix + 'T3RequestVolumeChart');
        if (requestCanvas && data.t3RequestVolume && data.t3RequestVolume.labels.length) {
            new Chart(requestCanvas, {
                type: 'bar',
                data: {
                    labels: data.t3RequestVolume.labels,
                    datasets: data.t3RequestVolume.datasets.map(function (ds) {
                        return { label: ds.label, data: ds.data, backgroundColor: ds.color, borderRadius: 3 };
                    }),
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true, position: 'bottom', labels: { color: tickColor, boxWidth: 10, font: { size: 11 } } },
                    },
                    scales: {
                        y: { beginAtZero: true, stacked: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 } },
                        x: { stacked: true, grid: { display: false }, ticks: { color: tickColor } },
                    },
                },
            });
        }

        /* ---------- Tab 4: barangays combined — actual vs forecast ---------- */
        renderForecastChart(idPrefix + 'T4ForecastChart', data.t4ForecastTrend);
    }

    return { renderAll: renderAll };
})();
