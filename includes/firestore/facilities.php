<?php
/**
 * =====================================================================
 * Shared data layer — Facilities
 * ---------------------------------------------------------------------
 * Pure Firestore-query + geo helpers for the real "facilities"
 * collection (admin/facility_management.php's card grid,
 * api/create-facility.php) — see includes/firestore/patients.php's
 * header comment for why this layer exists and what it does/doesn't
 * own.
 *
 * The Lipa City boundary check reuses the real, already-on-disk
 * assets/data/lipa_city_boundary.geojson (OSM/Nominatim polygon) —
 * previously unused by any code path — rather than a center+radius
 * approximation, so "is this pin actually in Lipa City" is a real
 * point-in-polygon test against the city's actual shape.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

if (!function_exists('hc_get_all_facilities_summary')) {
    /**
     * id/name/lat/lng/isIntegrated for every real facility doc that has
     * a real pinned location — feeds includes/public/gis-data.php's
     * $facilityMarkers (-> window.GIS_FACILITIES on both
     * admin/gis_mapping.php and patient/gis.php). A facility added
     * before this feature existed (no lat/lng on its doc yet — see
     * admin/facility_management.php's own header comment on why there's
     * no edit feature to backfill one) is simply skipped here rather
     * than plotted at some default point.
     *
     * @return array<int, array{id: string, name: string, lat: float, lng: float, isIntegrated: bool}>
     */
    function hc_get_all_facilities_summary(): array
    {
        global $firestore;

        $facilities = [];
        foreach ($firestore->listDocuments('facilities') as $doc) {
            $f = $doc['fields'];
            if (!isset($f['lat'], $f['lng'])) {
                continue;
            }
            $facilities[] = [
                'id' => $doc['id'],
                'name' => $f['name'] ?? $doc['id'],
                'lat' => (float) $f['lat'],
                'lng' => (float) $f['lng'],
                'isIntegrated' => !empty($f['isIntegrated']),
            ];
        }

        return $facilities;
    }
}

if (!function_exists('hc_lipa_city_boundary_ring')) {
    /**
     * @internal The real Lipa City polygon's outer ring, as
     * [[lng, lat], ...] (GeoJSON's own coordinate order) — loaded and
     * decoded once per request. Falls back to an empty ring (so
     * hc_point_in_lipa_city() below fails closed, rejecting every
     * point) if the file is ever missing, rather than silently
     * accepting any location.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    function hc_lipa_city_boundary_ring(): array
    {
        static $ring = null;
        if ($ring !== null) {
            return $ring;
        }

        $path = __DIR__ . '/../../assets/data/lipa_city_boundary.geojson';
        $raw = is_file($path) ? file_get_contents($path) : false;
        $geojson = $raw !== false ? json_decode($raw, true) : null;

        $ring = $geojson['geometry']['coordinates'][0] ?? [];

        return $ring;
    }
}

if (!function_exists('hc_point_in_lipa_city')) {
    /**
     * Real point-in-polygon test (ray casting) against the actual Lipa
     * City boundary (hc_lipa_city_boundary_ring() above) — used by
     * api/create-facility.php to enforce, server-side, that a newly
     * pinned facility is really within Lipa City, regardless of what
     * assets/js/add-facility.js's own client-side copy of this same
     * check already did (never trusted alone).
     */
    function hc_point_in_lipa_city(float $lat, float $lng): bool
    {
        $ring = hc_lipa_city_boundary_ring();
        $count = count($ring);
        if ($count < 3) {
            return false;
        }

        $inside = false;
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $ring[$i][0];
            $yi = $ring[$i][1];
            $xj = $ring[$j][0];
            $yj = $ring[$j][1];

            $intersects = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi);
            if ($intersects) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}
