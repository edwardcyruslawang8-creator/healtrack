<?php
/**
 * =====================================================================
 * Shared — Login Attempt Lockout
 * ---------------------------------------------------------------------
 * Real, server-enforced escalating penalty for repeated wrong-password
 * attempts against a real users/{uid} account:
 *
 *   attempt 3 -> locked out for  1 minute
 *   attempt 5 -> locked out for  3 minutes, AND emails the user's own
 *                registered address plus every sub-admin assigned to
 *                that user's facility
 *   attempt 6 -> locked out for  5 minutes
 *   attempt 7 -> PERMANENTLY locked (status becomes "locked", the same
 *                status admin/subadmin_management.php and
 *                admin/account_monitoring.php already display a badge
 *                for) — only a sub-admin unlocking the account
 *                (subadmin/user_management.php, api/unlock-user.php)
 *                clears it; a correct password alone no longer does.
 *
 * WHY THIS NEEDS TWO ENDPOINTS, NOT JUST ONE: Firebase Authentication
 * verifies the password entirely client-side (assets/js/login.js calls
 * firebase.auth().signInWithEmailAndPassword() directly) — a WRONG
 * password never reaches this PHP server at all on its own, only a
 * successful sign-in does (api/session-login.php, which only ever sees
 * the token AFTER Firebase already accepted the password). So:
 *   - api/check-login-lockout.php runs BEFORE the client even attempts
 *     Firebase sign-in, so a locked-out person sees the wait time
 *     immediately and Firebase is never even asked.
 *   - api/report-failed-login.php runs when Firebase Auth rejects a
 *     password (assets/js/login.js's .catch), the only way this server
 *     learns an attempt failed at all.
 *   - api/session-login.php ALSO re-checks lockout status right after
 *     verifying a real ID token, before starting a session — the actual
 *     unbypassable backstop, since it runs after Firebase Auth has
 *     already proven the password was correct. Even if something skips
 *     the client-side pre-check (a modified page, a direct API call),
 *     a session still can't start while locked out.
 *
 * Never trusts a client-supplied uid — every caller resolves it
 * server-side via $firebaseAuth->getUserByEmail($email) first.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/mailer.php'; // hc_send_email(), hc_account_email_shell()

const HC_LOGIN_LOCKOUT_SCHEDULE = [
    3 => 60,   // 1 minute
    5 => 180,  // 3 minutes
    6 => 300,  // 5 minutes
];
const HC_LOGIN_PERMANENT_LOCK_AT_ATTEMPT = 7;

if (!function_exists('hc_format_lockout_duration')) {
    /** $seconds -> "1 minute" / "3 minutes" / "45 seconds", for a human-readable wait message. */
    function hc_format_lockout_duration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0 seconds';
        }
        if ($seconds < 60) {
            return $seconds . ' second' . ($seconds === 1 ? '' : 's');
        }
        $minutes = (int) ceil($seconds / 60);

        return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    }
}

if (!function_exists('hc_login_lockout_status')) {
    /**
     * Reads $userDoc's own lockout fields (never re-fetches) and reports
     * whether sign-in is currently allowed. A caller that already has
     * the account's Firestore fields (from getDocument()) should pass
     * them straight in — this never talks to Firestore itself.
     *
     * @param array<string, mixed> $userDoc
     * @return array{locked: bool, permanentlyLocked: bool, retryAfterSeconds: ?int, message: ?string}
     */
    function hc_login_lockout_status(array $userDoc): array
    {
        if (($userDoc['status'] ?? '') === 'locked') {
            // A sub-admin account has no facility peer able to unlock it
            // (api/unlock-user.php refuses that — see its own comment) —
            // only the system administrator can, via
            // admin/subadmin_management.php.
            $unlocker = ($userDoc['role'] ?? '') === 'subadmin' ? 'the system administrator' : 'your sub-administrator';

            // status="locked" now has two real causes: this function's
            // own escalating-attempts penalty (hc_register_failed_login()
            // below), or a sub-admin's manual Lock Account action
            // (api/lock-user.php) — "repeated failed sign-in attempts"
            // would be a flat-out wrong explanation for the latter, so
            // the message is generic unless lockedReason says otherwise.
            $cause = ($userDoc['lockedReason'] ?? '') === 'Manually locked by sub-administrator'
                ? 'by a sub-administrator'
                : 'after repeated failed sign-in attempts';

            return [
                'locked' => true,
                'permanentlyLocked' => true,
                'retryAfterSeconds' => null,
                'message' => 'This account has been locked ' . $cause . '. Please contact ' . $unlocker . ' to have it unlocked.',
            ];
        }

        $lockedUntilRaw = $userDoc['lockedUntil'] ?? null;
        if ($lockedUntilRaw !== null && $lockedUntilRaw !== '') {
            $remaining = strtotime((string) $lockedUntilRaw) - time();
            if ($remaining > 0) {
                return [
                    'locked' => true,
                    'permanentlyLocked' => false,
                    'retryAfterSeconds' => $remaining,
                    'message' => 'Too many incorrect attempts. Please try again in ' . hc_format_lockout_duration($remaining) . '.',
                ];
            }
        }

        return ['locked' => false, 'permanentlyLocked' => false, 'retryAfterSeconds' => null, 'message' => null];
    }
}

if (!function_exists('hc_register_failed_login')) {
    /**
     * Call once per genuinely-wrong password attempt against a real
     * account. Increments failedLoginAttempts, applies whichever
     * penalty HC_LOGIN_LOCKOUT_SCHEDULE/HC_LOGIN_PERMANENT_LOCK_AT_ATTEMPT
     * says this attempt count has earned (writing the change for real),
     * fires the attempt-5 notifications, and returns the resulting
     * lockout status (same shape hc_login_lockout_status() returns) so
     * the caller can tell the person what just happened.
     *
     * @param array<string, mixed> $userDoc This account's current fields (from getDocument()).
     * @return array{locked: bool, permanentlyLocked: bool, retryAfterSeconds: ?int, message: ?string}
     */
    function hc_register_failed_login(string $uid, array $userDoc): array
    {
        global $firestore;

        $attempts = (int) ($userDoc['failedLoginAttempts'] ?? 0) + 1;
        $updated = array_merge($userDoc, [
            'failedLoginAttempts' => $attempts,
            'lastFailedLoginAt' => date('c'),
        ]);

        $justPermanentlyLocked = false;
        if ($attempts >= HC_LOGIN_PERMANENT_LOCK_AT_ATTEMPT) {
            $updated['status'] = 'locked';
            $updated['lockedUntil'] = null;
            $updated['lockedReason'] = 'Repeated failed sign-in attempts';
            $updated['lockedAt'] = date('c');
            $justPermanentlyLocked = ($userDoc['status'] ?? '') !== 'locked'; // only notify once, the moment it flips
        } elseif (isset(HC_LOGIN_LOCKOUT_SCHEDULE[$attempts])) {
            $updated['lockedUntil'] = date('c', time() + HC_LOGIN_LOCKOUT_SCHEDULE[$attempts]);
        }

        $firestore->setDocument('users', $uid, $updated);

        // A real audit trail entry for EVERY failed attempt — not just
        // the moment a lockout actually triggers, which
        // hc_send_login_lockout_alert() below already logs separately
        // as its own distinct "account locked" milestone entry. Written
        // directly (not via includes/shared/audit_log_writer.php's
        // write_audit_log()) for the same reason that function's own
        // header comment gives: there's no session to read an actor
        // from during a failed login, since the person isn't signed in.
        if (isset($firestore)) {
            $actorRole = match ($userDoc['role'] ?? null) {
                'admin' => 'System Administrator',
                'subadmin' => 'Sub-Administrator',
                'healthcare_staff' => 'Healthcare Staff',
                'pharmacist' => 'Pharmacist',
                'doctor' => 'Doctor',
                'medical_technician' => 'Medical Technologist',
                'radio_technologist' => 'Radio Technologist',
                default => 'System',
            };
            $firestore->addDocument('audit_logs', [
                'actorUid' => $uid,
                'actorName' => (string) ($userDoc['name'] ?? 'Unknown'),
                'actorRole' => $actorRole,
                'action' => 'logged_in',
                'module' => 'Account Security',
                'details' => 'Someone entered the wrong password while trying to sign in as ' . ($userDoc['name'] ?? 'Unknown') . ' (attempt #' . $attempts . ').',
                'status' => 'failed',
                'timestamp' => date('c'),
            ]);
        }

        if ($attempts === 5) {
            hc_send_login_lockout_alert($uid, $updated, false);
        } elseif ($justPermanentlyLocked) {
            hc_send_login_lockout_alert($uid, $updated, true);
        }

        return hc_login_lockout_status($updated);
    }
}

if (!function_exists('hc_reset_login_attempts')) {
    /**
     * Call right after a real session actually starts (api/session-login.php,
     * api/verify-2fa.php) — clears the failed-attempt counter and any
     * temporary lockout so a correct password fully starts over.
     * Deliberately does NOT touch "status" — a permanently locked
     * (status=locked) account stays locked even after someone finally
     * gets the password right; only a sub-admin's explicit unlock
     * (api/unlock-user.php) clears that.
     *
     * @param array<string, mixed> $userDoc This account's current fields (from getDocument()).
     */
    function hc_reset_login_attempts(string $uid, array $userDoc): void
    {
        global $firestore;

        if ((int) ($userDoc['failedLoginAttempts'] ?? 0) === 0 && ($userDoc['lockedUntil'] ?? null) === null) {
            return; // nothing to clear — skip the write
        }

        $firestore->setDocument('users', $uid, array_merge($userDoc, [
            'failedLoginAttempts' => 0,
            'lockedUntil' => null,
        ]));
    }
}

if (!function_exists('hc_send_login_lockout_alert')) {
    /**
     * @internal Real emails (includes/shared/mailer.php, already
     * SMTP-configured — see config/mail.php) to the account owner AND
     * every sub-admin assigned to that account's own real facility, plus
     * a real "audit_logs" entry (same collection admin/audit_logs.php,
     * subadmin/audit_logs.php, and admin/account_monitoring.php's
     * "Recent Account Activities" all read) — written directly rather
     * than via includes/shared/audit_log_writer.php's write_audit_log(),
     * since that helper reads $_SESSION for the actor and there is no
     * session at all during a failed login (the person isn't signed in).
     */
    function hc_send_login_lockout_alert(string $uid, array $userDoc, bool $permanent): void
    {
        global $firestore;

        $userName = (string) ($userDoc['name'] ?? 'A user');
        $userEmail = (string) ($userDoc['email'] ?? '');
        $facility = (string) ($userDoc['facility'] ?? '');
        // A locked-out SUB-ADMIN has no one at their own facility to
        // unlock them (subadmin/user_management.php deliberately can't —
        // see api/unlock-user.php's own role check) — only an admin can,
        // via admin/subadmin_management.php. Every other role is
        // unlocked by their own facility's sub-admin(s) instead.
        $isSubadminAccount = ($userDoc['role'] ?? '') === 'subadmin';
        $unlockerLabel = $isSubadminAccount ? 'the system administrator' : 'your facility\'s sub-administrator';

        if ($userEmail !== '') {
            hc_send_email($userEmail, $userName, 'Security alert: unusual sign-in activity on your HEALTrack account', hc_account_email_shell(
                $permanent ? 'Your account has been locked' : 'Multiple failed sign-in attempts',
                '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;">We noticed several incorrect password attempts on your HEALTrack account (' . htmlspecialchars($userEmail) . ').'
                . ($permanent
                    ? ' After repeated failures, your account has now been <strong>locked</strong> as a precaution.</p><p style="margin:0 0 12px; font-size:14px; line-height:1.6;">Please contact ' . $unlockerLabel . ' to have it unlocked.</p>'
                    : ' Your account has been temporarily locked for a few minutes as a precaution.</p><p style="margin:0 0 12px; font-size:14px; line-height:1.6;">If this wasn\'t you, please let ' . $unlockerLabel . ' know. If it was, just wait a few minutes and try again with the correct password.</p>')
            ));
        }

        // Who actually gets notified: this account's own facility
        // sub-admin(s) — unless the locked-out account IS a sub-admin,
        // in which case every system administrator is notified instead
        // (there's no one else at that facility able to unlock them).
        $recipientRole = $isSubadminAccount ? 'admin' : 'subadmin';
        $unlockPageHint = $isSubadminAccount
            ? 'System Administrator &gt; Sub-Administrator Management'
            : 'Sub-Administrator &gt; User Management';

        foreach ($firestore->listDocuments('users') as $doc) {
            $f = $doc['fields'];
            $recipientEmail = (string) ($f['email'] ?? '');
            if (($f['role'] ?? '') !== $recipientRole || $recipientEmail === '') {
                continue;
            }
            // Non-admin recipients (sub-admins) are further narrowed to
            // this account's own facility — admins manage every facility,
            // so they're notified regardless of which one this was.
            if ($recipientRole === 'subadmin' && ($f['facility'] ?? '') !== $facility) {
                continue;
            }

            hc_send_email($recipientEmail, (string) ($f['name'] ?? 'Administrator'), 'Security alert: repeated failed login attempts' . ($facility !== '' ? ' at ' . $facility : ''), hc_account_email_shell(
                $permanent ? 'An account was locked' : 'Repeated failed login attempts',
                '<p style="margin:0 0 12px; font-size:14px; line-height:1.6;"><strong>' . htmlspecialchars($userName) . '</strong> (' . htmlspecialchars($userEmail) . ')' . ($facility !== '' ? ' at <strong>' . htmlspecialchars($facility) . '</strong>' : '') . ' has had multiple incorrect password attempts.'
                . ($permanent
                    ? ' The account is now <strong>locked</strong> and will stay that way until you unlock it from ' . $unlockPageHint . '.</p>'
                    : ' It has been temporarily locked as a precaution. If this keeps happening, the account will lock permanently after a couple more attempts.</p>')
            ));
        }

        if (isset($firestore)) {
            $firestore->addDocument('audit_logs', [
                'actorUid' => $uid,
                'actorName' => $userName,
                'actorRole' => 'System',
                'action' => 'updated',
                'module' => 'Account Security',
                'details' => $permanent
                    ? $userName . '\'s account was locked after repeated failed sign-in attempts.'
                    : 'Multiple failed sign-in attempts detected for ' . $userName . ' — account temporarily locked.',
                'status' => 'failed',
                'timestamp' => date('c'),
            ]);
        }
    }
}
