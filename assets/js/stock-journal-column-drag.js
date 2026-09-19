/**
 * Sale-invoice style column drag: SortableJS on row-1 group headers + mousedown drag within group on row-2.
 */
(function (global) {
    'use strict';

    function abortPendingStockJournalColumnOrderPrefLoads() {
        if (global._sjColOrderPrefPrimaryXhr) {
            try { global._sjColOrderPrefPrimaryXhr.abort(); } catch (eAb) {}
            global._sjColOrderPrefPrimaryXhr = null;
        }
        if (global._sjColOrderPrefFallbackXhr) {
            try { global._sjColOrderPrefFallbackXhr.abort(); } catch (eAb2) {}
            global._sjColOrderPrefFallbackXhr = null;
        }
    }

    /** Fixed tail: canonical order for normalize + tbody reinsert (must match skipDragColumnKeys). */
    var STOCK_JOURNAL_FIXED_TAIL_ORDER = ['net-amt-tax', 'reverse', 'images', 'action', 'actions'];

    var LS_COL_ORDER_PREFIX = 'sj-col-order-v1:';

    function stockJournalColOrderLsKey(pageName, tabKey) {
        return LS_COL_ORDER_PREFIX + String(pageName || '') + ':' + String(tabKey != null ? tabKey : 'main');
    }

    function readStockJournalColOrderFromLocalStorage(pageName, tabKey) {
        try {
            var raw = global.localStorage.getItem(stockJournalColOrderLsKey(pageName, tabKey));
            if (!raw) return null;
            var o = JSON.parse(raw);
            return Array.isArray(o) ? o : null;
        } catch (e) {
            return null;
        }
    }

    function writeStockJournalColOrderToLocalStorage(pageName, tabKey, order) {
        if (!order || !order.length) return;
        try {
            global.localStorage.setItem(stockJournalColOrderLsKey(pageName, tabKey), JSON.stringify(order));
        } catch (e) {}
    }

    /** Remove every floating “Moving column” chip (survives missed mouseup / multiple table inits). */
    function removeAllStockJournalColDragGhosts() {
        try {
            document.querySelectorAll('.sj-modal-col-drag-ghost').forEach(function (node) {
                if (node && node.parentNode) node.parentNode.removeChild(node);
            });
        } catch (eRm) {}
    }

    /**
     * Hallmark and Net Amt+Tax / Reverse are different groups; hallmark columns must always sit immediately before net-reverse columns.
     */
    function enforceHallmarkGroupBeforeNetReverseGroup(orderedKeys, columnGroups) {
        if (!orderedKeys || !orderedKeys.length || !columnGroups) return orderedKeys;
        var hCols = columnGroups.hallmark;
        var nCols = columnGroups['net-reverse'];
        if (!hCols || !hCols.length || !nCols || !nCols.length) return orderedKeys;
        function inH(k) { return hCols.indexOf(k) !== -1; }
        function inN(k) { return nCols.indexOf(k) !== -1; }
        var firstH = -1;
        var firstN = -1;
        var i;
        for (i = 0; i < orderedKeys.length; i++) {
            if (firstH < 0 && inH(orderedKeys[i])) firstH = i;
            if (firstN < 0 && inN(orderedKeys[i])) firstN = i;
        }
        if (firstH < 0 || firstN < 0 || firstH < firstN) return orderedKeys;
        var hKeys = orderedKeys.filter(inH);
        var withoutH = orderedKeys.filter(function (k) { return !inH(k); });
        var insertAt = -1;
        for (i = 0; i < withoutH.length; i++) {
            if (inN(withoutH[i])) {
                insertAt = i;
                break;
            }
        }
        if (insertAt < 0) return orderedKeys;
        return withoutH.slice(0, insertAt).concat(hKeys).concat(withoutH.slice(insertAt));
    }

    function normalizeGroupOrderHallmarkBeforeNetReverse(groupOrder) {
        if (!groupOrder || !groupOrder.length) return groupOrder;
        var hi = groupOrder.indexOf('hallmark');
        var ni = groupOrder.indexOf('net-reverse');
        if (hi < 0 || ni < 0 || hi < ni) return groupOrder.slice();
        var out = groupOrder.slice();
        out.splice(hi, 1);
        ni = out.indexOf('net-reverse');
        if (ni < 0) return groupOrder.slice();
        out.splice(ni, 0, 'hallmark');
        return out;
    }

    function sanitizeColumnOrderToRespectGroups(orderedKeys, columnGroups) {
        if (!orderedKeys || !orderedKeys.length) return orderedKeys;
        if (!columnGroups) return orderedKeys;
        var columnToGroup = {};
        Object.keys(columnGroups).forEach(function (gk) {
            var cols = columnGroups[gk];
            if (Array.isArray(cols)) cols.forEach(function (c) { columnToGroup[c] = gk; });
        });
        var firstIndex = {};
        orderedKeys.forEach(function (k, idx) {
            var gk = columnToGroup[k];
            if (gk && firstIndex[gk] === undefined) firstIndex[gk] = idx;
        });
        var groupOrder = Object.keys(columnGroups).filter(function (gk) {
            return firstIndex[gk] !== undefined;
        }).sort(function (a, b) {
            return (firstIndex[a] || 0) - (firstIndex[b] || 0);
        });
        groupOrder = normalizeGroupOrderHallmarkBeforeNetReverse(groupOrder);
        var columnsByGroup = {};
        Object.keys(columnGroups).forEach(function (gk) { columnsByGroup[gk] = []; });
        orderedKeys.forEach(function (k) {
            var gk = columnToGroup[k];
            if (gk) columnsByGroup[gk].push(k);
        });
        var sanitized = [];
        groupOrder.forEach(function (gk) {
            sanitized = sanitized.concat(columnsByGroup[gk]);
        });
        orderedKeys.forEach(function (k) {
            if (!columnToGroup[k] && sanitized.indexOf(k) === -1) sanitized.push(k);
        });
        sanitized = enforceHallmarkGroupBeforeNetReverseGroup(sanitized, columnGroups);
        return sanitized.length ? sanitized : orderedKeys;
    }

    function getCanonicalFixedEndOrderForDrag(skipDragColumnKeys, tailColumnKeys) {
        var skip = skipDragColumnKeys || [];
        var tail = tailColumnKeys || [];
        var out = [];
        tail.forEach(function (k) {
            if (k && skip.indexOf(k) !== -1 && out.indexOf(k) === -1) out.push(k);
        });
        STOCK_JOURNAL_FIXED_TAIL_ORDER.forEach(function (k) {
            if (skip.indexOf(k) !== -1 && out.indexOf(k) === -1) out.push(k);
        });
        skip.forEach(function (k) {
            if (out.indexOf(k) === -1) out.push(k);
        });
        return out;
    }

    /** Strip fixed keys from the middle, then append only fixed columns that exist in headerMap (canonical tail order). */
    function forceAppendFixedColumnsToOrder(order, headerMap, skipDragColumnKeys, tailColumnKeys) {
        if (!order || !order.length) return order;
        if (!headerMap) return order;
        var skip = skipDragColumnKeys || [];
        var fixedSet = {};
        skip.forEach(function (k) { fixedSet[k] = true; });
        var mid = [];
        order.forEach(function (k) {
            if (!fixedSet[k]) mid.push(k);
        });
        var tail = [];
        getCanonicalFixedEndOrderForDrag(skip, tailColumnKeys).forEach(function (k) {
            if (headerMap[k]) tail.push(k);
        });
        return mid.concat(tail);
    }

    function normalizeOrderWithFixedTailGlobal(order, skipDragColumnKeys, tailColumnKeys) {
        if (!order || !order.length) return order;
        var skip = skipDragColumnKeys || [];
        var fixedSet = {};
        skip.forEach(function (k) { fixedSet[k] = true; });
        var draggable = [];
        order.forEach(function (k) {
            if (!fixedSet[k]) draggable.push(k);
        });
        var fixedTail = [];
        var seen = {};
        getCanonicalFixedEndOrderForDrag(skip, tailColumnKeys).forEach(function (k) {
            if (fixedSet[k] && order.indexOf(k) !== -1) {
                fixedTail.push(k);
                seen[k] = true;
            }
        });
        skip.forEach(function (k) {
            if (order.indexOf(k) !== -1 && !seen[k]) {
                fixedTail.push(k);
                seen[k] = true;
            }
        });
        return draggable.concat(fixedTail);
    }

    function getDomDataColumnOrder(headerRow2, table, skipDragColumnKeys, tailColumnKeys) {
        var order = [];
        if (!headerRow2) return order;
        headerRow2.querySelectorAll('th[data-column]').forEach(function (th) {
            var k = th.getAttribute('data-column');
            if (k && skipDragColumnKeys.indexOf(k) === -1) order.push(k);
        });
        (tailColumnKeys || []).forEach(function (k) {
            var esc = String(k).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
            if (table && table.querySelector('thead [data-column="' + esc + '"]') && order.indexOf(k) === -1) {
                order.push(k);
            }
        });
        return order;
    }

    function isHeaderCellVisible(th) {
        if (!th || th.classList.contains('hidden')) return false;
        var cs = global.getComputedStyle(th);
        return cs.display !== 'none' && cs.visibility !== 'collapse';
    }

    function isBodyCellVisible(td) {
        if (!td || td.classList.contains('hidden')) return false;
        var cs = global.getComputedStyle(td);
        return cs.display !== 'none' && cs.visibility !== 'collapse';
    }

    /** data-group on row-2 headers + tbody td; .group-end for last visible column per group */
    function stampDataGroupAndMarkGroupEnd(table, headerRow2, columnGroups) {
        if (!table || !headerRow2 || !columnGroups) return;
        var colToGroup = {};
        Object.keys(columnGroups).forEach(function (gk) {
            (columnGroups[gk] || []).forEach(function (c) { colToGroup[c] = gk; });
        });
        headerRow2.querySelectorAll('th[data-column]').forEach(function (th) {
            var c = th.getAttribute('data-column');
            if (c && colToGroup[c]) th.setAttribute('data-group', colToGroup[c]);
            else th.removeAttribute('data-group');
        });
        table.querySelectorAll('.group-end').forEach(function (el) { el.classList.remove('group-end'); });
        var ordered = [];
        headerRow2.querySelectorAll('th[data-column]').forEach(function (th) {
            var colKey = th.getAttribute('data-column');
            if (!colKey || colKey === 'actions' || colKey === 'action') return;
            if (!isHeaderCellVisible(th)) return;
            var g = th.getAttribute('data-group');
            if (!g) return;
            ordered.push({ colKey: colKey, group: g });
        });
        for (var i = 0; i < ordered.length; i++) {
            var cur = ordered[i];
            var next = ordered[i + 1];
            if (!next || next.group !== cur.group) {
                var th = headerRow2.querySelector('th[data-column="' + String(cur.colKey).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]');
                if (th) th.classList.add('group-end');
                var esc = String(cur.colKey).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
                table.querySelectorAll('tbody td[data-column="' + esc + '"]').forEach(function (td) {
                    if (isBodyCellVisible(td)) td.classList.add('group-end');
                });
            }
        }
    }

    function initStockJournalColumnDrag(opts) {
        var table = opts.table;
        var tbody = opts.tbody;
        if (!table || !tbody) return null;

        var pageName = opts.pageName || 'stock-journal-column-order';
        var tabKey = opts.tabKey != null ? opts.tabKey : 'main';
        var getTabKeyOpt = typeof opts.getTabKey === 'function' ? opts.getTabKey : null;
        function resolveTabKeyForSave() {
            if (getTabKeyOpt) return String(getTabKeyOpt());
            return tabKey != null ? String(tabKey) : 'main';
        }
        var tailColumnKeys = opts.tailColumnKeys || [];
        var anchorColumnKey = opts.anchorColumnKey === false ? null : (opts.anchorColumnKey || 'net-amt-tax');
        var row1Selector = opts.row1Selector || 'thead tr:first-child';
        var row2Selector = opts.row2Selector || 'thead tr:nth-child(2)';
        /** Sticky / summary columns: never reorder with groups; always at end (canonical order below). */
        var skipDragColumnKeys = opts.skipDragColumnKeys || STOCK_JOURNAL_FIXED_TAIL_ORDER.slice();
        var afterApply = typeof opts.afterApply === 'function' ? opts.afterApply : null;
        var syncGlobalLayout = opts.syncGlobalProductModalLayout !== false;

        var headerRow1 = table.querySelector(row1Selector);
        var headerRow2 = table.querySelector(row2Selector);
        if (!headerRow1 || !headerRow2) return null;

        function getColumnGroups() {
            return opts.columnGroups || global.PRODUCT_MODAL_COLUMN_GROUPS || {};
        }

        /** Resolve group id from PRODUCT_MODAL_COLUMN_GROUPS config only (not DOM order). */
        function getGroupForColumn(colKey) {
            if (!colKey) return null;
            var columnGroups = getColumnGroups();
            var gks = Object.keys(columnGroups);
            for (var i = 0; i < gks.length; i++) {
                var gk = gks[i];
                if ((columnGroups[gk] || []).indexOf(colKey) !== -1) return gk;
            }
            return null;
        }

        /**
         * Column order within a group: use columnGroups config array order, not thead row-2 DOM order.
         * Caller filters keys against headerRow2 / headerMap so only existing columns apply.
         */
        function getColumnsInGroupFromConfig(groupKey) {
            var columnGroups = getColumnGroups();
            return (columnGroups[groupKey] || []).slice();
        }

        /** Build map th[data-column] -> element from header row 2 only. */
        function buildHeaderDataColumnMap() {
            var headerMap = {};
            headerRow2.querySelectorAll('th[data-column]').forEach(function (th) {
                var k = th.getAttribute('data-column');
                if (k) headerMap[k] = th;
            });
            return headerMap;
        }

        /** Build map td[data-column] -> element for one body row. */
        function buildRowDataColumnMap(row) {
            var map = {};
            row.querySelectorAll('td[data-column]').forEach(function (td) {
                var k = td.getAttribute('data-column');
                if (k) map[k] = td;
            });
            return map;
        }

        function normalizeOrderWithFixedTail(order) {
            return normalizeOrderWithFixedTailGlobal(order, skipDragColumnKeys, tailColumnKeys);
        }

        function getModalColHeaders() {
            return headerRow2.querySelectorAll('th[data-column]');
        }

        function getModalCurrentColumnOrder() {
            return getDomDataColumnOrder(headerRow2, table, skipDragColumnKeys, tailColumnKeys);
        }

        function clearModalColHighlight() {
            getModalColHeaders().forEach(function (h) {
                h.classList.remove('modal-col-dragging', 'modal-col-drag-over-left', 'modal-col-drag-over-right');
            });
        }

        function saveModalColumnOrder() {
            var order = getModalCurrentColumnOrder();
            if (!order.length) return;
            var tabResolved = resolveTabKeyForSave();
            writeStockJournalColOrderToLocalStorage(pageName, tabResolved, order);
            if (typeof jQuery === 'undefined') return;
            var prefs = {};
            order.forEach(function (k) { prefs[k] = 1; });
            jQuery.ajax({
                url: 'ajax/save-product-modal-column-preferences.php',
                type: 'POST',
                data: {
                    page_name: pageName,
                    tab_key: tabResolved,
                    preferences: JSON.stringify(prefs),
                    order_keys: JSON.stringify(order)
                },
                dataType: 'json'
            }).done(function (res) {
                if (res && res.status === 'error' && typeof console !== 'undefined') {
                    console.warn('save column order (saved in this browser; server:', res.message + ')');
                }
            }).fail(function (xhr) {
                if (typeof console !== 'undefined') {
                    console.warn('save column order HTTP error (order kept in this browser)', xhr.status);
                }
            });
        }

        function getAnchorTh() {
            // Anchor is used with headerRow1.insertBefore(): it must be a row-1 cell.
            // anchorColumnKey (e.g. net-amt-tax) may only exist in row 2; fall back to
            // the locked group header (net-reverse) in row 1 in that case.
            if (anchorColumnKey) {
                var esc = String(anchorColumnKey).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
                var a = headerRow1.querySelector('th[data-column="' + esc + '"]');
                if (a) return a;
            }
            return headerRow1.querySelector('th[data-group-locked]') || null;
        }

        function runAfterStamp() {
            stampDataGroupAndMarkGroupEnd(table, headerRow2, getColumnGroups());
            if (afterApply) afterApply(table);
        }

        function syncGroupHeaderOrderToColumnOrder(orderedKeys) {
            if (!headerRow1 || !orderedKeys || !orderedKeys.length) return;
            var columnGroups = getColumnGroups();
            var columnToGroup = {};
            Object.keys(columnGroups).forEach(function (gk) {
                var cols = columnGroups[gk];
                if (Array.isArray(cols)) cols.forEach(function (c) { columnToGroup[c] = gk; });
            });
            var firstIndex = {};
            orderedKeys.forEach(function (k, idx) {
                var gk = columnToGroup[k];
                if (gk && firstIndex[gk] === undefined) firstIndex[gk] = idx;
            });
            var groupOrder = Object.keys(columnGroups).filter(function (gk) {
                return firstIndex[gk] !== undefined;
            }).sort(function (a, b) {
                return (firstIndex[a] || 0) - (firstIndex[b] || 0);
            });
            var groupMap = {};
            headerRow1.querySelectorAll('th[data-group]').forEach(function (gh) {
                var gk = gh.getAttribute('data-group');
                if (gk) groupMap[gk] = gh;
            });
            var anchorTh = getAnchorTh();
            groupOrder.forEach(function (gk) {
                var gh = groupMap[gk];
                if (!gh) return;
                // Never insertBefore a node that is not a child of headerRow1, and never
                // insert a group before itself (locked net-reverse is often the anchor).
                if (anchorTh && anchorTh.parentNode === headerRow1 && gh !== anchorTh) {
                    headerRow1.insertBefore(gh, anchorTh);
                } else if (gh.parentNode !== headerRow1 || !anchorTh) {
                    headerRow1.appendChild(gh);
                }
            });
            Object.keys(groupMap).forEach(function (gk) {
                if (groupOrder.indexOf(gk) !== -1) return;
                var gh = groupMap[gk];
                if (!gh) return;
                if (anchorTh && anchorTh.parentNode === headerRow1 && gh !== anchorTh) {
                    headerRow1.insertBefore(gh, anchorTh);
                }
            });
            if (syncGlobalLayout) {
                if (typeof global.syncProductModalColumnLayoutAfterToggle === 'function') {
                    global.syncProductModalColumnLayoutAfterToggle();
                } else if (typeof global.updateGroupHeaderVisibility === 'function') {
                    global.updateGroupHeaderVisibility();
                }
            }
            runAfterStamp();
        }

        /**
         * Detach all td[data-column], reinsert in finalOrder before one stable ref (no last-non-data anchor).
         * insertBeforeRef = first node after the contiguous data-column block (found before removal), so scrollbar/hidden
         * cells after that block do not shift insertion point.
         */
        function reorderRowBodyCellsByDataColumnKeys(row, orderedKeysFull, cellMap) {
            void cellMap;

            var firstDataCell = row.querySelector('td[data-column]');
            var insertBeforeRef = null;
            if (firstDataCell) {
                var walk = firstDataCell.nextSibling;
                while (walk) {
                    if (walk.nodeType === 1 && walk.tagName === 'TD' && walk.getAttribute('data-column')) {
                        walk = walk.nextSibling;
                        continue;
                    }
                    insertBeforeRef = walk;
                    break;
                }
            }

            var dataCells = {};
            row.querySelectorAll('td[data-column]').forEach(function (td) {
                var key = td.getAttribute('data-column');
                if (key) {
                    dataCells[key] = td;
                    row.removeChild(td);
                }
            });

            var fixedEnd = getCanonicalFixedEndOrderForDrag(skipDragColumnKeys, tailColumnKeys);
            var fixedSet = {};
            fixedEnd.forEach(function (k) { fixedSet[k] = true; });

            var finalOrder = [];
            orderedKeysFull.forEach(function (k) {
                if (!fixedSet[k] && dataCells[k]) {
                    finalOrder.push(k);
                }
            });
            fixedEnd.forEach(function (k) {
                if (dataCells[k]) {
                    finalOrder.push(k);
                }
            });

            // Checkbox (and similar) may live only in thead row 1 (rowspan), not row 2 — was omitted from
            // orderedKeysFull and dropped here, shifting every body column left. Prepend if still present.
            if (dataCells.checkbox && finalOrder.indexOf('checkbox') === -1) {
                finalOrder.unshift('checkbox');
            }

            var placed = {};
            finalOrder.forEach(function (k) {
                if (placed[k]) return;
                var td = dataCells[k];
                if (!td) return;
                row.insertBefore(td, insertBeforeRef);
                placed[k] = true;
            });
        }

        function applySavedModalColumnOrder(orderedKeys) {
            if (!orderedKeys || !orderedKeys.length) return;
            var columnGroups = getColumnGroups();
            orderedKeys = sanitizeColumnOrderToRespectGroups(orderedKeys, columnGroups);
            orderedKeys = normalizeOrderWithFixedTail(orderedKeys);
            var headerMap = buildHeaderDataColumnMap();
            orderedKeys = forceAppendFixedColumnsToOrder(orderedKeys, headerMap, skipDragColumnKeys, tailColumnKeys);
            orderedKeys.forEach(function (k) {
                if (headerMap[k]) headerRow2.appendChild(headerMap[k]);
            });

            var orderedKeysFull = orderedKeys.slice();
            var bodyRows = tbody.querySelectorAll('tr');
            bodyRows.forEach(function (row) {
                var cellMap = buildRowDataColumnMap(row);
                reorderRowBodyCellsByDataColumnKeys(row, orderedKeysFull, cellMap);
            });
            setTimeout(function () {
                if (typeof global.clampProductModalScroll === 'function') {
                    global.clampProductModalScroll();
                }
            }, 0);
            syncGroupHeaderOrderToColumnOrder(orderedKeys);
            refreshProductModalGroupSortable();
        }

        /**
         * Reorder one column within its group using data-column keys (not header indices).
         * Index-based reorder desynced tbody from thead after group drag / rowspan layout.
         */
        function reorderModalColumnsByKeys(dragTh, dropTh, insertAfterDrop) {
            if (!dragTh || !dropTh || dragTh === dropTh) return;
            var dk = dragTh.getAttribute('data-column');
            var sk = dropTh.getAttribute('data-column');
            if (!dk || !sk || skipDragColumnKeys.indexOf(dk) !== -1) return;
            var order = getModalCurrentColumnOrder();
            var di = order.indexOf(dk);
            if (di < 0) return;
            order.splice(di, 1);
            var dj = order.indexOf(sk);
            if (dj < 0) return;
            var insertAt = insertAfterDrop ? dj + 1 : dj;
            order.splice(insertAt, 0, dk);
            order = normalizeOrderWithFixedTail(sanitizeColumnOrderToRespectGroups(order, getColumnGroups()));
            applySavedModalColumnOrder(order);
            saveModalColumnOrder();
            if (typeof global.clampProductModalScroll === 'function') {
                setTimeout(function () { global.clampProductModalScroll(); }, 0);
            }
            if (syncGlobalLayout && typeof global.syncProductModalColumnLayoutAfterToggle === 'function') {
                global.syncProductModalColumnLayoutAfterToggle();
            } else runAfterStamp();
        }

        function reorderModalColumnsByGroupOrder(newGroupOrder) {
            var headerMap = buildHeaderDataColumnMap();
            var columnGroups = getColumnGroups();
            /** Per-group column keys in current DOM order (user sub-column order must survive group moves). */
            var byGroup = {};
            Object.keys(columnGroups).forEach(function (gk) {
                byGroup[gk] = [];
            });
            getModalCurrentColumnOrder().forEach(function (k) {
                if (!k || skipDragColumnKeys.indexOf(k) !== -1) return;
                var gk = getGroupForColumn(k);
                if (gk && byGroup[gk]) byGroup[gk].push(k);
            });
            var newColumnOrder = [];
            newGroupOrder.forEach(function (gk) {
                (byGroup[gk] || []).forEach(function (c) {
                    if (headerMap[c] && skipDragColumnKeys.indexOf(c) === -1 && newColumnOrder.indexOf(c) === -1) {
                        newColumnOrder.push(c);
                    }
                });
                getColumnsInGroupFromConfig(gk).forEach(function (c) {
                    if (headerMap[c] && skipDragColumnKeys.indexOf(c) === -1 && newColumnOrder.indexOf(c) === -1) {
                        newColumnOrder.push(c);
                    }
                });
            });
            var extra = [];
            headerRow2.querySelectorAll('th[data-column]').forEach(function (th) {
                var k = th.getAttribute('data-column');
                if (k && skipDragColumnKeys.indexOf(k) === -1 && newColumnOrder.indexOf(k) === -1 && headerMap[k]) {
                    extra.push(k);
                }
            });
            newColumnOrder = newColumnOrder.concat(extra);
            tailColumnKeys.forEach(function (k) {
                if (headerMap[k] && newColumnOrder.indexOf(k) === -1) newColumnOrder.push(k);
            });
            newColumnOrder = normalizeOrderWithFixedTail(newColumnOrder);
            newColumnOrder = forceAppendFixedColumnsToOrder(newColumnOrder, headerMap, skipDragColumnKeys, tailColumnKeys);
            if (newColumnOrder.length) {
                applySavedModalColumnOrder(newColumnOrder);

                setTimeout(function () {
                    applySavedModalColumnOrder(newColumnOrder);

                    if (typeof global.clampProductModalScroll === 'function') {
                        global.clampProductModalScroll();
                    }

                    if (syncGlobalLayout && typeof global.syncProductModalColumnLayoutAfterToggle === 'function') {
                        global.syncProductModalColumnLayoutAfterToggle();
                    }
                }, 50);

                saveModalColumnOrder();
            }
        }

        var modalDraggedTh = null;
        var modalDropTh = null;
        var modalDropRight = false;
        var colDragGhostEl = null;

        function removeColDragGhost() {
            if (colDragGhostEl && colDragGhostEl.parentNode) {
                colDragGhostEl.parentNode.removeChild(colDragGhostEl);
            }
            colDragGhostEl = null;
            removeAllStockJournalColDragGhosts();
        }

        function positionColDragGhost(e) {
            if (!colDragGhostEl || !e) return;
            colDragGhostEl.style.left = (e.clientX + 14) + 'px';
            colDragGhostEl.style.top = (e.clientY + 14) + 'px';
        }

        function showColDragGhost(th, e) {
            removeAllStockJournalColDragGhosts();
            colDragGhostEl = null;
            var g = document.createElement('div');
            g.className = 'sj-modal-col-drag-ghost';
            g.setAttribute('role', 'status');
            g.setAttribute('aria-live', 'polite');
            var badge = document.createElement('span');
            badge.className = 'sj-modal-col-drag-ghost__badge';
            badge.textContent = 'Moving column';
            var nameEl = document.createElement('span');
            nameEl.className = 'sj-modal-col-drag-ghost__name';
            var label = (th.textContent || '').replace(/\s+/g, ' ').trim();
            if (label.length > 48) label = label.slice(0, 46) + '\u2026';
            nameEl.textContent = label || (th.getAttribute('data-column') || '');
            g.appendChild(badge);
            g.appendChild(nameEl);
            g.style.position = 'fixed';
            g.style.zIndex = '10060';
            g.style.pointerEvents = 'none';
            document.body.appendChild(g);
            colDragGhostEl = g;
            positionColDragGhost(e);
        }

        function findHeader2ThFromPoint(el) {
            var n = el;
            while (n && n !== table) {
                if (n.tagName === 'TH' && headerRow2.contains(n) && n.getAttribute('data-column')) {
                    return n;
                }
                n = n.parentElement;
            }
            return null;
        }

        function onModalColMove(e) {
            if (!modalDraggedTh || !headerRow2) return;
            positionColDragGhost(e);
            var th = typeof global.findProductModalRow2DropTh === 'function'
                ? global.findProductModalRow2DropTh(headerRow2, e.clientX, e.clientY)
                : findHeader2ThFromPoint(document.elementFromPoint(e.clientX, e.clientY));
            getModalColHeaders().forEach(function (h) {
                h.classList.remove('modal-col-drag-over-left', 'modal-col-drag-over-right');
            });
            modalDropTh = null;
            if (!th || th === modalDraggedTh) return;
            if (th.classList.contains('hidden')) return;
            var dk = modalDraggedTh.getAttribute('data-column');
            var sk = th.getAttribute('data-column');
            if (skipDragColumnKeys.indexOf(sk) !== -1) return;
            var dragGroup = getGroupForColumn(dk);
            var dropGroup = getGroupForColumn(sk);
            if (dragGroup !== dropGroup) return;
            modalDropTh = th;
            var rect = th.getBoundingClientRect();
            modalDropRight = e.clientX >= rect.left + rect.width / 2;
            if (modalDropRight) th.classList.add('modal-col-drag-over-right');
            else th.classList.add('modal-col-drag-over-left');
        }

        function finishModalColDrag() {
            removeColDragGhost();
            if (modalDraggedTh) {
                modalDraggedTh.classList.remove('modal-col-dragging');
                modalDraggedTh = null;
            }
            modalDropTh = null;
            clearModalColHighlight();
            global.removeEventListener('mousemove', onModalColMove);
            global.removeEventListener('mouseup', onModalColEnd, true);
            global.removeEventListener('pointerup', onModalColEnd, true);
            global.removeEventListener('pointercancel', onModalColEnd, true);
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
        }

        function onModalColEnd() {
            try {
                if (modalDraggedTh && modalDropTh && modalDropTh !== modalDraggedTh) {
                    reorderModalColumnsByKeys(modalDraggedTh, modalDropTh, !!modalDropRight);
                }
            } catch (eOrd) {
                if (typeof console !== 'undefined') {
                    console.warn('stock-journal column reorder', eOrd);
                }
            } finally {
                finishModalColDrag();
            }
        }

        function refreshProductModalGroupSortable() {
            stampDataGroupAndMarkGroupEnd(table, headerRow2, getColumnGroups());
            if (headerRow1._stockJournalGroupSortable && typeof headerRow1._stockJournalGroupSortable.destroy === 'function') {
                try { headerRow1._stockJournalGroupSortable.destroy(); } catch (e1) {}
                headerRow1._stockJournalGroupSortable = null;
            }
            if (typeof Sortable === 'undefined') return;
            headerRow1._stockJournalGroupSortable = new Sortable(headerRow1, {
                animation: 150,
                forceFallback: true,
                fallbackOnBody: true,
                draggable: 'th[data-group]:not([data-group-locked])',
                handle: '.product-modal-group-drag-handle',
                filter: 'input,button,select,textarea,a,.add-category-icon,.add-product-category-icon,.add-product-icon,.add-location-icon',
                preventOnFilter: true,
                ghostClass: 'product-modal-group-sortable-ghost',
                dragClass: 'product-modal-group-sortable-drag-chosen',
                onEnd: function (evt) {
                    if (evt && typeof evt.oldIndex === 'number' && typeof evt.newIndex === 'number' && evt.oldIndex === evt.newIndex) {
                        return;
                    }
                    var order = [];
                    headerRow1.querySelectorAll('th[data-group]').forEach(function (cell) {
                        var g = cell.getAttribute('data-group');
                        if (g) order.push(g);
                    });
                    order = normalizeGroupOrderHallmarkBeforeNetReverse(order);
                    reorderModalColumnsByGroupOrder(order);
                    refreshProductModalGroupSortable();
                }
            });
        }

        var inited = headerRow2.getAttribute('data-sj-column-drag-inited') === '1';
        if (!inited) {
            headerRow2.setAttribute('data-sj-column-drag-inited', '1');
            getModalColHeaders().forEach(function (th) {
                var colKey = th.getAttribute('data-column');
                if (skipDragColumnKeys.indexOf(colKey) !== -1) {
                    th.setAttribute('title', 'Fixed column order');
                    return;
                }
                if (getGroupForColumn(colKey)) {
                    th.setAttribute('title', 'Drag this header to reorder within the group (not the + icons). Drag the group title row to move the whole group.');
                    th.classList.add('column-in-group');
                } else {
                    th.setAttribute('title', 'Drag this header to reorder the column.');
                }
            });
            headerRow2.addEventListener('mousedown', function (e) {
                if (e.button !== 0) return;
                var th = e.target.closest('th[data-column]');
                if (!th || !headerRow2.contains(th)) return;
                var ck = th.getAttribute('data-column');
                if (skipDragColumnKeys.indexOf(ck) !== -1) return;
                if (e.target.closest('.product-modal-col-drag-handle--locked')) return;
                if (e.target.closest('input,button,select,textarea,a,.add-category-icon,.add-product-category-icon,.add-product-icon,.add-location-icon')) return;
                e.preventDefault();
                modalDraggedTh = th;
                th.classList.add('modal-col-dragging');
                showColDragGhost(th, e);
                document.body.style.cursor = 'grabbing';
                document.body.style.userSelect = 'none';
                global.addEventListener('mousemove', onModalColMove);
                global.addEventListener('mouseup', onModalColEnd, true);
                global.addEventListener('pointerup', onModalColEnd, true);
                global.addEventListener('pointercancel', onModalColEnd, true);
            });
        }

        refreshProductModalGroupSortable();

        return {
            applySavedModalColumnOrder: applySavedModalColumnOrder,
            refreshProductModalGroupSortable: refreshProductModalGroupSortable,
            getModalCurrentColumnOrder: getModalCurrentColumnOrder
        };
    }

    global.initStockJournalColumnDrag = initStockJournalColumnDrag;
    global.sanitizeStockJournalColumnOrderForGroups = sanitizeColumnOrderToRespectGroups;

    global.applyStockJournalSavedColumnOrderToAll = function (pairs, config) {
        if (typeof jQuery === 'undefined' || !pairs || !pairs.length) return;
        var pageName = config.pageName || 'stock-journal-column-order';
        var columnGroups = config.columnGroups || {};
        var tailKeys = config.tailColumnKeys || [];
        var anchorKey = config.anchorColumnKey === false ? null : (config.anchorColumnKey || 'net-amt-tax');
        var afterApply = config.afterApply;
        var syncGlobal = config.syncGlobalProductModalLayout !== false;

        var first = pairs[0];
        if (!first || !first.table || !first.tbody) return;

        function resolveConfigTabKey() {
            if (typeof config.getTabKey === 'function') return String(config.getTabKey());
            return config.tabKey != null ? String(config.tabKey) : 'main';
        }

        var skipDrag = config.skipDragColumnKeys || STOCK_JOURNAL_FIXED_TAIL_ORDER.slice();

        function buildInitOpts(p, idx) {
            return {
                table: p.table,
                tbody: p.tbody,
                pageName: pageName,
                tabKey: resolveConfigTabKey(),
                getTabKey: config.getTabKey,
                columnGroups: columnGroups,
                tailColumnKeys: tailKeys,
                anchorColumnKey: anchorKey === null ? false : anchorKey,
                row1Selector: p.row1Selector || config.row1Selector,
                row2Selector: p.row2Selector || config.row2Selector,
                skipDragColumnKeys: skipDrag,
                afterApply: afterApply,
                syncGlobalProductModalLayout: idx === 0 ? syncGlobal : false
            };
        }

        /** Bind mousedown + Sortable immediately so DnD works before preferences XHR returns. */
        var apis = [];
        pairs.forEach(function (p, idx) {
            if (!p.table || !p.tbody) return;
            var api = initStockJournalColumnDrag(buildInitOpts(p, idx));
            if (api) apis.push(api);
        });

        function processColumnPreferencesResponse(res) {
            if (!res || typeof res !== 'object') res = { status: 'success', preferences: [] };
            var domOrder = getDomDataColumnOrder(first.table.querySelector(config.row2Selector || 'thead tr:nth-child(2)'), first.table, skipDrag, tailKeys);
            var sanitized = normalizeOrderWithFixedTailGlobal(domOrder.slice(), skipDrag, tailKeys);
            if (res.status === 'success' && res.preferences && res.preferences.length) {
                var savedOrder = res.preferences.map(function (p) { return p.column_key; });
                var merged = savedOrder.slice();
                domOrder.forEach(function (k) {
                    if (merged.indexOf(k) === -1) merged.push(k);
                });
                sanitized = normalizeOrderWithFixedTailGlobal(
                    sanitizeColumnOrderToRespectGroups(merged, columnGroups),
                    skipDrag,
                    tailKeys
                );
                if (sanitized.join(',') !== merged.join(',')) {
                    var prefs = {};
                    sanitized.forEach(function (k) { prefs[k] = 1; });
                    jQuery.ajax({
                        url: 'ajax/save-product-modal-column-preferences.php',
                        type: 'POST',
                        data: {
                            page_name: pageName,
                            tab_key: resolveConfigTabKey(),
                            preferences: JSON.stringify(prefs),
                            order_keys: JSON.stringify(sanitized)
                        },
                        dataType: 'json'
                    }).done(function (res) {
                        if (res && res.status === 'error' && typeof console !== 'undefined') {
                            console.warn('save column order (sanitized):', res.message);
                        }
                    }).fail(function (xhr) {
                        if (typeof console !== 'undefined') {
                            console.warn('save column order (sanitized) HTTP error', xhr.status, xhr.responseText);
                        }
                    });
                }
            } else {
                var tkLs = resolveConfigTabKey();
                var lsOrder = readStockJournalColOrderFromLocalStorage(pageName, tkLs);
                if ((!lsOrder || !lsOrder.length) && tkLs !== 'main') {
                    lsOrder = readStockJournalColOrderFromLocalStorage(pageName, 'main');
                }
                if (lsOrder && lsOrder.length) {
                    var mergedLs = lsOrder.slice();
                    domOrder.forEach(function (k) {
                        if (mergedLs.indexOf(k) === -1) mergedLs.push(k);
                    });
                    sanitized = normalizeOrderWithFixedTailGlobal(
                        sanitizeColumnOrderToRespectGroups(mergedLs, columnGroups),
                        skipDrag,
                        tailKeys
                    );
                }
            }

            apis.forEach(function (api) {
                if (api && sanitized.length) api.applySavedModalColumnOrder(sanitized);
            });
        }

        abortPendingStockJournalColumnOrderPrefLoads();
        var tabKeyResolved = resolveConfigTabKey();
        global._sjColOrderPrefPrimaryXhr = jQuery.ajax({
            url: 'ajax/get-column-preferences.php',
            type: 'POST',
            data: { page_name: pageName, tab_key: tabKeyResolved },
            dataType: 'json'
        }).done(function (res) {
            global._sjColOrderPrefPrimaryXhr = null;
            if (!res || res.status !== 'success') {
                processColumnPreferencesResponse({ status: 'success', preferences: [] });
                return;
            }
            var hasPrefs = res.preferences && res.preferences.length;
            if (!hasPrefs && tabKeyResolved !== 'main') {
                global._sjColOrderPrefFallbackXhr = jQuery.ajax({
                    url: 'ajax/get-column-preferences.php',
                    type: 'POST',
                    data: { page_name: pageName, tab_key: 'main' },
                    dataType: 'json'
                }).done(function (res2) {
                    global._sjColOrderPrefFallbackXhr = null;
                    if (!res2 || res2.status !== 'success') {
                        processColumnPreferencesResponse({ status: 'success', preferences: [] });
                        return;
                    }
                    processColumnPreferencesResponse(res2);
                }).fail(function (_x, status) {
                    global._sjColOrderPrefFallbackXhr = null;
                    if (status === 'abort') return;
                    processColumnPreferencesResponse({ status: 'success', preferences: [] });
                });
                return;
            }
            processColumnPreferencesResponse(res);
        }).fail(function (_x, status) {
            global._sjColOrderPrefPrimaryXhr = null;
            if (status === 'abort') return;
            processColumnPreferencesResponse({ status: 'success', preferences: [] });
        });
    };
})(typeof window !== 'undefined' ? window : this);
