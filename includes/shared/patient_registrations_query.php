<?php
/**
 * =====================================================================
 * Shared — Patient Registrations Query (New Patient Registrations card)
 * ---------------------------------------------------------------------
 * Real search/filter/sort/pagination over one real facility's own
 * "patients" collection — used by subadmin/patient_reports.php's own
 * default render AND api/get-patient-registrations.php (its date-range/
 * search/barangay/sort/pagination AJAX refresh), so the two never
 * drift. Independent of this page's own page-wide filter toolbar (date
 * range / age group / gender / barangay) on purpose — this card is a
 * self-contained "find a specific patient" tool, same spirit as
 * subadmin/patient_management.php's own independent search, not a
 * sub-view of whatever the page-wide filters happen to be set to.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/fuzzy_search.php'; // hc_fuzzy_match()

if (!function_exists('hc_query_patient_registrations')) {
    /**
     * @param array{q?: string, barangay?: string, days?: ?int, sort?: string, dir?: string, page?: int} $filters
     * @return array{items: array<int, array{id: string, name: string, age: int, gender: string, barangay: string, dateRegistered: string}>, total: int, totalPages: int, page: int}
     */
    function hc_query_patient_registrations(string $facilityName, array $filters): array
    {
        global $firestore;

        $q = trim((string) ($filters['q'] ?? ''));
        $barangay = (string) ($filters['barangay'] ?? '');
        $days = $filters['days'] ?? null;
        $sort = (string) ($filters['sort'] ?? 'dateRegistered');
        $dir = (string) ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = 5;

        if (!in_array($sort, ['name', 'age', 'gender', 'barangay', 'dateRegistered'], true)) {
            $sort = 'dateRegistered';
        }

        $cutoff = $days !== null ? date('Y-m-d', strtotime("-{$days} days")) : null;

        $patients = [];
        foreach ($firestore->listDocuments('patients') as $doc) {
            $f = $doc['fields'];
            if (($f['facility'] ?? '') !== $facilityName) {
                continue;
            }
            if ($barangay !== '' && ($f['barangay'] ?? '') !== $barangay) {
                continue;
            }
            $dateRegisteredRaw = (string) ($f['dateRegistered'] ?? '');
            if ($cutoff !== null && ($dateRegisteredRaw === '' || substr($dateRegisteredRaw, 0, 10) < $cutoff)) {
                continue;
            }
            if ($q !== '' && !hc_fuzzy_match((string) ($f['name'] ?? ''), $q)) {
                continue;
            }

            $patients[] = [
                'id' => $doc['id'],
                'name' => $f['name'] ?? '(no name)',
                'age' => (int) ($f['age'] ?? 0),
                'gender' => $f['gender'] ?? '—',
                'barangay' => $f['barangay'] ?? '—',
                'dateRegisteredRaw' => $dateRegisteredRaw,
                'dateRegistered' => $dateRegisteredRaw !== '' ? date('M j, Y', strtotime($dateRegisteredRaw)) : '—',
            ];
        }

        usort($patients, function ($a, $b) use ($sort, $dir) {
            $av = $sort === 'dateRegistered' ? $a['dateRegisteredRaw'] : $a[$sort];
            $bv = $sort === 'dateRegistered' ? $b['dateRegisteredRaw'] : $b[$sort];
            $cmp = (is_int($av) && is_int($bv)) ? ($av <=> $bv) : strcasecmp((string) $av, (string) $bv);

            return $dir === 'asc' ? $cmp : -$cmp;
        });

        $total = count($patients);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $items = array_map(
            fn ($p) => ['id' => $p['id'], 'name' => $p['name'], 'age' => $p['age'], 'gender' => $p['gender'], 'barangay' => $p['barangay'], 'dateRegistered' => $p['dateRegistered']],
            array_slice($patients, ($page - 1) * $perPage, $perPage)
        );

        return ['items' => $items, 'total' => $total, 'totalPages' => $totalPages, 'page' => $page];
    }
}
