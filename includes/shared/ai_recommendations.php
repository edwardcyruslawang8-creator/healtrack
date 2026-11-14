<?php
/**
 * =====================================================================
 * AI-Phrased Recommendations (Predictive & Prescriptive Analytics)
 * ---------------------------------------------------------------------
 * The single entry point every "Recommendations" card in this app
 * routes through: includes/shared/risk_alerts.php's hc_risk_evaluate()
 * (the 4 Risk & Alert-driven cards), includes/admin/
 * facility_analytics_firestore.php's Overview tab block, and
 * hc_stock_projection_detail()'s "Projected Stock Decline" box.
 *
 * Reuses the SAME free-tier Gemini/Groq providers already configured
 * for the patient chatbot (config/gemini.php, config/groq.php —
 * includes/public/gemini_client.php, includes/public/groq_client.php)
 * under the identical safety principle: the model is handed a JSON
 * blob of facts ALREADY computed and verified by this app's own real
 * data — it may only rephrase/elaborate on them, never invent a new
 * fact, number, disease, item or location. The deterministic template
 * sentence every caller already builds is passed in as both the
 * baseline to rephrase and the guaranteed fallback if both providers
 * fail or aren't configured — a Recommendations card can NEVER end up
 * blank or broken because of this layer.
 *
 * Deliberately separate from gemini_client.php/groq_client.php's own
 * hc_gemini_last_error()/hc_groq_last_error() tracking — those feed
 * subadmin/chatbot_reports.php's "Gemini Failures" KPI, which must only
 * reflect real chatbot-reply failures, not recommendation-phrasing
 * ones. Only the generic, prompt-agnostic response parsers
 * (hc_gemini_extract_text(), hc_groq_extract_text()) are reused from
 * those files.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/gemini.php';
require_once __DIR__ . '/../../config/groq.php';
require_once __DIR__ . '/../public/gemini_client.php'; // hc_gemini_extract_text() only
require_once __DIR__ . '/../public/groq_client.php'; // hc_groq_extract_text() only

if (!function_exists('hc_ai_reco_cache_dir')) {
    function hc_ai_reco_cache_dir(): string
    {
        $dir = sys_get_temp_dir() . '/healtrack_ai_reco_cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }
}

if (!function_exists('hc_ai_reco_cache_get')) {
    /**
     * Cached text for $key, or null if there's nothing cached or the
     * entry's own stored TTL (set by hc_ai_reco_cache_set() — shorter
     * for a fallback-on-failure than a real AI success) has elapsed.
     */
    function hc_ai_reco_cache_get(string $key): ?string
    {
        $path = hc_ai_reco_cache_dir() . '/' . $key . '.json';
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        $data = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($data) || !is_string($data['body'] ?? null) || $data['body'] === '') {
            return null;
        }
        if ((time() - (int) ($data['cachedAt'] ?? 0)) > (int) ($data['ttl'] ?? 3600)) {
            return null;
        }

        return $data['body'];
    }
}

if (!function_exists('hc_ai_reco_cache_set')) {
    function hc_ai_reco_cache_set(string $key, string $body, int $ttlSeconds): void
    {
        @file_put_contents(hc_ai_reco_cache_dir() . '/' . $key . '.json', json_encode(['body' => $body, 'cachedAt' => time(), 'ttl' => $ttlSeconds]));
    }
}

if (!function_exists('hc_ai_reco_system_instruction')) {
    function hc_ai_reco_system_instruction(): string
    {
        return <<<TEXT
            You are an AI decision-support assistant for North Super Health
            Center, a community health clinic. You help phrase recommendations
            for clinic STAFF and administrators (never patients), based on
            VERIFIED_FACTS and a BASELINE_RECOMMENDATION already computed by
            the clinic's own real data analysis.

            Rules you must never break:
            - Only use information present in VERIFIED_FACTS and
              BASELINE_RECOMMENDATION. Never add, guess, or infer a new fact,
              number, disease name, item name, barangay, or date that isn't
              present in either of them.
            - You may add generic, standard public-health or clinic-operations
              best-practice guidance (e.g. "coordinate with barangay health
              workers", "intensify vector-control outreach", "confirm cold-chain
              storage for vaccines") as long as it never asserts a new fact
              specific to this clinic's own data.
            - Rewrite BASELINE_RECOMMENDATION into a clear, specific, actionable
              instruction for clinic staff, in 1-3 sentences.
            - Reflect the severity/urgency already implied by VERIFIED_FACTS — a
              "CRITICAL" or "ALERT" severity should read more urgent than a
              "WATCH" one.
            - No markdown formatting, no emoji, no bullet points — plain
              sentences only.
            - Never mention "VERIFIED_FACTS", "BASELINE_RECOMMENDATION", JSON,
              Gemini, Groq, or that you are an AI model following instructions
              — write it as a normal, direct recommendation.
            TEXT;
    }
}

if (!function_exists('hc_ai_reco_user_turn')) {
    function hc_ai_reco_user_turn(string $kind, array $facts, string $fallbackBody): string
    {
        return 'CATEGORY: ' . $kind
            . "\nBASELINE_RECOMMENDATION: " . $fallbackBody
            . "\nVERIFIED_FACTS: " . json_encode($facts, JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('hc_gemini_recommendation_reply')) {
    /** Same call shape/contract as gemini_client.php's own functions (null on ANY failure), but for this module's own prompt. */
    function hc_gemini_recommendation_reply(string $kind, array $facts, string $fallbackBody): ?string
    {
        $GLOBALS['__hc_ai_reco_gemini_last_error'] = null;

        if (GEMINI_API_KEY === '') {
            return null;
        }

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 8]);
            $response = $client->post(
                'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent',
                [
                    'headers' => ['Content-Type' => 'application/json', 'x-goog-api-key' => GEMINI_API_KEY],
                    'json' => [
                        'systemInstruction' => ['parts' => [['text' => hc_ai_reco_system_instruction()]]],
                        'contents' => [['role' => 'user', 'parts' => [['text' => hc_ai_reco_user_turn($kind, $facts, $fallbackBody)]]]],
                        'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 150, 'thinkingConfig' => ['thinkingBudget' => 0]],
                    ],
                ]
            );
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            error_log('[AI Recommendations][Gemini] generateContent failed: ' . $e->getMessage());
            $GLOBALS['__hc_ai_reco_gemini_last_error'] = $e->getMessage();

            return null;
        }

        return hc_gemini_extract_text(json_decode((string) $response->getBody(), true) ?? []);
    }
}

if (!function_exists('hc_groq_recommendation_reply')) {
    /** Fallback for hc_gemini_recommendation_reply() — only tried after a real Gemini failure, same rule the chatbot follows. */
    function hc_groq_recommendation_reply(string $kind, array $facts, string $fallbackBody): ?string
    {
        if (GROQ_API_KEY === '') {
            return null;
        }

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 8]);
            $response = $client->post(
                'https://api.groq.com/openai/v1/chat/completions',
                [
                    'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . GROQ_API_KEY],
                    'json' => [
                        'model' => GROQ_MODEL,
                        'messages' => [
                            ['role' => 'system', 'content' => hc_ai_reco_system_instruction()],
                            ['role' => 'user', 'content' => hc_ai_reco_user_turn($kind, $facts, $fallbackBody)],
                        ],
                        'temperature' => 0.3,
                        'max_tokens' => 150,
                        'reasoning_effort' => 'low',
                    ],
                ]
            );
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            error_log('[AI Recommendations][Groq] chat completion failed: ' . $e->getMessage());

            return null;
        }

        return hc_groq_extract_text(json_decode((string) $response->getBody(), true) ?? []);
    }
}

if (!function_exists('hc_ai_recommendation_text')) {
    /**
     * Rephrases $fallbackBody into real AI-generated recommendation text,
     * grounded only in $facts — or returns $fallbackBody unchanged if
     * both providers are unconfigured or fail. Never throws, never
     * returns an empty string.
     *
     * @param string $kind  short label (e.g. 'disease_cluster', 'stock_shortage') — prompt framing + cache/logging only
     * @param array  $facts real, already-computed values only — no patient names/ids
     */
    function hc_ai_recommendation_text(string $kind, array $facts, string $fallbackBody): string
    {
        if (trim($fallbackBody) === '') {
            return $fallbackBody;
        }

        $cacheKey = md5($kind . '|' . json_encode($facts) . '|' . $fallbackBody);
        $cached = hc_ai_reco_cache_get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $text = hc_gemini_recommendation_reply($kind, $facts, $fallbackBody);
        $geminiFailed = $text === null && $GLOBALS['__hc_ai_reco_gemini_last_error'] !== null;
        if ($text === null && $geminiFailed) {
            $text = hc_groq_recommendation_reply($kind, $facts, $fallbackBody);
        }

        if ($text !== null && trim($text) !== '') {
            hc_ai_reco_cache_set($cacheKey, $text, 3600);

            return $text;
        }

        // Both providers unavailable/unconfigured/failed — cache the
        // fallback too, but briefly, so a real outage doesn't cost a
        // ~16s round-trip (two 8s timeouts) on every single page view
        // until it recovers.
        hc_ai_reco_cache_set($cacheKey, $fallbackBody, 300);

        return $fallbackBody;
    }
}
