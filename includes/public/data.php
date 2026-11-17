<?php
/**
 * =====================================================================
 * Page Data
 * ---------------------------------------------------------------------
 * Dashboard stats, medicines, and announcements are read live from
 * Firestore (see firebase/firestore.php for the query + schema). Nav
 * items and chat suggestions stay defined here since they're app
 * config, not content someone edits through the admin panel.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/constants.php'; // BASE_URL — used by $navItems below
require_once __DIR__ . '/../../firebase/firestore.php';

$dashboardStats = getDashboardStats();
// null (every real medicine/vaccine) rather than getMedicines()'s own
// 4-item default — the Home widget now slides between sets of 4
// instead of only ever showing one fixed set (index.php's own
// Medicine & Vaccine Availability card).
$medicines = getMedicines(null);
$announcements = getAnnouncements();

// Sidebar navigation. Hrefs are BASE_URL-anchored (see config/constants.php)
// so they resolve correctly whether the current page is at the project
// root (index.php) or inside a role folder like patient/. The active
// link is determined automatically in includes/sidebar.php by comparing
// each href's filename to the current page's filename.
// "Talk with NSHC" used to be a sidebar entry linking to its own page
// (patient/chatbot.php, now deleted) — it's a global floating widget on
// every page now instead (includes/shared/chatbot_widget.php, included
// from includes/shared/footer.php), so it no longer needs — or has — a
// page of its own to link to here.
// Services Offered (Home page, above Medicine & Vaccine Availability) —
// the 4 real service lines this clinic's staff pages already track
// (see includes/admin/facility_analytics_firestore.php's own
// $serviceOverview for the per-role icons; this card uses the Home
// page's own single-maroon-tint icon style instead — same as
// .med-item right below it — so every tile reads as one consistent
// set rather than mixing in that other widget's own 4-color palette).
// Bootstrap Icons has no actual x-ray/radiology glyph at all, and
// nothing close enough reads as "x-ray" on its own — X-ray uses a
// custom inline SVG instead ('icon' => 'xray-custom', rendered by
// index.php's own special case, markup in
// includes/shared/xray_icon_svg.php) recolored with this SAME page's
// own theme variables (var(--maroon) for the body silhouette,
// var(--maroon-tint) for the "visible through" ribcage lines — the
// exact two colors every other icon-circle on this page already uses,
// not a new palette), rather than a generic/inaccurate Bootstrap icon.
// Static app config, same reasoning as $navItems below — nothing here
// is subadmin-editable content.
$servicesOffered = [
    ['icon' => 'bi-clipboard2-pulse-fill', 'name' => 'Medical Consultation'],
    ['icon' => 'bi-droplet-fill',          'name' => 'Laboratory Test'],
    ['icon' => 'xray-custom',              'name' => 'X-ray'],
    ['icon' => 'bi-capsule',               'name' => 'Pharmacy'],
];

$navItems = [
    ['icon' => 'bi-house-door-fill', 'label' => 'Home',                          'href' => BASE_URL . '/index.php'],
    ['icon' => 'bi-search',          'label' => 'Search',                        'href' => BASE_URL . '/patient/search.php'],
    ['icon' => 'bi-capsule',         'label' => 'Medicine & Vaccine Availability','href' => BASE_URL . '/patient/medicine.php'],
    ['icon' => 'bi-geo-alt-fill',    'label' => 'GIS Mapping',                   'href' => BASE_URL . '/patient/gis.php'],
    ['icon' => 'bi-megaphone-fill',  'label' => 'Announcements',                 'href' => BASE_URL . '/patient/announcements.php'],
    ['icon' => 'bi-info-circle-fill','label' => 'About Us',                      'href' => BASE_URL . '/index.php#about-us'],
    ['icon' => 'bi-telephone-fill',  'label' => 'Contact Us',                    'href' => BASE_URL . '/index.php#site-footer'],
];
