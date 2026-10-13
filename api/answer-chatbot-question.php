<?php
/**
 * =====================================================================
 * Answer Chatbot Question (api/answer-chatbot-question.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/chatbot-reports.js from the "Answer" modal on
 * subadmin/chatbot_reports.php's "Needs Your Answer" table. The only
 * thing that ever writes to the chatbot_custom_answers collection —
 * includes/public/chatbot_engine.php's hc_chatbot_find_custom_answer()
 * is the only thing that ever reads it back, checked on every real
 * chatbot message from this point on (see api/chatbot-ask.php's own
 * pipeline comment, step #2b). This is the entire mechanism behind
 * "the chatbot learns answers beyond its original hand-built intents."
 *
 * Doc id is md5(questionNormalized) — the exact same hash
 * hc_chatbot_find_custom_answer()'s exact-match fast path looks up by
 * — so answering the same question twice (e.g. refining a previous
 * answer) updates that one doc instead of creating a duplicate.
 *
 * Body: {"questionNormalized": "...", "questionSample": "...", "answer": "..."}.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php'; // $firestore
require_once __DIR__ . '/../includes/shared/audit_log_writer.php'; // write_audit_log()

header('Content-Type: application/json');

if (($_SESSION['role'] ?? null) !== 'subadmin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$questionNormalized = trim((string) ($body['questionNormalized'] ?? ''));
$questionSample = trim((string) ($body['questionSample'] ?? ''));
$answer = trim((string) ($body['answer'] ?? ''));

if ($questionNormalized === '' || $answer === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'An answer is required.']);
    exit;
}

$docId = md5($questionNormalized);
$existing = $firestore->getDocument('chatbot_custom_answers', $docId);

$ok = $firestore->setDocument('chatbot_custom_answers', $docId, [
    'questionNormalized' => $questionNormalized,
    'questionSample' => $questionSample,
    'answer' => $answer,
    'hitCount' => (int) ($existing['hitCount'] ?? 0),
    'createdBy' => $_SESSION['name'] ?? 'Sub-Administrator',
    'createdAt' => $existing['createdAt'] ?? date('c'),
    'updatedAt' => date('c'),
]);

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save the answer. Please try again.']);
    exit;
}

write_audit_log(
    $existing === null ? 'created' : 'updated',
    'Chatbot Reports',
    'Taught the chatbot an answer for: "' . $questionSample . '"'
);

echo json_encode(['success' => true]);
