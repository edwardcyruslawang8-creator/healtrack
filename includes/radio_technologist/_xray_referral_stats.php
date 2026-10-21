<?php
/**
 * =====================================================================
 * Referral Inbox stat card partial
 * ---------------------------------------------------------------------
 * Shared verbatim between radio_technologist/xray_consultation.php's
 * initial page load (wrapped in #xcReferralStats there) and
 * api/radiotech-xray-queue-status.php (polled by
 * assets/js/radiotech_xray_consultation.js) — split out from
 * _xray_referral_table.php specifically so a live refresh only ever
 * touches this read-only counter, never the filter toolbar's search
 * input sitting between them (which could hold text the technologist
 * is still typing).
 *
 * Expects $referralsSummary already set by the includer (see
 * includes/radio_technologist/xray_consultation_firestore.php).
 * =====================================================================
 */
?>
<div class="mt-stats-row lxr-stats-row lxc-stats-row">
    <div class="mt-stat-card">
        <div class="mt-stat-icon" style="background:#E8F0FE; color:#1A73E8;"><i class="bi bi-inboxes-fill"></i></div>
        <div class="mt-stat-label">Pending X-ray Referrals</div>
        <div class="mt-stat-value"><?= number_format($referralsSummary['total']) ?></div>
    </div>
</div>
