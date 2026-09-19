<?php
/** Manufacturing Process — revert job from current department (undo last transfer into this dept) */
?>
<style>
/* Above JWQ modal and Bootstrap modals */
body.manufacturing-process-page.stop-scrolling .sweet-overlay {
    background: rgba(15, 23, 42, 0.45);
    z-index: 12000 !important;
}
body.manufacturing-process-page .sweet-alert.mp-delete-jwo-swal {
    z-index: 12001 !important;
    width: auto !important;
    max-width: 480px !important;
    min-width: 320px !important;
    margin-left: -240px !important;
    left: 50% !important;
    border-radius: 16px !important;
    padding: 28px 24px 22px !important;
    box-shadow: 0 12px 40px rgba(15, 23, 42, 0.2) !important;
    font-family: inherit;
}
body.manufacturing-process-page .sweet-alert.mp-delete-jwo-swal h2 {
    font-size: 1.3rem !important;
    font-weight: 700 !important;
    color: #1e3a5f !important;
    margin: 12px 0 10px !important;
    line-height: 1.3 !important;
}
body.manufacturing-process-page .sweet-alert.mp-delete-jwo-swal p,
body.manufacturing-process-page .sweet-alert.mp-delete-jwo-swal p.lead {
    color: #64748b !important;
    font-size: 0.95rem !important;
    line-height: 1.5 !important;
    margin: 0 0 4px !important;
    display: block !important;
}
body.manufacturing-process-page .sweet-alert.mp-delete-jwo-swal .sa-button-container {
    margin-top: 22px !important;
    padding-top: 0 !important;
    border-top: none !important;
    display: flex !important;
    flex-wrap: wrap !important;
    justify-content: center !important;
    align-items: center !important;
    gap: 12px !important;
}
body.manufacturing-process-page .sweet-alert.mp-delete-jwo-swal button.confirm.btn-mp-delete-sw-yes {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%) !important;
    color: #fff !important;
    border: none !important;
    border-radius: 999px !important;
    padding: 10px 28px !important;
    font-weight: 600 !important;
    font-size: 0.95rem !important;
    box-shadow: 0 4px 14px rgba(220, 38, 38, 0.35) !important;
    min-width: 96px !important;
    margin: 0 !important;
}
body.manufacturing-process-page .sweet-alert.mp-delete-jwo-swal button.cancel.btn-mp-delete-sw-no {
    background: #f1f5f9 !important;
    color: #475569 !important;
    border: none !important;
    border-radius: 999px !important;
    padding: 10px 28px !important;
    font-weight: 600 !important;
    font-size: 0.95rem !important;
    min-width: 96px !important;
    margin: 0 !important;
    border-left: none !important;
}
body.manufacturing-process-page .sweet-alert.mp-delete-jwo-swal button.confirm.btn-mp-delete-sw-yes:hover {
    filter: brightness(1.05);
}
body.manufacturing-process-page .sweet-alert.mp-delete-jwo-swal button.cancel.btn-mp-delete-sw-no:hover {
    background: #e2e8f0 !important;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var grid = document.getElementById('mpJobCardsGrid');
    if (!grid || grid._mpDeleteJwoBound) {
        return;
    }
    grid._mpDeleteJwoBound = true;
    grid.addEventListener('click', function (e) {
        var btn = e.target.closest('.mp-job-delete-btn');
        if (!btn || !grid.contains(btn)) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        var jwoId = btn.getAttribute('data-jwo-id');
        if (!jwoId) {
            return;
        }
        var card = btn.closest('.mp-job-card');

        function doRevert() {
            var fd = new FormData();
            fd.append('jobwork_order_id', jwoId);
            btn.disabled = true;
            fetch('ajax/mp-revert-jobwork-queue-dept.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    btn.disabled = false;
                    if (!data || !data.ok) {
                        var errMsg = data && data.message ? data.message : 'Could not remove from department';
                        if (typeof swal === 'function') {
                            swal({
                                title: 'Cannot remove',
                                text: errMsg,
                                type: 'error',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            alert(errMsg);
                        }
                        return;
                    }
                    if (data.removed_from_queue) {
                        /* Job left the manufacturing floor entirely (was still in its first department). */
                        if (card) {
                            var cardWrap = card.closest('.mp-job-card-grid-item') || card;
                            if (cardWrap.parentNode) {
                                cardWrap.parentNode.removeChild(cardWrap);
                            }
                        }
                        if (typeof filterByDepartmentAndUser === 'function') {
                            filterByDepartmentAndUser();
                        }
                        if (typeof swal === 'function') {
                            swal({
                                title: 'Removed',
                                text: data.message || 'Job removed from the manufacturing floor.',
                                type: 'success',
                                timer: 2200,
                                showConfirmButton: true,
                                confirmButtonText: 'OK'
                            });
                        }
                        return;
                    }
                    if (card) {
                        if (data.previous_department_id) {
                            card.setAttribute('data-dept-id', String(data.previous_department_id));
                        }
                        if (data.previous_user_id) {
                            card.setAttribute('data-user-id', String(data.previous_user_id));
                        } else {
                            card.setAttribute('data-user-id', '0');
                        }
                        var deptBanner = card.querySelector('.mp-dept-banner-name');
                        if (deptBanner && data.previous_department_name) {
                            deptBanner.textContent = String(data.previous_department_name).toUpperCase();
                        }
                        var workerMeta = card.querySelector('.mp-name-meta span:first-child');
                        if (workerMeta && data.previous_user_name) {
                            workerMeta.textContent = String(data.previous_user_name);
                        }
                        if (data.jobwork_queue_no) {
                            card.querySelectorAll('[data-jobwork-queue-no]').forEach(function (el) {
                                el.setAttribute('data-jobwork-queue-no', data.jobwork_queue_no);
                            });
                        }
                    }
                    if (typeof filterByDepartmentAndUser === 'function') {
                        filterByDepartmentAndUser();
                    }
                    var okMsg = data.message || 'Job moved to previous department.';
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
                })
                .catch(function () {
                    btn.disabled = false;
                    if (typeof swal === 'function') {
                        swal({
                            title: 'Error',
                            text: 'Could not remove from department.',
                            type: 'error',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        alert('Could not remove from department');
                    }
                });
        }

        var confirmText = 'This will remove the job from the current department and move it back to the previous department. If there is no previous department, the job will be removed from the manufacturing floor. Issued diamonds are returned to stock.';
        if (typeof swal === 'function') {
            swal({
                title: 'Are you sure?',
                text: confirmText,
                type: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
                confirmButtonClass: 'btn-mp-delete-sw-yes',
                cancelButtonClass: 'btn-mp-delete-sw-no',
                customClass: 'mp-delete-jwo-swal'
            }, function (isConfirm) {
                if (isConfirm) {
                    doRevert();
                }
            });
        } else if (window.confirm('Are you sure?\n\n' + confirmText)) {
            doRevert();
        }
    });
});
</script>
