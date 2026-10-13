<?php
/**
 * =====================================================================
 * Chatbot Feedback (api/chatbot-feedback.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/chatbot-widget.js — the global floating chat
 * widget (includes/shared/chatbot_widget.php) present on every public
 * page — when a visitor clicks "👎 This didn't answer my question"
 * under a bot reply. Public, no session/role gate — matches
 * api/chatbot-ask.php itself, which has none either (this chatbot has
 * no accounts to be signed into).
 *
 * WHY THIS EXISTS: includes/subadmin/chatbot_reports_firestore.php's
 * "Needs Your Answer" queue only ever caught a TOTAL miss — a question
 * that fell all the way through to Gemini's open-domain reply or the
 * fixed "I don't understand" message (matchType 'gemini_general'/
 * 'fallback'). A CONFIDENTLY wrong answer — one that matched a real
 * built-in branch, just the wrong one — never showed up there at all,
 * since nothing about that exchange looked unanswered server-side. This
 * is the other half: a real person telling us the answer they actually
 * got was wrong, regardless of which pipeline stage produced it.
 *
 * Sets 'flagged' => true on the referenced chatbot_logs/{logId} doc
 * (created with 'flagged' => false by hc_chatbot_log_exchange() for
 * every exchange) — includes/subadmin/chatbot_reports_firestore.php
 * reads this back and includes a flagged question in "Needs Your
 * Answer" the same as any other, so it goes through the exact same
 * teaching workflow (api/answer-chatbot-question.php).
 *
 * Body: {"logId": "..."}.
 * =====================================================================
 */

require_once __DIR__ . '/../config/firebase.php'; // $firestore

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$logId = trim((string) ($body['logId'] ?? ''));

if ($logId === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing logId.']);
    exit;
}

$existing = $firestore->getDocument('chatbot_logs', $logId);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'That exchange could not be found.']);
    exit;
}

$existing['flagged'] = true;
$firestore->setDocument('chatbot_logs', $logId, $existing);

echo json_encode(['success' => true]);
