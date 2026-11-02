/* =============================================================
   MEDICAL HISTORY REPORTS JS
   (subadmin/medical_history_reports.php, admin/facility_analytics.php's
   Medical History tab)
   ---------------------------------------------------------------
   Draws the charts of includes/shared/_medical_history_summary_view.php
   from window.MEDICAL_HISTORY_DATA:
     - one horizontal ranked bar per category (conditions, allergies,
       surgeries, family history) — unique patients per label;
     - a stacked horizontal bar per barangay for conditions and
       allergies (top 5 labels as series).
   Wrapped in window.initReportPageCharts() like every sibling report
   page, so assets/js/ajax-report-nav.js can rebuild the charts after a
   filter swap. Old Chart instances are destroyed first.
   ============================================================= */
var __medicalHistoryCharts = []; // var, not let: ajax-tab-nav.js re-injects this file on each tab visit

function initReportPageCharts() {
    __medicalHistoryCharts.forEach(function (c) { c.destroy(); });
    __medicalHistoryCharts = [];

    // This page's charts only — see consultation_reports.js for why.
    if (!document.querySelector('[id^="mhChart-"], [id^="mhBarangayChart-"]')) return;

    const data = window.MEDICAL_HISTORY_DATA;
    if (!data || typeof Chart === 'undefined') return;

    const rootStyle = getComputedStyle(document.documentElement);
    const gridColor = rootStyle.getPropertyValue('--adm-border').trim() || '#ECEDF3';
    const tickColor = rootStyle.getPropertyValue('--adm-text-muted').trim() || '#6B7280';
    const surface = rootStyle.getPropertyValue('--adm-card').trim() || '#FFFFFF';

    // One hue per category; the barangay series use the app's usual
    // categorical order (same colors as the report doughnuts).
    const categoryColors = { conditions: '#D93025', allergies: '#E8710A', surgeries: '#8B5CF6', family: '#1A73E8' };
    const seriesColors = ['#1A73E8', '#E8710A', '#1E8E3E', '#8B5CF6', '#12A6C6'];

    function patientsLabel(n) { return n + (n === 1 ? ' patient' : ' patients'); }

    Object.keys(data.charts || {}).forEach(function (key) {
        const canvas = document.getElementById('mhChart-' + key);
        const set = data.charts[key];
        if (!canvas || !set.values.length) return;

        __medicalHistoryCharts.push(new Chart(canvas, {
            type: 'bar',
            data: {
                labels: set.labels,
                datasets: [{
                    data: set.values,
                    backgroundColor: categoryColors[key] || '#1A73E8',
                    borderRadius: 4,
                    borderSkipped: 'start',
                    barThickness: 16,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return patientsLabel(ctx.parsed.x) + ' (' + set.pcts[ctx.dataIndex] + '% of patients)';
                            },
                        },
                    },
                },
                scales: {
                    x: { beginAtZero: true, ticks: { color: tickColor, precision: 0 }, grid: { color: gridColor } },
                    y: { ticks: { color: tickColor, autoSkip: false }, grid: { display: false } },
                },
            },
        }));
    });

    Object.keys(data.barangay || {}).forEach(function (key) {
        const canvas = document.getElementById('mhBarangayChart-' + key);
        const set = data.barangay[key];
        if (!canvas || !set.labels.length) return;

        __medicalHistoryCharts.push(new Chart(canvas, {
            type: 'bar',
            data: {
                labels: set.labels,
                datasets: set.series.map(function (s, i) {
                    return {
                        label: s.label,
                        data: s.values,
                        backgroundColor: seriesColors[i % seriesColors.length],
                        borderColor: surface,
                        borderWidth: 1,
                        barThickness: 16,
                    };
                }),
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', align: 'start', labels: { color: tickColor, boxWidth: 10, boxHeight: 10, font: { size: 11 } } },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function (ctx) { return ctx.dataset.label + ': ' + patientsLabel(ctx.parsed.x); },
                        },
                    },
                },
                scales: {
                    x: { stacked: true, beginAtZero: true, ticks: { color: tickColor, precision: 0 }, grid: { color: gridColor } },
                    y: { stacked: true, ticks: { color: tickColor, autoSkip: false }, grid: { display: false } },
                },
            },
        }));
    });
}

window.initReportPageCharts = initReportPageCharts;
document.addEventListener('DOMContentLoaded', initReportPageCharts);
