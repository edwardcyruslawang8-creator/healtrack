/* =============================================================
   HOMEPAGE SETTINGS PAGE JS (subadmin/homepage_settings.php)
   ---------------------------------------------------------------
   Add/remove rows for Operating Hours (a repeatable-row group),
   then one "Save Changes" submit that posts everything to
   api/update-homepage-settings.php.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    function addRow(container, fields, placeholders) {
        const row = document.createElement('div');
        row.className = 'hps-repeat-row';
        row.innerHTML = fields.map(function (field, i) {
            return '<input type="text" class="form-control" placeholder="' + placeholders[i] + '" data-field="' + field + '">';
        }).join('') + '<button type="button" class="hps-row-remove" aria-label="Remove"><i class="bi bi-trash"></i></button>';
        container.appendChild(row);
    }

    function wireRemoveButtons(container) {
        container.addEventListener('click', function (e) {
            const btn = e.target.closest('.hps-row-remove');
            if (!btn) return;
            btn.closest('.hps-repeat-row').remove();
        });
    }

    const hoursRows = document.getElementById('hpsOperatingHoursRows');
    const addHourBtn = document.getElementById('hpsAddOperatingHour');
    if (hoursRows && addHourBtn) {
        wireRemoveButtons(hoursRows);
        addHourBtn.addEventListener('click', function () {
            addRow(hoursRows, ['label', 'hours'], ['Day(s) (e.g. Monday - Friday)', 'Hours (e.g. 8:00 AM - 5:00 PM)']);
        });
    }

    function readRows(container) {
        return Array.from(container.querySelectorAll('.hps-repeat-row')).map(function (row) {
            const obj = {};
            row.querySelectorAll('[data-field]').forEach(function (input) {
                obj[input.dataset.field] = input.value.trim();
            });
            return obj;
        });
    }

    const form = document.getElementById('homepageSettingsForm');
    const errorBox = document.getElementById('hpsError');
    const successBox = document.getElementById('hpsSuccess');
    const submitBtn = document.getElementById('hpsSubmitBtn');
    const confirmModalEl = document.getElementById('hpsConfirmModal');
    const confirmSaveBtn = document.getElementById('hpsConfirmModalSaveBtn');
    if (!form) return;

    // Real confirmation before this goes live on the public homepage — an
    // in-app modal (#hpsConfirmModal) rather than a native window.confirm()
    // popup, which browsers prefix with "localhost says" and looks out of
    // place next to the rest of this page's own styling. Clicking "Save
    // Changes" opens the modal instead of submitting right away; the
    // actual save only happens once its own "Save Changes" button is
    // clicked.
    const confirmModal = confirmModalEl ? new bootstrap.Modal(confirmModalEl) : null;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (confirmModal) {
            confirmModal.show();
        } else {
            doSave();
        }
    });

    if (confirmSaveBtn) {
        confirmSaveBtn.addEventListener('click', function () {
            confirmModal.hide();
            doSave();
        });
    }

    function doSave() {
        errorBox.classList.add('d-none');
        successBox.classList.add('d-none');

        const payload = {
            missionText: document.getElementById('hpsMission').value.trim(),
            visionText: document.getElementById('hpsVision').value.trim(),
            communityFirstText: document.getElementById('hpsCommunityFirst').value.trim(),
            contactAddress: document.getElementById('hpsAddress').value.trim(),
            contactPhone: document.getElementById('hpsPhone').value.trim(),
            contactEmail: document.getElementById('hpsEmail').value.trim(),
            operatingHours: hoursRows ? readRows(hoursRows) : [],
        };

        submitBtn.disabled = true;
        const defaultHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

        fetch('../api/update-homepage-settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.error || 'Something went wrong. Please try again.');
                }
                successBox.textContent = 'Saved — the public homepage now reflects these changes.';
                successBox.classList.remove('d-none');
            })
            .catch(function (err) {
                errorBox.textContent = err.message;
                errorBox.classList.remove('d-none');
            })
            .finally(function () {
                submitBtn.disabled = false;
                submitBtn.innerHTML = defaultHtml;
            });
    }
});
