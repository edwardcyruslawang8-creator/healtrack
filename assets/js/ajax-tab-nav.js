/* =============================================================
   PATIENT PROFILE — AJAX TAB NAVIGATION
   ---------------------------------------------------------------
   Every Patient Profile sub-page (patient_profile.php, medical_history.php,
   patient_laboratory_xray.php, vaccination_records.php, allergies.php,
   medications.php, documents.php, history.php) marks its own tab-strip
   links with data-ajax-tab and loads this file — see each of those
   files' own header comments. Clicking one fetches the target page's
   real HTML (same URL a normal click would have gone to — still a
   real, bookmarkable link if this script never loads), pulls out just
   its <main class="adm-main">, and swaps that in for this page's own
   <main> — the 280px sidebar/topbar frame around it never re-renders,
   so switching tabs doesn't flash to blank/white like a real page load
   does.

   Stylesheets/scripts the target page needs that this one doesn't
   already have are added too:
     - <link rel="stylesheet"> — added once, by href; harmless to add
       more than once but pointless, so skipped if already present.
     - <script src> — anything NOT part of the very first real page
       load's own baseline (Bootstrap bundle, admin-sidebar-toggle.js,
       hc-dialogs.js, theme.js, ajax-tab-nav.js itself, etc. — shared
       by literally every page in this family) is RE-injected fresh on
       EVERY visit to that tab, not just the first. That's deliberate:
       most of this app's per-page scripts bind straight to `document`
       (event delegation — see e.g. assets/js/view-details-action.js's
       own header comment on why), which survives a <main> swap fine on
       its own either way, but a few bind to one cached element
       reference up front (assets/js/patient-history-filters.js's own
       filter <form>) — that reference is gone once <main> is replaced,
       so without a fresh re-run the form would silently stop working
       the second time its tab is visited. The real DOMContentLoaded
       event only ever fires once per real page load, so after
       injecting that page's new scripts this dispatches a synthetic
       one once they've all loaded — every one of this app's per-page
       scripts is written as `document.addEventListener('DOMContentLoaded', ...)`
       specifically so this works. Trade-off: a script that itself adds
       a `document`-level delegated listener (rather than a one-off
       action) does end up with one more such listener per revisit to
       that same tab within one visit to this page — harmless for
       every current script (each one's handler is idempotent — "show
       the same modal again", "re-fetch the same table again" — just
       not perfectly free), not worth a full teardown/re-init lifecycle
       for what this app actually needs.
   ============================================================= */
(function () {
    var mainSelector = 'main.adm-main';
    if (!document.querySelector(mainSelector)) return;

    var baselineScriptSrcs = new Set();
    document.querySelectorAll('script[src]').forEach(function (s) { baselineScriptSrcs.add(s.src); });
    var baselineStylesheetHrefs = new Set();
    document.querySelectorAll('link[rel="stylesheet"][href]').forEach(function (l) { baselineStylesheetHrefs.add(l.href); });

    function addMissingStylesheets(doc) {
        doc.querySelectorAll('link[rel="stylesheet"][href]').forEach(function (l) {
            if (baselineStylesheetHrefs.has(l.href)) return;
            if (document.querySelector('link[rel="stylesheet"][href="' + l.getAttribute('href') + '"]')) return;
            var fresh = document.createElement('link');
            fresh.rel = 'stylesheet';
            fresh.href = l.getAttribute('href');
            document.head.appendChild(fresh);
            baselineStylesheetHrefs.add(l.href);
        });
    }

    function rerunPageScripts(doc) {
        var toRun = Array.prototype.slice.call(doc.querySelectorAll('script[src]'))
            .filter(function (s) { return s.src && !baselineScriptSrcs.has(s.src); });
        if (!toRun.length) return;

        var remaining = toRun.length;
        var fireWhenReady = function () {
            remaining--;
            if (remaining <= 0) {
                document.dispatchEvent(new Event('DOMContentLoaded', { bubbles: true, cancelable: true }));
            }
        };
        toRun.forEach(function (oldScript) {
            var fresh = document.createElement('script');
            fresh.src = oldScript.getAttribute('src');
            fresh.onload = fireWhenReady;
            fresh.onerror = fireWhenReady;
            document.body.appendChild(fresh);
        });
    }

    function swapTo(url, pushState) {
        var mainEl = document.querySelector(mainSelector);
        if (!mainEl) { window.location.href = url; return; }

        mainEl.classList.add('ajax-tab-loading');

        fetch(url)
            .then(function (res) {
                if (!res.ok) throw new Error('Request failed');
                return res.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var newMain = doc.querySelector(mainSelector);
                if (!newMain) { window.location.href = url; return; }

                addMissingStylesheets(doc);
                if (doc.title) document.title = doc.title;

                document.querySelector(mainSelector).replaceWith(newMain);
                if (pushState) {
                    window.history.pushState({ ajaxTab: true }, '', url);
                }
                window.scrollTo(0, 0);

                rerunPageScripts(doc);
            })
            .catch(function () {
                // Real navigation is still a correct fallback — every
                // data-ajax-tab link is a real, working href.
                window.location.href = url;
            });
    }

    document.addEventListener('click', function (e) {
        var link = e.target.closest('[data-ajax-tab]');
        if (!link) return;
        var href = link.getAttribute('href');
        if (!href) return;
        e.preventDefault();
        swapTo(href, true);
    });

    window.addEventListener('popstate', function () {
        swapTo(window.location.href, false);
    });
})();
