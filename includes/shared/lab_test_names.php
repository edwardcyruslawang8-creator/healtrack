<?php
/**
 * =====================================================================
 * Laboratory test names — the one official spelling of each test
 * ---------------------------------------------------------------------
 * HEALTrack's official test names (exact capitalization, spelling and
 * acronyms — mixed-case ones like HbA1c / HBsAg / IgM kept as-is):
 *
 *   Clinical Chemistry:  Blood Urea Nitrogen (BUN), Creatinine, HDL/LDL,
 *                        SGPT, HbA1c, Blood Uric Acid, Total Cholesterol,
 *                        SGOT, Fasting Blood Sugar (FBS), Triglycerides
 *   Bacteriology:        Gram Stain, Wet Mount
 *   Serology:            IgM and IgG, Dengue NS1, CD4, HBsAg, Syphilis,
 *                        Viral Load, HIV
 *   Hematology:          CBC (Complete Blood Count)
 *   Clinical Microscopy: Fecal Occult Blood (FOBT), Urinalysis, Stool Exam
 *
 * Lab orders/results saved BEFORE this naming was fixed still carry the
 * old spellings in the database (e.g. "HBSAG", "IGM and IGG", "CBC",
 * "Fasting Blood Sugar", "Fecal Occult Blood (FBOT)"). Those records are
 * deliberately left untouched; instead, every place that shows or
 * compares a test name passes it through hc_lab_test_display_name(), so
 * old and new records display — and match — identically.
 * =====================================================================
 */

if (!function_exists('hc_lab_test_official_names')) {
    /** @return array<string, string[]> category => official test names */
    function hc_lab_test_official_names(): array
    {
        return [
            'Clinical Chemistry' => ['Blood Urea Nitrogen (BUN)', 'Creatinine', 'HDL/LDL', 'SGPT', 'HbA1c', 'Blood Uric Acid', 'Total Cholesterol', 'SGOT', 'Fasting Blood Sugar (FBS)', 'Triglycerides'],
            'Bacteriology' => ['Gram Stain', 'Wet Mount'],
            'Serology' => ['IgM and IgG', 'Dengue NS1', 'CD4', 'HBsAg', 'Syphilis', 'Viral Load', 'HIV'],
            'Hematology' => ['CBC (Complete Blood Count)'],
            'Clinical Microscopy' => ['Fecal Occult Blood (FOBT)', 'Urinalysis', 'Stool Exam'],
        ];
    }
}

if (!function_exists('hc_lab_test_display_name')) {
    /**
     * The official spelling of a test name, whatever spelling/casing it
     * was stored with. Matching ignores case and extra spaces, and also
     * accepts the older forms (no acronym suffix, all-caps, the "FBOT"
     * typo). A name that isn't a known test (e.g. a custom test a medical
     * technologist added) is returned unchanged, just trimmed.
     */
    function hc_lab_test_display_name(?string $name): string
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (hc_lab_test_official_names() as $tests) {
                foreach ($tests as $official) {
                    $map[strtolower($official)] = $official;
                }
            }
            // Older spellings still present in saved records.
            $aliases = [
                'blood urea nitrogen' => 'Blood Urea Nitrogen (BUN)',
                'bun' => 'Blood Urea Nitrogen (BUN)',
                'fasting blood sugar' => 'Fasting Blood Sugar (FBS)',
                'fbs' => 'Fasting Blood Sugar (FBS)',
                'cbc' => 'CBC (Complete Blood Count)',
                'complete blood count' => 'CBC (Complete Blood Count)',
                'complete blood count (cbc)' => 'CBC (Complete Blood Count)',
                'fecal occult blood' => 'Fecal Occult Blood (FOBT)',
                'fecal occult blood (fbot)' => 'Fecal Occult Blood (FOBT)',
                'fobt' => 'Fecal Occult Blood (FOBT)',
                'igm and igg' => 'IgM and IgG',
                'hba1c' => 'HbA1c',
                'hbsag' => 'HBsAg',
            ];
            $map += $aliases;
        }

        $clean = trim(preg_replace('/\s+/', ' ', (string) $name));
        return $map[strtolower($clean)] ?? $clean;
    }
}

if (!function_exists('hc_lab_test_names_match')) {
    /** Whether two stored/typed test names refer to the same test. */
    function hc_lab_test_names_match(?string $a, ?string $b): bool
    {
        return strcasecmp(hc_lab_test_display_name($a), hc_lab_test_display_name($b)) === 0;
    }
}
