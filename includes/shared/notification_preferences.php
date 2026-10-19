<?php
/**
 * =====================================================================
 * Shared — Notification Preferences (real gating)
 * ---------------------------------------------------------------------
 * Every role's Profile Settings > Notification Preferences toggles
 * (profile_settings_firestore.php's $notificationPreferences, saved for
 * real via api/update-profile-settings.php to users/{uid}.notifications.{key})
 * used to save correctly but were never read back anywhere — every
 * notification kept firing regardless of what the toggle said. This
 * file is the one place that's actually read, and the two functions
 * below are the real gate every notification source now checks before
 * including itself:
 *   - 'system'  (master "Receive in-app notifications" switch) — when
 *     off, every in-app notification for that account is suppressed,
 *     checked first by config/constants.php's 5 role unread-count
 *     functions and each role's own notifications_*_firestore.php.
 *   - 'consultation' — doctor/medical-staff's real follow-up
 *     notifications (includes/firestore/consultations.php's
 *     hc_get_followup_notifications()).
 *   - 'inventory' — pharmacist's real low-stock + expiring/expired
 *     medicine/vaccine + pending pharmacy order notifications
 *     (includes/firestore/medicines.php / pharmacy_orders.php); subadmin
 *     also gets the expiring/expired medicine/vaccine source (same
 *     hc_get_medicine_expiry_notifications()), but not low-stock or
 *     pending-order, which stay pharmacist-only.
 *   - 'email' — the one real staff-facing notification EMAIL that
 *     exists (the weekly 2FA reminder — see
 *     hc_2fa_reminder_email_due() in two_fa_reminder.php, the only
 *     call site that checks this one).
 *   - 'system' also covers two sources with no cleaner-fitting category
 *     of their own: doctor's patient-access-request notifications and
 *     medical_technician's pending lab/x-ray notifications — neither is
 *     really "consent" or "consultation" specifically, so they're only
 *     gated by the master switch, not a more specific one.
 *   - 'consent' has NO real source to gate anywhere in this app yet —
 *     nothing currently creates an in-app "new consent request"
 *     notification (subadmin/consent_management.php's own consent
 *     records don't feed into the notification list at all). Left
 *     alone rather than faking a behavior for it.
 *
 * Security-alert emails (includes/shared/login_lockout.php) and
 * transactional emails (account approval/rejection, registration
 * confirmation) are NEVER gated by any of this — those are essential,
 * not preference-based; silencing a brute-force alert because a toggle
 * was off would be a real security regression, not a convenience.
 * =====================================================================
 */

if (!function_exists('hc_notification_preference_enabled_for_doc')) {
    /** True unless $userDoc has explicitly turned $key off — same default (on) profile_settings_firestore.php's own toggles use. Takes a raw users/{uid} doc directly, for callers (like the 2FA reminder batch script) iterating accounts other than the signed-in one. */
    function hc_notification_preference_enabled_for_doc(array $userDoc, string $key): bool
    {
        return (bool) ($userDoc['notifications'][$key] ?? true);
    }
}

if (!function_exists('hc_my_notification_preferences')) {
    /** @internal The CURRENTLY SIGNED-IN account's own saved 'notifications' map, memoized per request (one Firestore read regardless of how many of the functions below get called) — same per-uid caching pattern as two_fa_reminder.php's hc_my_2fa_reminder_notification(). */
    function hc_my_notification_preferences(): array
    {
        static $cachedUid = null;
        static $cachedPrefs = null;

        $uid = $_SESSION['uid'] ?? '';
        if ($uid === '') {
            return [];
        }
        if ($cachedUid === $uid) {
            return $cachedPrefs;
        }

        global $firestore;
        require_once __DIR__ . '/../../config/firebase.php'; // $firestore

        // Same "never let a late/flaky Firestore read take down the whole
        // page" reasoning as two_fa_reminder.php's own try/catch — a
        // failure here defaults to every preference being on (never
        // silently hides a real notification just because this one read
        // happened to fail), not memoized, so a later call in the same
        // request can still succeed.
        try {
            $userDoc = $firestore->getDocument('users', $uid);
        } catch (\Throwable $e) {
            error_log('[Notification preferences] Firestore read failed, defaulting to all enabled: ' . $e->getMessage());
            return [];
        }

        $cachedUid = $uid;
        $cachedPrefs = $userDoc['notifications'] ?? [];

        return $cachedPrefs;
    }
}

if (!function_exists('hc_notification_preference_enabled')) {
    /** Same check as hc_notification_preference_enabled_for_doc(), for the CURRENTLY SIGNED-IN account specifically. */
    function hc_notification_preference_enabled(string $key): bool
    {
        $prefs = hc_my_notification_preferences();

        return (bool) ($prefs[$key] ?? true);
    }
}
