/* =============================================================
   PROFILE PHOTO — picker, live preview, and real upload
   (Profile / Settings, all 6 roles)
   ---------------------------------------------------------------
   The picker + instant preview (swap the avatar circle's icon for the
   picked image right away, before Save Changes is even clicked) are
   unchanged from before. What's new: window.HCProfilePhoto.uploadIfSelected(),
   called by assets/js/profile-settings.js (admin) and
   assets/js/subadmin-profile-settings.js (the other 5 roles) as the
   FIRST step of their "Save Changes" handler — resolves immediately
   (no-op) if the user never picked a new photo, otherwise resizes it
   client-side (a canvas, not the original file — this app has no
   server-side image library to do it, and nobody needs a full-resolution
   photo for an 80px sidebar circle) and uploads the result to
   api/upload-profile-photo.php, which is what actually persists it
   (Firebase Cloud Storage isn't enabled for this project — see that
   API's own header comment for the real reason this wasn't wired up
   before, and includes/shared/profile_photo_storage.php for where the
   file actually lives).
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('prfPhotoUploadBtn');
    const input = document.getElementById('prfPhotoInput');
    const preview = document.getElementById('prfPhotoPreview');

    // Always defined, even on a page with no photo picker, so a save
    // handler can call it unconditionally without an extra existence
    // check of its own.
    window.HCProfilePhoto = {
        uploadIfSelected: function () {
            return new Promise(function (resolve, reject) {
                const file = input && input.files && input.files[0];
                if (!file) { resolve(); return; }

                resizeImageToJpegBlob(file, 400, 0.85)
                    .then(function (blob) {
                        const formData = new FormData();
                        formData.append('photo', blob, 'photo.jpg');
                        return fetch('../api/upload-profile-photo.php', { method: 'POST', body: formData });
                    })
                    .then(function (res) {
                        return res.json().then(function (data) { return { ok: res.ok, data: data }; });
                    })
                    .then(function (result) {
                        if (!result.ok || !result.data.success) {
                            throw new Error(result.data.error || 'Could not save the photo. Please try again.');
                        }
                        resolve();
                    })
                    .catch(reject);
            });
        },
    };

    /**
     * Downscales/re-encodes an arbitrary picked image file to a small
     * JPEG via <canvas> — keeps the upload tiny (typically tens of KB
     * regardless of what the original photo was) and normalizes every
     * upload to one predictable format server-side. maxDim bounds
     * whichever of width/height is larger; aspect ratio is preserved.
     */
    function resizeImageToJpegBlob(file, maxDim, quality) {
        return new Promise(function (resolve, reject) {
            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = function () {
                URL.revokeObjectURL(url);
                let w = img.naturalWidth;
                let h = img.naturalHeight;
                if (w <= 0 || h <= 0) { reject(new Error('This image could not be read.')); return; }
                const scale = Math.min(1, maxDim / Math.max(w, h));
                w = Math.round(w * scale);
                h = Math.round(h * scale);

                const canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, w, h);
                canvas.toBlob(function (blob) {
                    if (!blob) { reject(new Error('This image could not be processed.')); return; }
                    resolve(blob);
                }, 'image/jpeg', quality);
            };
            img.onerror = function () {
                URL.revokeObjectURL(url);
                reject(new Error('Please choose a valid image file.'));
            };
            img.src = url;
        });
    }

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
