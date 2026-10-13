/* =============================================================
   LIVE CLOCK (.adm-datetime-widget)
   ---------------------------------------------------------------
   admin_dashboard.php, facility_management.php, facility_analytics.php,
   and profile_settings.php each show a small "date | time" pill in the
   topbar. PHP renders a correct value for the initial page load (see
   each page's <?= date(...) ?> calls); this keeps it accurate afterward
   by re-rendering from the browser's own clock every 30 seconds,
   without needing a page reload.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const widgets = document.querySelectorAll('.adm-datetime-widget');
    if (!widgets.length) return;

    function render() {
        const now = new Date();
        const dateStr = now.toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
        const timeStr = now.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', hour12: true });

        widgets.forEach(function (widget) {
            const dateEl = widget.querySelector('.adm-dt-date');
            const timeEl = widget.querySelector('.adm-dt-time');
            if (dateEl) dateEl.textContent = dateStr;
            if (timeEl) timeEl.textContent = timeStr;
        });
    }

    render();
    setInterval(render, 30000);
});
