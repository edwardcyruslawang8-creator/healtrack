/* =============================================================
   ADMIN THEME — live "System" switching
   ---------------------------------------------------------------
   The initial theme (including resolving "System" from the OS) is
   already applied before this file loads — see
   includes/theme_helper.php's admin_theme_head_script(), an inline
   <script> placed early in <head> specifically to avoid a flash of
   the wrong theme. This file only handles the case where the OS theme
   changes WHILE the page is open and the admin's preference is
   "System" — Light/Dark preferences are static and need nothing here.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    if (window.ADMIN_THEME_MODE !== 'System' || !window.matchMedia) return;

    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    function apply() {
        document.documentElement.setAttribute('data-bs-theme', mq.matches ? 'dark' : 'light');
    }

    if (mq.addEventListener) {
        mq.addEventListener('change', apply);
    } else if (mq.addListener) {
        mq.addListener(apply); // Safari < 14
    }
});
