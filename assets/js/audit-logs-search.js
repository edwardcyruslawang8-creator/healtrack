/* =============================================================
   AUDIT LOGS — SEARCH-BAR USER AUTOCOMPLETE
   ---------------------------------------------------------------
   Shared by admin/audit_logs.php and subadmin/audit_logs.php. Typing
   into the search box shows real user names — from
   window.AUDIT_USER_LIST, the exact same real, full-dataset list the
   filter toolbar's own "User" dropdown options come from (see
   includes/admin/audit_logs_firestore.php's / includes/subadmin/subadmin_audit_logs_firestore.php's
   $seenUsers) — that contain what's typed so far. Clicking one jumps
   straight to that person's own filtered log the same way picking
   them from the User filter dropdown would (?user=<name>), replacing
   the free-text search rather than combining with it.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('auditSearchInput');
    const box = document.getElementById('auditSearchSuggest');
    const users = Array.isArray(window.AUDIT_USER_LIST) ? window.AUDIT_USER_LIST : [];
    if (!input || !box || users.length === 0) return;

    function hide() {
        box.hidden = true;
        box.innerHTML = '';
    }

    function goToUser(name) {
        const url = new URL(window.location.href);
        url.searchParams.set('user', name);
        url.searchParams.delete('q');
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }

    function render(matches) {
        box.innerHTML = '';
        matches.forEach(function (name) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'aud-search-suggest-item';
            item.textContent = name;
            // mousedown (not click) fires before the input's own blur
            // handler hides the box, so the click always lands.
            item.addEventListener('mousedown', function (e) {
                e.preventDefault();
                goToUser(name);
            });
            box.appendChild(item);
        });
        box.hidden = matches.length === 0;
    }

    input.addEventListener('input', function () {
        const q = input.value.trim().toLowerCase();
        if (q === '') { hide(); return; }
        const matches = users.filter(function (name) {
            return name.toLowerCase().includes(q);
        }).slice(0, 8);
        render(matches);
    });

    input.addEventListener('blur', function () { setTimeout(hide, 120); });
    input.addEventListener('keydown', function (e) { if (e.key === 'Escape') hide(); });
});
