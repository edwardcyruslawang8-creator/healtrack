/* =============================================================
   ARCHIVED ITEMS JS (pharmacist/archived_items.php)
   ---------------------------------------------------------------
   One real row action, reading the clicked row's own data-arc-*
   attributes (set server-side, no extra request needed to know which
   item/type a click was on):
     - Unarchive: HCDialog.confirm() + POST to api/unarchive-medicine.php
       — clears the real "archived" flag, so the item reappears on
       pharmacist/medicine_vaccine_inventory.php again.
   No Delete here — api/delete-medicine.php is subadmin-only; a
   pharmacist can only archive/unarchive, never permanently remove an
   item. Reloads the page on success so the table re-renders from
   fresh Firestore data, same pattern as
   assets/js/medicine_vaccine_inventory_pharmacist.js.
   ============================================================= */
document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('click', async function (e) {
        const link = e.target.closest('.arc-unarchive');
        if (!link) return;
        const row = link.closest('tr');
        if (!row) return;
        e.preventDefault();
        const d = row.dataset;
        const confirmed = await HCDialog.confirm('Unarchive "' + d.arcName + '"? It will reappear in the active Medicine & Vaccine Inventory list.');
        if (!confirmed) return;

        fetch('../api/unarchive-medicine.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: d.arcId }),
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
                HCDialog.alert(err.message);
            });
    });
});
