<?php
/**
 * =====================================================================
 * Search Log — real "Popular Searches" (patient/search.php)
 * ---------------------------------------------------------------------
 * Every real, non-empty search term typed into the public search (the
 * homepage hero bar submits here too — both land on patient/search.php
 * with ?q=) increments a real per-term counter in Firestore's
 * "search_logs" collection, one doc per distinct term (keyed by a
 * normalized slug so "Paracetamol"/"paracetamol"/"  Paracetamol  " all
 * count toward the same doc). Popular Searches then shows the real top
 * 5 by count — no hardcoded list anywhere in this path anymore.
 *
 * Read-modify-write (get the current count, write count+1), not an
 * atomic increment — this client has no FieldValue.increment()
 * equivalent. Two near-simultaneous searches for the same term could
 * theoretically lose one increment; fine for a "roughly how popular is
 * this" ranking, same tolerance this app's other approximate counters
 * already accept.
 * =====================================================================
 */

if (!function_exists('hc_search_log_term_id')) {
    /** A stable Firestore doc id for a search term — same id for any casing/spacing variant of the same real word(s). */
    function hc_search_log_term_id(string $term): string
    {
        $slug = strtolower(trim($term));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return substr($slug !== '' ? $slug : 'blank', 0, 120);
    }
}

if (!function_exists('hc_log_search_term')) {
    function hc_log_search_term(string $term): void
    {
        global $firestore;

        $term = trim($term);
        if ($term === '') {
            return;
        }

        $docId = hc_search_log_term_id($term);
        $existing = $firestore->getDocument('search_logs', $docId);

        $firestore->setDocument('search_logs', $docId, [
            // Keeps the FIRST-seen real casing/spacing as the display
            // text (e.g. "Paracetamol"), rather than overwriting it with
            // whatever casing happened to be typed on a later search.
            'term' => $existing['term'] ?? $term,
            'count' => ($existing['count'] ?? 0) + 1,
            'lastSearchedAt' => date('c'),
        ]);
    }
}

if (!function_exists('hc_get_popular_searches')) {
    /**
     * Real top-N most-searched terms, by count — [] (not a hardcoded
     * fallback list) when nobody's searched anything yet, same honest-
     * empty-state principle as every other real, currently-empty list
     * in this app (e.g. "No facilities yet."). The caller hides the
     * whole "Popular Searches" row in that case rather than show
     * something fake.
     *
     * @return array<int, string>
     */
    function hc_get_popular_searches(int $limit = 5): array
    {
        global $firestore;

        $logs = $firestore->listDocuments('search_logs');
        usort($logs, fn ($a, $b) => ($b['fields']['count'] ?? 0) <=> ($a['fields']['count'] ?? 0));

        return array_values(array_map(
            fn ($doc) => $doc['fields']['term'] ?? $doc['id'],
            array_slice($logs, 0, $limit)
        ));
    }
}
