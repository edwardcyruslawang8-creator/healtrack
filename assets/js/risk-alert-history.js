/* =============================================================
   ALERT HISTORY MODAL (#riskHistoryModal)
   ---------------------------------------------------------------
   Markup: includes/shared/risk_alert_history_modal.php. Data:
   api/get-risk-alert-history.php (server-side filters + pagination).
   Loads page 1 each time the modal opens, reloads on any filter
   change. A Resolved row shows its automatic resolution reason under
   the status pill.

   Delegated on `document` behind a one-time flag — ajax-tab-nav.js
   re-injects page scripts on every tab revisit.
   ============================================================= */
(function () {
    if (window.__hcRiskHistoryBound) return;
    window.__hcRiskHistoryBound = true;

    var state = { page: 1, seq: 0, group: '' };

    // The tab each View History button lives on (data-risk-group, set by
    // includes/shared/_risk_alert_card.php) -> modal title + scope.
    var GROUP_LABELS = {
        disease: 'Disease & Demand Forecast',
        shortage: 'Medicine & Vaccine Shortage',
        replenishment: 'Inventory Replenishment',
        barangay: 'Consultation Trends by Barangay'
    };
    // Inventory tabs have no barangay — hide that filter and column.
    var NO_BARANGAY = { shortage: true, replenishment: true };

    function applyGroup(modal, group) {
        state.group = group || '';
        state.applying = true; // our own reset must not trigger a second load
        modal.querySelector('.rah-filters').reset();
        state.applying = false;
        modal.querySelector('.rah-tab-name').textContent = GROUP_LABELS[state.group] ? ' — ' + GROUP_LABELS[state.group] : '';
        modal.classList.toggle('rah-no-barangay', !!NO_BARANGAY[state.group]);

        // Only this tab's risk types in the Risk Type filter.
        var typeSel = modal.querySelector('select[name="riskType"]');
        var shown = 0;
        typeSel.querySelectorAll('option[data-group]').forEach(function (o) {
            var match = !state.group || o.dataset.group === state.group;
            o.hidden = !match;
            o.disabled = !match;
            if (match) shown++;
        });
        // A single-type tab needs no Risk Type filter at all.
        typeSel.closest('label').hidden = shown <= 1;
        syncAllDropdowns(modal);
    }

    /* ---------------------------------------------------------------
       Custom filter dropdowns. Native <select> popups can't be styled
       (bright-blue OS highlight), so each filter <select> gets a button
       + role="listbox" on top of it. The <select> stays the source of
       truth: FormData, form reset and the 'change' listener below keep
       working unchanged. Keyboard: Enter/Space/ArrowDown opens;
       Up/Down/Home/End move; Enter/Space picks; Esc/Tab closes.
       --------------------------------------------------------------- */
    var openDD = null;

    function ddOptions(sel) {
        return Array.prototype.filter.call(sel.options, function (o) { return !o.hidden && !o.disabled; });
    }

    function syncDropdown(sel) {
        var wrap = sel.closest('.rah-dd');
        if (!wrap) return;
        var opt = sel.options[sel.selectedIndex];
        wrap.querySelector('.rah-dd-value').textContent = opt ? opt.textContent : '';
    }

    function syncAllDropdowns(modal) {
        modal.querySelectorAll('.rah-dd select').forEach(syncDropdown);
    }

    function closeDropdown(focusButton) {
        if (!openDD) return;
        var dd = openDD;
        openDD = null;
        dd.list.hidden = true;
        dd.list.innerHTML = '';
        dd.btn.setAttribute('aria-expanded', 'false');
        dd.wrap.classList.remove('open');
        if (focusButton) dd.btn.focus();
    }

    function setActive(dd, idx) {
        var items = dd.list.querySelectorAll('.rah-dd-opt');
        if (!items.length) return;
        idx = Math.max(0, Math.min(items.length - 1, idx));
        items.forEach(function (li, i) { li.classList.toggle('active', i === idx); });
        dd.active = idx;
        dd.list.setAttribute('aria-activedescendant', items[idx].id);
        items[idx].scrollIntoView({ block: 'nearest' });
    }

    function choose(dd, idx) {
        var opts = ddOptions(dd.sel);
        if (!opts[idx]) return;
        var changed = dd.sel.value !== opts[idx].value;
        dd.sel.value = opts[idx].value;
        syncDropdown(dd.sel);
        closeDropdown(true);
        if (changed) dd.sel.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function openDropdown(dd) {
        closeDropdown(false);
        var opts = ddOptions(dd.sel);
        var selectedIdx = 0;
        opts.forEach(function (o, i) {
            var li = el('li', 'rah-dd-opt', o.textContent);
            li.id = dd.list.id + '-' + i;
            li.setAttribute('role', 'option');
            var isSel = o.value === dd.sel.value;
            li.setAttribute('aria-selected', isSel ? 'true' : 'false');
            if (isSel) { selectedIdx = i; li.classList.add('selected'); }
            li.addEventListener('mousedown', function (ev) { ev.preventDefault(); }); // keep focus in the list
            li.addEventListener('click', function () { choose(dd, i); });
            li.addEventListener('mousemove', function () { if (dd.active !== i) setActive(dd, i); });
            dd.list.appendChild(li);
        });
        dd.list.hidden = false;
        dd.btn.setAttribute('aria-expanded', 'true');
        dd.wrap.classList.add('open');
        openDD = dd;
        dd.list.focus();
        setActive(dd, selectedIdx);
    }

    function enhanceSelect(sel) {
        if (sel.closest('.rah-dd')) return;
        var labelSpan = sel.closest('label').querySelector('span');
        labelSpan.id = labelSpan.id || 'rah-lbl-' + sel.name;

        var wrap = el('div', 'rah-dd');
        sel.parentNode.insertBefore(wrap, sel);
        wrap.appendChild(sel);
        sel.classList.add('rah-native');
        sel.tabIndex = -1;
        sel.setAttribute('aria-hidden', 'true');

        var btn = el('button', 'rah-dd-btn');
        btn.type = 'button';
        btn.setAttribute('aria-haspopup', 'listbox');
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-labelledby', labelSpan.id + ' ' + 'rah-val-' + sel.name);
        var val = el('span', 'rah-dd-value');
        val.id = 'rah-val-' + sel.name;
        btn.appendChild(val);
        var chev = el('i', 'bi bi-chevron-down');
        chev.setAttribute('aria-hidden', 'true');
        btn.appendChild(chev);

        var list = el('ul', 'rah-dd-list');
        list.id = 'rah-list-' + sel.name;
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-labelledby', labelSpan.id);
        list.tabIndex = -1;
        list.hidden = true;
        btn.setAttribute('aria-controls', list.id);

        wrap.appendChild(btn);
        wrap.appendChild(list);

        var dd = { sel: sel, wrap: wrap, btn: btn, list: list, active: 0 };

        // The <label> wrapper would forward clicks to the hidden <select> —
        // send them to the visible button instead.
        wrap.addEventListener('click', function (ev) { ev.preventDefault(); });
        sel.closest('label').addEventListener('click', function (ev) {
            if (!wrap.contains(ev.target)) { ev.preventDefault(); btn.focus(); }
        });
        btn.addEventListener('click', function () {
            if (openDD === dd) closeDropdown(true); else openDropdown(dd);
        });
        btn.addEventListener('keydown', function (ev) {
            if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') { ev.preventDefault(); openDropdown(dd); }
        });
        list.addEventListener('keydown', function (ev) {
            var n = list.querySelectorAll('.rah-dd-opt').length;
            switch (ev.key) {
                case 'ArrowDown': ev.preventDefault(); setActive(dd, dd.active + 1); break;
                case 'ArrowUp': ev.preventDefault(); setActive(dd, dd.active - 1); break;
                case 'Home': ev.preventDefault(); setActive(dd, 0); break;
                case 'End': ev.preventDefault(); setActive(dd, n - 1); break;
                case 'Enter':
                case ' ': ev.preventDefault(); choose(dd, dd.active); break;
                case 'Escape': ev.preventDefault(); ev.stopPropagation(); closeDropdown(true); break; // don't close the modal
                case 'Tab': closeDropdown(false); break;
            }
        });
        list.addEventListener('blur', function () {
            setTimeout(function () { if (openDD === dd && !wrap.contains(document.activeElement)) closeDropdown(false); }, 0);
        });
        syncDropdown(sel);
    }

    document.addEventListener('mousedown', function (e) {
        if (openDD && !openDD.wrap.contains(e.target)) closeDropdown(false);
    });
    document.addEventListener('hide.bs.modal', function (e) {
        if (e.target.id === 'riskHistoryModal') closeDropdown(false);
    });

    function el(tag, cls, text) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text !== undefined && text !== null) e.textContent = text;
        return e;
    }

    function severityClass(s) {
        return s === 'CRITICAL' || s === 'ALERT' ? 'critical' : (s === 'ELEVATED' ? 'elevated' : 'watch');
    }

    function stateRow(body, text) {
        body.innerHTML = '';
        var tr = el('tr');
        var td = el('td', 'rah-state', text);
        // Span exactly the visible header cells — a wider colspan than the
        // real column count adds a phantom column (the right-side gap
        // seen when the Barangay column is hidden on inventory tabs).
        var table = body.closest('table');
        td.colSpan = Array.prototype.filter.call(table.querySelectorAll('thead th'), function (th) {
            return getComputedStyle(th).display !== 'none';
        }).length || 1;
        tr.appendChild(td);
        body.appendChild(tr);
    }

    function load(modal) {
        var body = modal.querySelector('.rah-body');
        var form = modal.querySelector('.rah-filters');
        var params = new URLSearchParams(new FormData(form));
        params.set('page', state.page);
        if (state.group) params.set('group', state.group);
        if (NO_BARANGAY[state.group]) params.delete('barangay');
        if (modal.dataset.facilityId) params.set('facility_id', modal.dataset.facilityId);

        var mySeq = ++state.seq;
        stateRow(body, 'Loading…');

        fetch(modal.dataset.endpoint + '?' + params.toString(), { credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return {}; }); })
            .then(function (data) {
                if (mySeq !== state.seq) return; // a newer request superseded this one
                if (!data.success) {
                    stateRow(body, data.error || 'Could not load alert history.');
                    renderPager(modal, { total: 0, page: 1, pages: 1 });
                    return;
                }
                render(body, data.rows);
                renderPager(modal, data);
            })
            .catch(function () {
                if (mySeq === state.seq) stateRow(body, 'Could not load alert history. Check your connection and try again.');
            });
    }

    function render(body, rows) {
        body.innerHTML = '';
        if (!rows.length) {
            stateRow(body, 'No alert history matches these filters.');
            return;
        }
        rows.forEach(function (r) {
            var tr = el('tr', 'rah-row');
            tr.appendChild(el('td', 'rah-col-barangay', r.barangay));

            var ind = el('td');
            ind.appendChild(el('span', 'rah-ind', r.riskIndicator));
            if (r.basis === 'forecast') ind.appendChild(el('span', 'pn-severity forecast', 'Forecast'));
            tr.appendChild(ind);

            var sev = el('td');
            sev.appendChild(el('span', 'pn-severity ' + severityClass(r.severity), r.severity));
            tr.appendChild(sev);

            tr.appendChild(el('td', 'rah-nowrap', r.detected));
            tr.appendChild(el('td', 'rah-nowrap', r.resolved));

            var resolved = r.status === 'RESOLVED';
            var st = el('td');
            st.appendChild(el('span', 'rah-status ' + (resolved ? 'resolved' : 'active'), resolved ? 'Resolved' : 'Active'));
            // Why it resolved — shows the system closed it from real data,
            // not that someone dismissed it.
            if (resolved && r.resolutionReason) {
                st.appendChild(el('span', 'rah-reason', 'Auto-resolved: ' + r.resolutionReason));
            }
            tr.appendChild(st);
            body.appendChild(tr);
        });
    }

    function renderPager(modal, d) {
        modal.querySelector('.rah-count').textContent = d.total + (d.total === 1 ? ' record' : ' records');
        modal.querySelector('.rah-page').textContent = 'Page ' + d.page + ' of ' + d.pages;
        modal.querySelector('.rah-prev').disabled = d.page <= 1;
        modal.querySelector('.rah-next').disabled = d.page >= d.pages;
        state.page = d.page;
    }

    document.addEventListener('show.bs.modal', function (e) {
        if (e.target.id !== 'riskHistoryModal') return;
        e.target.querySelectorAll('.rah-filters select').forEach(enhanceSelect);
        var trigger = e.relatedTarget && e.relatedTarget.closest ? e.relatedTarget.closest('[data-risk-group]') : null;
        applyGroup(e.target, trigger ? trigger.dataset.riskGroup : '');
        state.page = 1;
        load(e.target);
    });

    document.addEventListener('change', function (e) {
        var modal = e.target.closest('#riskHistoryModal');
        if (!modal || !e.target.closest('.rah-filters')) return;
        state.page = 1;
        load(modal);
    });

    document.addEventListener('reset', function (e) {
        var modal = e.target.closest('#riskHistoryModal');
        if (!modal || state.applying) return;
        setTimeout(function () { syncAllDropdowns(modal); state.page = 1; load(modal); }, 0); // after the form clears
    });

    document.addEventListener('submit', function (e) {
        if (e.target.closest('#riskHistoryModal')) e.preventDefault();
    });

    document.addEventListener('click', function (e) {
        var modal = e.target.closest('#riskHistoryModal');
        if (!modal) return;
        if (e.target.closest('.rah-prev')) { state.page = Math.max(1, state.page - 1); load(modal); return; }
        if (e.target.closest('.rah-next')) { state.page += 1; load(modal); return; }
    });
})();
