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
 *   Serology:            Dengue Test, CD4, HBsAg, Syphilis, Viral Load, HIV
 *   Hematology:          CBC (Complete Blood Count)
 *   Clinical Microscopy: Fecal Occult Blood (FOBT), Urinalysis, Stool Exam
 *
 * "Dengue Test" (NS1 Ag/IgM/IgG together, one order/one result sheet —
 * medical_technician/dengue_test_form.php) replaced the previous
 * separate, generic "IgM and IgG"/"Dengue NS1" catalog entries, which
 * didn't match the real Lipa City Health Office "SEROLOGY — DENGUE
 * TEST" paper form (one order, 3 lines). Historical lab_results docs
 * still saved under those 2 old names are left untouched — not
 * official tests any more, but hc_lab_test_display_name()'s own
 * fallback (return the name unchanged) still displays them correctly.
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
            'Serology' => ['Dengue Test', 'CD4', 'HBsAg', 'Syphilis', 'Viral Load', 'HIV'],
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

if (!function_exists('hc_lab_test_required_specimen')) {
    /**
     * The one real, correct specimen a test needs in standard clinical
     * lab practice — e.g. the routine chemistry panel (BUN, Creatinine,
     * SGPT/SGOT, Total Cholesterol, Blood Uric Acid, HDL/LDL) runs on
     * venous blood serum; HbA1c, CBC and Viral Load each need their own
     * different real draw (EDTA whole blood / EDTA plasma, not serum);
     * Fasting Blood Sugar and Triglycerides additionally need real
     * fasting windows (8h and 9-12h respectively — different from each
     * other, not interchangeable); Urinalysis/Fecal Occult Blood/Stool
     * Exam need their own non-blood specimen entirely.
     *
     * Null for: CD4 (keeps its own existing hc_cd4_specimen_label(),
     * untouched), Gram Stain and Wet Mount (the real specimen genuinely
     * depends on the clinical indication — vaginal/urethral swab,
     * sputum, wound exudate, stool, etc. — so forcing one fixed answer
     * here would itself be medically wrong, not more "credible"), and
     * any custom test a medical technologist has added beyond the
     * official 23 (medical_technician/lab_test_catalog.php's "Add
     * Laboratory") — all three keep today's free-text Specimen field.
     */
    function hc_lab_test_required_specimen(string $testName): ?string
    {
        static $map = null;
        if ($map === null) {
            $map = [
                'Blood Urea Nitrogen (BUN)' => 'Venous Blood (Serum)',
                'Creatinine' => 'Venous Blood (Serum)',
                'HDL/LDL' => 'Venous Blood (Serum), fasting preferred',
                'SGPT' => 'Venous Blood (Serum)',
                'HbA1c' => 'Whole Blood (EDTA)',
                'Blood Uric Acid' => 'Venous Blood (Serum)',
                'Total Cholesterol' => 'Venous Blood (Serum), fasting preferred',
                'SGOT' => 'Venous Blood (Serum)',
                'Fasting Blood Sugar (FBS)' => 'Venous Blood (Serum/Plasma), after an 8-hour fast',
                'Triglycerides' => 'Venous Blood (Serum), after a 9-12 hour fast',
                // Kept for the 36+21 historical "IgM and IgG"/"Dengue NS1"
                // lab_results docs (no longer official tests — see this
                // file's own header comment — but still readable through
                // the generic path if either ever needs re-viewing).
                'IgM and IgG' => 'Venous Blood (Serum)',
                'Dengue NS1' => 'Venous Blood (Serum)',
                'Dengue Test' => 'Venous Blood (Serum)',
                'HBsAg' => 'Venous Blood (Serum)',
                'Syphilis' => 'Venous Blood (Serum)',
                'Viral Load' => 'Venous Blood (EDTA Plasma)',
                'HIV' => 'Venous Blood (Serum)',
                'CBC (Complete Blood Count)' => 'Whole Blood (EDTA)',
                'Fecal Occult Blood (FOBT)' => 'Stool',
                'Urinalysis' => 'Urine (midstream, clean-catch)',
                'Stool Exam' => 'Stool',
            ];
        }

        return $map[hc_lab_test_display_name($testName)] ?? null;
    }
}
