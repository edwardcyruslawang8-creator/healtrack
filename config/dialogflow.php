<?php
/**
 * =====================================================================
 * Dialogflow Configuration (server-side)
 * ---------------------------------------------------------------------
 * Credentials for the Dialogflow ES agent used by the chatbot's NLU
 * step (intent + entity extraction only — see includes/public/
 * chatbot_decision_tree.php for the actual control logic that runs on
 * Dialogflow's output).
 *
 * Same Google Cloud project as Firebase (healtrackdb-4e786), but a
 * separate, narrowly-scoped service account ("Dialogflow API Client"
 * role only) rather than reusing the broader Firebase Admin SDK
 * account — least privilege: this key can only call Dialogflow, not
 * read/write Firestore or manage users.
 * =====================================================================
 */

define('DIALOGFLOW_PROJECT_ID', 'healtrackdb-4e786');
// Same reasoning as FIREBASE_CREDENTIALS_PATH in config/firebase.php:
// kept outside C:/xampp/htdocs (Apache's DocumentRoot) so the key can
// never be downloaded directly by URL.
define('DIALOGFLOW_CREDENTIALS_PATH', 'C:/xampp/Healtrack-secure/dialogflow-service-account.json');
// Dialogflow ES session IDs just need to be unique per conversation;
// no real user PII belongs in them.
define('DIALOGFLOW_LANGUAGE_CODE', 'en');
