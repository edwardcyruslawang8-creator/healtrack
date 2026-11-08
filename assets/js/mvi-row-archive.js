/*
  Row-level Archive button (.mvi-row-archive) on
  subadmin/medicine_vaccine_inventory.php (includes/subadmin/_mvi_*_table.php)
  and pharmacist/medicine_vaccine_inventory.php.
  Delegated on document so it keeps working after
  assets/js/mvi-ajax-pagination.js swaps the table HTML. Confirms, POSTs
  to api/archive-medicine.php (soft archive, same as manage_medicine.php's
  own Archive), then reloads so the table and the summary cards reflect it.
*/
(function () {
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.mvi-row-archive');
        if (!btn || btn.disabled) return;

        const id = btn.dataset.archiveId;
        const name = btn.dataset.archiveName || 'this item';
        const confirmed = await HCDialog.confirm('Archive "' + name + '"? It will stop appearing in the inventory list and on the public site.');
        if (!confirmed) return;

        btn.disabled = true;
        fetch('../api/archive-medicine.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id }),
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) throw new Error(result.data.error || 'Something went wrong. Please try again.');
                window.location.reload();
            })
            .catch(function (err) {
                btn.disabled = false;
                HCDialog.alert(err.message);
            });
    });
})();
