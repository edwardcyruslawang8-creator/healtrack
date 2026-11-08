/* =============================================================
   PRESCRIPTION QUEUE (pharmacist/prescription_queue.php)
   ---------------------------------------------------------------
   "View Details" opens #rxqDetailModal, populated purely from the
   clicked button's own data-* attributes (set server-side from
   $orders — no extra network request). Dispensing only happens
   from inside that modal now — its own Dispense button posts to
   api/dispense-pharmacy-order.php, then reloads so the row moves
   onto the "Dispensed" tab and the sidebar/topbar badge counts
   drop, same "re-render from fresh Firestore data" pattern used
   everywhere else in this app.

   Dispensing also decrements real medicines/{id} inventory now —
   the inventory item dropdown (#rxqDispenseItemSelect) is built
   here from window.PRESCRIPTION_QUEUE_DATA (real, live stock,
   handed off in prescription_queue.php), pre-selected from the
   order's server-computed data-suggested-item-id (a STRICT match on
   name + dosage form + strength — hc_find_inventory_match() in
   includes/shared/inventory_match.php). "Not in inventory" stays a
   real, valid choice — this order's item isn't guaranteed to match
   a tracked inventory record, and dispensing shouldn't be blocked
   when it doesn't. Quantity is required only when a real item is
   selected; the shown "in stock" count is a convenience — the
   authoritative check happens server-side in
   api/dispense-pharmacy-order.php, same principle as every other
   client-side check in this app.
   ============================================================= */

/** "500 Tablets", "1 Bottle" — same rule as includes/firestore/medicines.php's hc_qty_unit(). */
function qtyUnit(quantity, unit) {
    const n = Number(quantity) || 0;
    const label = n === 1 || !unit || /s$/i.test(unit) ? unit : (/(x|ch|sh)$/i.test(unit) ? unit + 'es' : unit + 's');
    return n.toLocaleString() + ' ' + label;
}

document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('rxqDetailModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;
    const modal = new bootstrap.Modal(modalEl);
    const dispenseBtn = document.getElementById('rxqDetailDispenseBtn');
    const dispenseFormWrap = document.getElementById('rxqDispenseFormWrap');
    const itemSelect = document.getElementById('rxqDispenseItemSelect');
    const stockNote = document.getElementById('rxqDispenseStockNote');
    const qtyInput = document.getElementById('rxqDispenseQtyInput');
    const inventoryData = window.PRESCRIPTION_QUEUE_DATA || { medicines: [], vaccines: [] };

    function currentInventoryList(typeLabel) {
        return typeLabel === 'Vaccine' ? inventoryData.vaccines : inventoryData.medicines;
    }

    // ---------------------------------------------------------------
    // Searchable inventory combo (#rxqItemCombo). The hidden
    // #rxqDispenseItemSelect stays the single source of truth — the
    // combo only writes its value and fires "change" — so the stock
    // note and dispenseOrder() below work unchanged. Items are sorted
    // A–Z; items with no usable stock are shown but can't be picked.
    // ---------------------------------------------------------------
    const combo = document.getElementById('rxqItemCombo');
    const comboToggle = document.getElementById('rxqItemComboToggle');
    const comboLabel = combo.querySelector('.rxq-combo-label');
    const comboPanel = combo.querySelector('.rxq-combo-panel');
    const comboSearch = document.getElementById('rxqItemComboSearch');
    const comboList = document.getElementById('rxqItemComboList');
    const NONE_LABEL = 'Not in inventory — don’t adjust stock';
    let comboItems = [];      // [{ id, name, stock, unit }]
    let activeIndex = -1;     // keyboard highlight within the visible <li>s

    function syncComboLabel() {
        const selected = itemSelect.options[itemSelect.selectedIndex];
        if (!selected || selected.value === '') {
            comboLabel.textContent = NONE_LABEL;
            comboLabel.classList.add('rxq-combo-placeholder');
        } else {
            comboLabel.textContent = selected.dataset.name + ' (' + qtyUnit(selected.dataset.stock, selected.dataset.unit) + ' in stock)';
            comboLabel.classList.remove('rxq-combo-placeholder');
        }
    }

    function chooseItem(id) {
        itemSelect.value = id;
        itemSelect.dispatchEvent(new Event('change'));
        syncComboLabel();
        closeCombo();
        comboToggle.focus();
    }

    function renderComboList() {
        const term = comboSearch.value.trim().toLowerCase();
        comboList.innerHTML = '';
        activeIndex = -1;

        const rows = [{ id: '', name: NONE_LABEL, none: true }].concat(
            comboItems.filter(function (i) { return term === '' || i.name.toLowerCase().indexOf(term) !== -1; })
        );

        rows.forEach(function (row) {
            const li = document.createElement('li');
            li.className = 'rxq-combo-option';
            li.setAttribute('role', 'option');
            li.dataset.id = row.id;
            li.id = 'rxqComboOpt-' + (row.id || 'none');
            const selected = itemSelect.value === row.id;
            li.setAttribute('aria-selected', selected ? 'true' : 'false');
            if (selected) li.classList.add('is-selected');

            const name = document.createElement('span');
            name.className = 'rxq-combo-name';
            name.textContent = row.name;
            li.appendChild(name);

            if (row.none) {
                li.classList.add('rxq-combo-option-none');
            } else {
                const stock = document.createElement('span');
                stock.className = 'rxq-combo-stock';
                if (row.stock > 0) {
                    stock.textContent = qtyUnit(row.stock, row.unit);
                } else {
                    stock.textContent = 'Out of stock';
                    li.classList.add('is-disabled');
                    li.setAttribute('aria-disabled', 'true');
                }
                li.appendChild(stock);
            }
            comboList.appendChild(li);
        });

        if (rows.length === 1 && term !== '') {
            const empty = document.createElement('li');
            empty.className = 'rxq-combo-empty';
            empty.textContent = 'No inventory items match “' + comboSearch.value.trim() + '”.';
            comboList.appendChild(empty);
        }
    }

    function selectableOptions() {
        return Array.prototype.slice.call(comboList.querySelectorAll('.rxq-combo-option:not(.is-disabled)'));
    }

    /** Scrolls only the list (never the modal/page) so $li is fully visible. */
    function scrollOptionIntoList(li, center) {
        const top = li.offsetTop;
        const bottom = top + li.offsetHeight;
        if (center) {
            comboList.scrollTop = Math.max(0, top - (comboList.clientHeight - li.offsetHeight) / 2);
        } else if (top < comboList.scrollTop) {
            comboList.scrollTop = top;
        } else if (bottom > comboList.scrollTop + comboList.clientHeight) {
            comboList.scrollTop = bottom - comboList.clientHeight;
        }
    }

    function setActive(index) {
        const opts = selectableOptions();
        opts.forEach(function (o) { o.classList.remove('is-active'); });
        if (opts.length === 0) { activeIndex = -1; comboSearch.removeAttribute('aria-activedescendant'); return; }
        activeIndex = (index + opts.length) % opts.length;
        opts[activeIndex].classList.add('is-active');
        scrollOptionIntoList(opts[activeIndex], false);
        comboSearch.setAttribute('aria-activedescendant', opts[activeIndex].id);
    }

    // The panel sits IN the modal's own layout, directly under the field
    // and exactly its width (CSS) — not floating over it. So it can never
    // be clipped by, or spill outside, the modal, and never covers the
    // Quantity field (separate column) or the footer buttons (the modal
    // grows to fit instead). After opening, the modal's own scroll area
    // is nudged so the whole panel is on screen — that replaces "open
    // upward when there's no room below", which would have covered the
    // prescription details above. Works the same on any screen size.
    function revealPanel() {
        const pr = comboPanel.getBoundingClientRect();
        const overflowBottom = pr.bottom - (window.innerHeight - 12);
        if (overflowBottom > 0) {
            modalEl.scrollTop += overflowBottom; // .modal is Bootstrap's scroll container
        }
    }

    function openCombo() {
        comboPanel.hidden = false;
        comboToggle.setAttribute('aria-expanded', 'true');
        comboSearch.value = '';
        renderComboList();
        comboList.scrollTop = 0;
        const sel = comboList.querySelector('.is-selected');
        if (sel && !sel.classList.contains('rxq-combo-option-none')) scrollOptionIntoList(sel, true);
        revealPanel();
        comboSearch.focus({ preventScroll: true });
    }

    function closeCombo() {
        comboPanel.hidden = true;
        comboToggle.setAttribute('aria-expanded', 'false');
    }

    comboToggle.addEventListener('click', function () {
        if (comboPanel.hidden) openCombo(); else closeCombo();
    });
    comboSearch.addEventListener('input', function () {
        renderComboList();
        if (comboSearch.value.trim() !== '') setActive(0);
    });
    comboSearch.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive(activeIndex + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(activeIndex - 1); }
        else if (e.key === 'Enter') {
            e.preventDefault();
            const opts = selectableOptions();
            if (activeIndex >= 0 && opts[activeIndex]) chooseItem(opts[activeIndex].dataset.id);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation(); // close the list, not the whole modal
            closeCombo();
            comboToggle.focus();
        }
    });
    comboList.addEventListener('click', function (e) {
        const li = e.target.closest('.rxq-combo-option');
        if (li && !li.classList.contains('is-disabled')) chooseItem(li.dataset.id);
    });
    document.addEventListener('click', function (e) {
        if (!comboPanel.hidden && !combo.contains(e.target)) closeCombo();
    });
    modalEl.addEventListener('hidden.bs.modal', closeCombo);

    // Automatic-match state for the order currently open — drives the
    // helper text under the field. The match itself is computed
    // server-side (hc_find_inventory_match(): name + form + strength,
    // strict), handed over as data-suggested-item-id/-matched-item-name.
    let matchState = { id: '', name: '', status: 'none' }; // status: matched | unavailable | none

    function populateItemSelect(typeLabel, matchedId, matchedName) {
        const items = currentInventoryList(typeLabel).slice().sort(function (a, b) {
            return a.name.localeCompare(b.name, undefined, { sensitivity: 'base' });
        });
        itemSelect.innerHTML = '';

        const noneOpt = document.createElement('option');
        noneOpt.value = '';
        noneOpt.textContent = NONE_LABEL;
        itemSelect.appendChild(noneOpt);

        items.forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.name;
            opt.dataset.name = item.name;
            opt.dataset.stock = item.currentStock;
            opt.dataset.unit = item.unit;
            if (!(Number(item.currentStock) > 0)) opt.disabled = true;
            itemSelect.appendChild(opt);
        });

        comboItems = items.map(function (item) {
            return { id: item.id, name: item.name, stock: Number(item.currentStock) || 0, unit: item.unit };
        });

        // Auto-select the confident match when it can actually be
        // dispensed; an expired/out-of-stock match (absent from this list
        // or stock 0) stays unselected and is explained in the note.
        const matched = matchedId ? comboItems.find(function (i) { return i.id === matchedId; }) : null;
        if (matched && matched.stock > 0) {
            matchState = { id: matchedId, name: matched.name, status: 'matched' };
            itemSelect.value = matchedId;
        } else {
            matchState = { id: matchedId || '', name: matchedName || '', status: matchedId ? 'unavailable' : 'none' };
            itemSelect.value = '';
        }
        updateStockNote();
        syncComboLabel();
        closeCombo();
    }

    const qtyLabel = document.querySelector('label[for="rxqDispenseQtyInput"]');

    function updateStockNote() {
        const selected = itemSelect.options[itemSelect.selectedIndex];
        if (!selected || selected.value === '') {
            if (matchState.status === 'unavailable') {
                stockNote.textContent = 'Matched “' + matchState.name + '” in inventory, but it has no usable (non-expired) stock. Stock will not be adjusted for this dispense.';
            } else if (matchState.status === 'none') {
                stockNote.textContent = 'No matching inventory item found. Stock will not be adjusted for this dispense — search the list to pick one manually.';
            } else {
                stockNote.textContent = 'Stock will not be adjusted for this dispense.';
            }
            qtyInput.required = false;
            if (qtyLabel) qtyLabel.textContent = 'Quantity';
            qtyInput.removeAttribute('max');
        } else {
            const unitPluralLabel = qtyUnit(2, selected.dataset.unit).replace(/^2\s+/, '');
            const auto = matchState.status === 'matched' && selected.value === matchState.id;
            stockNote.textContent = (auto ? 'Automatically matched to inventory. ' : '')
                + qtyUnit(selected.dataset.stock, selected.dataset.unit) + ' currently in stock. Quantity is in ' + unitPluralLabel.toLowerCase() + '.';
            qtyInput.required = true;
            qtyInput.max = selected.dataset.stock;
            if (qtyLabel) qtyLabel.textContent = 'Quantity (' + unitPluralLabel + ')';
        }
    }
    itemSelect.addEventListener('change', updateStockNote);

    function dispenseOrder(orderId, btn) {
        const selected = itemSelect.options[itemSelect.selectedIndex];
        const inventoryItemId = selected ? selected.value : '';
        let quantity = null;

        if (inventoryItemId !== '') {
            quantity = parseInt(qtyInput.value, 10);
            if (!Number.isInteger(quantity) || quantity < 1) {
                HCDialog.alert('Please enter a valid quantity dispensed.');
                return;
            }
            const stockAvailable = parseInt(selected.dataset.stock, 10);
            if (quantity > stockAvailable) {
                HCDialog.alert('Only ' + qtyUnit(stockAvailable, selected.dataset.unit) + ' in stock — cannot dispense ' + qtyUnit(quantity, selected.dataset.unit) + '.');
                return;
            }
        }

        const defaultHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Dispensing…';

        fetch('../api/dispense-pharmacy-order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ orderId: orderId, inventoryItemId: inventoryItemId, quantity: quantity }),
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.error || 'Something went wrong.');
                }
                window.location.reload();
            })
            .catch(function (err) {
                btn.disabled = false;
                btn.innerHTML = defaultHtml;
                HCDialog.alert(err.message || 'Something went wrong. Please try again.');
            });
    }

    document.querySelectorAll('.rxq-btn-view').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const d = btn.dataset;

            document.getElementById('rxqDetailPatientName').textContent = d.patientName;
            document.getElementById('rxqDetailType').textContent = d.typeLabel;
            document.getElementById('rxqDetailItemName').textContent = d.itemName;
            document.getElementById('rxqDetailDosage').textContent = d.dosage;
            // Prescribed quantity — hidden for vaccines, "Not specified"
            // for medicine orders made before the Quantity column existed.
            document.getElementById('rxqDetailQuantityWrap').style.display = d.typeLabel === 'Vaccine' ? 'none' : '';
            document.getElementById('rxqDetailQuantity').textContent = d.quantity || 'Not specified';
            document.getElementById('rxqDetailFrequency').textContent = d.frequency;
            document.getElementById('rxqDetailDuration').textContent = d.duration;
            document.getElementById('rxqDetailRoute').textContent = d.route;
            document.getElementById('rxqDetailPrescribedBy').textContent = d.prescribedBy;
            document.getElementById('rxqDetailFacility').textContent = d.facility;
            document.getElementById('rxqDetailDateTime').textContent = d.date + ' • ' + d.time;

            const statusBadge = document.getElementById('rxqDetailStatusBadge');
            statusBadge.textContent = d.statusLabel;
            statusBadge.style.background = d.statusBg;
            statusBadge.style.color = d.statusColor;

            const instructionsWrap = document.getElementById('rxqDetailInstructionsWrap');
            if (d.instructions) {
                instructionsWrap.style.display = '';
                document.getElementById('rxqDetailInstructions').textContent = d.instructions;
            } else {
                instructionsWrap.style.display = 'none';
            }

            const dispensedWrap = document.getElementById('rxqDetailDispensedWrap');
            const isPending = d.isPending === '1';
            if (!isPending && d.dispensedDate) {
                dispensedWrap.style.display = '';
                let dispensedText = 'By ' + d.dispensedBy + ' on ' + d.dispensedDate + ' • ' + d.dispensedTime;
                if (d.dispensedQuantity && d.dispensedItemName) {
                    dispensedText += ' — ' + d.dispensedQuantity + ' unit(s) of ' + d.dispensedItemName + ' deducted from inventory.';
                }
                document.getElementById('rxqDetailDispensed').textContent = dispensedText;
            } else {
                dispensedWrap.style.display = 'none';
            }

            if (isPending) {
                dispenseBtn.style.display = '';
                dispenseBtn.dataset.orderId = d.orderId;
                dispenseBtn.disabled = false;
                dispenseBtn.innerHTML = '<i class="bi bi-check-circle"></i> Dispense';

                dispenseFormWrap.style.display = '';
                qtyInput.value = d.quantity || '';
                populateItemSelect(d.typeLabel, d.suggestedItemId, d.matchedItemName);
            } else {
                dispenseBtn.style.display = 'none';
                dispenseFormWrap.style.display = 'none';
            }

            modal.show();
        });
    });

    dispenseBtn.addEventListener('click', async function () {
        const confirmed = await HCDialog.confirm('Mark this prescription as dispensed?');
        if (!confirmed) return;
        dispenseOrder(dispenseBtn.dataset.orderId, dispenseBtn);
    });
});
