<?php
/**
 * =====================================================================
 * Laboratory & X-ray tab filters (shared — doctor / medical-staff /
 * subadmin patient_laboratory_xray.php)
 * ---------------------------------------------------------------------
 * The Test Type / Body Part dropdown, the Status dropdown and the search
 * box of each section, applied server-side on top of that section's
 * existing date range. Query params:
 *   Laboratory: labType, labStatus, labQ   (paging: labPage)
 *   X-ray:      xrayPart, xrayStatus, xrayQ (paging: xrayPage)
 * The page's own assets/js/plx-filters.js writes them to the URL.
 *
 * The type/body-part choices are built from the patient's REAL results
 * (every distinct value), so a choice can never match nothing — the
 * old fixed lists ("Hematology", "Chemistry", "Microbiology"…, "Knee
 * (Right)"…) didn't line up with the stored values at all.
 * =====================================================================
 */

if (!function_exists('hc_plx_filter_rows')) {
    /**
     * @param array  $rows       rows already narrowed by the section's date range
     * @param array  $allRows    every row for the patient (for the dropdown choices)
     * @param string $typeKey    row key of the dropdown field ('testType' / 'bodyPart')
     * @param string $allLabel   the "everything" choice ('All Test Types' / 'All Body Part')
     * @param string $typeParam  query param for the type dropdown
     * @param string $statusParam query param for the status dropdown
     * @param string $searchParam query param for the search box
     * @param string[] $searchKeys row keys the search box looks in
     * @return array{0: array, 1: string[], 2: string, 3: string, 4: string}
     *         [filtered rows, type choices, chosen type, chosen status label, search text]
     */
    function hc_plx_filter_rows(array $rows, array $allRows, string $typeKey, string $allLabel, string $typeParam, string $statusParam, string $searchParam, array $searchKeys): array
    {
        $values = [];
        foreach ($allRows as $r) {
            $v = trim((string) ($r[$typeKey] ?? ''));
            if ($v !== '' && $v !== '—') {
                $values[strtolower($v)] = $v;
            }
        }
        natcasesort($values);
        $typeOptions = array_merge([$allLabel], array_values($values));

        $type = (string) ($_GET[$typeParam] ?? $allLabel);
        if (!in_array($type, $typeOptions, true)) {
            $type = $allLabel;
        }
        $status = (string) ($_GET[$statusParam] ?? 'All Status');
        if (!in_array($status, ['All Status', 'Completed', 'Pending'], true)) {
            $status = 'All Status';
        }
        $search = trim((string) ($_GET[$searchParam] ?? ''));

        $filtered = array_values(array_filter($rows, function ($r) use ($type, $allLabel, $typeKey, $status, $search, $searchKeys) {
            if ($type !== $allLabel && strcasecmp(trim((string) ($r[$typeKey] ?? '')), $type) !== 0) {
                return false;
            }
            if ($status !== 'All Status' && strcasecmp((string) ($r['status'] ?? ''), $status) !== 0) {
                return false;
            }
            if ($search !== '') {
                $haystack = '';
                foreach ($searchKeys as $k) {
                    $haystack .= ' ' . (string) ($r[$k] ?? '');
                }
                if (stripos($haystack, $search) === false) {
                    return false;
                }
            }
            return true;
        }));

        return [$filtered, $typeOptions, $type, $status, $search];
    }
}
