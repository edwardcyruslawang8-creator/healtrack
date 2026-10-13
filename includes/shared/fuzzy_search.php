<?php
/**
 * =====================================================================
 * Shared Fuzzy Search Matching
 * ---------------------------------------------------------------------
 * hc_fuzzy_match() replaces the plain stripos($haystack, $needle) !==
 * false checks every real search box in this app used to do (subadmin/,
 * medical-staff/, pharmacist/, doctor/'s User Management, Patient
 * Management, Consultations, Medicine & Vaccine Inventory, Consent
 * Management, and Stock Requests search boxes) — tolerating typos
 * ("Jaun" still finds "Juan", "Maraia" still finds "Maria") instead of
 * requiring an exact substring.
 *
 * Strategy, cheapest check first:
 *   1. Empty query matches everything (same as before).
 *   2. Case-insensitive substring match — the common case (correctly
 *      spelled searches, ids, emails) stays exactly as fast/precise as
 *      the old stripos() check.
 *   3. Otherwise, split both the query and the haystack into words and
 *      require EVERY query word to fuzzy-match at least one haystack
 *      word (Levenshtein distance within a small tolerance that scales
 *      with word length) — so a multi-word query like "jaun dela"
 *      (typo of "juan dela") only matches records where both words are
 *      close, not just one coincidentally-similar word.
 *
 * No new dependency — built entirely on PHP's built-in levenshtein().
 * =====================================================================
 */

if (!function_exists('hc_fuzzy_match')) {
    /** True if every word of $query fuzzy-matches somewhere in $haystack. */
    function hc_fuzzy_match(string $haystack, string $query): bool
    {
        $query = trim($query);
        if ($query === '') {
            return true;
        }

        $haystackLower = mb_strtolower($haystack);
        $queryLower = mb_strtolower($query);

        if (str_contains($haystackLower, $queryLower)) {
            return true;
        }

        $haystackWords = preg_split('/[^\p{L}\p{N}]+/u', $haystackLower, -1, PREG_SPLIT_NO_EMPTY);
        $queryWords = preg_split('/[^\p{L}\p{N}]+/u', $queryLower, -1, PREG_SPLIT_NO_EMPTY);
        if ($queryWords === [] || $haystackWords === []) {
            return false;
        }

        foreach ($queryWords as $queryWord) {
            if (!hc_fuzzy_word_in_list($queryWord, $haystackWords)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('hc_fuzzy_word_in_list')) {
    /** @internal True if $queryWord fuzzy-matches any single word in $words. */
    function hc_fuzzy_word_in_list(string $queryWord, array $words): bool
    {
        $queryLen = strlen($queryWord);
        // Typo tolerance scales with word length: most real names/terms
        // are <=7 characters, where 2 edits is loose enough to collide
        // two genuinely different short words (e.g. "maraia" landing on
        // both "Maria" and "Garcia") — 1 edit is enough to catch a
        // single typo there. Only longer words (8+) get a 2-edit budget.
        $maxDistance = $queryLen <= 7 ? 1 : 2;

        foreach ($words as $word) {
            if (str_contains($word, $queryWord)) {
                return true; // prefix/substring within a single word
            }
            if (abs(strlen($word) - $queryLen) > $maxDistance) {
                continue; // too different in length to plausibly be a typo
            }
            if (hc_edit_distance($queryWord, $word) <= $maxDistance) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('hc_edit_distance')) {
    /**
     * @internal Optimal-string-alignment distance — like PHP's built-in
     * levenshtein(), but also counts a transposed adjacent pair
     * ("jaun"/"juan") as a single edit instead of two substitutions.
     * That's the single most common typo shape (two keys hit in the
     * wrong order), so plain levenshtein() alone under-tolerates it —
     * e.g. it puts "jaun" 2 edits from "juan", outside a 1-edit budget.
     * Words here are short (names, test/medicine names), so the O(n·m)
     * table is negligible.
     */
    function hc_edit_distance(string $a, string $b): int
    {
        $lenA = strlen($a);
        $lenB = strlen($b);
        $d = [];
        for ($i = 0; $i <= $lenA; $i++) {
            $d[$i][0] = $i;
        }
        for ($j = 0; $j <= $lenB; $j++) {
            $d[0][$j] = $j;
        }

        for ($i = 1; $i <= $lenA; $i++) {
            for ($j = 1; $j <= $lenB; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $d[$i][$j] = min(
                    $d[$i - 1][$j] + 1,     // deletion
                    $d[$i][$j - 1] + 1,     // insertion
                    $d[$i - 1][$j - 1] + $cost, // substitution
                );
                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1); // transposition
                }
            }
        }

        return $d[$lenA][$lenB];
    }
}
