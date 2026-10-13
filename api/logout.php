<?php
/**
 * =====================================================================
 * Logout (api/logout.php)
 * ---------------------------------------------------------------------
 * Destroys the PHP session, then signs the client out of Firebase
 * Authentication too (session-login.php and the Firebase client SDK
 * are two separate sign-in states — both need clearing) before
 * sending the user back to login.php.
 * =====================================================================
 */
session_start();

require_once __DIR__ . '/../config/constants.php'; // BASE_URL

// Only admin/subadmin sign-outs are relevant to the admin audit trail —
// written before the session is torn down below, since it reads $_SESSION.
if (in_array($_SESSION['role'] ?? null, ['admin', 'subadmin'], true)) {
    require_once __DIR__ . '/../config/firebase.php';
    require_once __DIR__ . '/../includes/shared/audit_log_writer.php';
    write_audit_log('logged_out', 'User Management', 'User logged out of the system.');
}

// Invalidates any remember-token too — see
// includes/shared/remember_me.php's header comment. Without this, a
// stale-but-not-yet-expired remember cookie would silently log this
// same browser back in on the very next page load.
require_once __DIR__ . '/../includes/shared/remember_me.php';
hc_clear_remember_token();

session_unset();
session_destroy();

// Carried through from includes/shared/idle_timeout.php's / assets/js/idle-timeout.js's
// redirect here (?reason=idle) so login.php can show a "you were signed
// out due to inactivity" message instead of a bare login form — same
// query param either way this endpoint gets reached (idle timeout or a
// normal manual "Log out" click, which never sends it).
$loginUrl = BASE_URL . '/login.php' . (($_GET['reason'] ?? '') === 'idle' ? '?reason=idle' : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Signing out…</title>
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-auth-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-firestore-compat.js"></script>
<script src="<?= BASE_URL ?>/assets/js/firebase-config.js"></script>
<script>
    firebase.auth().signOut().finally(function () {
        window.location.href = "<?= htmlspecialchars($loginUrl, ENT_QUOTES) ?>";
    });
</script>
</head>
<body>
</body>
</html>
