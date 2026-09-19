<?php
/** Manufacturing Process — reverse a department transfer from inward/outward stock tables */
?>
<style>
body.manufacturing-process-page .sweet-alert.mp-mfg-reverse-swal {
    z-index: 12001 !important;
    width: auto !important;
    max-width: 520px !important;
    min-width: 320px !important;
    margin-left: -260px !important;
    left: 50% !important;
    border-radius: 16px !important;
    padding: 28px 24px 22px !important;
    box-shadow: 0 12px 40px rgba(15, 23, 42, 0.2) !important;
    font-family: inherit;
}
body.manufacturing-process-page .sweet-alert.mp-mfg-reverse-swal h2 {
    font-size: 1.25rem !important;
    font-weight: 700 !important;
    color: #1e3a5f !important;
    margin: 12px 0 10px !important;
    line-height: 1.3 !important;
}
body.manufacturing-process-page .sweet-alert.mp-mfg-reverse-swal p,
body.manufacturing-process-page .sweet-alert.mp-mfg-reverse-swal p.lead {
    color: #64748b !important;
    font-size: 0.95rem !important;
    line-height: 1.55 !important;
    margin: 0 0 6px !important;
    display: block !important;
    text-align: left !important;
}
body.manufacturing-process-page .sweet-alert.mp-mfg-reverse-swal .sa-button-container {
    margin-top: 22px !important;
    display: flex !important;
    flex-wrap: wrap !important;
    justify-content: center !important;
    gap: 12px !important;
}
body.manufacturing-process-page .sweet-alert.mp-mfg-reverse-swal button.confirm.btn-mp-mfg-reverse-yes {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%) !important;
    color: #fff !important;
    border: none !important;
    border-radius: 999px !important;
    padding: 10px 24px !important;
    font-weight: 600 !important;
    font-size: 0.95rem !important;
    min-width: 120px !important;
    margin: 0 !important;
}
body.manufacturing-process-page .sweet-alert.mp-mfg-reverse-swal button.cancel.btn-mp-mfg-reverse-no {
    background: #f1f5f9 !important;
    color: #475569 !important;
    border: none !important;
    border-radius: 999px !important;
    padding: 10px 24px !important;
    font-weight: 600 !important;
    font-size: 0.95rem !important;
    min-width: 96px !important;
    margin: 0 !important;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window._mpMfgQueueReverseBound) {
        return;
    }
    window._mpMfgQueueReverseBound = true;

    function mpAfterTransferReversed(data) {
        if (typeof filterByDepartmentAndUser === 'function') {
            filterByDepartmentAndUser();
        } else if (typeof mpReloadManufacturingQueueTable === 'function') {
            mpReloadManufacturingQueueTable();
        }
        var okMsg = data && data.message ? data.message : 'Transfer reversed.';
        if (typeof swal === 'function') {
            swal({
                title: 'Done',
                text: okMsg,
                type: 'success',
                timer: 2200,
                showConfirmButton: true,
                confirmButtonText: 'OK'
            });
        }
    }

    function mpRunTransferReverse(btn) {
        var jwoId = btn.getAttribute('data-jwo-id');
        var actId = btn.getAttribute('data-activity-id');
        if (!jwoId || !actId) {
            return;
        }
        var fd = new FormData();
        fd.append('jobwork_order_id', jwoId);
        fd.append('activity_id', actId);
        btn.disabled = true;
        fetch('ajax/mp-reverse-manufacturing-transfer.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled = false;
                if (!data || !data.ok) {
                    var errMsg = data && data.message ? data.message : 'Could not reverse transfer.';
                    if (typeof swal === 'function') {
                        swal({
                            title: 'Cannot reverse',
                            text: errMsg,
                            type: 'error',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        alert(errMsg);
                    }
                    return;
                }
                mpAfterTransferReversed(data);
            })
            .catch(function () {
                btn.disabled = false;
                var failMsg = 'Could not reverse transfer. Please try again.';
                if (typeof swal === 'function') {
                    swal({ title: 'Error', text: failMsg, type: 'error', confirmButtonText: 'OK' });
                } else {
                    alert(failMsg);
                }
            });
    }

    function mpConfirmTransferReverse(btn) {
        var confirmText = 'Are you sure you want to delete this manufacturing transfer?\n\n'
            + 'Deleting this transfer will reverse the movement and return the item/stock to the previous department.\n\n'
            + 'This action cannot be undone.';
        if (typeof swal === 'function') {
            swal({
                title: 'Delete / Reverse Transfer',
                text: confirmText,
                type: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete & Return',
                cancelButtonText: 'Cancel',
                closeOnConfirm: true,
                closeOnCancel: true,
                customClass: 'mp-mfg-reverse-swal',
                confirmButtonClass: 'btn-mp-mfg-reverse-yes',
                cancelButtonClass: 'btn-mp-mfg-reverse-no'
            }, function (isConfirm) {
                if (isConfirm) {
                    mpRunTransferReverse(btn);
                }
            });
        } else if (window.confirm(confirmText)) {
            mpRunTransferReverse(btn);
        }
    }

    ['fullStockTable', 'inwardTable', 'outwardTable'].forEach(function (tableId) {
        var table = document.getElementById(tableId);
        if (!table) {
            return;
        }
        table.addEventListener('click', function (e) {
            var btn = e.target.closest('.mp-mfg-queue-reverse');
            if (!btn || !table.contains(btn)) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            mpConfirmTransferReverse(btn);
        });
    });
});
</script>
