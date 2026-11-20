<?php
/**
 * =====================================================================
 * Prescription Queue stat cards partial
 * ---------------------------------------------------------------------
 * Shared verbatim between pharmacist/prescription_queue.php's initial
 * page load (wrapped in #rxqStats there) and
 * api/pharmacist-prescription-queue-status.php (polled by the inline
 * live-refresh script on that page via assets/js/queue-poll.js) — split
 * out from _prescription_queue_table.php specifically so a live refresh
 * only ever touches these read-only counters, never the filter
 * toolbar's search input sitting between them and the table (which
 * could hold text the pharmacist is still typing), same reasoning as
 * includes/medical_technician/_lab_xray_referral_stats.php.
 *
 * Expects $ordersSummary already set by the includer (see
 * includes/pharmacist/prescription_queue_pharmacist_firestore.php).
 * =====================================================================
 */
?>
<div class="phm-stats-row rxq-stats-row">
    <div class="phm-stat-card">
        <div class="phm-stat-icon" style="background:#FDECDD; color:#E8710A;"><i class="bi bi-hourglass-split"></i></div>
        <div class="phm-stat-label">Total Pending</div>
        <div class="phm-stat-value"><?= number_format($ordersSummary['pending']) ?></div>
    </div>
    <div class="phm-stat-card">
        <div class="phm-stat-icon" style="background:#EFE7FB; color:#7C3AED;"><i class="bi bi-capsule"></i></div>
        <div class="phm-stat-label">Medicines Pending</div>
        <div class="phm-stat-value"><?= number_format($ordersSummary['medicines']) ?></div>
    </div>
    <div class="phm-stat-card">
        <div class="phm-stat-icon" style="background:#E8F0FE; color:#1A73E8;"><i class="bi bi-shield-plus"></i></div>
        <div class="phm-stat-label">Vaccines Pending</div>
        <div class="phm-stat-value"><?= number_format($ordersSummary['vaccines']) ?></div>
    </div>
</div>
