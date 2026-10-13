/* =============================================================
   DOCUMENT UPLOAD / DOWNLOAD JS
   ---------------------------------------------------------------
   Shared by doctor/documents.php, medical-staff/documents.php, and
   subadmin/documents.php.

   Upload: the panel's drag-and-drop/click-to-browse + type/size
   validation is the same client-side check this page always had —
   once a file passes, this now opens #uploadDocumentModal
   (includes/shared/upload_document_modal.php) to collect the real
   Category (required on every patient_documents/{id} doc) and an
   optional Description, then POSTs a real multipart/form-data request
   to api/upload-document.php. Reads its own config from
   #docUploadPanel's data-patient-id/data-max-size-mb/data-allowed-ext
   attributes rather than being hardcoded per page.

   Download: each row's Download button (.doc-download-btn,
   data-doc-id) navigates straight to api/download-document.php?id=...
   — a real file response, not a POST, so a plain navigation is all
   it needs.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    var panel = document.getElementById('docUploadPanel');
    var input = document.getElementById('docFileInput');
    if (!panel || !input) return;

    var patientId = panel.dataset.patientId || '';
    var maxSizeBytes = (parseInt(panel.dataset.maxSizeMb, 10) || 10) * 1024 * 1024;
    var allowedExt = (panel.dataset.allowedExt || 'pdf,jpg,png').split(',');

    var modalEl = document.getElementById('uploadDocumentModal');
    var modal = modalEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(modalEl) : null;
    var fileNameBox = document.getElementById('udmFileName');
    var categorySelect = document.getElementById('udmCategory');
    var descriptionInput = document.getElementById('udmDescription');
    var errorBox = document.getElementById('udmError');
    var submitBtn = document.getElementById('udmSubmitBtn');
    var submitLabel = document.getElementById('udmSubmitLabel');

    var pendingFile = null;

    function handleFiles(files) {
        if (!files || !files.length) return;
        var file = files[0];
        var ext = file.name.split('.').pop().toLowerCase();
        var extNormalized = ext === 'jpeg' ? 'jpg' : ext;

        if (allowedExt.indexOf(extNormalized) === -1) {
            HCDialog.alert('Unsupported file type. Please upload a ' + allowedExt.join(', ').toUpperCase() + ' file.');
            return;
        }
        if (file.size > maxSizeBytes) {
            HCDialog.alert('File is too large. Maximum size is ' + Math.round(maxSizeBytes / 1024 / 1024) + 'MB.');
            return;
        }
        if (!patientId) {
            HCDialog.alert('Could not determine which patient this document belongs to. Please reload the page and try again.');
            return;
        }

        pendingFile = file;
        if (!modal) return; // defensive — every real page loads Bootstrap

        if (errorBox) errorBox.classList.add('d-none');
        if (categorySelect) categorySelect.value = '';
        if (descriptionInput) descriptionInput.value = '';
        if (fileNameBox) {
            fileNameBox.innerHTML = '<i class="bi bi-file-earmark-fill"></i> ' +
                file.name.replace(/</g, '&lt;').replace(/>/g, '&gt;') +
                ' <span style="color:var(--adm-text-muted);">(' + Math.round(file.size / 1024) + ' KB)</span>';
        }
        modal.show();
    }

    input.addEventListener('change', function () {
        handleFiles(input.files);
    });

    panel.addEventListener('click', function (e) {
        if (e.target !== input && e.target.tagName !== 'LABEL') {
            input.click();
        }
    });

    ['dragenter', 'dragover'].forEach(function (evt) {
        panel.addEventListener(evt, function (e) {
            e.preventDefault();
            e.stopPropagation();
            panel.classList.add('doc-upload-dragover');
        });
    });

    ['dragleave', 'drop'].forEach(function (evt) {
        panel.addEventListener(evt, function (e) {
            e.preventDefault();
            e.stopPropagation();
            panel.classList.remove('doc-upload-dragover');
        });
    });

    panel.addEventListener('drop', function (e) {
        handleFiles(e.dataTransfer.files);
    });

    if (submitBtn) {
        submitBtn.addEventListener('click', function () {
            if (!pendingFile) return;
            if (!categorySelect || !categorySelect.value) {
                errorBox.textContent = 'Please choose a category.';
                errorBox.classList.remove('d-none');
                return;
            }

            errorBox.classList.add('d-none');
            submitBtn.disabled = true;
            var defaultLabel = submitLabel.textContent;
            submitLabel.textContent = 'Uploading…';

            var formData = new FormData();
            formData.append('file', pendingFile);
            formData.append('patientId', patientId);
            formData.append('category', categorySelect.value);
            formData.append('description', descriptionInput ? descriptionInput.value.trim() : '');

            fetch('../api/upload-document.php', { method: 'POST', body: formData })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (result) {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.error || 'Something went wrong. Please try again.');
                    }
                    window.location.reload();
                })
                .catch(function (err) {
                    errorBox.textContent = err.message;
                    errorBox.classList.remove('d-none');
                    submitBtn.disabled = false;
                    submitLabel.textContent = defaultLabel;
                });
        });
    }

    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function () {
            pendingFile = null;
            input.value = '';
        });
    }

    document.querySelectorAll('.doc-download-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var docId = btn.getAttribute('data-doc-id');
            if (!docId) return;
            window.location.href = '../api/download-document.php?id=' + encodeURIComponent(docId);
        });
    });

    document.querySelectorAll('.doc-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var docId = btn.getAttribute('data-doc-id');
            var docName = btn.getAttribute('data-doc-name') || 'this document';
            if (!docId) return;

            var confirmed = await HCDialog.confirm('Delete "' + docName + '"? This cannot be undone.');
            if (!confirmed) return;

            btn.disabled = true;

            fetch('../api/delete-document.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: docId }),
            })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (result) {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.error || 'Something went wrong. Please try again.');
                    }
                    window.location.reload();
                })
                .catch(function (err) {
                    HCDialog.alert(err.message);
                    btn.disabled = false;
                });
        });
    });
});
