<?php
/**
 * =====================================================================
 * Doctor Queue Cards partial ("Patient Information" / "Ongoing
 * Consultation" / "Next to Consult")
 * ---------------------------------------------------------------------
 * Shared verbatim between doctor/consultations.php's initial page load
 * (wrapped in #cnsQueueStatus there) and api/doctor-queue-status.php
 * (polled by assets/js/doctor_consultations.js so this section updates
 * itself once medical staff sets a new consultation, without the
 * doctor needing to reload the page) — the SAME markup either way, so
 * a live refresh can never drift out of sync with what a real page
 * load would have shown.
 *
 * Expects $patient, $ongoingConsult, $ongoingPatient, $ongoingAwaitingLab,
 * $nextConsult, $nextPatient, $consultationTypeMeta,
 * $consultationStatusMeta already set by the includer
 * (hc_get_doctor_queue_status() — see includes/firestore/consultations.php).
 * =====================================================================
 */
?>
<!-- ============ PATIENT INFORMATION ============
     Same patient as "Next to Consult" below — an empty state shows
     instead when there's no pending consultation waiting on this
     doctor at all. -->
<?php if ($patient !== null): ?>
<div class="adm-card ppf-profile-card">
    <div class="ppf-identity">
        <h2 class="ppf-name"><?= htmlspecialchars($patient['name']) ?></h2>
        <p class="ppf-meta"><?= htmlspecialchars($patient['gender']) ?> &bull; <?= (int) $patient['age'] ?> years old (<?= htmlspecialchars($patient['birthDate']) ?>)</p>
        <div class="ppf-contact-row"><i class="bi bi-telephone-fill"></i> <?= htmlspecialchars($patient['phone']) ?><span class="ppf-contact-sep"></span><i class="bi bi-envelope-fill"></i> <?= htmlspecialchars($patient['email']) ?></div>
        <div class="ppf-contact-row"><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($patient['address']) ?></div>
    </div>

    <div class="ppf-info-col">
        <div class="ppf-info-item"><span class="ppf-info-label">Blood Type</span><span class="ppf-info-value"><?= htmlspecialchars($patient['bloodType']) ?></span></div>
        <div class="ppf-info-item"><span class="ppf-info-label">Civil Status</span><span class="ppf-info-value"><?= htmlspecialchars($patient['civilStatus']) ?></span></div>
        <div class="ppf-info-item"><span class="ppf-info-label">Occupation</span><span class="ppf-info-value"><?= htmlspecialchars($patient['occupation']) ?></span></div>
        <?php if (($patient['philhealthPin'] ?? '') !== ''): // optional — only shown when on file ?>
        <div class="ppf-info-item"><span class="ppf-info-label">PhilHealth PIN</span><span class="ppf-info-value"><?= htmlspecialchars($patient['philhealthPin']) ?></span></div>
        <?php endif; ?>
    </div>

    <div class="ppf-info-col">
        <div class="ppf-info-item"><span class="ppf-info-label">Registered On</span><span class="ppf-info-value"><?= htmlspecialchars($patient['registeredOn']) ?></span></div>
        <div class="ppf-info-item"><span class="ppf-info-label">Last Visit</span><span class="ppf-info-value"><?= htmlspecialchars($patient['lastVisit']) ?></span></div>
    </div>
</div>
<?php endif; ?>

<!-- ============ RESUME ONGOING CONSULTATION ============
     Only present when this doctor has a real "ongoing" consultation
     (started via "Proceed with Consultation" below, or a lab
     follow-up already picked up) — the only way back into
     active_consultation.php if they navigated away or reloaded
     mid-consultation, since that page itself has no other link to
     it. Shown ABOVE "Next to Consult" rather than replacing it —
     both can be true at once (an ongoing one plus more waiting).

     While $ongoingAwaitingLab is true (routed to the lab, at least one
     ordered test still "pending" — hc_consultation_is_awaiting_lab_results())
     "Resume Consultation" is replaced with a disabled, non-clickable
     waiting state instead: the patient is physically at the lab right
     now, so there's nothing for the doctor to resume documenting until
     a result comes back (doctor/active_consultation_doctor_firestore.php
     enforces this same rule server-side too, in case this card's link
     is ever bypassed). It flips back to a real "Resume Consultation"
     link on its own the moment every ordered test is completed —
     nothing here needs to separately clear it. -->
<?php
// Every ongoing consultation, newest first (hc_get_doctor_queue_status()'s
// ongoingList) — one card each, so an older one that's still open (e.g.
// left mid-consultation, or waiting on results) is never hidden behind a
// newer one. Falls back to the single $ongoingConsult for any includer
// that doesn't pass $ongoingList.
$ongoingCards = $ongoingList ?? (($ongoingConsult !== null && $ongoingPatient !== null)
    ? [['consult' => $ongoingConsult, 'patient' => $ongoingPatient, 'awaiting' => $ongoingAwaitingLab, 'awaitingStatusLabel' => 'In Laboratory', 'awaitingButtonLabel' => 'Waiting for Laboratory Results']]
    : []);
foreach (array_values($ongoingCards) as $ongoingIndex => $ongoingCard):
    $oc = $ongoingCard['consult'];
    $op = $ongoingCard['patient'];
    $ocAwaiting = $ongoingCard['awaiting'];
?>
<div class="cns-next-card">
    <div class="cns-next-identity">
        <div class="cns-next-avatar"><i class="bi <?= $ocAwaiting ? 'bi-hourglass-split' : 'bi-play-circle-fill' ?>"></i></div>
        <div>
            <div class="cns-next-label">Ongoing Consultation<?= count($ongoingCards) > 1 ? ' <span class="cns-ongoing-count">' . ($ongoingIndex + 1) . ' of ' . count($ongoingCards) . '</span>' : '' ?></div>
            <div class="cns-next-name"><?= htmlspecialchars($op['name']) ?></div>
            <div class="cns-next-meta"><?= (int) $op['age'] ?> years old (<?= htmlspecialchars($op['gender']) ?>)</div>
        </div>
    </div>

    <div class="cns-next-details">
        <div class="cns-next-field">
            <span class="cns-next-field-label">Consultation Type</span>
            <span class="cns-next-field-value">
                <?php $ongoingType = $consultationTypeMeta[$oc['consultationType']] ?? $consultationTypeMeta['walk_in']; ?>
                <span class="cns-badge" style="background:<?= htmlspecialchars($ongoingType['bg']) ?>; color:<?= htmlspecialchars($ongoingType['color']) ?>;"><?= htmlspecialchars($ongoingType['label']) ?></span>
            </span>
        </div>
        <div class="cns-next-field">
            <span class="cns-next-field-label">Status</span>
            <span class="cns-next-field-value">
                <?php $ongoingStatus = $consultationStatusMeta[$oc['status']] ?? $consultationStatusMeta['ongoing']; ?>
                <span class="cns-badge" style="background:<?= htmlspecialchars($ongoingStatus['bg']) ?>; color:<?= htmlspecialchars($ongoingStatus['color']) ?>;"><?= htmlspecialchars($ocAwaiting ? $ongoingCard['awaitingStatusLabel'] : $ongoingStatus['label']) ?></span>
            </span>
        </div>
        <div class="cns-next-field">
            <span class="cns-next-field-label">Started</span>
            <span class="cns-next-field-value"><?= $oc['startedAt'] !== '' ? htmlspecialchars(date('M j, g:i A', strtotime($oc['startedAt']))) : '—' ?></span>
        </div>
        <div class="cns-next-field">
            <span class="cns-next-field-label">Reason for Visit</span>
            <span class="cns-next-field-value"><?= htmlspecialchars($oc['chiefComplaint']) ?></span>
        </div>
    </div>

    <?php if ($ocAwaiting): ?>
    <div class="cns-btn-start cns-btn-waiting" aria-disabled="true" title="Waiting for this patient's results — not back yet.">
        <i class="bi bi-hourglass-split"></i> <?= htmlspecialchars($ongoingCard['awaitingButtonLabel']) ?>
    </div>
    <?php else: ?>
    <a href="active_consultation.php?id=<?= urlencode($oc['id']) ?>" class="cns-btn-start"><i class="bi bi-arrow-right-circle"></i> Resume Consultation</a>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- ============ NEXT TO CONSULT ============ -->
<?php if ($nextConsult !== null && $nextPatient !== null): ?>
<div class="cns-next-card">
    <div class="cns-next-identity">
        <div class="cns-next-avatar"><i class="bi bi-person-fill"></i></div>
        <div>
            <div class="cns-next-label">Next to Consult</div>
            <div class="cns-next-name"><?= htmlspecialchars($nextPatient['name']) ?></div>
            <div class="cns-next-meta"><?= (int) $nextPatient['age'] ?> years old (<?= htmlspecialchars($nextPatient['gender']) ?>)</div>
        </div>
    </div>

    <div class="cns-next-details">
        <div class="cns-next-field">
            <span class="cns-next-field-label">Consultation Type</span>
            <span class="cns-next-field-value">
                <?php $nextType = $consultationTypeMeta[$nextConsult['consultationType']] ?? $consultationTypeMeta['walk_in']; ?>
                <span class="cns-badge" style="background:<?= htmlspecialchars($nextType['bg']) ?>; color:<?= htmlspecialchars($nextType['color']) ?>;"><?= htmlspecialchars($nextType['label']) ?></span>
            </span>
        </div>
        <div class="cns-next-field">
            <span class="cns-next-field-label">Arrival Time</span>
            <span class="cns-next-field-value"><?= $nextConsult['dateTime'] !== '' ? htmlspecialchars(date('g:i A', strtotime($nextConsult['dateTime']))) : '—' ?></span>
        </div>
        <div class="cns-next-field">
            <span class="cns-next-field-label">Reason for Visit</span>
            <span class="cns-next-field-value"><?= htmlspecialchars($nextConsult['chiefComplaint']) ?></span>
        </div>
    </div>

    <?php $nextStatus = $consultationStatusMeta[$nextConsult['status']] ?? $consultationStatusMeta['pending']; ?>
    <button type="button" class="cns-btn-start" id="btnStartConsultation"
        data-id="<?= htmlspecialchars($nextConsult['id']) ?>"
        data-patient-id="<?= htmlspecialchars($nextConsult['patientId']) ?>"
        data-patient-name="<?= htmlspecialchars($nextPatient['name']) ?>"
        data-patient-age="<?= (int) $nextPatient['age'] ?>"
        data-patient-gender="<?= htmlspecialchars($nextPatient['gender']) ?>"
        data-type-label="<?= htmlspecialchars($nextType['label']) ?>"
        data-type-bg="<?= htmlspecialchars($nextType['bg']) ?>"
        data-type-color="<?= htmlspecialchars($nextType['color']) ?>"
        data-arrival-time="<?= $nextConsult['dateTime'] !== '' ? htmlspecialchars(date('g:i A', strtotime($nextConsult['dateTime']))) : '—' ?>"
        data-reason="<?= htmlspecialchars($nextConsult['chiefComplaint']) ?>"
        data-status-label="<?= htmlspecialchars($nextStatus['label']) ?>"
        data-status-bg="<?= htmlspecialchars($nextStatus['bg']) ?>"
        data-status-color="<?= htmlspecialchars($nextStatus['color']) ?>">
        <i class="bi bi-play-fill"></i> Start Consultation
    </button>
</div>
<?php else: ?>
<div class="cns-next-card">
    <div class="cns-next-identity">
        <div class="cns-next-avatar"><i class="bi bi-check2-circle"></i></div>
        <div>
            <div class="cns-next-label">Next to Consult</div>
            <div class="cns-next-name">No pending consultations</div>
            <div class="cns-next-meta">You're all caught up — nothing waiting in your queue right now.</div>
        </div>
    </div>
</div>
<?php endif; ?>
