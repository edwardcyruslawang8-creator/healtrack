/* =============================================================
   NORTH SUPER HEALTH CENTER — BASE JS
   ---------------------------------------------------------------
   Loaded on every page (see includes/footer.php): the sidebar
   off-canvas toggle, and the sidebar nav's page-transition fade (real
   full page loads, just a softer visual handoff between them — same
   pattern assets/js/admin-sidebar-toggle.js already uses for every
   admin/subadmin/doctor/pharmacist/etc. page) — the one behavior every
   page needs. Page-specific interactions live in their own file:
     - assets/js/home.js      → index.php (chatbot widget)
     - assets/js/search.js    → patient/search.php
     - assets/js/medicine.js  → patient/medicine.php
     - assets/js/gis.js       → patient/gis.php (Leaflet map)
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {

    /* ---------- Off-canvas sidebar (tablet/mobile) ---------- */
    const sidebar         = document.getElementById('sidebar');
    const sidebarToggle   = document.getElementById('sidebarToggle');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');

    function openSidebar() {
        sidebar.classList.add('show');
        sidebarBackdrop.classList.add('show');
    }
    function closeSidebar() {
        sidebar.classList.remove('show');
        sidebarBackdrop.classList.remove('show');
    }
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.contains('show') ? closeSidebar() : openSidebar();
        });
    }
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', closeSidebar);
    }

    /* ---------- Sidebar navigation fade-out (content area only) ----------
       Same pattern assets/js/admin-sidebar-toggle.js already uses for
       every admin/subadmin/doctor/pharmacist/etc. page: clicking a real
       nav link fades .main-content out first, then lets the browser
       navigate normally — a real page load still happens (this isn't
       AJAX/SPA navigation), just a softer visual handoff between two
       full page loads. .sidebar is never touched by this — fixed-
       position and identical on every page, so with this transition in
       place it reads as a stable frame around the content rather than
       something that "disappears" during an abrupt, untransitioned
       navigation. */
    const mainEl = document.querySelector('.main-content');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function navigateWithFade(destination) {
        if (!mainEl || prefersReducedMotion) {
            window.location.href = destination;
            return;
        }
        mainEl.classList.add('page-leaving');
        // Kept in sync with .page-leaving's own transition duration in
        // assets/css/base.css.
        setTimeout(function () {
            window.location.href = destination;
        }, 140);
    }

    document.querySelectorAll('.sidebar-nav .nav-link:not(.nav-link-anchor)').forEach(function (link) {
        link.addEventListener('click', function (e) {
            // A modifier/middle-click means "open in a new tab" — never
            // intercept that.
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            if (link.target === '_blank' || !link.href) return;

            e.preventDefault();
            navigateWithFade(link.href);
        });
    });

    /* ---------- Anchor-link nav items (About Us / Contact Us) ----------
       These point at a section on index.php rather than a page of their
       own (includes/public/data.php's own "#about-us"/"#site-footer"
       hrefs). From a DIFFERENT page, clicking one is already a real
       navigation to index.php — handled by the fade-out above, same as
       any other nav link. But when already ON that page, a same-
       document hash change never triggers a browser reload by itself —
       it just scrolls — which reads as "this nav item does nothing"
       next to every other item, which performs a real navigation.
       Forcing a real (faded) reload here makes every nav item behave
       the same way. */
    document.querySelectorAll('.nav-link-anchor').forEach(function (link) {
        link.addEventListener('click', function (e) {
            const url = new URL(link.getAttribute('href'), window.location.href);
            if (url.pathname !== window.location.pathname) return; // a different page — already handled above
            e.preventDefault();

            if (!mainEl || prefersReducedMotion) {
                window.location.hash = url.hash;
                window.location.reload();
                return;
            }
            mainEl.classList.add('page-leaving');
            setTimeout(function () {
                window.location.hash = url.hash;
                window.location.reload();
            }, 140);
        });
    });

});
