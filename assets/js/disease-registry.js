/* =============================================================
   DISEASE REGISTRY PAGE JS (admin/disease_registry.php)
   ---------------------------------------------------------------
   Wires the "Manage" button (existing entries) and "Add Condition"
   button (new entries) to the one shared modal
   (includes/admin/manage_disease_registry_modal.php) and its single
   form (#mdrEntryForm), submitted to:
     - api/update-disease-registry-entry.php (existing entries) —
       name/synonyms/preventable/3 preventive-measure fields, no
       reason required.
     - api/add-disease-registry-entry.php (new entries).
   Both endpoints make the condition visible on the public GIS map
   immediately — there's no separate classification/eligibility step
   any more, so #mdrAddReminder is shown (Add Condition only) to make
   sure that's never a surprise.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('manageDiseaseRegistryModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalEl);
    const modalTitle = document.getElementById('manageDiseaseRegistryModalLabel');
    const entryForm = document.getElementById('mdrEntryForm');
    const entryIdField = document.getElementById('mdrEntryId');
    const nameField = document.getElementById('mdrName');
    const synonymsField = document.getElementById('mdrSynonyms');
    const preventableField = document.getElementById('mdrPreventable');
    const addReminder = document.getElementById('mdrAddReminder');
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

    // "Manage" button on an existing row.
    document.querySelectorAll('[data-manage-registry]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            resetModal();
            isNewEntry = false;
            modalTitle.textContent = 'Manage Registry Entry';
            addReminder.classList.add('d-none');
            entryIdField.value = btn.dataset.id;
            nameField.value = btn.dataset.name || '';
            synonymsField.value = btn.dataset.synonyms || '';
            preventableField.checked = btn.dataset.preventable === '1';
            behavioralField.value = btn.dataset.behavioral || '';
            environmentalField.value = btn.dataset.environmental || '';
            communityField.value = btn.dataset.community || '';
            modal.show();
        });
    });

    // "Add Condition" — the reminder banner always shows here, since
    // this is the one action that creates a condition that will go
    // live on the public map immediately.
    const addBtn = document.querySelector('[data-add-entry]');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            resetModal();
            isNewEntry = true;
            modalTitle.textContent = 'Add Condition';
            addReminder.classList.remove('d-none');
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
