<?php
/**
 * =====================================================================
 * Groq API Configuration (server-side) — EXAMPLE TEMPLATE
 * ---------------------------------------------------------------------
 * Copy this file to config/groq.php (gitignored — never committed) and
 * fill in a real key. Used ONLY as the chatbot's fallback AI provider
 * (see includes/public/groq_client.php) when Gemini itself fails (rate
 * limit, quota exceeded, outage) — never tried first, only after a
 * real Gemini failure. Same rules as Gemini: never given permission to
 * invent clinic facts or make decisions, only to phrase/answer within
 * the same constraints.
 *
 * GROQ_API_KEY is intentionally blank until a real key is added —
 * every caller must treat an empty key as "Groq unavailable" and fall
 * back further down the chain, never error. Get a free key (no
 * billing info required) at https://console.groq.com/keys.
 * =====================================================================
 */

define('GROQ_API_KEY', '');
define('GROQ_MODEL', 'openai/gpt-oss-120b');
