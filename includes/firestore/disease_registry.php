<?php
/**
 * =====================================================================
 * Disease Registry — automatic category-based public visibility
 * ---------------------------------------------------------------------
 * Replaces includes/shared/predictive_analytics_core.php's old hardcoded
 * hc_notifiable_disease_categories() array with a real, admin-managed
 * Firestore collection ("disease_registry") — the exact same
 * notifiable/communicable-disease keyword categories that already fed
 * every predictive-analytics/GIS feature in this app, now with a real
 * CLINICAL CATEGORY per condition deciding whether it's safe to show on
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
 * NO manual per-disease approval step exists anywhere in this design —
 * that was this feature's FIRST iteration (a 4-tier 'classification'
 * field an admin had to explicitly set via a reason-required decision
 * endpoint) and has been deliberately removed. Public visibility is now
 * fully automatic and rule-based: a condition is one of 5 fixed clinical
 * categories (the "maintained clinical reference" taxonomy itself —
 * assigning one is ordinary registry upkeep, not a public-health policy
 * decision) or it has NO category at all, which simply means "not yet
 * classified" — functionally identical to "unrecognized." The moment a
 * real category is assigned, the condition is public on the very next
 * page load; nobody has to click "approve."
 *
 * The 5 categories: communicable_preventable, respiratory,
 * environmental_sanitation, animal_bite, other_approved_prevention —
 * see hc_disease_registry_category_meta() below for display labels.
 *
 * admin/disease_registry.php is the real management UI — editing a
 * condition's name/keywords/category/preventable flag is routine
 * catalog maintenance (api/update-disease-registry-entry.php), no
 * reason/justification required, same as fixing a typo. The ONLY thing
 * that's still deliberately gated is where a BRAND NEW condition starts:
 * api/add-disease-registry-entry.php always creates it with NO category
 * (never the client-requested one), so a newly-recognized condition is
 * never accidentally public before someone's actually looked at it.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../shared/inventory_match.php'; // hc_normalize_text()

if (!function_exists('hc_disease_registry_category_meta')) {
    /**
     * The 5 fixed, public-eligible clinical categories — this IS the
     * "maintained clinical reference process" the public-visibility
     * rule is built on. A condition with any one of these 5 assigned is
     * automatically public; a condition with none assigned (key absent
     * from a doc, or an unrecognized value) is automatically excluded.
     * There is no 6th "rejected"/"internal only" category — a condition
     * HEALTrack never wants public (e.g. COVID-19, Tuberculosis) simply
     * never gets one of these 5 assigned, the same as a condition nobody
     * has reviewed yet. Both stay internal-surveillance-visible only
     * (hc_disease_demand_forecast()'s direct callers never filter by
     * category at all).
     *
     * @return array<string, string> category key -> display label.
     */
    function hc_disease_registry_category_meta(): array
    {
        return [
            'communicable_preventable' => 'Communicable and Preventable Diseases',
            'respiratory' => 'Respiratory Health Concerns',
            'environmental_sanitation' => 'Environmental and Sanitation-Related Conditions',
            'animal_bite' => 'Animal Bite Exposures',
            'other_approved_prevention' => 'Other Approved Prevention-Focused Health Concerns',
        ];
    }
}

if (!function_exists('hc_seed_disease_registry')) {
    /**
     * One-time seed, only when the collection is genuinely empty (first
     * real read ever on a fresh install) — the exact same self-seed-on-
     * first-read pattern includes/firestore/lab_results.php's
     * hc_seed_lab_test_catalog() already uses. 17 entries: the app's
     * original 14 notifiable-disease keyword categories (unchanged
     * keyword sets) plus 3 genuinely new ones (Animal Bite Exposures/
     * Intestinal Parasitic Infections/Heat-Related Illness, which had no
     * keyword definition anywhere before this feature). 15 start with a
     * real category already assigned (automatically public from the
     * first page load); COVID-19 and Tuberculosis start with none
     * (excluded, established high-stigma conditions).
     *
     * On THIS environment (where the collection was already seeded by
     * this feature's first iteration, with the old 'classification'
     * field), hc_get_disease_registry_docs() runs
     * hc_migrate_disease_registry_classification_to_category() instead —
     * see that function below for the real one-time migration of the
     * live data already in this collection.
     */
    function hc_seed_disease_registry(): void
    {
        global $firestore;

        $now = date('c');
        $today = date('Y-m-d');

        $seed = [
            // --- category assigned at seed time — automatically public,
            // no approval step. ---
            ['name' => 'Dengue', 'keywords' => ['dengue'], 'category' => 'communicable_preventable', 'isPreventable' => true],
            ['name' => 'Influenza-like Illness', 'keywords' => ['influenza', 'flu-like', ' flu ', 'flu,'], 'category' => 'respiratory', 'isPreventable' => true],
            ['name' => 'Acute Gastroenteritis / Diarrhea', 'keywords' => ['gastroenteritis', 'diarrhea', 'diarrhoea'], 'category' => 'environmental_sanitation', 'isPreventable' => true],
            ['name' => 'Leptospirosis', 'keywords' => ['leptospirosis'], 'category' => 'environmental_sanitation', 'isPreventable' => true],
            ['name' => 'Hand-Foot-Mouth Disease', 'keywords' => ['hand-foot-mouth', 'hand, foot, and mouth', 'hfmd'], 'category' => 'communicable_preventable', 'isPreventable' => true],
            ['name' => 'Conjunctivitis (Sore Eyes)', 'keywords' => ['conjunctivitis', 'sore eyes'], 'category' => 'communicable_preventable', 'isPreventable' => true],
            ['name' => 'Animal Bite Exposures', 'keywords' => ['animal bite', 'dog bite', 'cat bite', 'rabies exposure', 'rabies'], 'category' => 'animal_bite', 'isPreventable' => true],
            ['name' => 'Intestinal Parasitic Infections', 'keywords' => ['parasit', 'ascariasis', 'helminth', 'worm infestation', 'intestinal worm'], 'category' => 'environmental_sanitation', 'isPreventable' => true],
            ['name' => 'Heat-Related Illness', 'keywords' => ['heat stroke', 'heatstroke', 'heat exhaustion', 'hyperthermia'], 'category' => 'other_approved_prevention', 'isPreventable' => true],
            ['name' => 'Measles', 'keywords' => ['measles', 'rubeola'], 'category' => 'communicable_preventable', 'isPreventable' => true],
            ['name' => 'Chickenpox', 'keywords' => ['chickenpox', 'varicella'], 'category' => 'communicable_preventable', 'isPreventable' => true],
            ['name' => 'Typhoid Fever', 'keywords' => ['typhoid'], 'category' => 'environmental_sanitation', 'isPreventable' => true],
            ['name' => 'Pneumonia', 'keywords' => ['pneumonia'], 'category' => 'respiratory', 'isPreventable' => true],
            ['name' => 'Mumps', 'keywords' => ['mumps'], 'category' => 'communicable_preventable', 'isPreventable' => true],
            ['name' => 'Pertussis (Whooping Cough)', 'keywords' => ['pertussis', 'whooping cough'], 'category' => 'respiratory', 'isPreventable' => true],

            // --- no category — stays excluded from the public map;
            // still fully visible on every internal surveillance page. ---
            ['name' => 'COVID-19', 'keywords' => ['covid'], 'category' => null, 'isPreventable' => true],
            ['name' => 'Tuberculosis', 'keywords' => ['tuberculosis', 'pulmonary tb'], 'category' => null, 'isPreventable' => true],
        ];

        foreach ($seed as $s) {
            $openingHistory = [
                'date' => $today,
                'event' => $s['category'] !== null ? ('Created — seeded with category ' . $s['category']) : 'Created — no category yet (excluded from public GIS)',
                'previousCategory' => null,
                'newCategory' => $s['category'],
                'changedByName' => 'System',
                'changedByUid' => '',
                'changedByRole' => 'system',
                'recordedAt' => $now,
            ];
            $firestore->addDocument('disease_registry', [
                'name' => $s['name'],
                'keywords' => $s['keywords'],
                'category' => $s['category'],
                'isPreventable' => $s['isPreventable'],
                'preventiveMeasures' => '',
                'isAutoDetected' => false,
                'createdAt' => $now,
                'updatedByName' => 'System',
                'updatedByUid' => '',
                'updatedByRole' => 'system',
                'updatedAt' => $now,
                'history' => [$openingHistory],
            ]);
        }
    }
}

if (!function_exists('hc_migrate_disease_registry_classification_to_category')) {
    /**
     * One-time, real data migration — NOT a reseed. This feature's first
     * iteration already seeded this exact collection with an old 4-tier
     * 'classification' field (public_awareness/conditional_public_display/
     * internal_surveillance_only/pending_classification) and real
     * classification-history entries from live admin use. Reseeding from
     * scratch would discard that real history. Instead: read every
     * existing doc, map its old classification to the new 'category'
     * scheme below, drop the old field, append one real history entry
     * documenting the migration, and leave every other field (keywords,
     * isPreventable, preventiveMeasures, existing history[]) untouched.
     *
     * Mapping (user-confirmed when this migration was authorized):
     *   public_awareness / conditional_public_display -> a real category
     *     (every one of those 15 conditions becomes automatically public)
     *   internal_surveillance_only / pending_classification -> no category
     *     (COVID-19, Tuberculosis — stays excluded, exactly as before)
     *
     * Safe to call more than once — a doc that no longer has a
     * 'classification' field (already migrated) is skipped.
     */
    function hc_migrate_disease_registry_classification_to_category(): void
    {
        global $firestore;

        $categoryByName = [
            'Dengue' => 'communicable_preventable',
            'Hand-Foot-Mouth Disease' => 'communicable_preventable',
            'Conjunctivitis (Sore Eyes)' => 'communicable_preventable',
            'Measles' => 'communicable_preventable',
            'Chickenpox' => 'communicable_preventable',
            'Mumps' => 'communicable_preventable',
            'Influenza-like Illness' => 'respiratory',
            'Pneumonia' => 'respiratory',
            'Pertussis (Whooping Cough)' => 'respiratory',
            'Leptospirosis' => 'environmental_sanitation',
            'Acute Gastroenteritis / Diarrhea' => 'environmental_sanitation',
            'Intestinal Parasitic Infections' => 'environmental_sanitation',
            'Typhoid Fever' => 'environmental_sanitation',
            'Animal Bite Exposures' => 'animal_bite',
            'Heat-Related Illness' => 'other_approved_prevention',
            'COVID-19' => null,
            'Tuberculosis' => null,
        ];

        $now = date('c');
        $today = date('Y-m-d');

        foreach ($firestore->listDocuments('disease_registry') as $doc) {
            $f = $doc['fields'];
            if (!array_key_exists('classification', $f)) {
                continue; // already migrated
            }

            $name = $f['name'] ?? '';
            $newCategory = $categoryByName[$name] ?? null;
            $oldClassification = $f['classification'] ?? null;

            $updated = $f;
            unset($updated['classification'], $updated['classificationReason'], $updated['classifiedByName'], $updated['classifiedByUid'], $updated['classifiedByRole'], $updated['classifiedAt']);
            $updated['category'] = $newCategory;
            $updated['updatedByName'] = 'System';
            $updated['updatedByUid'] = '';
            $updated['updatedByRole'] = 'system';
            $updated['updatedAt'] = $now;

            $updated['history'] = array_values(is_array($f['history'] ?? null) ? $f['history'] : []);
            $updated['history'][] = [
                'date' => $today,
                'event' => 'Migrated from manual-approval classification (' . ($oldClassification ?? 'none') . ') to automatic category (' . ($newCategory ?? 'none') . ')',
                'previousCategory' => null,
                'newCategory' => $newCategory,
                'changedByName' => 'System',
                'changedByUid' => '',
                'changedByRole' => 'system',
                'recordedAt' => $now,
            ];

            $firestore->setDocument('disease_registry', $doc['id'], $updated);
        }
    }
}

if (!function_exists('hc_get_disease_registry_docs')) {
    /**
     * The real read. Self-seeds a genuinely empty collection (fresh
     * install); runs the one-time classification->category migration
     * when it finds the OLD field shape still present (this
     * environment). Cached per-request (function-local static — a fresh
     * PHP interpreter per request under this app's mod_php/XAMPP setup,
     * so no cross-request staleness risk) since
     * hc_notifiable_disease_categories() below is already called twice
     * in a single request on at least two real pages
     * (admin/facility_analytics.php, api/get-disease-trends.php).
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
        } elseif (array_key_exists('classification', $docs[0]['fields'] ?? [])) {
            hc_migrate_disease_registry_classification_to_category();
            $docs = $firestore->listDocuments('disease_registry');
        }

        return $cache = $docs;
    }
}

if (!function_exists('hc_disease_registry_category_by_name')) {
    /** @return array<string, ?string> condition name -> category key, or null when uncategorized (excluded from the public GIS). */
    function hc_disease_registry_category_by_name(): array
    {
        $out = [];
        foreach (hc_get_disease_registry_docs() as $doc) {
            $f = $doc['fields'];
            $out[$f['name'] ?? ''] = $f['category'] ?? null;
        }

        return $out;
    }
}

if (!function_exists('hc_disease_registry_entry_by_category')) {
    /** @return array<string, array> condition name -> full registry doc row (id + fields merged). */
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
     * doesn't match ANY registry keyword yet, surfaced for review on
     * admin/disease_registry.php and (optionally) promoted into a real
     * registry entry. Deliberately reads ONLY the 'diagnosis' field (the
     * doctor's own documented conclusion — see
     * includes/shared/predictive_analytics_core.php's
     * hc_notifiable_cases_from_records() header comment for why
     * diagnosis and chiefComplaint aren't treated the same), never
     * chiefComplaint, so this doesn't flood the review list with raw
     * presenting-symptom phrasing. Exact-string clustering (no NLP) —
     * same simple-matching convention every other text feature in this
     * app already uses — so two real differently-worded mentions of the
     * same condition won't cluster together; that's a real limitation,
     * not a bug, left for whoever reviews this list.
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
