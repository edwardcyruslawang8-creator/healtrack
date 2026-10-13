/* =============================================================
   HEALTRACK — SHARED CONFIRM / ALERT DIALOGS
   ---------------------------------------------------------------
   Replaces window.confirm()/window.alert() app-wide. Native browser
   dialogs are prefixed by the browser with the page's own origin
   (e.g. "localhost says" in dev — the real deployed domain once this
   is live), which reads as a browser warning rather than part of the
   app. This builds the same kind of Bootstrap 5 modal this app
   already hand-builds per page for its own confirmations (e.g.
   subadmin/user_management.php's #usmDeleteModal), but ONE shared,
   dynamically-created copy any page can reuse via two globals:

     HCDialog.confirm(message, opts) -> Promise<boolean>
     HCDialog.alert(message, opts)   -> Promise<void>

   opts (both optional): { title, confirmText, cancelText }

   Falls back to the real window.confirm()/window.alert() if Bootstrap
   somehow isn't loaded on a page — defensive only; every real page
   that calls this already loads bootstrap.bundle.min.js first (see
   assets/css/hc-dialogs.css's own header comment for the styling
   half of this).
   ============================================================= */
(function () {
    function escapeForText(el, text) {
        el.textContent = text;
    }

    function buildModal(kind, message, opts) {
        var title = opts.title || (kind === 'confirm' ? 'Please Confirm' : 'Notice');
        var confirmText = opts.confirmText || (kind === 'confirm' ? 'Confirm' : 'OK');
        var cancelText = opts.cancelText || 'Cancel';
        var icon = kind === 'confirm' ? 'bi-question-circle-fill' : 'bi-info-circle-fill';

        var wrap = document.createElement('div');
        wrap.className = 'modal fade hcd-modal';
        wrap.setAttribute('tabindex', '-1');
        wrap.setAttribute('aria-hidden', 'true');
        wrap.innerHTML =
            '<div class="modal-dialog modal-dialog-centered">' +
                '<div class="modal-content hcd-modal-content">' +
                    '<div class="modal-body">' +
                        '<div class="hcd-icon"><i class="bi ' + icon + '"></i></div>' +
                        '<h5 class="hcd-title"></h5>' +
                        '<p class="hcd-message"></p>' +
                    '</div>' +
                    '<div class="modal-footer">' +
                        (kind === 'confirm' ? '<button type="button" class="hcd-btn-cancel"></button>' : '') +
                        '<button type="button" class="hcd-btn-confirm"></button>' +
                    '</div>' +
                '</div>' +
            '</div>';

        escapeForText(wrap.querySelector('.hcd-title'), title);
        escapeForText(wrap.querySelector('.hcd-message'), message);
        escapeForText(wrap.querySelector('.hcd-btn-confirm'), confirmText);
        if (kind === 'confirm') {
            escapeForText(wrap.querySelector('.hcd-btn-cancel'), cancelText);
        }

        document.body.appendChild(wrap);
        return wrap;
    }

    function showModal(kind, message, opts) {
        opts = opts || {};

        if (!window.bootstrap || !window.bootstrap.Modal) {
            // Defensive fallback — see header comment. Every real caller's
            // page already loads Bootstrap, so this path shouldn't run.
            if (kind === 'confirm') return Promise.resolve(window.confirm(message));
            window.alert(message);
            return Promise.resolve();
        }

        return new Promise(function (resolve) {
            var el = buildModal(kind, message, opts);
            var modal = new window.bootstrap.Modal(el);
            var resolved = false;

            function cleanup() {
                el.removeEventListener('hidden.bs.modal', onHidden);
                if (el.parentNode) el.parentNode.removeChild(el);
            }
            function onHidden() {
                cleanup();
                if (!resolved) {
                    resolved = true;
                    resolve(kind === 'confirm' ? false : undefined);
                }
            }
            el.addEventListener('hidden.bs.modal', onHidden);

            el.querySelector('.hcd-btn-confirm').addEventListener('click', function () {
                resolved = true;
                modal.hide();
                resolve(kind === 'confirm' ? true : undefined);
            });

            var cancelBtn = el.querySelector('.hcd-btn-cancel');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function () {
                    resolved = true;
                    modal.hide();
                    resolve(false);
                });
            }

            modal.show();
        });
    }

    window.HCDialog = {
        confirm: function (message, opts) { return showModal('confirm', message, opts); },
        alert: function (message, opts) { return showModal('alert', message, opts); },
    };
})();
