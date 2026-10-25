<?php
/**
 * =====================================================================
 * Shared — Live Notification Polling
 * ---------------------------------------------------------------------
 * Makes the topbar bell badge and sidebar "Notifications" nav badge
 * stay live on EVERY page across the 7 roles with a real notification
 * system (admin, subadmin, doctor, healthcare_staff, medical_technician,
 * radio_technologist, pharmacist) — not just notifications.php itself
 * — without reloading the page, and pops a bottom-right toast
 * (assets/js/notification-poll.js, same visual idiom as
 * two_fa_reminder.php's own toast) the moment a genuinely NEW
 * notification shows up while the page is just sitting open.
 *
 * There's no shared header/sidebar include anywhere in this app — every
 * one of its 100+ page files renders its own copy of the sidebar/topbar
 * HTML — so touching every page individually to add a stable id to
 * both badges wasn't the approach here. Instead this reuses markup that
 * ALREADY exists identically (or near-identically) on every page
 * without a single page edit (admin was the one exception — it had no
 * bell/badge markup anywhere at all before this, so that role's own
 * pages DID need the same one-time markup addition every other role
 * already had from before this feature existed):
 *   - The topbar bell is the exact same ".adm-bell-btn"/".adm-badge-count"
 *     class pair on all 7 roles — the client script just needs that one
 *     class pair, no role-specific config.
 *   - The sidebar's own badge class DOES differ per role ("sad-nav-badge",
 *     "doc-nav-badge", "mst-nav-badge", "mt-nav-badge" x2, "phm-nav-badge")
 *     — every one of them happens to share the "nav-badge" substring
 *     though, so the client script finds it via
 *     `a[href="notifications.php"] span[class*="nav-badge"]` (that link
 *     is this role's own sidebar entry, unique per page); the correct
 *     class name to use if the span needs to be CREATED from scratch
 *     (count going 0 -> 1, where today's markup doesn't render the span
 *     at all) is handed over below as 'sidebarBadgeClass', since the
 *     client has no way to know that mapping on its own.
 *
 * Called from config/constants.php's existing app-wide ob_start()
 * callback (the one that already appends assets/js/idle-timeout.js and
 * the 2FA/phone reminder toasts before every signed-in page's </body>)
 * — one shared injection point instead of editing every page.
 * =====================================================================
 */

if (!function_exists('hc_render_notification_poll_script')) {
    /**
     * Returns the <script> tags to inject, or '' for a role with no
     * real notification system to poll (patient, anything not in the
     * map below).
     *
     * Depends on config/constants.php's 7 role-specific
     * *_unread_notification_count() functions and
     * hc_unread_notification_count_for_role() — safe to call only from
     * that file's own ob_start() callback (runs at shutdown, by which
     * point the whole script, including the rest of constants.php, has
     * already executed and defined them), same precondition
     * hc_render_2fa_reminder_toast()/hc_render_phone_reminder_toast()
     * already rely on there.
     */
    function hc_render_notification_poll_script(): string
    {
        $eligibleRoleFolders = [
            'admin' => ['folder' => 'admin', 'badgeClass' => 'adm-nav-badge'],
            'subadmin' => ['folder' => 'subadmin', 'badgeClass' => 'sad-nav-badge'],
            'doctor' => ['folder' => 'doctor', 'badgeClass' => 'doc-nav-badge'],
            'healthcare_staff' => ['folder' => 'medical-staff', 'badgeClass' => 'mst-nav-badge'],
            'medical_technician' => ['folder' => 'medical_technician', 'badgeClass' => 'mt-nav-badge'],
            'radio_technologist' => ['folder' => 'radiologic_technologist', 'badgeClass' => 'mt-nav-badge'],
            'pharmacist' => ['folder' => 'pharmacist', 'badgeClass' => 'phm-nav-badge'],
        ];

        $uid = $_SESSION['uid'] ?? '';
        $role = $_SESSION['role'] ?? '';
        if ($uid === '' || !isset($eligibleRoleFolders[$role])) {
            return '';
        }

        $roleMeta = $eligibleRoleFolders[$role];

        $config = [
            'endpointUrl' => BASE_URL . '/api/get-unread-count.php',
            'notificationsUrl' => BASE_URL . '/' . $roleMeta['folder'] . '/notifications.php',
            'sidebarBadgeClass' => $roleMeta['badgeClass'],
            // Seeds the client's running count from the SAME real value
            // this exact page already rendered into the badges server-side
            // — without this, the first poll's result would look like a
            // brand-new notification arrived even when nothing actually
            // changed since page load.
            'initialUnreadCount' => hc_unread_notification_count_for_role($role),
            'intervalMs' => 20000,
        ];

        return '<script>window.HC_NOTIF_POLL = ' . json_encode($config) . ';</script>'
            . '<script src="' . asset_url('/assets/js/notification-poll.js') . '"></script>';
    }
}
