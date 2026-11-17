<?php
/**
 * =====================================================================
 * Custom X-ray icon (Home page's Services Offered card)
 * ---------------------------------------------------------------------
 * Bootstrap Icons (the only icon set this app loads — see
 * includes/shared/header.php) has no x-ray/radiology glyph at all, so
 * this is a small hand-built inline SVG instead: a scan-viewfinder
 * frame (corner brackets) around a ribcage/spine, outlined entirely in
 * var(--maroon) — the exact same CSS variable every other icon-circle
 * on this page already uses (.med-item, .stat-mini-card), not a
 * separate palette invented just for this one icon. Inline (not an
 * <img>) specifically so that var() resolves against the page's real
 * theme instead of being baked into a static file.
 * =====================================================================
 */
?>
<svg viewBox="0 0 24 24" class="service-xray-svg" aria-hidden="true" fill="none" stroke="var(--maroon)" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
    <path d="M5 3 L3 3 L3 5"/>
    <path d="M19 3 L21 3 L21 5"/>
    <path d="M3 19 L3 21 L5 21"/>
    <path d="M21 19 L21 21 L19 21"/>
    <circle cx="12" cy="6.2" r="0.75" fill="var(--maroon)" stroke="none"/>
    <path d="M12 7.2 V18.2"/>
    <path d="M12 8.3 C10.6 8.3 9.6 9.1 9.3 10.3"/>
    <path d="M12 10.2 C10.1 10.2 8.6 11.3 8.1 12.8"/>
    <path d="M12 12.1 C9.8 12.1 7.9 13.5 7.3 15.2"/>
    <path d="M12 14 C9.4 14 7.1 15.7 6.4 17.8"/>
    <path d="M12 8.3 C13.4 8.3 14.4 9.1 14.7 10.3"/>
    <path d="M12 10.2 C13.9 10.2 15.4 11.3 15.9 12.8"/>
    <path d="M12 12.1 C14.2 12.1 16.1 13.5 16.7 15.2"/>
    <path d="M12 14 C14.6 14 16.9 15.7 17.6 17.8"/>
    <path d="M12 18.2 L9.6 20.4"/>
    <path d="M12 18.2 L14.4 20.4"/>
</svg>
