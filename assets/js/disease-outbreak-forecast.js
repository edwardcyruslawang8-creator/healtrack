/* =============================================================
   DISEASE OUTBREAK MONITORING & FORECASTING CARD
   (subadmin/predictive_analytics.php's "Disease & Service Demand
   Forecast" tab — a new card alongside, not replacing, the existing
   "Potential Notifiable Disease Cases" card)
   ---------------------------------------------------------------
   window.HCDiseaseOutbreakForecast.init(idPrefix, config) drives the
   whole card: a searchable multi-select Disease filter and Barangay (no
   page reload — every filter change is a pure fetch() that only
   re-renders this card) — there is
   no date-range or horizon control of any kind; the chart always shows
   a fixed window: the real last 14 days of actual cases (solid lines)
   immediately followed by the real next 7 days' forecast (dashed
   lines), continuously shifting forward as today's real date changes.
   4 summary tiles, a multi-disease Chart.js line chart (a clearly
   marked, text-labeled "Forecast Begins" boundary between the two
   segments, real calendar-date labels throughout, an uncertainty band
   shown only when a single disease is in view to avoid overlapping
   shaded areas, clicking a disease in the legend hides its
   forecast+band together), and a monitoring table (Current/Previous/
   Trend/Monitoring Status, both periods a fixed 14 days and always
   ending today).

   Every disease is tracked by its real, stable registry doc ID
   throughout (never by display name alone) — the same id a disease
   keeps even if an admin later renames or reclassifies it — so chart
   colors stay consistent for the same disease across different filter
   combinations, and a rename can never silently reset its color or
   drop its selection.

   config = {
     monitoredDiseases: [{id, name}, ...],         // every GIS-eligible disease, for the multi-select
     defaultSummary: {...},                        // hc_disease_outbreak_monitoring()'s own 'summary'
     defaultChart: {...},                          // its own 'chart' {labels, forecastLabels, datasets}
     defaultTable: [...],                          // its own 'table' rows
     defaultCurrentPeriodLabel: 'Last 7 Days',     // its own 'currentPeriodLabel'
     apiUrl: '.../api/get-disease-outbreak-forecast.php',
   }

   All real numbers — this card never fabricates a disease, a case
   count, a trend, or a forecast that api/get-disease-outbreak-forecast.php
   (includes/shared/disease_outbreak_forecast.php's own
   hc_disease_outbreak_monitoring()) didn't actually compute from real,
   GIS-eligible, confirmed consultation cases. One disease with bad/
   incomplete data is isolated server-side and client-side alike — it's
   skipped (and logged), never allowed to blank the whole card.
   ============================================================= */
window.HCDiseaseOutbreakForecast = (function () {
    function escapeHtml(s) {
        const div = document.createElement('div');
        div.textContent = s == null ? '' : String(s);
        return div.innerHTML;
    }

    /**
     * Deterministic color for a real registry id — a small string hash
     * (no two diseases need a shared fixed palette slot, so this scales
     * to any real disease count) mapped to an HSL hue. The SAME id
     * always produces the SAME color, whether it's the only disease
     * shown or one of fifty, and surviving a disease rename (the id
     * never changes even when its display name does).
     */
    function colorForId(id) {
        let hash = 0;
        const s = String(id || 'unknown');
        for (let i = 0; i < s.length; i++) {
            hash = (hash * 31 + s.charCodeAt(i)) >>> 0;
        }
        const hue = hash % 360;
        return 'hsl(' + hue + ', 65%, 42%)';
    }

    function hslToRgba(hsl, alpha) {
        // hsl(H, S%, L%) -> rgba(...) string, for Chart.js fill colors
        // that need real alpha transparency (plain "hsl(...)" strings
        // don't support an alpha channel the way "hsla(...)" does).
        const m = /^hsl\((\d+),\s*(\d+)%,\s*(\d+)%\)$/.exec(hsl);
        if (!m) return 'rgba(107,114,128,' + alpha + ')';
        return 'hsla(' + m[1] + ',' + m[2] + '%,' + m[3] + '%,' + alpha + ')';
    }

    function init(idPrefix, config) {
        const diseaseBtn = document.getElementById(idPrefix + 'DOFDiseaseBtn');
        if (!diseaseBtn) return; // card not on this page

        const diseaseWrap = document.getElementById(idPrefix + 'DOFDiseaseMulti');
        const diseasePanel = document.getElementById(idPrefix + 'DOFDiseasePanel');
        const diseaseLabel = document.getElementById(idPrefix + 'DOFDiseaseLabel');
        const diseaseSearch = document.getElementById(idPrefix + 'DOFDiseaseSearch');
        const diseaseList = document.getElementById(idPrefix + 'DOFDiseaseList');
        const barangaySelect = document.getElementById(idPrefix + 'DOFBarangay');
        const summaryBox = document.getElementById(idPrefix + 'DOFSummary');
        const rangeLabelEl = document.getElementById(idPrefix + 'DOFRangeLabel');
        const capNote = document.getElementById(idPrefix + 'DOFCapNote');
        const canvas = document.getElementById(idPrefix + 'DOFChart');
        const canvasWrap = canvas ? canvas.closest('.an-trend-canvas-box') : null;
        const emptyBox = document.getElementById(idPrefix + 'DOFEmpty');
        const tableBody = document.getElementById(idPrefix + 'DOFTableBody');
        const forecastNote = document.getElementById(idPrefix + 'DOFForecastNote');

        const apiUrl = config.apiUrl;
        const monitoredDiseases = Array.isArray(config.monitoredDiseases) ? config.monitoredDiseases : [];
        let chart = null;
        let requestSeq = 0;
        // 'all' = "All Diseases"; 'ids' = a Set of real selected
        // registry ids.
        let selectionMode = 'all';
        let selectedIds = new Set();

        // ============================================================
        // Multi-select disease filter — a small, from-scratch widget
        // (no existing multi-pick component in this app to reuse), built
        // to match assets/js/styled-select.js's own trigger-button +
        // floating-listbox visual language (.hc-select* classes) rather
        // than inventing a new look.
        // ============================================================
        function buildDiseaseList(filterText) {
            const needle = (filterText || '').trim().toLowerCase();
            diseaseList.innerHTML = monitoredDiseases
                .filter((d) => !needle || d.name.toLowerCase().includes(needle))
                .map((d) => {
                    const checked = selectionMode === 'ids' && selectedIds.has(d.id);
                    return '<li class="dof-multiselect-item" role="option" aria-selected="' + checked + '" data-id="' + escapeHtml(d.id) + '">'
                        + '<input type="checkbox" ' + (checked ? 'checked' : '') + ' tabindex="-1">'
                        + '<span>' + escapeHtml(d.name) + '</span>'
                        + '</li>';
                }).join('') || '<li class="dof-multiselect-empty">No diseases match your search.</li>';
        }

        function updateDiseaseButtonLabel() {
            if (selectionMode === 'all') {
                diseaseLabel.textContent = 'All Diseases';
            } else if (selectedIds.size === 0) {
                diseaseLabel.textContent = 'All Diseases';
                selectionMode = 'all';
            } else if (selectedIds.size === 1) {
                const only = monitoredDiseases.find((d) => selectedIds.has(d.id));
                diseaseLabel.textContent = only ? only.name : '1 disease selected';
            } else {
                diseaseLabel.textContent = selectedIds.size + ' diseases selected';
            }
        }

        function openDiseasePanel() {
            diseaseWrap.classList.add('is-open');
            diseaseBtn.setAttribute('aria-expanded', 'true');
            buildDiseaseList(diseaseSearch.value);
        }
        function closeDiseasePanel() {
            diseaseWrap.classList.remove('is-open');
            diseaseBtn.setAttribute('aria-expanded', 'false');
        }

        diseaseBtn.addEventListener('click', function () {
            diseaseWrap.classList.contains('is-open') ? closeDiseasePanel() : openDiseasePanel();
        });
        document.addEventListener('click', function (e) {
            if (diseaseWrap.classList.contains('is-open') && !diseaseWrap.contains(e.target)) closeDiseasePanel();
        });
        if (diseaseSearch) {
            diseaseSearch.addEventListener('input', function () { buildDiseaseList(diseaseSearch.value); });
        }
        if (diseaseList) {
            diseaseList.addEventListener('click', function (e) {
                const item = e.target.closest('.dof-multiselect-item');
                if (!item) return;
                const id = item.dataset.id;
                if (selectionMode !== 'ids') { selectionMode = 'ids'; selectedIds = new Set(); }
                if (selectedIds.has(id)) selectedIds.delete(id); else selectedIds.add(id);
                buildDiseaseList(diseaseSearch ? diseaseSearch.value : '');
                updateDiseaseButtonLabel();
                refresh();
            });
        }
        diseaseWrap.querySelectorAll('.dof-multiselect-quick').forEach(function (btn) {
            btn.addEventListener('click', function () {
                selectionMode = 'all';
                selectedIds = new Set();
                updateDiseaseButtonLabel();
                closeDiseasePanel();
                refresh();
            });
        });

        if (barangaySelect) barangaySelect.addEventListener('change', refresh);

        function currentParams() {
            const params = new URLSearchParams();
            if (selectionMode === 'ids' && selectedIds.size > 0) {
                params.set('disease', Array.from(selectedIds).join(','));
            } else {
                params.set('disease', 'all');
            }
            params.set('barangay', barangaySelect ? barangaySelect.value : 'all');
            return params;
        }

        function setLoading(isLoading) {
            if (diseaseWrap) diseaseWrap.classList.toggle('dof-loading', false); // reserved — toolbar itself never disables
            if (canvasWrap) canvasWrap.classList.toggle('dof-loading', isLoading);
        }

        function refresh() {
            const mySeq = ++requestSeq;
            setLoading(true);
            fetch(apiUrl + '?' + currentParams().toString())
                .then((res) => res.json())
                .then((data) => {
                    if (mySeq !== requestSeq) return; // a newer filter change already started another request
                    if (!data || !data.success) throw new Error(data && data.error ? data.error : 'Request failed');
                    render(data.summary, data.chart, data.table, data.currentPeriodLabel);
                })
                .catch(() => {
                    if (mySeq !== requestSeq) return;
                    renderError();
                })
                .finally(() => {
                    if (mySeq === requestSeq) setLoading(false);
                });
        }

        function renderError() {
            if (summaryBox) {
                summaryBox.querySelectorAll('[data-dof-field]').forEach((el) => { el.textContent = '—'; });
            }
            if (canvasWrap) canvasWrap.style.display = 'none';
            if (emptyBox) {
                emptyBox.style.display = '';
                emptyBox.querySelector('p').textContent = 'Could not load disease outbreak data. Please try again.';
            }
            if (tableBody) tableBody.innerHTML = '';
        }

        function safeNumber(v, fallback) {
            return Number.isFinite(v) ? v : (fallback == null ? 0 : fallback);
        }

        function renderSummary(summary, rangeLabel) {
            if (!summaryBox) return;
            summaryBox.querySelectorAll('[data-dof-field]').forEach((el) => {
                const key = el.getAttribute('data-dof-field');
                const raw = summary ? summary[key] : null;
                el.textContent = String(safeNumber(Number(raw), 0));
            });
            if (rangeLabelEl && rangeLabel) rangeLabelEl.textContent = rangeLabel;
        }

        function renderTable(rows) {
            if (!tableBody) return;
            if (!Array.isArray(rows) || rows.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="5" class="adm-table-empty">No monitored diseases match this filter.</td></tr>';
                return;
            }
            const html = [];
            rows.forEach((r) => {
                // One malformed row must never blank the whole table —
                // skipped (logged) rather than thrown.
                try {
                    const trendIcon = r.trend === 'increasing' ? 'bi-arrow-up-short dof-trend-up'
                        : r.trend === 'decreasing' ? 'bi-arrow-down-short dof-trend-down'
                        : 'bi-dash dof-trend-flat';
                    const trendLabel = r.trend === 'increasing' ? 'Increasing' : r.trend === 'decreasing' ? 'Decreasing' : 'Stable';
                    const meta = r.statusMeta || { label: r.status || 'Insufficient Data', bg: '#EEF0F3', color: '#6B7280' };
                    html.push('<tr>'
                        + '<td>' + escapeHtml(r.disease) + '</td>'
                        + '<td>' + safeNumber(Number(r.currentCases)) + '</td>'
                        + '<td>' + safeNumber(Number(r.previousCases)) + '</td>'
                        + '<td><i class="bi ' + trendIcon + '"></i> ' + trendLabel + '</td>'
                        + '<td><span class="dof-status-badge" style="background:' + meta.bg + '; color:' + meta.color + ';">' + escapeHtml(meta.label) + '</span></td>'
                        + '</tr>');
                } catch (err) {
                    console.warn('HCDiseaseOutbreakForecast: skipped a malformed table row', err, r);
                }
            });
            tableBody.innerHTML = html.join('') || '<tr><td colspan="5" class="adm-table-empty">No monitored diseases match this filter.</td></tr>';
        }

        /**
         * Builds this chart's full Chart.js dataset list: for each
         * disease, an "actual" solid line over the real labels, plus —
         * only when that disease's own forecast has real signal — a
         * dashed "forecast" continuation and, only when a single
         * disease is in view, 2 invisible band datasets (upper/lower)
         * shaded between them (more than one disease would overlap
         * shaded bands and become unreadable, so bands are suppressed
         * then — the dashed forecast line itself still renders for
         * every disease). Band/forecast datasets are tagged
         * diseaseIndex so the legend's onClick (below) can toggle
         * a whole disease's datasets together, and excluded from the
         * legend's own item list via the legend filter callback — a
         * viewer toggles "Dengue", not "Dengue upper band" separately.
         * One malformed disease dataset is skipped (logged), never
         * allowed to blank every other disease's own line.
         */
        function buildDatasets(chartData) {
            const labels = (chartData.labels || []).concat(chartData.forecastLabels || []);
            const historyLen = (chartData.labels || []).length;
            const forecastLen = (chartData.forecastLabels || []).length;
            const datasets = [];
            const showBands = (chartData.datasets || []).length <= 1;

            (chartData.datasets || []).forEach((ds, i) => {
                try {
                    if (!ds || !Array.isArray(ds.data)) return;
                    const color = colorForId(ds.id || ds.label);
                    const cleanData = ds.data.map((v) => (Number.isFinite(v) ? v : null));
                    const actualData = cleanData.concat(new Array(forecastLen).fill(null));
                    datasets.push({
                        label: ds.label || 'Unknown',
                        data: actualData,
                        borderColor: color, backgroundColor: color,
                        borderWidth: 2, tension: 0.3, pointRadius: 2, pointBackgroundColor: color,
                        spanGaps: false, diseaseIndex: i, isBand: false,
                    });

                    if (Array.isArray(ds.forecast) && ds.forecast.length > 0) {
                        const cleanForecast = ds.forecast.map((v) => (Number.isFinite(v) ? v : 0));
                        const lastActual = cleanData.length > 0 ? cleanData[cleanData.length - 1] : null;
                        const forecastData = new Array(Math.max(0, historyLen - 1)).fill(null).concat([lastActual]).concat(cleanForecast);
                        datasets.push({
                            label: ds.label + ' (Forecast)',
                            data: forecastData,
                            borderColor: color, backgroundColor: color,
                            borderWidth: 2, borderDash: [6, 4], tension: 0.3, pointRadius: 2, pointBackgroundColor: color,
                            spanGaps: false, diseaseIndex: i, isBand: false, isForecast: true,
                        });

                        if (showBands) {
                            const cleanUpper = (ds.forecastUpper || []).map((v) => (Number.isFinite(v) ? v : 0));
                            const cleanLower = (ds.forecastLower || []).map((v) => (Number.isFinite(v) ? v : 0));
                            const upperData = new Array(historyLen).fill(null).concat(cleanUpper);
                            const lowerData = new Array(historyLen).fill(null).concat(cleanLower);
                            datasets.push({
                                label: ds.label + ' (Upper)', data: upperData,
                                borderColor: 'transparent', backgroundColor: hslToRgba(color, 0.15),
                                pointRadius: 0, fill: '+1', diseaseIndex: i, isBand: true,
                            });
                            datasets.push({
                                label: ds.label + ' (Lower)', data: lowerData,
                                borderColor: 'transparent', backgroundColor: hslToRgba(color, 0.15),
                                pointRadius: 0, fill: false, diseaseIndex: i, isBand: true,
                            });
                        }
                    }
                } catch (err) {
                    console.warn('HCDiseaseOutbreakForecast: skipped a malformed disease dataset', err, ds);
                }
            });

            return { labels, datasets };
        }

        function renderChart(chartData) {
            const hasData = chartData && Array.isArray(chartData.datasets) && chartData.datasets.length > 0;

            if (capNote) capNote.style.display = (hasData && chartData.datasets.length >= 6) ? '' : 'none';

            if (!hasData) {
                if (canvasWrap) canvasWrap.style.display = 'none';
                if (emptyBox) {
                    emptyBox.style.display = '';
                    emptyBox.querySelector('p').textContent = 'No GIS-eligible disease cases for this filter.';
                }
                if (forecastNote) forecastNote.style.display = 'none';
                if (chart) { chart.destroy(); chart = null; }
                return;
            }
            if (canvasWrap) canvasWrap.style.display = '';
            if (emptyBox) emptyBox.style.display = 'none';

            // A single selected disease whose forecast couldn't be
            // reliably generated (too little/too sparse history — see
            // hc_forecast_linear_fit()'s own honesty gate) gets a real,
            // visible note naming it, instead of silently showing a
            // chart that just stops at today with no explanation.
            if (forecastNote) {
                const onlyDs = chartData.datasets.length === 1 ? chartData.datasets[0] : null;
                const noSignal = onlyDs && (!Array.isArray(onlyDs.forecast) || onlyDs.forecast.length === 0);
                if (noSignal) {
                    forecastNote.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Insufficient Data to reliably forecast '
                        + escapeHtml(onlyDs.label || 'this disease') + ' — showing recorded cases only.';
                    forecastNote.style.display = '';
                } else {
                    forecastNote.style.display = 'none';
                }
            }

            if (typeof Chart === 'undefined' || !canvas) return;

            const built = buildDatasets(chartData);
            if (built.datasets.length === 0) {
                // Every disease dataset turned out malformed (extremely
                // unlikely, but isolated above) — same empty state as
                // genuinely no data, never a blank/broken canvas.
                if (canvasWrap) canvasWrap.style.display = 'none';
                if (emptyBox) {
                    emptyBox.style.display = '';
                    emptyBox.querySelector('p').textContent = 'No GIS-eligible disease cases for this filter.';
                }
                if (forecastNote) forecastNote.style.display = 'none';
                if (chart) { chart.destroy(); chart = null; }
                return;
            }

            const rootStyle = getComputedStyle(document.documentElement);
            const gridColor = rootStyle.getPropertyValue('--adm-border').trim() || '#ECEDF3';
            const tickColor = rootStyle.getPropertyValue('--adm-text-muted').trim() || '#6B7280';
            const maroon = rootStyle.getPropertyValue('--adm-red-bright').trim() || '#5B0A0A';

            // A clearly marked boundary between real historical cases and
            // forecasted ones — a real, computed index (chartData.boundaryIndex,
            // the last historical label's position), not an assumed
            // midpoint. Only drawn when there's an actual forecast segment
            // to mark a boundary before. The dashed line alone isn't
            // self-explanatory, so a subtle "Forecast Begins" text label
            // is drawn right beside it too — this is the one textual cue
            // this chart adds beyond its legend/tooltips, per the explicit
            // "subtle vertical separator labeled 'Forecast Begins'" spec.
            const boundaryIndex = Number.isFinite(chartData.boundaryIndex) ? chartData.boundaryIndex : null;
            const hasForecastSegment = (chartData.forecastLabels || []).length > 0;
            const boundaryLinePlugin = {
                id: 'dofBoundaryLine',
                afterDraw(ci) {
                    if (boundaryIndex == null || !hasForecastSegment) return;
                    const xScale = ci.scales.x;
                    const yScale = ci.scales.y;
                    if (!xScale || !yScale) return;
                    const x = xScale.getPixelForValue(boundaryIndex);
                    const ctx = ci.ctx;
                    ctx.save();
                    ctx.beginPath();
                    ctx.setLineDash([4, 3]);
                    ctx.lineWidth = 1.5;
                    ctx.strokeStyle = maroon;
                    ctx.moveTo(x, yScale.top);
                    ctx.lineTo(x, yScale.bottom);
                    ctx.stroke();

                    ctx.setLineDash([]);
                    ctx.font = '600 10px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
                    ctx.fillStyle = maroon;
                    const label = 'Forecast Begins';
                    const textWidth = ctx.measureText(label).width;
                    const nearRightEdge = (x + textWidth + 10) > xScale.right;
                    ctx.textBaseline = 'top';
                    ctx.textAlign = nearRightEdge ? 'right' : 'left';
                    ctx.fillText(label, nearRightEdge ? x - 6 : x + 6, yScale.top + 4);
                    ctx.restore();
                },
            };

            if (chart) chart.destroy();
            chart = new Chart(canvas, {
                type: 'line',
                data: { labels: built.labels, datasets: built.datasets },
                plugins: [boundaryLinePlugin],
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'nearest', intersect: false },
                    plugins: {
                        legend: {
                            labels: {
                                // Only the real "actual" line per disease gets its
                                // own legend entry — its forecast + band datasets
                                // are implementation detail, toggled together with
                                // it (onClick below), not separate legend items.
                                filter: (item, data) => !data.datasets[item.datasetIndex].isBand && !data.datasets[item.datasetIndex].isForecast,
                            },
                            onClick: function (e, legendItem, legend) {
                                const clickedIndex = built.datasets[legendItem.datasetIndex].diseaseIndex;
                                const ci = legend.chart;
                                built.datasets.forEach((ds, i) => {
                                    if (ds.diseaseIndex === clickedIndex) {
                                        const meta = ci.getDatasetMeta(i);
                                        meta.hidden = meta.hidden === null ? !ci.data.datasets[i].hidden : !meta.hidden;
                                    }
                                });
                                ci.update();
                            },
                        },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    const ds = ctx.dataset;
                                    if (ds.isBand) return null;
                                    const suffix = ds.isForecast ? ' (forecast)' : '';
                                    const val = ctx.parsed.y;
                                    return ds.label.replace(' (Forecast)', '') + suffix + ': ' + (Number.isFinite(val) ? val : '—') + ' case(s)';
                                },
                            },
                        },
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 } },
                        x: { grid: { display: false }, ticks: { color: tickColor, autoSkip: true, maxRotation: 45, minRotation: 0 } },
                    },
                },
            });
        }

        function render(summary, chartData, table, rangeLabel) {
            renderSummary(summary, rangeLabel);
            renderChart(chartData);
            renderTable(table);
        }

        // Initial disease list + first paint — the server-rendered
        // default (no fetch needed).
        buildDiseaseList('');
        updateDiseaseButtonLabel();
        render(config.defaultSummary, config.defaultChart, config.defaultTable, config.defaultCurrentPeriodLabel);
    }

    return { init: init };
})();
