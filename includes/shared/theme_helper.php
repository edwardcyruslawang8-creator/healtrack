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
 *     to a live OS theme change while the page is open.
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

    return $script;
}
