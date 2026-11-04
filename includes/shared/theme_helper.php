<?php
/**
 * =====================================================================
 * Admin theme helpers
 * ---------------------------------------------------------------------
 * Reads $_SESSION['theme'] (Light/Dark/System — set at login in
 * api/session-login.php, kept in sync by api/update-profile-settings.php)
 * and renders what each admin/*.php page needs to apply it without a
 * flash of the wrong theme:
 *   - admin_theme_data_attr() -> the data-bs-theme attribute for <html>,
 *     resolved server-side for Light/Dark. Bootstrap 5.3+ reads this
 *     attribute itself to flip its own components (dropdowns, modals,
 *     form controls); assets/css/admin-dashboard.css's
 *     :root[data-bs-theme="dark"] block redefines the same --adm-*
 *     variables everything else here is built on.
 *   - admin_theme_head_script() -> a small inline <script>, placed as
 *     early as possible in <head>, that resolves "System" from the
 *     OS's prefers-color-scheme synchronously (before first paint) and
 *     exposes window.ADMIN_THEME_MODE for assets/js/theme.js to react
 *     to a live OS theme change while the page is open. Also emits the
 *     favicon <link> — every admin/*.php-style page already calls this
 *     function as the very first thing in <head>, so it doubles as the
 *     one shared injection point that reaches all of them without
 *     editing each page individually (includes/shared/header.php is
 *     the equivalent injection point for the plain patient-facing
 *     pages, which don't go through this function at all).
 *
 * Assumes session_start() already ran — every admin/*.php page calls
 * it before requiring its own *_data.php file, same as $_SESSION reads
 * elsewhere in this codebase.
 * =====================================================================
 */

function admin_theme_data_attr(): string
{
    return match ($_SESSION['theme'] ?? 'Light') {
        'Dark' => ' data-bs-theme="dark"',
        'System' => '', // resolved client-side below, before first paint
        default => ' data-bs-theme="light"',
    };
}

function admin_theme_head_script(): string
{
    $theme = htmlspecialchars($_SESSION['theme'] ?? 'Light', ENT_QUOTES);
    $script = '<script>window.ADMIN_THEME_MODE = "' . $theme . '";';
    if ($theme === 'System') {
        $script .= 'document.documentElement.setAttribute("data-bs-theme", (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) ? "dark" : "light");';
    }
    $script .= '</script>';

    $favicon = '<link rel="icon" type="image/svg+xml" href="' . asset_url('/assets/images/favicon.svg') . '">';

    return $favicon . $script;
}
