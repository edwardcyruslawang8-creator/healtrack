/* =============================================================
   PREDICTIVE ANALYTICS JS (admin/facility_analytics.php)
   ---------------------------------------------------------------
   window.HCPredictiveCharts.renderAll(data, idPrefix) renders all 6
   Predictive Analytics charts (Tab 1's forecast + disease-trend, Tab
   2's stock-projection + order-frequency, Tab 3's request-volume, Tab
   4's forecast) from one data object shaped like
   {t1ForecastTrend, t1DiseaseTrend, t2ShortageTrend,
   t3RequestVolume, t4ForecastTrend, t4Barangays} (Tab 2's own
   "Projected Stock Decline" card is handled separately — see
   assets/js/stock-projection.js). $idPrefix is prepended to each
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

        // Every chart below gets created in this same tick regardless of
        // which Bootstrap tab (outer AND the 4 inner 6.3.1–6.3.4 ones)
        // happens to be visible right now — only the one tab marked
        // "show active" in the HTML has a real, non-zero size at this
        // point, so Chart.js silently draws every OTHER chart at 0×0
        // (a real, well-known Chart.js-inside-a-hidden-Bootstrap-tab
        // failure mode, not a data problem — compare this page's
        // Disease & Service Demand tab, which IS the hardcoded default
        // "show active" inner tab and so always renders fine, against
        // Medicine & Vaccine Shortage/Inventory Replenishment/
        // Consultation Trends, which don't). Every created chart is
        // tracked here and resized once its own tab-pane actually
        // becomes visible (Bootstrap's 'shown.bs.tab' event, delegated
        // on document so this works for both the outer tab strip and
        // the inner one) — resize() is a cheap no-op for a chart whose
        // tab was already visible.
        const createdCharts = [];
        function track(chart) {
            if (chart) createdCharts.push(chart);
            return chart;
        }

        /** Shared actual-vs-forecast line chart (Tab 1's own forecast, Tab 4's all-barangays combined). */
        function renderForecastChart(canvasId, trend) {
            const canvas = document.getElementById(canvasId);
            if (!canvas || !trend) return null;

            return new Chart(canvas, {
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
            if (!canvas || !series || !series.datasets.length) return null;

            return new Chart(canvas, {
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
        track(renderForecastChart(idPrefix + 'T1ForecastChart', data.t1ForecastTrend));
        /* ---------- Tab 1: top notifiable-disease categories, daily ---------- */
        track(renderMultiLineChart(idPrefix + 'T1DiseaseTrendChart', data.t1DiseaseTrend));

        /* ---------- Tab 2: "Projected Stock Decline" owns its own chart/dropdown/summary entirely — see assets/js/stock-projection.js ---------- */
        /* ---------- Tab 2: order/prescription frequency is now its own custom top-5 list + "View All" modal, not a Chart.js canvas — see assets/js/order-frequency.js ---------- */
        /* ---------- Tab 2: projected stock decline, EVERY at-risk item (vs. the single most-urgent-item chart above) ---------- */
        track(renderMultiLineChart(idPrefix + 'T2ShortageTrendChart', data.t2ShortageTrend));

        /* ---------- Tab 3: weekly stock-request volume by status ---------- */
        const requestCanvas = document.getElementById(idPrefix + 'T3RequestVolumeChart');
        if (requestCanvas && data.t3RequestVolume && data.t3RequestVolume.labels.length) {
            track(new Chart(requestCanvas, {
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
            }));
        }

        /* ---------- Tab 4: barangays combined — actual vs forecast ---------- */
        track(renderForecastChart(idPrefix + 'T4ForecastChart', data.t4ForecastTrend));
        /* ---------- Tab 4: consultations by barangay, last 30 days (bar) ---------- */
        const barangayCanvas = document.getElementById(idPrefix + 'T4BarangayChart');
        if (barangayCanvas && data.t4Barangays && data.t4Barangays.labels.length) {
            track(new Chart(barangayCanvas, {
                type: 'bar',
                data: {
                    labels: data.t4Barangays.labels,
                    datasets: [{
                        label: 'Consultations (Last 30 Days)',
                        data: data.t4Barangays.values,
                        backgroundColor: '#1A73E8',
                        borderRadius: 3,
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 } },
                        x: { grid: { display: false }, ticks: { color: tickColor, autoSkip: true, maxRotation: 45, minRotation: 45 } },
                    },
                },
            }));
        }

        // Resize every real chart created above once its own tab (outer
        // or inner) actually becomes visible — see the comment on
        // createdCharts above for why this is needed at all. Delegated
        // on document (not a specific tab-button element) so one
        // listener covers both admin/facility_analytics.php's outer
        // tab strip AND its nested 6.3.1–6.3.4 inner one, and
        // subadmin/predictive_analytics.php's own single tab strip,
        // without needing to know which markup this particular caller
        // uses.
        document.addEventListener('shown.bs.tab', function () {
            createdCharts.forEach(function (chart) {
                if (chart && typeof chart.resize === 'function') chart.resize();
            });
        });
    }

    return { renderAll: renderAll };
})();
