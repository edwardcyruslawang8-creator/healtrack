<?php
/**
 * =====================================================================
 * Shared data layer — Medicine/Vaccine Batches
 * ---------------------------------------------------------------------
 * The real fix for a problem a single currentStock/expiryDate pair on
 * each medicines/{id} doc can't honestly represent: the SAME medicine
 * can have multiple real batches on hand with DIFFERENT expiry dates
 * (e.g. a stock request released today, with older stock from a
 * previous delivery still not used up) — collapsing them into one
 * number/date either loses the older batch's real earlier expiry or
 * duplicates the catalog row for the same item, both of which mislead
 * whoever's reading the Expiry Date column.
 *
 * "medicine_batches" is the new source of truth for WHICH batches of a
 * given medicine exist and when each one expires. medicines/{id}'s own
 * currentStock/expiryDate fields are kept as a maintained ROLLUP (sum
 * of every batch's remaining quantity; the soonest expiry among
 * batches that still have stock) rather than being removed outright —
 * every existing read path over medicines (low-stock/expiry
 * notifications, predictive analytics, dashboards, the public medicine
 * page, Inventory Reports...) keeps working completely unchanged,
 * since it was never rewritten to understand batches individually.
 * Only the places that actually ADD or REMOVE stock, and the Manage
 * Medicine/Stock History views, need to know batches exist at all.
 *
 * FEFO (First Expiry, First Out) — the same discipline the main
 * inventory table's own sort already uses — governs every real
 * decrement: hc_decrement_medicine_stock_fefo() always draws down the
 * soonest-expiring batch first, so older stock is never left to expire
 * behind newer stock a dispense would otherwise pull from arbitrarily.
 *
 * hc_ensure_initial_batch() makes this self-healing for every
 * medicines/{id} doc that predates this file (real stock with no real
 * batch doc behind it yet) — the first time anything here needs that
 * item's batches, one real batch is materialized from its own existing
 * currentStock/expiryDate, so there's never a silent gap where a
 * pre-existing item's stock becomes undispensable.
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/medicines.php'; // hc_inventory_status_for_stock()

if (!function_exists('hc_ensure_initial_batch')) {
    /**
     * Self-healing migration, one medicine at a time: if $medicineId
     * has real stock (currentStock > 0) but no real medicine_batches
     * doc yet, materializes ONE real batch from its own existing
     * currentStock/expiryDate (source "initial") so FEFO decrement/the
     * batches list has real data to work from. A no-op (and cheap —
     * one query) for a medicine that already has at least one batch
     * doc, including one with zero real stock at creation.
     */
    function hc_ensure_initial_batch(string $medicineId): void
    {
        global $firestore;

        $existingBatches = $firestore->queryDocuments([
            'from' => [['collectionId' => 'medicine_batches']],
            'where' => $firestore->fieldEqualsFilter('medicineId', $medicineId),
        ]);
        if (!empty($existingBatches)) {
            return;
        }

        $medicineDoc = $firestore->getDocument('medicines', $medicineId);
        if ($medicineDoc === null) {
            return;
        }
        $stock = (int) ($medicineDoc['currentStock'] ?? 0);
        if ($stock <= 0) {
            return;
        }

        $expiryDate = (string) ($medicineDoc['expiryDate'] ?? '');
        $firestore->addDocument('medicine_batches', [
            'medicineId' => $medicineId,
            'quantity' => $stock,
            'originalQuantity' => $stock,
            'expiryDate' => $expiryDate !== '' ? $expiryDate : null,
            'receivedDate' => $medicineDoc['lastUpdated'] ?? date('c'),
            'source' => 'initial',
            'sourceRequestId' => null,
            'createdAt' => date('c'),
            'createdBy' => 'System (migrated)',
        ]);
    }
}

if (!function_exists('hc_batch_display_number')) {
    /**
     * The real "batchNumber" a pharmacist typed in at Restock time, or —
     * for every batch that predates that field (every batch
     * hc_ensure_initial_batch()/the old Adjust Stock/stock-request flows
     * ever created) — a short, stable label derived from the batch's own
     * real doc id, same convention
     * includes/shared/inventory_movement_monitor.php already uses for
     * the same reason: never invent a batch number that wasn't really
     * recorded, just make the real id legible.
     */
    function hc_batch_display_number(array $batchFields, string $batchDocId): string
    {
        $typed = trim((string) ($batchFields['batchNumber'] ?? ''));

        return $typed !== '' ? $typed : 'BATCH-' . strtoupper(substr($batchDocId, 0, 8));
    }
}

if (!function_exists('hc_batch_status')) {
    /**
     * ONE real status per batch — unlike medicines/{id}'s own "status"
     * (hc_inventory_status_for_stock(), which only ever reasons about
     * stock level), a single batch can ALSO be individually expired or
     * expiring soon regardless of what any other batch of the same
     * medicine looks like, so the Manage Medicine modal's batch table
     * needs one combined signal per row. Checked in order of real
     * urgency — an empty batch is "Out of Stock" no matter its expiry;
     * a batch that still has stock but is already past its own real
     * expiry is "Expired" (api/dispense-without-prescription.php would
     * refuse to draw from it) before a merely-low count is flagged;
     * "Low Stock" uses $reorderLevel (the one stock threshold the
     * Batch Management table actually shows a column for) rather
     * than the medicine's separate minLevel, and is skipped entirely
     * when $reorderLevel is 0 (no threshold configured to compare
     * against) — same "0 means no ceiling/floor set" convention
     * hc_inventory_status_for_stock() already uses for maxLevel.
     *
     * @return array{key: string, label: string, bg: string, color: string}
     */
    function hc_batch_status(int $quantity, string $expiryDateRaw, int $reorderLevel): array
    {
        $meta = [
            'out_of_stock' => ['label' => 'Out of Stock', 'bg' => '#FCE4E4', 'color' => '#D93025'],
            'expired'      => ['label' => 'Expired',       'bg' => '#FCE4E4', 'color' => '#D93025'],
            'low_stock'    => ['label' => 'Low Stock',     'bg' => '#FDECDD', 'color' => '#E8710A'],
            'expiring_soon'=> ['label' => 'Expiring Soon', 'bg' => '#FDF3DC', 'color' => '#B7791F'],
            'in_stock'     => ['label' => 'In Stock',      'bg' => '#E4F7EA', 'color' => '#1E8E3E'],
        ];

        if ($quantity <= 0) {
            return ['key' => 'out_of_stock'] + $meta['out_of_stock'];
        }
        $expiryState = hc_inventory_expiry_status($expiryDateRaw);
        if ($expiryState === 'expired') {
            return ['key' => 'expired'] + $meta['expired'];
        }
        if ($reorderLevel > 0 && $quantity <= $reorderLevel) {
            return ['key' => 'low_stock'] + $meta['low_stock'];
        }
        if ($expiryState === 'soon') {
            return ['key' => 'expiring_soon'] + $meta['expiring_soon'];
        }

        return ['key' => 'in_stock'] + $meta['in_stock'];
    }
}

if (!function_exists('hc_get_batches_for_medicine')) {
    /**
     * Every real medicine_batches doc for $medicineId with real stock
     * left (quantity > 0 unless $includeEmpty), soonest-expiry first —
     * FEFO order, same as the main inventory table's own sort
     * (includes/firestore/medicines.php's hc_query_inventory_items()).
     * A batch with no real expiry recorded sorts last (unknown
     * urgency, not "expires immediately").
     *
     * Each batch also carries the parent medicine's own real
     * reorderLevel/maxLevel thresholds (same value repeated on every
     * row — they're configured per MEDICINE, not per batch) plus a
     * per-batch 'status' (hc_batch_status()) and 'displayBatchNumber' —
     * everything the Manage Medicine modal's Batch Management table
     * (pharmacist/medicine_vaccine_inventory.php) needs in one call.
     *
     * @return array<int, array{id: string, batchNumber: string, displayBatchNumber: string, quantity: int, expiryDateRaw: string, expiryDate: string, receivedDate: string, source: string, sourceLabel: string, reorderLevel: int, maxLevel: int, status: array}>
     */
    function hc_get_batches_for_medicine(string $medicineId, bool $includeEmpty = false): array
    {
        global $firestore;

        hc_ensure_initial_batch($medicineId);

        $medicineDoc = $firestore->getDocument('medicines', $medicineId);
        $reorderLevel = (int) ($medicineDoc['reorderLevel'] ?? 0);
        $maxLevel = (int) ($medicineDoc['maxLevel'] ?? 0);

        $sourceLabels = [
            'initial' => 'Initial stock',
            'stock_request' => 'Stock request',
            'adjustment' => 'Manual adjustment',
            'restock' => 'Restock',
        ];

        $batches = [];
        foreach ($firestore->queryDocuments([
            'from' => [['collectionId' => 'medicine_batches']],
            'where' => $firestore->fieldEqualsFilter('medicineId', $medicineId),
        ]) as $doc) {
            $f = $doc['fields'];
            $qty = (int) ($f['quantity'] ?? 0);
            if ($qty <= 0 && !$includeEmpty) {
                continue;
            }
            $expiryRaw = (string) ($f['expiryDate'] ?? '');
            $batches[] = [
                'id' => $doc['id'],
                'batchNumber' => (string) ($f['batchNumber'] ?? ''),
                'displayBatchNumber' => hc_batch_display_number($f, $doc['id']),
                'quantity' => $qty,
                'expiryDateRaw' => $expiryRaw,
                'expiryDate' => $expiryRaw !== '' && strtotime($expiryRaw) !== false ? strtoupper(date('M d, Y', strtotime($expiryRaw))) : 'No expiry recorded',
                'receivedDate' => isset($f['receivedDate']) && $f['receivedDate'] !== '' ? date('M j, Y', strtotime((string) $f['receivedDate'])) : '—',
                'source' => (string) ($f['source'] ?? 'initial'),
                'sourceLabel' => $sourceLabels[$f['source'] ?? 'initial'] ?? 'Stock added',
                'reorderLevel' => $reorderLevel,
                'maxLevel' => $maxLevel,
                'status' => hc_batch_status($qty, $expiryRaw, $reorderLevel),
            ];
        }

        usort($batches, function ($a, $b) {
            $expiryA = $a['expiryDateRaw'] !== '' ? $a['expiryDateRaw'] : '9999-99-99';
            $expiryB = $b['expiryDateRaw'] !== '' ? $b['expiryDateRaw'] : '9999-99-99';

            return $expiryA <=> $expiryB;
        });

        return $batches;
    }
}

if (!function_exists('hc_recompute_medicine_rollup')) {
    /**
     * Recomputes medicines/{id}'s own currentStock (sum of every real
     * batch's remaining quantity) and expiryDate (the soonest expiry
     * among batches that still have stock — FEFO, same as the table's
     * own sort) from its real medicine_batches docs, and writes both
     * back along with a freshly-derived "status". Called after every
     * batch create/decrement so the rollup never drifts from what the
     * real batches actually add up to.
     */
    function hc_recompute_medicine_rollup(string $medicineId): void
    {
        global $firestore;

        $medicineDoc = $firestore->getDocument('medicines', $medicineId);
        if ($medicineDoc === null) {
            return;
        }

        $batches = hc_get_batches_for_medicine($medicineId);
        $totalStock = array_sum(array_column($batches, 'quantity'));
        $soonestExpiryRaw = '';
        foreach ($batches as $b) {
            if ($b['expiryDateRaw'] === '') {
                continue;
            }
            if ($soonestExpiryRaw === '' || $b['expiryDateRaw'] < $soonestExpiryRaw) {
                $soonestExpiryRaw = $b['expiryDateRaw'];
            }
        }

        $minLevel = (int) ($medicineDoc['minLevel'] ?? 0);
        $maxLevel = (int) ($medicineDoc['maxLevel'] ?? 0);
        $firestore->setDocument('medicines', $medicineId, array_merge($medicineDoc, [
            'currentStock' => $totalStock,
            'expiryDate' => $soonestExpiryRaw !== '' ? $soonestExpiryRaw : ($medicineDoc['expiryDate'] ?? null),
            'status' => hc_inventory_status_for_stock($totalStock, $minLevel, $maxLevel),
            'lastUpdated' => date('c'),
        ]));
    }
}

if (!function_exists('hc_create_medicine_batch')) {
    /**
     * Adds real stock to $medicineId as its OWN new batch (a stock
     * request release, a manual Adjust Stock increase, ...) rather
     * than blindly bumping a single shared currentStock number — this
     * is the actual fix for "the same medicine arrives with a
     * different expiry date than what's already on the shelf".
     * Recomputes the medicine's own rollup immediately after, so
     * every existing read path reflects the new total/soonest-expiry
     * right away.
     *
     * @return string|null The new batch doc's id, or null on failure.
     */
    function hc_create_medicine_batch(string $medicineId, int $quantity, string $expiryDate, string $source, ?string $sourceRequestId, string $createdBy, string $batchNumber = ''): ?string
    {
        global $firestore;

        if ($quantity <= 0) {
            return null;
        }

        $batchId = $firestore->addDocument('medicine_batches', [
            'medicineId' => $medicineId,
            'batchNumber' => trim($batchNumber) !== '' ? trim($batchNumber) : null,
            'quantity' => $quantity,
            'originalQuantity' => $quantity,
            'expiryDate' => $expiryDate !== '' ? $expiryDate : null,
            'receivedDate' => date('Y-m-d'),
            'source' => $source,
            'sourceRequestId' => $sourceRequestId,
            'createdAt' => date('c'),
            'createdBy' => $createdBy,
        ]);

        if ($batchId !== null) {
            hc_recompute_medicine_rollup($medicineId);
        }

        return $batchId;
    }
}

if (!function_exists('hc_add_to_existing_batch')) {
    /**
     * Restocking the SAME real batch (same batch number/expiry a
     * pharmacist is already holding, just more of it arrived) rather
     * than creating a brand-new batch doc — the other half of
     * api/restock-medicine.php's "select a specific batch or add a new
     * batch" choice. Adds $quantity to both the batch's own remaining
     * 'quantity' AND its 'originalQuantity' (so a later restock of the
     * same batch keeps the running total this batch has ever held
     * accurate, the same two fields hc_create_medicine_batch() seeds
     * together for a brand-new batch) and recomputes the medicine's own
     * rollup immediately after, same as every other real stock change
     * here.
     */
    function hc_add_to_existing_batch(string $batchId, int $quantity): bool
    {
        global $firestore;

        if ($quantity <= 0) {
            return false;
        }

        $batchDoc = $firestore->getDocument('medicine_batches', $batchId);
        if ($batchDoc === null) {
            return false;
        }

        $medicineId = (string) ($batchDoc['medicineId'] ?? '');
        if ($medicineId === '') {
            return false;
        }

        $saved = $firestore->setDocument('medicine_batches', $batchId, array_merge($batchDoc, [
            'quantity' => (int) ($batchDoc['quantity'] ?? 0) + $quantity,
            'originalQuantity' => (int) ($batchDoc['originalQuantity'] ?? 0) + $quantity,
        ]));

        if ($saved) {
            hc_recompute_medicine_rollup($medicineId);
        }

        return $saved;
    }
}

if (!function_exists('hc_decrement_medicine_stock_fefo')) {
    /**
     * Draws $quantity real units off $medicineId's real batches,
     * soonest-expiring batch first (FEFO) — used by every real
     * dispense (api/dispense-pharmacy-order.php,
     * api/dispense-without-prescription.php) and a manual Adjust Stock
     * decrease, so stock is never implicitly pulled from a batch that
     * expires LATER while an earlier-expiring one sits untouched.
     * Returns false (no writes made at all) if the real total
     * available across every batch is less than $quantity — same
     * "never leave stock negative" guarantee the old single-number
     * subtraction already gave, just correctly computed from real
     * batches now instead of one shared field.
     */
    function hc_decrement_medicine_stock_fefo(string $medicineId, int $quantity): bool
    {
        global $firestore;

        if ($quantity <= 0) {
            return true;
        }

        $batches = hc_get_batches_for_medicine($medicineId);
        $available = array_sum(array_column($batches, 'quantity'));
        if ($available < $quantity) {
            return false;
        }

        $remaining = $quantity;
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($batch['quantity'], $remaining);
            $batchDoc = $firestore->getDocument('medicine_batches', $batch['id']);
            if ($batchDoc === null) {
                continue; // genuinely deleted between the read above and now — skip rather than fail the whole dispense
            }
            $firestore->setDocument('medicine_batches', $batch['id'], array_merge($batchDoc, [
                'quantity' => $batch['quantity'] - $take,
            ]));
            $remaining -= $take;
        }

        hc_recompute_medicine_rollup($medicineId);

        return true;
    }
}
