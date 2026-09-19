(function (window) {
    'use strict';

    var API = 'ajax/employee-management.php';

    function qs(sel, root) { return (root || document).querySelector(sel); }
    function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    function getModalAlertTarget(fallbackEl) {
        var backdrop = document.querySelector('.em-modal-backdrop.show');
        if (!backdrop) {
            return fallbackEl;
        }
        var modal = backdrop.querySelector('.em-modal');
        if (!modal) {
            return fallbackEl;
        }
        var alertEl = modal.querySelector('.em-modal-alert');
        if (!alertEl) {
            alertEl = document.createElement('div');
            alertEl.className = 'em-alert em-modal-alert';
            alertEl.setAttribute('role', 'alert');
            var body = modal.querySelector('.em-modal-body');
            if (body) {
                body.insertBefore(alertEl, body.firstChild);
            } else {
                modal.insertBefore(alertEl, modal.firstChild);
            }
        }
        return alertEl;
    }

    function showAlert(el, msg, ok) {
        el = getModalAlertTarget(el);
        if (!el) return;
        el.textContent = msg || '';
        el.className = 'em-alert em-modal-alert show ' + (ok ? 'em-alert-success' : 'em-alert-error');
        if (typeof el.scrollIntoView === 'function') {
            el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
        setTimeout(function () { el.classList.remove('show'); }, 4000);
    }

    function post(action, data, isFormData) {
        var body = data || {};
        if (!isFormData) {
            body.action = action;
            body = new URLSearchParams(body);
        } else {
            body.append('action', action);
        }
        return fetch(API, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    function get(params) {
        var q = new URLSearchParams(params || {});
        return fetch(API + '?' + q.toString(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    }

    function openModal(id) {
        var m = document.getElementById(id);
        if (m) m.classList.add('show');
    }
    function closeModal(id) {
        var m = document.getElementById(id);
        if (m) m.classList.remove('show');
    }

    function bindModalClose() {
        qsa('.em-modal-backdrop').forEach(function (backdrop) {
            backdrop.addEventListener('click', function (e) {
                if (e.target === backdrop || e.target.closest('.em-close')) {
                    backdrop.classList.remove('show');
                }
            });
        });
    }

    function bindTabs(root) {
        qsa('.em-tabs .em-tab', root).forEach(function (tab) {
            tab.addEventListener('click', function () {
                var group = tab.closest('.em-tabs-group');
                if (!group) return;
                qsa('.em-tab', group).forEach(function (t) { t.classList.remove('active'); });
                qsa('.em-tab-panel', group).forEach(function (p) { p.classList.remove('active'); });
                tab.classList.add('active');
                var panel = group.querySelector('#' + tab.getAttribute('data-panel'));
                if (panel) panel.classList.add('active');
            });
        });
    }

    function badgeForStatus(status) {
        var s = String(status || '').toLowerCase();
        if (s === 'active' || s === 'present' || s === 'approved' || s === 'completed' || s === 'paid') return 'em-badge-green';
        if (s === 'pending' || s === 'open' || s === 'draft' || s === 'in progress') return 'em-badge-yellow';
        if (s === 'rejected' || s === 'absent' || s === 'cancelled') return 'em-badge-red';
        return 'em-badge-gray';
    }

    /**
     * Column show/hide + drag-reorder for employee management tables.
     * Table headers/cells must use data-column="key". Optional data-em-col-fixed="1" locks a column.
     */
    function initColumnTable(table, options) {
        options = options || {};
        if (!table || table.getAttribute('data-em-cols-ready') === '1') {
            return;
        }
        var storageKey = options.storageKey
            || table.getAttribute('data-em-col-key')
            || ('em_cols_' + (table.id || 'table'));
        var toolsHost = options.toolsHost
            || (table.closest('.em-table-panel') && table.closest('.em-table-panel').querySelector('.em-table-tools'))
            || null;
        if (!toolsHost) {
            toolsHost = document.createElement('div');
            toolsHost.className = 'em-table-tools';
            var wrap = table.closest('.em-table-wrap') || table.parentNode;
            if (wrap && wrap.parentNode) {
                wrap.parentNode.insertBefore(toolsHost, wrap);
            }
        }

        var theadRow = table.querySelector('thead tr');
        if (!theadRow) {
            return;
        }

        function getHeaderCells() {
            return Array.prototype.slice.call(theadRow.children).filter(function (th) {
                return th.getAttribute('data-column');
            });
        }

        function loadState() {
            try {
                var raw = localStorage.getItem(storageKey);
                if (!raw) return null;
                var parsed = JSON.parse(raw);
                if (!parsed || typeof parsed !== 'object') return null;
                return parsed;
            } catch (e) {
                return null;
            }
        }

        function saveState(state) {
            try {
                localStorage.setItem(storageKey, JSON.stringify(state));
            } catch (e) { /* ignore */ }
        }

        function currentOrder() {
            return getHeaderCells().map(function (th) {
                return th.getAttribute('data-column');
            });
        }

        function isFixed(th) {
            return th.getAttribute('data-em-col-fixed') === '1'
                || th.getAttribute('data-column') === 'actions';
        }

        function applyVisibility(hiddenMap) {
            getHeaderCells().forEach(function (th) {
                var key = th.getAttribute('data-column');
                var hide = !!(hiddenMap && hiddenMap[key]) && !isFixed(th);
                var idx = Array.prototype.indexOf.call(theadRow.children, th);
                table.querySelectorAll('tr').forEach(function (tr) {
                    var cell = tr.children[idx];
                    if (!cell) return;
                    if (hide) cell.classList.add('em-col-hidden');
                    else cell.classList.remove('em-col-hidden');
                });
            });
        }

        function moveColumn(fromIndex, toIndex) {
            if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0) return;
            Array.prototype.forEach.call(table.rows, function (tr) {
                var cells = tr.cells;
                if (!cells || fromIndex >= cells.length || toIndex >= cells.length) return;
                var moved = cells[fromIndex];
                if (fromIndex < toIndex) {
                    tr.insertBefore(moved, cells[toIndex].nextSibling);
                } else {
                    tr.insertBefore(moved, cells[toIndex]);
                }
            });
        }

        function pinFixedColumnsLast() {
            getHeaderCells().forEach(function (th) {
                if (!isFixed(th)) return;
                var idx = Array.prototype.indexOf.call(theadRow.children, th);
                var last = theadRow.children.length - 1;
                if (idx >= 0 && idx !== last) {
                    moveColumn(idx, last);
                }
            });
        }

        function applyOrder(order) {
            if (!Array.isArray(order) || !order.length) return;
            var desired = order.slice();
            currentOrder().forEach(function (key) {
                if (desired.indexOf(key) === -1) desired.push(key);
            });
            desired.forEach(function (key, targetIdx) {
                var th = theadRow.querySelector('[data-column="' + key + '"]');
                if (!th) return;
                var curIdx = Array.prototype.indexOf.call(theadRow.children, th);
                if (curIdx >= 0 && curIdx !== targetIdx) {
                    moveColumn(curIdx, targetIdx);
                }
            });
            pinFixedColumnsLast();
        }

        var state = loadState() || { order: currentOrder(), hidden: {} };
        if (Array.isArray(state.order) && state.order.length) {
            applyOrder(state.order);
        }
        applyVisibility(state.hidden || {});

        // Settings dropdown
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'em-col-settings-btn';
        btn.title = 'Show / hide columns';
        btn.setAttribute('aria-label', 'Show / hide columns');
        btn.innerHTML = '<i class="feather icon-settings"></i>';

        var dropdown = document.createElement('div');
        dropdown.className = 'em-col-settings-dropdown';
        dropdown.innerHTML = '<div class="em-col-settings-title">Columns</div><div class="em-col-settings-list"></div>'
            + '<p class="em-col-settings-hint">Use the move icon on headers to drag and reorder. Preferences are saved on this browser.</p>';

        toolsHost.appendChild(btn);
        toolsHost.appendChild(dropdown);

        function rebuildChecklist() {
            var list = dropdown.querySelector('.em-col-settings-list');
            if (!list) return;
            list.innerHTML = '';
            getHeaderCells().forEach(function (th) {
                var key = th.getAttribute('data-column');
                var inner = th.querySelector('.em-col-head-inner');
                var label = ((inner && inner.textContent) || th.textContent || key || '').trim();
                var fixed = isFixed(th);
                var item = document.createElement('label');
                item.className = 'em-col-settings-item';
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.checked = !(state.hidden && state.hidden[key]);
                cb.disabled = fixed;
                cb.addEventListener('change', function () {
                    if (!state.hidden) state.hidden = {};
                    if (cb.checked) delete state.hidden[key];
                    else state.hidden[key] = true;
                    state.order = currentOrder();
                    saveState(state);
                    applyVisibility(state.hidden);
                });
                item.appendChild(cb);
                item.appendChild(document.createTextNode(' ' + label));
                list.appendChild(item);
            });
        }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = dropdown.classList.contains('open');
            qsa('.em-col-settings-dropdown.open').forEach(function (d) {
                d.classList.remove('open');
            });
            if (!open) {
                rebuildChecklist();
                dropdown.classList.add('open');
            }
        });

        document.addEventListener('click', function (e) {
            if (!dropdown.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
                dropdown.classList.remove('open');
            }
        });

        // Drag reorder via gold move handle (same pattern as ageing-report)
        getHeaderCells().forEach(function (th) {
            if (isFixed(th)) return;
            var handle = th.querySelector('.em-col-drag');
            if (!handle) return;

            function clearDropHighlights() {
                getHeaderCells().forEach(function (h) { h.classList.remove('em-col-drag-over'); });
            }

            function thFromPoint(clientX, clientY) {
                var el = document.elementFromPoint(clientX, clientY);
                if (!el || !el.closest) return null;
                return el.closest('th[data-column]') || null;
            }

            handle.addEventListener('pointerdown', function (e) {
                if (e.button !== 0) return;
                var dragFromKey = th.getAttribute('data-column');
                if (!dragFromKey) return;
                e.preventDefault();
                th.classList.add('em-col-dragging');
                try {
                    handle.setPointerCapture(e.pointerId);
                } catch (errCap) { /* ignore */ }

                function onMove(ev) {
                    clearDropHighlights();
                    var over = thFromPoint(ev.clientX, ev.clientY);
                    if (over && theadRow.contains(over) && over.getAttribute('data-column') !== dragFromKey && !isFixed(over)) {
                        over.classList.add('em-col-drag-over');
                    }
                }

                function onEnd(ev) {
                    th.classList.remove('em-col-dragging');
                    clearDropHighlights();
                    try {
                        handle.releasePointerCapture(ev.pointerId);
                    } catch (errRel) { /* ignore */ }
                    handle.removeEventListener('pointermove', onMove);
                    handle.removeEventListener('pointerup', onEnd);
                    handle.removeEventListener('pointercancel', onEnd);

                    var over = thFromPoint(ev.clientX, ev.clientY);
                    if (!over || !theadRow.contains(over) || isFixed(over)) return;
                    var toKey = over.getAttribute('data-column');
                    if (!toKey || toKey === dragFromKey) return;

                    var fromIdx = -1;
                    var toIdx = -1;
                    Array.prototype.forEach.call(theadRow.children, function (h, idx) {
                        var k = h.getAttribute('data-column');
                        if (k === dragFromKey) fromIdx = idx;
                        if (k === toKey) toIdx = idx;
                    });
                    if (fromIdx < 0 || toIdx < 0) return;
                    moveColumn(fromIdx, toIdx);
                    pinFixedColumnsLast();
                    state.order = currentOrder();
                    saveState(state);
                    rebuildChecklist();
                }

                handle.addEventListener('pointermove', onMove);
                handle.addEventListener('pointerup', onEnd);
                handle.addEventListener('pointercancel', onEnd);
            });
        });

        table.setAttribute('data-em-cols-ready', '1');
    }

    window.EmApp = {
        API: API,
        qs: qs,
        qsa: qsa,
        showAlert: showAlert,
        post: post,
        get: get,
        openModal: openModal,
        closeModal: closeModal,
        bindModalClose: bindModalClose,
        bindTabs: bindTabs,
        badgeForStatus: badgeForStatus,
        initColumnTable: initColumnTable,
        reload: function () { window.location.reload(); }
    };

    document.addEventListener('DOMContentLoaded', function () {
        bindModalClose();
        bindTabs(document);
        qsa('table.em-table[data-em-col-key]').forEach(function (table) {
            initColumnTable(table);
        });
    });
})(window);
