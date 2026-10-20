<?php
/**
 * =====================================================================
 * Chatbot Engine — Real Answers from Real Data
 * ---------------------------------------------------------------------
 * Powers the global floating "Talk with NSHC" widget present on every
 * public page (includes/shared/chatbot_widget.php), via
 * api/chatbot-ask.php — one engine, one real answer, wherever the
 * question was asked from.
 *
 * NOT a general-purpose AI model — this is plain keyword/fuzzy-name
 * intent matching against this app's own real Firestore data, which is
 * exactly what this app's own manuscript describes for this feature
 * (see this session's earlier discussion of the chatbot's intended
 * decision-tree-style design): recognize what's being asked, then
 * answer it from real data instead of a canned/fabricated reply.
 * Genuinely data-driven intents:
 *   - Medicine/vaccine availability -> real includes/firestore/medicines.php
 *     status + stock count, fuzzy-matched against the question's words
 *     so small typos still resolve (same Levenshtein approach
 *     assets/js/gis.js already uses client-side for barangay search).
 *   - Vaccination schedule -> the most recent REAL "announcements" doc
 *     that actually mentions vaccination, not an invented date.
 * Contact details (phone/email/address) and operating hours are read
 * live from includes/shared/homepage_settings.php's
 * hc_get_homepage_settings() — the same real, subadmin-editable
 * settings index.php's own footer reads — via
 * hc_chatbot_contact_settings() below, rather than a hardcoded copy
 * that would silently drift out of sync the moment a sub-admin changes
 * the real contact info in subadmin/homepage_settings.php. The
 * remaining intents (services/health-tips/greeting) are genuinely
 * static institutional information with no Firestore collection of
 * their own to query, which is honest — nothing here pretends
 * otherwise.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/medicines.php'; // hc_get_inventory_items(), hc_medicine_status_meta()
require_once __DIR__ . '/../shared/predictive_analytics_core.php'; // HC_COVERED_BARANGAYS
require_once __DIR__ . '/../../firebase/firestore.php'; // getAnnouncements()
require_once __DIR__ . '/../firestore/consultations.php'; // hc_get_physician_options()
require_once __DIR__ . '/../firestore/lab_results.php'; // hc_lab_test_catalog()
require_once __DIR__ . '/../shared/homepage_settings.php'; // hc_get_homepage_settings()

if (!function_exists('hc_chatbot_contact_settings')) {
    /** @internal Memoized per request — every chatbot reply that needs contact info/hours calls this instead of its own Firestore read. */
    function hc_chatbot_contact_settings(): array
    {
        static $settings = null;
        if ($settings === null) {
            $settings = hc_get_homepage_settings();
        }

        return $settings;
    }
}

if (!function_exists('hc_chatbot_hours_sentence')) {
    /** Turns the real operatingHours array into one plain sentence, e.g. "Monday - Friday 8:00 AM - 5:00 PM, Saturday: Closed, Sunday & Holidays: Closed." — never a hardcoded guess at what the hours currently are. */
    function hc_chatbot_hours_sentence(array $operatingHours): string
    {
        $parts = array_map(function ($row) {
            $hours = (string) ($row['hours'] ?? '');
            $label = (string) ($row['label'] ?? '');
            return strcasecmp($hours, 'closed') === 0 ? ($label . ': Closed') : ($label . ' ' . $hours);
        }, $operatingHours);

        return 'We\'re open ' . implode(', ', $parts) . '.';
    }
}

if (!function_exists('hc_chatbot_bullet_list')) {
    /**
     * Formats a lead-in sentence + a real list of items as one
     * bulleted reply, instead of a long comma-separated run-on —
     * easier to scan for anything with more than a couple of items
     * (medicine/vaccine lists, doctors, barangays, announcements,
     * services, health tips). Relies on .chat-bubble/.chatbot-bubble's
     * `white-space: pre-line` (assets/css/chatbot.css, assets/css/
     * home.css) to actually render the \n line breaks — the bubble's
     * text is set via textContent, not innerHTML, so this stays plain
     * text and safe, never real markup.
     */
    function hc_chatbot_bullet_list(string $intro, array $items, string $outro = ''): string
    {
        $body = $intro . "\n" . implode("\n", array_map(fn ($item) => "• {$item}", $items));

        return $outro !== '' ? $body . "\n\n" . $outro : $body;
    }
}

if (!function_exists('hc_chatbot_levenshtein')) {
    function hc_chatbot_levenshtein(string $a, string $b): int
    {
        $m = strlen($a);
        $n = strlen($b);
        if ($m === 0) {
            return $n;
        }
        if ($n === 0) {
            return $m;
        }
        $dp = range(0, $n);
        for ($i = 1; $i <= $m; $i++) {
            $prev = $dp[0];
            $dp[0] = $i;
            for ($j = 1; $j <= $n; $j++) {
                $temp = $dp[$j];
                $dp[$j] = $a[$i - 1] === $b[$j - 1] ? $prev : 1 + min($prev, $dp[$j], $dp[$j - 1]);
                $prev = $temp;
            }
        }

        return $dp[$n];
    }
}

if (!function_exists('hc_chatbot_find_medicine_match')) {
    /**
     * Does this question mention a real medicine/vaccine by name? Matches
     * against just the first word of each real item's name (e.g.
     * "Amoxicillin" out of "Amoxicillin 500 mg Capsule") since that's
     * what someone would actually type, tolerant of small typos the same
     * way assets/js/gis.js's barangay search already is.
     *
     * @return array|null The matched real inventory item (see hc_get_inventory_items()), or null.
     */
    function hc_chatbot_find_medicine_match(string $lowerQuestion): ?array
    {
        $items = array_merge(
            array_map(fn ($m) => $m + ['itemType' => 'medicine'], hc_get_inventory_items('medicine')),
            array_map(fn ($v) => $v + ['itemType' => 'vaccine'], hc_get_inventory_items('vaccine'))
        );
        $words = preg_split('/[^a-z0-9]+/', $lowerQuestion, -1, PREG_SPLIT_NO_EMPTY);

        $best = null;
        $bestScore = 0.0;
        foreach ($items as $item) {
            $firstWord = strtolower((explode(' ', trim((string) $item['name'])))[0] ?? '');
            if (strlen($firstWord) < 4) {
                continue; // too short/generic a token to fuzzy-match safely
            }
            foreach ($words as $word) {
                if (strlen($word) < 4) {
                    continue;
                }
                if (str_contains($firstWord, $word) || str_contains($word, $firstWord)) {
                    $score = 1.0;
                } else {
                    $dist = hc_chatbot_levenshtein($word, $firstWord);
                    $score = 1 - $dist / max(strlen($word), strlen($firstWord));
                }
                if ($score >= 0.72 && $score > $bestScore) {
                    $bestScore = $score;
                    $best = $item;
                }
            }
        }

        return $best;
    }
}

if (!function_exists('hc_chatbot_normalize_question')) {
    /**
     * Lowercase, trimmed, punctuation-stripped form of a question — used
     * both to GROUP near-identical phrasings together for the "Most
     * Asked"/"Needs Your Answer" reports (subadmin/chatbot_reports.php)
     * and as the deterministic Firestore doc id for a custom answer
     * (api/answer-chatbot-question.php), so answering the same question
     * twice updates that one doc instead of creating a duplicate.
     */
    function hc_chatbot_normalize_question(string $question): string
    {
        $normalized = strtolower(trim($question));
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }
}

if (!function_exists('hc_chatbot_question_similarity')) {
    /**
     * How alike two already-normalized questions are, as the fraction of
     * their significant (non-stopword) words in common (Jaccard
     * overlap) — same spirit as hc_chatbot_levenshtein()'s fuzzy name
     * matching above, just at the whole-question level instead of a
     * single product name, since two people asking about the same real
     * thing rarely type it identically ("do you have paracetamol" vs
     * "is paracetamol available"). Not real NLP/embeddings — this
     * engine is deliberately plain keyword matching throughout (see
     * this file's header comment), and that's enough to usefully group
     * near-duplicate phrasings without an external ML service.
     */
    function hc_chatbot_question_similarity(string $normalizedA, string $normalizedB): float
    {
        static $stopwords = [
            'a', 'an', 'the', 'is', 'are', 'am', 'was', 'were', 'be', 'been',
            'do', 'does', 'did', 'can', 'could', 'will', 'would', 'should',
            'i', 'you', 'we', 'they', 'it', 'he', 'she', 'my', 'your', 'our',
            'to', 'of', 'for', 'in', 'on', 'at', 'with', 'about', 'if',
            'what', 'when', 'where', 'how', 'why', 'who', 'there', 'here',
            'have', 'has', 'had', 'me', 'us', 'this', 'that', 'please',
        ];

        $wordsA = array_diff(array_unique(preg_split('/\s+/', $normalizedA, -1, PREG_SPLIT_NO_EMPTY)), $stopwords);
        $wordsB = array_diff(array_unique(preg_split('/\s+/', $normalizedB, -1, PREG_SPLIT_NO_EMPTY)), $stopwords);

        if (!$wordsA || !$wordsB) {
            return 0.0;
        }

        $union = count(array_unique(array_merge($wordsA, $wordsB)));

        return $union > 0 ? count(array_intersect($wordsA, $wordsB)) / $union : 0.0;
    }
}

if (!function_exists('hc_chatbot_find_custom_answer')) {
    /**
     * Checks whether THIS question (or a close-enough phrasing of it)
     * already has a real, subadmin-authored answer on file — see
     * subadmin/chatbot_reports.php's "Needs Your Answer" workflow and
     * api/answer-chatbot-question.php, the only thing that ever writes
     * to the chatbot_custom_answers collection this reads from. Checked
     * (see api/chatbot-ask.php) AFTER the built-in decision tree/legacy
     * keyword engine — so an already-working built-in intent is never
     * silently shadowed by a possibly-stale manual answer — but BEFORE
     * the open-domain Gemini fallback, so a real, subadmin-verified
     * answer about THIS clinic always wins over Gemini's ungrounded
     * guess. This is the actual mechanism behind "the chatbot doesn't
     * only rely on the intents I made" — every question a subadmin
     * answers here is checked on every future message, forever, right
     * alongside the hand-built decision tree.
     *
     * Exact normalized match short-circuits straight to a getDocument()
     * by id (the same hc_chatbot_normalize_question() hash the doc was
     * saved under); anything less than exact falls back to a fuzzy scan
     * over every custom answer via hc_chatbot_question_similarity(),
     * same 0.55 threshold either way counts as "close enough" to reuse.
     *
     * @return array{id: string, answer: string}|null
     */
    function hc_chatbot_find_custom_answer(string $message): ?array
    {
        global $firestore;

        $normalized = hc_chatbot_normalize_question($message);
        if ($normalized === '') {
            return null;
        }

        $docId = md5($normalized);
        $exact = $firestore->getDocument('chatbot_custom_answers', $docId);
        if ($exact !== null && ($exact['answer'] ?? '') !== '') {
            return ['id' => $docId, 'answer' => (string) $exact['answer']];
        }

        $best = null;
        $bestScore = 0.0;
        foreach ($firestore->listDocuments('chatbot_custom_answers') as $doc) {
            $candidateNormalized = (string) ($doc['fields']['questionNormalized'] ?? '');
            $answer = (string) ($doc['fields']['answer'] ?? '');
            if ($candidateNormalized === '' || $answer === '') {
                continue;
            }
            $score = hc_chatbot_question_similarity($normalized, $candidateNormalized);
            if ($score >= 0.55 && $score > $bestScore) {
                $bestScore = $score;
                $best = ['id' => $doc['id'], 'answer' => $answer];
            }
        }

        return $best;
    }
}

if (!function_exists('hc_chatbot_log_exchange')) {
    /**
     * Records every real exchange to the chatbot_logs collection —
     * subadmin/chatbot_reports.php's entire "Most Asked"/"Needs Your
     * Answer" view is built purely by reading this back (see
     * includes/subadmin/chatbot_reports_firestore.php). $matchType is
     * one of: 'built_in' (the safety gates/follow-up memory),
     * 'decision_tree' (Dialogflow+decision tree), 'legacy_engine'
     * (hc_chatbot_answer()), 'custom' (a subadmin-authored answer —
     * $customAnswerId is that doc's id, whose hitCount is bumped here),
     * 'gemini_general'/'groq_general' (open-domain, ungrounded — Groq
     * only ever answers here after a real, confirmed Gemini failure,
     * see includes/public/groq_client.php), or 'fallback' (nothing
     * answered it at all). Only these last three ever show up in
     * "Needs Your Answer" — every other type already came from a real,
     * verified source and doesn't need subadmin review.
     *
     * Best-effort: a logging failure must never break an actual chat
     * reply, so this never throws. Returns the new chatbot_logs doc's
     * id (or null on failure/empty message) — api/chatbot-ask.php hands
     * this back to the browser as 'logId' so assets/js/chatbot-widget.js's
     * "👎 This didn't answer my question" link (api/chatbot-feedback.php)
     * can reference exactly this exchange.
     */
    function hc_chatbot_log_exchange(string $message, string $answer, string $matchType, ?string $customAnswerId = null, ?string $geminiFailureReason = null): ?string
    {
        global $firestore;

        $normalized = hc_chatbot_normalize_question($message);
        if ($normalized === '') {
            return null;
        }

        try {
            $logId = $firestore->addDocument('chatbot_logs', [
                'message' => $message,
                'questionNormalized' => $normalized,
                'answer' => $answer,
                'matchType' => $matchType,
                'flagged' => false,
                // A real Gemini API call was attempted somewhere in this
                // exchange AND it threw (not "unconfigured", not
                // "declined to answer") — see
                // includes/public/gemini_client.php's hc_gemini_last_error()
                // for the full reasoning. Read by
                // subadmin/chatbot_reports.php's "Gemini Failures" KPI.
                'geminiFailed' => $geminiFailureReason !== null,
                'geminiFailureReason' => $geminiFailureReason,
                'createdAt' => date('c'),
            ]);

            if ($matchType === 'custom' && $customAnswerId !== null) {
                $existing = $firestore->getDocument('chatbot_custom_answers', $customAnswerId);
                if ($existing !== null) {
                    $existing['hitCount'] = (int) ($existing['hitCount'] ?? 0) + 1;
                    $firestore->setDocument('chatbot_custom_answers', $customAnswerId, $existing);
                }
            }

            return $logId;
        } catch (\Throwable $e) {
            error_log('[Chatbot] hc_chatbot_log_exchange() failed: ' . $e->getMessage());

            return null;
        }
    }
}

if (!function_exists('hc_chatbot_item_status_reply')) {
    /**
     * Shared phrasing for a resolved medicine/vaccine item — used by
     * both a fresh name match here and a remembered item recalled via
     * hc_chatbot_recall_item(), so a follow-up ("how many are there")
     * is worded identically to the original lookup. Deliberately no
     * exact quantity — only available/low/unavailable, never a count.
     */
    function hc_chatbot_item_status_reply(array $item): string
    {
        $statusLabel = hc_medicine_status_meta()[$item['status']]['label'] ?? ucfirst($item['status']);

        return match ($item['status']) {
            'available' => "✅ Yes — {$item['name']} is currently {$statusLabel}.",
            'low' => "⚠️ {$item['name']} is currently {$statusLabel} — stock is limited, so please check with our pharmacy before visiting.",
            default => "❌ Unfortunately, {$item['name']} is currently {$statusLabel}. Please check back later or contact our pharmacy for alternatives.",
        };
    }
}

if (!function_exists('hc_chatbot_remember_turn')) {
    /**
     * Real short-term conversation memory — every turn (patient message
     * AND the bot's own final answer, whichever pipeline stage produced
     * it) gets appended here, capped to the last 6 EXCHANGES (12
     * entries). Used only by hc_gemini_general_reply() as real context
     * for the open-domain fallback, so a reply like "where?" or "but
     * that's already done" — which doesn't match ANY hand-written
     * intent/regex, here or in Dialogflow — can still be understood
     * from what was actually just discussed, instead of landing on the
     * fixed "I don't understand" message purely because no one
     * anticipated that exact phrasing. This is deliberately separate
     * from hc_chatbot_remember_topic()'s single-subject memory (which
     * stays fast/free/deterministic for the common pronoun cases it
     * already covers) — this is the fallback for everything that isn't.
     */
    function hc_chatbot_remember_turn(string $role, string $text): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || $text === '') {
            return;
        }

        $history = $_SESSION['hc_chatbot_history'] ?? [];
        $history[] = ['role' => $role, 'text' => $text];
        $_SESSION['hc_chatbot_history'] = array_slice($history, -12);
    }
}

if (!function_exists('hc_chatbot_recall_history')) {
    /** @return array<int, array{role: string, text: string}> Oldest first. */
    function hc_chatbot_recall_history(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return [];
        }

        $history = $_SESSION['hc_chatbot_history'] ?? [];

        return is_array($history) ? $history : [];
    }
}

if (!function_exists('hc_chatbot_remember_topic')) {
    /**
     * Stashes whatever ONE subject was just resolved — a medicine/
     * vaccine, a named doctor, a health-education topic, the
     * vaccination announcement, a scoped contact answer — so a short
     * follow-up next turn ("how many are there", "is he available",
     * "tell me more") can be answered without re-stating the subject,
     * instead of being misread as a brand-new, nameless query. See
     * hc_chatbot_is_followup_reference() and hc_chatbot_followup_reply(),
     * which dispatch on $type. This app's chatbot otherwise has zero
     * memory between requests; api/chatbot-ask.php starts the session
     * unconditionally specifically so this works regardless of whether
     * Dialogflow itself is configured.
     */
    function hc_chatbot_remember_topic(string $type, array $data): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION['hc_chatbot_last_topic'] = ['type' => $type, 'data' => $data];
    }
}

if (!function_exists('hc_chatbot_recall_topic')) {
    /** The last {type, data} hc_chatbot_remember_topic() stashed this session, or null if none yet. */
    function hc_chatbot_recall_topic(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        $topic = $_SESSION['hc_chatbot_last_topic'] ?? null;

        return is_array($topic) && isset($topic['type'], $topic['data']) ? $topic : null;
    }
}

if (!function_exists('hc_chatbot_remember_item')) {
    /** Thin wrapper over hc_chatbot_remember_topic() for the medicine/vaccine case specifically. */
    function hc_chatbot_remember_item(array $item): void
    {
        hc_chatbot_remember_topic('medicine', [
            'name' => $item['name'],
            'status' => $item['status'],
            'itemType' => $item['itemType'] ?? 'medicine',
        ]);
    }
}

if (!function_exists('hc_chatbot_is_followup_reference')) {
    /**
     * Short referential follow-ups to the ONE subject just discussed
     * (e.g. after "is amoxicillin available?", a bare "how many are
     * there" has no product name to fuzzy-match at all —
     * hc_chatbot_find_medicine_match() would otherwise wrongly try to
     * match the word "there" itself and fail; the same problem applies
     * to "is he available" after asking about a doctor, "tell me
     * more" after a health topic, etc.). Deliberately narrow: these
     * patterns only match pronoun/ellipsis phrasing, never a message
     * that names a real new subject, so a genuinely new question is
     * never swallowed here — it falls through to the normal pipeline.
     */
    function hc_chatbot_is_followup_reference(string $lowerQuestion): bool
    {
        return preg_match(
            '/\bhow (many|much) (are|is) (there|left)\b'
            . '|\b(is|are) (it|he|she|that|this|those|these) (still )?(available|in stock|left|here)\b'
            . '|\bwhat about (it|he|she|him|her|that|this|those|these|that one|this one)\b'
            . '|\bhow about (it|he|she|him|her|that|this)\b'
            . '|\b(is|are) there (any|some) (left|available)\b'
            . '|\btell me more\b|\bmore (info|information|details)\b'
            . '|\bwhat else (about|regarding) (it|him|her|that|this)\b'
            . '|\bwhere (does|is|can i find) (he|she|it)\b/i',
            $lowerQuestion
        ) === 1;
    }
}

if (!function_exists('hc_chatbot_is_meta_question')) {
    /**
     * Is this message asking ABOUT the chatbot's own last reply, rather
     * than asking a new question? ("why did you answer that I need a
     * specific medicine, not the medicine available here" — a real
     * example that used to trip branch 1b below: it contains both
     * "medicine" and "available", so without this guard it just
     * re-triggered the full "here's what we currently have available"
     * inventory dump instead of addressing what was actually asked —
     * a question about the bot's prior answer, not a new product
     * lookup). Checked before anything else in the pipeline that does
     * keyword matching (see hc_chatbot_followup_reply(), called from
     * api/chatbot-ask.php's stage #0e, ahead of Dialogflow/the legacy
     * engine/custom answers), so a meta-question never gets swallowed
     * by a keyword match on whatever words it happens to contain.
     */
    function hc_chatbot_is_meta_question(string $lowerQuestion): bool
    {
        return preg_match(
            '/\bwhy (did|do|would|are) you (say|answer|respond|tell me|mean)\b'
            . '|\bwhat do you mean\b'
            . '|\bthat\'?s not what i (asked|meant|wanted|said)\b'
            . '|\byou (didn\'?t|did not) answer\b'
            . '|\bthat (doesn\'?t|does not) answer\b'
            . '|\bi (didn\'?t|did not) ask (that|about that|for that)\b'
            . '|\bthat\'?s (wrong|incorrect|not (right|correct))\b/i',
            $lowerQuestion
        ) === 1;
    }
}

if (!function_exists('hc_chatbot_meta_reply')) {
    /** The honest reply for hc_chatbot_is_meta_question() — never re-guesses, just asks for the real subject plainly. */
    function hc_chatbot_meta_reply(): string
    {
        return '🙏 Sorry, I think I misunderstood your last question. Could you rephrase it, or tell me specifically which medicine, vaccine, service, or topic you\'d like to know about?';
    }
}

if (!function_exists('hc_chatbot_announcement_reply')) {
    /**
     * Shared phrasing for ONE real announcement — used both for the
     * initial "what's new?" answer (always the real latest one, never a
     * bulleted list of several at once) and for stepping one further
     * back into hc_chatbot_find_previous_announcement()'s remembered
     * list. $isLatest only changes the lead-in wording, never which
     * announcement is shown.
     */
    function hc_chatbot_announcement_reply(array $a, bool $isLatest = true): string
    {
        $posted = ($a['date'] ?? '') !== '' ? " (posted {$a['date']})" : '';
        $lead = $isLatest ? '📢 Here' . '\'s our latest announcement' : '📢 Here' . '\'s our previous announcement';

        return "{$lead}{$posted}: \"{$a['title']}\" — {$a['desc']}";
    }
}

if (!function_exists('hc_chatbot_is_previous_announcement_request')) {
    /**
     * "What about the previous one?" / "any older announcements?" /
     * "another announcement" — a DIFFERENT shape of follow-up than
     * hc_chatbot_is_followup_reference()'s pronoun-based patterns, so
     * it's checked separately in hc_chatbot_followup_reply() rather than
     * folded into that function: this one needs to mutate the
     * remembered 'announcement_list' index (step one further back),
     * not just re-state a static fact.
     */
    function hc_chatbot_is_previous_announcement_request(string $lowerQuestion): bool
    {
        return preg_match(
            '/\b(previous|older|prior|earlier|last|another|more|next)\b[^.?!]{0,20}\bannouncements?\b'
            . '|\bannouncements?\b[^.?!]{0,20}\b(before|prior|earlier)\b'
            . '|\bany other announcements?\b|\bwhat about (the )?(previous|older|last) one\b'
            . '|\b(the )?one before that\b/i',
            $lowerQuestion
        ) === 1;
    }
}

if (!function_exists('hc_chatbot_followup_reply')) {
    /**
     * Resolves a short follow-up against whatever topic was last
     * remembered, regardless of which kind of subject it was — not just
     * medicine. Returns null (so the caller falls through to the normal
     * pipeline) when the message isn't a follow-up at all, or nothing's
     * been discussed yet.
     */
    function hc_chatbot_followup_reply(string $lowerQuestion): ?string
    {
        // A question ABOUT the chatbot's own last reply, not a new
        // question itself — checked first and unconditionally (doesn't
        // depend on $topic at all), so it can never be swallowed by a
        // keyword match on whatever words it happens to contain further
        // down the pipeline. See hc_chatbot_is_meta_question()'s own
        // header comment for the real example this fixes.
        if (hc_chatbot_is_meta_question($lowerQuestion)) {
            return hc_chatbot_meta_reply();
        }

        $topic = hc_chatbot_recall_topic();

        // "Previous announcement" — checked first and independently of
        // hc_chatbot_is_followup_reference() below, since this phrasing
        // doesn't look like a pronoun reference at all. Only applies
        // when an announcement was genuinely the last thing discussed;
        // otherwise falls through to the normal pipeline, which still
        // answers a bare "previous announcement" reasonably (the real
        // latest one — there's nothing more specific to go on yet).
        if ($topic !== null && $topic['type'] === 'announcement_list' && hc_chatbot_is_previous_announcement_request($lowerQuestion)) {
            $list = $topic['data']['list'] ?? [];
            $nextIndex = ($topic['data']['index'] ?? 0) + 1;
            if (!isset($list[$nextIndex])) {
                return 'That\'s all the announcements we have on file right now — you can check our Announcements page for the full archive.';
            }
            hc_chatbot_remember_topic('announcement_list', ['list' => $list, 'index' => $nextIndex]);

            return hc_chatbot_announcement_reply($list[$nextIndex], false);
        }

        if (!hc_chatbot_is_followup_reference($lowerQuestion)) {
            return null;
        }
        if ($topic === null) {
            return null;
        }

        $d = $topic['data'];
        $doctorOutro = 'We don\'t have live schedule/duty data online yet, so for who\'s available today, please call us at ' . hc_chatbot_contact_settings()['contactPhone'] . ' or visit in person.';

        return match ($topic['type']) {
            'medicine' => hc_chatbot_item_status_reply($d),
            'doctor' => "👩‍⚕️ Yes, {$d['name']} is one of our doctors here at NSHC. {$doctorOutro}",
            'health_topic' => (hc_chatbot_health_topic_answer($d['topic']) ?? 'I don\'t have verified information on that topic yet.')
                . ' This is general information, not a diagnosis — for anything specific to you, please consult our healthcare staff directly.',
            'vaccination_announcement' => '💉 Here' . '\'s our latest vaccination announcement'
                . (($d['postedAt'] ?? '') !== '' ? " (posted {$d['postedAt']})" : '') . ": \"{$d['title']}\" — {$d['desc']}",
            // A generic follow-up ("tell me more") on the real announcement
            // just shown re-states that SAME one's full detail again —
            // stepping to the NEXT one is the dedicated branch above,
            // only for phrasing that actually asks for a different one.
            'announcement_list' => isset($d['list'][$d['index']]) ? hc_chatbot_announcement_reply($d['list'][$d['index']], $d['index'] === 0) : null,
            'contact' => $d['reply'],
            default => null,
        };
    }
}

if (!function_exists('hc_chatbot_list_available_items')) {
    /**
     * Real names of every currently-available (status === 'available')
     * medicine/vaccine, split by type. Backs the general "what
     * medicines are available?" question, which — unlike "do you have
     * paracetamol?" — doesn't name one specific item to look up.
     *
     * @return array{medicines: array<int, string>, vaccines: array<int, string>}
     */
    function hc_chatbot_list_available_items(): array
    {
        $names = fn (array $items) => array_values(array_map(
            fn ($i) => $i['name'],
            array_filter($items, fn ($i) => $i['status'] === 'available')
        ));

        return [
            'medicines' => $names(hc_get_inventory_items('medicine')),
            'vaccines' => $names(hc_get_inventory_items('vaccine')),
        ];
    }
}

if (!function_exists('hc_chatbot_list_doctors')) {
    /**
     * Real active doctor accounts on staff (hc_get_physician_options()),
     * scoped to this deployment's own facility. Names only — there's no
     * real schedule/shift/"on duty now" data anywhere in this system,
     * so that's deliberately NOT claimed here (see the caller's honest
     * deflect for the "available right now" part of the question).
     *
     * @return array<int, string>
     */
    function hc_chatbot_list_doctors(): array
    {
        return array_map(fn ($d) => $d['name'], hc_get_physician_options('North Super Health Center'));
    }
}

if (!function_exists('hc_chatbot_find_doctor_match')) {
    /**
     * Does this question name one specific real doctor (e.g. "who is
     * doctor Villareal?")? Same fuzzy word-matching approach as
     * hc_chatbot_find_medicine_match() — lets "who are your doctors?"
     * (no name) and "who is Dr. Villareal?" (a specific name) get
     * different, more accurate answers instead of both dumping the
     * full roster.
     */
    function hc_chatbot_find_doctor_match(string $lowerQuestion): ?string
    {
        $words = preg_split('/[^a-z0-9]+/', $lowerQuestion, -1, PREG_SPLIT_NO_EMPTY);

        $best = null;
        $bestScore = 0.0;
        foreach (hc_chatbot_list_doctors() as $name) {
            $nameWords = preg_split('/[^a-z0-9]+/', strtolower($name), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($nameWords as $nameWord) {
                // Real surnames can be short ("Sim") — a lower length
                // floor than the medicine matcher's is fine here since
                // there are only ever a handful of real doctors to
                // fuzzy-match against, not dozens of inventory items.
                if (strlen($nameWord) < 3 || in_array($nameWord, ['dr', 'doctor', 'doctors'], true)) {
                    continue; // "dr"/"doctor" itself isn't a name to match against
                }
                foreach ($words as $word) {
                    if (strlen($word) < 3) {
                        continue;
                    }
                    if (min(strlen($word), strlen($nameWord)) < 5) {
                        // Below 5 characters, a common short English
                        // word ("are", "sim"-as-in-"simple") can appear
                        // as a substring inside a much longer real name
                        // ("vill-ARE-al") and falsely score as a perfect
                        // match — require exact equality instead of
                        // substring containment for short words, only
                        // relaxing to substring/Levenshtein fuzziness
                        // once both sides are long enough for a partial
                        // match to be meaningfully specific.
                        $score = $word === $nameWord ? 1.0 : 0.0;
                    } elseif (str_contains($nameWord, $word) || str_contains($word, $nameWord)) {
                        $score = 1.0;
                    } else {
                        $dist = hc_chatbot_levenshtein($word, $nameWord);
                        $score = 1 - $dist / max(strlen($word), strlen($nameWord));
                    }
                    if ($score >= 0.72 && $score > $bestScore) {
                        $bestScore = $score;
                        $best = $name;
                    }
                }
            }
        }

        return $best;
    }
}

if (!function_exists('hc_chatbot_find_vaccination_announcement')) {
    /** The most recently posted real "announcements" doc that actually mentions vaccination, or null. */
    function hc_chatbot_find_vaccination_announcement(): ?array
    {
        global $firestore;

        $matches = [];
        foreach ($firestore->listDocuments('announcements') as $doc) {
            $f = $doc['fields'];
            $text = ($f['title'] ?? '') . ' ' . ($f['desc'] ?? '');
            if (preg_match('/vaccin/i', $text) === 1) {
                $matches[] = $f;
            }
        }
        usort($matches, fn ($a, $b) => strcmp($b['postedAt'] ?? '', $a['postedAt'] ?? ''));

        return $matches[0] ?? null;
    }
}

if (!function_exists('hc_chatbot_is_emergency')) {
    /**
     * Safety-critical, checked before ANY other logic (including
     * Dialogflow's own intent match, which is never trustworthy enough
     * on its own to gate this) — see api/chatbot-ask.php, which calls
     * this first, before even trying Dialogflow.
     */
    function hc_chatbot_is_emergency(string $lowerQuestion): bool
    {
        return preg_match('/\b(emergency|emergencies|dying|can\'?t breathe|severe bleeding|unconscious|overdose|heart attack|stroke)\b/i', $lowerQuestion) === 1;
    }
}

if (!function_exists('hc_chatbot_emergency_reply')) {
    function hc_chatbot_emergency_reply(): string
    {
        return 'If this is a medical emergency, please call your local emergency hotline or go to the nearest emergency room right away — do not wait for a reply here. For non-emergency concerns, I\'m happy to help.';
    }
}

if (!function_exists('hc_chatbot_is_confidential_request')) {
    /**
     * Privacy-critical, checked right after the emergency check and
     * before ANY other logic — including Dialogflow's own intent
     * match, same reasoning as hc_chatbot_is_emergency(). This chatbot
     * has NO real code path into the patients/consultations/lab-results
     * collections (confirmed: only medicines, announcements, and
     * doctor NAMES are ever queried anywhere in this file or
     * chatbot_decision_tree.php) — this check exists as a hard,
     * deterministic guarantee on top of that, not because a leak is
     * currently possible, and specifically to stop Gemini (in either
     * gemini_client.php function) from ever being asked to improvise
     * an answer that LOOKS like a real patient record, ID, or lab
     * result, which would be actively misleading even though fake.
     */
    function hc_chatbot_is_confidential_request(string $lowerQuestion): bool
    {
        // Tier 1 — credential/secret words. These are NEVER legitimate
        // for this chatbot to discuss, for ANY account or role, in ANY
        // phrasing or word order — so this needs no role-word pairing
        // at all, unlike Tier 2 below. This is what actually closes the
        // "one phrase at a time" gap: no future role name (or way of
        // naming one) can ever create a new hole here.
        if (preg_match('/\b(username|user name|password|passwords|log\s?in|credentials?|otp|passcode|pin code|verification code|2fa|two[\s-]factor)\b/i', $lowerQuestion) === 1) {
            return true;
        }

        // Tier 2 — any mention of a system role/account AND any mention
        // of a sensitive attribute, ANYWHERE in the message, not
        // necessarily adjacent or in "role's attribute" order. Covers
        // reordered/expanded phrasing like "the id of the healthcare
        // personnel account" without depending on exact grammar, and
        // the role list is now a broad catch-all (personnel/worker/
        // account/staff/etc.) rather than one entry per exact word
        // someone might type.
        $mentionsRole = preg_match('/\b(patient|doctor|physician|staff|employee|admin(istrator)?|subadmin|nurse|personnel|worker|receptionist|pharmacist|technician|account)\b/i', $lowerQuestion) === 1;
        $mentionsAttribute = preg_match('/\b(id|record|records|data|info|information|profile|history|file|files|email|contact info|personal)\b/i', $lowerQuestion) === 1;
        if ($mentionsRole && $mentionsAttribute) {
            return true;
        }

        // Tier 3 — specific phrasings that don't fit the role+attribute
        // shape at all (e.g. "lab results" names no role).
        return preg_match(
            '/\bmy (record|records|medical record|lab result|laboratory result|test result|results|consultation history|diagnosis)\b'
            . '|\blab(oratory)? results?\b|\btest results?\b|\bmedical (record|records|history)\b'
            . '|\bsomeone\'?s (record|records|data|information|id)\b'
            . '|\bconfidential\b|\bpatient list\b|\blist of patients\b|\bdatabase (id|record|records)\b'
            // Patient IDENTITIES specifically (who/which/names) — unlike
            // doctors, patients are never public information, so this is
            // its own alternative rather than folded into Tier 2, which
            // intentionally still lets "who are your doctors?" (a real,
            // legitimate feature) through since it has no attribute word.
            . '|\bwho (are|is) (your|the|our) patients?\b|\bwhich patients?\b'
            . '|\bpatients? (name|names|list)\b|\bname (a |some |your )?patients?\b'
            // Filtering/searching patients by a medical condition — no
            // "record"/"data"-style attribute word appears in this
            // phrasing at all, so Tier 2 alone would miss it.
            . '|\bpatients? (who|that) (has|have)\b|\bpatients? with (a |an )?(disease|condition|diagnosis|illness)\b'
            . '|\bwhich patients (has|have)\b'
            // Direct/bypass database access — "database" alone isn't
            // flagged (see api/gis-data.php etc., which legitimately
            // discuss "the database" in comments), only when paired with
            // access/bypass language.
            . '|\b(access|query|see|view) the database\b|\bdatabase access\b|\ball (the )?(patient )?records\b/i',
            $lowerQuestion
        ) === 1;
    }
}

if (!function_exists('hc_chatbot_is_harm_intent')) {
    /**
     * Safety-critical, checked at the SAME top priority as
     * hc_chatbot_is_emergency()/hc_chatbot_is_confidential_request() —
     * before Dialogflow, the decision tree, the legacy engine, or
     * Gemini ever see the message (see api/chatbot-ask.php). The real
     * gap this closes: a question like "anong gamot ang pede kong
     * gamitin para pumatay" ("what medicine can I use to kill") used to
     * fall straight into hc_chatbot_find_medicine_match() — it fuzzy-
     * matched "gamot"/medicine words and happily confirmed real stock
     * status (e.g. "Yes — Paracetamol is currently In Stock"), which is
     * actively dangerous. This system exists to help patients look up
     * real medicine/vaccine/service information, never to help plan
     * harm to anyone (including the person asking) — so this is checked
     * before any lookup logic ever runs, not bolted on after the fact.
     *
     * Two tiers:
     *   - Unambiguous self-harm/suicide phrases (English AND Tagalog —
     *     this clinic's real patients write in both, as the screenshot
     *     behind this fix did) match standalone, no pairing needed.
     *   - "Kill"/"poison"/"patayin" etc. are too ambiguous to match
     *     alone in Tagalog specifically — "pumatay" is also the
     *     everyday verb for turning an appliance OFF ("pumatay ng
     *     ilaw" = "turn off the light"), so those only count paired
     *     with a target (a person word) or a method (medicine/poison)
     *     within the same message, which is exactly the shape of a
     *     real harm question and not of "paano pumatay ng aircon".
     */
    function hc_chatbot_is_harm_intent(string $lowerQuestion): bool
    {
        // Tier 1 — unambiguous self-harm/suicide language, English and Tagalog.
        if (preg_match(
            '/\bsuicide\b|\bkill (myself|me)\b|\bend my (own )?life\b|\bdon\'?t want to live\b|\bwant to die\b'
            . '|\bself[\s-]?harm\b|\bhurt myself\b'
            . '|\bmagpakamatay\b|\bpagpapakamatay\b|\bgusto ko(ng)? mamatay\b|\bayoko na(ng)? mabuhay\b|\bsaktan ang sarili\b/i',
            $lowerQuestion
        ) === 1) {
            return true;
        }

        // Tier 2 — harm TO SOMEONE ELSE, or a "kill"/"poison" word that
        // needs a target or method nearby to be unambiguous (see header
        // comment above for why "pumatay" alone isn't enough).
        $hasKillWord = preg_match('/\b(kill|murder|poison)\b|\b(pumatay|papatayin|patayin|lasunin)\b/i', $lowerQuestion) === 1;
        if (!$hasKillWord) {
            return false;
        }
        $hasTargetOrMethod = preg_match(
            '/\b(someone|somebody|a person|people|him|her|them|my (wife|husband|partner|boyfriend|girlfriend|brother|sister|mom|mother|dad|father|friend|kapatid|asawa|kaibigan|anak))\b'
            . '|\b(tao|kapatid|asawa|kaibigan|kaaway)\b'
            . '|\b(medicine|drug|pill|dose|overdose|poison)\b'
            . '|\b(gamot|lason|gamit)\b/i',
            $lowerQuestion
        ) === 1;

        return $hasTargetOrMethod;
    }
}

if (!function_exists('hc_chatbot_harm_reply')) {
    /**
     * Deliberately doesn't try to guess whether hc_chatbot_is_harm_intent()
     * matched a self-harm phrasing or a harm-to-someone-else one — the
     * ambiguous Tier 2 case (just "kill" + "medicine", no stated target)
     * could honestly be either, so this covers both at once rather than
     * risk misreading a real crisis as something else. Never names a
     * method, dose, or product, regardless of what was asked.
     */
    function hc_chatbot_harm_reply(): string
    {
        return '💙 I\'m not able to help with that — this assistant is only here for real patient care questions (medicine/vaccine availability, services, appointments). '
            . 'If you\'re having thoughts of hurting yourself, please reach out right now: the National Center for Mental Health Crisis Hotline (Philippines) at 1553 (toll-free landline), '
            . '0966-351-4518 or 0917-899-8727 (Globe/TM), or 0908-639-2672 (Smart/TNT) — someone is available to talk, any time. '
            . 'If this is about keeping someone else safe, please contact your local authorities or emergency hotline directly.';
    }
}

if (!function_exists('hc_chatbot_health_topics')) {
    /**
     * Single source of truth for the 10 known health-education topics
     * — shared by hc_chatbot_answer()'s regex matching below AND
     * chatbot_decision_tree.php's per-topic Dialogflow intent cases,
     * so the fixed, sourced answer text only ever lives in one place.
     * Each entry: [topic key, matching regex, answer text ending in a
     * real CDC/WHO/DOH citation URL — never phrased freely by an LLM].
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    function hc_chatbot_health_topics(): array
    {
        // Each pattern also matches the common Tagalog/Filipino term for
        // the same topic (sipon, trangkaso, lagnat, etc.) — this engine
        // is the only part of the pipeline that still works when BOTH
        // Dialogflow (config/dialogflow.php's DIALOGFLOW_LANGUAGE_CODE is
        // English-only) and Gemini (whose free-tier quota this app has
        // hit before) are unavailable, so real Filipino patients asking
        // in Tagalog still get a grounded answer instead of the generic
        // "I'm not sure I understood that" fallback.
        return [
            ['cold', '/\b(common cold|colds?|sipon)\b/i', '🤧 A common cold usually brings a runny or stuffy nose, sore throat, cough, sneezing, and mild body aches or low fever, typically clearing up in about 7–10 days. Rest and fluids help; see a doctor if it doesn\'t improve or gets worse. (Source: CDC — https://www.cdc.gov/common-cold/about/index.html)'],
            ['flu', '/\b(flu|influenza|trangkaso)\b/i', '🤒 Flu symptoms usually start suddenly and are more intense than a cold — fever, chills, muscle aches, fatigue, headache, cough, and sore throat. Rest, fluids, and staying home to avoid spreading it are key; seek care if breathing becomes difficult or symptoms are severe. (Source: WHO — https://www.who.int/news-room/fact-sheets/detail/influenza-(seasonal))'],
            ['dengue', '/\bdengue\b/i', '🦟 Common dengue symptoms include sudden high fever, severe headache, pain behind the eyes, joint and muscle pain, and rash. Warning signs like severe abdominal pain, persistent vomiting, or bleeding need immediate medical attention — dengue can worsen quickly. (Source: Philippine DOH — https://doh.gov.ph/uhc/health-programs/dengue-prevention-and-control-program/)'],
            ['fever', '/\b(fevers?|lagnat)\b/i', '🌡️ For a mild fever at home: rest, drink plenty of fluids, dress lightly, and you may take paracetamol as directed on the packaging. Seek medical care if the fever is very high, lasts more than 2–3 days, or comes with other worrying symptoms. (Source: CDC — https://www.cdc.gov/common-cold/treatment/index.html)'],
            ['headache', '/\b(headaches?|sakit ng ulo|(sumasakit|masakit) (ang |na )?ulo)\b/i', '🤕 Common headache causes include stress, dehydration, lack of sleep, eye strain, and skipped meals. Most improve with rest, hydration, and over-the-counter pain relief. Seek care for a sudden, severe headache or one with other symptoms like vision changes. (Source: WHO — https://www.who.int/news-room/fact-sheets/detail/headache-disorders)'],
            ['diarrhea', '/\b(diarrh(o?ea)|pagtatae|nagtatae|lbm)\b/i', '🚰 Diarrhea means frequent loose or watery stools, sometimes with cramping, nausea, or fever. The main risk is dehydration, so fluids and oral rehydration salts (ORS) are important. See a doctor if it lasts more than 2 days, or has blood. (Source: WHO — https://www.who.int/news-room/fact-sheets/detail/diarrhoeal-disease)'],
            ['dehydration', '/\bdehydrat(ion|ed)?\b/i', '💧 To prevent dehydration, drink water regularly — more during heat, illness, exercise, or diarrhea/vomiting, when oral rehydration salts (ORS) help too. Watch for warning signs like dark urine, dizziness, or a dry mouth. (Source: WHO — https://www.who.int/news-room/fact-sheets/detail/diarrhoeal-disease)'],
            ['sore_throat', '/\b(sore throats?|sakit ng lalamunan|masakit (ang |na )?lalamunan)\b/i', '🗣️ A sore throat usually causes pain or scratchiness when swallowing, sometimes with redness or a mild fever. Warm fluids, rest, and lozenges can help; see a doctor if it\'s severe, lasts over a week, or comes with high fever or trouble breathing. (Source: CDC — https://www.cdc.gov/common-cold/about/index.html)'],
            ['infection_prevention', '/\binfectious disease|communicable disease|spread(ing)? of (disease|infection)/i', '🧼 Common ways to prevent the spread of infectious diseases include frequent handwashing, covering coughs and sneezes, staying home when sick, keeping vaccinations up to date, and practicing good food and water hygiene. (Source: WHO — https://www.who.int/health-topics/infection-prevention-and-control)'],
            ['cough', '/\b(coughs?|ubo)\b/i', '😮‍💨 See a healthcare professional if a cough lasts more than 2–3 weeks, or comes with fever, shortness of breath, chest pain, blood, or unexplained weight loss. (Source: CDC — https://www.cdc.gov/respiratory-viruses/about/index.html)'],
        ];
    }
}

if (!function_exists('hc_chatbot_health_topic_answer')) {
    /** Looks up one topic's answer by its key (e.g. 'dengue') — used by chatbot_decision_tree.php. */
    function hc_chatbot_health_topic_answer(string $topicKey): ?string
    {
        foreach (hc_chatbot_health_topics() as [$key, $pattern, $answer]) {
            if ($key === $topicKey) {
                return $answer;
            }
        }

        return null;
    }
}

if (!function_exists('hc_chatbot_is_lab_tests_question')) {
    /**
     * Is this asking what lab tests/exams are offered? Shared by
     * hc_chatbot_answer() below AND chatbot_decision_tree.php's
     * 'ask_services' case (same reasoning as hc_chatbot_contact_reply()'s
     * own header comment: without sharing this check, a Dialogflow-
     * matched "services" question would get the old generic summary
     * while an un-trained/fallback question got the real detailed
     * catalog — an inconsistent difference depending purely on which
     * path happened to handle it).
     */
    function hc_chatbot_is_lab_tests_question(string $lowerQuestion): bool
    {
        return preg_match(
            '/\b(lab(oratory)?)\b.{0,25}\b(test|tests|exam|exams|service|services)\b'
            . '|\b(test|tests|exam|exams)\b.{0,25}\b(lab(oratory)?)\b'
            . '|\bwhat (tests?|exams?) (do|can) you (offer|run|do|perform)\b/i',
            $lowerQuestion
        ) === 1;
    }
}

if (!function_exists('hc_chatbot_lab_tests_reply')) {
    /**
     * Real answer to "what lab tests do you offer?" — grouped straight
     * from includes/firestore/lab_results.php's hc_lab_test_catalog(),
     * the exact same fixed catalog doctor/active_consultation.php's
     * "Select Laboratory Tests" panel and api/complete-consultation.php's
     * own server-side validation both use — never an invented list (a
     * real example of the wrong kind of answer this replaces: an
     * ungrounded Gemini guess describing generic services like "rapid
     * tests for strep/flu/COVID-19" and "pregnancy testing" that this
     * clinic doesn't actually offer). There's no fixed X-ray exam
     * catalog anywhere in this app (medtech records each exam's name
     * freeform per request, not from a checkbox list), so that part
     * stays an honest, general mention instead of a guessed one.
     */
    function hc_chatbot_lab_tests_reply(): string
    {
        $lines = [];
        foreach (hc_lab_test_catalog() as $category => $tests) {
            $lines[] = $category . ' — ' . implode(', ', $tests);
        }

        return hc_chatbot_bullet_list(
            '🧪 Our laboratory currently offers these tests:',
            $lines,
            'We also offer X-ray imaging — ask our staff on-site about the specific exam you need. Availability can vary by day, so please confirm with our laboratory staff before your visit.'
        );
    }
}

if (!function_exists('hc_chatbot_contact_reply')) {
    /**
     * Scoped to exactly what was asked — "what's your contact number?"
     * gets just the number, not the full hours+email+address bundle.
     * Checked most-specific-first, same priority pattern as medicine-
     * name vs. general list. Shared by hc_chatbot_answer() below AND
     * chatbot_decision_tree.php's 'ask_hours_contact' case (passed the
     * same raw question) — Dialogflow's own single intent for this
     * doesn't distinguish "phone" from "hours" from "address", so
     * without sharing this logic, a Dialogflow-matched question would
     * still get the old blanket bundle while an un-trained/fallback
     * question got the scoped answer, which would be an inconsistent,
     * confusing difference depending purely on which path happened to
     * handle it.
     */
    function hc_chatbot_contact_reply(string $lowerQuestion): string
    {
        $c = hc_chatbot_contact_settings();

        if (preg_match('/\b(contact number|phone number|phone|call (us|you)?)\b/i', $lowerQuestion) === 1) {
            return 'You can reach us at ' . $c['contactPhone'] . '.';
        }
        if (preg_match('/\bemail\b/i', $lowerQuestion) === 1) {
            return 'You can email us at ' . $c['contactEmail'] . '.';
        }
        if (preg_match('/\b(address|location|where are you|where is (nshc|the clinic|the health center))\b/i', $lowerQuestion) === 1) {
            return 'We\'re located at ' . $c['contactAddress'] . '.';
        }
        if (preg_match('/\b(hours?|open|close[ds]?)\b/i', $lowerQuestion) === 1) {
            return hc_chatbot_hours_sentence($c['operatingHours']);
        }

        // Generic "how can I contact you?" with no specific channel
        // named — the only case that still gets the full bundle.
        return hc_chatbot_hours_sentence($c['operatingHours']) . ' Reach us at ' . $c['contactPhone'] . ' or ' . $c['contactEmail'] . ', or visit us at ' . $c['contactAddress'] . '.';
    }
}

if (!function_exists('hc_chatbot_confidential_reply')) {
    function hc_chatbot_confidential_reply(): string
    {
        return 'For your privacy and security, I can\'t access or share patient records, lab results, IDs, or any personal medical information through this chat — I don\'t have that data at all. To request your own records securely, please visit us in person or call ' . hc_chatbot_contact_settings()['contactPhone'] . ' and bring a valid ID.';
    }
}

if (!function_exists('hc_chatbot_is_security_bypass_request')) {
    /**
     * A DIFFERENT failure mode than hc_chatbot_is_confidential_request()
     * above: that one is about not LEAKING data; this one is about not
     * pretending to change, bypass, or escalate ACCESS CONTROL itself —
     * disabling auth, deleting audit trails, granting admin rights,
     * roleplaying as a higher-privileged user, or overriding this
     * chatbot's own rules via an in-message instruction ("ignore your
     * security rules"). None of that is remotely possible here (this
     * endpoint has no session, no role, no write access to anything —
     * see chatbot_decision_tree.php's header), but the reply should say
     * so plainly rather than leave it ambiguous or let Gemini improvise
     * agreement with the premise.
     */
    function hc_chatbot_is_security_bypass_request(string $lowerQuestion): bool
    {
        return preg_match(
            '/\b(disable|turn off|remove|bypass|skip|get around) (the )?(authentication|2fa|two[\s-]factor|login|verification)\b'
            . '|\b(delete|remove|clear|erase|wipe) (my |the |your )?(audit (trail|log)|logs?)\b'
            . '|\bwithout logging (the|this|it)\b|\bbypass(ing)? (the )?(audit|log)\b'
            . '|\bmake (my|this|the) account (an? )?admin(istrator)?\b|\bgive me admin(istrator)? (access|rights|privileges)\b'
            . '|\b(upgrade|promote) my account\b|\bbecome (an? )?admin(istrator)?\b'
            . '|\bpretend (i\'?m|you\'?re|i am|you are|to be)\b.{0,25}\b(admin|administrator|doctor|staff|nurse)\b'
            . '|\bact as (an? )?(admin|administrator|doctor|staff)\b|\brole[\s-]?play\b'
            . '|\bignore (your|the|all|previous) (security )?(rules|instructions|prompt)\b'
            . '|\bdisregard (your|the|previous) (rules|instructions)\b'
            . '|\bwithout (proper )?(permission|authorization)\b/i',
            $lowerQuestion
        ) === 1;
    }
}

if (!function_exists('hc_chatbot_security_bypass_reply')) {
    function hc_chatbot_security_bypass_reply(): string
    {
        return 'I can\'t change account permissions, disable security features, bypass audit logging, or act as a different role — none of that is something this chat can do, regardless of how it\'s asked. Access control here is enforced by the system itself, not by this conversation. If you need a permission change, that has to go through the proper account/admin process with our staff.';
    }
}

if (!function_exists('hc_chatbot_detect_rbac_role')) {
    /**
     * "What can an administrator access?" is a LEGITIMATE question,
     * unlike everything else on this page — describing what a role is
     * allowed to do in general terms is policy-level documentation, not
     * a data leak (no specific patient/record is ever named). Checked
     * BEFORE hc_chatbot_is_confidential_request() specifically because
     * that gate's Tier 2 would otherwise swallow this (role word +
     * "information"/"access" both present) and wrongly refuse a
     * question this system should actually answer.
     *
     * @return string One of hc_chatbot_rbac_descriptions()'s keys, or ''.
     */
    function hc_chatbot_detect_rbac_role(string $lowerQuestion): string
    {
        if (preg_match('/\bwhat (information|data)? ?can (a |an |the )?(.+?) (account )?(access|see|view|do)\b/i', $lowerQuestion, $m) !== 1) {
            return '';
        }

        $roleText = $m[3];
        return match (true) {
            preg_match('/\badmin(istrator)?\b/i', $roleText) === 1 => 'admin',
            preg_match('/\bsubadmin\b/i', $roleText) === 1 => 'subadmin',
            preg_match('/\bdoctor|physician\b/i', $roleText) === 1 => 'doctor',
            preg_match('/\b(medical staff|healthcare (worker|staff|personnel)|personnel|nurse)\b/i', $roleText) === 1 => 'medical-staff',
            preg_match('/\bpatient\b/i', $roleText) === 1 => 'patient',
            default => '',
        };
    }
}

if (!function_exists('hc_chatbot_rbac_reply')) {
    /**
     * General, policy-level description of each real role's own scope
     * in this system — accurate to the actual access rules elsewhere in
     * this codebase (facility scoping, the doctor-patient consent/
     * access-request workflow, etc.), without naming any specific
     * patient, record, or technical bypass detail.
     */
    function hc_chatbot_rbac_reply(string $role): string
    {
        return match ($role) {
            'admin' => 'Administrators can manage staff accounts, facility settings, system-wide reports, and disease-mapping data. They don\'t directly view or edit individual patients\' medical records.',
            'subadmin' => 'Subadmins manage announcements, patient consent records, and facility-level reports for their facility.',
            'doctor' => 'Doctors can access consultations and medical records for their own patients, and for other patients only after that patient\'s owning doctor approves AND the patient themselves consents by email — never automatically.',
            'medical-staff' => 'Medical staff (nurses, healthcare personnel) can view and update clinical records — labs, medications, vaccination records — for patients within their facility, under a doctor\'s supervision.',
            'patient' => 'Patients don\'t have login accounts in this system — this public chatbot and website are the only patient-facing access, and only to public information like medicine availability and announcements.',
            default => '',
        };
    }
}

if (!function_exists('hc_chatbot_answer')) {
    /**
     * Returns null (instead of a canned "I don't understand" string)
     * when nothing here matched, so api/chatbot-ask.php can try the
     * open-domain Gemini fallback (hc_gemini_general_reply()) before
     * ever showing the patient a dead-end reply.
     */
    function hc_chatbot_answer(string $question): ?string
    {
        $question = trim($question);
        if ($question === '') {
            return "Please type a question and I'll do my best to help.";
        }
        $lower = strtolower($question);

        // 0. Emergency — also checked earlier, before this engine even
        // runs (see api/chatbot-ask.php); kept here too so this engine
        // is still safe to call standalone.
        if (hc_chatbot_is_emergency($lower)) {
            return hc_chatbot_emergency_reply();
        }

        // 0b. "What can an administrator access?" — a legitimate
        // policy-level question, checked BEFORE the confidentiality
        // gate specifically because that gate's Tier 2 would otherwise
        // swallow it (role word + "access"/"information" both present)
        // and wrongly refuse something this system should answer.
        $rbacRole = hc_chatbot_detect_rbac_role($lower);
        if ($rbacRole !== '') {
            return hc_chatbot_rbac_reply($rbacRole);
        }

        // 0c. Confidential/patient data — also checked earlier in
        // api/chatbot-ask.php; kept here too so this engine is still
        // safe to call standalone.
        if (hc_chatbot_is_confidential_request($lower)) {
            return hc_chatbot_confidential_reply();
        }

        // 0d. Security/access-control bypass attempts (disable auth,
        // delete audit logs, grant admin rights, roleplay as staff,
        // prompt-injection phrasing) — also checked earlier in
        // api/chatbot-ask.php; kept here too so this engine is still
        // safe to call standalone.
        if (hc_chatbot_is_security_bypass_request($lower)) {
            return hc_chatbot_security_bypass_reply();
        }

        // 0e. Common health-topic education (cold, flu, dengue, fever,
        // headache, diarrhea, dehydration, sore throat, infection
        // prevention, persistent cough). Checked BEFORE #1's medicine-
        // name match specifically because some topic names are ALSO
        // real inventory items ("influenza" is both a disease and a
        // real "Influenza Vaccine" product) — a question containing a
        // clear symptom/education-context word like "symptoms" should
        // win over an incidental name match every time. Same principle
        // as everything else here: never let this get phrased freely by
        // an LLM with no real source — each entry is a fixed, pre-
        // written answer citing a real, verified public-health
        // authority page (CDC, WHO, or Philippine DOH — whichever
        // actually publishes a dedicated page for that topic), not this
        // chatbot's own claimed expertise. The URL is real text in the
        // reply; assets/js/chatbot-widget.js renders it as an actual
        // clickable link client-side.
        if (preg_match('/\b(symptoms?|signs?|causes?|prevent|prevention|treat|treatment|remedy|remedies|home|what (can|should) i do|when should i|consult|see a doctor|persistent|mild|severe|sintomas|sanhi|lunas|\w*iwasan)\b/i', $lower) === 1) {
            foreach (hc_chatbot_health_topics() as [$topicKey, $topicPattern, $answer]) {
                if (preg_match($topicPattern, $lower) === 1) {
                    hc_chatbot_remember_topic('health_topic', ['topic' => $topicKey]);

                    return $answer . ' This is general information, not a diagnosis — for anything specific to you, please consult our healthcare staff directly.';
                }
            }
        }

        // 1. Medicine/vaccine availability — a real name match is the most
        // specific, confident signal, so it's checked before anything else.
        $med = hc_chatbot_find_medicine_match($lower);
        if ($med !== null) {
            hc_chatbot_remember_item($med);

            return hc_chatbot_item_status_reply($med);
        }

        // 1a. Medication RECOMMENDATION requests — a fundamentally
        // different question than availability, and one this system
        // must never answer by pattern-matching "medicine" + a question
        // word into the inventory list. "Do you have medicine for a
        // cold?" is asking what's in stock (1b, below, handles it
        // correctly). "What medicine should I take for a cold?" / "Can
        // you tell me the medicine if I have a cold?" is asking this
        // system to recommend a specific drug — which it must never do
        // just because that drug happens to exist in inventory
        // (availability and clinical recommendation are different
        // functions). Checked before 1b specifically because "tell me"
        // + "medicine" would otherwise match 1b's broad trigger. The
        // "ano(ng) gamot" branch is the Tagalog equivalent ("ano gamot sa
        // sipon" = "what medicine for a cold") — same decline-and-
        // redirect-to-general-info behavior as the English phrasing.
        if (preg_match('/\b(what|which) (medicine|medication|drug)s? (should|do) i (take|use)\b|\bcan you tell me the medicine (if|when) i\b|\b(recommend|suggest) (a |some )?(medicine|medication|drug)\b|\bbest (medicine|medication|drug) for\b|\bwhat medication should i\b|\bano(ng)? (ang )?gamot\b|\bmay gamot (ba )?(sa|para sa)\b/i', $lower) === 1) {
            $topicKey = null;
            foreach (hc_chatbot_health_topics() as [$key, $topicPattern, $topicAnswer]) {
                if (preg_match($topicPattern, $lower) === 1) {
                    $topicKey = $key;
                    break;
                }
            }

            $decline = '🙏 I\'m not able to recommend a specific medicine — that needs to come from a doctor or pharmacist, not this chat, even for something that happens to be in our stock.';
            if ($topicKey !== null) {
                return $decline . ' Here\'s some general information instead: ' . hc_chatbot_health_topic_answer($topicKey);
            }

            return $decline . ' Please visit us or call ' . hc_chatbot_contact_settings()['contactPhone'] . ' to get a proper recommendation.';
        }

        // 1b. General "what medicines/vaccines are available?" — no
        // specific name to look up (that's #1 above), just a real list
        // of everything currently in stock. The second pattern used to
        // also match "about"/"what"/"tell me"/"know"/"info"/"information"
        // — common enough words that almost ANY sentence mentioning
        // "medicine" matched this, including a real example that wasn't
        // asking about stock at all ("why did you answer that I need a
        // specific medicine not the medicine available here" — a
        // question about the bot's OWN prior answer, now caught earlier
        // by hc_chatbot_is_meta_question() instead, but this was still
        // too broad on its own and worth narrowing regardless).
        if (preg_match('/\b(medicines?|vaccines?|meds)\b/i', $lower) === 1
            && preg_match('/\bavailable\b|\bin stock\b|\bdo you (have|carry|sell|offer)\b|\bgot any\b|\bwhat.{0,15}\b(have|offer|carry|stock)\b/i', $lower) === 1) {
            $list = hc_chatbot_list_available_items();
            $all = array_merge($list['medicines'], $list['vaccines']);
            if ($all === []) {
                return 'We don\'t have any medicines or vaccines marked as available right now. Please contact our pharmacy directly for the latest status.';
            }

            return hc_chatbot_bullet_list('💊 Here\'s what we currently have available:', $all);
        }

        // 2. Vaccination schedule — the real most recent vaccination announcement.
        if (preg_match('/vaccin|immuniz/i', $lower) === 1 && preg_match('/when|schedule|next|date|upcoming/i', $lower) === 1) {
            $ann = hc_chatbot_find_vaccination_announcement();
            if ($ann !== null) {
                $date = isset($ann['postedAt']) && $ann['postedAt'] !== '' ? date('M j, Y', strtotime($ann['postedAt'])) : '';
                $posted = $date !== '' ? " (posted {$date})" : '';
                hc_chatbot_remember_topic('vaccination_announcement', ['title' => $ann['title'], 'desc' => $ann['desc'], 'postedAt' => $date]);

                return "💉 Here's our latest vaccination announcement{$posted}: \"{$ann['title']}\" — {$ann['desc']}";
            }

            return 'There\'s no upcoming vaccination announcement on file right now. Please check our Announcements page or contact us directly for the latest schedule.';
        }

        // 2b. About this website/app/portal itself (HEALTrack) — checked
        // before #3's broader "what can you do" match so this more
        // specific question gets the right answer. Real feature list,
        // sourced from includes/public/data.php's own $navItems (the
        // sidebar), not invented.
        if (preg_match('/\bhealtrack\b|\bthis (website|system|app|platform|portal|site)\b|what can i do here|how does this (site|app|system|website) work/i', $lower) === 1) {
            return 'HEALTrack is North Super Health Center\'s online patient portal. From here you can check medicine & vaccine availability, view our GIS disease map, read announcements, search our records, and chat with me (NSHC Assistant) — right from this site, no appointment needed.';
        }

        // 2c. About the facility itself (NSHC) — real "About Us" copy
        // from this site's own homepage, not invented.
        if (preg_match('/\babout (nshc|you|this (clinic|health center|facility))\b|what is nshc|who are you\b/i', $lower) === 1) {
            return 'North Super Health Center is a community-based healthcare facility committed to providing accessible, reliable, and compassionate care to every barangay we serve — from general consultations and vaccinations to medicine availability and health advisories.';
        }

        // 2d. Doctor availability — real staff names, honestly separated
        // from "available right now"/schedule, which this system has no
        // real data for at all.
        if (preg_match('/\bdoctors?\b|\bphysicians?\b|\bdr\.?\b/i', $lower) === 1) {
            $outro = 'We don\'t have live schedule/duty data online yet, so for who\'s available today, please call us at ' . hc_chatbot_contact_settings()['contactPhone'] . ' or visit in person.';

            // A specific name was asked about (e.g. "who is Dr.
            // Villareal?") — confirm that one doctor, not the whole
            // roster, same as a specific medicine name takes priority
            // over the general "what's available" list above.
            $namedDoctor = hc_chatbot_find_doctor_match($lower);
            if ($namedDoctor !== null) {
                hc_chatbot_remember_topic('doctor', ['name' => $namedDoctor]);

                return "👩‍⚕️ Yes, {$namedDoctor} is one of our doctors here at NSHC. " . $outro;
            }

            $doctors = hc_chatbot_list_doctors();
            if ($doctors === []) {
                return 'We don\'t have any doctor accounts on file right now. ' . $outro;
            }

            return hc_chatbot_bullet_list('👩‍⚕️ Our doctors are:', $doctors, $outro);
        }

        // 2e. Laboratory/X-ray tests offered — checked BEFORE #3's
        // broader "services offered" bullet so this more specific
        // question gets the real, detailed catalog breakdown instead of
        // just the one summary line ("Laboratory & X-ray testing").
        // Grounded in the exact same fixed hc_lab_test_catalog() every
        // other real part of this app already uses — see
        // hc_chatbot_lab_tests_reply()'s own header comment for the
        // wrong, ungrounded kind of answer this replaces.
        if (hc_chatbot_is_lab_tests_question($lower)) {
            return hc_chatbot_lab_tests_reply();
        }

        // 3. Services offered — real, accurate to what this system actually
        // does; there's no "services" collection to query, so this stays
        // static rather than pretending otherwise.
        if (preg_match('/service|offer|what can you do|help with/i', $lower) === 1) {
            return hc_chatbot_bullet_list('🏥 NSHC offers:', [
                'Patient consultations',
                'Medicine & vaccine dispensing',
                'Laboratory & X-ray testing',
                'Vaccination records',
                'Real-time disease mapping for covered barangays',
            ], 'You can check medicine/vaccine availability or view the GIS map right from this site.');
        }

        // 3b. Covered barangays — the real list this facility's own GIS
        // disease-mapping feature already covers (HC_COVERED_BARANGAYS),
        // not a guess.
        if (preg_match('/barangay/i', $lower) === 1) {
            return hc_chatbot_bullet_list('📍 NSHC covers the following barangays:', HC_COVERED_BARANGAYS);
        }

        // 4. Hours / contact / location — same real values as this site's own footer.
        if (preg_match('/\b(hours?|open|close[ds]?|address|location|contact|phone|email|call)\b/i', $lower) === 1) {
            $contactReply = hc_chatbot_contact_reply($lower);
            hc_chatbot_remember_topic('contact', ['reply' => $contactReply]);

            return $contactReply;
        }

        // 4b. Latest announcements/news — real getAnnouncements() data
        // (approved-only), not limited to vaccination-related posts like
        // #2 above. Always the single real LATEST one first (never a
        // bulleted list of several at once, which buried which one was
        // actually most recent) — a real "previous announcement" follow-up
        // (hc_chatbot_is_previous_announcement_request(), checked earlier
        // in api/chatbot-ask.php's pipeline) steps one further back
        // through this same remembered list on request.
        if (preg_match('/\b(announcements?|news|updates?|what\'?s new|latest)\b/i', $lower) === 1) {
            $list = getAnnouncements(10);
            if ($list === []) {
                return 'There are no announcements posted right now. Please check our Announcements page later or contact us directly.';
            }
            hc_chatbot_remember_topic('announcement_list', ['list' => $list, 'index' => 0]);

            return hc_chatbot_announcement_reply($list[0]);
        }

        // 4c. Appointment booking — honest about what this system can
        // and can't do online rather than guessing (there's no patient
        // login/booking flow anywhere in this app).
        if (preg_match('/\bappointment(s)?\b|\bbook(ing)?\b/i', $lower) === 1) {
            return 'We don\'t currently support booking appointments online — walk-ins are welcome during our operating hours, or you can call us at ' . hc_chatbot_contact_settings()['contactPhone'] . ' to ask about scheduling a visit.';
        }

        // 4d. Insurance/PhilHealth — honest deflect; this system has no
        // real data on accepted insurance/PhilHealth arrangements, so it
        // asks the patient to confirm with staff rather than guessing.
        if (preg_match('/\binsurance\b|\bphilhealth\b|\bhmo\b/i', $lower) === 1) {
            return 'For insurance and PhilHealth coverage questions, please contact us directly at ' . hc_chatbot_contact_settings()['contactPhone'] . ' or ask our staff during your visit — that way you get the most accurate, up-to-date answer.';
        }

        // 4e. Prescription refills — same honest deflect; no online
        // refill-request flow exists in this system.
        if (preg_match('/\brefill\b|\bprescription\b/i', $lower) === 1) {
            return 'We don\'t currently support prescription refill requests online. Please visit us in person or call ' . hc_chatbot_contact_settings()['contactPhone'] . ', and bring your prescription or medicine record with you.';
        }

        // 4f. New-patient registration — honest deflect; this app has no
        // real self-registration flow/requirements to relay (patient
        // records here are created by staff, not by patients).
        if (preg_match('/\bregist(er|ration)\b|\bnew patient\b|\bsign\s?up\b|\bbecome a patient\b/i', $lower) === 1) {
            $c = hc_chatbot_contact_settings();
            return 'To register as a new patient, please visit us in person at ' . $c['contactAddress'] . ', or call ' . $c['contactPhone'] . ' for what to bring — we don\'t currently support registering online.';
        }

        // 4g. Cost/fees/pricing — honest deflect; no real pricing data
        // exists in this system to quote.
        if (preg_match('/\b(cost|price|pricing|fee|fees|how much)\b/i', $lower) === 1) {
            return 'For the most accurate pricing and fees, please contact us at ' . hc_chatbot_contact_settings()['contactPhone'] . ' or ask our staff directly — costs can depend on the specific service.';
        }

        // 5. General health tips — genuinely general, not personalized
        // medical advice (same disclaimer already shown on the chat page).
        if (preg_match('/healthy|health tip|wellness|stay healthy/i', $lower) === 1) {
            return hc_chatbot_bullet_list('💡 A few general tips:', [
                'Stay hydrated',
                'Eat a balanced diet',
                'Get regular exercise',
                'Keep your vaccinations up to date',
                "Don't skip your check-ups",
            ], 'For advice specific to you, please consult our healthcare staff directly.');
        }

        // 6. Greeting
        if (preg_match('/^(hi|hello|hey|kumusta|good\s?(morning|afternoon|evening))\b/i', $lower) === 1) {
            return '👋 Hello! I can help you check medicine/vaccine availability, our services, vaccination announcements, or our operating hours — what would you like to know?';
        }

        // 7. Nothing structured matched — let the caller try the
        // open-domain Gemini fallback instead of dead-ending here.
        return null;
    }
}
