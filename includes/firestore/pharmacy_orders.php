<?php
/**
 * =====================================================================
 * Shared data layer — Pharmacy Orders
 * ---------------------------------------------------------------------
 * Pure Firestore-query functions for the real "pharmacy_orders"
 * collection — the pharmacist-side equivalent of
 * includes/firestore/lab_results.php's "lab_results" collection.
 * api/complete-consultation.php creates one real doc per medicine/
 * vaccine row whenever a doctor completes a consultation routed to
 * "Pharmacy" (see that file's "pharmacy" routing block) — this is the
 * first write path this collection has ever had. A pharmacist "Dispense"
 * action (pharmacist/prescription_queue.php -> api/dispense-pharmacy-order.php)
 * flips a doc from "pending" to "dispensed".
 * =====================================================================
 */

require_once __DIR__ . '/../../config/firebase.php'; // $firestore
require_once __DIR__ . '/patients.php'; // hc_get_all_patients_summary()

if (!function_exists('hc_get_all_pharmacy_order_docs')) {
    /** @internal Every raw pharmacy_orders/{id} doc across ALL patients, unfiltered. */
    function hc_get_all_pharmacy_order_docs(): array
    {
        global $firestore;

        return $firestore->listDocuments('pharmacy_orders');
    }
}

if (!function_exists('hc_get_pending_pharmacy_order_notifications')) {
    /**
     * One real notification row per pharmacy_orders doc still "pending"
     * (a doctor prescribed it, no pharmacist has dispensed it yet) —
     * same shape/reasoning as includes/firestore/lab_results.php's
     * hc_get_pending_lab_xray_notifications() (id is crc32() of the doc
     * id, for the same reason: $_SESSION['ntfReadIds']/['ntfDismissedIds']
     * stay a plain int array).
     *
     * @return array<int, array{id: int, title: string, description: string, type: string, date: string, time: string, icon: string, iconBg: string, iconColor: string}>
     */
    function hc_get_pending_pharmacy_order_notifications(): array
    {
        $rows = [];
        foreach (hc_get_all_pharmacy_order_docs() as $doc) {
            $f = $doc['fields'];
            if (($f['status'] ?? '') !== 'pending') {
                continue;
            }
            $rows[] = ['docId' => $doc['id'], 'fields' => $f];
        }

        usort($rows, fn ($a, $b) => strcmp($a['fields']['orderedDate'] . $a['fields']['orderedTime'], $b['fields']['orderedDate'] . $b['fields']['orderedTime']));

        return array_map(function ($r) {
            $f = $r['fields'];
            $orderedAt = ($f['orderedDate'] ?? '') !== '' ? strtotime($f['orderedDate'] . ' ' . ($f['orderedTime'] ?? '')) : false;
            $daysWaiting = $orderedAt !== false ? (int) floor((time() - $orderedAt) / 86400) : 0;
            $isVaccine = ($f['itemType'] ?? '') === 'vaccine';

            return [
                'id' => crc32('pharmacy_orders-' . $r['docId']),
                'title' => 'Pending ' . ($isVaccine ? 'vaccine' : 'medicine') . ' dispense',
                'description' => ($f['itemName'] ?? 'Item') . ' for ' . ($f['patientName'] ?? '(unknown patient)') . ' — prescribed ' . ($orderedAt !== false ? date('M j, Y', $orderedAt) : 'recently') . ', still awaiting dispensing.',
                'type' => $isVaccine ? 'Vaccine' : 'Medicine',
                'date' => $orderedAt !== false ? date('M j, Y', $orderedAt) : '—',
                'time' => $orderedAt !== false ? date('g:i A', $orderedAt) : '',
                'icon' => $isVaccine ? 'bi-shield-plus' : 'bi-capsule',
                'iconBg' => $daysWaiting >= 2 ? '#FCE4E4' : '#FDECDD',
                'iconColor' => $daysWaiting >= 2 ? '#D93025' : '#E8710A',
            ];
        }, $rows);
    }
}

if (!function_exists('hc_get_dispensed_prescription_notifications')) {
    /**
     * One real notification row per pharmacy_orders doc this specific
     * doctor prescribed (prescribedBy === $doctorName) that a pharmacist
     * has since dispensed — same shape/reasoning as
     * includes/firestore/lab_results.php's hc_get_doctor_result_ready_notifications()
     * (14-day recency cutoff, doctor-name match, crc32() id so
     * $_SESSION['ntfReadIds']/['ntfDismissedIds'] stay a plain int array).
     * Walk-in orders (api/dispense-without-prescription.php — no real
     * prescribing doctor) are excluded; they'd never match a real
     * doctor's name anyway, but this is explicit rather than relying on
     * that.
     *
     * @return array<int, array{id: int, title: string, description: string, type: string, date: string, time: string, icon: string, iconBg: string, iconColor: string}>
     */
    function hc_get_dispensed_prescription_notifications(string $doctorName): array
    {
        if ($doctorName === '') {
            return [];
        }

        $cutoff = date('Y-m-d', strtotime('-14 days'));

        $rows = [];
        foreach (hc_get_all_pharmacy_order_docs() as $doc) {
            $f = $doc['fields'];
            if (($f['status'] ?? '') !== 'dispensed'
                || ($f['prescribedBy'] ?? '') !== $doctorName
                || ($f['source'] ?? 'prescribed') === 'walk_in') {
                continue;
            }
            if (($f['dispensedDate'] ?? '') < $cutoff) {
                continue;
            }
            $rows[] = ['docId' => $doc['id'], 'fields' => $f];
        }

        usort($rows, fn ($a, $b) => strcmp($b['fields']['dispensedDate'] . $b['fields']['dispensedTime'], $a['fields']['dispensedDate'] . $a['fields']['dispensedTime']));

        return array_map(function ($r) {
            $f = $r['fields'];
            $dispensedAt = ($f['dispensedDate'] ?? '') !== '' ? strtotime($f['dispensedDate'] . ' ' . ($f['dispensedTime'] ?? '')) : false;
            $isVaccine = ($f['itemType'] ?? '') === 'vaccine';

            return [
                'id' => crc32('pharmacy_orders-' . $r['docId'] . '-dispensed-doctor'),
                'title' => ($isVaccine ? 'Vaccine' : 'Medicine') . ' dispensed',
                'description' => ($f['itemName'] ?? 'Item') . ' for ' . ($f['patientName'] ?? '(unknown patient)') . ' has been dispensed by ' . ($f['dispensedBy'] ?? 'the pharmacist') . '.',
                'type' => $isVaccine ? 'Vaccine' : 'Medicine',
                'date' => $dispensedAt !== false ? date('M j, Y', $dispensedAt) : '—',
                'time' => $dispensedAt !== false ? date('g:i A', $dispensedAt) : '',
                'icon' => $isVaccine ? 'bi-shield-plus' : 'bi-capsule',
                'iconBg' => '#E4F7EA',
                'iconColor' => '#1E8E3E',
            ];
        }, $rows);
    }
}

if (!function_exists('hc_get_pending_pharmacy_order_count')) {
    /**
     * Just the count behind hc_get_pending_pharmacy_order_notifications()
     * above, without building the full notification-row shape — for
     * sidebar nav badges that only need the number (same reasoning as
     * includes/firestore/lab_results.php's hc_get_pending_lab_xray_count()).
     */
    function hc_get_pending_pharmacy_order_count(): int
    {
        $count = 0;
        foreach (hc_get_all_pharmacy_order_docs() as $doc) {
            if (($doc['fields']['status'] ?? '') === 'pending') {
                $count++;
            }
        }
        return $count;
    }
}
