<?php
/**
 * =====================================================================
 * NORTH SUPER HEALTH CENTER — Healthcare Management System
 * Home Dashboard (index.php)
 * ---------------------------------------------------------------------
 * CONNECTED to real Firestore data — includes/public/data.php reads
 * $dashboardStats, $medicines, and $announcements live via
 * firebase/firestore.php (config/firebase.php holds the connection).
 * $navItems stays defined in that file since it's app config, not
 * Firestore content. The GIS Mapping preview (assets/js/home-gis-preview.js)
 * is real too — see that file's own header comment. "Talk with NSHC"
 * used to be an embedded panel on this page (and a separate full page,
 * patient/chatbot.php) — it's the global floating widget on every page
 * now instead (includes/shared/chatbot_widget.php, included from
 * includes/shared/footer.php), so there's no chatbot-specific markup
 * or script on this page anymore.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/config/constants.php';     // BASE_URL
require_once __DIR__ . '/includes/public/data.php'; // $dashboardStats, $medicines, $announcements, $navItems
require_once __DIR__ . '/includes/shared/homepage_settings.php'; // hc_get_homepage_settings()
require_once __DIR__ . '/includes/firestore/facilities.php'; // hc_get_all_facilities_summary()

// Every real pinned facility (admin/facility_management.php's Add
// Facility map picker) — shown on this page's GIS preview below
// regardless of isIntegrated status. A patient visiting the homepage
// should be able to see where every facility actually is, even one
// that doesn't have the system running yet — "integrated" only governs
// whether the ADMIN can see that facility's own analytics, it has
// nothing to do with whether the public should know it exists.
$facilityMarkers = hc_get_all_facilities_summary();

// Mission/Vision/Community First (About section) and Contact Us/Quick
// Links/Operating Hours (footer) below — real, subadmin-editable
// content (subadmin/homepage_settings.php -> api/update-homepage-settings.php),
// not hardcoded HTML anymore.
$homepageSettings = hc_get_homepage_settings();

$pageTitle   = 'Home';
$pageCss     = ['home.css', 'quick-search.css'];
$pageScripts = ['quick-search.js', 'home-gis-preview.js'];
// Leaflet, loaded only for this page's small GIS preview widget — same
// library/version patient/gis.php's own full map uses, so the two stay
// visually identical (real barangay shapes/colors, not a hand-drawn
// mockup — see home-gis-preview.js's header comment).
$externalCss = ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'];
$externalJs  = ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'];
require_once __DIR__ . '/includes/shared/header.php';
require_once __DIR__ . '/includes/shared/sidebar.php';
?>

<div class="main-wrapper">
<main class="main-content">
<div class="dashboard-grid">

    <!-- ============ LEFT COLUMN (~73%) ============ -->
    <div class="col-main">

        <!-- HERO -->
        <div class="hero-panel">
            <div class="hero-inner">
                <div class="hero-text">
                    <p class="eyebrow">Welcome to</p>
                    <h1>North Super Health Center</h1>
                    <p class="lead-desc">Your partner in better health. Access healthcare services, check medicine availability, and stay informed.</p>

                    <form class="hero-search" role="search" action="<?= BASE_URL ?>/patient/search.php" method="get">
                        <div class="qs-field">
                            <i class="bi bi-search"></i>
                            <input type="text" name="q" placeholder="Search for services, medicines, vaccines, announcements..." autocomplete="off" data-quick-search="<?= BASE_URL ?>/api/quick-search-patient.php?scope=all">
                        </div>
                        <button type="submit">Search</button>
                    </form>
                </div>
                <div class="hero-image">
                    <img src="<?= asset_url('/assets/images/photos/hospital-photo.jpg') ?>" alt="North Super Health Center — front entrance">
                </div>
            </div>
        </div>

        <!-- STATISTICS -->
        <div class="row g-3 mb-1">
            <?php foreach ($dashboardStats as $s): ?>
            <div class="col-md-4">
                <div class="stat-mini-card">
                    <div class="icon-circle"><i class="bi <?= htmlspecialchars($s['icon']) ?>"></i></div>
                    <div>
                        <div class="stat-number"><?= htmlspecialchars($s['value']) ?></div>
                        <div class="stat-text"><?= htmlspecialchars($s['title']) ?><br><?= htmlspecialchars($s['sub']) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="mb-3"></div>

        <!-- MEDICINE & VACCINE AVAILABILITY -->
        <div class="panel">
            <div class="card-header-bar">
                <h2 class="section-title"><i class="bi bi-capsule"></i> Medicine &amp; Vaccine Availability</h2>
                <a href="<?= BASE_URL ?>/patient/medicine.php" class="view-all">View All <i class="bi bi-chevron-right"></i></a>
            </div>
            <div class="row g-0 px-2 pt-3">
                <?php foreach ($medicines as $m): ?>
                <div class="col-6 col-md-3">
                    <div class="med-item">
                        <div class="icon-circle"><i class="bi <?= htmlspecialchars($m['icon']) ?>"></i></div>
                        <div class="med-name"><?= htmlspecialchars($m['name']) ?></div>
                        <?php if ($m['status'] === 'available'): ?>
                            <span class="status-badge available"><i class="bi bi-check-lg"></i> Available</span>
                        <?php else: ?>
                            <span class="status-badge unavailable"><i class="bi bi-x-lg"></i> Not Available</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="med-notice">
                <i class="bi bi-info-circle-fill"></i>
                <span>Availability is updated in real-time.</span>
            </div>
        </div>

        <!-- LATEST ANNOUNCEMENTS -->
        <div class="panel">
            <div class="card-header-bar">
                <h2 class="section-title"><i class="bi bi-megaphone-fill"></i> Latest Announcements</h2>
                <a href="announcements.php" class="view-all">View All <i class="bi bi-chevron-right"></i></a>
            </div>
            <div>
                <?php foreach ($announcements as $a): ?>
                <div class="announcement-item">
                    <div class="ann-icon <?= htmlspecialchars($a['color']) ?>"><i class="bi <?= htmlspecialchars($a['icon']) ?>"></i></div>
                    <div class="ann-content">
                        <div class="ann-title"><?= htmlspecialchars($a['title']) ?></div>
                        <p class="ann-desc"><?= htmlspecialchars($a['desc']) ?></p>
                    </div>
                    <div class="ann-date"><?= htmlspecialchars($a['date']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
    <!-- ============ /LEFT COLUMN ============ -->

    <!-- ============ RIGHT COLUMN (~27%) ============ -->
    <div class="col-side">

        <!-- GIS MAPPING -->
        <div class="gis-panel">
            <div class="card-header-bar">
                <h2 class="section-title"><i class="bi bi-geo-alt-fill"></i> GIS Mapping: Disease Cases Overview</h2>
            </div>
            <div class="gis-map-area">
                <div id="homeGisMap"></div>
            </div>
            <script>window.GIS_FACILITIES = <?= json_encode($facilityMarkers) ?>;</script>
            <!-- Same 5-tier scheme/colors as patient/gis.php's own legend
                 (assets/data/barangays.geojson's real "intensity"/"color"
                 values) — kept in sync with that page on purpose. A slim
                 strip below the map rather than an overlay on top of it:
                 real barangay shapes (unlike the old hand-drawn mockup)
                 don't leave a clean empty corner to float a legend box
                 over without covering part of the map. -->
            <div class="gis-legend-strip">
                <span class="legend-item"><span class="dot" style="background:#D93025;"></span> Very High</span>
                <span class="legend-item"><span class="dot" style="background:#E8710A;"></span> High</span>
                <span class="legend-item"><span class="dot" style="background:#E8B800;"></span> Moderate</span>
                <span class="legend-item"><span class="dot" style="background:#1E8E3E;"></span> Low</span>
                <span class="legend-item"><span class="dot" style="background:#9CA3AF;"></span> No Data</span>
            </div>
            <div class="panel-footer">
                <a href="<?= BASE_URL ?>/patient/gis.php" class="btn-view-map">View Full Map <i class="bi bi-box-arrow-up-right"></i></a>
            </div>
        </div>

    </div>
    <!-- ============ /RIGHT COLUMN ============
         "Talk with NSHC" used to have its own panel here — it's the
         global floating widget on every page now instead (see
         includes/shared/chatbot_widget.php). ============ -->

</div>

<!-- =============================================================
     ABOUT US
     ============================================================= -->
<section id="about-us" class="about-section">
    <h2>About North Super Health Center</h2>
    <p class="about-lead">
        North Super Health Center is a community-based healthcare facility committed to providing
        accessible, reliable, and compassionate care to every barangay we serve. From general
        consultations and vaccinations to medicine availability and health advisories, our team
        works to keep our community informed and well cared for.
    </p>
    <div class="about-grid">
        <div class="about-item">
            <div class="about-icon"><i class="bi bi-bullseye"></i></div>
            <h3>Our Mission</h3>
            <p><?= htmlspecialchars($homepageSettings['missionText']) ?></p>
        </div>
        <div class="about-item">
            <div class="about-icon"><i class="bi bi-eye-fill"></i></div>
            <h3>Our Vision</h3>
            <p><?= htmlspecialchars($homepageSettings['visionText']) ?></p>
        </div>
        <div class="about-item">
            <div class="about-icon"><i class="bi bi-people-fill"></i></div>
            <h3>Community First</h3>
            <p><?= htmlspecialchars($homepageSettings['communityFirstText']) ?></p>
        </div>
    </div>
</section>

</main>
</div>

<!-- =============================================================
     SITE FOOTER (Contact Us lives here)
     ============================================================= -->
<footer id="site-footer" class="site-footer">
    <div class="footer-inner">
        <div class="footer-grid">

            <div class="footer-col footer-brand">
                <div class="footer-logo">
                    <div class="logo-badge-sm"><i class="bi bi-shield-fill-plus"></i></div>
                    <span>North Super<br>Health Center</span>
                </div>
                <p>Your partner in better health, serving our community with accessible and reliable care.</p>
            </div>

            <div class="footer-col">
                <h4>Contact Us</h4>
                <p><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($homepageSettings['contactAddress']) ?></p>
                <p><i class="bi bi-telephone-fill"></i> <?= htmlspecialchars($homepageSettings['contactPhone']) ?></p>
                <p><i class="bi bi-envelope-fill"></i> <?= htmlspecialchars($homepageSettings['contactEmail']) ?></p>
            </div>

            <div class="footer-col">
                <h4>Operating Hours</h4>
                <?php foreach ($homepageSettings['operatingHours'] as $row): ?>
                <p><?= htmlspecialchars($row['label']) ?>: <?= htmlspecialchars($row['hours']) ?></p>
                <?php endforeach; ?>
            </div>

        </div>
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> North Super Health Center. All rights reserved.
        </div>
    </div>
</footer>

<?php require_once __DIR__ . '/includes/shared/footer.php'; ?>
