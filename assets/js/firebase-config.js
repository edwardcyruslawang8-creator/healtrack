/**
 * =====================================================================
 * Firebase Client Config (login.php, forgot-password.php)
 * ---------------------------------------------------------------------
 * This is the public, client-side Firebase Web SDK config — safe to
 * expose in the browser (Firebase's own docs confirm this isn't a
 * secret key, unlike the server-side Admin SDK credentials in
 * config/firebase.php / firebase/firebase_credentials.json).
 *
 * Loaded via CDN <script> tags, in this order:
 *   1. https://www.gstatic.com/firebasejs/10.x/firebase-app-compat.js
 *   2. https://www.gstatic.com/firebasejs/10.x/firebase-auth-compat.js
 *   3. https://www.gstatic.com/firebasejs/10.x/firebase-firestore-compat.js
 *      (this file calls firebase.firestore() unconditionally below, so
 *      this script is required even on a page that doesn't use `db`
 *      itself — e.g. forgot-password.php loads it only to avoid that
 *      line throwing)
 *   4. this file (initializes the app)
 *   5. assets/js/login.js or assets/js/forgot-password.js (uses
 *      firebase.auth() for sign-in / password-reset emails)
 * =====================================================================
 */
const firebaseConfig = {
    apiKey: "AIzaSyAkzh-TEWBvf2x5BB9gyI1WV_9dSu_SSn8",
    authDomain: "healtrackdb-4e786.firebaseapp.com",
    projectId: "healtrackdb-4e786",
    storageBucket: "healtrackdb-4e786.firebasestorage.app",
    messagingSenderId: "751034583942",
    appId: "1:751034583942:web:fca2289f4acd476e19ba07",
    measurementId: "G-ZCSNTXTXD5"
};

firebase.initializeApp(firebaseConfig);

// Used by login.js to look up the signed-in user's role at users/{uid}.
const db = firebase.firestore();
