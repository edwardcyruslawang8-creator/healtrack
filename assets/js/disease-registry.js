/* =============================================================
   DISEASE REGISTRY PAGE JS (admin/disease_registry.php)
   ---------------------------------------------------------------
   Wires the "Manage" button (existing entries) and "Add to Registry"
   button (candidate conditions) to the one shared modal
   (includes/admin/manage_disease_registry_modal.php) and its single
   form (#mdrEntryForm), submitted to:
     - api/update-disease-registry-entry.php (existing entries) —
       name/keywords/preventable/category together, no reason required.
     - api/add-disease-registry-entry.php (new entries, from a
       candidate or from scratch) — always starts uncategorized
       server-side regardless of what's submitted, so the Category
       field is hidden until the entry has been saved once.
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
    const categorySection = document.getElementById('mdrCategorySection');
    const categoryField = document.getElementById('mdrCategory');
    const preventiveMeasuresField = document.getElementById('mdrPreventiveMeasures');
    const entryError = document.getElementById('mdrEntryError');
    const entrySaveBtn = document.getElementById('mdrEntrySaveBtn');

    let isNewEntry = false;
    let pendingCandidateFlag = false;

    function resetModal() {
        entryForm.reset();
        entryError.classList.add('d-none');
        entryIdField.value = '';
        entrySaveBtn.disabled = false;
    }

    function showCategorySection(show) {
        categorySection.style.display = show ? '' : 'none';
    }

    // "Manage" button on an existing row.
    document.querySelectorAll('[data-manage-registry]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            resetModal();
            isNewEntry = false;
            pendingCandidateFlag = false;
            entryIdField.value = btn.dataset.id;
            nameField.value = btn.dataset.name || '';
            keywordsField.value = btn.dataset.keywords || '';
            preventableField.checked = btn.dataset.preventable === '1';
            categoryField.value = btn.dataset.category || '';
            preventiveMeasuresField.value = btn.dataset.preventiveMeasures || '';
            showCategorySection(true);
            modal.show();
        });
    });

    // "Add to Registry" on a candidate row — pre-fills name/keyword,
    // category section hidden until the entry itself is saved once
    // (it always starts uncategorized regardless).
    document.querySelectorAll('[data-add-candidate]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            resetModal();
            isNewEntry = true;
            pendingCandidateFlag = true;
            nameField.value = btn.dataset.name || '';
            keywordsField.value = btn.dataset.keyword || '';
            preventableField.checked = false;
            showCategorySection(false);
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
            category: categoryField.value,
            preventiveMeasures: preventiveMeasuresField.value.trim(),
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
                window.location.reload();
            })
            .catch(function (err) {
                entryError.textContent = err.message;
                entryError.classList.remove('d-none');
                entrySaveBtn.disabled = false;
            });
    });
});
