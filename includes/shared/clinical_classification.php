<?php
/**
 * =====================================================================
 * Dynamic Disease Registry — Firebase-backed, admin-configurable
 * disease METADATA (never per-case approval)
 * ---------------------------------------------------------------------
 * The real "disease_registry" Firestore collection — one doc per
 * recognized condition, each carrying a name, real synonyms used for
 * case-insensitive diagnosis matching, a clinical classification (one
 * of 5 fixed values, or none while unclassified), and two SEPARATE
 * visibility flags:
 *   - gisEligible   — does this condition participate in geographic
 *                     aggregation AT ALL (internal surveillance AND
 *                     public)?
 *   - publicVisibility — given it's gisEligible, is it ALSO safe to
 *                     show on the PUBLIC map? (Forced false whenever
 *                     gisEligible is false or registryStatus is
 *                     'unclassified', regardless of the stored value —
 *                     see hc_is_publicly_visible() below.)
 * This is deliberately NOT a per-case approval system — every valid
 * consultation/diagnosis is always preserved exactly as recorded (see
 * api/complete-consultation.php, untouched by this file). What admin
 * configuration governs here is disease-level metadata only: whether
 * THIS CONDITION, as a category, is classified/eligible/public — never
 * whether one particular patient's case "counts." See
 * admin/disease_registry.php (the one real management UI for this
 * collection) and its own header comment.
 *
 * Disease RECOGNITION (does this diagnosis text refer to a known
 * condition?) and public PUBLICATION ELIGIBILITY (is it safe to show
 * publicly?) are two separate decisions, matching the real synonym
 * list and the two visibility flags independently of each other.
 *
 * A diagnosis matching no existing synonym is never silently dropped
 * nor silently invented a classification for — hc_ensure_disease_registry_entries()
 * auto-creates a real 'unclassified' doc for it (gisEligible=false,
 * publicVisibility=false by construction), which an admin can later
 * classify. That auto-provisioning write is deliberately triggered
 * ONLY from admin/gis_mapping.php's authenticated admin view, never
 * from the public-facing patient/gis.php or homepage widget — so
 * anonymous public traffic never causes a Firestore write.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/inventory_match.php'; // hc_normalize_text()

if (!function_exists('hc_disease_registry_classification_meta')) {
    /**
     * The 5 fixed clinical classifications a condition can be assigned
     * — set by admin configuration (routine metadata upkeep, not a
     * per-case decision), never invented automatically for a new
     * condition (see hc_ensure_disease_registry_entries()).
     *
     * @return array<string, string> classification key -> display label.
     */
    function hc_disease_registry_classification_meta(): array
    {
        return [
            'vector_borne' => 'Vector-Borne Diseases',
            'waterborne_foodborne' => 'Waterborne and Foodborne Diseases',
            'respiratory_contact' => 'Respiratory and Contact-Transmitted Diseases',
            'environment_zoonotic' => 'Environment-Related Zoonotic Diseases',
            'animal_exposure' => 'Animal Exposure Incidents',
        ];
    }
}

if (!function_exists('hc_seed_disease_registry')) {
    /**
     * The 20-item initial registry configuration (the user's own
     * curated prevention-focused list) plus real conditions already
     * tracked in this collection before this schema existed (carried
     * forward during migration, never seeded fresh — see
     * hc_migrate_disease_registry_to_dynamic_schema()). Only used on a
     * genuinely empty collection (fresh install).
     */
    function hc_seed_disease_registry(): void
    {
        foreach (hc_disease_registry_seed_data() as $s) {
            hc_write_new_disease_registry_doc($s, 'System', '', 'system');
        }
    }
}

if (!function_exists('hc_disease_registry_seed_data')) {
    /**
     * @return array<int, array{name: string, synonyms: array<int,string>, classification: ?string, gisEligible: bool, publicVisibility: bool, isPreventable: bool, behavioralMeasures: string, environmentalMeasures: string, communityMeasures: string}>
     */
    function hc_disease_registry_seed_data(): array
    {
        return [
            ['name' => 'Dengue', 'synonyms' => ['dengue', 'dengue fever', 'dengue hemorrhagic fever'], 'classification' => 'vector_borne', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Use mosquito repellent and wear protective clothing during peak mosquito hours; seek care promptly for fever with rash or bleeding.',
                'environmentalMeasures' => 'Eliminate stagnant water and mosquito breeding sites around the home and workplace.',
                'communityMeasures' => 'Organize cleanup drives and mosquito-control campaigns in affected barangays.'],
            ['name' => 'Chikungunya', 'synonyms' => ['chikungunya'], 'classification' => 'vector_borne', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Use mosquito repellent and wear protective clothing during peak mosquito hours.',
                'environmentalMeasures' => 'Eliminate stagnant water and mosquito breeding sites.',
                'communityMeasures' => 'Organize mosquito-control and cleanup campaigns.'],
            ['name' => 'Malaria', 'synonyms' => ['malaria'], 'classification' => 'vector_borne', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Use mosquito nets and repellent, especially at night, and seek care promptly for fever after travel to malaria-endemic areas.',
                'environmentalMeasures' => 'Clear vegetation and standing water that harbor mosquito breeding sites.',
                'communityMeasures' => 'Support community mosquito-control and bed-net distribution programs.'],
            // NOTE: no short 'je' abbreviation synonym — it's a
            // substring of common unrelated words (e.g. "object"),
            // which caused real false-positive matches in production
            // data (196 "lower back pain after lifting heavy object"
            // chief complaints). Full name only.
            ['name' => 'Japanese Encephalitis', 'synonyms' => ['japanese encephalitis'], 'classification' => 'vector_borne', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Use mosquito repellent and protective clothing, particularly near rice fields or pig farms at dusk.',
                'environmentalMeasures' => 'Manage standing water near agricultural areas to reduce mosquito breeding.',
                'communityMeasures' => 'Support community vaccination and mosquito-control campaigns.'],

            ['name' => 'Leptospirosis', 'synonyms' => ['leptospirosis', 'lepto'], 'classification' => 'environment_zoonotic', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Avoid contaminated floodwater and wear protective footwear.',
                'environmentalMeasures' => 'Improve drainage, sanitation, and rodent control.',
                'communityMeasures' => 'Conduct community cleanup and flood-related health education.'],

            // NOTE: no bare 'age' abbreviation synonym — it's a
            // substring of many common unrelated words ("damage",
            // "average", "massage"); full terms only.
            ['name' => 'Acute Gastroenteritis (AGE)', 'synonyms' => ['acute gastroenteritis', 'gastroenteritis', 'diarrhea', 'diarrhoea', 'loose bowel movement', 'lbm'], 'classification' => 'waterborne_foodborne', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Drink safe, boiled or treated water and wash hands before eating.',
                'environmentalMeasures' => 'Improve sanitation and safe food handling in the community.',
                'communityMeasures' => 'Conduct water-safety and hygiene education campaigns.'],
            ['name' => 'Acute Bloody Diarrhea', 'synonyms' => ['acute bloody diarrhea', 'bloody diarrhea', 'dysentery', 'shigellosis'], 'classification' => 'waterborne_foodborne', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Practice handwashing and safe food/water preparation; seek care promptly.',
                'environmentalMeasures' => 'Ensure safe disposal of waste and isolate soiled materials.',
                'communityMeasures' => 'Conduct sanitation and food-safety education campaigns.'],
            ['name' => 'Typhoid Fever', 'synonyms' => ['typhoid', 'typhoid fever'], 'classification' => 'waterborne_foodborne', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Drink safe water, wash hands before eating, and avoid food from unsanitary sources.',
                'environmentalMeasures' => 'Improve water supply safety and sanitation facilities.',
                'communityMeasures' => 'Support community water-safety and vaccination programs where available.'],
            ['name' => 'Paratyphoid Fever', 'synonyms' => ['paratyphoid', 'paratyphoid fever'], 'classification' => 'waterborne_foodborne', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Drink safe water and practice proper food hygiene.',
                'environmentalMeasures' => 'Improve water supply safety and sanitation.',
                'communityMeasures' => 'Support community water-safety education.'],
            ['name' => 'Cholera', 'synonyms' => ['cholera'], 'classification' => 'waterborne_foodborne', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Drink only safe, treated water and practice proper food hygiene.',
                'environmentalMeasures' => 'Ensure safe water supply and proper waste disposal.',
                'communityMeasures' => 'Coordinate rapid community water-safety response during outbreaks.'],
            ['name' => 'Hepatitis A', 'synonyms' => ['hepatitis a', 'hep a'], 'classification' => 'waterborne_foodborne', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Wash hands before eating and avoid food/water from unsanitary sources.',
                'environmentalMeasures' => 'Improve sanitation and safe water supply.',
                'communityMeasures' => 'Support community vaccination and hygiene education where available.'],
            ['name' => 'Hepatitis E', 'synonyms' => ['hepatitis e', 'hep e'], 'classification' => 'waterborne_foodborne', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Drink safe water and avoid food from unsanitary sources.',
                'environmentalMeasures' => 'Improve water supply safety and sanitation.',
                'communityMeasures' => 'Support community water-safety education.'],
            ['name' => 'Rotavirus Infection', 'synonyms' => ['rotavirus'], 'classification' => 'waterborne_foodborne', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Wash hands often, especially before preparing food for children.',
                'environmentalMeasures' => 'Improve sanitation and safe water access.',
                'communityMeasures' => 'Support routine childhood vaccination campaigns where available.'],
            ['name' => 'Intestinal Parasitic Infections', 'synonyms' => ['intestinal parasit', 'ascariasis', 'hookworm', 'trichuriasis', 'enterobiasis', 'pinworm', 'worm infestation', 'helminth'], 'classification' => 'waterborne_foodborne', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Wash hands before eating and wear footwear outdoors.',
                'environmentalMeasures' => 'Improve sanitation and safe disposal of waste.',
                'communityMeasures' => 'Support community deworming and sanitation programs.'],

            ['name' => 'Influenza-like Illness (ILI)', 'synonyms' => ['influenza', 'influenza-like illness', 'ili', 'flu'], 'classification' => 'respiratory_contact', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Cover coughs/sneezes, wash hands often, and stay home when sick.',
                'environmentalMeasures' => 'Improve ventilation in crowded indoor spaces.',
                'communityMeasures' => 'Support seasonal vaccination campaigns where available.'],
            ['name' => 'Hand, Foot, and Mouth Disease (HFMD)', 'synonyms' => ['hand foot and mouth', 'hand, foot, and mouth', 'hfmd', 'hand-foot-mouth'], 'classification' => 'respiratory_contact', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Wash hands often and avoid sharing utensils with a symptomatic child.',
                'environmentalMeasures' => 'Disinfect shared toys and surfaces in daycare/school settings.',
                'communityMeasures' => 'Coordinate daycare/school health advisories during outbreaks.'],
            ['name' => 'Measles', 'synonyms' => ['measles', 'rubeola'], 'classification' => 'respiratory_contact', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Isolate suspected cases and seek care promptly for fever with rash.',
                'environmentalMeasures' => 'Improve ventilation in crowded settings.',
                'communityMeasures' => 'Support routine measles vaccination campaigns.'],
            ['name' => 'Pertussis', 'synonyms' => ['pertussis', 'whooping cough'], 'classification' => 'respiratory_contact', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Isolate a coughing child from infants until cleared by a health worker.',
                'environmentalMeasures' => 'Improve ventilation in shared spaces.',
                'communityMeasures' => 'Support routine childhood vaccination campaigns.'],
            ['name' => 'Chickenpox (Varicella)', 'synonyms' => ['chickenpox', 'chicken pox', 'varicella'], 'classification' => 'respiratory_contact', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Keep an infected child home from school/daycare until lesions have crusted over.',
                'environmentalMeasures' => 'Disinfect shared surfaces in school/daycare settings.',
                'communityMeasures' => 'Support routine vaccination campaigns where available.'],
            ['name' => 'COVID-19', 'synonyms' => ['covid', 'covid-19', 'sars-cov-2'], 'classification' => 'respiratory_contact', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Wear a mask in crowded indoor settings when unwell, wash hands often, and stay home when symptomatic.',
                'environmentalMeasures' => 'Improve ventilation in shared indoor spaces.',
                'communityMeasures' => 'Support community vaccination campaigns and public health advisories.'],

            ['name' => 'Animal Bite Cases', 'synonyms' => ['animal bite', 'dog bite', 'cat bite', 'bitten by', 'rabies exposure'], 'classification' => 'animal_exposure', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Avoid provoking unfamiliar animals and seek immediate medical assessment after any bite.',
                'environmentalMeasures' => 'Support safe animal management and responsible waste disposal.',
                'communityMeasures' => 'Promote responsible pet ownership and animal vaccination campaigns.'],

            // --- Carried forward from before this schema existed (real
            // docs, real history — never deleted), classified where a
            // clear fit exists under the 5 fixed categories. ---
            ['name' => 'Conjunctivitis (Sore Eyes)', 'synonyms' => ['conjunctivitis', 'sore eyes', 'pink eye'], 'classification' => 'respiratory_contact', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Avoid sharing towels/pillows, wash hands often, and avoid touching or rubbing the eyes.',
                'environmentalMeasures' => 'Disinfect shared surfaces in schools/daycare settings.',
                'communityMeasures' => 'Coordinate school/daycare health advisories during outbreaks.'],
            ['name' => 'Mumps', 'synonyms' => ['mumps'], 'classification' => 'respiratory_contact', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Isolate a symptomatic child from others until no longer contagious.',
                'environmentalMeasures' => 'Improve ventilation in shared spaces.',
                'communityMeasures' => 'Support routine MMR vaccination campaigns.'],
            ['name' => 'Pneumonia', 'synonyms' => ['pneumonia'], 'classification' => 'respiratory_contact', 'gisEligible' => true, 'publicVisibility' => true, 'isPreventable' => true,
                'behavioralMeasures' => 'Seek care early for persistent cough with fast or difficult breathing.',
                'environmentalMeasures' => 'Avoid indoor smoke exposure and improve ventilation.',
                'communityMeasures' => 'Support routine childhood vaccination campaigns.'],

            // --- Carried forward but left UNCLASSIFIED — doesn't cleanly
            // fit any of the 5 fixed categories, so it's not force-fit
            // into one; stays excluded from GIS/public display until an
            // admin reviews it. ---
            ['name' => 'Heat-Related Illness', 'synonyms' => ['heat stroke', 'heatstroke', 'heat exhaustion', 'hyperthermia'], 'classification' => null, 'gisEligible' => false, 'publicVisibility' => false, 'isPreventable' => true,
                'behavioralMeasures' => 'Stay hydrated and avoid prolonged outdoor activity during peak heat.',
                'environmentalMeasures' => 'Provide shaded rest areas in outdoor work/public settings.',
                'communityMeasures' => 'Issue heat-advisory alerts during extreme-heat periods.'],

            // --- Privacy-sensitive — stay recognized for internal
            // surveillance only, never on the public map (point 3). ---
            ['name' => 'Tuberculosis', 'synonyms' => ['tuberculosis', 'pulmonary tb', ' tb '], 'classification' => 'respiratory_contact', 'gisEligible' => true, 'publicVisibility' => false, 'isPreventable' => true,
                'behavioralMeasures' => 'Complete the full prescribed treatment course and cover coughs.',
                'environmentalMeasures' => 'Improve ventilation in shared living/working spaces.',
                'communityMeasures' => 'Support community TB screening and DOTS treatment programs.'],
        ];
    }
}

if (!function_exists('hc_write_new_disease_registry_doc')) {
    /**
     * Shared write path for a brand-new registry doc, used by both the
     * initial seed and the migration's "fresh-add" step — always starts
     * with a real, documented history[0] entry.
     */
    function hc_write_new_disease_registry_doc(array $s, string $byName, string $byUid, string $byRole): ?string
    {
        global $firestore;
        $now = date('c');
        $today = date('Y-m-d');

        return $firestore->addDocument('disease_registry', [
            'name' => $s['name'],
            'synonyms' => array_values(array_unique(array_map('hc_normalize_text', $s['synonyms']))),
            'classification' => $s['classification'],
            'registryStatus' => $s['classification'] !== null ? 'classified' : 'unclassified',
            'gisEligible' => $s['gisEligible'],
            'publicVisibility' => $s['gisEligible'] ? $s['publicVisibility'] : false,
            'isPreventable' => $s['isPreventable'],
            'behavioralMeasures' => $s['behavioralMeasures'],
            'environmentalMeasures' => $s['environmentalMeasures'],
            'communityMeasures' => $s['communityMeasures'],
            'isAutoDetected' => false,
            'createdAt' => $now,
            'updatedByName' => $byName,
            'updatedByUid' => $byUid,
            'updatedByRole' => $byRole,
            'updatedAt' => $now,
            'history' => [[
                'date' => $today,
                'event' => $s['classification'] !== null ? ('Created — seeded with classification ' . $s['classification']) : 'Created — unclassified (excluded from GIS)',
                'previousClassification' => null, 'newClassification' => $s['classification'],
                'previousGisEligible' => null, 'newGisEligible' => $s['gisEligible'],
                'previousPublicVisibility' => null, 'newPublicVisibility' => $s['gisEligible'] ? $s['publicVisibility'] : false,
                'changedByName' => $byName, 'changedByUid' => $byUid, 'changedByRole' => $byRole,
                'recordedAt' => $now,
            ]],
        ]);
    }
}

if (!function_exists('hc_migrate_disease_registry_to_dynamic_schema')) {
    /**
     * One-time, real data migration of the 17 real docs already in this
     * collection from the previous (iteration-3) schema — {name,
     * category} only — to the full dynamic schema. Matches each doc's
     * real name against the canonical seed list (exact or known rename)
     * to inherit its classification/eligibility/preventive text,
     * preserves 100% of its real history[] (appending one migration
     * entry), and never deletes a doc. After every existing doc is
     * migrated, any seed item not yet present (genuinely new conditions
     * like Chikungunya, Malaria, Cholera, etc.) is added fresh.
     *
     * Idempotent — a doc without the old 'category' key is already
     * migrated and is skipped.
     */
    function hc_migrate_disease_registry_to_dynamic_schema(): void
    {
        global $firestore;

        // Old name -> canonical seed name (renames only; everything
        // else matches by exact name).
        $renames = [
            'Acute Gastroenteritis / Diarrhea' => 'Acute Gastroenteritis (AGE)',
            'Hand-Foot-Mouth Disease' => 'Hand, Foot, and Mouth Disease (HFMD)',
            'Chickenpox' => 'Chickenpox (Varicella)',
            'Pertussis (Whooping Cough)' => 'Pertussis',
            'Influenza-like Illness' => 'Influenza-like Illness (ILI)',
            'Animal Bite Exposures' => 'Animal Bite Cases',
        ];
        $seedByName = [];
        foreach (hc_disease_registry_seed_data() as $s) {
            $seedByName[$s['name']] = $s;
        }

        $now = date('c');
        $today = date('Y-m-d');
        $migratedNames = [];

        foreach ($firestore->listDocuments('disease_registry') as $doc) {
            $f = $doc['fields'];
            if (!array_key_exists('category', $f)) {
                $migratedNames[] = $f['name'] ?? '';
                continue; // already migrated
            }

            $oldName = $f['name'] ?? '';
            $canonicalName = $renames[$oldName] ?? $oldName;
            $seed = $seedByName[$canonicalName] ?? null;

            $updated = $f;
            unset($updated['category']);
            $updated['name'] = $canonicalName;
            $updated['synonyms'] = $seed !== null
                ? array_values(array_unique(array_map('hc_normalize_text', $seed['synonyms'])))
                : array_values(array_unique(array_merge((array) ($f['keywords'] ?? []), [hc_normalize_text($oldName)])));
            unset($updated['keywords']);
            $updated['classification'] = $seed['classification'] ?? null;
            $updated['registryStatus'] = ($seed['classification'] ?? null) !== null ? 'classified' : 'unclassified';
            $updated['gisEligible'] = $seed['gisEligible'] ?? true;
            $updated['publicVisibility'] = ($seed['gisEligible'] ?? true) ? ($seed['publicVisibility'] ?? false) : false;
            $updated['behavioralMeasures'] = $seed['behavioralMeasures'] ?? ($f['preventiveMeasures'] ?? '');
            $updated['environmentalMeasures'] = $seed['environmentalMeasures'] ?? '';
            $updated['communityMeasures'] = $seed['communityMeasures'] ?? '';
            unset($updated['preventiveMeasures']);
            $updated['updatedByName'] = 'System';
            $updated['updatedByUid'] = '';
            $updated['updatedByRole'] = 'system';
            $updated['updatedAt'] = $now;

            $updated['history'] = array_values(is_array($f['history'] ?? null) ? $f['history'] : []);
            $updated['history'][] = [
                'date' => $today,
                'event' => 'Migrated to the dynamic registry schema (classification=' . ($updated['classification'] ?? 'none') . ', gisEligible=' . ($updated['gisEligible'] ? 'true' : 'false') . ', publicVisibility=' . ($updated['publicVisibility'] ? 'true' : 'false') . ')',
                'previousClassification' => null, 'newClassification' => $updated['classification'],
                'previousGisEligible' => null, 'newGisEligible' => $updated['gisEligible'],
                'previousPublicVisibility' => null, 'newPublicVisibility' => $updated['publicVisibility'],
                'changedByName' => 'System', 'changedByUid' => '', 'changedByRole' => 'system',
                'recordedAt' => $now,
            ];

            $firestore->setDocument('disease_registry', $doc['id'], $updated);
            $migratedNames[] = $canonicalName;
        }

        // Fresh-add any canonical seed item not already present.
        foreach (hc_disease_registry_seed_data() as $s) {
            if (!in_array($s['name'], $migratedNames, true)) {
                hc_write_new_disease_registry_doc($s, 'System', '', 'system');
            }
        }
    }
}

if (!function_exists('hc_get_disease_registry_docs')) {
    /**
     * The real read — self-seeds a genuinely empty collection, runs the
     * one-time schema migration when it finds the old shape still
     * present, cached per-request (function-local static — this
     * function is called more than once per request on at least two
     * real pages).
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
        } elseif (array_key_exists('category', $docs[0]['fields'] ?? [])) {
            hc_migrate_disease_registry_to_dynamic_schema();
            $docs = $firestore->listDocuments('disease_registry');
        }

        return $cache = $docs;
    }
}

if (!function_exists('hc_notifiable_disease_categories_from_registry')) {
    /**
     * Only CLASSIFIED entries feed real diagnosis matching (internal
     * surveillance AND public alike) — an unclassified/auto-detected
     * entry (hc_ensure_disease_registry_entries()) exists purely so an
     * admin can review it on admin/disease_registry.php; it must never
     * flood internal trend/forecast analytics (api/get-disease-trends.php,
     * admin/facility_analytics.php, etc.) with routine/chronic-care
     * diagnosis text (e.g. "Well child visit", "Essential hypertension")
     * just because it recurred often enough to get auto-provisioned.
     * The moment an admin assigns it a real classification, its
     * synonyms enter this list and every matching historical
     * consultation is picked up automatically on the next computation —
     * no backfill needed.
     *
     * @return array<string, array<int, string>> condition name -> real synonym list.
     */
    function hc_notifiable_disease_categories_from_registry(): array
    {
        $out = [];
        foreach (hc_get_disease_registry_docs() as $doc) {
            $f = $doc['fields'];
            if (($f['registryStatus'] ?? 'unclassified') !== 'classified') {
                continue;
            }
            $out[$f['name'] ?? ''] = $f['synonyms'] ?? [];
        }

        return $out;
    }
}

if (!function_exists('hc_condition_registry_meta_by_name')) {
    /** @return array<string, array> condition name -> full registry doc (id + fields merged). */
    function hc_condition_registry_meta_by_name(): array
    {
        $out = [];
        foreach (hc_get_disease_registry_docs() as $doc) {
            $f = $doc['fields'];
            $out[$f['name'] ?? ''] = ['id' => $doc['id']] + $f;
        }

        return $out;
    }
}

if (!function_exists('hc_is_publicly_visible')) {
    /**
     * The one place that decides whether a condition's real cases may
     * ever reach the public map — forces publicVisibility off whenever
     * gisEligible is off or the registry entry is still unclassified,
     * regardless of what's stored, so a half-configured entry can never
     * leak.
     */
    function hc_is_publicly_visible(?array $registryEntry): bool
    {
        if ($registryEntry === null) {
            return false;
        }
        if (($registryEntry['registryStatus'] ?? 'unclassified') !== 'classified') {
            return false;
        }
        if (empty($registryEntry['gisEligible'])) {
            return false;
        }

        return !empty($registryEntry['publicVisibility']);
    }
}

if (!function_exists('hc_clinical_qualifier_terms_probable')) {
    /**
     * WHO/DOH case-definition convention: "probable" sits between
     * suspected and confirmed in certainty — kept as its own tier
     * rather than folded into 'suspected'.
     */
    function hc_clinical_qualifier_terms_probable(): array
    {
        return ['probable'];
    }
}

if (!function_exists('hc_clinical_qualifier_terms_suspected')) {
    /**
     * Lower-certainty hedging language — a diagnosis containing any of
     * these is never treated as CONFIRMED, no matter which condition
     * synonym it also contains. Real clinical free-text convention
     * (e.g. "suspected dengue", "r/o leptospirosis"), not a per-disease
     * judgment call.
     */
    function hc_clinical_qualifier_terms_suspected(): array
    {
        return ['suspected', 'suspect', 'possible', 'r/o', 'rule out', 'to consider', 'working diagnosis', 'differential diagnosis'];
    }
}

if (!function_exists('hc_dismissed_registry_synonyms')) {
    /**
     * Permanent exclusion list — when an admin deletes a registry entry
     * (api/delete-disease-registry-entry.php), its real synonym list is
     * recorded in the "disease_registry_dismissed" collection instead of
     * just vanishing. Without this, hc_ensure_disease_registry_entries()
     * would re-discover the same real diagnosis text (or any other real
     * phrasing containing the same deleted keyword, e.g. "Typhoid fever,
     * probable" after "Typhoid Fever" itself was deleted) on the very
     * next admin page view and silently resurrect it as a brand-new
     * "unclassified" entry — deleting something must actually stick.
     *
     * @return array<int, string> every dismissed synonym, flattened.
     */
    function hc_dismissed_registry_synonyms(): array
    {
        global $firestore;
        $out = [];
        foreach ($firestore->listDocuments('disease_registry_dismissed') as $doc) {
            foreach (($doc['fields']['synonyms'] ?? []) as $syn) {
                $out[] = $syn;
            }
        }

        return $out;
    }
}

if (!function_exists('hc_record_dismissed_registry_synonyms')) {
    /**
     * Writes one real tombstone doc so hc_dismissed_registry_synonyms()
     * picks it up on every future read — called whenever a registry
     * entry is deleted (api/delete-disease-registry-entry.php).
     */
    function hc_record_dismissed_registry_synonyms(string $name, array $synonyms, string $byName, string $byUid, string $byRole): void
    {
        global $firestore;
        $firestore->addDocument('disease_registry_dismissed', [
            'name' => $name,
            'synonyms' => array_values(array_unique($synonyms)),
            'dismissedByName' => $byName,
            'dismissedByUid' => $byUid,
            'dismissedByRole' => $byRole,
            'dismissedAt' => date('c'),
        ]);
    }
}

if (!function_exists('hc_ensure_disease_registry_entries')) {
    /**
     * Real, idempotent auto-provisioning — for diagnosis text matching
     * no existing registry synonym, creates ONE new 'unclassified' doc
     * per distinct normalized text not already provisioned (never
     * duplicated on repeat calls). Always starts gisEligible=false,
     * publicVisibility=false, isAutoDetected=true — an admin reviews
     * and classifies it afterward via admin/disease_registry.php.
     *
     * Deliberately called ONLY from admin/gis_mapping.php's
     * authenticated admin view (includes/admin/gis_mapping_firestore.php)
     * — never from the public patient/gis.php or homepage widget, so
     * anonymous public traffic never triggers a Firestore write.
     *
     * Real diagnosis text matching a permanently dismissed synonym
     * (hc_dismissed_registry_synonyms() — set when an admin deletes an
     * entry via api/delete-disease-registry-entry.php) is treated as
     * already recognized and never re-provisioned.
     *
     * @return int Number of new entries created.
     */
    function hc_ensure_disease_registry_entries(array $medicalRecordDocs, int $minOccurrences = 3): int
    {
        global $firestore;

        $existingSynonyms = hc_dismissed_registry_synonyms();
        $alreadyProvisioned = [];
        foreach (hc_get_disease_registry_docs() as $doc) {
            $f = $doc['fields'];
            foreach (($f['synonyms'] ?? []) as $syn) {
                $existingSynonyms[] = $syn;
            }
            if (!empty($f['isAutoDetected'])) {
                $alreadyProvisioned[] = $f['synonyms'][0] ?? '';
            }
        }

        $groups = [];
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
            $recognized = false;
            foreach ($existingSynonyms as $syn) {
                if (str_contains($normalized, $syn)) {
                    $recognized = true;
                    break;
                }
            }
            if ($recognized) {
                continue;
            }
            $groups[$normalized] ??= ['sample' => $raw, 'count' => 0];
            $groups[$normalized]['count']++;
        }

        $created = 0;
        $now = date('c');
        $today = date('Y-m-d');
        foreach ($groups as $normalized => $g) {
            if ($g['count'] < $minOccurrences || in_array($normalized, $alreadyProvisioned, true)) {
                continue;
            }
            $firestore->addDocument('disease_registry', [
                'name' => $g['sample'],
                'synonyms' => [$normalized],
                'classification' => null,
                'registryStatus' => 'unclassified',
                'gisEligible' => false,
                'publicVisibility' => false,
                'isPreventable' => false,
                'behavioralMeasures' => '',
                'environmentalMeasures' => '',
                'communityMeasures' => '',
                'isAutoDetected' => true,
                'createdAt' => $now,
                'updatedByName' => 'System', 'updatedByUid' => '', 'updatedByRole' => 'system',
                'updatedAt' => $now,
                'history' => [[
                    'date' => $today,
                    'event' => 'Auto-provisioned from ' . $g['count'] . ' real unmatched diagnoses — unclassified, excluded from GIS',
                    'previousClassification' => null, 'newClassification' => null,
                    'previousGisEligible' => null, 'newGisEligible' => false,
                    'previousPublicVisibility' => null, 'newPublicVisibility' => false,
                    'changedByName' => 'System', 'changedByUid' => '', 'changedByRole' => 'system',
                    'recordedAt' => $now,
                ]],
            ]);
            $created++;
        }

        return $created;
    }
}
