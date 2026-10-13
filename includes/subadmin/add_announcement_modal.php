<!-- =============================================================
     NEW ANNOUNCEMENT MODAL
     -------------------------------------------------------------
     Triggered by subadmin/announcements.php's "+ New Announcement"
     button (#btnAddAnnouncement). assets/js/announcements_subadmin.js
     POSTs this form to api/create-announcement.php, which writes a
     real doc to the "announcements" Firestore collection — the same
     collection patient/announcements.php (public homepage's
     Announcements page) and index.php's "latest 3" widget already
     read, so a posted announcement appears there immediately, no
     approval step. Reuses the "am-" (Add Modal) styling from
     assets/css/add_medicine_modal.css rather than writing new CSS —
     same generic form-modal look every other "Add" modal in this app
     uses.
     ============================================================= -->
<div class="modal fade am-modal" id="addAnnouncementModal" tabindex="-1" aria-labelledby="addAnnouncementModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="addAnnouncementForm" novalidate>
        <div class="modal-header">
          <h5 class="modal-title" id="addAnnouncementModalLabel">New Announcement</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="addAnnouncementError" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>

          <div class="mb-3">
            <label class="am-form-label" for="anTitle">Title<span class="text-danger">*</span></label>
            <input type="text" class="form-control am-form-control" id="anTitle" placeholder="e.g. Flu Vaccination Drive This Weekend" required>
          </div>

          <div class="mb-3">
            <label class="am-form-label" for="anDesc">Description<span class="text-danger">*</span></label>
            <textarea class="form-control am-form-control" id="anDesc" rows="4" placeholder="What patients need to know" required></textarea>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="am-form-label" for="anCategory">Category<span class="text-danger">*</span></label>
              <select class="form-select am-form-control" id="anCategory" required>
                <option value="general" selected>General</option>
                <option value="alert">Alert</option>
                <option value="event">Event</option>
              </select>
            </div>
            <div class="col-md-6 mb-3 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="anImportant">
                <label class="form-check-label am-form-label mb-0" for="anImportant">Mark as Important</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="am-btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="am-btn-submit" id="anSubmitBtn"><i class="bi bi-megaphone-fill"></i> <span id="anSubmitLabel">Post Announcement</span></button>
        </div>
      </form>
    </div>
  </div>
</div>
