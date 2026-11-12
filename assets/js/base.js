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

    // body.sidebar-open lets base.css move the toggle off the sidebar's
    // logo (it becomes a close button beside the sidebar) and hide the
    // floating chatbot launcher, which otherwise sits on top of it.
    const sidebarToggleIcon = sidebarToggle ? sidebarToggle.querySelector('i') : null;
    function openSidebar() {
        sidebar.classList.add('show');
        sidebarBackdrop.classList.add('show');
        document.body.classList.add('sidebar-open');
        if (sidebarToggle) sidebarToggle.setAttribute('aria-label', 'Close menu');
        if (sidebarToggleIcon) sidebarToggleIcon.className = 'bi bi-x-lg';
    }
    function closeSidebar() {
        sidebar.classList.remove('show');
        sidebarBackdrop.classList.remove('show');
        document.body.classList.remove('sidebar-open');
        if (sidebarToggle) sidebarToggle.setAttribute('aria-label', 'Open menu');
        if (sidebarToggleIcon) sidebarToggleIcon.className = 'bi bi-list';
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
       hrefs) — excluded from the plain nav-link handler above (its own
       ":not(.nav-link-anchor)" selector) so they always land here
       instead, on EITHER page: from a different page this is a real
       navigation to index.php (navigateWithFade() below, same fade
       every other nav link already gets). From index.php itself, just a
       smooth scroll to the target section — no page reload. A reload
       was tried here before purely so this link's click "felt" the same
       as every other nav item's full navigation, but that cost a full
       re-fetch of the whole home page (dashboard stats, the GIS preview
       map's own Firestore-backed API call, the medicine slider) just to
       scroll down a page already on screen; a smooth scroll already
       reads as its own real feedback without any of that. */
    document.querySelectorAll('.nav-link-anchor').forEach(function (link) {
        link.addEventListener('click', function (e) {
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            if (link.target === '_blank' || !link.href) return;

            const url = new URL(link.getAttribute('href'), window.location.href);
            if (url.pathname !== window.location.pathname) {
                e.preventDefault();
                navigateWithFade(link.href);
                return;
            }
            const target = document.querySelector(url.hash);
            if (!target) return; // let the browser fall back to its own default handling

            e.preventDefault();
            history.pushState(null, '', url.hash);
            target.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'start' });
        });
    });

});
