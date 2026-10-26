/* =============================================================
   LAB TEST CATALOG (medical_technician/lab_test_catalog.php)
   ---------------------------------------------------------------
   Wires: "+ Add Laboratory" -> #ltcAddModal -> api/add-lab-test.php;
   each row's trash button -> confirm (HCDialog) -> api/delete-lab-test.php;
   each row's upload/replace button -> #ltcUploadModal (a real file,
   sent as multipart/form-data, not JSON) -> api/upload-lab-test-template.php;
   each row's "Remove Template" button -> confirm (HCDialog) ->
   api/delete-lab-test-template.php. Same confirm+fetch+reload idiom as
   assets/js/medical-history.js, except the upload submit uses FormData
   since it carries a real file.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof bootstrap === 'undefined') return;

    function postJson(endpoint, payload) {
        return fetch('../api/' + endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        }).then(function (res) {
            return res.json().then(function (d) { return { ok: res.ok, data: d }; });
        }).then(function (result) {
            if (!result.ok || !result.data.success) {
                throw new Error(result.data.error || 'Something went wrong. Please try again.');
            }
            return result.data;
        });
    }

    // ---------------------------------------------------------------
    // Add Laboratory
    // ---------------------------------------------------------------
    var addModalEl = document.getElementById('ltcAddModal');
    var addModal = addModalEl ? new bootstrap.Modal(addModalEl) : null;
    var addBtn = document.getElementById('btnAddLabTest');
    var addForm = document.getElementById('ltcAddForm');
    var addError = document.getElementById('ltcAddError');
    var addSubmitBtn = document.getElementById('ltcAddSubmitBtn');
    var addSubmitDefaultHtml = addSubmitBtn ? addSubmitBtn.innerHTML : '';

    if (addBtn && addModal) {
        addBtn.addEventListener('click', function () {
            addForm.reset();
            addForm.classList.remove('was-validated');
            if (addError) addError.classList.add('d-none');
            addModal.show();
        });
    }

    if (addForm) {
        addForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!addForm.checkValidity()) {
                addForm.classList.add('was-validated');
                return;
            }

            if (addError) addError.classList.add('d-none');
            if (addSubmitBtn) {
                addSubmitBtn.disabled = true;
                addSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding…';
            }

            postJson('add-lab-test.php', {
                testName: document.getElementById('ltcAddTestName').value.trim(),
                category: document.getElementById('ltcAddCategory').value.trim(),
            }).then(function () {
                window.location.reload();
            }).catch(function (err) {
                if (addError) {
                    addError.textContent = err.message;
                    addError.classList.remove('d-none');
                }
                if (addSubmitBtn) {
                    addSubmitBtn.disabled = false;
                    addSubmitBtn.innerHTML = addSubmitDefaultHtml;
                }
            });
        });
    }

    // ---------------------------------------------------------------
    // Delete laboratory test
    // ---------------------------------------------------------------
    document.querySelectorAll('[data-ltc-delete]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.dataset.id;
            var label = btn.dataset.label || 'this laboratory test';
            HCDialog.confirm('Remove ' + label + '? This cannot be undone.').then(function (confirmed) {
                if (!confirmed) return;
                postJson('delete-lab-test.php', { id: id }).then(function () {
                    window.location.reload();
                }).catch(function (err) {
                    HCDialog.alert(err.message);
                });
            });
        });
    });

    // ---------------------------------------------------------------
    // Remove template (keeps the catalog entry, just clears the file)
    // ---------------------------------------------------------------
    document.querySelectorAll('[data-ltc-delete-template]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.dataset.id;
            var label = btn.dataset.label || 'this template';
            HCDialog.confirm('Remove ' + label + '?').then(function (confirmed) {
                if (!confirmed) return;
                postJson('delete-lab-test-template.php', { id: id }).then(function () {
                    window.location.reload();
                }).catch(function (err) {
                    HCDialog.alert(err.message);
                });
            });
        });
    });

    // ---------------------------------------------------------------
    // Upload / Replace Template
    // ---------------------------------------------------------------
    var uploadModalEl = document.getElementById('ltcUploadModal');
    var uploadModal = uploadModalEl ? new bootstrap.Modal(uploadModalEl) : null;
    var uploadForm = document.getElementById('ltcUploadForm');
    var uploadError = document.getElementById('ltcUploadError');
    var uploadSubmitBtn = document.getElementById('ltcUploadSubmitBtn');
    var uploadSubmitDefaultHtml = uploadSubmitBtn ? uploadSubmitBtn.innerHTML : '';
    var uploadTestIdField = document.getElementById('ltcUploadTestId');
    var uploadTitleEl = document.getElementById('ltcUploadModalLabel');
    var uploadFileInput = document.getElementById('ltcUploadFileInput');
    var uploadDropzone = document.getElementById('ltcUploadDropzone');
    var uploadDropzoneText = document.getElementById('ltcUploadDropzoneText');
    var uploadFileNameLabel = document.getElementById('ltcUploadFileNameLabel');

    function resetUploadModal() {
        uploadForm.reset();
        uploadForm.classList.remove('was-validated');
        if (uploadError) uploadError.classList.add('d-none');
        if (uploadFileNameLabel) {
            uploadFileNameLabel.textContent = '';
            uploadFileNameLabel.classList.add('d-none');
        }
        if (uploadDropzoneText) uploadDropzoneText.textContent = 'Click to choose a file, or drag one here';
    }

    document.querySelectorAll('[data-ltc-upload]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!uploadModal) return;
            resetUploadModal();
            uploadTestIdField.value = btn.dataset.id || '';
            if (uploadTitleEl) uploadTitleEl.textContent = 'Upload Template — ' + (btn.dataset.testName || '');
            uploadModal.show();
        });
    });

    if (uploadFileInput && uploadFileNameLabel && uploadDropzoneText) {
        uploadFileInput.addEventListener('change', function () {
            var file = uploadFileInput.files[0];
            if (file) {
                uploadDropzoneText.textContent = 'Choose a different file';
                uploadFileNameLabel.textContent = file.name;
                uploadFileNameLabel.classList.remove('d-none');
            } else {
                uploadDropzoneText.textContent = 'Click to choose a file, or drag one here';
                uploadFileNameLabel.classList.add('d-none');
            }
        });
    }

    if (uploadDropzone && uploadFileInput) {
        ['dragover', 'dragenter'].forEach(function (evt) {
            uploadDropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                uploadDropzone.classList.add('ltc-dropzone-active');
            });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            uploadDropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                uploadDropzone.classList.remove('ltc-dropzone-active');
            });
        });
        uploadDropzone.addEventListener('drop', function (e) {
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                uploadFileInput.files = e.dataTransfer.files;
                uploadFileInput.dispatchEvent(new Event('change'));
            }
        });
    }

    if (uploadForm) {
        uploadForm.addEventListener('submit', function (e) {
            e.preventDefault();

            var file = uploadFileInput.files[0];
            if (!file) {
                if (uploadError) {
                    uploadError.textContent = 'Please choose a file to upload.';
                    uploadError.classList.remove('d-none');
                }
                return;
            }

            var formData = new FormData();
            formData.append('id', uploadTestIdField.value);
            formData.append('template', file);

            if (uploadError) uploadError.classList.add('d-none');
            if (uploadSubmitBtn) {
                uploadSubmitBtn.disabled = true;
                uploadSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading…';
            }

            fetch('../api/upload-lab-test-template.php', {
                method: 'POST',
                body: formData,
            })
                .then(function (res) {
                    return res.json().then(function (d) { return { ok: res.ok, data: d }; });
                })
                .then(function (result) {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.error || 'Something went wrong. Please try again.');
                    }
                    window.location.reload();
                })
                .catch(function (err) {
                    if (uploadError) {
                        uploadError.textContent = err.message;
                        uploadError.classList.remove('d-none');
                    }
                    if (uploadSubmitBtn) {
                        uploadSubmitBtn.disabled = false;
                        uploadSubmitBtn.innerHTML = uploadSubmitDefaultHtml;
                    }
                });
        });
    }
});
