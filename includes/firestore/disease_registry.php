<?php
/**
 * =====================================================================
 * Disease Registry — public-visibility classification
 * ---------------------------------------------------------------------
 * Replaces includes/shared/predictive_analytics_core.php's old hardcoded
 * hc_notifiable_disease_categories() array with a real, admin-managed
 * Firestore collection ("disease_registry") — the exact same
 * notifiable/communicable-disease keyword categories that already fed
 * every predictive-analytics/GIS feature in this app, now with a real
 * classification per category deciding whether it's safe to show on
 * the PUBLIC GIS map (patient/gis.php, admin/gis_mapping.php, the
 * homepage preview widget — all three go through api/gis-data.php ->
 * hc_gis_disease_overview()) or stays internal-surveillance-only
 * (admin/facility_analytics.php's Predictive & Prescriptive tab,
 * subadmin/predictive_analytics.php, doctor's/medical-staff's own
 * dashboard preview cards — all of which call
 * hc_disease_demand_forecast() directly, bypassing this file's public
 * filtering entirely, by design — see hc_gis_disease_overview()'s own
 * header comment).
 *
 * Four classifications, same spirit as the allergy clinicalStatus/
 * verificationStatus system built earlier (api/update-allergy-clinical-status.php):
 *   - public_awareness            — shown on the public GIS freely.
 *   - conditional_public_display  — shown on the public GIS, but under
 *                                    a stricter small-cell-suppression
 *                                    floor (see hc_gis_disease_overview()).
 *   - internal_surveillance_only  — never shown on the public GIS, only
 *                                    on the internal staff pages above.
 *   - pending_classification      — same as internal_surveillance_only
 *                                    for display purposes, but means
 *                                    "nobody has reviewed this yet" —
 *                                    every category api/add-disease-registry-entry.php
 *                                    creates starts here, unconditionally,
 *                                    regardless of what the client asked for.
 *
 * admin/disease_registry.php is the one real management UI — classifying
 * a disease is a documented, audited decision (required reason, the
 * admin's own identity from $_SESSION, an append-only history[] array),
 * same shape the allergy status-change endpoint already established.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../shared/inventory_match.php'; // hc_normalize_text()

if (!function_exists('hc_seed_disease_registry')) {
    /**
     * One-time seed, only when the collection is genuinely empty (first
     * real read ever) — the exact same self-seed-on-first-read pattern
     * includes/firestore/lab_results.php's hc_seed_lab_test_catalog()
     * already uses. 17 entries: the app's original 14 notifiable-disease
     * keyword categories (unchanged keyword sets) plus 3 genuinely new
     * ones the user asked for (Animal Bite Exposures/Intestinal
     * Parasitic Infections/Heat-Related Illness, which had no keyword
     * definition anywhere before this).
     *
     * Classification at seed time is a real, documented decision too
     * (not a silent default) — see this function's own $seed entries
     * below for the reasoning behind each tier, recorded verbatim in
     * each doc's own classificationReason/history[0].
     */
    function hc_seed_disease_registry(): void
    {
        global $firestore;

        $now = date('c');
        $today = date('Y-m-d');

        $seed = [
            // --- Public Awareness — low inherent stigma, real practical
            // preventive actions exist (hygiene/mosquito control/safe
            // food+water/animal-bite care/heat safety), matches the
            // user's own 10 named examples (the 10th, "Diarrheal
            // Diseases", merges into the one existing "Acute
            // Gastroenteritis / Diarrhea" category below rather than
            // becoming a separate entry). ---
            ['name' => 'Dengue', 'keywords' => ['dengue'], 'classification' => 'public_awareness', 'isPreventable' => true,
                'reason' => 'User-approved initial example — standard mosquito-borne notifiable disease, low stigma, clear preventable actions (eliminate stagnant water, use repellent).'],
            ['name' => 'Influenza-like Illness', 'keywords' => ['influenza', 'flu-like', ' flu ', 'flu,'], 'classification' => 'public_awareness', 'isPreventable' => true,
                'reason' => 'User-approved initial example — common seasonal illness, low stigma, respiratory-hygiene/vaccination prevention applies.'],
            ['name' => 'Acute Gastroenteritis / Diarrhea', 'keywords' => ['gastroenteritis', 'diarrhea', 'diarrhoea'], 'classification' => 'public_awareness', 'isPreventable' => true,
                'reason' => 'User-approved initial example (covers both "Acute Gastroenteritis" and "Diarrheal Diseases" from the request) — low stigma, safe food/water practices are the real prevention message.'],
            ['name' => 'Leptospirosis', 'keywords' => ['leptospirosis'], 'classification' => 'public_awareness', 'isPreventable' => true,
                'reason' => 'User-approved initial example — flood/water-exposure notifiable disease, low stigma, clear prevention message (avoid floodwater, protective footwear).'],
            ['name' => 'Hand-Foot-Mouth Disease', 'keywords' => ['hand-foot-mouth', 'hand, foot, and mouth', 'hfmd'], 'classification' => 'public_awareness', 'isPreventable' => true,
                'reason' => 'User-approved initial example — common childhood illness, low stigma, hygiene-based prevention.'],
            ['name' => 'Conjunctivitis (Sore Eyes)', 'keywords' => ['conjunctivitis', 'sore eyes'], 'classification' => 'public_awareness', 'isPreventable' => true,
                'reason' => 'User-approved initial example — low stigma, hygiene-based prevention.'],
            ['name' => 'Animal Bite Exposures', 'keywords' => ['animal bite', 'dog bite', 'cat bite', 'rabies exposure', 'rabies'], 'classification' => 'public_awareness', 'isPreventable' => true,
                'reason' => 'User-approved initial example — new category, no keyword definition existed before. Low stigma, real prevention message (avoid stray animals, prompt post-exposure care).'],
            ['name' => 'Intestinal Parasitic Infections', 'keywords' => ['parasit', 'ascariasis', 'helminth', 'worm infestation', 'intestinal worm'], 'classification' => 'public_awareness', 'isPreventable' => true,
                'reason' => 'User-approved initial example — new category, no keyword definition existed before. Low stigma, hygiene/safe-water prevention.'],
            ['name' => 'Heat-Related Illness', 'keywords' => ['heat stroke', 'heatstroke', 'heat exhaustion', 'hyperthermia'], 'classification' => 'public_awareness', 'isPreventable' => true,
                'reason' => 'User-approved initial example — new category, no keyword definition existed before. Not communicable, no stigma risk, clear prevention message (hydration, shade, avoid peak heat).'],

            // --- Conditional Public Display — standard notifiable
            // diseases already in this app's existing keyword list, not
            // explicitly named by the user, lower inherent stigma than
            // COVID/TB but not pre-approved either — shown on the public
            // GIS under a stricter small-cell-suppression floor rather
            // than freely. ---
            ['name' => 'Measles', 'keywords' => ['measles', 'rubeola'], 'classification' => 'conditional_public_display', 'isPreventable' => true,
                'reason' => 'Pre-existing notifiable-disease category, not in the user\'s named list — vaccine-preventable, standard public-health reporting, but not pre-approved for free public display; seeded Conditional pending admin review.'],
            ['name' => 'Chickenpox', 'keywords' => ['chickenpox', 'varicella'], 'classification' => 'conditional_public_display', 'isPreventable' => true,
                'reason' => 'Pre-existing category, not in the user\'s named list — low-stigma childhood illness, seeded Conditional pending admin review.'],
            ['name' => 'Typhoid Fever', 'keywords' => ['typhoid'], 'classification' => 'conditional_public_display', 'isPreventable' => true,
                'reason' => 'Pre-existing category, not in the user\'s named list — food/water-safety relevant, seeded Conditional pending admin review.'],
            ['name' => 'Pneumonia', 'keywords' => ['pneumonia'], 'classification' => 'conditional_public_display', 'isPreventable' => true,
                'reason' => 'Pre-existing category, not in the user\'s named list — seeded Conditional pending admin review.'],
            ['name' => 'Mumps', 'keywords' => ['mumps'], 'classification' => 'conditional_public_display', 'isPreventable' => true,
                'reason' => 'Pre-existing category, not in the user\'s named list — vaccine-preventable, seeded Conditional pending admin review.'],
            ['name' => 'Pertussis (Whooping Cough)', 'keywords' => ['pertussis', 'whooping cough'], 'classification' => 'conditional_public_display', 'isPreventable' => true,
                'reason' => 'Pre-existing category, not in the user\'s named list — vaccine-preventable, seeded Conditional pending admin review.'],

            // --- Internal Surveillance Only — established high-stigma/
            // high-sensitivity conditions. Still fully tracked on the
            // internal Predictive & Prescriptive pages (unfiltered), but
            // never named on the public map. ---
            ['name' => 'COVID-19', 'keywords' => ['covid'], 'classification' => 'internal_surveillance_only', 'isPreventable' => true,
                'reason' => 'Historically significant community stigma risk around individual/household-level geographic case disclosure — internal surveillance only until explicitly reviewed otherwise.'],
            ['name' => 'Tuberculosis', 'keywords' => ['tuberculosis', 'pulmonary tb'], 'classification' => 'internal_surveillance_only', 'isPreventable' => true,
                'reason' => 'Established high-stigma condition — internal surveillance only until explicitly reviewed otherwise.'],
        ];

        foreach ($seed as $s) {
            $openingHistory = [
                'date' => $today,
                'event' => 'Created — seeded as ' . $s['classification'],
                'previousClassification' => null,
                'newClassification' => $s['classification'],
                'reason' => $s['reason'],
                'previousPreventiveMeasures' => null,
                'newPreventiveMeasures' => '',
                'classifiedByName' => 'System',
                'classifiedByUid' => '',
                'classifiedByRole' => 'system',
                'recordedAt' => $now,
            ];
            $firestore->addDocument('disease_registry', [
                'name' => $s['name'],
                'keywords' => $s['keywords'],
                'classification' => $s['classification'],
                'isPreventable' => $s['isPreventable'],
                'preventiveMeasures' => '',
                'isAutoDetected' => false,
                'createdAt' => $now,
                'classifiedByName' => 'System',
                'classifiedByUid' => '',
                'classifiedByRole' => 'system',
                'classifiedAt' => $now,
                'classificationReason' => $s['reason'],
                'history' => [$openingHistory],
            ]);
        }
    }
}

if (!function_exists('hc_get_disease_registry_docs')) {
    /**
     * The real read, self-seeding on first call. Cached per-request
     * (function-local static — a fresh PHP interpreter per request under
     * this app's mod_php/XAMPP setup, so no cross-request staleness risk)
     * since hc_notifiable_disease_categories() below is already called
     * twice in a single request on at least two real pages
     * (admin/facility_analytics.php, api/get-disease-trends.php) —
     * without this cache, converting that function from a static array
     * to a live Firestore read would double real reads AND risk the two
     * calls within one request seeing different data if a classification
     * changed mid-request.
     *
     * @return array<int, array{id: string, fields: array}>
     */
    function hc_get_disease_registry_docs(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        global $firestore;
        $docs = $firestore->listDocuments('disease_registry');
        if (empty($docs)) {
            hc_seed_disease_registry();
            $docs = $firestore->listDocuments('disease_registry');
        }

        return $cache = $docs;
    }
}

if (!function_exists('hc_disease_registry_classification_by_category')) {
    /** @return array<string, string> category name -> classification. */
    function hc_disease_registry_classification_by_category(): array
    {
        $out = [];
        foreach (hc_get_disease_registry_docs() as $doc) {
            $f = $doc['fields'];
            $out[$f['name'] ?? ''] = $f['classification'] ?? 'pending_classification';
        }

        return $out;
    }
}

if (!function_exists('hc_disease_registry_entry_by_category')) {
    /** @return array<string, array> category name -> full registry doc row (id + fields merged). */
    function hc_disease_registry_entry_by_category(): array
    {
        $out = [];
        foreach (hc_get_disease_registry_docs() as $doc) {
            $f = $doc['fields'];
            $out[$f['name'] ?? ''] = ['id' => $doc['id']] + $f;
        }

        return $out;
    }
}

if (!function_exists('hc_disease_registry_candidates')) {
    /**
     * "New disease recognition" — real recurring diagnosis text that
     * doesn't match ANY registry keyword yet, surfaced for an admin to
     * review on admin/disease_registry.php and (optionally) promote into
     * a real registry entry. Deliberately reads ONLY the 'diagnosis'
     * field (the doctor's own documented conclusion — see
     * includes/shared/predictive_analytics_core.php's
     * hc_notifiable_cases_from_records() header comment for why
     * diagnosis and chiefComplaint aren't treated the same), never
     * chiefComplaint, so this doesn't flood the review list with raw
     * presenting-symptom phrasing. Exact-string clustering (no NLP) —
     * same simple-matching convention every other text feature in this
     * app already uses — so two real differently-worded mentions of the
     * same condition won't cluster together; that's a real limitation,
     * not a bug, left for the reviewing admin's own judgment.
     *
     * @param array $medicalRecordDocs Raw medical_records docs (type=consultation), same shape hc_notifiable_cases_from_records() takes.
     * @return array<int, array{normalizedText: string, sampleOriginalText: string, count: int, firstDate: string, lastDate: string}>
     */
    function hc_disease_registry_candidates(array $medicalRecordDocs, int $minOccurrences = 3, int $limit = 20): array
    {
        // Built directly from the registry docs already available in
        // this same file (not via hc_notifiable_disease_categories() in
        // includes/shared/predictive_analytics_core.php) — that file
        // requires THIS one for hc_get_disease_registry_docs(), so
        // calling back into it here would be a needless circular
        // dependency for data already on hand.
        $allKeywords = [];
        foreach (hc_get_disease_registry_docs() as $doc) {
            foreach (($doc['fields']['keywords'] ?? []) as $kw) {
                $allKeywords[] = $kw;
            }
        }

        $groups = []; // normalizedText => {sample, count, firstDate, lastDate}
        foreach ($medicalRecordDocs as $doc) {
            $f = $doc['fields'];
            $raw = trim((string) ($f['diagnosis'] ?? ''));
            if ($raw === '') {
                continue;
            }
            $normalized = hc_normalize_text($raw);
            if ($normalized === '') {
                continue;
            }
            $alreadyRegistered = false;
            foreach ($allKeywords as $kw) {
                if (str_contains($normalized, $kw)) {
                    $alreadyRegistered = true;
                    break;
                }
            }
            if ($alreadyRegistered) {
                continue;
            }

            $date = substr((string) ($f['date'] ?? ''), 0, 10);
            $groups[$normalized] ??= ['sample' => $raw, 'count' => 0, 'firstDate' => $date, 'lastDate' => $date];
            $groups[$normalized]['count']++;
            if ($date !== '' && ($groups[$normalized]['firstDate'] === '' || $date < $groups[$normalized]['firstDate'])) {
                $groups[$normalized]['firstDate'] = $date;
            }
            if ($date !== '' && $date > $groups[$normalized]['lastDate']) {
                $groups[$normalized]['lastDate'] = $date;
            }
        }

        $candidates = [];
        foreach ($groups as $normalized => $g) {
            if ($g['count'] < $minOccurrences) {
                continue;
            }
            $candidates[] = [
                'normalizedText' => $normalized,
                'sampleOriginalText' => $g['sample'],
                'count' => $g['count'],
                'firstDate' => $g['firstDate'],
                'lastDate' => $g['lastDate'],
            ];
        }
        usort($candidates, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_slice($candidates, 0, $limit);
    }
}
