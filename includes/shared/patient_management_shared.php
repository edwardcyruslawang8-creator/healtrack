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

        // -------------------------------------------------------------
        // Real patient list, scoped to the given facility — same full
        // scan + PHP filter includes/subadmin/patient_management_firestore.php's
        // own header comment explains (Firestore composite-index
        // limitation), just parameterized on $facility instead of
        // always the signed-in sub-admin's own.
        // -------------------------------------------------------------
        $allPatients = hc_get_all_patients_summary();
        if ($firestore->lastRequestFailed) {
            $patientsLoadFailed = true;
        }
        $allPatients = array_values(array_filter($allPatients, fn ($p) => $p['facility'] === $facility));

        $thisMonthPrefix = date('Y-m');
        $totalPatientsCount = count($allPatients);
        $newThisMonthCount = count(array_filter($allPatients, fn ($p) => str_starts_with($p['dateRegisteredRaw'], $thisMonthPrefix)));
        $averageAge = $totalPatientsCount > 0 ? (int) round(array_sum(array_column($allPatients, 'age')) / $totalPatientsCount) : 0;

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
            // Browsing view (no search/filter active) — Previous/Next only,
            // simple page-number math over the already facility-scoped
            // $allPatients.
            // -------------------------------------------------------------
            $patientsCurrentPage = max(1, (int) ($_GET['page'] ?? 1));
            $patientsTotalPages = max(1, (int) ceil($totalPatientsCount / $patientsPerPage));
            $patientsCurrentPage = min($patientsCurrentPage, $patientsTotalPages);

            $result['patients'] = array_slice($allPatients, ($patientsCurrentPage - 1) * $patientsPerPage, $patientsPerPage);
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
                if ($searchTerm !== '' && !hc_fuzzy_match($p['name'] . ' ' . $p['contact'], $searchTerm)) {
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

            // Centered pagination window (1 … current-1, current,
            // current+1 … last).
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
        }

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
