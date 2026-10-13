<?php
/**
 * Footer include — closes the layout wrapper and loads scripts.
 */
require_once __DIR__ . '/../../config/constants.php'; // BASE_URL, asset_url() — used below for JS paths
?>
<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php
// Optional per-page external scripts (e.g. Leaflet on the GIS page).
// Set $externalJs = ['https://example.com/lib.js']; before requiring header.php.
if (!empty($externalJs)) {
    foreach ($externalJs as $jsUrl) {
        echo '<script src="' . htmlspecialchars($jsUrl) . '"></script>' . "\n";
    }
}
?>

<!-- Base JS (shared: sidebar off-canvas toggle) -->
<script src="<?= asset_url('/assets/js/base.js') ?>"></script>

<?php
// Page-specific script(s), e.g. 'home.js', 'search.js', 'medicine.js', 'gis.js'.
// Set $pageScripts = ['home.js']; (array, so a page can load more than one)
// before requiring header.php. Uses asset_url() so every script also
// carries a cache-buster — same reasoning as includes/shared/header.php's
// $pageCss loop.
if (!empty($pageScripts)) {
    foreach ($pageScripts as $jsFile) {
        echo '<script src="' . asset_url('/assets/js/' . $jsFile) . '"></script>' . "\n";
    }
}
?>

<!-- Global floating chatbot widget (includes/shared/chatbot_widget.php)
     — included unconditionally, same reasoning as base.js above: every
     public page gets the same one chat surface, not a per-page opt-in.
     Placed last (after Bootstrap's JS bundle, which its "How it works"/
     "Contact" modals need, and after any page-specific scripts) so
     nothing it depends on loads after it. -->
<?php require_once __DIR__ . '/chatbot_widget.php'; ?>
<script src="<?= asset_url('/assets/js/chatbot-history.js') ?>"></script>
<script src="<?= asset_url('/assets/js/chatbot-widget.js') ?>"></script>
<script src="<?= asset_url('/assets/js/hc-dialogs.js') ?>"></script>
<script src="<?= asset_url('/assets/js/search-form-guard.js') ?>"></script>
</body>
</html>
