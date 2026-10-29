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

    Chart.register({
        id: 'doughnutCenterTotal',
        afterDraw: function (chart) {
            if (chart.config.type !== 'doughnut') return;

            var canvasParent = chart.canvas && chart.canvas.parentElement;
            // .an-donut-total is Facility Analytics' own HTML total (same idea, "an-" prefix)
            if (canvasParent && canvasParent.querySelector('.adm-donut-total, .an-donut-total')) return;

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
