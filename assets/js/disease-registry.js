/* =============================================================
   DISEASE REGISTRY PAGE JS (admin/disease_registry.php)
   ---------------------------------------------------------------
   Wires the "Manage" button (existing entries) and "Add to Registry"
   button (candidate conditions) to the one shared modal
   (includes/admin/manage_disease_registry_modal.php), and submits its
   two independent forms to their own endpoints:
     - #mdrEntryForm -> api/update-disease-registry-entry.php (existing)
       or api/add-disease-registry-entry.php (new, from a candidate or
       from scratch) — routine name/keywords/preventable maintenance.
     - #mdrClassificationForm -> api/update-disease-classification.php —
       the real, reason-required visibility decision. Hidden entirely
       for a brand-new (not-yet-saved) entry, since there's nothing to
       classify until the entry itself is first saved.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('manageDiseaseRegistryModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalEl);
    const entryForm = document.getElementById('mdrEntryForm');
    const entryIdField = document.getElementById('mdrEntryId');
    const nameField = document.getElementById('mdrName');
    const keywordsField = document.getElementById('mdrKeywords');
    const preventableField = document.getElementById('mdrPreventable');
    const entryError = document.getElementById('mdrEntryError');
    const entrySaveBtn = document.getElementById('mdrEntrySaveBtn');

    const classificationSection = document.getElementById('mdrClassificationSection');
    const classificationForm = document.getElementById('mdrClassificationForm');
    const classificationEntryIdField = document.getElementById('mdrClassificationEntryId');
    const classificationField = document.getElementById('mdrClassification');
    const preventiveMeasuresField = document.getElementById('mdrPreventiveMeasures');
    const reasonField = document.getElementById('mdrReason');
    const classificationError = document.getElementById('mdrClassificationError');
    const classificationSaveBtn = document.getElementById('mdrClassificationSaveBtn');

    let isNewEntry = false;
    let pendingCandidateFlag = false;

    function resetModal() {
        entryForm.reset();
        classificationForm.reset();
        entryError.classList.add('d-none');
        classificationError.classList.add('d-none');
        entryIdField.value = '';
        classificationEntryIdField.value = '';
    }

    function showClassificationSection(show) {
        classificationSection.style.display = show ? '' : 'none';
        classificationSaveBtn.style.display = show ? '' : 'none';
    }

    // "Manage" button on an existing row.
    document.querySelectorAll('[data-manage-registry]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            resetModal();
            isNewEntry = false;
            pendingCandidateFlag = false;
            const id = btn.dataset.id;
            entryIdField.value = id;
            classificationEntryIdField.value = id;
            nameField.value = btn.dataset.name || '';
            keywordsField.value = btn.dataset.keywords || '';
            preventableField.checked = btn.dataset.preventable === '1';
            classificationField.value = btn.dataset.classification || 'pending_classification';
            preventiveMeasuresField.value = btn.dataset.preventiveMeasures || '';
            showClassificationSection(true);
            modal.show();
        });
    });

    // "Add to Registry" on a candidate row — pre-fills name/keyword,
    // classification section hidden until the entry itself is saved
    // once (nothing to classify yet).
    document.querySelectorAll('[data-add-candidate]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            resetModal();
            isNewEntry = true;
            pendingCandidateFlag = true;
            nameField.value = btn.dataset.name || '';
            keywordsField.value = btn.dataset.keyword || '';
            preventableField.checked = false;
            showClassificationSection(false);
            modal.show();
        });
    });

    entryForm.addEventListener('submit', function (e) {
        e.preventDefault();
        entryError.classList.add('d-none');
        entrySaveBtn.disabled = true;

        const payload = {
            id: entryIdField.value,
            name: nameField.value.trim(),
            keywords: keywordsField.value,
            isPreventable: preventableField.checked,
        };
        const url = isNewEntry ? '../api/add-disease-registry-entry.php' : '../api/update-disease-registry-entry.php';
        if (isNewEntry) {
            payload.isAutoDetected = pendingCandidateFlag;
        }

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.error || 'Something went wrong. Please try again.');
                }
                if (isNewEntry) {
                    // A brand-new entry is always created Pending — reload
                    // so the admin sees it in the table and can classify
                    // it as a real, separate next action.
                    window.location.reload();
                    return;
                }
                window.location.reload();
            })
            .catch(function (err) {
                entryError.textContent = err.message;
                entryError.classList.remove('d-none');
                entrySaveBtn.disabled = false;
            });
    });

    classificationForm.addEventListener('submit', function (e) {
        e.preventDefault();
        classificationError.classList.add('d-none');
        classificationSaveBtn.disabled = true;

        fetch('../api/update-disease-classification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: classificationEntryIdField.value,
                classification: classificationField.value,
                preventiveMeasures: preventiveMeasuresField.value.trim(),
                reason: reasonField.value.trim(),
            }),
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.error || 'Something went wrong. Please try again.');
                }
                window.location.reload();
            })
            .catch(function (err) {
                classificationError.textContent = err.message;
                classificationError.classList.remove('d-none');
                classificationSaveBtn.disabled = false;
            });
    });
});
