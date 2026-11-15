<?php
/**
 * =====================================================================
 * Current / Past Medications tables + Medication History timeline
 * ---------------------------------------------------------------------
 * Shared by doctor/medications.php, medical-staff/medications.php and
 * subadmin/medications.php (previously three identical copies).
 *
 * Expects: $currentMedications, $pastMedications (split by each row's
 * 'isCurrent', see includes/firestore/medications.php's
 * hc_medication_classify() — recalculated on every page view, so a
 * prescription whose period has ended is already under Past here
 * without anyone moving it) and $medicationHistory.
 *
 * Each row carries its full record as data-med (JSON) for the View
 * Details modal (assets/js/medication-details.js).
 * =====================================================================
 */

$hcMedListSections = [
    // Current Medications leaves out Indication / Reason to keep the table
    // compact — the full text is still in each row's data-med and shown in
    // the View Details modal.
    ['title' => 'Current Medications', 'sub' => 'Medications the patient is currently taking, including ongoing maintenance medications.', 'rows' => $currentMedications, 'empty' => 'No current medications recorded.', 'showIndication' => false, 'class' => 'mdc-table-current'],
    ['title' => 'Past Medications', 'sub' => 'Medications whose treatment period has ended, or that were discontinued or completed. Moved here automatically.', 'rows' => $pastMedications, 'empty' => 'No past medications recorded.', 'showIndication' => true, 'class' => ''],
];
?>
<?php foreach ($hcMedListSections as $section): ?>
<?php $hcColCount = $section['showIndication'] ? 9 : 8; ?>
    <div class="mdc-list-header">
        <h3><?= htmlspecialchars($section['title']) ?></h3>
        <p><?= htmlspecialchars($section['sub']) ?></p>
    </div>

    <div class="adm-card mdc-table-card">
        <div class="adm-table-wrap">
            <table class="adm-table mdc-table <?= $section['class'] ?>">
                <thead>
                    <tr>
                        <th>Medication</th>
                        <th>Dosage</th>
                        <th>Frequency</th>
                        <th>Route</th>
                        <?php if ($section['showIndication']): ?>
                        <th>Indication / Reason</th>
                        <?php endif; ?>
                        <th>Treatment Period</th>
                        <th>Prescribed By</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($section['rows'])): ?>
                    <tr>
                        <td colspan="<?= $hcColCount ?>" class="adm-table-empty"><?= htmlspecialchars($section['empty']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($section['rows'] as $med): ?>
                    <tr data-med="<?= htmlspecialchars(json_encode($med, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)) ?>">
                        <td>
                            <div class="mdc-name-cell">
                                <span class="mdc-strong"><?= htmlspecialchars($med['name']) ?></span>
                                <span class="mdc-category">(<?= htmlspecialchars($med['category']) ?>)</span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($med['dosage']) ?></td>
                        <td><?= htmlspecialchars($med['frequency']) ?></td>
                        <td><?= htmlspecialchars($med['route']) ?></td>
                        <?php if ($section['showIndication']): ?>
                        <td><?= htmlspecialchars($med['indication']) ?></td>
                        <?php endif; ?>
                        <td>
                            <div class="mdc-period-cell">
                                <span class="mdc-period-dates"><?= htmlspecialchars($med['startDate']) ?> &ndash; <?= htmlspecialchars($med['endLabel']) ?></span>
                                <span class="mdc-status-pill mdc-tone-<?= htmlspecialchars($med['statusTone']) ?>"><?= htmlspecialchars($med['statusLabel']) ?></span>
                            </div>
                        </td>
                        <td class="mdc-strong"><?= htmlspecialchars($med['prescribedBy']) ?></td>
                        <td class="mdc-notes-cell"><?= htmlspecialchars($med['notes']) ?></td>
                        <td>
                            <div class="adm-action-btns">
                                <button type="button" class="mdc-view-details-btn med-view-details">View Details</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-table-footer">
            <span class="adm-showing-text"><?= count($section['rows']) > 0 ? 'Showing ' . count($section['rows']) . ' of ' . count($section['rows']) . ' entries' : 'No entries to show' ?></span>
        </div>
    </div>
<?php endforeach; ?>

    <!-- ============ MEDICATION HISTORY TIMELINE ============ -->
    <div class="adm-card mdc-history-card">
        <div class="mdc-card-header"><i class="bi bi-info-circle-fill"></i> <h3>Medication History</h3></div>
        <p class="mdc-history-sub">A timeline of changes made to the patient's medications.</p>

        <ul class="mdc-timeline">
            <?php foreach ($medicationHistory as $i => $entry): ?>
            <?php $hcDotColor = (string) $entry['indicatorColor']; ?>
            <li class="mdc-timeline-item">
                <div class="mdc-timeline-marker">
                    <?php if (preg_match('/^#[0-9A-Fa-f]{3,8}$/', $hcDotColor)): // older entries store a hex colour ?>
                    <span class="mdc-timeline-dot" style="background:<?= $hcDotColor ?>"></span>
                    <?php else: ?>
                    <span class="mdc-timeline-dot mdc-dot-<?= htmlspecialchars($hcDotColor) ?>"></span>
                    <?php endif; ?>
                    <?php if ($i < count($medicationHistory) - 1): ?>
                    <span class="mdc-timeline-line"></span>
                    <?php endif; ?>
                </div>
                <div class="mdc-timeline-content">
                    <span class="mdc-timeline-date"><?= htmlspecialchars($entry['date']) ?></span>
                    <span class="mdc-timeline-event"><?= htmlspecialchars($entry['event']) ?></span>
                    <span class="mdc-timeline-detail"><?= htmlspecialchars($entry['detail']) ?></span>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
