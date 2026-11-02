/* =============================================================
   DOUGHNUT CENTER TOTAL (Chart.js plugin, registered globally)
   ---------------------------------------------------------------
   Every doughnut chart across this app (14+ role dashboards/report
   pages — admin/subadmin/doctor/medical-staff/medical_technician/
   pharmacist/radiologic_technologist dashboards, Analytics & Reports,
   Account Monitoring, Facility Analytics, Inventory/Patient/
   Consultation/Stock Request/Vaccine Reports) has a real cutout hole
   (cutout: '68%'/'72%') and a real total — but on each per-role
   dashboard's own doughnut cards specifically, that hole sat empty,
   with the total only shown as plain text below/beside the chart
   instead. This draws that same real total INSIDE the hole too,
   centered, filling that one gap — registered ONCE here rather than
   editing each of those files' own Chart.js configs individually:
   Chart.register() applies to every chart instance created afterward
   on the page this script is loaded on, and the plugin's own afterDraw
   hook no-ops for anything that isn't a doughnut, so a line/bar chart
   on the same page (e.g. a dashboard's own "Consultations Trend") is
   untouched. See the "SKIPS drawing" note below for the other 13
   pages, which already had their own real HTML version of this same
   idea and don't need this plugin's help.

   The number is computed from the chart's own first dataset
   (data.datasets[0].data summed) — never duplicated from/passed in by
   PHP, so it can never drift out of sync with what the chart itself is
   actually showing, and keeps every one of those 14+ files' own
   Chart.js config blocks completely unchanged. Skips drawing entirely
   for an all-zero dataset (an empty/"No entries" chart), since a bare
   "0" sitting in the middle of an otherwise-empty ring would just be
   noise, not useful information.

   SKIPS drawing entirely when the canvas already has a real sibling
   total next to it — 13 of these pages (every analytics_reports.php,
   subadmin's own consultation/inventory/patient/stock-request/vaccine
   report pages, admin's account_monitoring.php/admin_dashboard.php)
   already render their own real total as plain HTML
   (.adm-donut-center > .adm-donut-total/.adm-donut-total-label,
   CSS-positioned directly over the canvas, same real number this
   plugin would also compute) — this plugin's whole point was filling
   that gap on the pages that DON'T already have it (the per-role
   dashboards' own doughnut cards, which really did have an empty
   hole); on a page that already does, drawing a second copy painted
   it right on top of the existing HTML one, reading as doubled/
   smeared text rather than either number being wrong. Detected
   per-canvas via its own DOM, not a hardcoded page list, so this stays
   correct automatically if a page's markup changes later.
   ============================================================= */
(function () {
    if (typeof Chart === 'undefined') return;

    function hasHtmlTotal(chart) {
        var canvasParent = chart.canvas && chart.canvas.parentElement;
        // .an-donut-total is Facility Analytics' own HTML total (same idea, "an-" prefix)
        return !!(canvasParent && canvasParent.querySelector('.adm-donut-total, .an-donut-total'));
    }

    /*
     * Tooltip for doughnuts with an HTML total in the hole. Chart.js's
     * own tooltip is drawn INSIDE the canvas, and on these small
     * doughnuts it lands right in the hole — under the HTML total
     * (which sits above the canvas), so the two overlapped. This draws
     * the same tooltip content (title, colour swatch, label lines from
     * each chart's own callbacks) as an HTML box just OUTSIDE the ring,
     * past the hovered slice's outer edge, so the total stays fully
     * visible. Attached to <body> with position:fixed so no card's
     * overflow can clip it.
     */
    function externalDoughnutTooltip(context) {
        var chart = context.chart;
        var tooltip = context.tooltip;
        var el = chart.canvas.__hcDonutTooltip;
        if (!el) {
            el = document.createElement('div');
            el.setAttribute('role', 'tooltip');
            el.style.cssText = 'position:fixed;z-index:1080;pointer-events:none;opacity:0;transition:opacity .12s ease;' +
                'background:rgba(17,24,39,.92);color:#fff;border-radius:8px;padding:7px 10px;font:500 12px/1.35 Poppins,-apple-system,Segoe UI,Roboto,Arial,sans-serif;' +
                'box-shadow:0 6px 18px rgba(0,0,0,.18);white-space:nowrap;';
            document.body.appendChild(el);
            chart.canvas.__hcDonutTooltip = el;
        }

        var point = tooltip.dataPoints && tooltip.dataPoints[0];
        if (tooltip.opacity === 0 || !point) {
            el.style.opacity = '0';
            return;
        }

        el.textContent = '';
        (tooltip.title || []).forEach(function (line) {
            var title = document.createElement('div');
            title.style.fontWeight = '700';
            title.textContent = line;
            el.appendChild(title);
        });
        (tooltip.body || []).forEach(function (bodyItem, i) {
            bodyItem.lines.forEach(function (line) {
                var row = document.createElement('div');
                row.style.cssText = 'display:flex;align-items:center;gap:6px;';
                var swatch = document.createElement('span');
                var colors = tooltip.labelColors && tooltip.labelColors[i];
                swatch.style.cssText = 'width:10px;height:10px;border-radius:2px;flex-shrink:0;border:1px solid rgba(255,255,255,.7);background:' +
                    ((colors && colors.backgroundColor) || '#999') + ';';
                row.appendChild(swatch);
                row.appendChild(document.createTextNode(line));
                el.appendChild(row);
            });
        });

        // Just past the hovered slice's outer edge, on the side it points to.
        var arc = point.element;
        var angle = (arc.startAngle + arc.endAngle) / 2;
        var radius = arc.outerRadius + 8;
        var rect = chart.canvas.getBoundingClientRect();
        var x = rect.left + arc.x + Math.cos(angle) * radius;
        var y = rect.top + arc.y + Math.sin(angle) * radius;
        var cos = Math.cos(angle);
        var sin = Math.sin(angle);
        var shiftX = Math.abs(cos) < 0.35 ? '-50%' : (cos > 0 ? '0' : '-100%');
        var shiftY = Math.abs(sin) < 0.35 ? '-50%' : (sin > 0 ? '0' : '-100%');
        el.style.left = x + 'px';
        el.style.top = y + 'px';
        el.style.transform = 'translate(' + shiftX + ',' + shiftY + ')';
        el.style.opacity = '1';
    }

    Chart.register({
        id: 'doughnutCenterTotal',
        // Swap Chart.js's in-canvas tooltip for the outside-the-ring one
        // above, only on doughnuts that have an HTML total in the hole.
        beforeInit: function (chart) {
            if (chart.config.type !== 'doughnut' || !hasHtmlTotal(chart)) return;
            var options = chart.config.options;
            options.plugins = options.plugins || {};
            options.plugins.tooltip = Object.assign({}, options.plugins.tooltip, { enabled: false, external: externalDoughnutTooltip });
        },
        afterDestroy: function (chart) {
            var el = chart.canvas && chart.canvas.__hcDonutTooltip;
            if (el && el.parentNode) el.parentNode.removeChild(el);
        },
        afterDraw: function (chart) {
            if (chart.config.type !== 'doughnut') return;

            if (hasHtmlTotal(chart)) return; // the page shows its own HTML total — see header comment

            var dataset = chart.data && chart.data.datasets && chart.data.datasets[0];
            if (!dataset || !Array.isArray(dataset.data)) return;

            var total = dataset.data.reduce(function (sum, v) { return sum + (Number(v) || 0); }, 0);
            if (total <= 0) return;

            var meta = chart.getDatasetMeta(0);
            var arc = meta && meta.data && meta.data[0];
            var chartArea = chart.chartArea;
            var x = arc ? arc.x : (chartArea.left + chartArea.right) / 2;
            var y = arc ? arc.y : (chartArea.top + chartArea.bottom) / 2;
            var outerRadius = arc ? arc.outerRadius : 60;
            var innerRadius = arc ? arc.innerRadius : outerRadius * 0.68;
            var fontSize = Math.max(14, Math.round(outerRadius * 0.32));

            var ctx = chart.ctx;
            ctx.save();

            // Wipe any stale text from an earlier afterDraw call before
            // painting this one — see header comment for why this is
            // necessary, not just defensive. Inscribed inside innerRadius
            // (not outerRadius), so this can never clear any part of the
            // ring itself.
            var clearHalfSize = innerRadius / Math.SQRT2;
            ctx.clearRect(x - clearHalfSize, y - clearHalfSize, clearHalfSize * 2, clearHalfSize * 2);

            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.font = '700 ' + fontSize + 'px -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif';
            ctx.fillStyle = '#1F2937';
            ctx.fillText(total.toLocaleString(), x, y);
            ctx.restore();
        },
    });
})();
