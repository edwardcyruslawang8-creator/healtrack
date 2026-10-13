<?php
/**
 * =====================================================================
 * Shared — GIS Mapping Barangay Shapes
 * ---------------------------------------------------------------------
 * Each barangay's CENTER POINT is real — geocoded from PhilAtlas
 * (philatlas.com), a real Philippine geographic reference site, one
 * barangay page at a time. The outline drawn around each center point
 * is still a plain schematic hexagon, NOT a real surveyed boundary —
 * no actual GIS boundary polygon data for these barangays exists
 * anywhere in this project, only their real centroid coordinates. That
 * still makes a real, meaningful difference from before: the old
 * version placed all 11 shapes on an arbitrary evenly-spaced ring
 * around Lipa's town-center point (and before that, on 7 entirely made-
 * up "Barangay 1"–"7" shapes over Quezon City) — so nothing was
 * actually near where these barangays sit relative to each other.
 * These real centers restore that — Marauoy/Balintawak sit near the
 * town center, San Lucas/Bugtong na Pulo sit well north, Talisay sits
 * east, etc., matching their genuine relative positions.
 *
 * A few of this app's own HC_COVERED_BARANGAYS spellings still don't
 * exactly match Lipa's real official barangay names (likely typos from
 * whoever first typed this list) — matched to their real counterpart
 * for geocoding purposes only, WITHOUT changing the spelling used
 * everywhere else in this app (that's a separate, bigger cleanup this
 * task didn't ask for). "Pulis" was one of these too until it was
 * corrected app-wide to its real spelling, "Pusil":
 *   "Buclanin"   -> real "Bulacnin"
 *   "Inosluban"  -> real "Inosloban"
 *   "Marawoy"    -> real "Marauoy"
 *   "Bugtong"    -> real "Bugtong na Pulo"
 *
 * Swap hc_gis_barangay_shapes() for a real boundary GeoJSON source
 * (e.g. PSA/PhilGIS) if one is ever obtained — nothing else in
 * api/gis-data.php or the map JS needs to change for that.
 * =====================================================================
 */

require_once __DIR__ . '/predictive_analytics_core.php'; // HC_COVERED_BARANGAYS

const HC_GIS_HEX_RADIUS_DEG = 0.0055; // ~600m — how big each schematic hexagon is drawn around its real center point

if (!function_exists('hc_gis_barangay_centers')) {
    /**
     * Real [lat, lng] centroid per barangay, geocoded from each
     * barangay's own philatlas.com/luzon/r04a/batangas/lipa/*.html page —
     * see this file's header comment for the name-spelling mapping.
     *
     * @return array<string, array{0: float, 1: float}> Keyed by this app's own HC_COVERED_BARANGAYS spelling.
     */
    function hc_gis_barangay_centers(): array
    {
        return [
            'Balintawak' => [13.9530, 121.1588],
            'Bugtong' => [14.0000, 121.1670],
            'Buclanin' => [13.9854, 121.1414],
            'Dagatan' => [13.9661, 121.1816],
            'Inosluban' => [13.9888, 121.1710],
            'Lumbang' => [13.9829, 121.1989],
            'Marawoy' => [13.9644, 121.1657],
            'Plaridel' => [13.9997, 121.1788],
            'Pusil' => [13.9827, 121.1552],
            'San Lucas' => [14.0131, 121.1823],
            'Talisay' => [13.9698, 121.2042],
        ];
    }
}

if (!function_exists('hc_gis_hexagon_ring')) {
    /** A closed 7-point (first == last) hexagon ring around one center point, in [lng, lat] pairs. */
    function hc_gis_hexagon_ring(float $centerLng, float $centerLat): array
    {
        $points = [];
        for ($i = 0; $i <= 6; $i++) {
            $angle = deg2rad($i * 60);
            $points[] = [
                round($centerLng + HC_GIS_HEX_RADIUS_DEG * cos($angle), 6),
                // Slight vertical compression — degrees of longitude cover less
                // real ground distance than degrees of latitude at this
                // latitude, so an uncompressed circle would look visibly
                // stretched east-west on the map.
                round($centerLat + HC_GIS_HEX_RADIUS_DEG * sin($angle) * 0.85, 6),
            ];
        }
        return $points;
    }
}

if (!function_exists('hc_gis_barangay_shapes')) {
    /**
     * @return array<string, array{polygon: array<int, array{0: float, 1: float}>, centroid: array{0: float, 1: float}}>
     *         Keyed by real barangay name (HC_COVERED_BARANGAYS).
     */
    function hc_gis_barangay_shapes(): array
    {
        $centers = hc_gis_barangay_centers();
        $shapes = [];

        foreach (HC_COVERED_BARANGAYS as $name) {
            [$lat, $lng] = $centers[$name] ?? [13.9411, 121.1622]; // Lipa town-center fallback — should never actually hit if the list above stays in sync with HC_COVERED_BARANGAYS

            $shapes[$name] = [
                'polygon' => hc_gis_hexagon_ring($lng, $lat),
                'centroid' => [$lng, $lat],
            ];
        }

        return $shapes;
    }
}
