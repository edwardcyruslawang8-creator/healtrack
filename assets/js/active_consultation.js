/* =============================================================
   ACTIVE CONSULTATION JS (doctor/active_consultation.php)
   ---------------------------------------------------------------
   Drives every interactive piece on this page:
     - Character counters on the plain textareas + the rich-text
       Prescription editor (data-for -> element id, counts
       .value.length / .textContent.length against maxlength).
     - The Prescription editor itself: a contenteditable div +
       document.execCommand() toolbar (bold/italic/underline/lists/
       link) — no external rich-text library anywhere in this app, so
       this stays dependency-free rather than pulling one in.
     - Medications & Vaccines: one shared row-management pattern
       (add/remove/renumber) adapted directly from
       assets/js/new-stock-request.js's addItemRow()/updateRemoveButtons(),
       just with more columns; the Medications/Vaccines tabs filter
       which rows are VISIBLE (by a data-type on each row) — both types'
       rows submit together as one array either way.
     - "Patient Will Be Next In" -> Laboratory reveals a "Select
       Laboratory Tests" checkbox panel (hc_lab_test_catalog(),
       includes/firestore/lab_results.php — at least one required).
       Confirm collapses it to a compact summary ("Change" reopens it);
       Cancel clears every checkbox and deselects Laboratory entirely.
       Re-clicking an already-selected Laboratory radio toggles it OFF
       and hides the panel too — plain radio inputs don't support that
       natively (no "change" event fires from clicking one that's
       already checked), so this is done manually via each radio's own
       click listener + a data-was-checked flag, not a "change"
       listener. api/complete-consultation.php creates one real pending
       lab_results doc PER selected test, which the medical
       technician's own queue/notifications already pick up (status
       "pending") with no wiring needed on their side.
     - Save & End Consultation: gathers every field into one payload,
       POSTs api/complete-consultation.php, redirects back to
       consultations.php on success.
     - Cancel Consultation: HCDialog.confirm() + POSTs the EXISTING
       api/update-consultation.php ({id, status:'cancelled'}) — no new
       endpoint needed for this one, same file doctor/consultations.php's
       own "Start Consultation" already uses.
     - Unsaved-changes guard: this page has no autosave/draft, and isn't
       reachable from the sidebar (only via "Proceed with Consultation"
       on doctor/consultations.php) — so navigating away any other way
       (a sidebar link, the browser's back button, an accidental tab
       close/refresh) would otherwise silently lose everything typed.
       A standard beforeunload prompt covers all of those; it's turned
       off right before the two INTENTIONAL navigations this page makes
       itself (Save & End success, Cancel success).
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    let formDirty = false;
    document.getElementById('acvForm').addEventListener('input', function () { formDirty = true; }, true);
    document.getElementById('acvForm').addEventListener('change', function () { formDirty = true; }, true);
    window.addEventListener('beforeunload', function (e) {
        if (!formDirty) return;
        e.preventDefault();
        e.returnValue = '';
    });

    // -----------------------------------------------------------
    // Character counters
    // -----------------------------------------------------------
    document.querySelectorAll('.acv-char-count').forEach(function (counter) {
        const targetId = counter.dataset.for;
        const target = document.getElementById(targetId);
        if (!target) return;
        const max = target.tagName === 'TEXTAREA' ? parseInt(target.getAttribute('maxlength'), 10) : 2000;

        function update() {
            const len = target.tagName === 'TEXTAREA' ? target.value.length : target.textContent.length;
            counter.textContent = len + '/' + max;
        }
        target.addEventListener('input', update);
        update();
    });

    // -----------------------------------------------------------
    // Add Allergy — writes immediately (api/add-allergy.php), separate
    // from Save & End: allergies are real, patient-wide records (same
    // "allergies" collection every role's Allergies page reads), not
    // scoped to this one consultation the way HPI/diagnosis/etc. are.
    // -----------------------------------------------------------
    const allergyToggle = document.getElementById('acvAddAllergyToggle');
    const allergyForm = document.getElementById('acvAddAllergyForm');
    const allergyCancelBtn = document.getElementById('acvAddAllergyCancel');
    const allergySaveBtn = document.getElementById('acvAddAllergySave');
    const allergyError = document.getElementById('acvAddAllergyError');

    function closeAllergyForm() {
        allergyForm.classList.add('d-none');
        document.getElementById('acvAllergyAllergen').value = '';
        document.getElementById('acvAllergyReaction').value = '';
        document.getElementById('acvAllergySeverity').value = 'mild';
        allergyError.classList.add('d-none');
    }

    allergyToggle.addEventListener('click', function () {
        allergyForm.classList.toggle('d-none');
        if (!allergyForm.classList.contains('d-none')) {
            document.getElementById('acvAllergyAllergen').focus();
        }
    });
    allergyCancelBtn.addEventListener('click', closeAllergyForm);

    allergySaveBtn.addEventListener('click', function () {
        const allergen = document.getElementById('acvAllergyAllergen').value.trim();
        if (allergen === '') {
            allergyError.textContent = 'Please enter an allergen.';
            allergyError.classList.remove('d-none');
            return;
        }
        const severity = document.getElementById('acvAllergySeverity').value;

        const payload = {
            patientId: document.getElementById('acvAllergyPatientId').value,
            allergen: allergen,
            reaction: document.getElementById('acvAllergyReaction').value.trim(),
            severity: severity,
        };

        allergySaveBtn.disabled = true;
        allergyError.classList.add('d-none');

        fetch('../api/add-allergy.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.error || 'Something went wrong. Please try again.');
                }
                // Update the list in place rather than reloading the
                // page — this form has no autosave (see the
                // beforeunload guard above), so a reload here would
                // silently wipe out any HPI/diagnosis/prescription/
                // vitals/medications/notes the doctor already typed but
                // hasn't Saved & Ended yet. Everything needed to render
                // the new entry was already just submitted, so no
                // re-fetch is needed either.
                const list = document.getElementById('acvAllergyList');
                const entry = allergen + ' (' + severity + ')';
                list.textContent = list.textContent.trim() === 'No known allergies.' ? entry : list.textContent + ', ' + entry;
                closeAllergyForm();
                allergySaveBtn.disabled = false;
            })
            .catch(function (err) {
                allergyError.textContent = err.message;
                allergyError.classList.remove('d-none');
                allergySaveBtn.disabled = false;
            });
    });

    // -----------------------------------------------------------
    // Prescription rich-text editor
    // -----------------------------------------------------------
    const rteEditor = document.getElementById('acvPrescription');
    document.querySelectorAll('.acv-rte-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const cmd = btn.dataset.cmd;
            rteEditor.focus();
            if (cmd === 'createLink') {
                const url = prompt('Link URL:');
                if (!url) return;
                document.execCommand(cmd, false, url);
            } else {
                document.execCommand(cmd, false, null);
            }
            rteEditor.dispatchEvent(new Event('input'));
        });
    });

    // -----------------------------------------------------------
    // Medications & Vaccines table
    // -----------------------------------------------------------
    const medRowsBody = document.getElementById('acvMedRows');
    const addMedRowBtn = document.getElementById('acvAddMedRow');
    const tabBtns = document.querySelectorAll('.acv-tab-btn');
    let activeMedType = 'medication';

    function datalistIdFor(type) {
        return type === 'vaccine' ? 'acvVaccineList' : 'acvMedicineList';
    }

    function renumberVisibleRows() {
        let n = 1;
        medRowsBody.querySelectorAll('tr').forEach(function (row) {
            if (row.dataset.type !== activeMedType) return;
            row.querySelector('.acv-row-num').textContent = n++;
        });
    }

    function updateRemoveButtons() {
        const visible = Array.from(medRowsBody.querySelectorAll('tr')).filter(function (row) {
            return row.dataset.type === activeMedType;
        });
        visible.forEach(function (row) {
            row.querySelector('.acv-remove-row-btn').disabled = visible.length <= 1;
        });
    }

    function applyTabVisibility() {
        medRowsBody.querySelectorAll('tr').forEach(function (row) {
            row.style.display = row.dataset.type === activeMedType ? '' : 'none';
        });
        medRowsBody.closest('table').classList.toggle('acv-med-table--vaccine', activeMedType === 'vaccine');
        renumberVisibleRows();
        updateRemoveButtons();
    }

    function addMedRow(type, data) {
        data = data || {};
        const row = document.createElement('tr');
        row.dataset.type = type;

        const numTd = document.createElement('td');
        numTd.className = 'acv-row-num';
        row.appendChild(numTd);

        const nameTd = document.createElement('td');
        const nameInput = document.createElement('input');
        nameInput.type = 'text';
        nameInput.className = 'acv-med-name';
        nameInput.setAttribute('list', datalistIdFor(type));
        nameInput.placeholder = type === 'vaccine' ? 'Vaccine name' : 'Medicine name';
        nameInput.value = data.name || '';
        nameTd.appendChild(nameInput);
        row.appendChild(nameTd);

        function appendTextCell(field) {
            const td = document.createElement('td');
            const input = document.createElement('input');
            input.type = 'text';
            input.className = field[1];
            input.placeholder = field[2];
            input.value = data[field[0]] || '';
            td.appendChild(input);
            row.appendChild(td);
        }

        appendTextCell(['dosage', 'acv-med-dosage', 'Dosage']);

        // Quantity to dispense (total tablets/capsules/bottles…) —
        // Medications tab only; the column is hidden on the Vaccines
        // tab (applyTabVisibility()) and never sent for vaccine rows.
        const qtyTd = document.createElement('td');
        qtyTd.className = 'acv-col-qty';
        const qtyInput = document.createElement('input');
        qtyInput.type = 'number';
        qtyInput.min = '1';
        qtyInput.step = '1';
        qtyInput.className = 'acv-med-quantity';
        qtyInput.placeholder = 'Qty';
        qtyInput.value = data.quantity || '';
        qtyTd.appendChild(qtyInput);
        row.appendChild(qtyTd);

        [
            ['frequency', 'acv-med-frequency', 'Frequency'],
            ['duration', 'acv-med-duration', 'Duration'],
            ['route', 'acv-med-route', 'Route'],
        ].forEach(appendTextCell);

        // Instructions gets its own auto-growing <textarea> instead of a
        // single-line <input> — free-text notes like "take it every 4
        // hours with food" otherwise scroll out of view inside a fixed-
        // width box while typing instead of staying visible.
        const instructionsTd = document.createElement('td');
        const instructionsInput = document.createElement('textarea');
        instructionsInput.className = 'acv-med-instructions';
        instructionsInput.placeholder = 'Instructions';
        instructionsInput.rows = 1;
        instructionsInput.value = data.instructions || '';
        instructionsInput.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
        instructionsTd.appendChild(instructionsInput);
        row.appendChild(instructionsTd);

        const actionTd = document.createElement('td');
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'acv-remove-row-btn';
        removeBtn.setAttribute('aria-label', 'Remove row');
        removeBtn.innerHTML = '<i class="bi bi-trash"></i>';
        removeBtn.addEventListener('click', function () {
            const visibleCount = Array.from(medRowsBody.querySelectorAll('tr')).filter(function (r) {
                return r.dataset.type === activeMedType;
            }).length;
            if (visibleCount <= 1) return;
            row.remove();
            applyTabVisibility();
        });
        actionTd.appendChild(removeBtn);
        row.appendChild(actionTd);

        medRowsBody.appendChild(row);
        // scrollHeight only reflects real layout once the element is
        // actually in the DOM — grow it to fit a pre-filled value now
        // (e.g. re-opening a consultation that already has a long
        // instructions note) rather than only ever growing on the
        // NEXT keystroke.
        if (instructionsInput.value) {
            instructionsInput.style.height = 'auto';
            instructionsInput.style.height = instructionsInput.scrollHeight + 'px';
        }
    }

    tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            tabBtns.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            activeMedType = btn.dataset.type;
            const anyVisible = Array.from(medRowsBody.querySelectorAll('tr')).some(function (r) {
                return r.dataset.type === activeMedType;
            });
            if (!anyVisible) addMedRow(activeMedType);
            applyTabVisibility();
        });
    });

    addMedRowBtn.addEventListener('click', function () { addMedRow(activeMedType); });

    // Seed from real existing data (a consultation re-opened while
    // still "ongoing") or start with one blank Medications row.
    const existingMeds = Array.isArray(window.ACV_MEDICATIONS) ? window.ACV_MEDICATIONS : [];
    if (existingMeds.length > 0) {
        existingMeds.forEach(function (m) { addMedRow(m.type === 'vaccine' ? 'vaccine' : 'medication', m); });
    } else {
        addMedRow('medication');
    }
    applyTabVisibility();

    function collectMedications() {
        const rows = [];
        medRowsBody.querySelectorAll('tr').forEach(function (row) {
            const name = row.querySelector('.acv-med-name').value.trim();
            if (name === '') return;
            rows.push({
                type: row.dataset.type,
                name: name,
                dosage: row.querySelector('.acv-med-dosage').value.trim(),
                quantity: row.dataset.type === 'medication' ? row.querySelector('.acv-med-quantity').value.trim() : '',
                frequency: row.querySelector('.acv-med-frequency').value.trim(),
                duration: row.querySelector('.acv-med-duration').value.trim(),
                route: row.querySelector('.acv-med-route').value.trim(),
                instructions: row.querySelector('.acv-med-instructions').value.trim(),
            });
        });
        return rows;
    }

    // -----------------------------------------------------------
    // "Patient Will Be Next In" -> Laboratory reveals a "Select
    // Laboratory Tests" checkbox panel (at least one test required).
    // Confirm collapses it to a compact summary line ("Change" reopens
    // it); Cancel clears everything and deselects Laboratory entirely.
    // The checkboxes themselves stay in the DOM (just hidden) either
    // way, so reading their :checked state on Save & End works
    // regardless of which view is currently showing.
    // -----------------------------------------------------------
    const labTestPanel = document.getElementById('acvLabTestPanel');
    const labTestSummary = document.getElementById('acvLabTestSummary');
    const labTestSummaryText = document.getElementById('acvLabTestSummaryText');
    const labTestError = document.getElementById('acvLabTestError');
    // Scoped to #acvLabTestPanel, not a bare '.acv-lab-test-checkbox
    // input' — the X-ray panel below reuses the same checkbox class
    // name (same generic styling), so an unscoped query here would also
    // pick up X-ray's own checkboxes.
    const labTestCheckboxes = labTestPanel.querySelectorAll('.acv-lab-test-checkbox input');
    const labTestCancelBtn = document.getElementById('acvLabTestCancel');
    const labTestConfirmBtn = document.getElementById('acvLabTestConfirm');
    const labTestChangeBtn = document.getElementById('acvLabTestChange');
    const labStaffSingleInput = document.getElementById('acvLabAssignToSingle');
    const labStaffError = document.getElementById('acvLabStaffError');

    function checkedLabTests() {
        return Array.from(labTestCheckboxes).filter(function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });
    }
    function selectedLabStaffUid() {
        if (labStaffSingleInput) return labStaffSingleInput.value;
        const checked = document.querySelector('input[name="acvLabAssignTo"]:checked');
        return checked ? checked.value : '';
    }

    function showLabTestPanel() {
        labTestPanel.classList.remove('d-none');
        labTestSummary.classList.add('d-none');
    }
    function showLabTestSummary() {
        const tests = checkedLabTests();
        labTestSummaryText.textContent = tests.length + ' test' + (tests.length === 1 ? '' : 's') + ' selected: ' + tests.join(', ');
        labTestSummary.classList.remove('d-none');
        labTestPanel.classList.add('d-none');
    }
    function hideLabTestUi() {
        labTestPanel.classList.add('d-none');
        labTestSummary.classList.add('d-none');
    }

    // -----------------------------------------------------------
    // "Patient Will Be Next In" -> X-ray reveals a "Select X-ray Exams"
    // checkbox panel — same confirm/summary/cancel pattern as the
    // Laboratory panel above, just its own elements/catalog.
    // -----------------------------------------------------------
    const xrayTestPanel = document.getElementById('acvXrayTestPanel');
    const xrayTestSummary = document.getElementById('acvXrayTestSummary');
    const xrayTestSummaryText = document.getElementById('acvXrayTestSummaryText');
    const xrayTestError = document.getElementById('acvXrayTestError');
    const xrayTestCheckboxes = xrayTestPanel ? xrayTestPanel.querySelectorAll('.acv-lab-test-checkbox input') : [];
    const xrayTestCancelBtn = document.getElementById('acvXrayTestCancel');
    const xrayTestConfirmBtn = document.getElementById('acvXrayTestConfirm');
    const xrayTestChangeBtn = document.getElementById('acvXrayTestChange');
    const xrayStaffSingleInput = document.getElementById('acvXrayAssignToSingle');
    const xrayStaffError = document.getElementById('acvXrayStaffError');

    function checkedXrayExams() {
        return Array.from(xrayTestCheckboxes).filter(function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });
    }
    function selectedXrayStaffUid() {
        if (xrayStaffSingleInput) return xrayStaffSingleInput.value;
        const checked = document.querySelector('input[name="acvXrayAssignTo"]:checked');
        return checked ? checked.value : '';
    }

    function showXrayTestPanel() {
        if (!xrayTestPanel) return;
        xrayTestPanel.classList.remove('d-none');
        xrayTestSummary.classList.add('d-none');
    }
    function showXrayTestSummary() {
        if (!xrayTestPanel) return;
        const exams = checkedXrayExams();
        xrayTestSummaryText.textContent = exams.length + ' exam' + (exams.length === 1 ? '' : 's') + ' selected: ' + exams.join(', ');
        xrayTestSummary.classList.remove('d-none');
        xrayTestPanel.classList.add('d-none');
    }
    function hideXrayTestUi() {
        if (!xrayTestPanel) return;
        xrayTestPanel.classList.add('d-none');
        xrayTestSummary.classList.add('d-none');
    }

    // -----------------------------------------------------------
    // "Patient Will Be Next In" -> Pharmacy or Vaccination reveals the
    // Medications & Vaccines table (left column) — same routing-driven
    // show/hide as the lab test panel above, just without a confirm/
    // summary step since there's nothing to collapse: the table stays
    // interactive the whole time it's visible.
    // -----------------------------------------------------------
    const medVaccinePanel = document.getElementById('acvMedVaccinePanel');
    function showMedVaccinePanel() {
        medVaccinePanel.classList.remove('d-none');
    }
    function hideMedVaccinePanel() {
        medVaccinePanel.classList.add('d-none');
    }

    // -----------------------------------------------------------
    // "Also needs a follow-up visit" checkbox -> reveals a date input,
    // same show/hide idiom as the panels above. Posted as "nextFollowUp"
    // (api/complete-consultation.php) — see this field's own comment on
    // doctor/active_consultation.php for why it reuses medical staff's
    // existing field instead of a new one.
    // -----------------------------------------------------------
    const needsFollowUpCheckbox = document.getElementById('acvNeedsFollowUp');
    const followUpDatePanel = document.getElementById('acvFollowUpDatePanel');
    const followUpDateInput = document.getElementById('acvFollowUpDate');
    const followUpDateError = document.getElementById('acvFollowUpDateError');
    if (needsFollowUpCheckbox) {
        needsFollowUpCheckbox.addEventListener('change', function () {
            followUpDatePanel.classList.toggle('d-none', !this.checked);
            followUpDateError.classList.add('d-none');
        });
    }

    // -----------------------------------------------------------
    // Routing to Laboratory/X-ray doesn't end the consultation (see
    // api/complete-consultation.php's header comment) — the main
    // action button reflects that: "Send to Medical Technologist"/
    // "Send to Radiologic Technologist" instead of "Save & End Consultation",
    // same button/handler either way, just a different label/confirm
    // message read at click time (see the click handler below).
    // -----------------------------------------------------------
    function updateSaveBtnLabel(routingValue) {
        const btn = document.getElementById('acvSaveBtn');
        if (!btn) return;
        if (routingValue === 'laboratory') {
            btn.innerHTML = '<i class="bi bi-send-fill"></i> Send to Medical Technologist';
        } else if (routingValue === 'xray') {
            btn.innerHTML = '<i class="bi bi-send-fill"></i> Send to Radiologic Technologist';
        } else {
            btn.innerHTML = '<i class="bi bi-save"></i> Save &amp; End Consultation';
        }
    }

    const routingRadios = document.querySelectorAll('input[name="acvRouting"]');
    routingRadios.forEach(function (radio) {
        // Radios (unlike checkboxes) never fire a native "change" event
        // from clicking one that's ALREADY selected — nothing changed,
        // by definition — so re-clicking Laboratory to close its panel
        // needs a "click" listener with manual toggle-off tracking
        // instead (radio.dataset.wasChecked, updated on every click).
        radio.addEventListener('click', function () {
            if (radio.dataset.wasChecked === 'true') {
                radio.checked = false;
                radio.dataset.wasChecked = 'false';
                hideLabTestUi();
                hideXrayTestUi();
                hideMedVaccinePanel();
                updateSaveBtnLabel('');
                return;
            }

            routingRadios.forEach(function (r) { r.dataset.wasChecked = (r === radio) ? 'true' : 'false'; });
            updateSaveBtnLabel(radio.value);

            if (radio.value === 'pharmacy' || radio.value === 'vaccination') {
                showMedVaccinePanel();
            } else {
                hideMedVaccinePanel();
            }

            if (radio.value !== 'laboratory') {
                hideLabTestUi();
            }
            if (radio.value !== 'xray') {
                hideXrayTestUi();
            }
            if (radio.value === 'laboratory') {
                // Already has confirmed tests (either from earlier this
                // session, or re-opening a consultation Saved with some
                // already picked) — go straight to the summary instead
                // of making the doctor reopen the grid every time.
                if (checkedLabTests().length > 0) {
                    showLabTestSummary();
                } else {
                    showLabTestPanel();
                }
            } else if (radio.value === 'xray') {
                if (checkedXrayExams().length > 0) {
                    showXrayTestSummary();
                } else {
                    showXrayTestPanel();
                }
            }
        });
    });
    const initiallyCheckedRadio = document.querySelector('input[name="acvRouting"]:checked');
    if (initiallyCheckedRadio) {
        initiallyCheckedRadio.dataset.wasChecked = 'true';
        if (initiallyCheckedRadio.value === 'laboratory') {
            if (checkedLabTests().length > 0) {
                showLabTestSummary();
            } else {
                showLabTestPanel();
            }
        } else if (initiallyCheckedRadio.value === 'xray') {
            if (checkedXrayExams().length > 0) {
                showXrayTestSummary();
            } else {
                showXrayTestPanel();
            }
        }
    }

    labTestCancelBtn.addEventListener('click', function () {
        labTestCheckboxes.forEach(function (cb) { cb.checked = false; });
        const routingInput = document.querySelector('input[name="acvRouting"]:checked');
        if (routingInput) {
            routingInput.checked = false;
            routingInput.dataset.wasChecked = 'false';
        }
        hideLabTestUi();
        updateSaveBtnLabel('');
        labTestError.classList.add('d-none');
    });

    labTestConfirmBtn.addEventListener('click', function () {
        if (checkedLabTests().length === 0) {
            labTestError.textContent = 'Please select at least one laboratory test.';
            labTestError.classList.remove('d-none');
            return;
        }
        if (labStaffError && !selectedLabStaffUid()) {
            labStaffError.classList.remove('d-none');
            return;
        }
        labTestError.classList.add('d-none');
        if (labStaffError) labStaffError.classList.add('d-none');
        showLabTestSummary();
    });

    labTestChangeBtn.addEventListener('click', showLabTestPanel);

    if (xrayTestPanel) {
        xrayTestCancelBtn.addEventListener('click', function () {
            xrayTestCheckboxes.forEach(function (cb) { cb.checked = false; });
            const routingInput = document.querySelector('input[name="acvRouting"]:checked');
            if (routingInput) {
                routingInput.checked = false;
                routingInput.dataset.wasChecked = 'false';
            }
            hideXrayTestUi();
            updateSaveBtnLabel('');
            xrayTestError.classList.add('d-none');
        });

        xrayTestConfirmBtn.addEventListener('click', function () {
            if (checkedXrayExams().length === 0) {
                xrayTestError.textContent = 'Please select at least one X-ray exam.';
                xrayTestError.classList.remove('d-none');
                return;
            }
            if (xrayStaffError && !selectedXrayStaffUid()) {
                xrayStaffError.classList.remove('d-none');
                return;
            }
            xrayTestError.classList.add('d-none');
            if (xrayStaffError) xrayStaffError.classList.add('d-none');
            showXrayTestSummary();
        });

        xrayTestChangeBtn.addEventListener('click', showXrayTestPanel);
    }

    // -----------------------------------------------------------
    // Save & End Consultation / Cancel Consultation
    // -----------------------------------------------------------
    const saveBtn = document.getElementById('acvSaveBtn');
    const cancelBtn = document.getElementById('acvCancelBtn');
    const consultationId = window.ACV_CONSULTATION_ID;

    function setBusy(btn, busyLabel) {
        btn.disabled = true;
        btn.dataset.defaultHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + busyLabel;
    }
    function clearBusy(btn) {
        btn.disabled = false;
        btn.innerHTML = btn.dataset.defaultHtml;
    }

    saveBtn.addEventListener('click', async function () {
        const routingInput = document.querySelector('input[name="acvRouting"]:checked');
        const nextRouting = routingInput ? routingInput.value : '';
        const needsFollowUp = document.getElementById('acvNeedsFollowUp').checked;
        const medications = collectMedications();

        if (medications.some(function (m) { return m.quantity !== '' && !/^[1-9]\d*$/.test(m.quantity); })) {
            await HCDialog.alert('Please enter a whole number of 1 or more for each medication\'s Quantity.');
            return;
        }

        if (nextRouting === 'laboratory' && checkedLabTests().length === 0) {
            await HCDialog.alert('Please select at least one laboratory test before sending this patient to the Laboratory.');
            showLabTestPanel();
            return;
        }
        if (nextRouting === 'laboratory' && labStaffError && !selectedLabStaffUid()) {
            await HCDialog.alert('Please choose which medical technologist this should be sent to.');
            showLabTestPanel();
            return;
        }

        if (nextRouting === 'xray' && checkedXrayExams().length === 0) {
            await HCDialog.alert('Please select at least one X-ray exam before sending this patient for X-ray.');
            showXrayTestPanel();
            return;
        }
        if (nextRouting === 'xray' && xrayStaffError && !selectedXrayStaffUid()) {
            await HCDialog.alert('Please choose which radiologic technologist this should be sent to.');
            showXrayTestPanel();
            return;
        }

        if (nextRouting === 'pharmacy' && medications.length === 0) {
            await HCDialog.alert('Please add at least one medicine or vaccine before sending this patient to the Pharmacy.');
            showMedVaccinePanel();
            return;
        }

        const followUpDate = needsFollowUp ? followUpDateInput.value : '';
        if (needsFollowUp && !followUpDate) {
            followUpDateError.classList.remove('d-none');
            followUpDatePanel.classList.remove('d-none');
            return;
        }
        followUpDateError.classList.add('d-none');

        const confirmMessages = {
            laboratory: 'Send this patient to the Laboratory? This saves the consultation and notifies the medical technologist — it does NOT end the consultation.',
            xray: 'Send this patient for X-ray? This saves the consultation and notifies the radiologic technologist — it does NOT end the consultation.',
        };
        const confirmMessage = confirmMessages[nextRouting] || 'Save and end this consultation? This marks it as completed.';
        const confirmed = await HCDialog.confirm(confirmMessage);
        if (!confirmed) return;

        const payload = {
            id: consultationId,
            historyOfPresentIllness: document.getElementById('acvHpi').value.trim(),
            reviewOfSymptoms: document.getElementById('acvRos').value.trim(),
            diagnosis: document.getElementById('acvDiagnosis').value.trim(),
            prescriptionHtml: rteEditor.innerHTML.trim(),
            vitals: {
                temperature: document.getElementById('acvVitalTemp').value,
                bloodPressureSystolic: document.getElementById('acvVitalBpSys').value,
                bloodPressureDiastolic: document.getElementById('acvVitalBpDia').value,
                heartRate: document.getElementById('acvVitalHr').value,
            },
            medications: medications,
            nextRouting: nextRouting,
            needsFollowUp: needsFollowUp,
            nextFollowUp: followUpDate,
            labTests: checkedLabTests(),
            xrayExams: checkedXrayExams(),
            assignedToUid: nextRouting === 'laboratory' ? selectedLabStaffUid() : (nextRouting === 'xray' ? selectedXrayStaffUid() : ''),
            doctorNotes: document.getElementById('acvNotes').value.trim(),
        };

        setBusy(saveBtn, 'Saving…');
        cancelBtn.disabled = true;

        fetch('../api/complete-consultation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.error || 'Something went wrong. Please try again.');
                }
                formDirty = false;
                window.location.href = 'consultations.php';
            })
            .catch(function (err) {
                HCDialog.alert(err.message);
                clearBusy(saveBtn);
                cancelBtn.disabled = false;
            });
    });

    cancelBtn.addEventListener('click', async function () {
        const confirmed = await HCDialog.confirm('Cancel this consultation? This cannot be undone.');
        if (!confirmed) return;

        setBusy(cancelBtn, 'Cancelling…');
        saveBtn.disabled = true;

        fetch('../api/update-consultation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: consultationId, status: 'cancelled' }),
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.error || 'Something went wrong. Please try again.');
                }
                formDirty = false;
                window.location.href = 'consultations.php';
            })
            .catch(function (err) {
                HCDialog.alert(err.message);
                clearBusy(cancelBtn);
                saveBtn.disabled = false;
            });
    });
});
