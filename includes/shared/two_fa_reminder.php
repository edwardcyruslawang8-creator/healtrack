<?php
/**
 * =====================================================================
 * Shared — 2FA Setup Reminder
 * ---------------------------------------------------------------------
 * Real, account-wide nudge for any account that hasn't turned on
 * two-factor authentication yet — whether it's brand new or has existed
 * for a long time, the rule is the same: "due" the moment 7 real days
 * have passed since whichever happened more recently, account creation
 * or the last reminder actually sent (hc_2fa_reminder_email_due()).
 * Two real, independent delivery channels read this same due-check:
 *   - scripts/send-2fa-reminders.php (run weekly by a real Windows
 *     Task Scheduler job — see that file's own header comment) emails
 *     every account that's due, regardless of whether anyone ever logs
 *     in to trigger it.
 *   - hc_get_2fa_reminder_notification() surfaces the SAME real
 *     condition (2FA simply not enabled — not time-gated the way the
 *     email is, so it's visible every time they're signed in, not just
 *     once a week) as one more real notification item, merged into
 *     each role's own existing notification list/unread-count
 *     functions (config/constants.php, includes/{role}/notifications_*.php)
 *     the exact same way every other real notification source already
 *     is there.
 * =====================================================================
 */

require_once __DIR__ . '/mailer.php'; // hc_send_email(), hc_account_email_shell()

const HC_2FA_REMINDER_INTERVAL_DAYS = 7;

if (!function_exists('hc_2fa_reminder_email_due')) {
    /**
     * True when $userDoc has no 2FA enabled AND at least
     * HC_2FA_REMINDER_INTERVAL_DAYS have passed since whichever is more
     * recent: the account's real createdAt, or the last time a
     * reminder was actually sent (twoFaReminderLastSentAt) — so a
     * long-existing account with 2FA still off keeps getting nudged
     * once a week indefinitely, not just once.
     */
    function hc_2fa_reminder_email_due(array $userDoc): bool
    {
        if ($userDoc['twoFactorEnabled'] ?? false) {
            return false;
        }

        // The EMAIL channel specifically — gated by 'email' ("Receive
        // updates and alerts via email"), independently of 'system' (the
        // separate in-app toast/notification, see
        // hc_my_2fa_reminder_notification() above). Checked per-account
        // (scripts/send-2fa-reminders.php iterates every real user doc,
        // not just the signed-in one), so this takes $userDoc directly
        // rather than reading the current session.
        require_once __DIR__ . '/notification_preferences.php'; // hc_notification_preference_enabled_for_doc()
        if (!hc_notification_preference_enabled_for_doc($userDoc, 'email')) {
            return false;
        }

        $baseline = $userDoc['twoFaReminderLastSentAt'] ?? ($userDoc['createdAt'] ?? null);
        if ($baseline === null || $baseline === '') {
            return true; // no real timestamp to compare against — honest default is "due"
        }
        $baselineTs = strtotime((string) $baseline);
        if ($baselineTs === false) {
            return true;
        }

        return (time() - $baselineTs) >= (HC_2FA_REMINDER_INTERVAL_DAYS * 86400);
    }
}

if (!function_exists('hc_send_2fa_reminder_email')) {
    /**
     * Sends the real reminder email and records the real timestamp
     * (twoFaReminderLastSentAt) that hc_2fa_reminder_email_due() reads
     * back next time — called only by scripts/send-2fa-reminders.php,
     * never from a page load (that's what makes this the "weekly
     * email" channel instead of the always-visible in-app notification
     * channel below).
     */
    function hc_send_2fa_reminder_email(string $uid, array $userDoc): void
    {
        global $firestore;

        $email = (string) ($userDoc['email'] ?? '');
        $name = (string) ($userDoc['name'] ?? 'there');
        if ($email === '') {
            return;
        }

        hc_send_email($email, $name, 'Security reminder: enable two-factor authentication on your HEALTrack account', hc_account_email_shell(
            'Protect your account with two-factor authentication',
            '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">Hi ' . htmlspecialchars($name) . ', your HEALTrack account (' . htmlspecialchars($email) . ') still doesn\'t have two-factor authentication (2FA) turned on.</p>'
            . '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">2FA adds a real second layer of protection — even if someone ever got your password, they still couldn\'t sign in without the 6-digit code from your own authenticator app. It only takes a minute to set up from your Profile Settings &gt; Security Settings page.</p>'
            . '<p style="margin:0; font-size:14px; line-height:1.6;">We\'ll keep sending this reminder about once a week until it\'s enabled.</p>'
        ));

        $firestore->setDocument('users', $uid, array_merge($userDoc, [
            'twoFaReminderLastSentAt' => date('c'),
        ]));
    }
}

if (!function_exists('hc_my_2fa_reminder_notification')) {
    /**
     * Thin convenience wrapper over hc_get_2fa_reminder_notification()
     * for the common case every unread-count function and every
     * notifications-list file actually needs: "the CURRENTLY SIGNED-IN
     * account's own real doc" — avoids repeating that same
     * $_SESSION['uid'] + getDocument() lookup at every one of those call
     * sites. Requires config/firebase.php itself (not just assumed
     * already loaded) since this is called from config/constants.php's
     * own unread-count functions, which run very early on every page —
     * and now also from hc_render_2fa_reminder_toast() below, called
     * from that same file's app-wide ob_start() callback on literally
     * every signed-in page, so the per-uid memoization below matters
     * more than it used to: without it, a single request could trigger
     * this same Firestore read 2-3 times over (nav badge, this toast,
     * and — on notifications.php specifically — that page's own data
     * file calling hc_get_2fa_reminder_notification() directly).
     */
    function hc_my_2fa_reminder_notification(): ?array
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

        // This specifically represents the IN-APP reminder (notification-
        // list row + hc_render_2fa_reminder_toast()'s bottom-right toast
        // below) — the separate weekly EMAIL reminder
        // (hc_2fa_reminder_email_due()/hc_send_2fa_reminder_email()) is
        // gated by the 'email' preference instead, independently, since
        // they're different channels for the same underlying condition.
        // See includes/shared/notification_preferences.php's header
        // comment for the full picture of what each preference key gates.
        require_once __DIR__ . '/notification_preferences.php'; // hc_notification_preference_enabled()
        if (!hc_notification_preference_enabled('system')) {
            return null;
        }

        global $firestore;
        require_once __DIR__ . '/../../config/firebase.php'; // $firestore

        // This is called from several places that each run at a different
        // point in the request (config/constants.php's own nav-badge
        // unread-count functions run early/mid-script; its ob_start()
        // callback below runs very late, at output-buffer flush time) —
        // and on this Windows/Apache mod_php setup, $firestore's
        // underlying Guzzle cURL handles have intermittently already been
        // torn down by the time a late call like that runs (the same
        // environment flake that otherwise surfaces on its own as
        // "Couldn't execute method GuzzleHttp\Handler\CurlMultiHandler::
        // __destruct" in the Apache error log). An uncaught failure here
        // used to take down the ENTIRE page just to skip one
        // non-essential reminder notification — treated the same as
        // "no user doc found" instead (not memoized, so a different,
        // earlier-running call site in the same request can still
        // succeed and doesn't get poisoned by one later call's failure).
        try {
            $userDoc = $firestore->getDocument('users', $uid);
        } catch (\Throwable $e) {
            error_log('[2FA reminder] Firestore read failed, skipping this notification: ' . $e->getMessage());
            return null;
        }

        $cachedUid = $uid;
        $cachedResult = $userDoc === null ? null : hc_get_2fa_reminder_notification($uid, $userDoc);

        return $cachedResult;
    }
}

if (!function_exists('hc_get_2fa_reminder_notification')) {
    /**
     * One real notification item (same shape every other real
     * notification source in this app already returns — see e.g.
     * includes/firestore/medicines.php's hc_get_low_stock_notifications())
     * for the CURRENTLY SIGNED-IN account specifically, when (and only
     * when) it genuinely doesn't have 2FA enabled — null otherwise, so
     * every caller can merge this in exactly like any other source
     * ("if it returns something, add it; if not, nothing to add").
     *
     * Deliberately NOT time-gated the way the weekly email is — unlike
     * an email (which would be spammy sent on every page load), an
     * always-visible in-app reminder for a real unresolved security gap
     * is the honest, expected behavior for a notification feed, same as
     * a low-stock alert stays visible every page load until the real
     * stock issue is actually fixed, not just once.
     *
     * The id is scoped to TODAY's date (not just the uid) on purpose: it
     * goes through the exact same read/archive pipeline as every other
     * notification (api/mark-notifications-read.php, api/archive-notifications.php),
     * so once marked read or archived it would otherwise stay silenced
     * forever — exactly the kind of security nag that must NOT be
     * permanently dismissible with one click. Baking the date into the
     * id means today's read/archived id simply doesn't match tomorrow's
     * id, so it comes back as a fresh, unread row every calendar day
     * 2FA is still off, and stops appearing at all — immediately, same
     * page load — the moment 2FA is actually enabled (the early return
     * above).
     */
    function hc_get_2fa_reminder_notification(string $uid, array $userDoc): ?array
    {
        if ($userDoc['twoFactorEnabled'] ?? false) {
            return null;
        }

        return [
            'id' => crc32('2fa-reminder-' . $uid . '-' . date('Y-m-d')),
            'title' => 'Enable two-factor authentication',
            'description' => 'Your account doesn\'t have 2FA enabled yet — add it from Profile Settings > Security Settings for stronger account protection.',
            'type' => 'Reminder',
            'date' => date('M j, Y'),
            'time' => date('g:i A'),
            'icon' => 'bi-shield-exclamation',
            'iconBg' => '#FDECDD',
            'iconColor' => '#E8710A',
        ];
    }
}

if (!function_exists('hc_render_2fa_reminder_toast')) {
    /**
     * A second, more insistent surface for the same real condition
     * hc_get_2fa_reminder_notification() already represents in the
     * notification list/badges — a bottom-right toast that pops up once
     * per calendar day (tracked client-side in localStorage, keyed by
     * uid + today's date so it naturally resets at midnight without any
     * extra server-side state) and auto-hides itself after 2 minutes.
     * Returns '' (render nothing) once 2FA is actually enabled — same
     * instant cutoff as the notification list, since both read the same
     * hc_my_2fa_reminder_notification() check.
     *
     * Called from config/constants.php's existing app-wide ob_start()
     * callback (the one that already appends assets/js/idle-timeout.js
     * before every signed-in page's </body>), so this reaches every
     * page of every eligible role with this one call site instead of
     * editing each page individually. Restricted to the roles that
     * actually have a profile_settings.php page to send "Enable now" to
     * — patient has no such page and no 2FA concept in this app at all,
     * so it's deliberately left out here.
     */
    function hc_render_2fa_reminder_toast(): string
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

        if (hc_my_2fa_reminder_notification() === null) {
            return '';
        }

        $config = [
            // Resets automatically at local midnight — a new day means a
            // new key, so there's nothing to clean up once 2FA is
            // enabled or the day passes.
            'storageKey' => 'hc2faToastShown:' . $uid . ':' . date('Y-m-d'),
            'profileUrl' => BASE_URL . '/' . $eligibleRoleFolders[$role] . '/profile_settings.php',
            'autoHideMs' => 120000,
        ];

        return '<script>window.HC_2FA_TOAST = ' . json_encode($config) . ';</script>'
            . '<script src="' . asset_url('/assets/js/two-fa-reminder-toast.js') . '"></script>';
    }
}
