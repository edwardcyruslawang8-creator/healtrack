/* =============================================================
   MEDICATION DETAILS MODAL JS
   ---------------------------------------------------------------
   Shared by doctor/medications.php, medical-staff/medications.php,
   and subadmin/medications.php. Each row's "View Details" button
   (.med-view-details) opens #medDetailsModal
   (includes/shared/medication_details_modal.php), populated purely
   from that row's own data-med JSON — no network request, since the
   page already rendered every field server-side.

   On the doctor's page only (the modal carries data-manage-url), it
   also offers Extend / Renew, Discontinue and Confirm Completed,
   posted to api/update-medication.php; the page reloads afterwards
   so the row lands in the right Current/Past table.

   ajax-tab-nav.js re-fires DOMContentLoaded after swapping tabs, so
   every binding below is guarded against running twice on the same
   element.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('medDetailsModal');
    if (!modalEl || typeof bootstrap === 'undefined' || modalEl.dataset.mdvBound === '1') return;
    modalEl.dataset.mdvBound = '1';
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const manageUrl = modalEl.dataset.manageUrl || '';
    const $ = function (id) { return document.getElementById(id); };

    let current = null;   // the medication record being shown
    let openForm = null;  // 'extend' | 'discontinue' | 'confirm' | null

    function setText(id, value) {
        const v = value === null || value === undefined ? '' : String(value);
        $(id).textContent = v.trim() !== '' ? v : '—';
    }
    function show(id, visible) { const el = $(id); if (el) el.hidden = !visible; }

    function render(m) {
        current = m;
        setText('mdvName', m.name);
        setText('mdvCategory', m.category);
        setText('mdvDosage', m.dosage);
        setText('mdvFrequency', m.frequency);
        setText('mdvRoute', m.route);
        setText('mdvIndication', m.indication);
        setText('mdvDuration', m.duration);
        setText('mdvStartDate', m.startDate);
        setText('mdvEndDate', m.endLabel);
        setText('mdvPrescribedBy', m.prescribedBy);
        setText('mdvConsultation', m.consultationCode);
        setText('mdvNotes', m.endDateNote ? m.notes + ' (' + m.endDateNote + ')' : m.notes);

        const status = $('mdvStatus');
        status.textContent = m.statusLabel;
        status.className = 'mdc-status-pill mdc-tone-' + m.statusTone;

        const extended = m.originalEndDate && m.endDate && m.originalEndDate !== m.endDate;
        show('mdvOriginalEndRow', !!extended);
        if (extended) setText('mdvOriginalEndDate', m.originalEndDate);

        let detailLabel = '', detail = '';
        if (m.state === 'discontinued') {
            detailLabel = 'Reason Discontinued';
            detail = (m.discontinueReason || '—') + (m.discontinuedBy ? ' — ' + m.discontinuedBy : '') + (m.discontinuedDate ? ', ' + m.discontinuedDate : '');
        } else if (m.state === 'completed' && m.completionConfirmedBy) {
            detailLabel = 'Completion Confirmed';
            detail = m.completionConfirmedBy + (m.completionConfirmedDate ? ', ' + m.completionConfirmedDate : '');
        }
        show('mdvStatusDetailRow', detail !== '');
        if (detail !== '') { $('mdvStatusDetailLabel').textContent = detailLabel; $('mdvStatusDetail').textContent = detail; }

        const ext = $('mdvExtensions');
        ext.replaceChildren();
        (m.extensions || []).forEach(function (e) {
            const li = document.createElement('li');
            li.textContent = e.previousEndDate + ' → ' + e.newEndDate + ' by ' + e.by + (e.date ? ' on ' + e.date : '') + (e.reason ? ' — ' + e.reason : '');
            ext.appendChild(li);
        });
        show('mdvExtensionsRow', (m.extensions || []).length > 0);
        show('mdvEndedHint', m.state === 'ended' || m.state === 'lapsed');

        if (manageUrl) {
            const isCurrent = !!m.isCurrent;
            const ended = m.state === 'ended' || m.state === 'lapsed';
            show('mdvBtnExtend', isCurrent || ended);
            $('mdvBtnExtend').textContent = extendLabel(m);
            show('mdvBtnDiscontinue', isCurrent);
            show('mdvBtnConfirm', ended);
            openFormFor(null);
        }
    }

    // Ended course -> "Renew"; no end date yet (e.g. a medication recorded
    // at registration, or maintenance) -> "Set End Date", which is how it
    // later moves to Past by itself; otherwise "Extend".
    function extendLabel(m) {
        if (!m) return 'Extend';
        if (m.state === 'ended' || m.state === 'lapsed') return 'Renew';
        if (!m.endDate) return 'Set End Date';
        return 'Extend';
    }

    function openFormFor(kind) {
        openForm = kind;
        show('mdvExtendForm', kind === 'extend');
        show('mdvDiscontinueForm', kind === 'discontinue');
        show('mdvConfirmForm', kind === 'confirm');
        show('mdvManageError', false);
        show('mdvSubmit', kind !== null);
        const submit = $('mdvSubmit');
        submit.disabled = false;
        const label = extendLabel(current);
        submit.textContent = { extend: label === 'Extend' ? 'Save Extension' : label, discontinue: 'Discontinue', confirm: 'Confirm Completed' }[kind] || 'Save';

        if (kind === 'extend') {
            $('mdvExtendTitle').textContent = { Renew: 'Renew Medication', 'Set End Date': 'Set End Date', Extend: 'Extend Medication' }[label];
            const input = $('mdvNewEndDate');
            input.value = '';
            input.min = todayYmd();
            $('mdvExtendReason').value = '';
            input.focus();
        } else if (kind === 'discontinue') {
            $('mdvDiscontinueReason').value = '';
            $('mdvDiscontinueReason').focus();
        } else if (kind === 'confirm') {
            $('mdvConfirmNote').value = '';
        }
    }

    // Today in the clinic's timezone — matches the server's
    // hc_medication_today(), regardless of this browser's own timezone.
    function todayYmd() {
        try {
            return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
        } catch (e) {
            return new Date().toISOString().slice(0, 10);
        }
    }

    function fail(message) {
        const el = $('mdvManageError');
        el.textContent = message;
        el.hidden = false;
        $('mdvSubmit').disabled = false;
    }

    if (manageUrl) {
        modalEl.querySelectorAll('[data-mdv-open]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openFormFor(openForm === btn.dataset.mdvOpen ? null : btn.dataset.mdvOpen);
            });
        });

        $('mdvSubmit').addEventListener('click', function () {
            if (!current || !openForm) return;
            const payload = { id: current.id, action: openForm };
            if (openForm === 'extend') {
                payload.newEndDate = $('mdvNewEndDate').value;
                payload.reason = $('mdvExtendReason').value.trim();
                if (!payload.newEndDate) return fail('Please choose a new end date.');
            } else if (openForm === 'discontinue') {
                payload.reason = $('mdvDiscontinueReason').value.trim();
                if (!payload.reason) return fail('Please give a reason for discontinuing.');
            } else {
                payload.reason = $('mdvConfirmNote').value.trim();
            }

            $('mdvSubmit').disabled = true;
            fetch(manageUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            })
                .then(function (r) { return r.json().catch(function () { return { success: false, error: 'Unexpected server response.' }; }); })
                .then(function (res) {
                    if (!res.success) return fail(res.error || 'Could not save the change.');
                    window.location.reload();
                })
                .catch(function () { fail('Network error — please try again.'); });
        });
    }

    document.querySelectorAll('.med-view-details').forEach(function (btn) {
        if (btn.dataset.mdvBound === '1') return;
        btn.dataset.mdvBound = '1';
        btn.addEventListener('click', function () {
            const row = btn.closest('tr');
            if (!row || !row.dataset.med) return;
            let med;
            try { med = JSON.parse(row.dataset.med); } catch (e) { return; }
            render(med);
            modal.show();
        });
    });
});
