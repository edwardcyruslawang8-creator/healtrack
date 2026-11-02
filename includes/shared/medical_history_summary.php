<?php
/**
 * =====================================================================
 * Medical History Summary — shared builder
 * ---------------------------------------------------------------------
 * Consolidates every patient's own medical history records across one
 * facility's patients (requirement 6.1.1): Chronic Conditions
 * ("medical_conditions"), Allergies ("allergies"), Past Surgeries
 * ("surgical_history") and Family History ("family_history") — the same
 * collections the per-patient Medical History / Allergies pages read
 * (includes/firestore/medical_records.php, allergies.php,
 * family_history.php), joined to "patients" by patientId.
 *
 * Counting rules (so no number is inflated):
 *   - Every count is UNIQUE PATIENTS, never records. A patient with the
 *     same condition entered twice counts once for it; a patient with
 *     three different conditions counts once under each of them.
 *   - Labels are grouped case/whitespace-insensitively ("Seafood" and
 *     "seafood" are one allergy), shown with their most common spelling.
 *     Clinically different wording is NOT merged — no guessing.
 *   - Percentages are of the patients in scope (after the patient
 *     filters), i.e. prevalence among those patients.
 *
 * Filters: barangay / age group / gender narrow the patients; the date
 * range applies to each record's own date — diagnosedOn (conditions),
 * addedOn (allergies), datePerformed or dateRecorded (surgeries). Family
 * history records carry no date, so the date range doesn't apply to
 * them. The category filter limits which sections are shown and which
 * records count toward "Patients with Recorded History".
 *
 * Used by subadmin/medical_history_reports.php (own facility),
 * admin/facility_analytics.php's Medical History tab (selected
 * facility) and the report export endpoint.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/date_range_filter.php';     // hc_resolve_date_range_filter(), hc_date_in_range()

if (!function_exists('hc_mh_categories')) {
    function hc_mh_categories(): array
    {
        return [
            'conditions' => ['label' => 'Chronic Conditions', 'collection' => 'medical_conditions', 'field' => 'label'],
            'allergies'  => ['label' => 'Allergies',          'collection' => 'allergies',          'field' => 'allergen'],
            'surgeries'  => ['label' => 'Past Surgeries & Procedures', 'collection' => 'surgical_history', 'field' => 'procedure'],
            'family'     => ['label' => 'Family History',     'collection' => 'family_history',     'field' => 'condition'],
        ];
    }
}

if (!function_exists('hc_mh_age_bucket')) {
    // Same buckets as Patient Reports' age group filter.
    function hc_mh_age_bucket(int $age): string
    {
        return match (true) {
            $age <= 17 => '0 - 17 years',
            $age <= 39 => '18 - 39 years',
            $age <= 59 => '40 - 59 years',
            default => '60 years and above',
        };
    }
}

if (!function_exists('hc_mh_record_date')) {
    function hc_mh_record_date(string $category, array $f): ?string
    {
        return match ($category) {
            'conditions' => $f['diagnosedOn'] ?? null,
            'allergies' => $f['addedOn'] ?? null,
            'surgeries' => $f['datePerformed'] ?? $f['dateRecorded'] ?? null,
            default => null, // family history has no date
        };
    }
}

if (!function_exists('hc_mh_filters_from_get')) {
    function hc_mh_filters_from_get(): array
    {
        $category = (string) ($_GET['historyCategory'] ?? '');

        return [
            'date' => hc_resolve_date_range_filter(),
            'barangay' => (string) ($_GET['barangay'] ?? ''),
            'ageGroup' => (string) ($_GET['ageGroup'] ?? ''),
            'gender' => (string) ($_GET['gender'] ?? ''),
            'category' => array_key_exists($category, hc_mh_categories()) ? $category : '',
        ];
    }
}

if (!function_exists('hc_mh_filter_options')) {
    /**
     * Dropdown option lists in includes/shared/simple_filter_dropdown.php's
     * shape (index 0 = "All ..."). The category list is keyed by its
     * query value, so callers render it with their own loop.
     */
    function hc_mh_filter_options(array $patientsById): array
    {
        $barangays = array_values(array_unique(array_filter(array_map(fn ($p) => trim((string) ($p['barangay'] ?? '')), $patientsById))));
        sort($barangays);

        return [
            'barangay' => array_merge(['All Barangays'], $barangays),
            'ageGroup' => ['All Age Groups', '0 - 17 years', '18 - 39 years', '40 - 59 years', '60 years and above'],
            'gender' => ['All Gender', 'Female', 'Male'],
            'category' => ['' => 'All Categories'] + array_map(fn ($c) => $c['label'], hc_mh_categories()),
        ];
    }
}

if (!function_exists('hc_mh_facility_patients')) {
    /** Every patient doc at $facilityName, keyed by doc id. */
    function hc_mh_facility_patients(string $facilityName): array
    {
        global $firestore;

        $patients = [];
        foreach ($firestore->listDocuments('patients') as $doc) {
            if (($doc['fields']['facility'] ?? '') === $facilityName) {
                $patients[$doc['id']] = $doc['fields'];
            }
        }

        return $patients;
    }
}

if (!function_exists('hc_medical_history_summary')) {
    function hc_medical_history_summary(array $patientsById, array $filters): array
    {
        global $firestore;

        $categories = hc_mh_categories();

        // Patients in scope after the patient-level filters.
        $scoped = [];
        foreach ($patientsById as $id => $p) {
            if ($filters['barangay'] !== '' && trim((string) ($p['barangay'] ?? '')) !== $filters['barangay']) {
                continue;
            }
            if ($filters['ageGroup'] !== '' && hc_mh_age_bucket((int) ($p['age'] ?? 0)) !== $filters['ageGroup']) {
                continue;
            }
            if ($filters['gender'] !== '' && ($p['gender'] ?? '') !== $filters['gender']) {
                continue;
            }
            $scoped[$id] = $p;
        }
        $scopeTotal = count($scoped);

        $result = [];
        $patientsWithAny = [];
        foreach ($categories as $key => $meta) {
            $groups = [];         // normalized label => ['spellings' => [label => n], 'patients' => [pid => true]]
            $patientsWith = [];   // pid => true
            $byBarangay = [];     // barangay => normalized label => [pid => true]
            $barangayPatients = []; // barangay => [pid => true]
            $relations = [];      // normalized label => relation => [pid => true]
            $records = 0;

            foreach ($firestore->listDocuments($meta['collection']) as $doc) {
                $f = $doc['fields'];
                $pid = (string) ($f['patientId'] ?? '');
                if (!isset($scoped[$pid])) {
                    continue;
                }
                if ($key !== 'family' && !hc_date_in_range(hc_mh_record_date($key, $f), $filters['date'])) {
                    continue;
                }
                $label = preg_replace('/\s+/', ' ', trim((string) ($f[$meta['field']] ?? '')));
                if ($label === '') {
                    continue;
                }
                $norm = mb_strtolower($label);

                $records++;
                $groups[$norm]['spellings'][$label] = ($groups[$norm]['spellings'][$label] ?? 0) + 1;
                $groups[$norm]['patients'][$pid] = true;
                $patientsWith[$pid] = true;

                $barangay = trim((string) ($scoped[$pid]['barangay'] ?? ''));
                if ($barangay !== '') {
                    $byBarangay[$barangay][$norm][$pid] = true;
                    $barangayPatients[$barangay][$pid] = true;
                }
                if ($key === 'family') {
                    $relation = trim((string) ($f['relation'] ?? '')) ?: 'Unspecified';
                    $relations[$norm][$relation][$pid] = true;
                }
            }

            $displayLabel = [];
            $items = [];
            foreach ($groups as $norm => $g) {
                arsort($g['spellings']);
                $displayLabel[$norm] = array_key_first($g['spellings']);
                $count = count($g['patients']);
                $item = [
                    'label' => $displayLabel[$norm],
                    'patients' => $count,
                    'pct' => $scopeTotal > 0 ? round($count / $scopeTotal * 100, 1) : 0,
                ];
                if ($key === 'family') {
                    $rel = array_map('count', $relations[$norm]);
                    arsort($rel);
                    $item['relations'] = $rel;
                }
                $items[$norm] = $item;
            }
            uasort($items, fn ($a, $b) => [$b['patients'], $a['label']] <=> [$a['patients'], $b['label']]);

            // Per-barangay breakdown: unique patients with any record in
            // this category, plus the top 5 labels overall as series.
            $topNorms = array_slice(array_keys($items), 0, 5);
            $barangayRows = [];
            foreach ($barangayPatients as $barangay => $pids) {
                $labelCounts = array_map('count', $byBarangay[$barangay]);
                arsort($labelCounts);
                $topNorm = array_key_first($labelCounts);
                $inBarangay = count(array_filter($scoped, fn ($p) => trim((string) ($p['barangay'] ?? '')) === $barangay));
                $barangayRows[] = [
                    'barangay' => $barangay,
                    'patients' => count($pids),
                    'pct' => $inBarangay > 0 ? round(count($pids) / $inBarangay * 100, 1) : 0,
                    'top' => $displayLabel[$topNorm],
                    'topPatients' => $labelCounts[$topNorm],
                    'series' => array_map(fn ($n) => count($byBarangay[$barangay][$n] ?? []), $topNorms),
                ];
            }
            usort($barangayRows, fn ($a, $b) => [$b['patients'], $a['barangay']] <=> [$a['patients'], $b['barangay']]);

            $result[$key] = [
                'label' => $meta['label'],
                'patients' => count($patientsWith),
                'records' => $records,
                'items' => array_values($items),
                'barangays' => $barangayRows,
                'seriesLabels' => array_map(fn ($n) => $displayLabel[$n], $topNorms),
            ];

            if ($filters['category'] === '' || $filters['category'] === $key) {
                $patientsWithAny += $patientsWith;
            }
        }

        return [
            'scopePatients' => $scopeTotal,
            'patientsWithHistory' => count($patientsWithAny),
            'categories' => $result,
            'visible' => $filters['category'] === '' ? array_keys($categories) : [$filters['category']],
        ];
    }
}

if (!function_exists('hc_mh_stat_cards')) {
    function hc_mh_stat_cards(array $mh): array
    {
        $topCondition = $mh['categories']['conditions']['items'][0] ?? null;
        $topAllergy = $mh['categories']['allergies']['items'][0] ?? null;
        $pct = fn (int $n) => $mh['scopePatients'] > 0 ? round($n / $mh['scopePatients'] * 100, 1) : 0;

        return [
            ['icon' => 'bi-journal-medical', 'iconBg' => '#E8F0FE', 'iconColor' => '#1A73E8', 'label' => 'Patients with Recorded History',
                'value' => number_format($mh['patientsWithHistory']), 'sub' => $pct($mh['patientsWithHistory']) . '% of ' . number_format($mh['scopePatients']) . ' patients'],
            ['icon' => 'bi-heart-pulse-fill', 'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Most Common Chronic Condition',
                'value' => $topCondition['label'] ?? '—', 'sub' => $topCondition ? number_format($topCondition['patients']) . ' patients (' . $topCondition['pct'] . '%)' : 'No conditions on record', 'text' => true],
            ['icon' => 'bi-exclamation-triangle-fill', 'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'label' => 'Most Common Allergy',
                'value' => $topAllergy['label'] ?? '—', 'sub' => $topAllergy ? number_format($topAllergy['patients']) . ' patients (' . $topAllergy['pct'] . '%)' : 'No allergies on record', 'text' => true],
            ['icon' => 'bi-bandaid-fill', 'iconBg' => '#F1E8FD', 'iconColor' => '#8B5CF6', 'label' => 'Patients with Past Surgeries',
                'value' => number_format($mh['categories']['surgeries']['patients']), 'sub' => $pct($mh['categories']['surgeries']['patients']) . '% of patients'],
        ];
    }
}

if (!function_exists('hc_mh_filter_summary')) {
    /** [label, value] pairs describing the active filters (for exports). */
    function hc_mh_filter_summary(array $filters): array
    {
        $categories = hc_mh_categories();

        return [
            ['Date Range', $filters['date']['label'] . ' (record dates; family history has no date)'],
            ['Barangay', $filters['barangay'] !== '' ? $filters['barangay'] : 'All Barangays'],
            ['Age Group', $filters['ageGroup'] !== '' ? $filters['ageGroup'] : 'All Age Groups'],
            ['Gender', $filters['gender'] !== '' ? $filters['gender'] : 'All Gender'],
            ['Category', $filters['category'] !== '' ? $categories[$filters['category']]['label'] : 'All Categories'],
        ];
    }
}
