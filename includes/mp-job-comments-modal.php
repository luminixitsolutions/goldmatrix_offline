<?php
/** Comments modal — Manufacturing Process job cards (status + comment thread) */
?>
<style>
.mp-comments-modal-content {
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(91, 33, 182, 0.35);
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
}
.mp-comments-header {
    border-bottom: 2px solid #7c3aed !important;
    background: #fff !important;
    color: #1e1b4b !important;
    padding: 12px 16px !important;
}
.mp-comments-header .modal-title {
    width: 100%;
    text-align: center;
    font-weight: 700;
    font-size: 1.1rem;
    color: #4c1d95;
}
.mp-comments-header .close {
    color: #64748b !important;
    opacity: 1;
    text-shadow: none;
    position: absolute;
    right: 12px;
    top: 10px;
}
.mp-comments-status-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}
.mp-comments-status-row label {
    margin: 0;
    font-weight: 600;
    color: #334155;
    font-size: 13px;
}
.mp-comments-status-row select.form-control {
    max-width: 220px;
    border-color: #c4b5fd;
    color: #1e1b4b;
}
#mpCommentsUpdateStatusBtn {
    border: 1px solid #7c3aed;
    color: #6d28d9;
    background: #fff;
    font-weight: 600;
    font-size: 13px;
    padding: 6px 14px;
    border-radius: 6px;
    cursor: pointer;
}
#mpCommentsUpdateStatusBtn:hover {
    background: #f5f3ff;
}
.mp-comments-list-wrap {
    border: 1px solid #e9d5ff;
    border-radius: 8px;
    background: #fafaff;
    min-height: 120px;
    max-height: 220px;
    overflow-y: auto;
    padding: 8px 10px;
    margin-bottom: 10px;
}
.mp-comments-line {
    font-size: 12px;
    color: #334155;
    padding: 8px 0;
    border-bottom: 1px solid #ede9fe;
}
.mp-comments-line:last-child {
    border-bottom: 0;
}
.mp-comments-line time {
    display: block;
    font-size: 10px;
    color: #94a3b8;
    margin-bottom: 4px;
}
.mp-comments-line p {
    margin: 0;
    white-space: pre-wrap;
    word-break: break-word;
}
.mp-comments-empty {
    text-align: center;
    color: #94a3b8;
    font-size: 13px;
    padding: 24px 8px;
}
.mp-comments-input-row {
    display: flex;
    gap: 8px;
    align-items: stretch;
}
#mpCommentsInput {
    flex: 1;
    border: 1px solid #c4b5fd;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 13px;
}
#mpCommentsAddBtn {
    width: 40px;
    border: 1px solid #7c3aed;
    background: #fff;
    color: #6d28d9;
    border-radius: 8px;
    font-size: 20px;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}
#mpCommentsAddBtn:hover {
    background: #f5f3ff;
}
#mpCommentsDoneBtn {
    border: 0;
    background: transparent;
    color: #6d28d9;
    font-weight: 700;
    font-size: 15px;
    padding: 8px 24px;
    cursor: pointer;
}
#mpCommentsDoneBtn:hover {
    text-decoration: underline;
}
.manufacturing-process-page #mpCommentsModal {
    z-index: 2105 !important;
}
.manufacturing-process-page #mpCommentsModal + .modal-backdrop {
    z-index: 2100 !important;
}
</style>
<div class="modal fade" id="mpCommentsModal" tabindex="-1" role="dialog" aria-labelledby="mpCommentsModalTitle" aria-hidden="true" data-backdrop="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 480px;">
        <div class="modal-content mp-comments-modal-content">
            <div class="modal-header mp-comments-header position-relative">
                <h5 class="modal-title mb-0" id="mpCommentsModalTitle">Comments</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" style="padding:16px 18px;">
                <input type="hidden" id="mpCommentsJwoId" value="">
                <div class="mp-comments-status-row">
                    <label for="mpCommentsStatus">Status</label>
                    <select class="form-control form-control-sm" id="mpCommentsStatus">
                        <option value="Completed">Completed</option>
                        <option value="Hold">Hold</option>
                        <option value="Invoice Created">Invoice Created</option>
                        <option value="Not Initiate">Not Initiate</option>
                        <option value="Processing">Processing</option>
                        <option value="Rejected">Rejected</option>
                        <option value="Transferred">Transferred</option>
                        <option value="draft">draft</option>
                    </select>
                    <button type="button" id="mpCommentsUpdateStatusBtn">Update Status</button>
                </div>
                <div class="mp-comments-list-wrap" id="mpCommentsListWrap">
                    <div class="mp-comments-empty" id="mpCommentsEmpty">No comments yet.</div>
                    <div id="mpCommentsList" style="display:none;"></div>
                </div>
                <div class="mp-comments-input-row">
                    <input type="text" id="mpCommentsInput" placeholder="Enter Comment..." autocomplete="off">
                    <button type="button" id="mpCommentsAddBtn" title="Add comment" aria-label="Add comment">+</button>
                </div>
            </div>
            <div class="modal-footer border-0 justify-content-center pt-0" style="padding-bottom:16px;">
                <button type="button" class="btn btn-link" id="mpCommentsDoneBtn" data-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    function mpCommentsEsc(s) {
        if (s == null || s === '') return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function mpCommentsFormatDt(iso) {
        if (!iso) return '';
        var t = iso.replace(' ', 'T');
        var d = new Date(t);
        if (isNaN(d.getTime())) return iso;
        return d.toLocaleString();
    }

    function mpCommentsSetStatusSelect(val) {
        var sel = document.getElementById('mpCommentsStatus');
        if (!sel) return;
        var v = (val || '').trim();
        var found = false;
        for (var i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value === v) {
                found = true;
                break;
            }
        }
        sel.value = found ? v : 'Processing';
    }

    function mpCommentsRenderList(comments) {
        var list = document.getElementById('mpCommentsList');
        var empty = document.getElementById('mpCommentsEmpty');
        if (!list || !empty) return;
        if (!comments || !comments.length) {
            empty.style.display = '';
            list.style.display = 'none';
            list.innerHTML = '';
            return;
        }
        empty.style.display = 'none';
        list.style.display = '';
        list.innerHTML = comments.map(function (c) {
            return '<div class="mp-comments-line" data-cid="' + c.id + '"><time>' + mpCommentsEsc(mpCommentsFormatDt(c.created_at)) + '</time><p>' + mpCommentsEsc(c.comment_text) + '</p></div>';
        }).join('');
    }

    function mpCommentsUpdateCardPill(jwoId, statusText) {
        var card = document.querySelector('.mp-job-card[data-jwo-id="' + jwoId + '"]');
        if (!card) return;
        var pill = card.querySelector('.status-pill');
        if (!pill) return;
        var s = (statusText || '').trim();
        pill.textContent = s !== '' ? s : 'Processing';
    }

    function mpCommentsLoad(jwoId) {
        var id = parseInt(jwoId, 10);
        if (isNaN(id) || id < 1) return;
        var hid = document.getElementById('mpCommentsJwoId');
        if (hid) hid.value = String(id);
        var list = document.getElementById('mpCommentsList');
        var empty = document.getElementById('mpCommentsEmpty');
        if (empty) {
            empty.textContent = 'Loading…';
            empty.style.display = '';
        }
        if (list) list.innerHTML = '';
        fetch('ajax/mp-jobwork-order-comments-api.php?jobwork_order_id=' + encodeURIComponent(String(id)), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    if (empty) {
                        empty.textContent = 'Could not load comments.';
                        empty.style.display = '';
                    }
                    return;
                }
                mpCommentsSetStatusSelect(data.status);
                mpCommentsRenderList(data.comments || []);
            })
            .catch(function () {
                if (empty) {
                    empty.textContent = 'Could not load comments.';
                    empty.style.display = '';
                }
            });
    }

    function mpCommentsOpen(btn) {
        var jwoId = parseInt(btn.getAttribute('data-jwo-id') || '0', 10);
        if (jwoId < 1) return;
        mpCommentsLoad(jwoId);
        if (typeof window.jQuery !== 'undefined' && window.jQuery.fn.modal) {
            window.jQuery('#mpCommentsModal').modal('show');
        }
    }

    function initMpJobCommentsModal() {
        var grid = document.getElementById('mpJobCardsGrid');
        if (grid && !grid._mpCommentsBound) {
            grid._mpCommentsBound = true;
            grid.addEventListener('click', function (e) {
                var btn = e.target.closest('.mp-comment-btn');
                if (!btn || !grid.contains(btn)) return;
                e.preventDefault();
                e.stopPropagation();
                mpCommentsOpen(btn);
            });
        }

        var upd = document.getElementById('mpCommentsUpdateStatusBtn');
        if (upd && !upd._bound) {
            upd._bound = true;
            upd.addEventListener('click', function () {
                var hid = document.getElementById('mpCommentsJwoId');
                var sel = document.getElementById('mpCommentsStatus');
                var id = hid ? parseInt(hid.value || '0', 10) : 0;
                var status = sel ? sel.value : '';
                if (id < 1) return;
                var fd = new FormData();
                fd.append('action', 'update_status');
                fd.append('jobwork_order_id', String(id));
                fd.append('status', status);
                upd.disabled = true;
                fetch('ajax/mp-jobwork-order-comments-api.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        upd.disabled = false;
                        if (!data || !data.ok) {
                            alert(data && data.message ? data.message : 'Update failed');
                            return;
                        }
                        mpCommentsUpdateCardPill(id, data.status || status);
                        alert(data.message || 'Status updated.');
                    })
                    .catch(function () {
                        upd.disabled = false;
                        alert('Update failed');
                    });
            });
        }

        var addBtn = document.getElementById('mpCommentsAddBtn');
        var inp = document.getElementById('mpCommentsInput');
        if (addBtn && inp && !addBtn._bound) {
            addBtn._bound = true;
            function addComment() {
                var hid = document.getElementById('mpCommentsJwoId');
                var id = hid ? parseInt(hid.value || '0', 10) : 0;
                var text = (inp.value || '').trim();
                if (id < 1) return;
                if (!text) {
                    alert('Enter a comment.');
                    return;
                }
                var fd = new FormData();
                fd.append('action', 'add_comment');
                fd.append('jobwork_order_id', String(id));
                fd.append('comment_text', text);
                addBtn.disabled = true;
                fetch('ajax/mp-jobwork-order-comments-api.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        addBtn.disabled = false;
                        if (!data || !data.ok) {
                            alert(data && data.message ? data.message : 'Save failed');
                            return;
                        }
                        inp.value = '';
                        var c = data.comment;
                        if (c && c.id) {
                            var list = document.getElementById('mpCommentsList');
                            var empty = document.getElementById('mpCommentsEmpty');
                            if (empty) empty.style.display = 'none';
                            if (list) {
                                list.style.display = '';
                                var div = document.createElement('div');
                                div.className = 'mp-comments-line';
                                div.setAttribute('data-cid', String(c.id));
                                div.innerHTML = '<time>' + mpCommentsEsc(mpCommentsFormatDt(c.created_at)) + '</time><p>' + mpCommentsEsc(c.comment_text) + '</p>';
                                list.insertBefore(div, list.firstChild);
                            }
                        }
                    })
                    .catch(function () {
                        addBtn.disabled = false;
                        alert('Save failed');
                    });
            }
            addBtn.addEventListener('click', addComment);
            inp.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addComment();
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', initMpJobCommentsModal);
})();
</script>
