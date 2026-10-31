<?php
/**
 * =====================================================================
 * Shared — Stock Movement Summary & Inventory Expiry Monitor
 * ---------------------------------------------------------------------
 * Real computations behind subadmin/inventory_reports.php's "Stock
 * Movement Summary" (previously 100% hardcoded placeholder numbers —
 * see hc_compute_stock_movement_summary()'s own comment) and
 * "Inventory Expiry Monitor" (previously the medicines collection's own
 * single currentStock/expiryDate pair — now real per-BATCH expiry,
 * since the same medicine can have multiple real batches on hand with
 * different expiry dates; see includes/firestore/medicine_batches.php's
 * own header comment for why that distinction matters).
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/../firestore/medicines.php'; // hc_get_inventory_items(), hc_inventory_expiry_status()
require_once __DIR__ . '/../firestore/medicine_batches.php'; // hc_get_batches_for_medicine()

if (!function_exists('hc_compute_stock_movement_summary')) {
    /**
     * Real per-category (Medicines/Vaccines) Opening/Received/Issued/
     * Closing stock for [$startDate, $endDate] inclusive (Y-m-d, null =
     * no bound on that side).
     *
     * - Closing stock is TODAY's real live rollup (medicines.currentStock
     *   — see hc_recompute_medicine_rollup()'s own comment), exact.
     * - Received is the real sum of medicine_batches.originalQuantity
     *   for batches actually received inside the window — excludes
     *   'initial'-source batches, which are a self-healing migration
     *   artifact backfilled from whatever stock already existed before
     *   per-batch tracking began, not a real receiving event.
     * - Issued is the real sum of pharmacy_orders.dispensedQuantity for
     *   dispenses inside the window — only counted where that field was
     *   actually recorded (every dispense through the real Dispense flow
     *   sets it; older/seeded dispense records that predate this field
     *   are honestly left uncounted rather than guessed at).
     * - Opening is derived algebraically from the three real numbers
     *   above (closing - received + issued) — standard inventory
     *   accounting identity, not a fabricated figure; this app has no
     *   point-in-time stock snapshots to read an exact opening figure
     *   from directly.
     *
     * @return array{byCategory: array<int, array{category: string, items: int, opening: int, received: int, issued: int, closing: int}>, total: array{items: int, opening: int, received: int, issued: int, closing: int}}
     */
    function hc_compute_stock_movement_summary(?string $startDate, ?string $endDate): array
    {
        global $firestore;

        $medicines = hc_get_inventory_items('medicine');
        $vaccines = hc_get_inventory_items('vaccine');
        // A dispense can reference an item that's since been archived —
        // still real catalog data (just not currently active), so it's
        // included here too rather than falling back to a generic
        // "medicine" guess for every archived item's own real dispenses.
        $itemTypeById = [];
        foreach (array_merge($medicines, hc_get_archived_inventory_items('medicine')) as $m) {
            $itemTypeById[$m['id']] = 'medicine';
        }
        foreach (array_merge($vaccines, hc_get_archived_inventory_items('vaccine')) as $v) {
            $itemTypeById[$v['id']] = 'vaccine';
        }

        $closingByType = [
            'medicine' => array_sum(array_column($medicines, 'currentStock')),
            'vaccine' => array_sum(array_column($vaccines, 'currentStock')),
        ];
        $itemsByType = ['medicine' => count($medicines), 'vaccine' => count($vaccines)];

        $inRange = function (string $date) use ($startDate, $endDate): bool {
            if ($date === '') {
                return false;
            }
            $day = substr($date, 0, 10);
            if ($startDate !== null && $day < $startDate) {
                return false;
            }
            if ($endDate !== null && $day > $endDate) {
                return false;
            }

            return true;
        };

        $receivedByType = ['medicine' => 0, 'vaccine' => 0];
        foreach ($firestore->listDocuments('medicine_batches') as $doc) {
            $f = $doc['fields'];
            if (($f['source'] ?? 'initial') === 'initial') {
                continue;
            }
            $medicineId = (string) ($f['medicineId'] ?? '');
            $type = $itemTypeById[$medicineId] ?? null;
            if ($type === null) {
                continue; // archived/deleted item — nothing current to attribute this to
            }
            $receivedDate = (string) ($f['receivedDate'] ?? ($f['createdAt'] ?? ''));
            if (!$inRange($receivedDate)) {
                continue;
            }
            $receivedByType[$type] += (int) ($f['originalQuantity'] ?? 0);
        }

        $issuedByType = ['medicine' => 0, 'vaccine' => 0];
        foreach ($firestore->listDocuments('pharmacy_orders') as $doc) {
            $f = $doc['fields'];
            if (($f['status'] ?? '') !== 'dispensed') {
                continue;
            }
            if (!isset($f['dispensedQuantity'])) {
                continue; // real but unrecorded — not guessed at
            }
            $dispensedDate = (string) ($f['dispensedDate'] ?? '');
            if (!$inRange($dispensedDate)) {
                continue;
            }
            $medicineId = (string) ($f['dispensedInventoryId'] ?? '');
            $type = $itemTypeById[$medicineId] ?? (($f['itemType'] ?? 'medicine') === 'vaccine' ? 'vaccine' : 'medicine');
            $issuedByType[$type] += (int) $f['dispensedQuantity'];
        }

        $byCategory = [];
        foreach (['medicine' => 'Medicines', 'vaccine' => 'Vaccines'] as $type => $label) {
            $closing = $closingByType[$type];
            $received = $receivedByType[$type];
            $issued = $issuedByType[$type];
            $byCategory[] = [
                'category' => $label,
                'items' => $itemsByType[$type],
                'opening' => max(0, $closing - $received + $issued),
                'received' => $received,
                'issued' => $issued,
                'closing' => $closing,
            ];
        }

        $total = [
            'items' => array_sum(array_column($byCategory, 'items')),
            'opening' => array_sum(array_column($byCategory, 'opening')),
            'received' => array_sum(array_column($byCategory, 'received')),
            'issued' => array_sum(array_column($byCategory, 'issued')),
            'closing' => array_sum(array_column($byCategory, 'closing')),
        ];

        return ['byCategory' => $byCategory, 'total' => $total];
    }
}

if (!function_exists('hc_compute_stock_movement_transactions')) {
    /**
     * The real individual events behind hc_compute_stock_movement_summary()'s
     * own totals — one row per real medicine_batches receipt (source !==
     * 'initial') and one row per real dispensedQuantity-bearing
     * pharmacy_orders dispense, inside [$startDate, $endDate], newest
     * first. Backs the "View Stock Movements" detailed report.
     *
     * @return array<int, array{date: string, type: string, itemName: string, category: string, quantity: int, unit: string, detail: string}>
     */
    function hc_compute_stock_movement_transactions(?string $startDate, ?string $endDate): array
    {
        global $firestore;

        $medicines = hc_get_inventory_items('medicine');
        $vaccines = hc_get_inventory_items('vaccine');
        $itemById = [];
        foreach (array_merge($medicines, hc_get_archived_inventory_items('medicine')) as $m) {
            $itemById[$m['id']] = ['name' => $m['name'], 'category' => 'Medicine', 'unit' => $m['unit']];
        }
        foreach (array_merge($vaccines, hc_get_archived_inventory_items('vaccine')) as $v) {
            $itemById[$v['id']] = ['name' => $v['name'], 'category' => 'Vaccine', 'unit' => $v['unit']];
        }

        $inRange = function (string $date) use ($startDate, $endDate): bool {
            if ($date === '') {
                return false;
            }
            $day = substr($date, 0, 10);
            if ($startDate !== null && $day < $startDate) {
                return false;
            }
            if ($endDate !== null && $day > $endDate) {
                return false;
            }

            return true;
        };

        $sourceLabels = ['stock_request' => 'Stock request', 'adjustment' => 'Manual adjustment'];

        $rows = [];
        foreach ($firestore->listDocuments('medicine_batches') as $doc) {
            $f = $doc['fields'];
            if (($f['source'] ?? 'initial') === 'initial') {
                continue;
            }
            $receivedDate = (string) ($f['receivedDate'] ?? ($f['createdAt'] ?? ''));
            if (!$inRange($receivedDate)) {
                continue;
            }
            $item = $itemById[(string) ($f['medicineId'] ?? '')] ?? null;
            if ($item === null) {
                continue;
            }
            $rows[] = [
                'date' => substr($receivedDate, 0, 10),
                'type' => 'received',
                'itemName' => $item['name'],
                'category' => $item['category'],
                'quantity' => (int) ($f['originalQuantity'] ?? 0),
                'unit' => $item['unit'],
                'detail' => $sourceLabels[$f['source'] ?? ''] ?? 'Stock added',
            ];
        }

        foreach ($firestore->listDocuments('pharmacy_orders') as $doc) {
            $f = $doc['fields'];
            if (($f['status'] ?? '') !== 'dispensed' || !isset($f['dispensedQuantity'])) {
                continue;
            }
            $dispensedDate = (string) ($f['dispensedDate'] ?? '');
            if (!$inRange($dispensedDate)) {
                continue;
            }
            $item = $itemById[(string) ($f['dispensedInventoryId'] ?? '')] ?? null;
            $itemName = $item['name'] ?? (string) ($f['dispensedItemName'] ?? ($f['itemName'] ?? 'Item'));
            $category = $item['category'] ?? (($f['itemType'] ?? 'medicine') === 'vaccine' ? 'Vaccine' : 'Medicine');
            $rows[] = [
                'date' => substr($dispensedDate, 0, 10),
                'type' => 'issued',
                'itemName' => $itemName,
                'category' => $category,
                'quantity' => (int) $f['dispensedQuantity'],
                'unit' => $item['unit'] ?? 'unit',
                'detail' => 'Dispensed by ' . (string) ($f['dispensedBy'] ?? 'pharmacist'),
            ];
        }

        usort($rows, fn ($a, $b) => $b['date'] <=> $a['date']);

        return $rows;
    }
}

if (!function_exists('hc_compute_inventory_expiry_monitor')) {
    /**
     * Real per-BATCH expiry monitoring across every real medicine AND
     * vaccine — the same medicine can have multiple real batches with
     * DIFFERENT expiry dates (see includes/firestore/medicine_batches.php's
     * own header comment), so this reports per-batch, not per-item.
     * Only batches with real remaining stock (quantity > 0) are
     * included — a fully-used-up batch isn't an "actionable risk"
     * anymore, real or otherwise.
     *
     * Status: 'expired' (real expiry date before today), 'today'
     * (expires today — called out separately so it doesn't blur into
     * either "already expired" or an ordinary "expiring soon" day
     * count), 'expiring_soon' (within the next 30 real days), 'no_expiry'
     * (batch has no real expiry date recorded at all — gray, not an
     * invented one).
     *
     * @return array{items: array<int, array{medicineId: string, batchId: string, name: string, category: string, batchNo: string, expiryDateRaw: string, expiryDate: string, quantity: int, unit: string, status: string, daysFromToday: ?int}>, expiredCount: int, expiringSoonCount: int}
     */
    function hc_compute_inventory_expiry_monitor(): array
    {
        $medicines = array_map(fn ($m) => $m + ['itemType' => 'medicine'], hc_get_inventory_items('medicine'));
        $vaccines = array_map(fn ($v) => $v + ['itemType' => 'vaccine'], hc_get_inventory_items('vaccine'));
        $allItems = array_merge($medicines, $vaccines);

        $today = strtotime(date('Y-m-d'));
        $rows = [];
        foreach ($allItems as $item) {
            foreach (hc_get_batches_for_medicine($item['id']) as $batch) {
                if ($batch['quantity'] <= 0) {
                    continue;
                }
                $expiryRaw = $batch['expiryDateRaw'];
                $expiryTs = $expiryRaw !== '' ? strtotime($expiryRaw) : false;

                if ($expiryTs === false) {
                    $status = 'no_expiry';
                    $daysFromToday = null;
                } else {
                    $daysFromToday = (int) round(($expiryTs - $today) / 86400);
                    $status = match (true) {
                        $daysFromToday < 0 => 'expired',
                        $daysFromToday === 0 => 'today',
                        $daysFromToday <= 30 => 'expiring_soon',
                        default => 'ok',
                    };
                }

                // The real Firestore batch doc id IS the batch's real,
                // unique identifier — but shown raw (a 20-character
                // random string) it reads as noise, not a batch number.
                // "Not Recorded" for a batch id itself never fires today
                // (hc_get_batches_for_medicine() always returns a real
                // id), but the field stays honest if that ever changes.
                // Shortened to an 8-char reference, uppercased — still
                // the real id (just its leading chars), not an invented
                // number, and still unique enough in practice for a
                // facility's own real batch count.
                $rows[] = [
                    'medicineId' => $item['id'],
                    'batchId' => $batch['id'],
                    'name' => $item['name'],
                    'category' => $item['itemType'] === 'vaccine' ? 'Vaccine' : 'Medicine',
                    'batchNo' => $batch['id'] !== '' ? ('BATCH-' . strtoupper(substr($batch['id'], 0, 8))) : 'Not Recorded',
                    'expiryDateRaw' => $expiryRaw,
                    'expiryDate' => $expiryTs !== false ? date('M j, Y', $expiryTs) : 'Not Recorded',
                    'quantity' => $batch['quantity'],
                    'unit' => $item['unit'],
                    'status' => $status,
                    'daysFromToday' => $daysFromToday,
                ];
            }
        }

        // Urgency order: most-overdue expired first, then expires-today,
        // then soonest-expiring-soon, then "ok"/no-expiry rows last
        // (shown only in the full "View All" list, never among the
        // card's top 5 "relevant" rows).
        $statusRank = ['expired' => 0, 'today' => 1, 'expiring_soon' => 2, 'ok' => 3, 'no_expiry' => 4];
        usort($rows, function ($a, $b) use ($statusRank) {
            $rankCmp = $statusRank[$a['status']] <=> $statusRank[$b['status']];
            if ($rankCmp !== 0) {
                return $rankCmp;
            }
            // Expired: furthest-overdue (most negative daysFromToday) first.
            // Expiring soon: soonest (smallest daysFromToday) first.
            if ($a['daysFromToday'] === null || $b['daysFromToday'] === null) {
                return 0;
            }

            return $a['status'] === 'expired'
                ? $a['daysFromToday'] <=> $b['daysFromToday']
                : $a['daysFromToday'] <=> $b['daysFromToday'];
        });

        $expiredCount = count(array_filter($rows, fn ($r) => $r['status'] === 'expired'));
        $expiringSoonCount = count(array_filter($rows, fn ($r) => in_array($r['status'], ['today', 'expiring_soon'], true)));

        return ['items' => $rows, 'expiredCount' => $expiredCount, 'expiringSoonCount' => $expiringSoonCount];
    }
}
