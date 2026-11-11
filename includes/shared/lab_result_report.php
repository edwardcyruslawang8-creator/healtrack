<?php
/**
 * =====================================================================
 * Shared — Lab Result Report (uniform printable template)
 * ---------------------------------------------------------------------
 * The ONE paper-form layout used everywhere a completed (or still-
 * pending, shown blank) lab test gets printed or filed as a real
 * attachment, matching the real Lipa City Health Office paper form
 * (header/patient-info table/title/results table/signature line) —
 * every test uses this same template; only which rows the results
 * table shows varies per test (set by whoever builds $labFields: see
 * medical_technician/print_gram_stain.php, print_cd4_count.php,
 * print_urinalysis.php, print_serology_rapid_test.php for the 4
 * built-in tests' own fixed field sets, synthesized blank for a still-
 * pending test so the right row labels show even with nothing filled
 * in yet; any OTHER catalog test's own resultGroups, already in this
 * exact shape, needs no synthesizing at all).
 *
 * Split into 3 pieces so the SAME markup serves two different callers:
 *   - hc_lab_result_report_css() + hc_lab_result_report_body_html() —
 *     used by the 4 print_*.php pages above, each wrapping the body in
 *     its own <html>/<head>/toolbar/auto-print script (a live, browser-
 *     only view — never saved anywhere).
 *   - hc_build_lab_result_report_html() — the full, standalone document
 *     (its own <html>/<head>, no toolbar/print script) stored as a
 *     completed test's real "Attached File" by
 *     includes/shared/lab_result_attachment.php right after a medical
 *     technologist completes a test, and streamed back by
 *     api/download-lab-result-attachment.php. No PDF/image-generation
 *     library is available on this server (no GD/Imagick, nothing in
 *     vendor/), so this is deliberately an HTML file rather than a
 *     rendered image/PDF — openable and printable (Ctrl+P) directly in
 *     a browser.
 * =====================================================================
 */

require_once __DIR__ . '/lab_test_names.php'; // hc_lab_test_display_name()

if (!function_exists('hc_lab_result_report_css')) {
    function hc_lab_result_report_css(): string
    {
        return implode("\n", [
            '* { box-sizing: border-box; }',
            'body { font-family: "Arial", "Helvetica", sans-serif; color: #111; max-width: 720px; margin: 32px auto; padding: 0 24px; background: #fff; }',
            '.prt-toolbar { text-align: center; margin-bottom: 24px; }',
            '.prt-toolbar button { background: #7a0d0d; color: #fff; border: none; border-radius: 8px; padding: 10px 22px; font-size: 14px; font-weight: 600; cursor: pointer; }',
            '.prt-header { text-align: center; margin-bottom: 22px; }',
            '.prt-header h1 { font-size: 20px; font-weight: 800; margin: 0 0 2px; letter-spacing: .5px; }',
            '.prt-header h2 { font-size: 13px; font-weight: 600; margin: 0; letter-spacing: .5px; }',
            '.prt-info-table { width: 100%; border-collapse: collapse; margin-bottom: 22px; }',
            '.prt-info-table td { border: 1px solid #111; padding: 8px 10px; font-size: 13px; vertical-align: bottom; }',
            '.prt-info-table td span { display: block; font-size: 10px; color: #444; margin-bottom: 10px; }',
            '.prt-title { text-align: center; margin-bottom: 22px; }',
            '.prt-title .main { font-size: 17px; font-weight: 800; text-decoration: underline; }',
            'table.lr-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }',
            'table.lr-table th, table.lr-table td { border: 1px solid #111; padding: 8px 10px; text-align: left; font-size: 12.5px; }',
            'table.lr-table th { background: #f0f0f0; text-transform: uppercase; font-size: 10px; letter-spacing: .5px; }',
            '.lr-group-row td { background: #f7f7f7; font-weight: 700; text-transform: uppercase; font-size: 11px; }',
            '.lr-interpretation { margin-bottom: 40px; font-size: 13px; }',
            '.lr-interpretation .lr-label { font-weight: 700; }',
            '.prt-sign { text-align: right; }',
            '.prt-sign .line { display: inline-block; width: 240px; border-top: 1px solid #111; padding-top: 4px; font-size: 12.5px; }',
            '@media print { .prt-toolbar { display: none; } @page { margin: 18mm 16mm; } body { margin: 0; } }',
        ]);
    }
}

if (!function_exists('hc_lab_result_report_body_html')) {
    /**
     * @param array $patient Real patients/{id} profile shape (hc_get_patient_profile()) — 'name', 'address', 'age', 'gender' are used.
     * @param array $labFields testName/resultGroups/interpretation — either a real completed lab_results/{id} doc's own fields (already in this exact shape), or a synthesized blank version of the same shape for a still-pending test.
     */
    function hc_lab_result_report_body_html(array $patient, array $labFields, string $performedBy): string
    {
        $testName = hc_lab_test_display_name($labFields['testName'] ?? 'Laboratory Test');
        $todayLabel = date('F j, Y');

        $rowsHtml = '';
        $rg = $labFields['resultGroups'] ?? [];
        $groups = [];
        if (!empty($rg['group1_name'])) {
            $groups[$rg['group1_name']] = $rg['group1_rows'] ?? [];
        }
        if (!empty($rg['group2_name'])) {
            $groups[$rg['group2_name']] = $rg['group2_rows'] ?? [];
        }
        // Optional 3rd group — only CBC (includes/medical_technician/cbc_form.php's
        // "Blood Typing" section, alongside its Hematology/Differential
        // Count groups) uses this; every other test only ever sets
        // group1/group2, so this is a no-op for them.
        if (!empty($rg['group3_name'])) {
            $groups[$rg['group3_name']] = $rg['group3_rows'] ?? [];
        }
        foreach ($groups as $groupName => $rows) {
            $rowsHtml .= '<tr class="lr-group-row"><td colspan="5">' . htmlspecialchars(strtoupper((string) $groupName)) . '</td></tr>';
            foreach ($rows as $row) {
                $rowsHtml .= '<tr>'
                    . '<td>' . htmlspecialchars((string) ($row['test'] ?? '')) . '</td>'
                    . '<td>' . htmlspecialchars((string) ($row['result'] ?? '')) . '</td>'
                    . '<td>' . htmlspecialchars((string) ($row['unit'] ?? '')) . '</td>'
                    . '<td>' . htmlspecialchars((string) ($row['range'] ?? '')) . '</td>'
                    . '<td>' . htmlspecialchars((string) ($row['interpretation'] ?? '')) . '</td>'
                    . '</tr>';
            }
        }
        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="5" style="text-align:center; color:#666;">No structured result values on this test.</td></tr>';
        }

        return '<div class="prt-header"><h1>LIPA CITY HEALTH OFFICE</h1><h2>LABORATORY DEPARTMENT</h2></div>'
            . '<table class="prt-info-table">'
            . '<tr><td style="width:65%;"><span>Name</span>' . htmlspecialchars($patient['name'] ?? '—') . '</td><td><span>Date</span>' . htmlspecialchars($todayLabel) . '</td></tr>'
            . '<tr><td><span>Address</span>' . htmlspecialchars((string) ($patient['address'] ?? '—')) . '</td><td><table style="width:100%; border-collapse:collapse;"><tr>'
            . '<td style="border:none; padding:0;"><span>Age</span>' . (int) ($patient['age'] ?? 0) . '</td>'
            . '<td style="border:none; padding:0;"><span>Sex</span>' . htmlspecialchars((string) ($patient['gender'] ?? '—')) . '</td>'
            . '</tr></table></td></tr>'
            . '</table>'
            . '<div class="prt-title"><div class="main">' . htmlspecialchars(strtoupper($testName)) . '</div></div>'
            . '<table class="lr-table"><thead><tr><th>Test</th><th>Result</th><th>Unit</th><th>Reference Range</th><th>Interpretation</th></tr></thead><tbody>' . $rowsHtml . '</tbody></table>'
            . '<div class="lr-interpretation"><span class="lr-label">Interpretation:</span> ' . htmlspecialchars((string) ($labFields['interpretation'] ?? '—')) . '</div>'
            . '<div class="prt-sign"><span class="line">' . htmlspecialchars($performedBy) . ' — Medical Technologist</span></div>';
    }
}

if (!function_exists('hc_lab_result_report_blank_fields')) {
    /**
     * The same $labFields shape hc_lab_result_report_body_html() expects,
     * ALL rows blank, no real patient behind it — for the 6 tests with
     * their own dedicated fixed field set (medical_technician/
     * print_gram_stain.php, print_cd4_count.php, print_urinalysis.php,
     * print_serology_rapid_test.php), and a single generic row (test
     * name only, same shape api/complete-lab-result.php already writes
     * for a real completed "other" test) for every OTHER real catalog
     * test — this facility has no real paper form on file for most of
     * these, so every test gets this same uniform template rather than
     * leaving most of the catalog with nothing at all. Used only for
     * api/preview-lab-template.php's Lab Test Catalog preview (letting
     * a medical technologist see the template's shape before any real
     * patient ever has one) — the 4 live print pages build their own
     * copy of this from each real patient's own saved values instead,
     * since "blank" there means "still pending", not "just previewing".
     *
     * @return ?array Null only for an empty test name.
     */
    function hc_lab_result_report_blank_fields(string $testName): ?array
    {
        if ($testName === '') {
            return null;
        }

        if ($testName === 'Gram Stain') {
            return ['testName' => $testName, 'interpretation' => '', 'resultGroups' => [
                'group1_name' => 'Gram Stain',
                'group1_rows' => [['test' => 'Gram Stain', 'result' => '', 'unit' => '', 'range' => '', 'interpretation' => '']],
                'group2_name' => null, 'group2_rows' => [],
            ]];
        }
        if ($testName === 'CD4') {
            require_once __DIR__ . '/../firestore/lab_results.php'; // hc_cd4_reference_range()
            $range = hc_cd4_reference_range();
            return ['testName' => $testName, 'interpretation' => '', 'resultGroups' => [
                'group1_name' => 'CD4 Count',
                'group1_rows' => [['test' => 'CD4 Count', 'result' => '', 'unit' => 'c/uL', 'range' => $range['label'], 'interpretation' => '']],
                'group2_name' => null, 'group2_rows' => [],
            ]];
        }
        if ($testName === 'Urinalysis') {
            require_once __DIR__ . '/../firestore/lab_results.php'; // hc_urinalysis_physical_fields()/hc_urinalysis_microscopic_fields()
            $blankRows = fn (array $fields) => array_map(fn ($label) => ['test' => $label, 'result' => '', 'unit' => '', 'range' => '', 'interpretation' => ''], array_values($fields));
            return ['testName' => $testName, 'interpretation' => '', 'resultGroups' => [
                'group1_name' => 'Physical Examination',
                'group1_rows' => $blankRows(hc_urinalysis_physical_fields()),
                'group2_name' => 'Microscopic Examination',
                'group2_rows' => $blankRows(hc_urinalysis_microscopic_fields()),
            ]];
        }
        if (in_array($testName, ['HBsAg', 'Syphilis', 'HIV'], true)) {
            require_once __DIR__ . '/../firestore/lab_results.php'; // hc_serology_rapid_test_catalog()
            $rows = array_map(fn ($label) => ['test' => $label, 'result' => '', 'unit' => '', 'range' => 'Non-Reactive', 'interpretation' => ''], array_values(hc_serology_rapid_test_catalog()));
            return ['testName' => 'Serology / Rapid Immunochromatographic Tests', 'interpretation' => '', 'resultGroups' => [
                'group1_name' => 'Serology',
                'group1_rows' => $rows,
                'group2_name' => null, 'group2_rows' => [],
            ]];
        }
        if ($testName === 'Dengue Test') {
            $rows = array_map(fn ($label) => ['test' => $label, 'result' => '', 'unit' => '', 'range' => 'Non-Reactive', 'interpretation' => ''], ['Dengue NS1 Ag', 'Dengue IgM', 'Dengue IgG']);
            return ['testName' => $testName, 'interpretation' => '', 'resultGroups' => [
                'group1_name' => 'Dengue Test',
                'group1_rows' => $rows,
                'group2_name' => null, 'group2_rows' => [],
            ]];
        }
        if ($testName === 'CBC (Complete Blood Count)') {
            require_once __DIR__ . '/../firestore/lab_results.php'; // hc_cbc_hematology_fields()/hc_cbc_differential_fields()
            $blankRows = fn (array $fields) => array_map(fn ($f) => ['test' => $f['label'], 'result' => '', 'unit' => $f['unit'], 'range' => $f['range'], 'interpretation' => ''], array_values($fields));
            return ['testName' => $testName, 'interpretation' => '', 'resultGroups' => [
                'group1_name' => 'Hematology',
                'group1_rows' => $blankRows(hc_cbc_hematology_fields()),
                'group2_name' => 'Differential Count',
                'group2_rows' => $blankRows(hc_cbc_differential_fields()),
                'group3_name' => 'Blood Typing',
                'group3_rows' => [
                    ['test' => 'Blood Type', 'result' => '', 'unit' => '', 'range' => '', 'interpretation' => ''],
                    ['test' => 'Rh', 'result' => '', 'unit' => '', 'range' => '', 'interpretation' => ''],
                ],
            ]];
        }

        // Every other real catalog test — this facility has no real
        // paper form for most of these, so a single blank row (its own
        // real display name, same shape a completed "other" test's
        // resultGroups already takes) stands in for one, rather than
        // leaving the catalog with nothing to preview/print at all.
        $displayName = hc_lab_test_display_name($testName);

        return ['testName' => $testName, 'interpretation' => '', 'resultGroups' => [
            'group1_name' => $displayName,
            'group1_rows' => [['test' => $displayName, 'result' => '', 'unit' => '', 'range' => '', 'interpretation' => '']],
            'group2_name' => null, 'group2_rows' => [],
        ]];
    }
}

if (!function_exists('hc_build_lab_result_report_html')) {
    /**
     * @param array $patient Real patients/{id} profile shape (hc_get_patient_profile()) — 'name', 'address', 'age', 'gender' are used.
     * @param array $labFields The merged lab_results/{id} fields JUST saved — the same array_merge($existing, [...]) array each complete-*.php endpoint already builds.
     */
    function hc_build_lab_result_report_html(array $patient, array $labFields, string $performedBy): string
    {
        $testName = hc_lab_test_display_name($labFields['testName'] ?? 'Laboratory Test');

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($testName) . ' — ' . htmlspecialchars($patient['name'] ?? '') . '</title>'
            . '<style>' . hc_lab_result_report_css() . '</style></head><body>'
            . hc_lab_result_report_body_html($patient, $labFields, $performedBy)
            . '</body></html>';
    }
}
