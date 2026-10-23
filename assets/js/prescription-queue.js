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
   order's server-computed data-suggested-item-id (a fuzzy name
   match — see includes/shared/inventory_match.php's own comment
   for why it's a guess, not exact). "Not in inventory" stays a
   real, valid choice — this order's item isn't guaranteed to match
   a tracked inventory record, and dispensing shouldn't be blocked
   when it doesn't. Quantity is required only when a real item is
   selected; the shown "in stock" count is a convenience — the
   authoritative check happens server-side in
   api/dispense-pharmacy-order.php, same principle as every other
   client-side check in this app.
   ============================================================= */
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

    function populateItemSelect(typeLabel, suggestedId) {
        const items = currentInventoryList(typeLabel);
        itemSelect.innerHTML = '';

        const noneOpt = document.createElement('option');
        noneOpt.value = '';
        noneOpt.textContent = 'Not in inventory — don’t adjust stock';
        itemSelect.appendChild(noneOpt);

        items.forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.name + ' (' + item.currentStock + ' ' + item.unit + ' in stock)';
            opt.dataset.stock = item.currentStock;
            opt.dataset.unit = item.unit;
            itemSelect.appendChild(opt);
        });

        itemSelect.value = suggestedId && items.some(function (i) { return i.id === suggestedId; }) ? suggestedId : '';
        updateStockNote();
    }

    function updateStockNote() {
        const selected = itemSelect.options[itemSelect.selectedIndex];
        if (!selected || selected.value === '') {
            stockNote.textContent = 'Stock will not be adjusted for this dispense.';
            qtyInput.required = false;
        } else {
            stockNote.textContent = selected.dataset.stock + ' ' + selected.dataset.unit + ' currently in stock.';
            qtyInput.required = true;
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
                HCDialog.alert('Only ' + stockAvailable + ' ' + selected.dataset.unit + ' in stock — cannot dispense ' + quantity + '.');
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
                qtyInput.value = '';
                populateItemSelect(d.typeLabel, d.suggestedItemId);
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
