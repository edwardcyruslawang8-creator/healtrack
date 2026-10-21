<?php
/**
 * =====================================================================
 * Referral Inbox table + pagination partial
 * ---------------------------------------------------------------------
 * Shared verbatim between radiologic_technologist/xray_consultation.php's
 * initial page load (wrapped in #xcReferralTable there) and
 * api/radiotech-xray-queue-status.php (polled by
 * assets/js/radiotech_xray_consultation.js so this table updates
 * itself once a doctor sends a new X-ray referral, without the
 * technologist needing to reload the page) — the SAME markup either
 * way, so a live refresh can never show something different than a
 * real page load would have.
 *
 * Expects $referralRows, $referralsTotal, $referralsPerPage,
 * $referralsCurrentPage, $referralsTotalPages,
 * $referralsPaginationWindow already set by the includer (see
 * includes/radiologic_technologist/xray_consultation_firestore.php).
 * =====================================================================
 */
?>
<div class="adm-card">
    <div class="adm-table-wrap">
        <table class="adm-table lxr-table">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Examination</th>
                    <th>Body Part</th>
                    <th>Sent By</th>
                    <th>Facility</th>
                    <th>Date &amp; Time</th>
                    <th>Priority</th>
                    <th>Assigned To</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($referralRows)): ?>
                <tr>
                    <td colspan="9" class="adm-table-empty">No patients currently waiting on an X-ray examination.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($referralRows as $r): ?>
                <tr>
                    <td>
                        <div class="lxr-strong"><?= htmlspecialchars($r['patientName']) ?></div>
                        <div class="lxr-patient-meta"><?= (int) $r['patientAge'] ?> years old</div>
                    </td>
                    <td><?= htmlspecialchars($r['examination']) ?></td>
                    <td><?= htmlspecialchars($r['bodyPart']) ?></td>
                    <td><?= htmlspecialchars($r['requestedBy']) ?></td>
                    <td><?= htmlspecialchars($r['facility']) ?></td>
                    <td class="lxr-datetime"><?= htmlspecialchars($r['date']) ?><span class="lxr-dot"></span><?= htmlspecialchars($r['time']) ?></td>
                    <td><span class="lxr-badge" style="background:<?= htmlspecialchars($r['priority']['bg']) ?>; color:<?= htmlspecialchars($r['priority']['color']) ?>;"><?= htmlspecialchars($r['priority']['label']) ?></span></td>
                    <td><?= $r['assignedToName'] !== null ? htmlspecialchars($r['assignedToName']) : '<span class="lxr-report-none">Unassigned</span>' ?></td>
                    <td>
                        <div class="adm-action-btns">
                            <a href="xray_result_form.php?patientId=<?= urlencode($r['patientId']) ?>&recordId=<?= urlencode($r['id']) ?>" class="lxr-consult-btn">Consult</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="adm-table-footer">
        <span class="adm-showing-text"><?= $referralsTotal > 0 ? 'Showing ' . (($referralsCurrentPage - 1) * $referralsPerPage + 1) . ' to ' . min($referralsCurrentPage * $referralsPerPage, $referralsTotal) . ' of ' . $referralsTotal . ' entries' : 'No entries to show' ?></span>
        <nav class="adm-pagination" aria-label="Referrals pages">
            <a href="?<?= hc_radiotech_xc_query(['page' => 1]) ?>" class="page-btn" aria-label="First page"><i class="bi bi-chevron-bar-left"></i></a>
            <a href="?<?= hc_radiotech_xc_query(['page' => max(1, $referralsCurrentPage - 1)]) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
            <?php foreach ($referralsPaginationWindow as $p): ?>
                <?php if ($p === '...'): ?>
                <span class="page-ellipsis">&hellip;</span>
                <?php else: ?>
                <a href="?<?= hc_radiotech_xc_query(['page' => $p]) ?>" class="page-btn <?= $p === $referralsCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <a href="?<?= hc_radiotech_xc_query(['page' => min($referralsTotalPages, $referralsCurrentPage + 1)]) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
            <a href="?<?= hc_radiotech_xc_query(['page' => $referralsTotalPages]) ?>" class="page-btn" aria-label="Last page"><i class="bi bi-chevron-bar-right"></i></a>
        </nav>
    </div>
</div>
