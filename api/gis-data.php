<?php
/**
 * =====================================================================
 * GIS Mapping — Live GeoJSON (api/gis-data.php)
 * ---------------------------------------------------------------------
 * Fetched client-side by assets/js/gis.js (patient/gis.php's full map)
 * and assets/js/home-gis-preview.js (index.php's home preview widget) —
 * replaces the old static assets/data/barangays.geojson file, exactly
 * the swap patient/gis.php's own header comment already anticipated.
 * Public, no session/role gate — matches patient/gis.php itself, which
 * has none either.
 *
 * ?range=KEY selects the same real window the date-range dropdown
 * drives on the rest of the page (includes/public/gis-data.php) —
 * restricted to the same allowed set ('1w'/'3m'/'6m'/'1y' — see
 * hc_gis_date_range_spec()), not trusted as an arbitrary client-
 * supplied value. Defaults to '1w' (the same default that file uses)
 * so index.php's home preview widget — which never passes ?range= at
 * all — shows the same "most present" picture by default.
 *
 * ?classification=KEY mirrors the same real, server-side filter
 * (hc_disease_registry_classification_meta()'s keys, or 'all') the
 * dynamic classification dropdown drives — same allowlist convention,
 * not trusted as an arbitrary client-supplied value.
 * =====================================================================
 */

require_once __DIR__ . '/../includes/public/gis_data_firestore.php'; // hc_gis_disease_overview()
require_once __DIR__ . '/../includes/shared/clinical_classification.php'; // hc_disease_registry_classification_meta()

header('Content-Type: application/json');

$allowedRanges = ['1w', '3m', '6m', '1y'];
$range = (string) ($_GET['range'] ?? '1w');
if (!in_array($range, $allowedRanges, true)) {
    $range = '1w';
}

$classification = (string) ($_GET['classification'] ?? 'all');
if ($classification !== 'all' && !array_key_exists($classification, hc_disease_registry_classification_meta())) {
    $classification = 'all';
}

echo json_encode(hc_gis_disease_overview($range, $classification)['geojson']);
