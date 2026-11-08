/* =============================================================
   DISEASE REGISTRY PAGE JS (admin/disease_registry.php)
   ---------------------------------------------------------------
   Wires the "Manage" button (existing entries) and "Add Condition"
   button (new entries) to the one shared modal
   (includes/admin/manage_disease_registry_modal.php) and its single
   form (#mdrEntryForm), submitted to:
     - api/update-disease-registry-entry.php (existing entries) —
       name/synonyms/classification/GIS eligibility/public visibility/
       preventable/3 preventive-measure fields together, no reason
       required — disease-level metadata upkeep, never a per-case
       decision.
     - api/add-disease-registry-entry.php (new entries) — always starts
       unclassified/gisEligible=false/publicVisibility=false
       server-side regardless of what's submitted, so the
       classification section is hidden until the entry has been saved
       once.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('manageDiseaseRegistryModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalEl);
    const entryForm = document.getElementById('mdrEntryForm');
    const entryIdField = document.getElementById('mdrEntryId');
    const nameField = document.getElementById('mdrName');
    const synonymsField = document.getElementById('mdrSynonyms');
    const preventableField = document.getElementById('mdrPreventable');
    const classificationSection = document.getElementById('mdrClassificationSection');
    const classificationField = document.getElementById('mdrClassification');
    const gisEligibleField = document.getElementById('mdrGisEligible');
    const publicVisibilityField = document.getElementById('mdrPublicVisibility');
    const behavioralField = document.getElementById('mdrBehavioral');
    const environmentalField = document.getElementById('mdrEnvironmental');
    const communityField = document.getElementById('mdrCommunity');
    const entryError = document.getElementById('mdrEntryError');
    const entrySaveBtn = document.getElementById('mdrEntrySaveBtn');

    let isNewEntry = false;

    function resetModal() {
        entryForm.reset();
        entryError.classList.add('d-none');
        entryIdField.value = '';
        entrySaveBtn.disabled = false;
    }

    function showClassificationSection(show) {
        classificationSection.style.display = show ? '' : 'none';
    }

    // Public Visibility can't be true when GIS Eligible is off — keep
    // the checkbox itself honest client-side (the server enforces this
    // regardless).
    function syncPublicVisibilityAvailability() {
        if (!gisEligibleField.checked) {
            publicVisibilityField.checked = false;
            publicVisibilityField.disabled = true;
        } else {
            publicVisibilityField.disabled = false;
        }
    }
    gisEligibleField.addEventListener('change', syncPublicVisibilityAvailability);

    // "Manage" button on an existing row.
    document.querySelectorAll('[data-manage-registry]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            resetModal();
            isNewEntry = false;
            entryIdField.value = btn.dataset.id;
            nameField.value = btn.dataset.name || '';
            synonymsField.value = btn.dataset.synonyms || '';
            preventableField.checked = btn.dataset.preventable === '1';
            classificationField.value = btn.dataset.classification || '';
            gisEligibleField.checked = btn.dataset.gisEligible === '1';
            publicVisibilityField.checked = btn.dataset.publicVisibility === '1';
            behavioralField.value = btn.dataset.behavioral || '';
            environmentalField.value = btn.dataset.environmental || '';
            communityField.value = btn.dataset.community || '';
            syncPublicVisibilityAvailability();
            showClassificationSection(true);
            modal.show();
        });
    });

    // "Add Condition" — classification section hidden until the entry
    // itself is saved once (it always starts unclassified regardless).
    const addBtn = document.querySelector('[data-add-entry]');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            resetModal();
            isNewEntry = true;
            showClassificationSection(false);
            modal.show();
        });
    }

    entryForm.addEventListener('submit', function (e) {
        e.preventDefault();
        entryError.classList.add('d-none');
        entrySaveBtn.disabled = true;

        const payload = {
            id: entryIdField.value,
            name: nameField.value.trim(),
            synonyms: synonymsField.value,
            isPreventable: preventableField.checked,
            classification: classificationField.value,
            gisEligible: gisEligibleField.checked,
            publicVisibility: publicVisibilityField.checked,
            behavioralMeasures: behavioralField.value.trim(),
            environmentalMeasures: environmentalField.value.trim(),
            communityMeasures: communityField.value.trim(),
        };
        const url = isNewEntry ? '../api/add-disease-registry-entry.php' : '../api/update-disease-registry-entry.php';

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
                window.location.reload();
            })
            .catch(function (err) {
                entryError.textContent = err.message;
                entryError.classList.remove('d-none');
                entrySaveBtn.disabled = false;
            });
    });
});
