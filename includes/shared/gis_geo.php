<?php
/**
 * =====================================================================
 * Shared — GIS Mapping Barangay Shapes
 * ---------------------------------------------------------------------
 * Each barangay's OUTLINE is its real official boundary — the 11
 * North District barangays' polygons from the PSA/NAMRIA 2023
 * barangay boundary set (phl_admbnda_adm4_psa_namria_20231106, as
 * mirrored by github.com/altcoder/philippines-psgc-shapefiles),
 * extracted at full resolution into
 * assets/data/lipa_north_barangays.geojson. Each feature carries its
 * PSGC code (e.g. PH0401014021 = Inosloban), official name, this app's
 * own spelling, and district. Only these 11 are in the file, so no
 * barangay outside the North District is ever drawn.
 *
 * Each barangay's CENTER POINT (where its marker/label sits) is
 * geocoded from PhilAtlas (philatlas.com) — every one of them was
 * checked to fall inside its own official polygon above.
 *
 * A few of this app's own HC_COVERED_BARANGAYS spellings don't exactly
 * match Lipa's official barangay names — the GeoJSON's "name" property
 * uses this app's spelling (so it joins straight onto case data keyed
 * by HC_COVERED_BARANGAYS) and "official_name" keeps the PSA one:
 *   "Inosluban"  -> official "Inosloban"
 *   "Marawoy"    -> official "Marauoy"
 *   "Bugtong"    -> official "Bugtong na Pulo"
 * =====================================================================
 */

require_once __DIR__ . '/predictive_analytics_core.php'; // HC_COVERED_BARANGAYS

const HC_GIS_BOUNDARIES_PATH = __DIR__ . '/../../assets/data/lipa_north_barangays.geojson';

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
            'Bulacnin' => [13.9854, 121.1414],
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

if (!function_exists('hc_gis_barangay_shapes')) {
    /**
     * Official boundary geometry per covered barangay (see this file's
     * header comment). A barangay missing from the boundary file is left
     * out rather than drawn with a made-up shape.
     *
     * @return array<string, array{geometry: array, centroid: array{0: float, 1: float}, officialName: string, psgc: string, district: string}>
     *         Keyed by this app's own barangay spelling (HC_COVERED_BARANGAYS).
     */
    function hc_gis_barangay_shapes(): array
    {
        static $shapes = null;
        if ($shapes !== null) {
            return $shapes;
        }

        $boundaries = [];
        $geojson = json_decode((string) @file_get_contents(HC_GIS_BOUNDARIES_PATH), true);
        foreach ($geojson['features'] ?? [] as $feature) {
            $boundaries[$feature['properties']['name']] = $feature;
        }

        $centers = hc_gis_barangay_centers();
        $shapes = [];
        foreach (HC_COVERED_BARANGAYS as $name) {
            if (!isset($boundaries[$name], $centers[$name])) {
                continue;
            }
            [$lat, $lng] = $centers[$name];
            $props = $boundaries[$name]['properties'];
            $shapes[$name] = [
                'geometry' => $boundaries[$name]['geometry'],
                'centroid' => [$lng, $lat],
                'officialName' => $props['official_name'],
                'psgc' => $props['psgc'],
                'district' => $props['district'],
            ];
        }

        return $shapes;
    }
}
