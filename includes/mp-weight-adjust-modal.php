<?php
/** Add / Reduce weight: opens full Jobwork Queue overlay (same as Transfer) with inline weight strip — manufacturing + jobwork-queue page */
?>
<script>
(function () {
    window.jwqIsReduceWeightMode = function () {
        if (!window.__jwqWeightAdjustActive) {
            return false;
        }
        var modeHid = document.getElementById('jwqWeightAdjustMode');
        return !!(modeHid && modeHid.value === 'reduce');
    };

    window.jwqIsAddWeightMode = function () {
        if (!window.__jwqWeightAdjustActive) {
            return false;
        }
        var modeHid = document.getElementById('jwqWeightAdjustMode');
        return !!(modeHid && modeHid.value === 'add');
    };

    /** Add Weight: Metal Wt field is extra grams only (starts at 0); Total Wt = opening total + extra. */
    window.jwqIsAddWeightExtraMetalUi = function () {
        return window.__jwqAddWeightExtraMetalUi === true;
    };

    /** Reduce Weight: Metal Wt = grams to reduce (starts at 0); Total Wt = opening total − reduce. */
    window.jwqIsReduceWeightExtraMetalUi = function () {
        return window.__jwqReduceWeightExtraMetalUi === true;
    };

    window.jwqIsWeightExtraMetalUi = function () {
        return window.jwqIsAddWeightExtraMetalUi() || window.jwqIsReduceWeightExtraMetalUi();
    };

    window.jwqIsWeightAdjustMode = function () {
        return window.jwqIsAddWeightMode() || window.jwqIsReduceWeightMode();
    };

    window.jwqToggleJwqTransferFields = function (showTransfer) {
        var toBlock = document.querySelector('#jwqModalOverlay .jwq-to-block');
        var arrows = document.querySelector('#jwqModalOverlay .jwq-arrows');
        var toDept = document.getElementById('jwqToDept');
        var mainSave = document.getElementById('jwqBtnSave');
        if (toBlock) {
            toBlock.style.display = showTransfer ? '' : 'none';
        }
        if (arrows) {
            arrows.style.display = showTransfer ? '' : 'none';
        }
        if (toDept) {
            if (showTransfer) {
                toDept.setAttribute('required', 'required');
            } else {
                toDept.removeAttribute('required');
            }
        }
        /* Keep the main Save visible in Reduce Weight mode too: line weights (Total Wt / Diamond Wt)
           and diamond issues are committed only by the main Save, not the strip Save. */
        if (mainSave) {
            mainSave.style.display = '';
        }
    };

    window.jwqToggleWeightStrip = function (show, weightMode) {
        var strip = document.getElementById('jwqWeightAdjustStrip');
        if (!strip) {
            return;
        }
        var modeHid = document.getElementById('jwqWeightAdjustMode');
        var titleEl = document.getElementById('jwqWeightAdjustTitle');
        var grams = document.getElementById('jwqWeightAdjustGrams');
        var remark = document.getElementById('jwqWeightAdjustRemark');
        if (show) {
            var wm = (weightMode === 'add') ? 'add' : 'reduce';
            window.__jwqWeightAdjustActive = true;
            if (modeHid) {
                modeHid.value = wm;
            }
            if (titleEl) {
                titleEl.textContent = wm === 'add' ? 'Add Weight' : 'Reduce Weight';
            }
            if (grams) {
                grams.value = '';
            }
            if (remark) {
                remark.value = '';
            }
            /* Add / Reduce Weight: metal exchange rows only — hide inline weight strip. */
            strip.style.display = 'none';
            strip.setAttribute('aria-hidden', 'true');
            if (typeof window.jwqToggleJwqTransferFields === 'function') {
                window.jwqToggleJwqTransferFields(true);
            }
            if (wm === 'reduce' && typeof window.jwqSnapshotReduceWeightOrigTotals === 'function') {
                setTimeout(function () {
                    window.jwqSnapshotReduceWeightOrigTotals(true);
                }, 0);
            }
            if (wm === 'add' && typeof window.jwqSnapshotAddWeightOrigTotals === 'function') {
                setTimeout(function () {
                    window.jwqSnapshotAddWeightOrigTotals(true);
                }, 0);
            }
        } else {
            window.__jwqWeightAdjustActive = false;
            window.__jwqAddWeightExtraMetalUi = false;
            window.__jwqReduceWeightExtraMetalUi = false;
            strip.style.display = 'none';
            strip.setAttribute('aria-hidden', 'true');
            if (modeHid) {
                modeHid.value = '';
            }
            if (typeof window.jwqToggleJwqTransferFields === 'function') {
                window.jwqToggleJwqTransferFields(true);
            }
        }
    };

    window.jwqApplyWeightAdjustLinesUi = function (mode) {
        mode = (mode === 'add') ? 'add' : 'reduce';
        window.__jwqAddWeightExtraMetalUi = (mode === 'add');
        window.__jwqReduceWeightExtraMetalUi = (mode === 'reduce');
        window.__jwqPrefillLineWeights = !(window.__jwqAddWeightExtraMetalUi || window.__jwqReduceWeightExtraMetalUi);
        document.querySelectorAll('#jwqOrderLinesBody tr[data-item-id]').forEach(function (tr) {
            tr.removeAttribute('data-jwq-orig-total-before-add');
            tr.removeAttribute('data-jwq-orig-metal-before-add');
            tr.removeAttribute('data-jwq-orig-total-before-reduce');
            tr.removeAttribute('data-jwq-orig-metal-before-reduce');
            tr.removeAttribute('data-jwq-add-orig-snapshotted');
            tr.removeAttribute('data-jwq-reduce-orig-snapshotted');
            tr.removeAttribute('data-jwq-manual-add-grams');
            tr.removeAttribute('data-jwq-manual-reduce-grams');
        });
        if (mode === 'add' && typeof window.jwqSnapshotAddWeightOrigTotals === 'function') {
            window.jwqSnapshotAddWeightOrigTotals(true);
        }
        if (mode === 'reduce' && typeof window.jwqSnapshotReduceWeightOrigTotals === 'function') {
            window.jwqSnapshotReduceWeightOrigTotals(true);
        }
        if (mode === 'add' && typeof window.jwqInitAddWeightExtraMetalUi === 'function') {
            window.jwqInitAddWeightExtraMetalUi();
        }
        if (mode === 'reduce' && typeof window.jwqInitReduceWeightExtraMetalUi === 'function') {
            window.jwqInitReduceWeightExtraMetalUi();
        }
        if (typeof window.jwqSyncOrderLineDiamondWtFromMaterialTable === 'function') {
            window.jwqSyncOrderLineDiamondWtFromMaterialTable();
        }
    };

    function handleWeightButtonClick(btn, e) {
        var mode = (btn.getAttribute('data-weight-mode') || 'reduce').toLowerCase();
        if (btn.closest('#jwqModalOverlay')) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            var jwoEl = document.getElementById('jwqCurrentJwoId');
            var jwoId = jwoEl ? parseInt(jwoEl.value || '0', 10) : 0;
            if (jwoId < 1) {
                alert('No job work order loaded.');
                return;
            }
            window.jwqToggleWeightStrip(true, mode);
            if (typeof window.jwqApplyWeightAdjustLinesUi === 'function') {
                window.jwqApplyWeightAdjustLinesUi(mode);
            }
            return;
        }
        if (typeof jwqOpenModal !== 'function') {
            alert('Jobwork Queue modal is not available.');
            return;
        }
        jwqOpenModal(btn, { forWeight: true, weightMode: mode });
    }

    function mpWeightAdjOpen(mode, jwoId, jobworkNo) {
        mode = (mode === 'add') ? 'add' : 'reduce';
        var idNum = parseInt(jwoId, 10);
        if (isNaN(idNum) || idNum < 1) {
            alert('Select a job work order first.');
            return;
        }
        var grid = document.getElementById('mpJobCardsGrid');
        var sel = '.mp-weight-btn[data-jwo-id="' + idNum + '"][data-weight-mode="' + mode + '"]';
        var btn = grid ? grid.querySelector(sel) : null;
        if (!btn && grid) {
            btn = grid.querySelector('.mp-weight-btn[data-jwo-id="' + idNum + '"]');
        }
        if (btn && typeof jwqOpenModal === 'function') {
            jwqOpenModal(btn, { forWeight: true, weightMode: mode });
            return;
        }
        alert('Open the job from the manufacturing grid to adjust weight.');
    }

    function initMpWeightAdjustModal() {
        var grid = document.getElementById('mpJobCardsGrid');
        if (grid && !grid._mpWeightBound) {
            grid._mpWeightBound = true;
            grid.addEventListener('click', function (e) {
                var btn = e.target.closest('.mp-weight-btn');
                if (!btn || !grid.contains(btn)) {
                    return;
                }
                handleWeightButtonClick(btn, e);
            });
        }

        document.querySelectorAll('.mp-weight-btn').forEach(function (btn) {
            if (btn.closest('#mpJobCardsGrid')) {
                return;
            }
            if (btn._mpWeightDirectBound) {
                return;
            }
            btn._mpWeightDirectBound = true;
            btn.addEventListener('click', function (e) {
                handleWeightButtonClick(btn, e);
            });
        });

        var saveBtn = document.getElementById('jwqWeightAdjustSaveBtn');
        if (saveBtn && !saveBtn._mpWeightSaveBound) {
            saveBtn._mpWeightSaveBound = true;
            saveBtn.addEventListener('click', function () {
                var hidId = document.getElementById('jwqCurrentJwoId');
                var hidMode = document.getElementById('jwqWeightAdjustMode');
                var wEl = document.getElementById('jwqWeightAdjustGrams');
                var rEl = document.getElementById('jwqWeightAdjustRemark');
                var fromDeptEl = document.getElementById('jwqFromDept');
                var fromUserEl = document.getElementById('jwqFromUser');
                var toDeptEl = document.getElementById('jwqToDept');
                var toUserEl = document.getElementById('jwqToUser');
                var id = hidId ? parseInt(hidId.value || '0', 10) : 0;
                var mode = (hidMode && hidMode.value === 'add') ? 'add' : 'reduce';
                var w = wEl ? parseFloat(String(wEl.value || '').replace(/,/g, '')) : NaN;
                var fromDeptId = fromDeptEl ? parseInt(fromDeptEl.value || '0', 10) : 0;
                var fromUserId = fromUserEl ? parseInt(fromUserEl.value || '0', 10) : 0;
                var toDeptId = toDeptEl ? parseInt(toDeptEl.value || '0', 10) : 0;
                var toUserId = toUserEl ? parseInt(toUserEl.value || '0', 10) : 0;
                if (id < 1) {
                    alert('Invalid job work order.');
                    return;
                }
                if (!isFinite(w) || w <= 0) {
                    alert('Enter a valid weight greater than zero.');
                    return;
                }
                if (mode === 'add' && fromDeptId < 1) {
                    alert('Please select From Dept.');
                    if (fromDeptEl) fromDeptEl.focus();
                    return;
                }
                if (mode === 'add' && toDeptId < 1) {
                    alert('Please select To Dept.');
                    if (toDeptEl) toDeptEl.focus();
                    return;
                }
                if (mode === 'add' && fromDeptId === toDeptId && fromUserId === toUserId) {
                    alert('From and To department/user cannot be the same.');
                    return;
                }
                var fd = new FormData();
                fd.append('jobwork_order_id', String(id));
                fd.append('adjustment_type', mode);
                fd.append('weight_grams', String(w));
                fd.append('remark', rEl ? String(rEl.value || '').trim() : '');
                if (fromDeptId > 0) fd.append('from_dept_id', String(fromDeptId));
                if (fromUserId > 0) fd.append('from_user_id', String(fromUserId));
                if (toDeptId > 0) fd.append('to_dept_id', String(toDeptId));
                if (toUserId > 0) fd.append('to_user_id', String(toUserId));
                saveBtn.disabled = true;
                fetch('ajax/mp-save-jobwork-weight-adjustment.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        saveBtn.disabled = false;
                        if (!data || !data.ok) {
                            alert(data && data.message ? data.message : 'Save failed');
                            return;
                        }
                        if (typeof window.jwqToggleWeightStrip === 'function') {
                            window.jwqToggleWeightStrip(false);
                        }
                        if (typeof window.mpReloadManufacturingQueueTable === 'function') {
                            window.mpReloadManufacturingQueueTable();
                        }
                        alert(data.message || 'Saved.');
                    })
                    .catch(function () {
                        saveBtn.disabled = false;
                        alert('Save failed');
                    });
            });
        }
    }

    document.addEventListener('DOMContentLoaded', initMpWeightAdjustModal);
    window.mpWeightAdjOpen = mpWeightAdjOpen;
})();
</script>
