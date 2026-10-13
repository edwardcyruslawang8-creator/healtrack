/* =============================================================
   NORTH SUPER HEALTH CENTER — BASE JS
   ---------------------------------------------------------------
   Loaded on every page (see includes/footer.php). Only the sidebar
   off-canvas toggle lives here since it's the one behavior every
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

});
