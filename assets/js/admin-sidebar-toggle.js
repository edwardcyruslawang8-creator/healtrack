/* =============================================================
   ADMIN SIDEBAR TOGGLE + PAGE TRANSITION
   (shared across every admin/subadmin/doctor/pharmacist/
   medical-staff/medical_technician page — every one of them loads
   this same file and shares the same .adm-sidebar/.adm-main shell)
   ---------------------------------------------------------------
   Mobile hamburger button + backdrop for the fixed .adm-sidebar.
   Extracted out of admin-dashboard.js so pages that don't need the
   dashboard's Chart.js code (e.g. facility_management.php) can still
   get the sidebar behavior without loading unrelated logic.

   Also drives the sidebar-navigation page transition (see
   assets/css/admin-dashboard.css's own comment on .adm-page-leaving):
   clicking a real .adm-nav-link fades the current page's .adm-main
   (content area only — the sidebar itself stays put) out first, then
   lets the browser navigate normally — a real page load still happens
   (this isn't AJAX/SPA navigation, just a softer visual handoff
   between two full page loads), so it works identically on every one
   of these 90 pages without any of them needing their own copy of
   this logic.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    // Bind once: assets/js/ajax-tab-nav.js re-dispatches DOMContentLoaded
    // after every tab swap, and these elements live outside the swapped
    // <main> — a second binding would make the menu toggle open+close.
    if (window.__admSidebarToggleBound) return;
    window.__admSidebarToggleBound = true;

    const sidebar = document.getElementById('admSidebar');
    const toggleBtn = document.getElementById('admSidebarToggle');
    const backdrop = document.getElementById('admSidebarBackdrop');

    function openSidebar() {
        sidebar.classList.add('show');
        backdrop.classList.add('show');
    }
    function closeSidebar() {
        sidebar.classList.remove('show');
        backdrop.classList.remove('show');
    }
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.contains('show') ? closeSidebar() : openSidebar();
        });
    }
    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    /* ---------- Sidebar navigation fade-out (content area only) ---------- */
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // :not(.adm-nav-toggle) — the sidebar dropdown's own toggle anchor
    // (e.g. Audit Logs > Sub Admin/User) has a same-page "#..." href
    // handled entirely by Bootstrap's collapse plugin; it never
    // performs a real navigation, so it must never hit the fade+
    // redirect logic below.
    document.querySelectorAll('.adm-nav-link:not(.adm-nav-toggle)').forEach(function (link) {
        link.addEventListener('click', function (e) {
            // A modifier/middle-click means "open in a new tab" — never
            // intercept that, or the new tab would open on the OLD page's
            // href before the delayed navigation below even runs.
            // Looked up per click: an AJAX tab swap replaces <main>.
            const mainEl = document.querySelector('.adm-main');
            if (!mainEl || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            if (link.target === '_blank' || !link.href) return;

            e.preventDefault();
            const destination = link.href;

            if (prefersReducedMotion) {
                window.location.href = destination;
                return;
            }

            mainEl.classList.add('adm-page-leaving');
            // Kept in sync with .adm-page-leaving's own transition
            // duration in assets/css/admin-dashboard.css — long enough
            // for that fade-out to actually be visible as a curve
            // instead of a flash, short enough the page change still
            // feels immediate.
            setTimeout(function () {
                window.location.href = destination;
            }, 140);
        });
    });
});
