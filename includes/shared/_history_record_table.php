<!-- =============================================================
     CONSULTATION HISTORY TABLE (shared fragment)
     -------------------------------------------------------------
     Required verbatim by doctor/history.php, medical-staff/history.php,
     and subadmin/history.php (byte-identical markup across all three —
     confirmed before extracting this) AND by api/history-record-table.php,
     the AJAX endpoint assets/js/patient-history-filters.js calls when
     the Type/Date Range filter or a pagination link changes, so picking
     a filter re-renders just this card instead of the whole page (same
     "shared fragment, two callers" pattern api/doctor-queue-status.php
     already established for includes/doctor/_consultation_queue_cards.php).

     Real consultations/{id} docs for this one patient, already narrowed
     to 'completed' visits only by the includer — NOT the generic
     patient_record_history audit trail, and no Status column since
     every row here is the same status by definition.

     Expects $recordHistory, $consultationTypeMeta,
     $recordHistoryTotal, $recordHistoryCurrentPage, $recordHistoryPerPage,
     $recordHistoryTotalPages, and hc_render_pagination_nav() already in
     scope — exactly what each role's own history_*_firestore.php data
     file already provides.

     Each row's "View Details" button is a ".vdm-trigger" — the generic,
     reusable read-only detail modal (includes/shared/view_details_modal.php
     + assets/js/view-details-action.js), which each of the 3 history.php
     pages includes once, inside <main> but OUTSIDE this AJAX-swapped
     card (so the modal survives a filter/pagination refresh even though
     these rows don't — api/history-record-table.php only ever re-sends
     THIS card). Staying inside <main> (not a sibling of it) matters for
     a different reason: assets/js/ajax-tab-nav.js's Patient Profile tab
     switch swaps <main> wholesale, so anything the "View Details" button
     needs has to actually be part of what that swap brings in. No
     second request either way — the button's own data-vdm-fields JSON
     already carries everything the row has.

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
                    <th>Type</th>
                    <th>Reason for Visit / Diagnosis</th>
                    <th>Attended By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recordHistory)): ?>
                <tr>
                    <td colspan="5" class="adm-table-empty">No completed consultations recorded yet.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($recordHistory as $rec): $type = $consultationTypeMeta[$rec['consultationType']] ?? ['label' => ucfirst($rec['consultationType']), 'bg' => '#EEF0F3', 'color' => '#6B7280']; ?>
                <tr>
                    <td class="his-datetime"><?= htmlspecialchars($rec['date']) ?> <span class="his-dot"></span> <?= htmlspecialchars($rec['time']) ?></td>
                    <td><span class="his-badge" style="background:<?= htmlspecialchars($type['bg']) ?>; color:<?= htmlspecialchars($type['color']) ?>;"><?= htmlspecialchars($type['label']) ?></span></td>
                    <td class="his-details-cell"><?= htmlspecialchars($rec['detail']) ?></td>
                    <td class="his-strong"><?= htmlspecialchars($rec['assignedPersonnel']) ?></td>
                    <td>
                        <button type="button" class="vdm-trigger"
                            data-vdm-title="<?= htmlspecialchars($rec['consultationCode']) ?>"
                            data-vdm-fields="<?= htmlspecialchars(json_encode([
                                'Date & Time' => $rec['date'] . ' ' . $rec['time'],
                                'Type' => $type['label'],
                                'Chief Complaint' => $rec['chiefComplaint'],
                                'Diagnosis' => $rec['diagnosis'],
                                'Attended By' => $rec['assignedPersonnel'],
                                'Notes' => $rec['notes'],
                            ])) ?>"
                        ><i class="bi bi-eye"></i> View Details</button>
                    </td>
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
