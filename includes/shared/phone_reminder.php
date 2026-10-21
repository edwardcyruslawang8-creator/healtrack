<?php
/**
 * =====================================================================
 * Shared — Complete Your Profile (Phone Number) Reminder
 * ---------------------------------------------------------------------
 * "Sign up with Google" (register.php/assets/js/register.js) no longer
 * requires a phone number at registration time — only Role — since
 * Google has no phone number of its own to hand over. That leaves some
 * real accounts approved and active with an empty 'phone' field, which
 * this nudges them to fill in from their own Profile Settings once
 * they're actually signed in. Modeled directly on
 * includes/shared/two_fa_reminder.php's in-app notification + toast
 * pair (same $uid-memoized lookup, same always-visible-until-resolved
 * behavior, same per-day toast) — see that file's header comment for
 * the full design reasoning, which applies here unchanged. Unlike 2FA,
 * there's no separate weekly email channel for this one; a phone
 * number is a profile-completeness nudge, not a security control.
 *
 * Only wired into the 5 roles self-registration can actually produce
 * (pharmacist, doctor, healthcare_staff, medical_technician,
 * radio_technologist — see api/register.php's $jobTitleToRole) — admin
 * and subadmin accounts are always provisioned by an admin via
 * api/create-user.php, which already collects a real phone number, so
 * there's nothing to remind them about.
 * =====================================================================
 */

if (!function_exists('hc_my_phone_reminder_notification')) {
    /**
     * Thin convenience wrapper over hc_get_phone_reminder_notification()
     * for the common case every unread-count function and every
     * notifications-list file actually needs: "the CURRENTLY SIGNED-IN
     * account's own real doc" — same per-uid memoization as
     * two_fa_reminder.php's hc_my_2fa_reminder_notification(), so a
     * single request never re-reads this account's own doc more than
     * once no matter how many call sites ask.
     */
    function hc_my_phone_reminder_notification(): ?array
    {
        static $cachedUid = null;
        static $cachedResult = null;

        $uid = $_SESSION['uid'] ?? '';
        if ($uid === '') {
            return null;
        }
        if ($cachedUid === $uid) {
            return $cachedResult;
        }

        require_once __DIR__ . '/notification_preferences.php'; // hc_notification_preference_enabled()
        if (!hc_notification_preference_enabled('system')) {
            return null;
        }

        global $firestore;
        require_once __DIR__ . '/../../config/firebase.php'; // $firestore

        // Same defensive try/catch as hc_my_2fa_reminder_notification()
        // — this is called from config/constants.php's late-running
        // ob_start() callback too, where $firestore's underlying Guzzle
        // cURL handles have intermittently already been torn down on
        // this Windows/Apache mod_php setup. Skip the reminder rather
        // than take down the whole page over it.
        try {
            $userDoc = $firestore->getDocument('users', $uid);
        } catch (\Throwable $e) {
            error_log('[Phone reminder] Firestore read failed, skipping this notification: ' . $e->getMessage());
            return null;
        }

        $cachedUid = $uid;
        $cachedResult = $userDoc === null ? null : hc_get_phone_reminder_notification($uid, $userDoc);

        return $cachedResult;
    }
}

if (!function_exists('hc_get_phone_reminder_notification')) {
    /**
     * One real notification item (same shape as every other real
     * source — see hc_get_2fa_reminder_notification()) for the
     * CURRENTLY SIGNED-IN account specifically, when (and only when) it
     * genuinely has no phone number on file — null otherwise.
     *
     * The id is scoped to TODAY's date, same reasoning as the 2FA
     * reminder's id: it goes through the same read/archive pipeline as
     * every other notification, so baking today's date in means a
     * dismissed/read reminder comes back as a fresh unread row the next
     * calendar day the phone number is still missing, and stops
     * appearing at all, same page load, the instant it's filled in.
     */
    function hc_get_phone_reminder_notification(string $uid, array $userDoc): ?array
    {
        if (trim((string) ($userDoc['phone'] ?? '')) !== '') {
            return null;
        }

        return [
            'id' => crc32('phone-reminder-' . $uid . '-' . date('Y-m-d')),
            'title' => 'Add your phone number',
            'description' => 'Your account doesn\'t have a phone number on file yet — add one from Profile Settings so your facility can reach you.',
            'type' => 'Reminder',
            'date' => date('M j, Y'),
            'time' => date('g:i A'),
            'icon' => 'bi-telephone-x',
            'iconBg' => '#FDECDD',
            'iconColor' => '#E8710A',
        ];
    }
}

if (!function_exists('hc_render_phone_reminder_toast')) {
    /**
     * A second, more insistent surface for the same real condition
     * hc_get_phone_reminder_notification() already represents in the
     * notification list/badges — a bottom-right toast that pops up once
     * per calendar day (localStorage, keyed by uid + today's date, same
     * idiom as hc_render_2fa_reminder_toast()) and auto-hides itself.
     * Returns '' once a phone number is actually on file, or for a role
     * self-registration can't produce (see header comment).
     */
    function hc_render_phone_reminder_toast(): string
    {
        $eligibleRoleFolders = [
            'doctor' => 'doctor',
            'healthcare_staff' => 'medical-staff',
            'medical_technician' => 'medical_technician',
            'radio_technologist' => 'radio_technologist',
            'pharmacist' => 'pharmacist',
        ];

        $uid = $_SESSION['uid'] ?? '';
        $role = $_SESSION['role'] ?? '';
        if ($uid === '' || !isset($eligibleRoleFolders[$role])) {
            return '';
        }

        if (hc_my_phone_reminder_notification() === null) {
            return '';
        }

        $config = [
            'storageKey' => 'hcPhoneToastShown:' . $uid . ':' . date('Y-m-d'),
            'profileUrl' => BASE_URL . '/' . $eligibleRoleFolders[$role] . '/profile_settings.php',
            'autoHideMs' => 120000,
        ];

        return '<script>window.HC_PHONE_TOAST = ' . json_encode($config) . ';</script>'
            . '<script src="' . asset_url('/assets/js/phone-reminder-toast.js') . '"></script>';
    }
}
