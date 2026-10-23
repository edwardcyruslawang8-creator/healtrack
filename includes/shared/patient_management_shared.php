<?php
/**
 * =====================================================================
 * Patient Management — shared per-facility computation
 * ---------------------------------------------------------------------
 * hc_compute_patient_management_for_facility($facility) is the exact
 * same stat-card/search/gender-age-filter/pagination logic
 * includes/subadmin/patient_management_firestore.php used to compute
 * inline, extracted here so includes/admin/patient_management_firestore.php
 * (which lets a System Administrator pick ANY facility, not just their
 * own) can reuse it instead of duplicating ~90 lines of the same
 * full-scan-and-filter logic a second time. Both callers do
 * `extract(hc_compute_patient_management_for_facility($facility))`
 * right after requiring this file — extract() is otherwise avoided in
 * this codebase, but is safe here since the array only ever holds keys
 * this function itself defines (never user input), and it's what lets
 * both subadmin/patient_management.php and admin/patient_management.php
 * keep reading the exact same $statCards/$patients/$patientsCurrentPage/...
 * variable names their templates already used before this refactor.
 *
 * Returns everything BUT $facilityOptions/$dashboardDateLabel/$adminProfile
 * — those differ per caller (a sub-admin's is fixed to their own real
 * facility; admin's is chosen from a dropdown) so each caller still
 * builds those itself before calling this.
 * =====================================================================
 */

require_once __DIR__ . '/../firestore/patients.php'; // hc_get_all_patients_summary(), hc_patient_age_group()
require_once __DIR__ . '/fuzzy_search.php'; // hc_fuzzy_match()

if (!function_exists('hc_compute_patient_management_for_facility')) {
    function hc_compute_patient_management_for_facility(string $facility): array
    {
        global $firestore;

        $genderOptions = ['All Genders', 'Male', 'Female'];
        $ageGroupOptions = ['All Age Groups', '0-12', '13-17', '18-30', '31-45', '46-60', '60+'];

        $searchTerm = trim($_GET['q'] ?? '');
        $genderFilter = $_GET['gender'] ?? 'All Genders';
        $ageGroupFilter = $_GET['ageGroup'] ?? 'All Age Groups';
        $patientsUsingRealQuery = $searchTerm === '' && $genderFilter === 'All Genders' && $ageGroupFilter === 'All Age Groups';

        $patientsPerPage = 8;
        $patientsLoadFailed = false;

        $thisMonthPrefix = date('Y-m');
        $facilityFilter = $firestore->fieldEqualsFilter('facility', $facility);
        // "WHERE facility == X ORDER BY dateRegistered DESC" — served by
        // the composite index patients (facility ASC, dateRegistered DESC),
        // created manually in the Firebase console (this app's service
        // account can't deploy indexes itself).
        $newestFirstQuery = static fn (array $extraFilters = []) => [
            'from' => [['collectionId' => 'patients']],
            'where' => $extraFilters === []
                ? $facilityFilter
                : ['compositeFilter' => ['op' => 'AND', 'filters' => array_merge([$facilityFilter], $extraFilters)]],
            'orderBy' => [['field' => ['fieldPath' => 'dateRegistered'], 'direction' => 'DESCENDING']],
        ];

        if ($patientsUsingRealQuery) {
            // ---------------------------------------------------------
            // Browsing view — server-side counts plus one page of rows,
            // instead of reading every patient.
            // ---------------------------------------------------------
            $allPatients = null;
            $totalPatientsCount = $firestore->countDocuments('patients', [$facilityFilter]);
            $patientsLoadFailed = $patientsLoadFailed || $firestore->lastRequestFailed;
            $totalPatientsCount ??= 0;

            // Ranged on the same indexed field + order, so it needs no
            // second index; only this month's (few) docs are read.
            $newThisMonthCount = count($firestore->queryDocuments($newestFirstQuery([
                $firestore->fieldFilter('dateRegistered', 'GREATER_THAN_OR_EQUAL', $thisMonthPrefix),
            ])));
            $patientsLoadFailed = $patientsLoadFailed || $firestore->lastRequestFailed;

            // Needs its own composite index, patients (facility ASC, age
            // ASC); without it this fails (logged) and the card shows "—"
            // rather than a misleading 0, without failing the rest of the page.
            $averageAgeRaw = $firestore->averageField('patients', 'age', [$facilityFilter]);
            $averageAge = $averageAgeRaw === null ? null : (int) round($averageAgeRaw);
        } else {
            // ---------------------------------------------------------
            // Search/filter active — fuzzy name/contact/address search
            // can't run server-side, so read just this facility's
            // patients (single-field filter, no index needed) and filter
            // in PHP below.
            // ---------------------------------------------------------
            $allPatients = array_map('hc_map_patient_summary_doc', $firestore->listDocumentsWhere('patients', 'facility', $facility));
            $patientsLoadFailed = $firestore->lastRequestFailed;
            usort($allPatients, fn ($a, $b) => strcmp($b['dateRegisteredRaw'], $a['dateRegisteredRaw']));

            $totalPatientsCount = count($allPatients);
            $newThisMonthCount = count(array_filter($allPatients, fn ($p) => str_starts_with($p['dateRegisteredRaw'], $thisMonthPrefix)));
            $averageAge = $totalPatientsCount > 0 ? (int) round(array_sum(array_column($allPatients, 'age')) / $totalPatientsCount) : 0;
        }

        $statCards = [
            ['icon' => 'bi-people-fill',          'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Total Patients',                   'value' => $totalPatientsCount, 'staticSub' => 'All registered patients'],
            ['icon' => 'bi-person-plus-fill',     'iconBg' => '#FDECDD', 'iconColor' => '#E8710A', 'label' => 'New Patients (This Month)',        'value' => $newThisMonthCount,  'staticSub' => date('F Y')],
            ['icon' => 'bi-calendar2-check-fill', 'iconBg' => '#EFE7FB', 'iconColor' => '#7C3AED', 'label' => 'Patients Registered (This Month)', 'value' => $newThisMonthCount,  'staticSub' => date('F Y')],
            ['icon' => 'bi-person-fill',          'iconBg' => '#FCE4E4', 'iconColor' => '#D93025', 'label' => 'Average Age',                      'value' => $averageAge,         'staticSub' => 'Years Old'],
        ];

        $result = [
            'genderOptions' => $genderOptions,
            'ageGroupOptions' => $ageGroupOptions,
            'searchTerm' => $searchTerm,
            'genderFilter' => $genderFilter,
            'ageGroupFilter' => $ageGroupFilter,
            'patientsUsingRealQuery' => $patientsUsingRealQuery,
            'patientsPerPage' => $patientsPerPage,
            'patientsLoadFailed' => $patientsLoadFailed,
            'statCards' => $statCards,
            'totalPatientsCount' => $totalPatientsCount,
        ];

        if ($patientsUsingRealQuery) {
            // -------------------------------------------------------------
            // Browsing view (no search/filter active) — Previous/Next only;
            // just this page's rows, via offset/limit on the indexed query.
            // -------------------------------------------------------------
            $patientsCurrentPage = max(1, (int) ($_GET['page'] ?? 1));
            $patientsTotalPages = max(1, (int) ceil($totalPatientsCount / $patientsPerPage));
            $patientsCurrentPage = min($patientsCurrentPage, $patientsTotalPages);

            $pageQuery = $newestFirstQuery() + ['limit' => $patientsPerPage];
            if ($patientsCurrentPage > 1) {
                $pageQuery['offset'] = ($patientsCurrentPage - 1) * $patientsPerPage;
            }
            $result['patients'] = array_map('hc_map_patient_summary_doc', $firestore->queryDocuments($pageQuery));
            if ($firestore->lastRequestFailed) {
                $result['patientsLoadFailed'] = true;
            }
            $result['patientsTotal'] = $totalPatientsCount;
            $result['patientsCurrentPage'] = $patientsCurrentPage;
            $result['patientsTotalPages'] = $patientsTotalPages;
            $result['patientsHasPrev'] = $patientsCurrentPage > 1;
            $result['patientsHasNext'] = $patientsCurrentPage < $patientsTotalPages;
            $result['patientsPrevQuery'] = $result['patientsHasPrev'] ? ($patientsCurrentPage - 1 > 1 ? http_build_query(['page' => $patientsCurrentPage - 1]) : '') : null;
            $result['patientsNextQuery'] = $result['patientsHasNext'] ? http_build_query(['page' => $patientsCurrentPage + 1]) : null;
        } else {
            // -------------------------------------------------------------
            // Search/filter active — same facility-scoped $allPatients,
            // further narrowed by the search box and gender/age-group
            // filters.
            // -------------------------------------------------------------
            $filteredPatients = array_values(array_filter($allPatients, function ($p) use ($searchTerm, $genderFilter, $ageGroupFilter) {
                if ($searchTerm !== '' && !hc_fuzzy_match($p['name'] . ' ' . $p['contact'] . ' ' . $p['address'], $searchTerm)) {
                    return false;
                }
                if ($genderFilter !== 'All Genders' && $p['gender'] !== $genderFilter) {
                    return false;
                }
                if ($ageGroupFilter !== 'All Age Groups' && hc_patient_age_group($p['age']) !== $ageGroupFilter) {
                    return false;
                }
                return true;
            }));

            $patientsTotal = count($filteredPatients);
            $patientsCurrentPage = max(1, (int) ($_GET['page'] ?? 1));
            $patientsTotalPages = max(1, (int) ceil($patientsTotal / $patientsPerPage));
            $patientsCurrentPage = min($patientsCurrentPage, $patientsTotalPages);

            $result['patients'] = array_slice($filteredPatients, ($patientsCurrentPage - 1) * $patientsPerPage, $patientsPerPage);
            $result['patientsTotal'] = $patientsTotal;
            $result['patientsCurrentPage'] = $patientsCurrentPage;
            $result['patientsTotalPages'] = $patientsTotalPages;
        }

        // Centered pagination window (1 … current-1, current, current+1 …
        // last) — built for both views: the sub-admin/admin templates use
        // it only while filtering, the doctor/medical-staff ones always.
        $patientsCurrentPage = $result['patientsCurrentPage'];
        $patientsTotalPages = $result['patientsTotalPages'];
        $patientsPaginationWindow = [];
        if ($patientsTotalPages <= 7) {
            for ($p = 1; $p <= $patientsTotalPages; $p++) {
                $patientsPaginationWindow[] = $p;
            }
        } else {
            $patientsPaginationWindow[] = 1;
            $windowStart = max(2, $patientsCurrentPage - 1);
            $windowEnd = min($patientsTotalPages - 1, $patientsCurrentPage + 1);
            if ($windowStart > 2) {
                $patientsPaginationWindow[] = '...';
            }
            for ($p = $windowStart; $p <= $windowEnd; $p++) {
                $patientsPaginationWindow[] = $p;
            }
            if ($windowEnd < $patientsTotalPages - 1) {
                $patientsPaginationWindow[] = '...';
            }
            $patientsPaginationWindow[] = $patientsTotalPages;
        }
        $result['patientsPaginationWindow'] = $patientsPaginationWindow;

        return $result;
    }
}

if (!function_exists('hc_pm_query')) {
    /**
     * Builds a ?query string carrying every current filter except the
     * ones in $overrides — used by the pagination/filter links so
     * Next/Previous/a page number/a facility pick never drops the
     * others. 'facility' is only ever present for admin/patient_management.php
     * (subadmin's own page never puts it in the URL) — harmless either
     * way since an absent/empty value is filtered out below.
     */
    function hc_pm_query(array $overrides = []): string
    {
        $params = array_merge([
            'q' => $_GET['q'] ?? '',
            'gender' => $_GET['gender'] ?? 'All Genders',
            'ageGroup' => $_GET['ageGroup'] ?? 'All Age Groups',
            'facility' => $_GET['facility'] ?? '',
            'page' => $_GET['page'] ?? 1,
        ], $overrides);

        return http_build_query(array_filter($params, fn ($v) => $v !== '' && $v !== null && $v !== 'All Genders' && $v !== 'All Age Groups'));
    }
}
