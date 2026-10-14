<?php
/**
 * =====================================================================
 * Chatbot — Ask (api/chatbot-ask.php)
 * ---------------------------------------------------------------------
 * Called by assets/js/chatbot-widget.js — the global floating chat
 * widget (includes/shared/chatbot_widget.php) present on every public
 * page. Public, no session/role gate — this chatbot has no accounts to
 * be signed into.
 *
 * Pipeline, in order, each one falling through to the next on any
 * failure or non-match:
 *   0. Emergency detection — checked first, unconditionally, before
 *      Dialogflow is even called. Safety-critical: never let intent-
 *      classification uncertainty risk missing an emergency message
 *      (Dialogflow could misclassify it into some other intent and
 *      short-circuit the pipeline before the legacy engine's own copy
 *      of this same check ever runs).
 *   0a. Harm intent (self-harm/suicide, or harm to someone else) —
 *      same unconditional top priority as #0, checked right after it.
 *      Real gap this closes: "what medicine can I use to kill" used to
 *      fall straight into the medicine-availability lookup below and
 *      confirm real stock status — see hc_chatbot_is_harm_intent()'s
 *      own header comment for the full reasoning (English AND Tagalog,
 *      this clinic's real patients write in both).
 *   0b. RBAC policy question ("what can an administrator access?") —
 *      checked BEFORE the confidentiality gate specifically because
 *      that gate would otherwise swallow it (role word + "access"/
 *      "information" both present) and wrongly refuse a question this
 *      system should actually answer — describing a role's general
 *      scope is policy documentation, not a data leak.
 *   0c. Confidential/patient-data request detection — same reasoning
 *      as #0, checked right after it. This chatbot has no real code
 *      path into patient records/lab results/IDs at all (confirmed —
 *      see hc_chatbot_is_confidential_request()'s own comment), but
 *      this still runs first and unconditionally so a request for
 *      that kind of information always gets the same fixed, honest
 *      refusal rather than any chance of Dialogflow/Gemini improvising
 *      something that looks like real patient data.
 *   0d. Security/access-control bypass detection — same reasoning as
 *      #0c, for a different failure mode: not leaking data, but
 *      refusing to pretend to disable auth, delete audit logs, grant
 *      admin rights, roleplay as staff, or obey an in-message
 *      instruction to override this chatbot's own rules.
 *   0e. Short follow-up reference ("how many are there", "is it
 *      available") — resolved against the ONE most-recently-discussed
 *      subject, stashed in $_SESSION by whichever path (Dialogflow/
 *      decision-tree or the legacy engine) last resolved one — see
 *      hc_chatbot_remember_topic()'s own comment. Fast/free/
 *      deterministic, but only covers the phrasings
 *      hc_chatbot_is_followup_reference() was actually written to
 *      recognize — #3 below is the real catch-all for everything else.
 *   1. Dialogflow (NLU) -> Decision Tree (real DB lookups) -> Gemini
 *      (phrasing ONLY verified facts) — for the clinic's own defined
 *      intents (medicine/vaccine stock, vaccination announcements,
 *      services, hours, health tips, greeting). Never invents facts.
 *   2. The legacy keyword engine (hc_chatbot_answer()) — covers the
 *      same ground as #1 via plain text matching, for whenever
 *      Dialogflow isn't configured or didn't confidently match.
 *   2b. A subadmin-authored custom answer (hc_chatbot_find_custom_answer()) —
 *      checked only once #1 and #2 both have nothing, so it can never
 *      shadow an already-working built-in intent. This is the learning
 *      loop behind subadmin/chatbot_reports.php's "Needs Your Answer"
 *      queue: every question that falls past this point gets logged as
 *      needing review (see hc_chatbot_log_exchange() below); once a
 *      subadmin answers one there, it's matched here on every future
 *      message from then on, right alongside the hand-built decision
 *      tree — so this chatbot isn't permanently limited to only the
 *      intents it shipped with.
 *   3. hc_gemini_general_reply() — genuinely open-domain questions
 *      AND free-form follow-ups that don't match #0e's hand-written
 *      patterns either ("where?", "but that's already done") — given
 *      the real last few turns of this conversation
 *      (hc_chatbot_recall_history()) as context, so it can actually
 *      understand what a bare follow-up refers to instead of the
 *      conversation dead-ending in the fixed message below just
 *      because no one anticipated that exact phrasing. Only reached
 *      once #0e, #1, #2, and #2b all agree there's nothing specific to
 *      look up, so it can never override a verified answer — see its
 *      own header comment for why it's still scoped tightly even
 *      though it's not fact-constrained the way hc_gemini_generate_reply()
 *      is.
 *   4. A fixed "I don't understand" message — only if #3 isn't
 *      configured either (no Gemini key yet) or its call fails.
 * The patient always gets a real reply either way. Every real exchange
 * (whichever stage answered it) is recorded via hc_chatbot_remember_turn()
 * right before responding, so #3 always has up-to-date context on the
 * NEXT message, and so does #0e's own topic memory already — and,
 * separately, via hc_chatbot_log_exchange() for
 * subadmin/chatbot_reports.php's own real, persisted history (nothing
 * above this point was ever saved anywhere before that feature existed
 * — see that function's header comment).
 * =====================================================================
 */

require_once __DIR__ . '/../includes/public/chatbot_engine.php'; // hc_chatbot_answer() — the fallback
require_once __DIR__ . '/../includes/public/chatbot_decision_tree.php'; // hc_chatbot_decide(), hc_chatbot_phrase_decision()
require_once __DIR__ . '/../includes/public/gemini_client.php'; // hc_gemini_generate_reply(), hc_gemini_general_reply()
require_once __DIR__ . '/../includes/public/groq_client.php'; // hc_groq_generate_reply(), hc_groq_general_reply() — tried only after a real Gemini failure, see this file's own header comment
require_once __DIR__ . '/../config/dialogflow.php';
require_once __DIR__ . '/../firebase/DialogflowRestClient.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// Started unconditionally (not just when Dialogflow is configured) so
// the follow-up-memory check below (#0e) works regardless of
// Dialogflow's own setup state — this is the ONLY state this endpoint
// keeps between requests, so it needs to always be available.
session_start();

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$message = trim((string) ($body['message'] ?? ''));

if ($message === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please type a message.']);
    exit;
}

$lowerMessage = strtolower($message);
$rbacRole = hc_chatbot_detect_rbac_role($lowerMessage);

// Emergency/RBAC/confidential/security-bypass/follow-up — checked
// first, unconditionally, before Dialogflow or any Gemini call, same
// priority order as always (see the header comment above). Chained via
// ??= (instead of each branch echoing + exiting on its own) so every
// path funnels through the single "record + respond" block at the
// bottom — that's what lets hc_gemini_general_reply() below always see
// an up-to-date history on the NEXT message, no matter which stage
// answered THIS one.
// $matchType tracks WHICH stage actually produced $answer, purely for
// hc_chatbot_log_exchange() at the bottom — subadmin/chatbot_reports.php
// reads this back to know which exchanges already came from a real,
// verified source (never shown for review) vs. which ones didn't (shown
// in "Needs Your Answer"). Set once, the first time $answer stops being
// null — every later ??= below is a no-op once that's happened, same as
// $answer itself.
$matchType = null;
// Set true if a Gemini call was actually attempted AND threw a real
// error (not "unconfigured", not "declined to answer") anywhere in
// this request — see includes/public/gemini_client.php's
// hc_gemini_last_error() for the full reasoning. Fed into
// hc_chatbot_log_exchange() below for subadmin/chatbot_reports.php's
// "Gemini Failures" KPI card.
$geminiFailureReason = null;

$answer = null;
$answer ??= hc_chatbot_is_emergency($lowerMessage) ? hc_chatbot_emergency_reply() : null;
$answer ??= hc_chatbot_is_harm_intent($lowerMessage) ? hc_chatbot_harm_reply() : null;
$answer ??= $rbacRole !== '' ? hc_chatbot_rbac_reply($rbacRole) : null;
$answer ??= hc_chatbot_is_confidential_request($lowerMessage) ? hc_chatbot_confidential_reply() : null;
$answer ??= hc_chatbot_is_security_bypass_request($lowerMessage) ? hc_chatbot_security_bypass_reply() : null;
// 0e. Short follow-up reference to whatever subject was just discussed
// — a medicine, a doctor, a health topic, the vaccination
// announcement, a scoped contact answer ("how many are there", "is he
// available", "tell me more") — answered straight from session
// memory, bypassing Dialogflow/the keyword engine entirely, since
// neither has any way to resolve a bare pronoun to a real subject.
$answer ??= hc_chatbot_followup_reply($lowerMessage);
if ($answer !== null) {
    $matchType = 'built_in'; // emergency/harm-intent/RBAC/confidential/security/follow-up memory — always confident, never needs review
}

const HC_DIALOGFLOW_CONFIDENCE_THRESHOLD = 0.6;

// Only these count as a resolved intent — anything else (including
// Dialogflow's own built-in "Default Welcome Intent"/"Default Fallback
// Intent", which report high confidence but map to nothing in the
// decision tree) falls through to the legacy keyword engine instead,
// same as no intent match at all.
const HC_DIALOGFLOW_KNOWN_INTENTS = [
    'ask_medicine_availability',
    'ask_vaccination_schedule',
    'ask_services',
    'ask_hours_contact',
    'ask_health_tips',
    'ask_covered_barangays',
    'ask_announcements',
    'ask_appointment',
    'ask_insurance',
    'ask_prescription_refill',
    'ask_registration',
    'ask_pricing',
    'ask_about_system',
    'ask_about_facility',
    'ask_doctors',
    'ask_health_symptom_info',
    'greeting',
    'emergency',
    'confidential_info_request',
    'security_bypass_request',
];

// $answer may already be set by one of the gates above (emergency/
// RBAC/confidential/security-bypass/follow-up) — NOT reset to null
// here, so this never overrides one of those with a Dialogflow/Gemini
// guess.
if ($answer === null && is_file(DIALOGFLOW_CREDENTIALS_PATH)) {
    try {
        $sessionId = $_SESSION['chatbot_session_id'] ??= bin2hex(random_bytes(16));

        $dialogflow = new DialogflowRestClient(DIALOGFLOW_PROJECT_ID, DIALOGFLOW_CREDENTIALS_PATH, DIALOGFLOW_LANGUAGE_CODE);
        $nlu = $dialogflow->detectIntent($sessionId, $message);

        if (in_array($nlu['intent'], HC_DIALOGFLOW_KNOWN_INTENTS, true) && $nlu['confidence'] >= HC_DIALOGFLOW_CONFIDENCE_THRESHOLD) {
            $decision = hc_chatbot_decide($nlu['intent'], $nlu['parameters'], $message);
            $geminiAnswer = hc_gemini_generate_reply($message, $decision);
            $geminiFailureReason ??= hc_gemini_last_error();
            // Groq only gets a turn when Gemini actually threw (not just
            // "declined to phrase it" — hc_gemini_last_error() is null in
            // that case too) — see includes/public/groq_client.php's own
            // header comment.
            $groqAnswer = ($geminiAnswer === null && $geminiFailureReason !== null) ? hc_groq_generate_reply($message, $decision) : null;
            $answer = $geminiAnswer ?? $groqAnswer ?? hc_chatbot_phrase_decision($decision);
        }
    } catch (\Throwable $e) {
        error_log('[Chatbot] Dialogflow/decision-tree pipeline failed, falling back: ' . $e->getMessage());
    }
}
if ($answer !== null && $matchType === null) {
    $matchType = 'decision_tree';
}

$answer ??= hc_chatbot_answer($message);
if ($answer !== null && $matchType === null) {
    $matchType = 'legacy_engine';
}

// 2b. A subadmin-authored custom answer — see this endpoint's own
// header comment (#2b) and hc_chatbot_find_custom_answer()'s for the
// full reasoning. $customAnswerId feeds hc_chatbot_log_exchange()
// below so its hitCount can be bumped.
$customAnswerId = null;
if ($answer === null) {
    $customMatch = hc_chatbot_find_custom_answer($message);
    if ($customMatch !== null) {
        $answer = $customMatch['answer'];
        $matchType = 'custom';
        $customAnswerId = $customMatch['id'];
    }
}

// Real conversation history as context — see this function's own
// header comment for why this is the actual fix for a free-form
// follow-up ("where?", "but that's already done") that doesn't match
// hc_chatbot_is_followup_reference()'s hand-written patterns above.
if ($answer === null) {
    $recentHistory = hc_chatbot_recall_history();
    $generalAnswer = hc_gemini_general_reply($message, $recentHistory);
    $geminiFailureReason ??= hc_gemini_last_error();
    if ($generalAnswer === null && $geminiFailureReason !== null) {
        // Same rule as the decision-tree phrasing step above: Groq only
        // steps in once a real Gemini failure is confirmed.
        $generalAnswer = hc_groq_general_reply($message, $recentHistory);
        if ($generalAnswer !== null) {
            $matchType = 'groq_general';
        }
    }
    $answer = $generalAnswer;
}
if ($answer !== null && $matchType === null) {
    $matchType = 'gemini_general';
}

if ($answer === null) {
    $answer = 'I\'m not sure I understood that. You can ask me things like "Is Paracetamol available?", "What services are available?", "When is the next vaccination?", or "What are your hours?". For anything else, please contact us directly.';
    $matchType = 'fallback';
}

// Recorded as a pair, AFTER $answer is final — hc_chatbot_recall_history()
// above (while computing $answer) must only ever see turns BEFORE this
// exchange, never this one twice (once from history, once as the
// explicit current question hc_gemini_general_reply() also appends).
hc_chatbot_remember_turn('user', $message);
hc_chatbot_remember_turn('bot', $answer);
$logId = hc_chatbot_log_exchange($message, $answer, $matchType, $customAnswerId, $geminiFailureReason);

// logId lets the browser's own "👎 This didn't answer my question" link
// (assets/js/chatbot-widget.js -> api/chatbot-feedback.php) flag
// exactly THIS exchange — null when logging failed, in which case that
// link simply doesn't render (see that file's own null-check).
echo json_encode(['success' => true, 'answer' => $answer, 'logId' => $logId]);
