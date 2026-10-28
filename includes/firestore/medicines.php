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
            'overstocked' => ['label' => 'Overstocked',  'bg' => '#F1E8FD', 'color' => '#8B5CF6'],
        ];
    }
}

if (!function_exists('hc_inventory_expiry_status')) {
    /**
     * 'expired' | 'soon' | 'ok' for a real expiryDate raw string ('Y-m-d',
     * as hc_get_inventory_items() stores it in 'expiryDateRaw') — blank
     * or unparseable means 'ok' (nothing real to warn about). Same
     * 30-day "soon" window hc_get_medicine_expiry_notifications() and
     * includes/subadmin/inventory_reports_firestore.php's own "Expiring
     * Soon" stat/table already use, so the Medicine & Vaccine Inventory
     * table's per-row badge (see hc_medicine_expiry_badge_meta()) never
     * disagrees with either of those about what counts as "soon".
     */
    function hc_inventory_expiry_status(string $expiryDateRaw, int $daysSoon = 30): string
    {
        if ($expiryDateRaw === '') {
            return 'ok';
        }
        $expiry = strtotime($expiryDateRaw);
        if ($expiry === false) {
            return 'ok';
        }
        $today = strtotime(date('Y-m-d'));
        if ($expiry < $today) {
            return 'expired';
        }
        if ($expiry <= strtotime("+{$daysSoon} days", $today)) {
            return 'soon';
        }

        return 'ok';
    }
}

if (!function_exists('hc_medicine_expiry_badge_meta')) {
    /**
     * hc_inventory_expiry_status() key -> badge meta, same {label, bg,
     * color} shape hc_medicine_status_meta() already uses for the Status
     * column — rendered as a second badge next to the Expiry Date column
     * on the Medicine & Vaccine Inventory table whenever a row's real
     * expiry isn't 'ok'. No 'ok' entry on purpose — a normal, far-off
     * expiry date has nothing to flag.
     */
    function hc_medicine_expiry_badge_meta(): array
    {
        return [
            'expired' => ['label' => 'Expired',       'bg' => '#FCE4E4', 'color' => '#D93025'],
            'soon'    => ['label' => 'Expiring Soon',  'bg' => '#FDF3DC', 'color' => '#B7791F'],
        ];
    }
}

if (!function_exists('hc_query_inventory_items')) {
    /**
     * @internal Shared by hc_get_inventory_items()/hc_get_archived_inventory_items()
     * below — every medicines/{id} doc matching $type ("medicine" or
     * "vaccine") AND whose real "archived" flag matches $archived,
     * newest-name-first (alphabetical) with human-formatted date
     * strings.
     *
     * @return array<int, array{id: string, name: string, category: string, unit: string, form: string, desc: string, currentStock: int, minLevel: int, reorderLevel: int, maxLevel: int, expiryDate: string, expiryDateRaw: string, expiryStatus: string, status: string, lastUpdatedRaw: string, lastUpdated: string, icon: string, colorMeta: array}>
     */
    function hc_query_inventory_items(string $type, bool $archived): array
    {
        global $firestore;

        $colorMeta = hc_medicine_color_meta();
        $items = [];
        foreach ($firestore->listDocuments('medicines') as $doc) {
            $f = $doc['fields'];
            if (($f['type'] ?? 'medicine') !== $type) {
                continue;
            }
            if (($f['archived'] ?? false) !== $archived) {
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
                'maxLevel' => (int) ($f['maxLevel'] ?? 0),
                'expiryDateRaw' => $f['expiryDate'] ?? '',
                'expiryDate' => isset($f['expiryDate']) && $f['expiryDate'] !== '' ? strtoupper(date('M d, Y', strtotime($f['expiryDate']))) : '—',
                'expiryStatus' => hc_inventory_expiry_status($f['expiryDate'] ?? ''),
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

if (!function_exists('hc_get_inventory_items')) {
    /** Every NON-archived medicines/{id} doc matching $type — see hc_query_inventory_items(). */
    function hc_get_inventory_items(string $type): array
    {
        return hc_query_inventory_items($type, false);
    }
}

if (!function_exists('hc_get_archived_inventory_items')) {
    /**
     * Every ARCHIVED medicines/{id} doc matching $type — the mirror
     * image of hc_get_inventory_items(), which excludes archived items
     * from every other read path over this collection
     * (api/archive-medicine.php's own header comment: "an archived
     * item just stops appearing anywhere rather than being destroyed").
     * This is the one place that's no longer quite true —
     * pharmacist/archived_items.php uses this to actually show them
     * again, with a real Unarchive action
     * (api/unarchive-medicine.php) to bring one back.
     */
    function hc_get_archived_inventory_items(string $type): array
    {
        return hc_query_inventory_items($type, true);
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
    /**
     * Real status derived from stock levels — computed server-side at
     * write time, not trusted from the client. $maxLevel is optional
     * (0 = "no ceiling set", the default for every item created before
     * this field existed) — only flags 'overstocked' when a real
     * ceiling was set AND stock has actually exceeded it, so an item
     * with no ceiling is never flagged regardless of how much stock it
     * has.
     */
    function hc_inventory_status_for_stock(int $currentStock, int $minLevel, int $maxLevel = 0): string
    {
        if ($currentStock <= 0) {
            return 'unavailable';
        }
        if ($currentStock <= $minLevel) {
            return 'low';
        }
        if ($maxLevel > 0 && $currentStock > $maxLevel) {
            return 'overstocked';
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
    /** Total/In Stock/Low Stock/Out of Stock/Overstocked counts over $items — the same 5 numbers both inventory cards' pill row shows. */
    function hc_medicine_status_pills(array $items): array
    {
        return [
            'total' => count($items),
            'inStock' => count(array_filter($items, fn ($i) => $i['status'] === 'available')),
            'lowStock' => count(array_filter($items, fn ($i) => $i['status'] === 'low')),
            'outOfStock' => count(array_filter($items, fn ($i) => $i['status'] === 'unavailable')),
            'overstocked' => count(array_filter($items, fn ($i) => $i['status'] === 'overstocked')),
        ];
    }
}

if (!function_exists('hc_medicine_stock_distribution')) {
    /** $pills (hc_medicine_status_pills()'s own return shape) -> the 4-slice donut/legend data both inventory cards render. */
    function hc_medicine_stock_distribution(array $pills): array
    {
        $total = $pills['total'];

        return [
            ['label' => 'In Stock', 'pct' => $total > 0 ? round($pills['inStock'] / $total * 100, 1) : 0, 'color' => '#1E8E3E'],
            ['label' => 'Low Stock', 'pct' => $total > 0 ? round($pills['lowStock'] / $total * 100, 1) : 0, 'color' => '#E8710A'],
            ['label' => 'Out of Stock', 'pct' => $total > 0 ? round($pills['outOfStock'] / $total * 100, 1) : 0, 'color' => '#D93025'],
            ['label' => 'Overstocked', 'pct' => $total > 0 ? round($pills['overstocked'] / $total * 100, 1) : 0, 'color' => '#8B5CF6'],
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
        // Explicit 'low'/'unavailable' allowlist rather than "!==
        // 'available'" — now that 'overstocked' exists too, that would
        // have wrongly swept a surplus item into a shortage alert (see
        // hc_get_overstock_notifications()'s own sibling check below).
        $items = array_values(array_filter($items, fn ($item) => in_array($item['status'], ['low', 'unavailable'], true)));

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

if (!function_exists('hc_get_overstock_notifications')) {
    /**
     * One real notification row per medicine/vaccine currently at
     * status "overstocked" (hc_inventory_status_for_stock()) — the
     * surplus-side sibling of hc_get_low_stock_notifications() above,
     * same id scheme (crc32() of "overstock:{doc id}" rather than
     * crc32($item['id']) alone, so a single item can independently be
     * read/unread for its low-stock alert and its overstock alert
     * without the two colliding on the same id).
     *
     * @return array<int, array{id: int, title: string, description: string, type: string, date: string, time: string, icon: string, iconBg: string, iconColor: string}>
     */
    function hc_get_overstock_notifications(): array
    {
        $items = array_merge(hc_get_inventory_items('medicine'), hc_get_inventory_items('vaccine'));
        $items = array_values(array_filter($items, fn ($item) => $item['status'] === 'overstocked'));

        usort($items, fn ($a, $b) => strcmp($b['lastUpdated'], $a['lastUpdated']));

        return array_map(function ($item) {
            $updatedAt = $item['lastUpdatedRaw'] !== '' ? strtotime($item['lastUpdatedRaw']) : false;

            return [
                'id' => crc32('overstock:' . $item['id']),
                'title' => 'Overstock alert',
                'description' => $item['name'] . ' — ' . number_format($item['currentStock']) . ' ' . $item['unit'] . ' on hand (maximum level: ' . number_format($item['maxLevel']) . ').',
                'type' => 'Inventory',
                'date' => $updatedAt !== false ? date('M j, Y', $updatedAt) : '—',
                'time' => $updatedAt !== false ? date('g:i A', $updatedAt) : '',
                'icon' => $item['icon'],
                'iconBg' => '#F1E8FD',
                'iconColor' => '#8B5CF6',
            ];
        }, $items);
    }
}

if (!function_exists('hc_get_medicine_expiry_notifications')) {
    /**
     * One real notification row per medicine/vaccine that's either
     * already past its real expiryDate or will reach it within
     * $daysSoon days (default 30 — the same window
     * includes/subadmin/inventory_reports_firestore.php's own "Expiring
     * Soon" stat/table already uses, so this page never disagrees with
     * that one about what counts as "soon"), across both
     * hc_get_inventory_items() types — for pharmacist/notifications.php,
     * subadmin/notifications.php, and their two unread badges (see
     * config/constants.php's hc_pharmacist_unread_notification_count()
     * and subadmin_unread_notification_count()). Items with no recorded
     * expiry are skipped entirely — nothing real to alert on. Already-
     * expired items surface before still-expiring-soon ones (more
     * urgent), ties broken by soonest real expiry date first.
     *
     * 'id' is crc32() of "expiry:{doc id}" rather than crc32($item['id'])
     * alone (the scheme hc_get_low_stock_notifications() already uses) —
     * the SAME medicine doc can be both low-stock AND expiring soon at
     * once, and the shared read-tracking session field
     * ($_SESSION['ntfReadIds']) can't tell two different notifications
     * about the same item apart if they collide on one int id.
     *
     * @return array<int, array{id: int, title: string, description: string, type: string, date: string, time: string, icon: string, iconBg: string, iconColor: string}>
     */
    function hc_get_medicine_expiry_notifications(int $daysSoon = 30): array
    {
        $items = array_merge(hc_get_inventory_items('medicine'), hc_get_inventory_items('vaccine'));
        $items = array_values(array_filter($items, fn ($item) => hc_inventory_expiry_status($item['expiryDateRaw'], $daysSoon) !== 'ok'));

        usort($items, fn ($a, $b) => strcmp($a['expiryDateRaw'], $b['expiryDateRaw']));

        $today = strtotime(date('Y-m-d'));

        return array_map(function ($item) use ($today) {
            $expiry = strtotime($item['expiryDateRaw']);
            $isExpired = $expiry < $today;
            $daysUntil = (int) floor(($expiry - $today) / 86400);

            if ($isExpired) {
                $description = $item['name'] . ' expired on ' . date('M j, Y', $expiry) . '.';
            } elseif ($daysUntil === 0) {
                $description = $item['name'] . ' expires today (' . date('M j, Y', $expiry) . ').';
            } else {
                $description = $item['name'] . ' expires in ' . $daysUntil . ' day' . ($daysUntil === 1 ? '' : 's') . ' (' . date('M j, Y', $expiry) . ').';
            }

            return [
                'id' => crc32('expiry:' . $item['id']),
                'title' => $isExpired ? 'Expired' : 'Expiring soon',
                'description' => $description,
                'type' => 'Inventory',
                'date' => date('M j, Y', $expiry),
                'time' => '',
                'icon' => $item['icon'],
                'iconBg' => $isExpired ? '#FCE4E4' : '#FDF3DC',
                'iconColor' => $isExpired ? '#D93025' : '#B7791F',
            ];
        }, $items);
    }
}
