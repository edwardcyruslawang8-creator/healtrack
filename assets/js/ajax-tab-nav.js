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

    // Inline <style> blocks in <head> belong to one page (e.g. the
    // Analytics & Reports pages' grid/filter-bar rules) — swapped along
    // with <main>, unlike shared <link> stylesheets which only accumulate.
    var pageStyleAttr = 'data-ajax-page-style';
    document.querySelectorAll('head style').forEach(function (s) { s.setAttribute(pageStyleAttr, ''); });

    /** Adds the target page's missing stylesheets; resolves once they've loaded (or failed), so <main> is never shown unstyled. */
    function addMissingStylesheets(doc) {
        var pending = [];
        doc.querySelectorAll('link[rel="stylesheet"][href]').forEach(function (l) {
            if (baselineStylesheetHrefs.has(l.href)) return;
            if (document.querySelector('link[rel="stylesheet"][href="' + l.getAttribute('href') + '"]')) return;
            var fresh = document.createElement('link');
            fresh.rel = 'stylesheet';
            fresh.href = l.getAttribute('href');
            pending.push(new Promise(function (resolve) {
                fresh.onload = resolve;
                fresh.onerror = resolve;
            }));
            document.head.appendChild(fresh);
            baselineStylesheetHrefs.add(l.href);
        });
        return Promise.all(pending);
    }

    function swapPageStyles(doc) {
        document.querySelectorAll('head style[' + pageStyleAttr + ']').forEach(function (s) { s.remove(); });
        doc.querySelectorAll('head style').forEach(function (s) {
            var fresh = document.createElement('style');
            fresh.setAttribute(pageStyleAttr, '');
            fresh.textContent = s.textContent;
            document.head.appendChild(fresh);
        });
    }

    /**
     * Runs, in document order, the target page's scripts that need to
     * run again: every <script src> outside this page's baseline, plus
     * inline scripts that carry page data/init — id="reportDataScript"
     * (each Analytics & Reports page's chart data) or data-ajax-rerun.
     * Sequential, since a page's chart script reads the data script
     * above it. Then fires a synthetic DOMContentLoaded — always, even
     * when nothing was injected (returning to the page this visit
     * started on, whose scripts are all baseline), since that's what
     * every page script's init listens for.
     */
    function runOneScript(oldScript) {
        return new Promise(function (resolve) {
            var fresh = document.createElement('script');
            if (oldScript.hasAttribute('src')) {
                fresh.src = oldScript.getAttribute('src');
                fresh.onload = resolve;
                fresh.onerror = resolve;
            } else {
                fresh.textContent = oldScript.textContent;
                if (oldScript.id) fresh.id = oldScript.id;
            }
            var existing = fresh.id ? document.getElementById(fresh.id) : null;
            if (existing) existing.replaceWith(fresh); else document.body.appendChild(fresh);
            if (!fresh.src) resolve(); // inline scripts run synchronously on insert
        });
    }

    function rerunPageScripts(doc) {
        var all = Array.prototype.slice.call(doc.querySelectorAll('script')).filter(function (s) {
            // id="reportDataScript"/data-ajax-rerun scripts live INSIDE
            // <main> for every Analytics & Reports page (see the comment
            // above) — they must still be re-run even though <main> itself
            // was just swapped in via replaceWith(), which never executes
            // the script tags it brings along. Only an ordinary (unmarked)
            // in-main script is skipped here, since <main>'s own swap
            // already placed its markup; it was never relying on this
            // function to run it.
            if (s.closest(mainSelector)) return s.id === 'reportDataScript' || s.hasAttribute('data-ajax-rerun');
            if (s.hasAttribute('src')) return !baselineScriptSrcs.has(s.src);
            return s.id === 'reportDataScript' || s.hasAttribute('data-ajax-rerun');
        });

        // The library <script src> files a report page's own init code
        // calls into (e.g. predictive_analytics.php's half-dozen small
        // "assets/js/*-forecast.js"/"*-projection.js" helper files) don't
        // depend on each other — only the data-ajax-rerun init code at the
        // bottom actually reads from them, via a plain `if (window.HCXxx)`
        // check. Fetching them one at a time (the original behavior here)
        // serializes every one of their network round trips for no reason,
        // which on a first visit to a tab with several such files is slow
        // enough that a quick look (or a screenshot) right after the click
        // can catch the card still showing its unfilled placeholder HTML —
        // not actually broken, just not finished loading yet. Loading them
        // in parallel cuts that window down to roughly the slowest single
        // file instead of the sum of all of them. The inline data/init
        // scripts (reportDataScript, data-ajax-rerun) keep their real
        // "data script runs before the code that reads it" ordering
        // requirement (see this function's own doc comment above) by
        // running sequentially, same as before, only AFTER every external
        // file has finished.
        var externalScripts = all.filter(function (s) { return s.hasAttribute('src'); });
        var inlineScripts = all.filter(function (s) { return !s.hasAttribute('src'); });

        return Promise.all(externalScripts.map(runOneScript)).then(function () {
            return inlineScripts.reduce(function (chain, oldScript) {
                return chain.then(function () { return runOneScript(oldScript); });
            }, Promise.resolve());
        }).then(function () {
            document.dispatchEvent(new Event('DOMContentLoaded', { bubbles: true, cancelable: true }));
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
                if (!newMain) throw new Error('Unexpected response shape');

                return addMissingStylesheets(doc).then(function () { return doc; });
            })
            .then(function (doc) {
                swapPageStyles(doc);
                if (doc.title) document.title = doc.title;

                document.querySelector(mainSelector).replaceWith(doc.querySelector(mainSelector));
                if (pushState) {
                    window.history.pushState({ ajaxTab: true }, '', url);
                }
                window.scrollTo(0, 0);

                // Outside the fallback chain below: the swap already
                // happened, so a page-script error must not trigger a
                // second, full navigation.
                rerunPageScripts(doc).catch(function (err) { console.error(err); });
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
