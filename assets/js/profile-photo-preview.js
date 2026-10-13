/* =============================================================
   PROFILE PHOTO — client-side preview only (Profile / Settings, all 6 roles)
   ---------------------------------------------------------------
   Firebase Cloud Storage is not enabled for this project, so there is
   nowhere to persist an uploaded photo yet. This swaps the avatar
   circle's icon for the picked image for the rest of this session
   only (same "preview, not persisted" pattern as the Documents
   upload panel elsewhere in this app) — it resets on reload.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('prfPhotoUploadBtn');
    const input = document.getElementById('prfPhotoInput');
    const preview = document.getElementById('prfPhotoPreview');
    if (!btn || !input || !preview) return;

    btn.addEventListener('click', function () {
        input.click();
    });

    input.addEventListener('change', function () {
        const file = input.files && input.files[0];
        if (!file) return;

        if (!file.type.startsWith('image/')) {
            HCDialog.alert('Please choose an image file.');
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            preview.innerHTML = '';
            const img = document.createElement('img');
            img.src = e.target.result;
            img.alt = 'Profile photo preview';
            preview.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
});
