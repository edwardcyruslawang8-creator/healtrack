<!-- =============================================================
     RECORD HISTORY TABLE (shared fragment)
     -------------------------------------------------------------
     Required verbatim by doctor/history.php, medical-staff/history.php,
     and subadmin/history.php (byte-identical markup across all three —
     confirmed before extracting this) AND by api/history-record-table.php,
     the AJAX endpoint assets/js/patient-history-filters.js calls when
     the Record Type/Date Range filter or a pagination link changes, so
     picking a filter re-renders just this card instead of the whole
     page (same "shared fragment, two callers" pattern
     api/doctor-queue-status.php already established for
     includes/doctor/_consultation_queue_cards.php).

     Expects $recordHistory, $recordTypeMeta, $actionMeta,
     $recordHistoryTotal, $recordHistoryCurrentPage, $recordHistoryPerPage,
     $recordHistoryTotalPages, and hc_render_pagination_nav() already in
     scope — exactly what each role's own history_*_firestore.php data
     file already provides.

     #hisRecordTableWrap is the AJAX swap target — the whole card
     (table + footer), NOT just the <tbody>, since the "Showing X to Y"
     text and the pagination nav both need to refresh too.
     ============================================================= -->
<div class="adm-card his-table-card" id="hisRecordTableWrap">
    <div class="adm-table-wrap">
        <table class="adm-table his-table">
            <thead>
                <tr>
                    <th>Date &amp; Time</th>
                    <th>Record Type</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>Changed By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recordHistory)): ?>
                <tr>
                    <td colspan="5" class="adm-table-empty">No history recorded yet.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($recordHistory as $rec): $type = $recordTypeMeta[$rec['type']] ?? ['label' => ucfirst($rec['type']), 'icon' => 'bi-clock-history', 'color' => '#6B7280']; $action = $actionMeta[$rec['action']] ?? $actionMeta['updated']; ?>
                <tr>
                    <td class="his-datetime"><?= htmlspecialchars($rec['date']) ?> <span class="his-dot"></span> <?= htmlspecialchars($rec['time']) ?></td>
                    <td>
                        <div class="his-type-cell">
                            <i class="bi <?= htmlspecialchars($type['icon']) ?>" style="color:<?= htmlspecialchars($type['color']) ?>;"></i>
                            <span><?= htmlspecialchars($type['label']) ?></span>
                        </div>
                    </td>
                    <td><span class="his-badge" style="background:<?= htmlspecialchars($action['bg']) ?>; color:<?= htmlspecialchars($action['color']) ?>;"><?= htmlspecialchars($action['label']) ?></span></td>
                    <td class="his-details-cell"><?= htmlspecialchars($rec['details']) ?></td>
                    <td class="his-strong"><?= htmlspecialchars($rec['changedBy']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="adm-table-footer">
        <span class="adm-showing-text"><?= $recordHistoryTotal > 0 ? 'Showing ' . (($recordHistoryCurrentPage - 1) * $recordHistoryPerPage + 1) . ' to ' . (($recordHistoryCurrentPage - 1) * $recordHistoryPerPage + count($recordHistory)) . ' of ' . $recordHistoryTotal . ' entries' : 'No entries to show' ?></span>
        <?= hc_render_pagination_nav('Record history pages', 'recordPage', $recordHistoryCurrentPage, $recordHistoryTotalPages) ?>
    </div>
</div>
