<?php
/**
 * =====================================================================
 * Shared — Email Verification Reminder
 * ---------------------------------------------------------------------
 * Real, account-wide nudge for any account signed in with an
 * unverified email — same shape and reasoning as
 * includes/shared/two_fa_reminder.php's own 2FA reminder (that file's
 * header comment has the full design rationale; this is a direct
 * sibling of it, not a separate design). hc_get_email_verification_notification()
 * surfaces the real condition as one more real notification item,
 * merged into each role's own existing notification list/unread-count
 * functions the exact same way 2FA's own reminder already is there.
 *
 * Unlike 2FA (whose "enabled" state is stored on the account's own
 * Firestore users/{uid} doc, so any caller can check any account's
 * status with a plain Firestore read), "verified" is Firebase AUTH
 * state, not Firestore state — there's no users/{uid}.emailVerified
 * field to read. The one place this app already tracks it live is
 * $_SESSION['emailVerified'], set from a real Firebase Auth lookup at
 * every sign-in (api/session-login.php, api/verify-2fa.php,
 * api/complete-password-setup.php) —
 * so this reminder is scoped to the CURRENTLY SIGNED-IN account only
 * (hc_my_email_verification_notification()), not a general "check any
 * account" function the way 2FA's hc_get_2fa_reminder_notification()
 * is — there's nothing to look up for anyone else without a live Admin
 * SDK call per account, which this app's existing session-level signal
 * already avoids needing.
 * =====================================================================
 */

if (!function_exists('hc_my_email_verification_notification')) {
    /**
     * Thin, memoized wrapper — the CURRENTLY SIGNED-IN account's own
     * real verification status, from the session (not a fresh
     * Firebase Auth call — $_SESSION['emailVerified'] is already live
     * as of this sign-in, same trust level profile_settings.php's own
     * "Resend Email" row already relies on). Requires
     * notification_preferences.php itself (not just assumed already
     * loaded) since this is called from config/constants.php's own
     * unread-count functions, which run very early on every page — and
     * from that same file's app-wide ob_start() callback too.
     */
    function hc_my_email_verification_notification(): ?array
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
            $cachedUid = $uid;
            $cachedResult = null;

            return null;
        }

        $cachedUid = $uid;
        $cachedResult = ($_SESSION['emailVerified'] ?? false)
            ? null
            : hc_get_email_verification_notification($uid, (string) ($_SESSION['email'] ?? ''));

        return $cachedResult;
    }
}

if (!function_exists('hc_get_email_verification_notification')) {
    /**
     * One real notification item (same shape every other real
     * notification source in this app returns), when (and only when)
     * $uid genuinely has no verified email right now.
     *
     * The id is scoped to TODAY's date on purpose — same reasoning as
     * hc_get_2fa_reminder_notification()'s own id: it goes through the
     * exact same read/archive pipeline as every other notification, so
     * baking the date in means marking it read/archived only silences
     * it for that one day, not forever, and it stops appearing at all,
     * same page load, the moment the email actually gets verified (the
     * caller above never calls this once $_SESSION['emailVerified'] is
     * true).
     */
    function hc_get_email_verification_notification(string $uid, string $email): array
    {
        return [
            'id' => crc32('email-verification-reminder-' . $uid . '-' . date('Y-m-d')),
            'title' => 'Verify your email address',
            'description' => $email !== ''
                ? 'Your account (' . $email . ') doesn\'t have a verified email yet — verify it from Profile Settings > Security Settings.'
                : 'Your account doesn\'t have a verified email yet — verify it from Profile Settings > Security Settings.',
            'type' => 'Reminder',
            'date' => date('M j, Y'),
            'time' => date('g:i A'),
            'icon' => 'bi-envelope-exclamation-fill',
            'iconBg' => '#FDECDD',
            'iconColor' => '#E8710A',
        ];
    }
}

if (!function_exists('hc_render_email_verification_toast')) {
    /**
     * A second, more insistent surface for the same real condition —
     * a bottom-right toast that pops up once per calendar day (tracked
     * client-side in localStorage, keyed by uid + today's date, same
     * idiom as hc_render_2fa_reminder_toast()'s own toast) and
     * auto-hides itself after 2 minutes. Returns '' (render nothing)
     * the instant the email is actually verified, or for a role with
     * no profile_settings.php to send "Verify now" to.
     *
     * Called from config/constants.php's existing app-wide ob_start()
     * callback, same injection point as the 2FA/phone reminder toasts
     * and the notification poller — one shared call site instead of
     * editing every page.
     */
    function hc_render_email_verification_toast(): string
    {
        $eligibleRoleFolders = [
            'admin' => 'admin',
            'subadmin' => 'subadmin',
            'doctor' => 'doctor',
            'healthcare_staff' => 'medical-staff',
            'medical_technician' => 'medical_technician',
            'radio_technologist' => 'radiologic_technologist',
            'pharmacist' => 'pharmacist',
        ];

        $uid = $_SESSION['uid'] ?? '';
        $role = $_SESSION['role'] ?? '';
        if ($uid === '' || !isset($eligibleRoleFolders[$role])) {
            return '';
        }

        if (hc_my_email_verification_notification() === null) {
            return '';
        }

        $config = [
            'storageKey' => 'hcEmailVerifyToastShown:' . $uid . ':' . date('Y-m-d'),
            'profileUrl' => BASE_URL . '/' . $eligibleRoleFolders[$role] . '/profile_settings.php',
            'autoHideMs' => 120000,
        ];

        return '<script>window.HC_EMAIL_VERIFY_TOAST = ' . json_encode($config) . ';</script>'
            . '<script src="' . asset_url('/assets/js/email-verification-toast.js') . '"></script>';
    }
}
