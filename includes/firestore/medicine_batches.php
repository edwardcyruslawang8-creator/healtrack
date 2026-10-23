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

if (!function_exists('hc_get_batches_for_medicine')) {
    /**
     * Every real medicine_batches doc for $medicineId with real stock
     * left (quantity > 0 unless $includeEmpty), soonest-expiry first —
     * FEFO order, same as the main inventory table's own sort
     * (includes/firestore/medicines.php's hc_query_inventory_items()).
     * A batch with no real expiry recorded sorts last (unknown
     * urgency, not "expires immediately").
     *
     * @return array<int, array{id: string, quantity: int, expiryDateRaw: string, expiryDate: string, receivedDate: string, source: string, sourceLabel: string}>
     */
    function hc_get_batches_for_medicine(string $medicineId, bool $includeEmpty = false): array
    {
        global $firestore;

        hc_ensure_initial_batch($medicineId);

        $sourceLabels = [
            'initial' => 'Initial stock',
            'stock_request' => 'Stock request',
            'adjustment' => 'Manual adjustment',
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
                'quantity' => $qty,
                'expiryDateRaw' => $expiryRaw,
                'expiryDate' => $expiryRaw !== '' && strtotime($expiryRaw) !== false ? strtoupper(date('M d, Y', strtotime($expiryRaw))) : 'No expiry recorded',
                'receivedDate' => isset($f['receivedDate']) && $f['receivedDate'] !== '' ? date('M j, Y', strtotime((string) $f['receivedDate'])) : '—',
                'source' => (string) ($f['source'] ?? 'initial'),
                'sourceLabel' => $sourceLabels[$f['source'] ?? 'initial'] ?? 'Stock added',
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
        $firestore->setDocument('medicines', $medicineId, array_merge($medicineDoc, [
            'currentStock' => $totalStock,
            'expiryDate' => $soonestExpiryRaw !== '' ? $soonestExpiryRaw : ($medicineDoc['expiryDate'] ?? null),
            'status' => hc_inventory_status_for_stock($totalStock, $minLevel),
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
    function hc_create_medicine_batch(string $medicineId, int $quantity, string $expiryDate, string $source, ?string $sourceRequestId, string $createdBy): ?string
    {
        global $firestore;

        if ($quantity <= 0) {
            return null;
        }

        $batchId = $firestore->addDocument('medicine_batches', [
            'medicineId' => $medicineId,
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
