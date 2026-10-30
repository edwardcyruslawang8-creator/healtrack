/* =============================================================
   PROJECTED STOCK DECLINE CARD
   (admin/facility_analytics.php, subadmin/predictive_analytics.php)
   ---------------------------------------------------------------
   window.HCStockProjection.init(idPrefix, config) drives the whole
   "Projected Stock Decline" card: a searchable/keyboard-accessible
   custom dropdown (any real medicine or vaccine, resolved by its own
   real doc id — never by name alone, since two or more real inventory
   docs can share the exact same name in this system), a forecast-
   period select (7/14/30 days), a refresh button, 4 summary
   indicators + a stock-status badge, the chart itself, and a
   recommendation box with a "View in Inventory" link.

   config = {
     items: [{id, name, itemType, label}, ...],   // every real item, PHP-sorted by name
     defaultDetail: {...} | null,                 // includes/shared/predictive_analytics_core.php's hc_stock_projection_detail(), for the page's own default item/period (id, name, itemType, unit, currentStock, minLevel, maxLevel, avgDailyConsumption, insufficientData, sharedName, estimatedDaysRemaining, stockStatus, forecastDays, labels, projected)
     apiUrl: '.../api/get-stock-projection.php',  // ?id=&days= -> {success, detail}
     inventoryUrl: '.../medicine_vaccine_inventory.php', // role's own inventory page
   }

   Average daily consumption / days-remaining / stock status all come
   from the server's own hc_stock_projection_detail() — this file only
   renders whatever that function decided, including its honest
   "insufficient_data" state when no real dispensed-quantity history
   exists for an item (true for most real items today — see that
   function's own comment).
   ============================================================= */
window.HCStockProjection = (function () {
    const STATUS_META = {
        healthy: { label: 'Healthy', icon: 'bi-check-circle-fill' },
        low: { label: 'Low Stock', icon: 'bi-exclamation-circle-fill' },
        critical: { label: 'Critical', icon: 'bi-exclamation-triangle-fill' },
        out_of_stock: { label: 'Out of Stock', icon: 'bi-x-octagon-fill' },
        insufficient_data: { label: 'Insufficient Data', icon: 'bi-question-circle-fill' },
    };

    function escapeHtml(s) {
        const div = document.createElement('div');
        div.textContent = s == null ? '' : String(s);
        return div.innerHTML;
    }

    function typeColor(itemType) {
        return itemType === 'vaccine' ? '#12A6C6' : '#5B0A0A';
    }

    function init(idPrefix, config) {
        const dropdown = document.getElementById(idPrefix + 'PSDDropdown');
        if (!dropdown) return; // card not on this page, or empty state (no items at all)

        const dropdownBtn = document.getElementById(idPrefix + 'PSDDropdownBtn');
        const dropdownLabel = document.getElementById(idPrefix + 'PSDDropdownLabel');
        const dropdownPanel = document.getElementById(idPrefix + 'PSDDropdownPanel');
        const dropdownSearch = document.getElementById(idPrefix + 'PSDDropdownSearch');
        const dropdownSeg = document.getElementById(idPrefix + 'PSDDropdownSeg');
        const dropdownList = document.getElementById(idPrefix + 'PSDDropdownList');
        const periodSelect = document.getElementById(idPrefix + 'PSDPeriod');
        const refreshBtn = document.getElementById(idPrefix + 'PSDRefresh');
        const sharedNameNote = document.getElementById(idPrefix + 'PSDSharedNameNote');
        const summaryBox = document.getElementById(idPrefix + 'PSDSummary');
        const legendBox = document.getElementById(idPrefix + 'PSDLegend');
        const chartCanvas = document.getElementById(idPrefix + 'T2StockProjectionChart');
        const recommendationBox = document.getElementById(idPrefix + 'PSDRecommendation');

        const items = config.items || [];
        const apiUrl = config.apiUrl;
        const inventoryUrl = config.inventoryUrl;

        let dropdownCategory = 'all';
        let focusedIndex = -1;
        let currentItemId = config.defaultDetail ? config.defaultDetail.id : null;
        let chart = null;

        // ---------------- dropdown ----------------
        function visibleItems() {
            const term = (dropdownSearch && dropdownSearch.value || '').trim().toLowerCase();
            return items.filter(function (i) {
                if (dropdownCategory !== 'all' && i.itemType !== dropdownCategory) return false;
                if (term && i.name.toLowerCase().indexOf(term) === -1) return false;
                return true;
            });
        }

        function renderDropdownList() {
            const rows = visibleItems();
            focusedIndex = rows.length ? 0 : -1;
            dropdownList.innerHTML = '';
            if (rows.length === 0) {
                const empty = document.createElement('li');
                empty.className = 'psd-dropdown-empty';
                empty.textContent = 'No items match.';
                dropdownList.appendChild(empty);
                return;
            }
            rows.forEach(function (item, idx) {
                const li = document.createElement('li');
                li.className = 'psd-dropdown-item' + (item.id === currentItemId ? ' selected' : '') + (idx === focusedIndex ? ' focused' : '');
                li.setAttribute('role', 'option');
                li.dataset.id = item.id;
                li.innerHTML = '<span class="dot" style="background:' + typeColor(item.itemType) + ';"></span><span>' + escapeHtml(item.label || item.name) + '</span>';
                li.addEventListener('click', function () { selectItem(item.id, item.name); });
                dropdownList.appendChild(li);
            });
        }

        function openDropdown() {
            dropdown.classList.add('open');
            dropdownPanel.hidden = false;
            dropdownBtn.setAttribute('aria-expanded', 'true');
            if (dropdownSearch) {
                dropdownSearch.value = '';
                renderDropdownList();
                dropdownSearch.focus();
            }
        }

        function closeDropdown() {
            dropdown.classList.remove('open');
            dropdownPanel.hidden = true;
            dropdownBtn.setAttribute('aria-expanded', 'false');
        }

        function moveFocus(delta) {
            const rows = dropdownList.querySelectorAll('.psd-dropdown-item');
            if (!rows.length) return;
            focusedIndex = (focusedIndex + delta + rows.length) % rows.length;
            rows.forEach(function (row, idx) { row.classList.toggle('focused', idx === focusedIndex); });
            rows[focusedIndex].scrollIntoView({ block: 'nearest' });
        }

        if (dropdownBtn) {
            dropdownBtn.addEventListener('click', function () {
                if (dropdownPanel.hidden) openDropdown(); else closeDropdown();
            });
        }
        document.addEventListener('click', function (e) {
            if (!dropdown.contains(e.target)) closeDropdown();
        });
        if (dropdownSearch) {
            dropdownSearch.addEventListener('input', renderDropdownList);
            dropdownSearch.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowDown') { e.preventDefault(); moveFocus(1); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); moveFocus(-1); }
                else if (e.key === 'Enter') {
                    e.preventDefault();
                    const rows = dropdownList.querySelectorAll('.psd-dropdown-item');
                    if (focusedIndex >= 0 && rows[focusedIndex]) rows[focusedIndex].click();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    closeDropdown();
                    dropdownBtn.focus();
                }
            });
        }
        if (dropdownSeg) {
            dropdownSeg.addEventListener('click', function (e) {
                const btn = e.target.closest('.opf-seg-btn');
                if (!btn || !dropdownSeg.contains(btn)) return;
                Array.prototype.forEach.call(dropdownSeg.querySelectorAll('.opf-seg-btn'), function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                dropdownCategory = btn.getAttribute('data-category') || 'all';
                renderDropdownList();
            });
        }

        function selectItem(id, name) {
            closeDropdown();
            dropdownBtn.focus();
            fetchDetail(id, periodSelect ? periodSelect.value : '30');
        }

        // ---------------- fetch + render ----------------
        function fetchDetail(id, days) {
            if (!id) return;
            fetch(apiUrl + '?id=' + encodeURIComponent(id) + '&days=' + encodeURIComponent(days), { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    if (!json || !json.success) return;
                    currentItemId = json.detail.id;
                    renderAll(json.detail);
                })
                .catch(function () {
                    // A real network hiccup — leave whatever's already on screen rather than blanking it.
                });
        }

        function renderAll(detail) {
            if (dropdownLabel) dropdownLabel.textContent = detail.name;
            renderSharedNameNote(detail);
            renderSummary(detail);
            renderChart(detail);
            renderRecommendation(detail);
            renderDropdownList(); // refresh the "selected" highlight
        }

        function renderSharedNameNote(detail) {
            if (!sharedNameNote) return;
            sharedNameNote.innerHTML = detail.sharedName
                ? '<div class="psd-shared-name-note"><i class="bi bi-info-circle-fill"></i><span>Another real inventory entry shares this exact name. Stock figures below are for this specific entry, but usage history (orders/prescriptions) can\'t be told apart between them — it\'s combined across every entry named "' + escapeHtml(detail.name) + '".</span></div>'
                : '';
        }

        function renderSummary(detail) {
            if (!summaryBox) return;
            const meta = STATUS_META[detail.stockStatus] || STATUS_META.insufficient_data;
            const avgText = detail.avgDailyConsumption !== null
                ? detail.avgDailyConsumption + ' ' + detail.unit + '/day'
                : 'Insufficient Data';
            const daysText = detail.estimatedDaysRemaining !== null
                ? detail.estimatedDaysRemaining + ' day' + (detail.estimatedDaysRemaining === 1 ? '' : 's')
                : 'Insufficient Data';

            summaryBox.innerHTML =
                '<div class="psd-status-row" style="grid-column:1 / -1;">' +
                    '<span></span>' +
                    '<span class="psd-status-badge psd-status-' + detail.stockStatus + '"><span class="dot"></span>' + meta.label + '</span>' +
                '</div>' +
                tile('Current Stock', detail.currentStock + ' ' + detail.unit) +
                tile('Reorder Level', detail.minLevel + ' ' + detail.unit) +
                tile('Avg. Daily Consumption', avgText, detail.avgDailyConsumption === null) +
                tile('Est. Days Remaining', daysText, detail.estimatedDaysRemaining === null);
        }

        function tile(label, value, muted) {
            return '<div class="psd-summary-tile"><span class="psd-summary-tile-label">' + label + '</span><span class="psd-summary-tile-value' + (muted ? ' muted' : '') + '">' + escapeHtml(value) + '</span></div>';
        }

        function renderChart(detail) {
            if (!chartCanvas || typeof Chart === 'undefined') return;

            const rootStyle = getComputedStyle(document.documentElement);
            const gridColor = rootStyle.getPropertyValue('--adm-border').trim() || '#ECEDF3';
            const tickColor = rootStyle.getPropertyValue('--adm-text-muted').trim() || '#6B7280';

            const reorderLine = detail.labels.map(function () { return detail.minLevel; });
            const values = detail.projected.concat([detail.minLevel]);
            const vMin = Math.min.apply(null, values);
            const vMax = Math.max.apply(null, values);
            const pad = Math.max(1, (vMax - vMin) * 0.15);
            const yMin = Math.max(0, Math.floor(vMin - pad));
            const yMax = Math.ceil(vMax + pad);

            // Insufficient data -> the line is flat (no real decline could
            // be computed), so it's drawn plainly (solid gray, "Current
            // stock") instead of looking like a real forecast.
            const projectedStyle = detail.insufficientData
                ? { label: 'Current stock (no forecast — insufficient usage data)', borderColor: '#9AA0AC', backgroundColor: '#9AA0AC22', borderDash: [] }
                : { label: detail.name + ' — projected stock', borderColor: '#D93025', backgroundColor: '#D9302533', borderDash: [6, 4] };

            if (legendBox) {
                legendBox.innerHTML = detail.insufficientData
                    ? '<span><span class="dot" style="background:#9AA0AC;"></span> Current stock (flat — no usage data)</span><span><span class="dot dashed" style="color:#E8710A;"></span> Reorder level</span>'
                    : '<span><span class="dot dashed" style="color:#D93025;"></span> Projected stock</span><span><span class="dot dashed" style="color:#E8710A;"></span> Reorder level</span>';
            }

            if (chart) {
                chart.data.labels = detail.labels;
                chart.data.datasets[0].label = projectedStyle.label;
                chart.data.datasets[0].data = detail.projected;
                chart.data.datasets[0].borderColor = projectedStyle.borderColor;
                chart.data.datasets[0].backgroundColor = projectedStyle.backgroundColor;
                chart.data.datasets[0].borderDash = projectedStyle.borderDash;
                chart.data.datasets[1].data = reorderLine;
                chart.options.scales.y.min = yMin;
                chart.options.scales.y.max = yMax;
                chart.update();
                return;
            }

            chart = new Chart(chartCanvas, {
                type: 'line',
                data: {
                    labels: detail.labels,
                    datasets: [
                        {
                            label: projectedStyle.label, data: detail.projected,
                            borderColor: projectedStyle.borderColor, backgroundColor: projectedStyle.backgroundColor,
                            borderWidth: 2, borderDash: projectedStyle.borderDash, tension: 0.2, pointRadius: 0, fill: true,
                        },
                        {
                            label: 'Reorder level', data: reorderLine,
                            borderColor: '#E8710A', borderWidth: 1.5, borderDash: [3, 3], pointRadius: 0, fill: false,
                        },
                    ],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }, // custom legend in HTML
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    return ctx.dataset.label + ': ' + ctx.formattedValue + ' ' + detail.unit;
                                },
                            },
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: false, min: yMin, max: yMax,
                            grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 },
                            title: { display: true, text: 'Stock (' + detail.unit + ')', color: tickColor, font: { size: 11 } },
                        },
                        x: {
                            grid: { display: false }, ticks: { color: tickColor, maxTicksLimit: 8 },
                            title: { display: true, text: 'Date', color: tickColor, font: { size: 11 } },
                        },
                    },
                },
            });

            // Same hidden-Bootstrap-tab 0×0 problem predictive-analytics.js's
            // own createdCharts tracker solves for its charts — this card's
            // chart isn't part of that tracker (it's created later, on
            // demand), so it gets its own resize listener.
            document.addEventListener('shown.bs.tab', function () {
                if (chart) chart.resize();
            });
        }

        function renderRecommendation(detail) {
            if (!recommendationBox) return;
            const needsReplenishment = ['out_of_stock', 'critical', 'low'].indexOf(detail.stockStatus) !== -1;
            let text;
            switch (detail.stockStatus) {
                case 'out_of_stock':
                    text = '<strong>' + escapeHtml(detail.name) + '</strong> is currently out of stock. Reorder immediately to avoid disruption.';
                    break;
                case 'critical':
                    text = '<strong>' + escapeHtml(detail.name) + '</strong> is projected to run out in about ' + detail.estimatedDaysRemaining + ' day(s) at its current usage pace. Reordering soon is recommended.';
                    break;
                case 'low':
                    text = 'Current stock of <strong>' + escapeHtml(detail.name) + '</strong> (' + detail.currentStock + ' ' + detail.unit + ') is at or below its reorder level (' + detail.minLevel + ' ' + detail.unit + '). Consider placing a reorder.';
                    break;
                case 'insufficient_data':
                    text = 'There isn\'t enough real dispensing data yet to estimate how fast <strong>' + escapeHtml(detail.name) + '</strong> is being used. Monitor its stock manually until more usage is recorded.';
                    break;
                default:
                    text = '<strong>' + escapeHtml(detail.name) + '</strong> currently has sufficient stock. No action needed right now.';
            }

            const href = inventoryUrl + '?q=' + encodeURIComponent(detail.name) + '&vq=' + encodeURIComponent(detail.name);
            recommendationBox.innerHTML =
                '<p class="psd-recommendation-text">' + text + '</p>' +
                (needsReplenishment
                    ? '<a class="psd-recommendation-btn" href="' + href + '"><i class="bi bi-box-seam"></i> View in Inventory</a>'
                    : '<a class="psd-recommendation-btn" href="' + href + '" style="background:var(--adm-bg);color:var(--adm-text);"><i class="bi bi-box-seam"></i> View in Inventory</a>');
        }

        // ---------------- period + refresh ----------------
        if (periodSelect) {
            periodSelect.addEventListener('change', function () {
                fetchDetail(currentItemId, periodSelect.value);
            });
        }
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                refreshBtn.classList.add('spinning');
                fetchDetail(currentItemId, periodSelect ? periodSelect.value : '30');
                setTimeout(function () { refreshBtn.classList.remove('spinning'); }, 600);
            });
        }

        // ---------------- initial render ----------------
        if (config.defaultDetail) {
            renderAll(config.defaultDetail);
        }
    }

    return { init: init };
})();
