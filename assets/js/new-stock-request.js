/* =============================================================
   NEW / EDIT STOCK REQUEST JS
   (pharmacist/stock_requests.php)
   ---------------------------------------------------------------
   Drives the "+ New Stock Request" / "Edit Request" modal
   (includes/pharmacist/new_stock_request_modal.php) — one shared form
   for both, same convention as
   assets/js/medicine_vaccine_inventory_pharmacist.js's Add/Edit
   modal. Each item row's name is a free-text <input> (NOT a locked
   <select>) for flexibility — a pharmacist can request anything, not
   only items that already exist in inventory — paired with a
   hand-built suggestion dropdown (attachItemCombo() below, one
   instance per row, built from window.NSR_ITEMS) for autocomplete
   convenience only; switching Request Type just repoints each row's
   dropdown at the other type's item list, it never clears or
   validates against it. Not a native <datalist> — this needs to show
   a Low Stock/Out of Stock/Expiring Soon/Expired badge next to each
   suggestion, which plain <option> text can't render.

   New: submitting POSTs {type, dateNeeded, items} to
   api/create-stock-request.php. Edit: each pending row's "Edit
   Request" menu item opens the SAME form pre-filled from that row's
   data-request-* attributes, with #nsrFormId set to the request's real
   doc id, and submitting POSTs to api/update-stock-request.php
   instead (docId included). Either way, on success the page reloads
   so the table/summary cards re-render from fresh Firestore data.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('newStockRequestModal');
    const form = document.getElementById('newStockRequestForm');
    if (!modalEl || !form || typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalEl);
    const errorBox = document.getElementById('newStockRequestError');
    const submitBtn = document.getElementById('nsrSubmitBtn');
    const submitBtnDefaultHtml = submitBtn.innerHTML;
    const submitLabel = document.getElementById('nsrSubmitLabel');
    const modalTitle = document.getElementById('newStockRequestModalLabel');

    const idInput = document.getElementById('nsrFormId');
    const typeSelect = document.getElementById('nsrType');
    const dateNeededInput = document.getElementById('nsrDateNeeded');
    const rowsContainer = document.getElementById('nsrItemRows');
    const addItemBtn = document.getElementById('nsrAddItemBtn');
    const todayIso = dateNeededInput.min; // set server-side to today (see new_stock_request_modal.php)

    /** "500 Tablets", "1 Bottle" — same rule as includes/firestore/medicines.php's hc_qty_unit() (and assets/js/prescription-queue.js's own copy, a separate page/script). */
    function qtyUnit(quantity, unit) {
        const n = Number(quantity) || 0;
        const label = n === 1 || !unit || /s$/i.test(unit) ? unit : (/(x|ch|sh)$/i.test(unit) ? unit + 'es' : unit + 's');
        return n.toLocaleString() + ' ' + label;
    }

    function itemsForType(type) {
        return (window.NSR_ITEMS || {})[type === 'vaccine' ? 'vaccine' : 'medicine'] || [];
    }

    // Real stock badge for one suggestion row — same priority order as
    // hc_sort_inventory_by_restock_priority() (near-expired/expired,
    // then out of stock, then low stock), so the badge shown always
    // matches why that item sorted where it did. null for a normal
    // item — its real stock count shows instead (see renderComboList).
    function comboBadgeFor(item) {
        if (item.expiryStatus === 'expired') return { label: 'Expired', tone: 'red' };
        if (item.expiryStatus === 'soon') return { label: 'Expiring Soon', tone: 'amber' };
        if (item.status === 'unavailable') return { label: 'Out of Stock', tone: 'red' };
        if (item.status === 'low') return { label: 'Low Stock', tone: 'amber' };
        return null;
    }

    // Every open combo panel's own close() — one shared document-level
    // scroll listener (below) closes whichever are currently open rather
    // than each attachItemCombo() instance adding its own listener, which
    // would otherwise pile up one per item row for the life of the page.
    // position: fixed doesn't follow an ancestor's scroll (the modal body,
    // if the form is tall enough to scroll), so a still-open panel would
    // otherwise end up pointing at the wrong spot.
    const openComboClosers = [];
    document.addEventListener('scroll', function (e) {
        // Scrolling the suggestion list itself (.nsr-combo-list, when
        // there are more matches than fit) must NOT close it — only an
        // ANCESTOR scrolling (the modal body) invalidates the panel's
        // position.
        if (e.target && e.target.classList && e.target.classList.contains('nsr-combo-list')) return;
        openComboClosers.slice().forEach(function (close) { close(); });
    }, true);

    // One independent suggestion-dropdown instance per item row (unlike
    // prescription-queue.js's single #rxqItemCombo, this modal can have
    // several rows at once) — built around the row's own free-text
    // <input> rather than a locked <select>, since typing a name that
    // matches nothing is still a valid request (see this file's header
    // comment). A real <datalist> can't render anything but plain
    // option text, so this hand-built panel is what actually shows each
    // suggestion's Low Stock/Out of Stock/Expiring Soon/Expired badge.
    function attachItemCombo(input) {
        // input is a freshly document.createElement()'d node here, not yet
        // attached anywhere (buildNameInput() calls this before the row
        // itself is appended to the DOM) — so this builds the wrapper
        // around it directly rather than via insertBefore(), and the
        // caller appends the returned .wrap (not the bare input) to the
        // row; .nsr-item-name is still found the same way either way
        // (row.querySelector('.nsr-item-name')), it's just nested one
        // level deeper now.
        const wrap = document.createElement('div');
        wrap.className = 'nsr-combo';
        wrap.appendChild(input);

        const panel = document.createElement('div');
        panel.className = 'nsr-combo-panel';
        panel.hidden = true;
        const list = document.createElement('ul');
        list.className = 'nsr-combo-list';
        list.setAttribute('role', 'listbox');
        panel.appendChild(list);
        wrap.appendChild(panel);

        let activeIndex = -1;
        let currentType = typeSelect.value;

        function renderList() {
            const term = input.value.trim().toLowerCase();
            const items = itemsForType(currentType).filter(function (i) {
                return term === '' || i.name.toLowerCase().indexOf(term) !== -1;
            });
            list.innerHTML = '';
            activeIndex = -1;

            items.forEach(function (item) {
                const li = document.createElement('li');
                li.className = 'nsr-combo-option';
                li.setAttribute('role', 'option');
                li.dataset.name = item.name;

                const name = document.createElement('span');
                name.className = 'nsr-combo-name';
                name.textContent = item.name;
                li.appendChild(name);

                const badge = comboBadgeFor(item);
                if (badge) {
                    const b = document.createElement('span');
                    b.className = 'nsr-combo-badge nsr-combo-badge-' + badge.tone;
                    b.textContent = badge.label;
                    li.appendChild(b);
                } else {
                    const stock = document.createElement('span');
                    stock.className = 'nsr-combo-stock';
                    stock.textContent = qtyUnit(item.currentStock, item.unit) + ' in stock';
                    li.appendChild(stock);
                }

                li.addEventListener('mousedown', function (e) {
                    // mousedown (not click) — fires before the input's own
                    // blur, so choosing a suggestion never races the panel
                    // closing itself first.
                    e.preventDefault();
                    input.value = item.name;
                    input.dispatchEvent(new Event('change'));
                    closePanel();
                });
                list.appendChild(li);
            });

            if (items.length === 0) {
                const empty = document.createElement('li');
                empty.className = 'nsr-combo-empty';
                empty.textContent = term === ''
                    ? 'No items in inventory yet — type a name to request something new.'
                    : 'No inventory matches — this will be requested as a new item.';
                list.appendChild(empty);
            }
        }

        function setActive(index) {
            const opts = list.querySelectorAll('.nsr-combo-option');
            opts.forEach(function (o) { o.classList.remove('is-active'); });
            if (opts.length === 0) { activeIndex = -1; return; }
            activeIndex = (index + opts.length) % opts.length;
            opts[activeIndex].classList.add('is-active');
            opts[activeIndex].scrollIntoView({ block: 'nearest' });
        }

        // position: fixed, computed from the input's own real on-screen
        // rect — same fix (and reasoning) as assets/js/styled-select.js's
        // own place(): Bootstrap's .modal-content clips any descendant
        // with position: absolute that extends past the modal's rounded-
        // corner box, so a plain "under the input" absolute panel got
        // silently cut off after only 1-2 rows. position: fixed escapes
        // that clipping entirely since it's no longer a descendant box
        // for overflow purposes, immune to ANY ancestor's overflow:hidden.
        function placePanel() {
            const rect = input.getBoundingClientRect();
            panel.style.left = rect.left + 'px';
            panel.style.top = (rect.bottom + 4) + 'px';
            panel.style.width = rect.width + 'px';
        }

        function openPanel() {
            renderList();
            panel.hidden = false;
            placePanel();
            if (openComboClosers.indexOf(closePanel) === -1) openComboClosers.push(closePanel);
        }
        function closePanel() {
            panel.hidden = true;
            const i = openComboClosers.indexOf(closePanel);
            if (i !== -1) openComboClosers.splice(i, 1);
        }

        input.addEventListener('focus', openPanel);
        input.addEventListener('input', function () { if (!panel.hidden) { renderList(); placePanel(); } else { openPanel(); } });
        input.addEventListener('blur', closePanel);
        input.addEventListener('keydown', function (e) {
            if (panel.hidden && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) { openPanel(); return; }
            if (e.key === 'ArrowDown') { e.preventDefault(); setActive(activeIndex + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(activeIndex - 1); }
            else if (e.key === 'Enter') {
                const opts = list.querySelectorAll('.nsr-combo-option');
                if (activeIndex >= 0 && opts[activeIndex]) {
                    e.preventDefault();
                    input.value = opts[activeIndex].dataset.name;
                    input.dispatchEvent(new Event('change'));
                    closePanel();
                }
            } else if (e.key === 'Escape') {
                closePanel();
            }
        });

        return {
            wrap: wrap,
            setType: function (type) { currentType = type; if (!panel.hidden) renderList(); },
        };
    }

    function buildNameInput(value) {
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control am-form-control nsr-item-name';
        input.placeholder = 'Item name — type anything, or pick a suggestion';
        input.autocomplete = 'off';
        input.required = true;
        if (value) input.value = value;
        input.nsrCombo = attachItemCombo(input);
        return input;
    }

    function updateRemoveButtons() {
        const rows = rowsContainer.querySelectorAll('.nsr-item-row');
        rows.forEach(function (row) {
            row.querySelector('.nsr-remove-item-btn').disabled = rows.length <= 1;
        });
    }

    // Units a line can be requested in — the matched inventory item's own
    // (window.NSR_ITEM_UNITS, new_stock_request_modal.php: Box first for
    // box-stocked items), or a general list for a name not in inventory.
    const GENERIC_UNITS = ['Box', 'Tablet', 'Capsule', 'Bottle', 'Inhaler', 'Vial', 'Ampule', 'Sachet', 'Dose', 'Piece'];

    function unitsForName(name) {
        const known = (window.NSR_ITEM_UNITS || {})[(name || '').trim().toLowerCase()];
        return known && known.length ? known : GENERIC_UNITS;
    }

    // selectedUnit = a saved line's own unit (Edit) — kept even if the
    // item's units have changed since; otherwise the item's first unit.
    function fillUnitSelect(select, name, selectedUnit) {
        const units = unitsForName(name).slice();
        if (selectedUnit && units.indexOf(selectedUnit) === -1) units.push(selectedUnit);
        select.innerHTML = '';
        units.forEach(function (unit) {
            const opt = document.createElement('option');
            opt.value = unit;
            opt.textContent = unit;
            select.appendChild(opt);
        });
        select.value = selectedUnit || units[0];
    }

    function addItemRow(selectedName, selectedQty, selectedUnit) {
        const row = document.createElement('div');
        row.className = 'nsr-item-row';

        const nameInput = buildNameInput(selectedName);
        row.appendChild(nameInput.nsrCombo.wrap);

        const qtyInput = document.createElement('input');
        qtyInput.type = 'number';
        qtyInput.className = 'form-control am-form-control nsr-item-qty';
        qtyInput.min = '1';
        qtyInput.step = '1';
        qtyInput.placeholder = 'Qty';
        qtyInput.required = true;
        if (selectedQty != null) qtyInput.value = selectedQty;
        row.appendChild(qtyInput);

        const unitSelect = document.createElement('select');
        unitSelect.className = 'form-select am-form-control nsr-item-unit';
        unitSelect.setAttribute('aria-label', 'Unit');
        fillUnitSelect(unitSelect, selectedName, selectedUnit);
        nameInput.addEventListener('change', function () { fillUnitSelect(unitSelect, nameInput.value, null); });
        row.appendChild(unitSelect);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'nsr-remove-item-btn';
        removeBtn.setAttribute('aria-label', 'Remove item');
        removeBtn.innerHTML = '<i class="bi bi-trash"></i>';
        removeBtn.addEventListener('click', function () {
            if (rowsContainer.querySelectorAll('.nsr-item-row').length <= 1) return;
            row.remove();
            updateRemoveButtons();
        });
        row.appendChild(removeBtn);

        rowsContainer.appendChild(row);
        updateRemoveButtons();
    }

    function resetRows(items) {
        rowsContainer.innerHTML = '';
        if (items && items.length > 0) {
            items.forEach(function (item) { addItemRow(item.name, item.qty, item.unit || null); });
        } else {
            addItemRow(null, null);
        }
    }

    typeSelect.addEventListener('change', function () {
        // Just repoint every existing row's dropdown at the other type's
        // item list (suggestions only) — whatever was already typed stays,
        // since free text was never validated against either list.
        rowsContainer.querySelectorAll('.nsr-item-name').forEach(function (input) {
            if (input.nsrCombo) input.nsrCombo.setType(typeSelect.value);
        });
    });

    addItemBtn.addEventListener('click', function () { addItemRow(null, null); });

    const openBtn = document.getElementById('btnNewStockRequest');
    if (openBtn) {
        openBtn.addEventListener('click', function () {
            form.reset();
            form.classList.remove('was-validated');
            errorBox.classList.add('d-none');
            idInput.value = '';
            modalTitle.textContent = 'New Stock Request';
            submitLabel.textContent = 'Submit Request';
            dateNeededInput.min = todayIso;
            resetRows(null);
            submitBtn.disabled = false;
            submitBtn.innerHTML = submitBtnDefaultHtml;
            modal.show();
        });
    }

    document.querySelectorAll('.srq-edit-request').forEach(function (link) {
        const row = link.closest('tr');
        if (!row) return;
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const d = row.dataset;

            form.reset();
            form.classList.remove('was-validated');
            errorBox.classList.add('d-none');
            idInput.value = d.requestId;
            modalTitle.textContent = 'Edit Stock Request';
            submitLabel.textContent = 'Save Changes';

            typeSelect.value = d.requestTypeRaw;
            // Already-overdue pending requests (real dateNeeded already in
            // the past) must stay editable for their OTHER fields without
            // the date picker rejecting their own unchanged value — so the
            // min here relaxes to the request's existing date when that's
            // earlier than today, instead of always requiring today+.
            dateNeededInput.min = d.requestDateNeededRaw < todayIso ? d.requestDateNeededRaw : todayIso;
            dateNeededInput.value = d.requestDateNeededRaw;

            let items = [];
            try { items = JSON.parse(d.requestItems || '[]'); } catch (err) { items = []; }
            resetRows(items);

            submitBtn.disabled = false;
            submitBtn.innerHTML = submitBtnDefaultHtml;
            modal.show();
        });
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const items = [];
        rowsContainer.querySelectorAll('.nsr-item-row').forEach(function (row) {
            const name = row.querySelector('.nsr-item-name').value;
            const qty = row.querySelector('.nsr-item-qty').value;
            const unit = row.querySelector('.nsr-item-unit').value;
            if (name && qty) items.push({ name: name, qty: qty, unit: unit });
        });

        if (!form.checkValidity() || items.length === 0) {
            form.classList.add('was-validated');
            if (items.length === 0) {
                errorBox.textContent = 'Please add at least one item with a name and quantity.';
                errorBox.classList.remove('d-none');
            }
            return;
        }

        const isEdit = idInput.value !== '';
        const payload = {
            type: typeSelect.value,
            dateNeeded: dateNeededInput.value,
            items: items,
        };
        if (isEdit) payload.docId = idInput.value;

        errorBox.classList.add('d-none');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + (isEdit ? 'Saving…' : 'Submitting…');

        fetch(isEdit ? '../api/update-stock-request.php' : '../api/create-stock-request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.error || 'Something went wrong. Please try again.');
                }
                window.location.reload();
            })
            .catch(function (err) {
                errorBox.textContent = err.message;
                errorBox.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtnDefaultHtml;
            });
    });
});
