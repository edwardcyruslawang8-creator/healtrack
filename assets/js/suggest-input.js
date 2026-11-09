/* =============================================================
   SUGGEST INPUT (shared) — styled replacement for <datalist>
   ---------------------------------------------------------------
   A native <datalist> is drawn by the browser: a huge, dark, unstyled
   list that covers the page and can't be restyled or sized. On a page
   that loads this script, every <input list="…"> — including ones
   created later (repeat rows) or whose list is switched by a script —
   is upgraded the first time it's focused: the `list` attribute is
   moved to data-suggest-list (so the browser's own popup never shows)
   and a styled, filterable suggestion panel is used instead.

   - Options are read live from the <datalist> each time, so a script
     that changes which list an input uses still works.
   - Filters by "contains" (case-insensitive) as you type; matching text
     is bolded; at most ~8 rows visible, the rest scroll.
   - Keyboard: ↓/↑ move, Enter picks, Esc closes (without closing an
     enclosing modal), Tab closes.
   - Free text is still allowed — picking is optional, exactly like a
     datalist.
   - Picking sets the value and fires real 'input' + 'change' events so
     existing page scripts react as before.
   - The panel is position:fixed under the field, so scrolling tables or
     modals can't clip it; it flips above when there's no room below.
   Styles: assets/css/suggest-input.css.
   ============================================================= */
(function () {
    let panel = null;
    let current = null;   // the input the panel is open for
    let items = [];       // currently shown option strings
    let active = -1;

    function ensurePanel() {
        if (panel) return panel;
        panel = document.createElement('ul');
        panel.className = 'hc-suggest-panel';
        panel.id = 'hcSuggestPanel';
        panel.setAttribute('role', 'listbox');
        panel.hidden = true;
        // Keep focus in the input while clicking an option.
        panel.addEventListener('mousedown', function (e) { e.preventDefault(); });
        panel.addEventListener('click', function (e) {
            const li = e.target.closest('.hc-suggest-option');
            if (li) pick(Number(li.dataset.index));
        });
        panel.addEventListener('mousemove', function (e) {
            const li = e.target.closest('.hc-suggest-option');
            if (li) setActive(Number(li.dataset.index), false);
        });
        document.body.appendChild(panel);
        return panel;
    }

    function upgrade(input) {
        const listId = input.getAttribute('list');
        if (listId) {
            input.dataset.suggestList = listId;
            input.removeAttribute('list');
        }
        if (input.dataset.suggestReady) return;
        input.dataset.suggestReady = '1';
        input.setAttribute('autocomplete', 'off');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', 'hcSuggestPanel');
        input.setAttribute('aria-expanded', 'false');

        input.addEventListener('input', function () { open(input); });
        input.addEventListener('click', function () { if (current !== input) open(input); });
        input.addEventListener('keydown', onKeydown);
        input.addEventListener('blur', function () { if (current === input) close(); });
    }

    function optionsFor(input) {
        const list = document.getElementById(input.dataset.suggestList || '');
        if (!list) return [];
        const seen = new Set();
        return Array.from(list.options)
            .map(function (o) { return (o.value || o.textContent || '').trim(); })
            .filter(function (v) { if (!v || seen.has(v)) return false; seen.add(v); return true; });
    }

    function render(input) {
        const term = input.value.trim().toLowerCase();
        const all = optionsFor(input);
        items = term === '' ? all : all.filter(function (v) { return v.toLowerCase().indexOf(term) !== -1; });
        // Starts-with matches first, then A–Z.
        items.sort(function (a, b) {
            const as = a.toLowerCase().indexOf(term) === 0 ? 0 : 1;
            const bs = b.toLowerCase().indexOf(term) === 0 ? 0 : 1;
            return as - bs || a.localeCompare(b, undefined, { sensitivity: 'base' });
        });

        panel.textContent = '';
        items.forEach(function (v, i) {
            const li = document.createElement('li');
            li.className = 'hc-suggest-option';
            li.id = 'hcSuggestOpt' + i;
            li.dataset.index = i;
            li.setAttribute('role', 'option');
            const at = term === '' ? -1 : v.toLowerCase().indexOf(term);
            if (at === -1) {
                li.textContent = v;
            } else {
                li.appendChild(document.createTextNode(v.slice(0, at)));
                const b = document.createElement('strong');
                b.textContent = v.slice(at, at + term.length);
                li.appendChild(b);
                li.appendChild(document.createTextNode(v.slice(at + term.length)));
            }
            panel.appendChild(li);
        });
        active = -1;
        return items.length > 0;
    }

    function place(input) {
        const r = input.getBoundingClientRect();
        const vh = window.innerHeight;
        const below = vh - r.bottom - 12;
        const above = r.top - 12;
        const openUp = below < 180 && above > below;
        const maxH = Math.max(120, Math.min(300, openUp ? above : below));
        panel.style.maxHeight = maxH + 'px';
        panel.style.minWidth = Math.max(r.width, 220) + 'px';
        panel.style.left = Math.min(r.left, document.documentElement.clientWidth - Math.max(r.width, 220) - 8) + 'px';
        if (openUp) {
            panel.style.top = 'auto';
            panel.style.bottom = (vh - r.top + 4) + 'px';
        } else {
            panel.style.bottom = 'auto';
            panel.style.top = (r.bottom + 4) + 'px';
        }
    }

    function open(input) {
        ensurePanel();
        current = input;
        if (!render(input)) { hide(); return; }
        panel.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        place(input);
        panel.scrollTop = 0;
    }

    function hide() {
        if (panel) panel.hidden = true;
        if (current) {
            current.setAttribute('aria-expanded', 'false');
            current.removeAttribute('aria-activedescendant');
        }
    }

    function close() {
        hide();
        current = null;
    }

    function setActive(i, scroll) {
        const lis = panel.querySelectorAll('.hc-suggest-option');
        if (!lis.length) return;
        active = (i + lis.length) % lis.length;
        lis.forEach(function (li, idx) { li.classList.toggle('is-active', idx === active); });
        if (current) current.setAttribute('aria-activedescendant', lis[active].id);
        if (scroll !== false) lis[active].scrollIntoView({ block: 'nearest' });
    }

    function pick(i) {
        if (!current || !items[i]) return;
        const input = current;
        input.value = items[i];
        close();
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        close(); // the 'input' event above re-opens it
    }

    function onKeydown(e) {
        const input = e.currentTarget;
        const isOpen = panel && !panel.hidden && current === input;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (!isOpen) { open(input); if (panel && !panel.hidden) setActive(0); return; }
            setActive(active + 1);
        } else if (e.key === 'ArrowUp') {
            if (!isOpen) return;
            e.preventDefault();
            setActive(active - 1);
        } else if (e.key === 'Enter') {
            if (isOpen && active >= 0) { e.preventDefault(); pick(active); }
        } else if (e.key === 'Escape') {
            if (isOpen) { e.preventDefault(); e.stopPropagation(); close(); }
        } else if (e.key === 'Tab') {
            close();
        }
    }

    // Upgrade on focus (covers inputs created or re-pointed later).
    document.addEventListener('focusin', function (e) {
        const t = e.target;
        if (t instanceof HTMLInputElement && (t.hasAttribute('list') || t.dataset.suggestList)) upgrade(t);
    });
    // Remove the native popup up front for inputs already on the page.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('input[list]').forEach(upgrade);
    });
    // Keep it attached to its field while the page/table/modal scrolls.
    window.addEventListener('scroll', function (e) {
        if (current && panel && !panel.hidden && e.target !== panel) place(current);
    }, true);
    window.addEventListener('resize', function () {
        if (current && panel && !panel.hidden) place(current);
    });
})();
