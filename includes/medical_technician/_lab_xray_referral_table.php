<?php
/**
 * =====================================================================
 * Referral Inbox table + pagination partial
 * ---------------------------------------------------------------------
 * Shared verbatim between medical_technician/lab_xray_consultation.php's
 * initial page load (wrapped in #lxcReferralTable there) and
 * api/medtech-referral-queue-status.php (polled by
 * assets/js/medtech_lab_xray_consultation.js so this table updates
 * itself once a doctor sends a new laboratory referral, without the
 * technician needing to reload the page) — the SAME markup either way,
 * so a live refresh can never show something different than a real
 * page load would have. See includes/medical_technician/_lab_xray_referral_stats.php's
 * header comment for why the stat cards are a SEPARATE live-refresh
 * zone rather than bundled in here.
 *
 * Expects $referralRows, $referralsTotal, $referralsPerPage,
 * $referralsCurrentPage, $referralsTotalPages,
 * $referralsPaginationWindow already set by the includer (see
 * includes/medical_technician/lab_xray_consultation_firestore.php).
 * =====================================================================
 */
?>
<div class="adm-card">
    <div class="adm-table-wrap">
        <table class="adm-table lxr-table">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Type</th>
                    <th>Test / Examination</th>
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
                    <td colspan="9" class="adm-table-empty">No patients currently waiting on laboratory testing.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($referralRows as $r): ?>
                <tr>
                    <td>
                        <div class="lxr-strong"><?= htmlspecialchars($r['patientName']) ?></div>
                        <div class="lxr-patient-meta"><?= (int) $r['patientAge'] ?> years old</div>
                    </td>
                    <td><span class="lxr-badge" style="background:#EFE7FB; color:#7C3AED;"><?= htmlspecialchars($r['typeLabel']) ?></span></td>
                    <td><?= htmlspecialchars($r['examination']) ?></td>
                    <td><?= htmlspecialchars($r['requestedBy']) ?></td>
                    <td><?= htmlspecialchars($r['facility']) ?></td>
                    <td class="lxr-datetime"><?= htmlspecialchars($r['date']) ?><span class="lxr-dot"></span><?= htmlspecialchars($r['time']) ?></td>
                    <td><span class="lxr-badge" style="background:<?= htmlspecialchars($r['priority']['bg']) ?>; color:<?= htmlspecialchars($r['priority']['color']) ?>;"><?= htmlspecialchars($r['priority']['label']) ?></span></td>
                    <td><?= $r['assignedToName'] !== null ? htmlspecialchars($r['assignedToName']) : '<span class="lxr-report-none">Unassigned</span>' ?></td>
                    <?php
                    // "Consult" always goes straight to whichever form
                    // actually lets the technician fill in THIS test's
                    // result — one of the 4 named paper-form replicas
                    // when the test matches one of those, otherwise the
                    // generic lab result form (medical_technician/
                    // lab_result_form.php — see its own header comment)
                    // covering every other test in hc_lab_test_catalog().
                    // Never laboratory_result_details.php directly —
                    // that's a read-only view, not where a result gets
                    // entered. No X-ray branch here — every row on this
                    // page is 'lab' by construction (see this file's
                    // header comment).
                    if ($r['isSerologyRapid']) {
                        $consultHref = 'serology_rapid_test_form.php?patientId=' . urlencode($r['patientId']);
                    } elseif ($r['isUrinalysis']) {
                        $consultHref = 'urinalysis_form.php?patientId=' . urlencode($r['patientId']);
                    } elseif ($r['isCD4Count']) {
                        $consultHref = 'cd4_count_form.php?patientId=' . urlencode($r['patientId']);
                    } elseif ($r['isGramStain']) {
                        $consultHref = 'gram_stain_form.php?patientId=' . urlencode($r['patientId']);
                    } else {
                        $consultHref = 'lab_result_form.php?patientId=' . urlencode($r['patientId']) . '&recordId=' . urlencode($r['id']);
                    }
                    ?>
                    <td>
                        <div class="adm-action-btns">
                            <a href="<?= htmlspecialchars($consultHref) ?>" class="lxr-consult-btn lxr-btn-maroon">Consult</a>
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
            <a href="?<?= hc_medtech_lxc_query(['page' => 1]) ?>" class="page-btn" aria-label="First page"><i class="bi bi-chevron-bar-left"></i></a>
            <a href="?<?= hc_medtech_lxc_query(['page' => max(1, $referralsCurrentPage - 1)]) ?>" class="page-btn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
            <?php foreach ($referralsPaginationWindow as $p): ?>
                <?php if ($p === '...'): ?>
                <span class="page-ellipsis">&hellip;</span>
                <?php else: ?>
                <a href="?<?= hc_medtech_lxc_query(['page' => $p]) ?>" class="page-btn <?= $p === $referralsCurrentPage ? 'active' : '' ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <a href="?<?= hc_medtech_lxc_query(['page' => min($referralsTotalPages, $referralsCurrentPage + 1)]) ?>" class="page-btn" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
            <a href="?<?= hc_medtech_lxc_query(['page' => $referralsTotalPages]) ?>" class="page-btn" aria-label="Last page"><i class="bi bi-chevron-bar-right"></i></a>
        </nav>
    </div>
</div>
