<?php
/**
 * =====================================================================
 * Chatbot Reports (Sub-Admin) — Data
 * ---------------------------------------------------------------------
 * CONNECTED to real data — the chatbot_logs collection
 * (includes/public/chatbot_engine.php's hc_chatbot_log_exchange(),
 * written on every real exchange through api/chatbot-ask.php) and
 * chatbot_custom_answers (api/answer-chatbot-question.php, the only
 * thing that ever writes there).
 *
 * UNLIKE every other page in this Analytics & Reports family, this is
 * deliberately NOT scoped to this sub-admin's own facility: the
 * chatbot (a global floating widget on every public page — see
 * includes/shared/chatbot_widget.php) is one public bot for the whole
 * site, not assigned to any one facility, and
 * chatbot_logs has no facility field at all to scope by. This page
 * shows the real, system-wide chatbot activity to every sub-admin who
 * opens it — same real data regardless of which facility they belong
 * to, which is honest given there's only one chatbot in this app.
 *
 * Two real views built from the same log data, grouped by
 * hc_chatbot_normalize_question() so near-identical phrasings collapse
 * into one row instead of flooding either table with duplicates:
 *   - "Most Asked" — the top 5 questions actually asked more than once
 *     (count >= 2), ranked by how many times. Every exchange is logged,
 *     including one-offs, so without that floor this table would just
 *     be a dump of everything ever asked rather than a real "frequently
 *     asked" signal — same "Top 5" convention as this app's other
 *     ranked lists (e.g. subadmin/analytics_reports.php's Top 5
 *     Diagnoses).
 *   - "Needs Your Answer" — distinct questions where, at least once,
 *     EITHER the chatbot had no real answer (matchType 'gemini_general'/
 *     'fallback' — see hc_chatbot_log_exchange()'s own header comment
 *     for what each matchType means) OR a real person flagged the
 *     answer it DID give as wrong (api/chatbot-feedback.php's "👎 This
 *     didn't answer my question" link — the 'flagged' field) — a
 *     confidently wrong answer looks exactly like a correct one from
 *     the server's side alone, so that's the only way this queue can
 *     catch it. Either way, no chatbot_custom_answers doc can already
 *     exist for it. Answering one here (api/answer-chatbot-question.php)
 *     writes that doc, which hc_chatbot_find_custom_answer() then
 *     checks on every future message — the actual mechanism making the
 *     chatbot's knowledge grow past its original hand-built intents
 *     instead of being permanently limited to them.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../public/chatbot_engine.php'; // hc_chatbot_normalize_question()

$myUid = $_SESSION['uid'] ?? '';
$myDoc = $myUid !== '' ? $firestore->getDocument('users', $myUid) : null;

$adminProfile = [
    'name' => $_SESSION['name'] ?? 'Sub-Administrator',
    'role' => match ($_SESSION['role'] ?? null) {
        'subadmin' => 'Sub-Admin',
        'healthcare_staff' => 'Healthcare Staff',
        default => 'Sub-Administrator',
    },
    'facility' => $myDoc['facility'] ?? 'Unassigned Facility',
];

$analyticsDateRangeLabel = date('F j, Y');
$facilityOptions = ['North Super Health Center', 'East Super Health Center', 'West Super Health Center'];

// ---------------------------------------------------------------------
// Group every real logged exchange by its normalized question text.
// ---------------------------------------------------------------------
$chatbotGroups = []; // questionNormalized => ['questionNormalized','sampleMessage','count','lastAskedAt','needsAnswer']
foreach ($firestore->listDocuments('chatbot_logs') as $doc) {
    $f = $doc['fields'];
    $normalized = (string) ($f['questionNormalized'] ?? '');
    if ($normalized === '') {
        continue;
    }

    if (!isset($chatbotGroups[$normalized])) {
        $chatbotGroups[$normalized] = [
            'questionNormalized' => $normalized,
            'sampleMessage' => (string) ($f['message'] ?? $normalized),
            'count' => 0,
            'lastAskedAt' => '',
            'missed' => false,
            'flagged' => false,
        ];
    }

    $chatbotGroups[$normalized]['count']++;
    $askedAt = (string) ($f['createdAt'] ?? '');
    if ($askedAt > $chatbotGroups[$normalized]['lastAskedAt']) {
        $chatbotGroups[$normalized]['lastAskedAt'] = $askedAt;
        $chatbotGroups[$normalized]['sampleMessage'] = (string) ($f['message'] ?? $chatbotGroups[$normalized]['sampleMessage']);
    }

    if (in_array($f['matchType'] ?? '', ['gemini_general', 'fallback'], true)) {
        $chatbotGroups[$normalized]['missed'] = true;
    }
    if ($f['flagged'] ?? false) {
        $chatbotGroups[$normalized]['flagged'] = true;
    }
}

// Questions already answered by a subadmin — excluded from "Needs Your
// Answer" regardless of what any individual past log said, since every
// FUTURE occurrence will now match hc_chatbot_find_custom_answer()
// instead (same md5(normalized) id both sides use).
$chatbotCustomAnswerDocs = $firestore->listDocuments('chatbot_custom_answers');
$chatbotAnsweredIds = array_fill_keys(array_column($chatbotCustomAnswerDocs, 'id'), true);

// "Most Asked" means actually repeated — a question only ever asked
// once isn't a pattern worth surfacing here (every chatbot exchange
// gets logged, including one-offs, so without this filter this table
// would just be a dump of everything ever asked instead of the real
// signal: what people keep coming back to ask). Capped at the real top
// 5 — a subadmin scanning this wants the handful of questions that
// actually matter most, not a long tail of twenty-some near-ties.
$chatbotMostAsked = array_values(array_filter($chatbotGroups, fn ($g) => $g['count'] >= 2));
usort($chatbotMostAsked, fn ($a, $b) => $b['count'] <=> $a['count']);
$chatbotMostAsked = array_slice($chatbotMostAsked, 0, 5);

$chatbotNeedsAnswer = array_values(array_filter(
    $chatbotGroups,
    fn ($g) => ($g['missed'] || $g['flagged']) && !isset($chatbotAnsweredIds[md5($g['questionNormalized'])])
));
usort($chatbotNeedsAnswer, fn ($a, $b) => strcmp($b['lastAskedAt'], $a['lastAskedAt']));

// ---------------------------------------------------------------------
// Summary KPI cards — real counts, same "adm-card arp-kpi-card" look
// as every other report page's stat row.
// ---------------------------------------------------------------------
$chatbotKpiCards = [
    ['label' => 'Total Exchanges',      'value' => array_sum(array_column($chatbotGroups, 'count')), 'icon' => 'bi-chat-dots-fill',          'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8'],
    ['label' => 'Distinct Questions',   'value' => count($chatbotGroups),                             'icon' => 'bi-question-circle-fill',    'iconBg' => '#EFE7FB', 'iconColor' => '#7C3AED'],
    ['label' => 'Needs Your Answer',    'value' => count($chatbotNeedsAnswer),                        'icon' => 'bi-exclamation-triangle-fill', 'iconBg' => '#FDECDD', 'iconColor' => '#E8710A'],
    ['label' => 'Custom Answers Taught','value' => count($chatbotCustomAnswerDocs),                   'icon' => 'bi-mortarboard-fill',        'iconBg' => '#E4F7EA', 'iconColor' => '#1E8E3E'],
];
