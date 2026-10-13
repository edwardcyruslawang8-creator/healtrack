<?php
/**
 * =====================================================================
 * Shared — Does this account have a real password yet?
 * ---------------------------------------------------------------------
 * A "Sign up with Google" account (api/register.php) has NO password
 * credential in Firebase Auth at all until it goes through
 * set-password.php (api/session-login.php's post-login gate) — but an
 * already-active session established before that gate existed, or one
 * that simply hasn't hit a fresh login yet, could still reach Profile
 * / Settings' "Change Password" form directly. That form's "Current
 * Password" field is meaningless for such an account (there's nothing
 * to verify), so both api/update-admin-password.php and every
 * profile_settings.php page check this the same way, via this one
 * function, rather than trusting a Firestore field that might be
 * stale or was never set for an account created before it existed.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firebaseAuth — required directly here, not assumed
// already loaded by whatever required this file: profile_settings.php
// pages call hc_account_has_password() BEFORE their own
// profile_settings_*_firestore.php require (the thing that would
// otherwise have pulled this in), so without this, $firebaseAuth was
// null at that point — ->getUser() on it threw, silently caught below,
// and failed open to "has a password" for EVERY account, hiding the
// Current Password field's removal for exactly the accounts that need it.

if (!function_exists('hc_account_has_password')) {
    function hc_account_has_password(string $uid): bool
    {
        global $firebaseAuth;

        try {
            foreach ($firebaseAuth->getUser($uid)->providerData as $provider) {
                if ($provider->providerId === 'password') {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            return true; // fail open — never block a real password-change over an Admin SDK hiccup
        }
    }
}
