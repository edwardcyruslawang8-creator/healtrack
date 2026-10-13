<?php
/**
 * =====================================================================
 * Quick Search — Patient-Facing (api/quick-search-patient.php)
 * ---------------------------------------------------------------------
 * Backs the live "Quick Search Results" dropdown on the PUBLIC side of
 * the app (index.php's hero search, patient/medicine.php,
 * patient/announcements.php, patient/search.php — all load
 * assets/js/quick-search.js, the exact same reusable component the
 * staff-side pages already use). Deliberately NO session/role gate —
 * unlike every other quick-search-*.php endpoint, these pages are
 * public (index.php is explicitly not gated behind login; there's no
 * patient login flow yet), so this endpoint is too.
 *
 * ?scope=medicines|announcements|all (default "all") — "all" backs
 * index.php's hero search and patient/search.php's own bar (both want
 * results across every real category); "medicines"/"announcements"
 * scope a single page's search box to just its own real collection
 * (patient/medicine.php, patient/announcements.php).
 *
 * Fuzzy-matches ?q= via includes/shared/fuzzy_search.php's
 * hc_fuzzy_match() — the SAME matching includes/public/medicine-data.php
 * and includes/public/announcements-data.php's full-page filters
 * already use, and includes/public/search-data.php's real merged
 * results use too, so what this dropdown suggests always agrees with
 * what actually shows up on the full results page.
 *
 * No per-item detail page exists for either category on the patient
 * side, so every result omits a view target on purpose — each is
 * rendered as a fill-and-submit row by assets/js/quick-search.js
 * (same pattern already used for the pharmacist inventory quick
 * search, which has the same no-detail-page situation), landing the
 * visitor on the real, fully-filtered results page for that exact
 * item instead of nowhere.
 * =====================================================================
 */

require_once __DIR__ . '/../firebase/firestore.php'; // getAllMedicines(), getAllVaccines(), getAllAnnouncements()
require_once __DIR__ . '/../includes/shared/fuzzy_search.php'; // hc_fuzzy_match()

header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');
$scope = $_GET['scope'] ?? 'all';
if (!in_array($scope, ['medicines', 'announcements', 'all'], true)) {
    $scope = 'all';
}

if ($query === '') {
    echo json_encode(['results' => [], 'total' => 0]);
    exit;
}

$matches = [];

if ($scope === 'medicines' || $scope === 'all') {
    // "medicines" scope backs patient/medicine.php's own search box,
    // which is shared by BOTH its Medicines and Vaccines tabs — so this
    // covers both real collections, not just getAllMedicines().
    foreach (array_merge(getAllMedicines(), getAllVaccines()) as $m) {
        if (hc_fuzzy_match($m['name'] . ' ' . $m['desc'], $query)) {
            $matches[] = [
                'name' => $m['name'],
                'meta' => ($m['type'] === 'vaccine' ? 'Vaccine &bull; ' : 'Medicine &bull; ') . ($m['status'] === 'unavailable' ? 'Not Available' : 'Available'),
                'icon' => $m['icon'],
                'iconBg' => $m['status'] === 'unavailable' ? '#FCE4E4' : '#E4F7EA',
                'iconColor' => $m['status'] === 'unavailable' ? '#D93025' : '#1E8E3E',
                // Read by assets/js/medicine.js — patient/medicine.php's search
                // box is shared by both tabs, so clicking a suggestion needs
                // to land on the tab that actually has this exact item.
                'type' => $m['type'],
            ];
        }
    }
}

if ($scope === 'announcements' || $scope === 'all') {
    foreach (getAllAnnouncements() as $a) {
        if (hc_fuzzy_match($a['title'] . ' ' . $a['desc'], $query)) {
            $matches[] = [
                'name' => $a['title'],
                'meta' => ($scope === 'all' ? 'Announcement &bull; ' : '') . $a['date'],
                'icon' => $a['icon'],
                'iconBg' => '#E8F0FE',
                'iconColor' => '#1A73E8',
            ];
        }
    }
}

echo json_encode([
    'results' => array_slice($matches, 0, 5),
    'total' => count($matches),
]);
