<?php
/** Manufacturing Process: print icon → SweetAlert "Print bill" → jobwork slip only */
?>
<style>
/* Stack above Jobwork Queue modal (.jwq-modal-overlay z-index: 1500) and Bootstrap modals (~2000) */
body .sweet-overlay {
    z-index: 12000 !important;
}
body .sweet-alert {
    z-index: 12001 !important;
}

.sweet-alert.mp-print-bill-swal {
    border-radius: 12px !important;
    padding-top: 28px !important;
}
.sweet-alert.mp-print-bill-swal h2 {
    color: #1e40af !important;
    font-weight: 700 !important;
    font-size: 1.25rem !important;
}
.sweet-alert.mp-print-bill-swal p.lead {
    color: #64748b !important;
    font-size: 0.95rem !important;
}
.sweet-alert.mp-print-bill-swal .sa-button-container {
    text-align: center !important;
    padding-top: 8px !important;
}
.sweet-alert.mp-print-bill-swal button.confirm {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    color: #7c3aed !important;
    font-weight: 700 !important;
    font-size: 1rem !important;
}
.sweet-alert.mp-print-bill-swal button.cancel {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    color: #db2777 !important;
    font-weight: 700 !important;
    font-size: 1rem !important;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var grid = document.getElementById('mpJobCardsGrid');
    if (!grid || grid._mpPrintSlipBound) {
        return;
    }
    grid._mpPrintSlipBound = true;
    grid.addEventListener('click', function (e) {
        var btn = e.target.closest('.mp-print-slip-btn');
        if (!btn || !grid.contains(btn)) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        var jwoId = btn.getAttribute('data-jwo-id') || '';
        if (!jwoId) {
            return;
        }
        var slipUrl = 'manufacturing-jobwork-slip-print.php?id=' + encodeURIComponent(jwoId) + '&autoprint=1';

        function openPrints() {
            window.open(slipUrl, '_blank', 'noopener,noreferrer');
        }

        if (typeof swal === 'function') {
            swal({
                title: 'Print bill',
                text: 'Do you want to print invoice?',
                type: 'info',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
                confirmButtonClass: 'confirm',
                cancelButtonClass: 'cancel',
                customClass: 'mp-print-bill-swal'
            }, function (isConfirm) {
                if (isConfirm) {
                    openPrints();
                }
            });
        } else if (confirm('Do you want to print invoice?')) {
            openPrints();
        }
    });
});

/** Manufacturing queue + inward/outward stock tables — Print bill → slip only */
document.addEventListener('DOMContentLoaded', function () {
    ['fullStockTable', 'inwardTable', 'outwardTable'].forEach(function (tableId) {
        var table = document.getElementById(tableId);
        if (!table || table._mpMfgQueuePrintBound) {
            return;
        }
        table._mpMfgQueuePrintBound = true;
        table.addEventListener('click', function (e) {
            var btn = e.target.closest('.mp-mfg-queue-print');
            if (!btn || !table.contains(btn)) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            var jwoId = btn.getAttribute('data-jwo-id') || '';
            if (!jwoId) {
                return;
            }
            var slipUrl = 'manufacturing-jobwork-slip-print.php?id=' + encodeURIComponent(jwoId) + '&autoprint=1';

            function openPrints() {
                window.open(slipUrl, '_blank', 'noopener,noreferrer');
            }

            if (typeof swal === 'function') {
                swal({
                    title: 'Print bill',
                    text: 'Do you want to print invoice?',
                    type: 'info',
                    showCancelButton: true,
                    confirmButtonText: 'Yes',
                    cancelButtonText: 'No',
                    confirmButtonClass: 'confirm',
                    cancelButtonClass: 'cancel',
                    customClass: 'mp-print-bill-swal'
                }, function (isConfirm) {
                    if (isConfirm) {
                        openPrints();
                    }
                });
            } else if (confirm('Do you want to print invoice?')) {
                openPrints();
            }
        });
    });
});
</script>
