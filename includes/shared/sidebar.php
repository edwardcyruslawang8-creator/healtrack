<?php
/**
 * Sidebar include — logo, primary navigation, Operating Hours card.
 * Expects $navItems to be defined (see includes/data.php).
 *
 * The active link is whichever item's href matches the current page's
 * filename, so this same include works unchanged on index.php,
 * search.php, and every other page in the system.
 *
 * Operating Hours here reads the same real site_settings/homepage doc
 * as index.php's footer (both via hc_get_homepage_settings()) — required
 * directly rather than assumed-in-scope, since this include is shared
 * across several pages that don't all already load it themselves.
 */
require_once __DIR__ . '/homepage_settings.php';
$sidebarOperatingHours = hc_get_homepage_settings()['operatingHours'];
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<aside class="sidebar" id="sidebar">

    <div class="sidebar-brand">
        <div class="logo-badge"><i class="bi bi-shield-fill-plus"></i></div>
        <div class="brand-text">
            <h1>NORTH SUPER<br>HEALTH CENTER</h1>
            <p>Healthcare Management System</p>
        </div>
    </div>

    <ul class="sidebar-nav">
        <?php foreach ($navItems as $item):
            // Anchor links (e.g. index.php#about-us) point at a section on a
            // page rather than a distinct page of their own, so they never
            // get the "active" highlight — only real pages do. Also tagged
            // "nav-link-anchor" so assets/js/base.js can force a real full
            // page reload when clicked while already on that same page
            // (a same-document hash change never reloads on its own,
            // which otherwise reads as "this nav item does nothing" next
            // to every other item, which performs a real navigation).
            $isAnchorLink = (strpos($item['href'], '#') !== false);
            $isActive = !$isAnchorLink && (basename($item['href']) === $currentPage);
        ?>
        <li>
            <a href="<?= htmlspecialchars($item['href']) ?>"
               class="nav-link <?= $isActive ? 'active' : '' ?> <?= $isAnchorLink ? 'nav-link-anchor' : '' ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="sidebar-hours">
        <div class="hours-title">
            <i class="bi bi-clock-fill"></i>
            <span>Operating Hours</span>
        </div>
        <?php foreach ($sidebarOperatingHours as $row): ?>
        <?php $isClosed = (strcasecmp(trim((string) $row['hours']), 'Closed') === 0); ?>
        <div class="hours-row">
            <span class="hours-day"><?= htmlspecialchars($row['label']) ?></span>
            <?php if ($isClosed): ?>
            <span class="closed">CLOSED</span>
            <?php else: ?>
            <span class="hours-time"><?= htmlspecialchars($row['hours']) ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</aside>
