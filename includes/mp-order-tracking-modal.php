<?php
/** Order Tracking modal — Manufacturing Process job cards (vertical timeline + sidebar) */
?>
<style>
/* Order tracking — GoldMatrix navy + gold (logo palette) */
#mpOrderTrackingModal {
    --mp-ot-navy: #142a40;
    --mp-ot-navy-mid: #1e3a5a;
    --mp-ot-navy-soft: #2a4a6e;
    --mp-ot-gold: #c9a227;
    --mp-ot-gold-deep: #a68519;
    --mp-ot-gold-light: #dfc565;
    --mp-ot-gold-pale: #f5efd9;
    --mp-ot-cream: #faf8f3;
    --mp-ot-line: rgba(20, 42, 64, 0.12);
}
#mpOrderTrackingModal .modal-dialog {
    max-width: min(1040px, 98vw);
}
#mpOrderTrackingModal .modal-content {
    border-radius: 12px;
    border: 2px solid var(--mp-ot-navy);
    box-shadow: 0 8px 32px rgba(12, 27, 46, 0.18);
    overflow: hidden;
}
#mpOrderTrackingModal .modal-header {
    background: linear-gradient(180deg, var(--mp-ot-cream) 0%, #fff 100%);
    border-bottom: 2px solid var(--mp-ot-gold);
    padding: 14px 20px;
}
#mpOrderTrackingModal .modal-title {
    width: 100%;
    text-align: center;
    font-weight: 700;
    color: var(--mp-ot-navy);
    font-size: 1.08rem;
}
#mpOrderTrackingModal .close {
    position: absolute;
    right: 14px;
    top: 12px;
    opacity: 0.75;
    font-size: 1.5rem;
    font-weight: 400;
    color: var(--mp-ot-navy);
}
#mpOrderTrackingModal .close:hover {
    opacity: 1;
    color: var(--mp-ot-gold-deep);
}
.mp-ot-grid {
    display: grid;
    grid-template-columns: minmax(260px, 34%) 1fr;
    gap: 0;
    min-height: 360px;
}
@media (max-width: 900px) {
    .mp-ot-grid { grid-template-columns: 1fr; }
}
.mp-ot-left {
    border-right: 1px solid var(--mp-ot-line);
    padding: 16px 18px;
    background: var(--mp-ot-cream);
}
.mp-ot-right {
    padding: 20px 16px 24px;
    background: #fff;
    position: relative;
    overflow-x: auto;
}
.mp-ot-section {
    padding-bottom: 14px;
    margin-bottom: 14px;
    border-bottom: 1px solid var(--mp-ot-gold-pale);
}
.mp-ot-section:last-of-type {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}
.mp-ot-section h6 {
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--mp-ot-navy);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin: 0 0 10px 0;
    border-left: 3px solid var(--mp-ot-gold);
    padding-left: 8px;
}
.mp-ot-kv {
    font-size: 0.84rem;
    line-height: 1.55;
    color: #0f172a;
}
.mp-ot-kv div {
    margin-bottom: 5px;
}
.mp-ot-kv span.lbl {
    font-weight: 700;
    color: var(--mp-ot-navy-mid);
    margin-right: 6px;
}
.mp-ot-kv span.status-val {
    font-weight: 700;
    color: var(--mp-ot-gold-deep);
}
.mp-ot-images {
    min-height: 64px;
    font-size: 0.85rem;
    color: #94a3b8;
    font-style: italic;
    padding: 4px 0 0;
}
.mp-ot-images img {
    max-width: 100%;
    max-height: 100px;
    border-radius: 6px;
    margin: 4px 4px 0 0;
    border: 1px solid var(--mp-ot-gold-pale);
}
/* Vertical timeline */
.mp-ot-tl-v2 {
    position: relative;
    padding: 8px 0 8px;
    min-height: 120px;
}
.mp-ot-tl-v2::before {
    content: '';
    position: absolute;
    left: 50%;
    top: 12px;
    bottom: 12px;
    width: 3px;
    margin-left: -1.5px;
    background: linear-gradient(180deg, var(--mp-ot-navy) 0%, var(--mp-ot-gold) 50%, var(--mp-ot-navy) 100%);
    border-radius: 2px;
    z-index: 0;
}
.mp-ot-step {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: 1fr 44px 1fr;
    gap: 10px 12px;
    align-items: start;
    margin-bottom: 28px;
}
.mp-ot-step:last-child {
    margin-bottom: 8px;
}
.mp-ot-step--right .mp-ot-step-meta {
    grid-column: 1;
    text-align: right;
    padding-right: 4px;
}
.mp-ot-step--right .mp-ot-step-node {
    grid-column: 2;
}
.mp-ot-step--right .mp-ot-step-card {
    grid-column: 3;
}
.mp-ot-step--left .mp-ot-step-card {
    grid-column: 1;
}
.mp-ot-step--left .mp-ot-step-node {
    grid-column: 2;
}
.mp-ot-step--left .mp-ot-step-meta {
    grid-column: 3;
    text-align: left;
    padding-left: 4px;
}
.mp-ot-step-node {
    justify-self: center;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: var(--mp-ot-gold);
    border: 3px solid #fff;
    box-shadow: 0 0 0 2px var(--mp-ot-navy);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--mp-ot-navy);
    flex-shrink: 0;
}
.mp-ot-step-node i {
    width: 18px;
    height: 18px;
}
.mp-ot-step-node--pending {
    background: var(--mp-ot-gold-pale);
    box-shadow: 0 0 0 2px rgba(20, 42, 64, 0.25);
    color: var(--mp-ot-navy-soft);
}
.mp-ot-step-card {
    background: #fff;
    border: 1px solid var(--mp-ot-gold-pale);
    border-radius: 10px;
    padding: 14px 16px;
    box-shadow: 0 4px 16px rgba(20, 42, 64, 0.12);
    font-weight: 800;
    font-size: 0.95rem;
    letter-spacing: 0.04em;
    color: var(--mp-ot-navy);
    text-align: center;
}
.mp-ot-step-card-qno {
    display: block;
    margin-top: 4px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: var(--mp-ot-gold-deep);
}
.mp-ot-step-meta {
    font-size: 0.78rem;
    line-height: 1.45;
    color: #334155;
}
.mp-ot-step-meta .mrow {
    margin-bottom: 3px;
}
.mp-ot-step-meta .mlbl {
    font-weight: 700;
    color: var(--mp-ot-navy-mid);
    margin-right: 4px;
}
/* Legacy stacked timeline (fallback: comments only) */
.mp-ot-timeline {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.mp-ot-tl-card {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    background: #fff;
    border: 1px solid var(--mp-ot-gold-pale);
    border-radius: 10px;
    padding: 12px 14px;
    box-shadow: 0 2px 8px rgba(20, 42, 64, 0.08);
}
.mp-ot-tl-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(145deg, var(--mp-ot-gold-pale) 0%, #fff 100%);
    border: 2px solid var(--mp-ot-gold);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: var(--mp-ot-navy);
}
.mp-ot-tl-row {
    font-size: 0.82rem;
    padding: 8px 10px;
    border-left: 3px solid var(--mp-ot-gold);
    background: var(--mp-ot-cream);
    border-radius: 0 8px 8px 0;
}
.mp-ot-tl-row .t {
    font-weight: 700;
    color: var(--mp-ot-navy);
    font-size: 0.72rem;
    letter-spacing: 0.06em;
}
.manufacturing-process-page #mpOrderTrackingModal {
    z-index: 2120 !important;
}
.manufacturing-process-page body.modal-open .modal-backdrop:last-of-type {
    z-index: 2115 !important;
}
.mp-ot-queue-history {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--mp-ot-gold-pale);
}
.mp-ot-queue-history h6 {
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--mp-ot-navy);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin: 0 0 10px 0;
    border-left: 3px solid var(--mp-ot-gold);
    padding-left: 8px;
}
.mp-ot-qh-table-wrap {
    overflow-x: auto;
    border-radius: 10px;
    border: 1px solid var(--mp-ot-line);
    background: var(--mp-ot-cream);
}
.mp-ot-qh-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.76rem;
    color: #0f172a;
}
.mp-ot-qh-table th,
.mp-ot-qh-table td {
    padding: 8px 10px;
    text-align: left;
    border-bottom: 1px solid var(--mp-ot-gold-pale);
    vertical-align: top;
}
.mp-ot-qh-table th {
    font-weight: 800;
    color: var(--mp-ot-navy);
    background: linear-gradient(180deg, var(--mp-ot-gold-pale) 0%, #fff 100%);
    white-space: nowrap;
}
.mp-ot-qh-table tbody tr:last-child td {
    border-bottom: none;
}
.mp-ot-qh-table .qh-qno {
    font-weight: 700;
    color: var(--mp-ot-gold-deep);
    white-space: nowrap;
}
</style>
<div class="modal fade" id="mpOrderTrackingModal" tabindex="-1" role="dialog" aria-labelledby="mpOrderTrackingTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header position-relative">
                <h5 class="modal-title" id="mpOrderTrackingTitle">Order Tracking</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <div class="mp-ot-grid">
                    <div class="mp-ot-left">
                        <div class="mp-ot-section">
                            <h6>Order Details</h6>
                            <div class="mp-ot-kv" id="mpOtSaleKv">
                                <div><span class="lbl">Order No:</span><span id="mpOtSoNo">—</span></div>
                                <div><span class="lbl">Customer Name:</span><span id="mpOtSoCust">—</span></div>
                                <div><span class="lbl">Order Date:</span><span id="mpOtSoOd">—</span></div>
                                <div><span class="lbl">Delivery Date:</span><span id="mpOtSoDd">—</span></div>
                                <div><span class="lbl">Status:</span><span class="status-val" id="mpOtSoSt">—</span></div>
                            </div>
                        </div>
                        <div class="mp-ot-section">
                            <h6>Job Order Details</h6>
                            <div class="mp-ot-kv" id="mpOtJwoKv">
                                <div><span class="lbl">Job Order No:</span><span id="mpOtJwoNo">—</span></div>
                                <div><span class="lbl">Job Queue No:</span><span id="mpOtJwoQueueNo">—</span></div>
                                <div><span class="lbl">Customer Name:</span><span id="mpOtJwoCust">—</span></div>
                                <div><span class="lbl">Assign To:</span><span id="mpOtJwoAssign">—</span></div>
                                <div><span class="lbl">Order Date:</span><span id="mpOtJwoOd">—</span></div>
                                <div><span class="lbl">Delivery Date:</span><span id="mpOtJwoDd">—</span></div>
                            </div>
                        </div>
                        <div class="mp-ot-section">
                            <h6>Images</h6>
                            <div class="mp-ot-images" id="mpOtImages">No Images To Display !</div>
                        </div>
                    </div>
                    <div class="mp-ot-right">
                        <div class="mp-ot-tl-v2" id="mpOtTimelineV2"></div>
                        <div class="mp-ot-timeline" id="mpOtTimeline" style="display:none;"></div>
                        <div class="mp-ot-queue-history" id="mpOtQueueHistoryWrap" style="display:none;">
                            <h6>Job queue history (by department)</h6>
                            <div class="mp-ot-qh-table-wrap">
                                <table class="mp-ot-qh-table" id="mpOtQueueHistoryTable">
                                    <thead>
                                        <tr>
                                            <th>Date &amp; time</th>
                                            <th>Queue no</th>
                                            <th>Event</th>
                                            <th>Department flow</th>
                                            <th>Wt</th>
                                            <th>Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody id="mpOtQueueHistoryBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    function mpOtEsc(s) {
        if (s == null || s === '') return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function mpOtMetaHtml(it) {
        var od = it.order_date != null ? String(it.order_date) : '—';
        var cd = it.completed_date != null ? String(it.completed_date) : 'NA';
        var tw = it.total_weight != null ? String(it.total_weight) : 'NA';
        var tq = it.total_quantity != null ? String(it.total_quantity) : 'NA';
        var jq = it.job_queue_no != null ? String(it.job_queue_no) : 'NA';
        var ta = it.transfer_at != null ? String(it.transfer_at) : 'NA';
        var html = '';
        html += '<div class="mrow"><span class="mlbl">Order Date</span><span>' + mpOtEsc(od) + '</span></div>';
        html += '<div class="mrow"><span class="mlbl">Completed Date</span><span>' + mpOtEsc(cd) + '</span></div>';
        if (it.state === 'completed' || it.state === 'active') {
            html += '<div class="mrow"><span class="mlbl">Job Queue No</span><span>' + mpOtEsc(jq) + '</span></div>';
            if (ta !== 'NA' && ta !== '—') {
                html += '<div class="mrow"><span class="mlbl">Transfer / arrival</span><span>' + mpOtEsc(ta) + '</span></div>';
            }
            html += '<div class="mrow"><span class="mlbl">Total Weight</span><span>' + mpOtEsc(tw) + '</span></div>';
            html += '<div class="mrow"><span class="mlbl">Total Quantity</span><span>' + mpOtEsc(tq) + '</span></div>';
        }
        return html;
    }

    function mpOtRenderQueueHistory(rows) {
        var wrap = document.getElementById('mpOtQueueHistoryWrap');
        var tbody = document.getElementById('mpOtQueueHistoryBody');
        if (!wrap || !tbody) return;
        tbody.innerHTML = '';
        if (!rows || !rows.length) {
            wrap.style.display = 'none';
            return;
        }
        wrap.style.display = '';
        rows.forEach(function (r) {
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + mpOtEsc(r.date_time) + '</td>'
                + '<td class="qh-qno">' + mpOtEsc(r.queue_no) + '</td>'
                + '<td>' + mpOtEsc(r.action) + '</td>'
                + '<td>' + mpOtEsc(r.flow) + '</td>'
                + '<td>' + mpOtEsc(r.total_weight) + '</td>'
                + '<td>' + mpOtEsc(r.total_quantity) + '</td>';
            tbody.appendChild(tr);
        });
    }

    function mpOtNodeIcon(state) {
        if (state === 'completed') {
            return '<i class="feather icon-check"></i>';
        }
        if (state === 'active') {
            return '<i class="feather icon-clock"></i>';
        }
        return '<i class="feather icon-circle"></i>';
    }

    function mpOtRenderProcessSteps(items) {
        var el = document.getElementById('mpOtTimelineV2');
        var legacy = document.getElementById('mpOtTimeline');
        if (!el) return;
        el.innerHTML = '';
        if (legacy) legacy.style.display = 'none';
        if (!items || !items.length) {
            el.innerHTML = '<div class="text-muted small text-center py-4">No process steps.</div>';
            return;
        }
        items.forEach(function (it, idx) {
            var row = document.createElement('div');
            var side = idx % 2 === 0 ? 'mp-ot-step--right' : 'mp-ot-step--left';
            row.className = 'mp-ot-step ' + side;
            var st = it.state || 'pending';
            var nodeClass = 'mp-ot-step-node';
            if (st === 'pending') nodeClass += ' mp-ot-step-node--pending';
            var meta = document.createElement('div');
            meta.className = 'mp-ot-step-meta';
            meta.innerHTML = mpOtMetaHtml(it);
            var node = document.createElement('div');
            node.className = nodeClass;
            node.innerHTML = mpOtNodeIcon(st);
            var card = document.createElement('div');
            card.className = 'mp-ot-step-card';
            var titleTxt = it.title ? String(it.title) : '';
            var jqCard = it.job_queue_no != null ? String(it.job_queue_no) : '';
            if (jqCard !== '' && jqCard !== 'NA') {
                card.innerHTML = '<span>' + mpOtEsc(titleTxt) + '</span><span class="mp-ot-step-card-qno">' + mpOtEsc(jqCard) + '</span>';
            } else {
                card.textContent = titleTxt;
            }
            if (side === 'mp-ot-step--right') {
                row.appendChild(meta);
                row.appendChild(node);
                row.appendChild(card);
            } else {
                row.appendChild(card);
                row.appendChild(node);
                row.appendChild(meta);
            }
            el.appendChild(row);
        });
    }

    function mpOtRenderTimeline(items) {
        var el = document.getElementById('mpOtTimeline');
        var v2 = document.getElementById('mpOtTimelineV2');
        if (!el) return;
        el.innerHTML = '';
        if (v2) v2.innerHTML = '';
        if (!items || !items.length) {
            if (v2) v2.innerHTML = '<div class="text-muted small text-center py-4">No tracking history.</div>';
            return;
        }
        el.style.display = 'flex';
        items.forEach(function (it) {
            if (it.kind === 'stage') {
                var card = document.createElement('div');
                card.className = 'mp-ot-tl-card';
                card.innerHTML = '<div class="mp-ot-tl-icon"><i class="feather icon-rotate-ccw"></i></div><div class="mp-ot-tl-body"><strong>'
                    + mpOtEsc(it.title) + '</strong>'
                    + (it.subtitle ? '<div class="sub">' + mpOtEsc(it.subtitle) + '</div>' : '')
                    + (it.at ? '<div class="at">' + mpOtEsc(it.at) + '</div>' : '') + '</div>';
                el.appendChild(card);
            } else {
                var row = document.createElement('div');
                row.className = 'mp-ot-tl-row';
                row.innerHTML = '<div class="t">' + mpOtEsc(it.title) + '</div>'
                    + (it.subtitle ? '<div>' + mpOtEsc(it.subtitle) + '</div>' : '')
                    + (it.at ? '<div class="at" style="margin-top:4px;">' + mpOtEsc(it.at) + '</div>' : '');
                el.appendChild(row);
            }
        });
    }

    function mpOtOpen(jwoId) {
        var id = parseInt(jwoId, 10);
        if (isNaN(id) || id < 1) return;
        fetch('ajax/mp-order-tracking.php?jobwork_order_id=' + encodeURIComponent(String(id)), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    alert(data && data.message ? data.message : 'Could not load tracking.');
                    return;
                }
                var t = document.getElementById('mpOrderTrackingTitle');
                if (t) t.textContent = data.title_bar || 'Order Tracking';
                document.getElementById('mpOtSoNo').textContent = data.sale_order.order_no || '—';
                document.getElementById('mpOtSoCust').textContent = data.sale_order.customer_name || '—';
                document.getElementById('mpOtSoOd').textContent = data.sale_order.order_date || '—';
                document.getElementById('mpOtSoDd').textContent = data.sale_order.delivery_date || '—';
                document.getElementById('mpOtSoSt').textContent = data.sale_order.status || '—';
                document.getElementById('mpOtJwoNo').textContent = data.jobwork_order.jobwork_no || '—';
                var jqEl = document.getElementById('mpOtJwoQueueNo');
                if (jqEl) {
                    jqEl.textContent = (data.jobwork_order.jobwork_queue_no != null && String(data.jobwork_order.jobwork_queue_no).trim() !== '')
                        ? String(data.jobwork_order.jobwork_queue_no)
                        : '—';
                }
                document.getElementById('mpOtJwoCust').textContent = data.jobwork_order.customer_name || '—';
                document.getElementById('mpOtJwoAssign').textContent = data.jobwork_order.assign_to || '—';
                document.getElementById('mpOtJwoOd').textContent = data.jobwork_order.order_date || '—';
                document.getElementById('mpOtJwoDd').textContent = data.jobwork_order.delivery_date || '—';
                var imgEl = document.getElementById('mpOtImages');
                if (imgEl) {
                    if (data.images && data.images.length) {
                        imgEl.innerHTML = '';
                        imgEl.style.fontStyle = 'normal';
                        imgEl.style.color = '';
                        data.images.forEach(function (src) {
                            var im = document.createElement('img');
                            im.src = src;
                            im.alt = '';
                            imgEl.appendChild(im);
                        });
                    } else {
                        imgEl.innerHTML = 'No Images To Display !';
                        imgEl.style.fontStyle = 'italic';
                        imgEl.style.color = '#94a3b8';
                    }
                }
                if (data.process_steps && data.process_steps.length) {
                    mpOtRenderProcessSteps(data.process_steps);
                } else if (data.timeline && data.timeline.length) {
                    mpOtRenderTimeline(data.timeline);
                } else {
                    mpOtRenderProcessSteps([]);
                }
                mpOtRenderQueueHistory(data.queue_activity_history || []);
                if (typeof window.jQuery !== 'undefined' && window.jQuery.fn.modal) {
                    window.jQuery('#mpOrderTrackingModal').modal('show');
                }
            })
            .catch(function () {
                alert('Could not load order tracking.');
            });
    }
    window.mpOrderTrackingOpen = mpOtOpen;

    function initMpOrderTracking() {
        var grid = document.getElementById('mpJobCardsGrid');
        if (!grid || grid._mpOtBound) return;
        grid._mpOtBound = true;
        grid.addEventListener('click', function (e) {
            var btn = e.target.closest('.mp-order-tracking-btn');
            if (!btn || !grid.contains(btn)) return;
            e.preventDefault();
            e.stopPropagation();
            var jwoId = btn.getAttribute('data-jwo-id');
            mpOtOpen(jwoId);
        });
    }

    document.addEventListener('DOMContentLoaded', initMpOrderTracking);
})();
</script>
