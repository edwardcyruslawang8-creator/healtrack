<?php
/**
 * =====================================================================
 * Quick Search — Medicines & Vaccines (api/quick-search-medicines.php)
 * ---------------------------------------------------------------------
 * Backs the live "Quick Search Results" dropdown under the Medicine &
 * Vaccine Inventory search boxes in subadmin/ and pharmacist/
 * (assets/js/quick-search.js — the exact same reusable component
 * api/quick-search-patients.php already uses, no changes needed
 * there). Same real "medicines" Firestore collection
 * (includes/firestore/medicines.php's hc_get_inventory_items()),
 * split by ?type=medicine|vaccine (mirrors the Medicines/Vaccines tabs
 * on those pages) since one page can have two independent quick-search
 * boxes.
 *
 * Uses the SAME hc_fuzzy_match() (includes/shared/fuzzy_search.php)
 * every other search box in this app already uses — this endpoint
 * adds a live dropdown UI on top, it does not add a different or new
 * matching algorithm.
 *
 * Each result's avatar reuses that medicine's own real icon/colorMeta
 * (hc_medicine_color_meta()) instead of a generic placeholder, since
 * the data already carries it.
 * =====================================================================
 */

session_start();

require_once __DIR__ . '/../config/firebase.php'; // $firestore
require_once __DIR__ . '/../includes/firestore/medicines.php';
require_once __DIR__ . '/../includes/shared/fuzzy_search.php'; // hc_fuzzy_match()

header('Content-Type: application/json');

if (!in_array($_SESSION['role'] ?? null, ['subadmin', 'pharmacist'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized.']);
    exit;
}

$query = trim($_GET['q'] ?? '');
$type = ($_GET['type'] ?? 'medicine') === 'vaccine' ? 'vaccine' : 'medicine';

if ($query === '') {
    echo json_encode(['results' => [], 'total' => 0]);
    exit;
}

$statusMeta = hc_medicine_status_meta();

$matches = array_values(array_filter(
    hc_get_inventory_items($type),
    fn ($m) => hc_fuzzy_match($m['name'] . ' ' . $m['category'], $query)
));

$results = array_map(function ($m) use ($statusMeta) {
    $status = $statusMeta[$m['status']] ?? $statusMeta['available'];

    return [
        'id' => $m['id'],
        'name' => $m['name'],
        'meta' => $m['category'] . ' &bull; ' . htmlspecialchars($status['label']),
        'icon' => $m['icon'],
        'iconBg' => $m['colorMeta']['bg'],
        'iconColor' => $m['colorMeta']['color'],
    ];
}, array_slice($matches, 0, 5));

echo json_encode(['results' => $results, 'total' => count($matches)]);
