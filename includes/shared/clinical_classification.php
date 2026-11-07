<?php
/**
 * =====================================================================
 * Clinical Classification — automated, code-based disease recognition
 * and public-visibility rules (replaces includes/firestore/disease_registry.php)
 * ---------------------------------------------------------------------
 * There is NO admin-controlled registry behind this file — no Firestore
 * collection, no management UI, no runtime edit of any kind. Every
 * condition recognized here, and every decision about whether its
 * clinical chapter is safe to show on the public GIS map, is a plain
 * PHP array reviewed and changed the same way any other code in this
 * app is (a git-tracked pull request) — that review history IS the
 * audit trail for this layer, there is nothing left to approve at
 * runtime.
 *
 * TWO SEPARATE DECISIONS, kept deliberately apart (see
 * hc_clinical_chapter_meta() vs hc_clinical_condition_catalog()):
 *   1. RECOGNITION — does this diagnosis text refer to a known,
 *      standardized condition? (hc_clinical_condition_catalog(), keyed
 *      by real ICD-10 chapter groupings, not an arbitrary admin list.)
 *   2. PUBLICATION ELIGIBILITY — is this condition's clinical CHAPTER
 *      (never an individual disease, never an individual case) safe
 *      for prevention-focused public display? (hc_clinical_chapter_meta().)
 * A new condition added under an already-eligible chapter inherits
 * that chapter's eligibility automatically — nobody decides per-disease
 * whether it's "allowed."
 *
 * HEALTrack's clinical workflow has no existing ICD-10/coded-diagnosis
 * field and no existing confirmed/suspected case-classification field —
 * diagnosis and chiefComplaint are free text only (confirmed by
 * grepping api/complete-consultation.php and
 * includes/firestore/consultations.php). So "standardized terminology"
 * here means: free-text diagnosis is matched against real synonyms for
 * each standardized condition below, and each condition carries its
 * real ICD-10 code/range for traceability — not that this app captures
 * ICD-10 coding at the point of care (it doesn't, and this file doesn't
 * change that).
 *
 * The excluded chapter (chronic_stigmatized_communicable: TB, COVID-19,
 * HIV, STIs, Viral Hepatitis) mirrors real Philippine legal/clinical
 * confidentiality norms for these specific conditions (RA 11332's
 * notifiable-disease confidentiality provisions, RA 11166 for HIV) —
 * these stay fully visible to internal surveillance
 * (hc_disease_demand_forecast()'s direct callers), only excluded from
 * the PUBLIC map.
 * =====================================================================
 */

require_once __DIR__ . '/inventory_match.php'; // hc_normalize_text()

if (!function_exists('hc_clinical_chapter_meta')) {
    /**
     * The ONE predefined, fixed public-visibility rule — per CLINICAL
     * CHAPTER, never per individual disease. Every chapter here is
     * publicly eligible except the stigmatized/legally-sensitive one.
     *
     * @return array<string, array{label: string, publiclyEligible: bool}>
     */
    function hc_clinical_chapter_meta(): array
    {
        return [
            'vector_borne_viral' => ['label' => 'Vector-Borne Viral Illness', 'publiclyEligible' => true],
            'zoonotic_bacterial' => ['label' => 'Zoonotic Bacterial Illness', 'publiclyEligible' => true],
            'enteric_diarrheal' => ['label' => 'Enteric and Diarrheal Illness', 'publiclyEligible' => true],
            'intestinal_parasitic' => ['label' => 'Intestinal Parasitic Infection', 'publiclyEligible' => true],
            'acute_respiratory' => ['label' => 'Acute Respiratory Infection', 'publiclyEligible' => true],
            'vaccine_preventable_childhood' => ['label' => 'Vaccine-Preventable Childhood Illness', 'publiclyEligible' => true],
            'conjunctival' => ['label' => 'Conjunctival Infection', 'publiclyEligible' => true],
            'zoonotic_exposure' => ['label' => 'Animal Bite / Rabies Exposure', 'publiclyEligible' => true],
            'environmental_heat' => ['label' => 'Heat-Related Illness', 'publiclyEligible' => true],
            'chronic_stigmatized_communicable' => ['label' => 'Chronic/Stigma-Sensitive Communicable Disease', 'publiclyEligible' => false],
        ];
    }
}

if (!function_exists('hc_clinical_condition_catalog')) {
    /**
     * Standardized conditions, grouped under real ICD-10 chapters. Each
     * entry's 'keywords' are real-world synonyms/phrasings matched
     * case-insensitively as a substring of the normalized diagnosis (or
     * chief complaint) text — broadened deliberately so recognition
     * isn't a thin, easily-outdated list.
     *
     * @return array<int, array{name: string, icd10: string, chapter: string, keywords: array<int,string>, isPreventable: bool, preventiveMeasures: string}>
     */
    function hc_clinical_condition_catalog(): array
    {
        return [
            ['name' => 'Dengue', 'icd10' => 'A90-A91', 'chapter' => 'vector_borne_viral',
                'keywords' => ['dengue'], 'isPreventable' => true,
                'preventiveMeasures' => 'Eliminate stagnant water around the home, use mosquito repellent, wear long sleeves during peak mosquito hours, and seek care promptly for fever with rash or bleeding.'],

            ['name' => 'Leptospirosis', 'icd10' => 'A27', 'chapter' => 'zoonotic_bacterial',
                'keywords' => ['leptospirosis', 'lepto'], 'isPreventable' => true,
                'preventiveMeasures' => 'Avoid wading through floodwater; if exposure is unavoidable, wear boots and cover open wounds, and seek care promptly for fever after flood exposure.'],

            ['name' => 'Acute Gastroenteritis / Diarrhea', 'icd10' => 'A09', 'chapter' => 'enteric_diarrheal',
                'keywords' => ['gastroenteritis', 'diarrhea', 'diarrhoea', 'loose bowel movement', 'lbm'], 'isPreventable' => true,
                'preventiveMeasures' => 'Drink safe, boiled or treated water, wash hands before eating, and practice proper food handling and sanitation.'],
            ['name' => 'Typhoid Fever', 'icd10' => 'A01.0', 'chapter' => 'enteric_diarrheal',
                'keywords' => ['typhoid'], 'isPreventable' => true,
                'preventiveMeasures' => 'Drink safe water, wash hands before eating, and avoid food from unsanitary sources.'],
            ['name' => 'Shigellosis / Bacterial Dysentery', 'icd10' => 'A03', 'chapter' => 'enteric_diarrheal',
                'keywords' => ['shigellosis', 'dysentery'], 'isPreventable' => true,
                'preventiveMeasures' => 'Practice handwashing and safe food/water preparation; isolate soiled linens and dispose of waste safely.'],
            ['name' => 'Cholera', 'icd10' => 'A00', 'chapter' => 'enteric_diarrheal',
                'keywords' => ['cholera'], 'isPreventable' => true,
                'preventiveMeasures' => 'Drink only safe, treated water and practice proper sanitation and food hygiene.'],

            ['name' => 'Intestinal Parasitic Infection', 'icd10' => 'B65-B83', 'chapter' => 'intestinal_parasitic',
                'keywords' => ['ascariasis', 'hookworm', 'trichuriasis', 'enterobiasis', 'pinworm', 'intestinal parasit', 'intestinal worm', 'helminth', 'worm infestation'], 'isPreventable' => true,
                'preventiveMeasures' => 'Wash hands before eating, wear footwear outdoors, and practice proper sanitation and deworming as advised by a health worker.'],

            ['name' => 'Influenza-like Illness', 'icd10' => 'J09-J11', 'chapter' => 'acute_respiratory',
                'keywords' => ['influenza', 'flu-like', ' flu ', 'flu,', 'ili'], 'isPreventable' => true,
                'preventiveMeasures' => 'Cover coughs/sneezes, wash hands often, stay home when sick, and keep routine vaccinations up to date where available.'],
            ['name' => 'Pneumonia', 'icd10' => 'J12-J18', 'chapter' => 'acute_respiratory',
                'keywords' => ['pneumonia'], 'isPreventable' => true,
                'preventiveMeasures' => 'Keep routine childhood vaccinations up to date, avoid smoke exposure, and seek care early for persistent cough with fast or difficult breathing.'],
            ['name' => 'Pertussis (Whooping Cough)', 'icd10' => 'A37', 'chapter' => 'acute_respiratory',
                'keywords' => ['pertussis', 'whooping cough'], 'isPreventable' => true,
                'preventiveMeasures' => 'Keep routine childhood vaccinations up to date and isolate a coughing child from infants until cleared by a health worker.'],
            ['name' => 'Acute Upper Respiratory Tract Infection', 'icd10' => 'J00-J06', 'chapter' => 'acute_respiratory',
                'keywords' => ['upper respiratory tract infection', 'urti', 'acute bronchitis'], 'isPreventable' => true,
                'preventiveMeasures' => 'Cover coughs/sneezes, wash hands often, and avoid close contact with others while symptomatic.'],

            ['name' => 'Chickenpox (Varicella)', 'icd10' => 'B01', 'chapter' => 'vaccine_preventable_childhood',
                'keywords' => ['chickenpox', 'chicken pox', 'varicella'], 'isPreventable' => true,
                'preventiveMeasures' => 'Keep routine vaccinations up to date and keep an infected child home from school/daycare until lesions have crusted over.'],
            ['name' => 'Measles', 'icd10' => 'B05', 'chapter' => 'vaccine_preventable_childhood',
                'keywords' => ['measles', 'rubeola'], 'isPreventable' => true,
                'preventiveMeasures' => 'Keep routine measles vaccination up to date; isolate suspected cases and seek care promptly for fever with rash.'],
            ['name' => 'Hand-Foot-Mouth Disease', 'icd10' => 'B08.4', 'chapter' => 'vaccine_preventable_childhood',
                'keywords' => ['hand-foot-mouth', 'hand, foot, and mouth', 'hand foot and mouth', 'hfmd'], 'isPreventable' => true,
                'preventiveMeasures' => 'Wash hands often, disinfect shared toys/surfaces, and keep a symptomatic child home from daycare/school.'],
            ['name' => 'Mumps', 'icd10' => 'B26', 'chapter' => 'vaccine_preventable_childhood',
                'keywords' => ['mumps'], 'isPreventable' => true,
                'preventiveMeasures' => 'Keep routine MMR vaccination up to date and isolate a symptomatic child from others until no longer contagious.'],

            ['name' => 'Conjunctivitis (Sore Eyes)', 'icd10' => 'B30 / H10', 'chapter' => 'conjunctival',
                'keywords' => ['conjunctivitis', 'sore eyes', 'pink eye'], 'isPreventable' => true,
                'preventiveMeasures' => 'Avoid sharing towels/pillows, wash hands often, and avoid touching or rubbing the eyes.'],

            ['name' => 'Animal Bite Exposure', 'icd10' => 'W54 / Z20.3', 'chapter' => 'zoonotic_exposure',
                'keywords' => ['animal bite', 'dog bite', 'cat bite', 'rabies exposure', 'rabies', 'bitten by'], 'isPreventable' => true,
                'preventiveMeasures' => 'Avoid stray or unfamiliar animals, have pets vaccinated, and seek immediate post-exposure care after any bite.'],

            ['name' => 'Heat-Related Illness', 'icd10' => 'T67', 'chapter' => 'environmental_heat',
                'keywords' => ['heat stroke', 'heatstroke', 'heat exhaustion', 'hyperthermia'], 'isPreventable' => true,
                'preventiveMeasures' => 'Stay hydrated, avoid prolonged outdoor activity during peak heat, and rest in shade or a cool area.'],

            // --- Chapter deliberately NOT publicly eligible (see
            // hc_clinical_chapter_meta()) — still fully recognized and
            // visible on every internal-surveillance page. ---
            ['name' => 'Tuberculosis', 'icd10' => 'A15-A19', 'chapter' => 'chronic_stigmatized_communicable',
                'keywords' => ['tuberculosis', 'pulmonary tb', ' tb '], 'isPreventable' => true, 'preventiveMeasures' => ''],
            ['name' => 'COVID-19', 'icd10' => 'U07.1', 'chapter' => 'chronic_stigmatized_communicable',
                'keywords' => ['covid'], 'isPreventable' => true, 'preventiveMeasures' => ''],
            ['name' => 'HIV', 'icd10' => 'B20-B24', 'chapter' => 'chronic_stigmatized_communicable',
                'keywords' => ['hiv', 'aids'], 'isPreventable' => true, 'preventiveMeasures' => ''],
            ['name' => 'Sexually Transmitted Infection', 'icd10' => 'A50-A64', 'chapter' => 'chronic_stigmatized_communicable',
                'keywords' => ['gonorrhea', 'syphilis', 'chlamydia', 'sexually transmitted'], 'isPreventable' => true, 'preventiveMeasures' => ''],
            ['name' => 'Viral Hepatitis', 'icd10' => 'B15-B19', 'chapter' => 'chronic_stigmatized_communicable',
                'keywords' => ['hepatitis'], 'isPreventable' => true, 'preventiveMeasures' => ''],
        ];
    }
}

if (!function_exists('hc_condition_chapter_by_name')) {
    /** @return array<string, string> condition name -> chapter key. */
    function hc_condition_chapter_by_name(): array
    {
        $out = [];
        foreach (hc_clinical_condition_catalog() as $c) {
            $out[$c['name']] = $c['chapter'];
        }

        return $out;
    }
}

if (!function_exists('hc_condition_catalog_by_name')) {
    /** @return array<string, array> condition name -> full catalog entry. */
    function hc_condition_catalog_by_name(): array
    {
        $out = [];
        foreach (hc_clinical_condition_catalog() as $c) {
            $out[$c['name']] = $c;
        }

        return $out;
    }
}

if (!function_exists('hc_is_chapter_publicly_eligible')) {
    function hc_is_chapter_publicly_eligible(?string $chapter): bool
    {
        if ($chapter === null) {
            return false;
        }
        $meta = hc_clinical_chapter_meta();

        return !empty($meta[$chapter]['publiclyEligible']);
    }
}

if (!function_exists('hc_clinical_qualifier_terms')) {
    /**
     * Fixed, documented hedging-language list — a diagnosis containing
     * any of these is treated as a SUSPECTED case, never CONFIRMED, no
     * matter which condition keyword it also contains. Real clinical
     * free-text convention (e.g. "suspected dengue", "r/o leptospirosis"),
     * not a per-disease judgment call.
     *
     * @return array<int, string>
     */
    function hc_clinical_qualifier_terms(): array
    {
        return ['suspected', 'suspect', 'probable', 'possible', 'r/o', 'rule out', 'to consider', 'working diagnosis', 'differential diagnosis'];
    }
}

if (!function_exists('hc_unclassified_diagnoses_report')) {
    /**
     * Data-quality monitoring ONLY — real, recurring diagnosis text that
     * matches no entry in hc_clinical_condition_catalog() yet. No action
     * is attached anywhere to this list (no "add to registry" — there is
     * no registry); it exists purely so unfamiliar terms are visible for
     * review rather than silently dropped or guessed at.
     *
     * @param array $medicalRecordDocs Raw medical_records docs (type=consultation).
     * @return array<int, array{normalizedText: string, sampleOriginalText: string, count: int, firstDate: string, lastDate: string}>
     */
    function hc_unclassified_diagnoses_report(array $medicalRecordDocs, int $minOccurrences = 3, int $limit = 20): array
    {
        $allKeywords = [];
        foreach (hc_clinical_condition_catalog() as $c) {
            foreach ($c['keywords'] as $kw) {
                $allKeywords[] = $kw;
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
            foreach ($allKeywords as $kw) {
                if (str_contains($normalized, $kw)) {
                    $recognized = true;
                    break;
                }
            }
            if ($recognized) {
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

        $report = [];
        foreach ($groups as $normalized => $g) {
            if ($g['count'] < $minOccurrences) {
                continue;
            }
            $report[] = [
                'normalizedText' => $normalized,
                'sampleOriginalText' => $g['sample'],
                'count' => $g['count'],
                'firstDate' => $g['firstDate'],
                'lastDate' => $g['lastDate'],
            ];
        }
        usort($report, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_slice($report, 0, $limit);
    }
}
