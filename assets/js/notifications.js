/* =============================================================
   NOTIFICATIONS JS
   (shared as-is by subadmin/, medical_technician/, doctor/,
   medical-staff/, and pharmacist/'s notifications.php — DOM ids only,
   no role-specific logic, so every selector below is null-guarded)
   ---------------------------------------------------------------
   Genuinely interactive client-side behavior for the notifications
   table — a synced unread counter (tab badge + sidebar badge + topbar
   bell badge), all three tabs (All / Unread / Archived — the old
   System Alerts/Reminders/Updates category tabs were removed), a Type
   filter, search, and clicking a notification row (or its "View
   Details" button) to open a real
   Notification Details modal — which also marks that notification
   read. There's no Delete action and no bulk checkbox-selection UI on
   any role's page any more — "Mark all as read" and each row's own
   Archive/Unarchive button are the other real actions here.

   Marking read (individually via the details modal, or "Mark all as
   read") POSTs to api/mark-notifications-read.php, which stores the
   read ids in $_SESSION['ntfReadIds']. Archiving/unarchiving a row
   POSTs to api/archive-notifications.php / api/unarchive-notifications.php,
   which store archived ids in $_SESSION['ntfDismissedIds'] the same
   way. Both survive navigating to another page (server-side session
   state) and logging out and back in (also written to the signed-in
   user's own Firestore doc, read back in api/session-login.php). An
   archived row stays in the DOM (so switching to the Archived tab
   still shows it) but is hidden from every other tab — see
   applyFilters() below.

   Pagination is client-side too, over the full (unsliced) set of rows
   the server renders — it used to be server-side (?page=N links,
   includes/shared/pagination_helpers.php's hc_paginate()/
   hc_render_pagination_nav()), which meant a page click reloaded the
   whole page AND re-ran with the tab reset to "All Notifications"
   (that tab state only ever lived in this file's own `activeTab`
   variable, never the URL), plus whichever 8 rows happened to fall on
   that server-sliced page might not match the tab you were actually
   on — e.g. clicking page 2 while on "Archived" could land you back on
   "All" showing unrelated rows, or land on an "Archived" page with
   nothing on it because none of that page's 8 rows happened to be
   archived. Paginating over the FILTERED row set here instead fixes
   both: the active tab never resets (nothing reloads), and "page 2"
   always means "page 2 of what this tab is actually showing right
   now." See applyFilters()/renderPaginationNav() below.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.getElementById('ntfTableBody');
    const markAllReadLink = document.getElementById('ntfMarkAllRead');
    const searchInput = document.getElementById('ntfSearchInput');
    const typeFilter = document.getElementById('ntfTypeFilter');
    const unreadTabBadge = document.querySelector('[data-ntf-tab-badge="unread"]');
    const archivedTabBadge = document.querySelector('[data-ntf-tab-badge="archived"]');
    const bellBadge = document.getElementById('ntfBellBadge');
    const sidebarBadge = document.getElementById('ntfSidebarBadge');
    const detailsModalEl = document.getElementById('ntfDetailsModal');
    const paginationNav = document.getElementById('ntfPaginationNav');
    const showingText = document.querySelector('.adm-showing-text');

    if (!tableBody) return;

    let activeTab = 'all';
    let currentPage = 1;
    const perPage = 8;

    function getRows() {
        return Array.from(tableBody.querySelectorAll('.ntf-row'));
    }

    /* ---------- Unread counter, synced across tab badge / sidebar / bell ----------
       Starts from the REAL total across every notification this role has
       (tableBody's data-initial-unread, server-rendered from the exact
       same hc_*_unread_notification_count() every other page's own
       badge already uses) — NOT recomputed from this page's own ~8 DOM
       rows. Recomputing from the DOM was the actual bug: this list
       paginates, so a role with more unread notifications than fit on
       one page would see a small, page-local number here while every
       other page correctly showed the real total. From here on,
       markRowRead()/the "Mark all as read" handler below adjust this
       one running count instead of re-deriving it from the DOM. */
    let unreadCount = parseInt(tableBody.dataset.initialUnread || '0', 10) || 0;

    // "All Notifications" has no badge at all (see any
    // includes/*/notifications_*_firestore.php's header comment on
    // $notificationTabs) — it went through two designs (mirroring this
    // unread count, then a real total count) before landing on no
    // number at all being the clearest option.
    function syncUnreadBadges() {
        // These badges only appear when there's actually something to
        // show — a "0" badge next to "Unread" reads as if there's one
        // unread item, not zero, so it's hidden instead once the count
        // hits 0.
        [unreadTabBadge, bellBadge, sidebarBadge].forEach(function (el) {
            if (!el) return;
            el.textContent = unreadCount;
            el.style.display = unreadCount > 0 ? '' : 'none';
        });
    }

    function updateArchivedTabBadge() {
        const count = getRows().filter(function (row) { return row.dataset.archived === '1'; }).length;
        if (archivedTabBadge) {
            archivedTabBadge.textContent = count;
            archivedTabBadge.style.display = count > 0 ? '' : 'none';
        }
    }

    /* ---------- Mark a row read (removes its unread flag + flips its Unread/Read badge) ---------- */
    function markRowRead(row) {
        if (row.dataset.unread === '1') {
            unreadCount = Math.max(0, unreadCount - 1);
        }
        row.dataset.unread = '0';
        row.classList.remove('ntf-row-unread');
        var badge = row.querySelector('.ntf-read-badge');
        if (badge) {
            badge.textContent = 'Read';
            badge.classList.remove('unread');
            badge.classList.add('read');
        }
    }

    /* ---------- Persist read state server-side (see header comment) ---------- */
    function persistReadIds(ids) {
        fetch('../api/mark-notifications-read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: ids }),
        }).catch(function () {
            // Best-effort — the sidebar/bell badge on THIS page is already
            // correct from the client-side DOM update either way; a failed
            // request here just means other pages won't reflect it until
            // retried, not a broken interaction on this page.
        });
    }

    // Sends {all: true} rather than gathering ids from the table — the
    // table only ever holds ONE pagination page's worth of rows
    // (doctor/notifications.php and friends paginate at 8 per page), so
    // gathering ids from the DOM here used to mark only the current
    // page's notifications read, leaving everything on other pages
    // still unread — which is exactly why the sidebar/bell badge kept
    // showing a nonzero count on other pages even right after clicking
    // "Mark all as read". The server now computes the role's real,
    // COMPLETE id universe itself (api/mark-notifications-read.php),
    // the same live sources every page's own badge count already reads
    // from, so this works correctly no matter how many pages of
    // notifications exist.
    function persistAllRead() {
        fetch('../api/mark-notifications-read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ all: true }),
        }).catch(function () {});
    }

    // Rows start with the unread-dot class applied if they were seeded unread.
    getRows().forEach(function (row) {
        row.classList.toggle('ntf-row-unread', row.dataset.unread === '1');
    });

    markAllReadLink.addEventListener('click', function () {
        getRows().forEach(markRowRead);
        // markRowRead() above only accounted for THIS page's rows, but
        // the real action (persistAllRead() below) marks EVERY
        // notification read, everywhere — force the running total to
        // the true post-action value (0) rather than whatever partial
        // decrement this page's own rows produced.
        unreadCount = 0;
        syncUnreadBadges();
        // If "Unread" is the active tab, marking everything read empties it —
        // re-apply filters so the (now correctly empty) table reflects that
        // instead of still showing rows that no longer belong there.
        applyFilters();
        persistAllRead();
    });

    /* ---------- Pagination nav — rendered here, not by the server ----------
       Same markup/classes includes/shared/pagination_helpers.php's
       hc_render_pagination_nav() produces (.adm-pagination/.page-btn/
       .page-ellipsis/.active/.disabled), just as real <button>s wired
       to currentPage instead of <a href="?page=N">s — see this file's
       header comment for why. totalPages is always of the CURRENTLY
       FILTERED row set (whatever tab/type/search is active right now),
       recomputed on every applyFilters() call below. */
    function renderPaginationNav(totalPages) {
        if (!paginationNav) return;
        if (totalPages <= 1) {
            paginationNav.innerHTML = '';
            return;
        }

        const atFirst = currentPage <= 1;
        const atLast = currentPage >= totalPages;
        const windowStart = Math.max(1, Math.min(currentPage, Math.max(1, totalPages - 2)));
        const windowEnd = Math.min(totalPages, windowStart + 2);

        function pageBtn(page, label, extraClass, ariaLabel, disabled) {
            return '<button type="button" class="page-btn' + (extraClass ? ' ' + extraClass : '') + '"'
                + ' data-ntf-page="' + page + '"'
                + (ariaLabel ? ' aria-label="' + ariaLabel + '"' : '')
                + (disabled ? ' disabled' : '')
                + '>' + label + '</button>';
        }

        let html = '';
        html += pageBtn(1, '<i class="bi bi-chevron-bar-left"></i>', atFirst ? 'disabled' : '', 'First page', atFirst);
        html += pageBtn(Math.max(1, currentPage - 1), '<i class="bi bi-chevron-left"></i>', atFirst ? 'disabled' : '', 'Previous page', atFirst);
        for (let p = windowStart; p <= windowEnd; p++) {
            html += pageBtn(p, String(p), p === currentPage ? 'active' : '', null, false);
        }
        if (windowEnd < totalPages) {
            html += '<span class="page-ellipsis">&hellip;</span>';
            html += pageBtn(totalPages, String(totalPages), '', null, false);
        }
        html += pageBtn(Math.min(totalPages, currentPage + 1), '<i class="bi bi-chevron-right"></i>', atLast ? 'disabled' : '', 'Next page', atLast);
        html += pageBtn(totalPages, '<i class="bi bi-chevron-bar-right"></i>', atLast ? 'disabled' : '', 'Last page', atLast);

        paginationNav.innerHTML = html;
    }

    if (paginationNav) {
        paginationNav.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-ntf-page]');
            if (!btn || btn.disabled) return;
            const page = parseInt(btn.dataset.ntfPage, 10);
            if (!page || page === currentPage) return;
            currentPage = page;
            applyFilters();
        });
    }

    function updateShowingText(totalFiltered, startNum, endNum) {
        if (!showingText) return;
        showingText.textContent = totalFiltered > 0
            ? 'Showing ' + startNum + ' to ' + endNum + ' of ' + totalFiltered + ' notifications'
            : 'No notifications to show';
    }

    /* ---------- Archive / Unarchive a single row (see header comment) ---------- */
    function setRowArchived(row, archived) {
        row.dataset.archived = archived ? '1' : '0';

        var actionsCell = row.querySelector('.adm-action-btns');
        var toggleBtn = actionsCell && actionsCell.querySelector('.ntf-archive-btn, .ntf-unarchive-btn');
        if (toggleBtn) {
            if (archived) {
                toggleBtn.className = 'ntf-unarchive-btn';
                toggleBtn.textContent = 'Unarchive';
            } else {
                toggleBtn.className = 'ntf-archive-btn';
                toggleBtn.textContent = 'Archive';
            }
        }
    }

    function persistArchived(id, archived) {
        fetch(archived ? '../api/archive-notifications.php' : '../api/unarchive-notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: [id] }),
        }).catch(function () {
            // Best-effort — the row's own state on THIS page is already
            // correct from the client-side DOM update either way, same
            // reasoning as persistReadIds() above.
        });
    }

    tableBody.addEventListener('click', function (e) {
        var btn = e.target.closest('.ntf-archive-btn, .ntf-unarchive-btn');
        if (!btn) return;

        var row = btn.closest('.ntf-row');
        if (!row) return;

        var archiving = btn.classList.contains('ntf-archive-btn');
        setRowArchived(row, archiving);
        updateArchivedTabBadge();
        // Archiving takes a row out of "Unread"/the sidebar/bell's count
        // too if it was still unread; unarchiving puts it back. Either
        // way the currently active tab's view needs re-applying right now.
        if (row.dataset.unread === '1') {
            unreadCount = Math.max(0, unreadCount + (archiving ? -1 : 1));
        }
        syncUnreadBadges();
        applyFilters();
        persistArchived(row.dataset.id, archiving);
    });

    /* ---------- Click a notification (row or its "View Details" button) -> open Notification Details modal + mark it read ---------- */
    function openDetailsModal(row) {
        const set = function (id, value) {
            const el = document.getElementById(id);
            if (el) el.textContent = value || '—';
        };

        const titleEl = row.querySelector('.ntf-notif-title');
        const descEl = row.querySelector('.ntf-notif-desc');

        set('ntfDetailTitle', titleEl ? titleEl.textContent : '');
        set('ntfDetailDesc', descEl ? descEl.textContent : '');
        set('ntfDetailDatetime', (row.dataset.date || '') + ' • ' + (row.dataset.time || ''));
        set('ntfDetailType', row.dataset.type);

        const wasUnread = row.dataset.unread === '1';
        const statusEl = document.getElementById('ntfDetailStatus');
        if (statusEl) {
            statusEl.textContent = wasUnread ? 'Unread' : 'Read';
            statusEl.style.color = wasUnread ? '#D93025' : '#1E8E3E';
        }

        const iconWrap = document.getElementById('ntfDetailIcon');
        if (iconWrap) {
            iconWrap.style.background = row.dataset.iconBg || '';
            iconWrap.style.color = row.dataset.iconColor || '';
            iconWrap.innerHTML = '<i class="bi ' + (row.dataset.icon || 'bi-bell-fill') + '"></i>';
        }

        if (wasUnread) {
            markRowRead(row);
            syncUnreadBadges();
            if (activeTab === 'unread') applyFilters();
            persistReadIds([row.dataset.id]);
        }

        if (detailsModalEl) bootstrap.Modal.getOrCreateInstance(detailsModalEl).show();
    }

    tableBody.addEventListener('click', function (e) {
        // Archive/Unarchive clicks are handled by their own listener
        // above and must never also open the details modal.
        if (e.target.closest('.ntf-archive-btn, .ntf-unarchive-btn')) return;

        const row = e.target.closest('.ntf-row');
        if (!row) return;

        openDetailsModal(row);
    });

    /* ---------- Filtering: tabs (All / Unread / Archived) + Type + Search, all combined ---------- */
    document.querySelectorAll('[data-ntf-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-ntf-tab]').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            activeTab = btn.getAttribute('data-ntf-tab');
            // Switching tabs/filters changes what "page 1" even means (a
            // different row set), so always land back on it rather than
            // keeping whatever page number happened to be active before.
            currentPage = 1;
            applyFilters();
        });
    });

    function matchesFilters(row, query, typeValue) {
        if (activeTab === 'archived') {
            if (row.dataset.archived !== '1') return false;
        } else if (row.dataset.archived === '1') {
            // An archived row is hidden from every OTHER tab —
            // archiving is what actually takes it out of the main view.
            return false;
        } else if (activeTab === 'unread') {
            if (row.dataset.unread !== '1') return false;
        }
        // activeTab === 'all' shows everything else (subject to the filters below).

        if (typeValue !== 'All Types' && row.dataset.type !== typeValue) return false;
        if (query && row.dataset.title.indexOf(query) === -1 && row.dataset.desc.indexOf(query) === -1) return false;

        return true;
    }

    /* Does double duty: picks which rows match the active tab/type/search
       (same as before), AND which of those land on the current page —
       the only rows actually shown are the ones that do both. Rows that
       fail the filter stay hidden regardless of page; rows that pass the
       filter but fall outside the current page's slice are hidden too, by
       the same single d-none toggle, so there's only ever one source of
       truth for a row's visibility. */
    function applyFilters() {
        const query = searchInput.value.trim().toLowerCase();
        const typeValue = typeFilter.value;

        const filtered = getRows().filter(function (row) { return matchesFilters(row, query, typeValue); });
        const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIdx = (currentPage - 1) * perPage;
        const pageRows = filtered.slice(startIdx, startIdx + perPage);
        const pageRowSet = new Set(pageRows);

        getRows().forEach(function (row) {
            row.classList.toggle('d-none', !pageRowSet.has(row));
        });

        updateShowingText(filtered.length, filtered.length === 0 ? 0 : startIdx + 1, startIdx + pageRows.length);
        renderPaginationNav(totalPages);
    }

    searchInput.addEventListener('input', function () { currentPage = 1; applyFilters(); });
    typeFilter.addEventListener('change', function () { currentPage = 1; applyFilters(); });

    syncUnreadBadges();
    applyFilters();
});
