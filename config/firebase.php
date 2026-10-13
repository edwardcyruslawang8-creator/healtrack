<?php
/**
 * =====================================================================
 * Firebase Configuration (server-side / Admin SDK)
 * ---------------------------------------------------------------------
 * Bootstraps the Kreait Firebase Admin SDK from the service account
 * below. $firebaseAuth verifies ID tokens issued by the client-side
 * sign-in in login.php (see api/session-login.php).
 *
 * Firestore reads/writes do NOT go through Kreait's createFirestore()
 * here — that wrapper requires the PHP grpc extension, which has no
 * official build for this PHP version/platform. firebase/FirestoreRestClient.php
 * talks to the same Firestore database over its REST API instead, using
 * this same service account for auth. $firestore below is an instance
 * of that REST client.
 * =====================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../firebase/FirestoreRestClient.php';

use Kreait\Firebase\Factory;

define('FIREBASE_PROJECT_ID', 'healtrackdb-4e786');
// Moved outside C:/xampp/htdocs (Apache's DocumentRoot) — this key used
// to sit in config/ inside the web root, where it was directly
// downloadable by anyone who requested its URL (Admin SDK access
// bypasses every Firestore security rule and every role/facility check
// this app has, so that was a full database compromise waiting to
// happen, not just a config leak). Kept as an absolute path one level
// above htdocs rather than anywhere still under it.
define('FIREBASE_CREDENTIALS_PATH', 'C:/xampp/Healtrack-secure/healtrackdb-4e786-firebase-adminsdk-fbsvc-c28367ddb2.json');
// The public Web API key from assets/js/firebase-config.js — safe to use
// server-side too. api/update-admin-password.php uses it to call Identity
// Toolkit's accounts:signInWithPassword REST endpoint directly, which is
// how a current password gets verified before changing it (the Admin SDK
// itself has no "verify this password" call, only "set a new one").
define('FIREBASE_WEB_API_KEY', 'AIzaSyAkzh-TEWBvf2x5BB9gyI1WV_9dSu_SSn8');

$factory = (new Factory)->withServiceAccount(FIREBASE_CREDENTIALS_PATH);

$firebaseAuth = $factory->createAuth();
$firestore = new FirestoreRestClient(FIREBASE_PROJECT_ID, FIREBASE_CREDENTIALS_PATH);
