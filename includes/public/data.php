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
$medicines = getMedicines();
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
$navItems = [
    ['icon' => 'bi-house-door-fill', 'label' => 'Home',                          'href' => BASE_URL . '/index.php'],
    ['icon' => 'bi-search',          'label' => 'Search',                        'href' => BASE_URL . '/patient/search.php'],
    ['icon' => 'bi-capsule',         'label' => 'Medicine & Vaccine Availability','href' => BASE_URL . '/patient/medicine.php'],
    ['icon' => 'bi-geo-alt-fill',    'label' => 'GIS Mapping',                   'href' => BASE_URL . '/patient/gis.php'],
    ['icon' => 'bi-megaphone-fill',  'label' => 'Announcements',                 'href' => BASE_URL . '/patient/announcements.php'],
    ['icon' => 'bi-info-circle-fill','label' => 'About Us',                      'href' => BASE_URL . '/index.php#about-us'],
    ['icon' => 'bi-telephone-fill',  'label' => 'Contact Us',                    'href' => BASE_URL . '/index.php#site-footer'],
];
