/* =============================================================
   STYLED SELECT (shared — patient/medicine.php, patient/gis.php)
   ---------------------------------------------------------------
   A native <select>'s open list is drawn by the browser: it can't be
   restyled (bright blue highlight), and in narrow/emulated viewports
   it can run past the screen edge. Every <select data-styled-select>
   is kept in the page, hidden, as the real form field, and driven by a
   button + listbox drawn here. Choosing an option sets the select's
   value and fires a real 'change' event, so each page's existing
   handlers (form submits, chart swaps) run exactly as before. Without
   JS, the plain native select still works.

   The button copies the select's own class (filter-select /
   mini-select / sort-select), so it keeps that page's existing look,
   including its responsive widths. Styles: assets/css/styled-select.css.
   ============================================================= */
(function () {
    let openDropdown = null;
    let uid = 0;

    function enhanceSelect(select) {
        // Reuse the page's existing icon wrapper when there is one
        // (.filter-select-wrap / .mini-select-wrap already position a
        // chevron over the select); otherwise wrap it and add a chevron.
        let wrap = select.parentElement;
        if (!wrap.matches('.filter-select-wrap, .mini-select-wrap')) {
            wrap = document.createElement('span');
            select.parentNode.insertBefore(wrap, select);
            wrap.appendChild(select);
            const chevron = document.createElement('i');
            chevron.className = 'bi bi-chevron-down chevron';
            wrap.appendChild(chevron);
            wrap.classList.add('hc-select-own-wrap');
        }
        wrap.classList.add('hc-select');

        const listId = 'hcSelect' + (++uid);
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = select.className + ' hc-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-controls', listId);
        if (select.getAttribute('aria-label')) trigger.setAttribute('aria-label', select.getAttribute('aria-label'));
        const triggerLabel = document.createElement('span');
        triggerLabel.className = 'hc-select-label';
        trigger.appendChild(triggerLabel);

        const menu = document.createElement('ul');
        menu.className = 'hc-select-menu';
        menu.id = listId;
        menu.setAttribute('role', 'listbox');
        menu.tabIndex = -1;

        Array.from(select.options).forEach(function (opt, i) {
            const li = document.createElement('li');
            li.className = 'hc-select-option';
            li.id = listId + '-' + i;
            li.setAttribute('role', 'option');
            li.dataset.index = i;
            li.textContent = opt.text;
            menu.appendChild(li);
        });

        select.hidden = true;
        select.tabIndex = -1;
        wrap.insertBefore(trigger, select);
        wrap.appendChild(menu);

        let activeIndex = select.selectedIndex;

        function sync() {
            const current = select.options[select.selectedIndex];
            triggerLabel.textContent = current ? current.text : '';
            menu.querySelectorAll('.hc-select-option').forEach(function (li) {
                const selected = Number(li.dataset.index) === select.selectedIndex;
                li.classList.toggle('is-selected', selected);
                li.setAttribute('aria-selected', selected ? 'true' : 'false');
                // A page script can disable an option at any time (then
                // fire 'change') — mirror that so it can't be chosen.
                const opt = select.options[Number(li.dataset.index)];
                const disabled = !!(opt && opt.disabled);
                li.classList.toggle('is-disabled', disabled);
                li.setAttribute('aria-disabled', disabled ? 'true' : 'false');
            });
        }

        function setActive(index) {
            const items = menu.querySelectorAll('.hc-select-option');
            if (!items.length) return;
            activeIndex = Math.max(0, Math.min(items.length - 1, index));
            items.forEach(function (li, i) { li.classList.toggle('is-active', i === activeIndex); });
            menu.setAttribute('aria-activedescendant', items[activeIndex].id);
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        }

        // The visible area the list has to fit in: the viewport, narrowed
        // by any scrolling/clipping ancestor (e.g. a Bootstrap
        // .modal-dialog-scrollable's .modal-body) — otherwise a list near
        // the bottom of a scrolling modal is cut off by it.
        function visibleBounds() {
            let top = 0;
            let bottom = window.innerHeight;
            for (let el = wrap.parentElement; el && el !== document.body; el = el.parentElement) {
                const oy = getComputedStyle(el).overflowY;
                if (oy === 'auto' || oy === 'scroll' || oy === 'hidden') {
                    const r = el.getBoundingClientRect();
                    top = Math.max(top, r.top);
                    bottom = Math.min(bottom, r.bottom);
                }
            }
            return { top: top, bottom: bottom };
        }

        // Opens below the button, or above it when there isn't room; the
        // list's height is capped to the space actually left in the
        // visible area (scrolls past that), and it's right-aligned to the
        // button when left-aligning would run off the screen's edge.
        function place() {
            const rect = trigger.getBoundingClientRect();
            const bounds = visibleBounds();
            const below = bounds.bottom - rect.bottom - 12;
            const above = rect.top - bounds.top - 12;
            const openUp = below < 200 && above > below;
            wrap.classList.toggle('opens-up', openUp);
            menu.style.maxHeight = Math.max(140, Math.min(300, openUp ? above : below)) + 'px';

            wrap.classList.remove('align-right');
            if (menu.getBoundingClientRect().right > document.documentElement.clientWidth - 8) {
                wrap.classList.add('align-right');
            }
        }

        function open() {
            if (openDropdown && openDropdown !== api) openDropdown.close(false);
            wrap.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            sync(); // pick up any option enabled/disabled since last time
            place();
            setActive(select.selectedIndex);
            openDropdown = api;
        }

        function close(refocus) {
            wrap.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            menu.removeAttribute('aria-activedescendant');
            if (openDropdown === api) openDropdown = null;
            if (refocus) trigger.focus();
        }

        function choose(index) {
            if (select.options[index] && select.options[index].disabled) return; // greyed-out option
            const changed = select.selectedIndex !== index;
            select.selectedIndex = index;
            sync();
            close(true);
            if (changed) select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        trigger.addEventListener('click', function () {
            wrap.classList.contains('is-open') ? close(false) : open();
        });

        trigger.addEventListener('keydown', function (e) {
            const isOpen = wrap.classList.contains('is-open');
            if (!isOpen && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
                e.preventDefault();
                open();
                return;
            }
            if (!isOpen) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); setActive(activeIndex + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(activeIndex - 1); }
            else if (e.key === 'Home') { e.preventDefault(); setActive(0); }
            else if (e.key === 'End') { e.preventDefault(); setActive(select.options.length - 1); }
            else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); choose(activeIndex); }
            else if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation(); // close just this list, not an enclosing Bootstrap modal
                close(true);
            }
            else if (e.key === 'Tab') { close(false); }
        });

        menu.addEventListener('mousedown', function (e) { e.preventDefault(); }); // keep focus on the button
        menu.addEventListener('click', function (e) {
            const li = e.target.closest('.hc-select-option');
            if (li) choose(Number(li.dataset.index));
        });
        menu.addEventListener('mousemove', function (e) {
            const li = e.target.closest('.hc-select-option');
            if (li && Number(li.dataset.index) !== activeIndex) setActive(Number(li.dataset.index));
        });

        // A page script that sets select.value itself (and fires
        // 'change') still gets reflected on the button.
        select.addEventListener('change', sync);

        // form.reset() changes the value without a 'change' event —
        // re-sync once the reset has applied.
        if (select.form) {
            select.form.addEventListener('reset', function () { setTimeout(sync, 0); });
        }

        // Validation state set on the (hidden) select by a page script —
        // .is-invalid — is mirrored onto the visible button.
        function syncInvalid() {
            trigger.classList.toggle('is-invalid', select.classList.contains('is-invalid'));
        }
        new MutationObserver(syncInvalid).observe(select, { attributes: true, attributeFilter: ['class'] });
        syncInvalid();

        const api = { wrap: wrap, close: close, place: place };
        select.dataset.hcEnhanced = '1';
        sync();
    }

    // For selects added after page load (e.g. a cloned repeat-row
    // template): HCStyledSelect.enhance(container) styles any
    // select[data-styled-select] inside it not yet enhanced.
    window.HCStyledSelect = {
        enhance: function (root) {
            (root || document).querySelectorAll('select[data-styled-select]:not([data-hc-enhanced])').forEach(enhanceSelect);
        },
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.HCStyledSelect.enhance(document);

        document.addEventListener('click', function (e) {
            if (openDropdown && !openDropdown.wrap.contains(e.target)) openDropdown.close(false);
        });
        window.addEventListener('resize', function () {
            if (openDropdown) openDropdown.place();
        });
    });
})();
