<?php
/**
 * =====================================================================
 * Shared data layer — Medicines & Vaccines
 * ---------------------------------------------------------------------
 * Pure Firestore-query functions for the real "medicines" collection —
 * see includes/firestore/patients.php's header comment for why this layer
 * exists and what it does/doesn't own. Medicines and vaccines are the
 * same collection, split by a "type" field ("medicine" | "vaccine");
 * every doc also carries its own "icon" (a Bootstrap Icons class) and
 * "color" (a short name, not a hex code) set at write time, resolved
 * here via hc_medicine_color_meta() rather than a fabricated
 * category-keyed lookup.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore

if (!function_exists('hc_medicine_color_meta')) {
    /** Short color name (as stored per-doc) -> badge/thumbnail bg+color hex pair. */
    function hc_medicine_color_meta(): array
    {
        return [
            'blue'   => ['bg' => '#E8F0FE', 'color' => '#1A73E8'],
            'green'  => ['bg' => '#E4F7EA', 'color' => '#1E8E3E'],
            'red'    => ['bg' => '#FCE4E4', 'color' => '#D93025'],
            'amber'  => ['bg' => '#FDF3DC', 'color' => '#B7791F'],
            'purple' => ['bg' => '#F1E8FD', 'color' => '#8B5CF6'],
            'cyan'   => ['bg' => '#E3F6FB', 'color' => '#12A6C6'],
        ];
    }
}

if (!function_exists('hc_medicine_status_meta')) {
    /** Status key (as stored per-doc) -> badge meta. */
    function hc_medicine_status_meta(): array
    {
        return [
            'available'   => ['label' => 'In Stock',     'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
            'low'         => ['label' => 'Low Stock',    'bg' => '#FDECDD', 'color' => '#E8710A'],
            'unavailable' => ['label' => 'Out of Stock', 'bg' => '#FCE4E4', 'color' => '#D93025'],
        ];
    }
}

if (!function_exists('hc_get_inventory_items')) {
    /**
     * Every medicines/{id} doc matching $type ("medicine" or "vaccine"),
     * newest-name-first (alphabetical) with human-formatted date strings.
     *
     * @return array<int, array{id: string, name: string, category: string, unit: string, form: string, desc: string, currentStock: int, minLevel: int, reorderLevel: int, expiryDate: string, expiryDateRaw: string, status: string, lastUpdatedRaw: string, lastUpdated: string, icon: string, colorMeta: array}>
     */
    function hc_get_inventory_items(string $type): array
    {
        global $firestore;

        $colorMeta = hc_medicine_color_meta();
        $items = [];
        foreach ($firestore->listDocuments('medicines') as $doc) {
            $f = $doc['fields'];
            if (($f['type'] ?? 'medicine') !== $type) {
                continue;
            }
            // Archived (api/archive-medicine.php) — excluded from the
            // default inventory list, same as every other read path over
            // this collection (firebase/firestore.php's getAllMedicines()/
            // getAllVaccines(), includes/shared/medicine_vaccine_inventory_shared.php).
            if ($f['archived'] ?? false) {
                continue;
            }

            $items[] = [
                'id' => $doc['id'],
                'name' => $f['name'] ?? '(unnamed)',
                'category' => $f['category'] ?? 'Uncategorized',
                'unit' => $f['unit'] ?? '—',
                'form' => $f['form'] ?? '—',
                'desc' => $f['desc'] ?? '',
                'currentStock' => (int) ($f['currentStock'] ?? 0),
                'minLevel' => (int) ($f['minLevel'] ?? 0),
                'reorderLevel' => (int) ($f['reorderLevel'] ?? 0),
                'expiryDateRaw' => $f['expiryDate'] ?? '',
                'expiryDate' => isset($f['expiryDate']) && $f['expiryDate'] !== '' ? strtoupper(date('M d, Y', strtotime($f['expiryDate']))) : '—',
                'status' => $f['status'] ?? 'available',
                'lastUpdatedRaw' => $f['lastUpdated'] ?? '',
                'lastUpdated' => isset($f['lastUpdated']) && $f['lastUpdated'] !== '' ? date('M j, Y g:i A', strtotime($f['lastUpdated'])) : '—',
                'icon' => $f['icon'] ?? ($type === 'vaccine' ? 'bi-droplet-half' : 'bi-capsule'),
                'colorMeta' => $colorMeta[$f['color'] ?? ''] ?? ['bg' => '#EEF0F3', 'color' => '#6B7280'],
            ];
        }

        // FEFO — First Expiry, First Out: soonest-expiring stock surfaces
        // first so staff naturally work through it before it expires.
        // Items with no recorded expiry sort last (unknown urgency, not
        // "expires immediately" — an empty string would otherwise sort
        // before every real ISO date under plain string comparison).
        // Same-expiry ties fall back to alphabetical by name.
        usort($items, function ($a, $b) {
            $expiryA = $a['expiryDateRaw'] !== '' ? $a['expiryDateRaw'] : '9999-99-99';
            $expiryB = $b['expiryDateRaw'] !== '' ? $b['expiryDateRaw'] : '9999-99-99';

            return $expiryA <=> $expiryB ?: strcasecmp($a['name'], $b['name']);
        });

        return $items;
    }
}

if (!function_exists('hc_find_inventory_item_by_name')) {
    /**
     * The one real medicines/{id} doc of $type whose name exactly
     * matches $name (case/whitespace-insensitive), or null if there
     * isn't one. Used by api/create-stock-request.php to resolve a
     * pharmacist's typed item name to a real inventory item AT REQUEST
     * TIME — a stock request can still name an item that doesn't exist
     * yet (see includes/pharmacist/new_stock_request_modal.php's header
     * comment on why that's deliberate), that item's request row just
     * carries no itemId and api/update-stock-request-status.php's
     * release step leaves inventory untouched for it.
     *
     * @return array{id: string, name: string, currentStock: int, minLevel: int}|null
     */
    function hc_find_inventory_item_by_name(string $type, string $name): ?array
    {
        $needle = trim($name);
        if ($needle === '') {
            return null;
        }

        foreach (hc_get_inventory_items($type) as $item) {
            if (strcasecmp(trim($item['name']), $needle) === 0) {
                return $item;
            }
        }

        return null;
    }
}

if (!function_exists('hc_inventory_status_for_stock')) {
    /** Real status derived from stock levels — computed server-side at write time, not trusted from the client. */
    function hc_inventory_status_for_stock(int $currentStock, int $minLevel): string
    {
        if ($currentStock <= 0) {
            return 'unavailable';
        }
        if ($currentStock <= $minLevel) {
            return 'low';
        }
        return 'available';
    }
}

// ---------------------------------------------------------------------
// Category-filter helpers — shared by admin/facility_analytics.php's
// Medicine/Vaccine Inventory cards (their own real category dropdown,
// includes/admin/facility_analytics_firestore.php) and
// api/get-inventory-card.php (that filter's AJAX-refresh endpoint), so
// both compute the exact same view instead of two copies that could
// drift. Pure transforms over hc_get_inventory_items()'s own output
// shape — nothing here reads Firestore itself.
// ---------------------------------------------------------------------

if (!function_exists('hc_medicine_categories')) {
    /** Every real, distinct category present in $items, alphabetical — the dropdown's real option list, not a hardcoded guess. */
    function hc_medicine_categories(array $items): array
    {
        $categories = array_values(array_unique(array_map(fn ($i) => $i['category'], $items)));
        sort($categories);

        return $categories;
    }
}

if (!function_exists('hc_medicine_filter_by_category')) {
    /** $items narrowed to $category — 'All' (or blank) returns $items unchanged. */
    function hc_medicine_filter_by_category(array $items, string $category): array
    {
        if ($category === '' || $category === 'All') {
            return $items;
        }

        return array_values(array_filter($items, fn ($i) => $i['category'] === $category));
    }
}

if (!function_exists('hc_medicine_status_pills')) {
    /** Total/In Stock/Low Stock/Out of Stock counts over $items — the same 4 numbers both inventory cards' pill row shows. */
    function hc_medicine_status_pills(array $items): array
    {
        return [
            'total' => count($items),
            'inStock' => count(array_filter($items, fn ($i) => $i['status'] === 'available')),
            'lowStock' => count(array_filter($items, fn ($i) => $i['status'] === 'low')),
            'outOfStock' => count(array_filter($items, fn ($i) => $i['status'] === 'unavailable')),
        ];
    }
}

if (!function_exists('hc_medicine_stock_distribution')) {
    /** $pills (hc_medicine_status_pills()'s own return shape) -> the 3-slice donut/legend data both inventory cards render. */
    function hc_medicine_stock_distribution(array $pills): array
    {
        $total = $pills['total'];

        return [
            ['label' => 'In Stock', 'pct' => $total > 0 ? round($pills['inStock'] / $total * 100, 1) : 0, 'color' => '#1E8E3E'],
            ['label' => 'Low Stock', 'pct' => $total > 0 ? round($pills['lowStock'] / $total * 100, 1) : 0, 'color' => '#E8710A'],
            ['label' => 'Out of Stock', 'pct' => $total > 0 ? round($pills['outOfStock'] / $total * 100, 1) : 0, 'color' => '#D93025'],
        ];
    }
}

if (!function_exists('hc_get_low_stock_notifications')) {
    /**
     * One real notification row per medicine/vaccine currently at
     * status "low" or "unavailable" (hc_inventory_status_for_stock()),
     * across both hc_get_inventory_items() types — for
     * pharmacist/notifications.php and the pharmacist unread badge
     * (see config/constants.php's hc_pharmacist_unread_notification_count()).
     * Out-of-stock items surface before low-stock ones (more urgent),
     * ties broken by most-recently-updated first.
     *
     * 'id' is crc32() of the real medicine doc id rather than the doc
     * id itself — the shared read-tracking session field
     * ($_SESSION['ntfReadIds'], api/mark-notifications-read.php) is an
     * array of ints (matching subadmin's small 1-8 seed ids), so this
     * keeps a real medicine doc's read state stable across requests
     * without needing a non-numeric id scheme change to that shared
     * mechanism.
     *
     * @return array<int, array{id: int, title: string, description: string, type: string, date: string, time: string, icon: string, iconBg: string, iconColor: string}>
     */
    function hc_get_low_stock_notifications(): array
    {
        $items = array_merge(hc_get_inventory_items('medicine'), hc_get_inventory_items('vaccine'));
        $items = array_values(array_filter($items, fn ($item) => $item['status'] !== 'available'));

        usort($items, function ($a, $b) {
            if ($a['status'] !== $b['status']) {
                return $a['status'] === 'unavailable' ? -1 : 1;
            }
            return strcmp($b['lastUpdated'], $a['lastUpdated']);
        });

        return array_map(function ($item) {
            $isOut = $item['status'] === 'unavailable';
            $updatedAt = $item['lastUpdatedRaw'] !== '' ? strtotime($item['lastUpdatedRaw']) : false;

            return [
                'id' => crc32($item['id']),
                'title' => $isOut ? 'Out of stock' : 'Low stock alert',
                'description' => $item['name'] . ' — ' . number_format($item['currentStock']) . ' ' . $item['unit'] . ' remaining (reorder level: ' . number_format($item['reorderLevel']) . ').',
                'type' => 'Inventory',
                'date' => $updatedAt !== false ? date('M j, Y', $updatedAt) : '—',
                'time' => $updatedAt !== false ? date('g:i A', $updatedAt) : '',
                'icon' => $item['icon'],
                'iconBg' => $isOut ? '#FCE4E4' : '#FDECDD',
                'iconColor' => $isOut ? '#D93025' : '#E8710A',
            ];
        }, $items);
    }
}
