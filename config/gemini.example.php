<?php
/**
 * =====================================================================
 * Gemini API Configuration (server-side) — EXAMPLE TEMPLATE
 * ---------------------------------------------------------------------
 * Copy this file to config/gemini.php (gitignored — never committed)
 * and fill in a real key. Used ONLY for the chatbot's final
 * reply-phrasing step (see includes/public/gemini_client.php) — it
 * turns the decision tree's already-verified structured facts into a
 * natural sentence. It is never given permission to invent facts or
 * make decisions.
 *
 * GEMINI_API_KEY is intentionally blank until a real key is added —
 * every caller must treat an empty key as "Gemini unavailable" and
 * fall back to a plain template/the legacy keyword engine, never
 * error. Get a free key at https://aistudio.google.com/apikey.
 * =====================================================================
 */

define('GEMINI_API_KEY', '');
define('GEMINI_MODEL', 'gemini-3.8-flash');
