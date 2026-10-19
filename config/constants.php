<?php
/**
 * =====================================================================
 * App Constants
 * ---------------------------------------------------------------------
 * BASE_URL makes every link/asset path work the same whether a page
 * sits at the project root (index.php) or inside a role folder like
 * patient/, admin/, subadmin/, healthcare_staff/ (see the project's
 * folder structure). Every shared include (header, footer, sidebar)
 * and every cross-page link uses BASE_URL so nothing breaks no matter
 * how deep the current page lives.
 *
 * IMPORTANT: set this to match your local folder name under htdocs.
 * Right now the project sits at C:\xampp\htdocs\Healtrack, which is
 * served as http://localhost/Healtrack — so BASE_URL is '/Healtrack'.
 *
 *   - Renamed the folder?         change the string below to match.
 *   - Deploying to a live domain
 *     where the project IS the
 *     web root (e.g. https://northsuperhealthcenter.gov.ph/)?
 *                                  set BASE_URL to '' (empty string).
 * =====================================================================
 */

// This server's php.ini sets date.timezone=Europe/Berlin (XAMPP's
// default) — wrong for a clinic actually operating in the Philippines,
// and the real cause behind things like admin/account_monitoring.php's
// topbar clock/date showing several hours off from local time, and
// every other date()/time()-based display across this app silently
// inheriting the same wrong zone. Set once, here, since this file is
// already the one shared bootstrap every page requires first — fixing
// it in the shared XAMPP php.ini instead would affect every OTHER
// project on this machine too, not just this one.
date_default_timezone_set('Asia/Manila');

define('BASE_URL', '/Healtrack');

// "Remember me" — every real page requires this file right after its own
// session_start(), so this is the one place that can silently restore a
// full session from a long-lived remember-token cookie when the actual
// PHP session has none of its own (see includes/shared/remember_me.php's
// header comment for why the session cookie's own extended lifetime
// alone doesn't already handle this). A no-op, and doesn't touch
// Firestore at all, unless $_SESSION genuinely has no uid AND a remember
// cookie is actually present.
require_once __DIR__ . '/../includes/shared/remember_me.php';
hc_restore_session_from_remember_token();

// Idle-session auto-logout (30 min) — server-side, authoritative half.
// See includes/shared/idle_timeout.php's header comment for the full
// design; may redirect (and exit) right here if this session has been
// idle too long.
require_once __DIR__ . '/../includes/shared/idle_timeout.php';
hc_enforce_idle_timeout();

// Client-side half of the same mechanism — buffers this page's entire
// output so assets/js/idle-timeout.js can be appended right before
// </body> without editing every one of this app's 100+ page files
// individually. A no-op for anything that isn't a full HTML page (JSON
// from an api/*.php endpoint, an AJAX-fetched HTML fragment — neither
// contains a </body> tag) and for anyone not signed in.
//
// The same buffer also carries includes/shared/two_fa_reminder.php's
// bottom-right "enable 2FA" toast (see hc_render_2fa_reminder_toast())
// for the same reason — one shared injection point instead of editing
// every page, and it's a no-op string ('') the moment 2FA is enabled or
// for a role (patient) this doesn't apply to.
ob_start(function ($buffer) {
    if (($_SESSION['uid'] ?? '') === '' || strpos($buffer, '</body>') === false) {
        return $buffer;
    }
    $script = '<script>window.HC_IDLE_BASE_URL = ' . json_encode(BASE_URL) . ';</script>'
        . '<script src="' . asset_url('/assets/js/idle-timeout.js') . '"></script>';

    // This runs very late (PHP's own output-buffer-flush machinery, at
    // or near request shutdown), by which point Guzzle's cURL handles
    // (used by $firestore under the hood — see firebase/FirestoreRestClient.php)
    // have intermittently already been torn down on this Windows/Apache
    // mod_php setup — an observed, hard-to-eliminate environment flake
    // (also surfaces on its own as "Couldn't execute method
    // GuzzleHttp\Handler\CurlMultiHandler::__destruct" in the Apache
    // error log), not something a code-level fix here can prevent
    // outright. Without this try/catch, that one intermittent failure
    // took down the ENTIRE page — the whole buffered response, already
    // fully rendered, discarded in favor of a blank one — just to skip
    // showing a non-essential reminder toast. Caught and logged instead,
    // so this best-effort toast degrades gracefully on a bad request
    // rather than ever blanking a real page the admin/staff member
    // actually needed.
    try {
        require_once __DIR__ . '/../includes/shared/two_fa_reminder.php';
        $script .= hc_render_2fa_reminder_toast();
    } catch (\Throwable $e) {
        error_log('[2FA reminder toast] Skipped after a late-stage failure: ' . $e->getMessage());
    }

    return str_replace('</body>', $script . '</body>', $buffer);
});

/**
 * Appends a "?v=<last-modified-time>" cache-buster to a local asset path
 * (e.g. '/assets/css/admin-dashboard.css'), so browsers fetch the new
 * file the moment it changes on disk instead of serving a stale cached
 * copy until someone hard-refreshes.
 */
function asset_url(string $path): string
{
    $fsPath = __DIR__ . '/..' . $path;
    $version = file_exists($fsPath) ? filemtime($fsPath) : time();

    return BASE_URL . $path . '?v=' . $version;
}

/**
 * Unread notification count for every admin/*.php page's sidebar nav
 * badge + topbar bell badge — a System Administrator account has no
 * notification system of its own at all before this (admin/*.php had
 * no bell/badge anywhere), built specifically to carry the real 2FA
 * reminder (includes/shared/two_fa_reminder.php) — the one real source
 * this role has right now. Same $_SESSION['ntfReadIds']/
 * ['ntfDismissedIds'] mechanism every other role's own count function
 * already uses, so it's a real, extensible notification system (more
 * real sources can be merged in here later) rather than a one-off hack
 * scoped only to this one reminder.
 */
function hc_admin_unread_notification_count(): int
{
    require_once __DIR__ . '/../includes/shared/two_fa_reminder.php'; // hc_my_2fa_reminder_notification()
    require_once __DIR__ . '/../includes/shared/notification_preferences.php'; // hc_notification_preference_enabled()

    if (!hc_notification_preference_enabled('system')) {
        return 0;
    }

    $readIds = $_SESSION['ntfReadIds'] ?? [];
    $dismissedIds = $_SESSION['ntfDismissedIds'] ?? [];
    $allIds = array_column(array_filter([hc_my_2fa_reminder_notification()]), 'id');

    return count(array_diff($allIds, $readIds, $dismissedIds));
}

/**
 * The ids flagged unread=true in includes/notifications_firestore.php's
 * seed list — there's no notifications collection anywhere in this
 * app yet, so this is the fixed "starts unread" baseline every
 * sub-admin session begins with.
 */
function subadmin_seed_unread_notification_ids(): array
{
    return [1, 2, 3, 4, 5];
}

/**
 * Unread notification count for every subadmin/*.php page's sidebar
 * nav badge + topbar bell badge. $_SESSION['ntfReadIds'] accumulates
 * ids marked read via subadmin/notifications.php's Mark as read / Mark
 * all as read (persisted through api/mark-notifications-read.php,
 * which also writes them to this subadmin's users/{uid} Firestore doc),
 * so this count — and therefore every page's badges — stays correct
 * across navigation AND survives logging out and back in:
 * api/session-login.php reloads the same field from Firestore into
 * $_SESSION['ntfReadIds'] on every login instead of starting fresh.
 */
function subadmin_unread_notification_count(): int
{
    require_once __DIR__ . '/../includes/firestore/medicines.php'; // hc_get_medicine_expiry_notifications()
    require_once __DIR__ . '/../includes/shared/two_fa_reminder.php'; // hc_my_2fa_reminder_notification()
    require_once __DIR__ . '/../includes/shared/notification_preferences.php'; // hc_notification_preference_enabled()

    if (!hc_notification_preference_enabled('system')) {
        return 0;
    }

    $readIds = $_SESSION['ntfReadIds'] ?? [];
    $dismissedIds = $_SESSION['ntfDismissedIds'] ?? [];
    // The real 2FA reminder and real medicine/vaccine expiry alerts are
    // merged in alongside the rest of this role's own notification
    // list, which is still fixed seed data
    // (subadmin_seed_unread_notification_ids() above) — a separate,
    // pre-existing gap outside this change's scope. These two sources
    // are real regardless.
    $allIds = array_merge(
        subadmin_seed_unread_notification_ids(),
        hc_notification_preference_enabled('inventory') ? array_column(hc_get_medicine_expiry_notifications(), 'id') : [],
        array_column(array_filter([hc_my_2fa_reminder_notification()]), 'id')
    );

    return count(array_diff($allIds, $readIds, $dismissedIds));
}

/**
 * Unread notification count for every pharmacist/*.php page's sidebar
 * nav badge + topbar bell badge — same $_SESSION['ntfReadIds']
 * mechanism as subadmin_unread_notification_count() above, but the
 * "universe of ids" isn't a fixed seed list: it's real, generated live
 * from THREE sources merged together — includes/firestore/medicines.php's
 * hc_get_low_stock_notifications() (every medicine/vaccine currently
 * low or out of stock), hc_get_medicine_expiry_notifications() (every
 * medicine/vaccine already expired or expiring soon), AND
 * includes/firestore/pharmacy_orders.php's
 * hc_get_pending_pharmacy_order_notifications() (every medicine/vaccine
 * a doctor prescribed that's still awaiting dispensing) — so the count
 * rises and falls with real inventory AND real prescriptions instead of
 * a static baseline. Requires both files here rather than assuming a
 * caller already has, since this is called from early in the page (nav
 * item arrays) across every pharmacist/*.php page.
 */
function hc_pharmacist_unread_notification_count(): int
{
    require_once __DIR__ . '/../includes/firestore/medicines.php';
    require_once __DIR__ . '/../includes/firestore/pharmacy_orders.php';
    require_once __DIR__ . '/../includes/shared/two_fa_reminder.php'; // hc_get_2fa_reminder_notification()
    require_once __DIR__ . '/../includes/shared/notification_preferences.php'; // hc_notification_preference_enabled()

    if (!hc_notification_preference_enabled('system')) {
        return 0;
    }

    $readIds = $_SESSION['ntfReadIds'] ?? [];
    $dismissedIds = $_SESSION['ntfDismissedIds'] ?? [];
    $allIds = array_merge(
        hc_notification_preference_enabled('inventory') ? array_column(hc_get_low_stock_notifications(), 'id') : [],
        hc_notification_preference_enabled('inventory') ? array_column(hc_get_medicine_expiry_notifications(), 'id') : [],
        hc_notification_preference_enabled('inventory') ? array_column(hc_get_pending_pharmacy_order_notifications(), 'id') : [],
        array_column(array_filter([hc_my_2fa_reminder_notification()]), 'id')
    );

    return count(array_diff($allIds, $readIds, $dismissedIds));
}

/**
 * Unread notification count for every doctor/*.php page's sidebar nav
 * badge + topbar bell badge — same $_SESSION['ntfReadIds']/
 * ['ntfDismissedIds'] mechanism as hc_pharmacist_unread_notification_count()
 * above, but the "universe of ids" is real upcoming/overdue follow-ups
 * from this doctor's own caseload (includes/firestore/consultations.php's
 * hc_get_followup_notifications(), scoped by the signed-in doctor's own
 * name) rather than inventory.
 */
function hc_doctor_unread_notification_count(): int
{
    require_once __DIR__ . '/../includes/firestore/consultations.php';
    require_once __DIR__ . '/../includes/firestore/lab_results.php'; // hc_get_doctor_result_ready_notifications()
    require_once __DIR__ . '/../includes/shared/two_fa_reminder.php'; // hc_my_2fa_reminder_notification()
    require_once __DIR__ . '/../includes/shared/notification_preferences.php'; // hc_notification_preference_enabled()

    if (!hc_notification_preference_enabled('system')) {
        return 0;
    }

    $readIds = $_SESSION['ntfReadIds'] ?? [];
    $dismissedIds = $_SESSION['ntfDismissedIds'] ?? [];
    $allIds = array_merge(
        hc_notification_preference_enabled('consultation') ? array_column(hc_get_followup_notifications($_SESSION['name'] ?? null), 'id') : [],
        array_column(hc_get_doctor_result_ready_notifications($_SESSION['name'] ?? ''), 'id'),
        array_column(array_filter([hc_my_2fa_reminder_notification()]), 'id')
    );

    return count(array_diff($allIds, $readIds, $dismissedIds));
}

/**
 * Adds a "Dr. " honorific for every doctor/*.php page's topbar profile
 * dropdown name — UNLESS the real stored name already starts with one.
 * Some real doctor accounts have "Dr." typed directly into their name
 * field at registration (e.g. "Dr. Ramon Villareal"), others don't
 * (e.g. "Jake Sim") — every one of those pages used to hardcode a bare
 * "Dr. " literal in front of $doctorProfile['name'], which produced
 * "Dr. Dr. Ramon Villareal" for the first group instead of adding the
 * title only where it was actually missing.
 */
function hc_doctor_display_name(?string $name): string
{
    $name = trim((string) $name);

    return preg_match('/^dr\.?\s/i', $name) === 1 ? $name : ('Dr. ' . $name);
}

/**
 * Unread notification count for every medical-staff/*.php page's
 * sidebar nav badge + topbar bell badge — same mechanism as
 * hc_doctor_unread_notification_count() above, but facility-wide
 * (unscoped) since healthcare_staff isn't personally assigned a
 * caseload the way a doctor is.
 */
function hc_medstaff_unread_notification_count(): int
{
    require_once __DIR__ . '/../includes/firestore/consultations.php';
    require_once __DIR__ . '/../includes/shared/two_fa_reminder.php'; // hc_my_2fa_reminder_notification()
    require_once __DIR__ . '/../includes/shared/notification_preferences.php'; // hc_notification_preference_enabled()

    if (!hc_notification_preference_enabled('system')) {
        return 0;
    }

    $readIds = $_SESSION['ntfReadIds'] ?? [];
    $dismissedIds = $_SESSION['ntfDismissedIds'] ?? [];
    $allIds = array_merge(
        hc_notification_preference_enabled('consultation') ? array_column(hc_get_followup_notifications(), 'id') : [],
        array_column(array_filter([hc_my_2fa_reminder_notification()]), 'id')
    );

    return count(array_diff($allIds, $readIds, $dismissedIds));
}

/**
 * Unread notification count for every medical_technician/*.php page's
 * sidebar nav badge + topbar bell badge — same mechanism as
 * hc_pharmacist_unread_notification_count() above, but the "universe of
 * ids" is real pending lab/x-ray results still awaiting a result
 * (includes/firestore/lab_results.php's hc_get_pending_lab_xray_notifications())
 * rather than inventory. Replaces the old reuse of
 * subadmin_unread_notification_count() (a coincidental fit against a
 * fixed 1-5 seed list, not this role's own real data).
 */
function hc_medtech_unread_notification_count(): int
{
    require_once __DIR__ . '/../includes/firestore/lab_results.php';
    require_once __DIR__ . '/../includes/shared/two_fa_reminder.php'; // hc_my_2fa_reminder_notification()
    require_once __DIR__ . '/../includes/shared/notification_preferences.php'; // hc_notification_preference_enabled()

    if (!hc_notification_preference_enabled('system')) {
        return 0;
    }

    $readIds = $_SESSION['ntfReadIds'] ?? [];
    $dismissedIds = $_SESSION['ntfDismissedIds'] ?? [];
    $allIds = array_merge(
        array_column(hc_get_pending_lab_xray_notifications(), 'id'),
        array_column(array_filter([hc_my_2fa_reminder_notification()]), 'id')
    );

    return count(array_diff($allIds, $readIds, $dismissedIds));
}
