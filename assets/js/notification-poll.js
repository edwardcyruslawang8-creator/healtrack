/* =============================================================
   LIVE NOTIFICATION POLLING (every signed-in page, all 6 roles with a
   real notification system)
   ---------------------------------------------------------------
   Injected by includes/shared/notification_poll.php's
   hc_render_notification_poll_script() — see that file's header
   comment for the full design (why there's no per-page markup to edit,
   how the sidebar's per-role badge class is found/created). Reads
   window.HC_NOTIF_POLL it also sets: {endpointUrl, notificationsUrl,
   sidebarBadgeClass, initialUnreadCount, intervalMs}.

   Every intervalMs, fetches endpointUrl (api/get-unread-count.php) and:
     - keeps the topbar bell + sidebar nav badges in sync with the real
       server-side count, creating or removing either span as the count
       crosses 0 (neither one exists in the DOM at all when a page first
       loads with nothing unread — see notification_poll.php's comment).
     - shows a bottom-right toast the moment the count goes UP since the
       last poll (a genuinely new notification arrived) — never on a
       decrease (that's just something getting read/archived elsewhere,
       nothing new to announce) and never on the very first poll
       (seeded from initialUnreadCount, the same real number this page
       already rendered server-side, not treated as an "increase" from
       zero).

   Skips itself entirely on notifications.php — that page already keeps
   its own badges live from real actions taken right on it
   (assets/js/notifications.js); running this too would just be a
   second poller fighting over the same elements.
   ============================================================= */
(function () {
    var config = window.HC_NOTIF_POLL;
    if (!config || !config.endpointUrl) return;
    if (document.getElementById('ntfTableBody')) return;

    var lastCount = config.initialUnreadCount || 0;

    function findBellBadge() {
        var btn = document.querySelector('.adm-bell-btn');
        if (!btn) return null;
        return { container: btn, el: btn.querySelector('.adm-badge-count'), className: 'adm-badge-count' };
    }

    function findSidebarBadge() {
        var link = document.querySelector('a[href="notifications.php"]');
        if (!link) return null;
        return { container: link, el: link.querySelector('span[class*="nav-badge"]'), className: config.sidebarBadgeClass };
    }

    function syncBadge(target) {
        if (!target || !target.container) return;
        if (lastCount > 0) {
            if (!target.el) {
                target.el = document.createElement('span');
                target.el.className = target.className;
                target.container.appendChild(target.el);
            }
            target.el.textContent = lastCount;
        } else if (target.el && target.el.parentNode) {
            target.el.parentNode.removeChild(target.el);
        }
    }

    function showNewNotificationToast(increaseBy) {
        var existing = document.getElementById('hcNewNotifToast');
        if (existing && existing.parentNode) existing.parentNode.removeChild(existing);

        var el = document.createElement('div');
        el.id = 'hcNewNotifToast';
        el.setAttribute('role', 'status');
        el.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;background:#1F2937;color:#fff;'
            + 'padding:16px 20px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.3);'
            + 'font:14px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;max-width:320px;';
        var text = increaseBy === 1 ? 'You have a new notification.' : ('You have ' + increaseBy + ' new notifications.');
        el.innerHTML =
            '<div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:12px;">'
            + '<i class="bi bi-bell-fill" style="color:#E8710A;font-size:18px;line-height:1.5;"></i>'
            + '<div>' + text + '</div>'
            + '</div>'
            + '<div style="display:flex;gap:8px;justify-content:flex-end;">'
            + '<button type="button" id="hcNewNotifDismiss" style="background:transparent;color:#D1D5DB;border:none;'
            + 'padding:7px 12px;cursor:pointer;font-weight:600;font-size:13px;">Dismiss</button>'
            + '<a href="' + config.notificationsUrl + '" style="background:#2563EB;color:#fff;border:none;'
            + 'border-radius:6px;padding:7px 16px;cursor:pointer;font-weight:600;font-size:13px;text-decoration:none;display:inline-block;">'
            + 'View</a>'
            + '</div>';
        document.body.appendChild(el);

        function close() {
            if (el.parentNode) el.parentNode.removeChild(el);
        }
        document.getElementById('hcNewNotifDismiss').addEventListener('click', close);
        setTimeout(close, 15000);
    }

    function poll() {
        fetch(config.endpointUrl, { credentials: 'same-origin' })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                if (!data || !data.success || typeof data.unreadCount !== 'number') return;

                if (data.unreadCount > lastCount) {
                    showNewNotificationToast(data.unreadCount - lastCount);
                }
                lastCount = data.unreadCount;
                syncBadge(findBellBadge());
                syncBadge(findSidebarBadge());
            })
            .catch(function () { /* best-effort — tries again next interval */ });
    }

    setInterval(poll, config.intervalMs || 20000);
})();
