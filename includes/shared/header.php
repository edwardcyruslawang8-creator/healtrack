<?php
/**
 * Header include — <head> and opening <body>.
 * Included at the top of every page in the system.
 */
require_once __DIR__ . '/../../config/constants.php'; // BASE_URL — used below for asset paths
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>North Super Health Center</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<?php
// Optional per-page external stylesheets (e.g. Leaflet on the GIS page).
// Set $externalCss = ['https://example.com/lib.css']; before requiring this file.
if (!empty($externalCss)) {
    foreach ($externalCss as $cssUrl) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars($cssUrl) . '">' . "\n";
    }
}
?>

<!-- Base stylesheet (shared: variables, sidebar, layout shell, cross-page atoms) -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/base.css') ?>">
<!-- Global floating chatbot widget (includes/shared/chatbot_widget.php,
     included unconditionally from footer.php below) — loaded here,
     also unconditionally, so every public page gets it regardless of
     that page's own $pageCss. -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/chatbot-widget.css') ?>">
<!-- Shared confirm/alert dialog (assets/js/hc-dialogs.js, loaded from
     footer.php) — explicitly designed to work on both admin-family and
     plain patient-facing pages, see that stylesheet's own header
     comment. Used by assets/js/search-form-guard.js's empty-search
     error on this page's own search bar(s). -->
<link rel="stylesheet" href="<?= asset_url('/assets/css/hc-dialogs.css') ?>">

<script>window.APP_BASE_URL = "<?= BASE_URL ?>";</script>

<?php
// Page-specific stylesheet(s), e.g. 'home.css', 'search.css', 'medicine.css',
// 'gis.css'. Set $pageCss = 'home.css'; (a single page normally needs just
// one) or $pageCss = ['home.css', 'quick-search.css']; (array, for a page
// that also needs a shared cross-page stylesheet like quick-search.css)
// before requiring this file. Uses asset_url() (not a raw BASE_URL path)
// so every link carries a "?v=<filemtime>" cache-buster — without it, a
// browser that already cached an old copy of a shared file like
// quick-search.css has no signal that it changed and keeps serving the
// stale version indefinitely, same reasoning every staff-side page
// already followed for its own asset_url() calls.
if (!empty($pageCss)) {
    foreach ((array) $pageCss as $cssFile) {
        echo '<link rel="stylesheet" href="' . asset_url('/assets/css/' . $cssFile) . '">' . "\n";
    }
}
?>
</head>
<body>

<!-- Mobile sidebar trigger (hidden on desktop, matches reference exactly there) -->
<button class="mobile-menu-btn" id="sidebarToggle" aria-label="Open menu">
    <i class="bi bi-list"></i>
</button>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
