<?php
/**
 * =====================================================================
 * Referral Inbox stat cards partial
 * ---------------------------------------------------------------------
 * Shared verbatim between medical_technician/lab_xray_consultation.php's
 * initial page load (wrapped in #lxcReferralStats there) and
 * api/medtech-referral-queue-status.php (polled by
 * assets/js/medtech_lab_xray_consultation.js) — split out from
 * _lab_xray_referral_table.php specifically so a live refresh only ever
 * touches these read-only counters, never the filter toolbar's search
 * input sitting between them and the table (which could hold text the
 * technician is still typing).
 *
 * Expects $referralsSummary already set by the includer (see
 * includes/medical_technician/lab_xray_consultation_firestore.php).
 * =====================================================================
 */
?>
<div class="mt-stats-row lxr-stats-row lxc-stats-row">
    <div class="mt-stat-card">
        <div class="mt-stat-icon" style="background:#E8F0FE; color:#1A73E8;"><i class="bi bi-inboxes-fill"></i></div>
        <div class="mt-stat-label">Total Referrals</div>
        <div class="mt-stat-value"><?= number_format($referralsSummary['total']) ?></div>
    </div>
    <div class="mt-stat-card">
        <div class="mt-stat-icon" style="background:#EFE7FB; color:#7C3AED;"><i class="bi bi-droplet-fill"></i></div>
        <div class="mt-stat-label">Laboratory</div>
        <div class="mt-stat-value"><?= number_format($referralsSummary['lab']) ?></div>
    </div>
</div>
