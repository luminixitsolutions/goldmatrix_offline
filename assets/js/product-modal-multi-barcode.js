/**
 * Product modal barcode field: space-separated barcodes (e.g. "GD00018 GD00020 GD00021")
 * on Enter / Tab / blur — fetch each barcode sequentially with a loading overlay.
 */
(function (global) {
    'use strict';

    var LOADER_ID = 'productModalBarcodeBatchLoader';
    var STYLES_ID = 'productModalBarcodeBatchLoaderStyles';

    /** Strip control chars (CR/LF/TAB) that wedge scanners often append inside the scanned string. */
    function normalizeScannedBarcode(raw) {
        return String(raw || '')
            .replace(/[\x00-\x1F\x7F]/g, '')
            .trim();
    }

    function parseTokens(raw) {
        return normalizeScannedBarcode(raw).split(/\s+/).filter(function (s) {
            return s.length > 0;
        });
    }

    function sanitizeBarcodeInput(el) {
        if (!el) {
            return '';
        }
        var raw = String(el.value || '');
        var cleaned = normalizeScannedBarcode(raw);
        if (cleaned !== raw) {
            el.value = cleaned;
        }
        return cleaned;
    }

    function isEnterLikeKey(e) {
        return !!(e && (e.key === 'Enter' || e.keyCode === 13));
    }

    /**
     * USB barcode wedge + manual entry for #modalProductBarcode.
     * opts.onTrigger(input, fromBlur) — required (page supplies fetch/dedupe logic).
     * opts.onInputDedupeReset — optional, called on each input keystroke.
     * opts.onModalHidden — optional reset when product selection modal closes.
     */
    function initModalProductBarcodeScanner(opts) {
        opts = opts || {};
        var onTrigger = opts.onTrigger;
        if (typeof onTrigger !== 'function' || typeof jQuery === 'undefined') {
            return;
        }

        var inputSel = opts.inputSelector || '#modalProductBarcode';
        var fetchBtnSel = opts.fetchBtnSelector || '#modalProductBarcodeFetchBtn';
        var modalSel = opts.modalSelector || '#productSelectionModal';
        var blurTimer = null;
        var inputTimer = null;
        var rapidKeyCount = 0;
        var rapidKeyResetTimer = null;
        var ENTER_DEFER_MS = 40;
        var BLUR_DEFER_MS = 150;
        var SCAN_IDLE_MS = 90;

        function clearBlurTimer() {
            if (blurTimer) {
                clearTimeout(blurTimer);
                blurTimer = null;
            }
        }

        function clearInputTimer() {
            if (inputTimer) {
                clearTimeout(inputTimer);
                inputTimer = null;
            }
        }

        function clearAllTimers() {
            clearBlurTimer();
            clearInputTimer();
        }

        function scheduleTrigger(input, fromBlur, delayMs) {
            clearBlurTimer();
            clearInputTimer();
            setTimeout(function () {
                sanitizeBarcodeInput(input);
                onTrigger(input, !!fromBlur);
            }, delayMs || 0);
        }

        jQuery(document).on('keydown', inputSel, function (e) {
            if (isEnterLikeKey(e)) {
                if (sanitizeBarcodeInput(this)) {
                    e.preventDefault();
                    clearAllTimers();
                    scheduleTrigger(this, false, ENTER_DEFER_MS);
                }
                return;
            }
            if (e.key === 'Tab' && !e.shiftKey) {
                if (sanitizeBarcodeInput(this)) {
                    clearAllTimers();
                    scheduleTrigger(this, false, ENTER_DEFER_MS);
                }
                return;
            }
            if (e.key && e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                rapidKeyCount += 1;
                if (rapidKeyResetTimer) {
                    clearTimeout(rapidKeyResetTimer);
                }
                rapidKeyResetTimer = setTimeout(function () {
                    rapidKeyResetTimer = null;
                    rapidKeyCount = 0;
                }, 400);
            }
        });

        jQuery(document).on('input', inputSel, function (e) {
            if (typeof opts.onInputDedupeReset === 'function') {
                opts.onInputDedupeReset();
            }
            var self = this;
            var cleaned = sanitizeBarcodeInput(self);
            if (!cleaned) {
                clearInputTimer();
                return;
            }

            var oe = e.originalEvent || e;
            var bulkInput = oe && (
                oe.inputType === 'insertFromPaste' ||
                oe.inputType === 'insertReplacementText' ||
                (oe.data && String(oe.data).length > 1)
            );

            clearInputTimer();
            inputTimer = setTimeout(function () {
                inputTimer = null;
                if (!sanitizeBarcodeInput(self)) {
                    return;
                }
                if (bulkInput || rapidKeyCount >= 4) {
                    rapidKeyCount = 0;
                    onTrigger(self, false);
                }
            }, bulkInput ? 30 : SCAN_IDLE_MS);
        });

        jQuery(document).on('blur', inputSel, function () {
            clearBlurTimer();
            var self = this;
            blurTimer = setTimeout(function () {
                blurTimer = null;
                if (sanitizeBarcodeInput(self)) {
                    onTrigger(self, true);
                }
            }, BLUR_DEFER_MS);
        });

        jQuery(document).on('click', fetchBtnSel, function (e) {
            e.preventDefault();
            e.stopPropagation();
            var inp = document.querySelector(inputSel);
            if (!inp) {
                return;
            }
            clearAllTimers();
            if (!sanitizeBarcodeInput(inp)) {
                inp.focus();
                alert('Please enter or scan a barcode number.');
                return;
            }
            onTrigger(inp, false);
        });

        jQuery(modalSel).on('hidden.bs.modal', function () {
            clearAllTimers();
            rapidKeyCount = 0;
            if (rapidKeyResetTimer) {
                clearTimeout(rapidKeyResetTimer);
                rapidKeyResetTimer = null;
            }
            if (typeof opts.onModalHidden === 'function') {
                opts.onModalHidden();
            }
        });

        jQuery(modalSel).on('shown.bs.modal', function () {
            setTimeout(function () {
                var inp = document.querySelector(inputSel);
                if (!inp) {
                    return;
                }
                try {
                    inp.focus();
                    inp.select();
                } catch (err) {}
            }, 120);
        });
    }

    function batchIsActive() {
        return !!(global.__auragoldModalBarcodeBatch && global.__auragoldModalBarcodeBatch.active);
    }

    function ensureLoaderStyles() {
        if (document.getElementById(STYLES_ID)) return;
        var style = document.createElement('style');
        style.id = STYLES_ID;
        style.textContent = [
            '#productSelectionModal .modal-content { position: relative; }',
            '#' + LOADER_ID + '.product-modal-barcode-batch-loader {',
            '  display: none; position: absolute; left: 0; top: 0; right: 0; bottom: 0;',
            '  z-index: 1090; align-items: center; justify-content: center; flex-direction: column;',
            '  background: rgba(15, 23, 42, 0.55); border-radius: 0.3rem;',
            '}',
            '#' + LOADER_ID + '.product-modal-barcode-batch-loader.is-visible { display: flex; }',
            '#' + LOADER_ID + ' .product-modal-barcode-batch-loader__panel {',
            '  text-align: center; padding: 1.5rem 2rem; background: #1e293b; color: #f8fafc;',
            '  border-radius: 8px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.25); max-width: 92%;',
            '}',
            '#' + LOADER_ID + ' .product-modal-barcode-batch-loader__spinner {',
            '  width: 2.5rem; height: 2.5rem; margin: 0 auto 1rem;',
            '  border: 3px solid rgba(248, 250, 252, 0.25); border-top-color: #c5a864;',
            '  border-radius: 50%; animation: productModalBarcodeBatchSpin 0.7s linear infinite;',
            '}',
            '#' + LOADER_ID + ' .product-modal-barcode-batch-loader__text {',
            '  margin: 0; font-size: 0.95rem; font-weight: 600; color: #f1f5f9;',
            '}',
            '@keyframes productModalBarcodeBatchSpin { to { transform: rotate(360deg); } }'
        ].join('\n');
        document.head.appendChild(style);
    }

    function ensureLoaderDom() {
        var modal = document.getElementById('productSelectionModal');
        if (!modal) return null;
        var content = modal.querySelector('.modal-content');
        if (!content) return null;
        var el = document.getElementById(LOADER_ID);
        if (!el) {
            ensureLoaderStyles();
            el = document.createElement('div');
            el.id = LOADER_ID;
            el.className = 'product-modal-barcode-batch-loader';
            el.setAttribute('aria-hidden', 'true');
            el.setAttribute('role', 'status');
            el.innerHTML = '<div class="product-modal-barcode-batch-loader__panel">'
                + '<div class="product-modal-barcode-batch-loader__spinner" aria-hidden="true"></div>'
                + '<p class="product-modal-barcode-batch-loader__text">Loading items…</p>'
                + '</div>';
            content.appendChild(el);
        }
        return el;
    }

    function showLoader(currentIndex, total, barcode) {
        var el = ensureLoaderDom();
        if (!el) return;
        var textEl = el.querySelector('.product-modal-barcode-batch-loader__text');
        if (textEl) {
            var n = Math.max(1, Math.min(currentIndex, total));
            var line = 'Loading items (' + n + ' of ' + total + ')';
            if (barcode) {
                line += ' — ' + barcode;
            }
            textEl.textContent = line + '…';
        }
        el.classList.add('is-visible');
        el.setAttribute('aria-hidden', 'false');
    }

    function hideLoader() {
        var el = document.getElementById(LOADER_ID);
        if (!el) return;
        el.classList.remove('is-visible');
        el.setAttribute('aria-hidden', 'true');
    }

    function clearInput(barcodeInput) {
        if (!barcodeInput) return;
        if (batchIsActive()) return;
        barcodeInput.value = '';
        barcodeInput.style.borderColor = '';
        try {
            barcodeInput.focus();
        } catch (e) {}
    }

    function batchStep() {
        var b = global.__auragoldModalBarcodeBatch;
        if (!b || !b.active) return;
        if (b.idx >= b.list.length) {
            b.active = false;
            hideLoader();
            if (b.input) {
                b.input.value = '';
                b.input.style.borderColor = '';
                try {
                    b.input.focus();
                } catch (e) {}
            }
            global.__auragoldModalBarcodeBatch = null;
            return;
        }
        var bc = b.list[b.idx];
        showLoader(b.idx + 1, b.list.length, bc);
        if (typeof b.fetchOne === 'function') {
            b.fetchOne(bc);
        }
        b.idx++;
    }

    function startBatch(barcodes, inputEl, fetchOne) {
        if (!barcodes || !barcodes.length || typeof fetchOne !== 'function') return;
        if (batchIsActive()) return;
        global.__auragoldModalBarcodeBatch = {
            active: true,
            input: inputEl || null,
            list: barcodes.slice(),
            idx: 0,
            fetchOne: fetchOne
        };
        batchStep();
    }

    function onFetchComplete() {
        if (batchIsActive()) {
            setTimeout(batchStep, 40);
        }
    }

    function onModalHidden(e) {
        var t = e && e.target;
        if (!t || t.id !== 'productSelectionModal') return;
        hideLoader();
        global.__auragoldModalBarcodeBatch = null;
    }

    if (typeof document !== 'undefined') {
        document.addEventListener('hidden.bs.modal', onModalHidden);
        if (typeof jQuery !== 'undefined') {
            jQuery(document).on('hidden.bs.modal', '#productSelectionModal', function () {
                hideLoader();
                global.__auragoldModalBarcodeBatch = null;
            });
        }
    }

    global.auragoldParseModalBarcodeTokens = parseTokens;
    global.auragoldNormalizeScannedBarcode = normalizeScannedBarcode;
    /** Prefer server-resolved full barcode (0123 → ASD123) from get-product-by-barcode JSON. */
    global.auragoldResolvedBarcodeFromApi = function (data, fallback) {
        if (data && data.resolved_barcode) {
            return String(data.resolved_barcode).trim();
        }
        if (data && data.product && data.product.barcode) {
            return String(data.product.barcode).trim();
        }
        if (data && data.products && data.products[0] && data.products[0].barcode) {
            return String(data.products[0].barcode).trim();
        }
        return String(fallback || '').trim();
    };
    /** Expand 4-digit label scan to full barcode (0123 → ASD123) before product fetch. */
    global.auragoldResolveScannedBarcodeRemote = function (raw, opts) {
        opts = opts || {};
        var scanned = normalizeScannedBarcode(raw);
        if (!scanned) {
            return Promise.resolve({ scanned: '', barcode: '', resolved_barcode: null });
        }
        var qs = 'barcode=' + encodeURIComponent(scanned);
        if (opts.branch_id != null && parseInt(opts.branch_id, 10) > 0) {
            qs += '&branch_id=' + parseInt(opts.branch_id, 10);
        }
        if (opts.metal_id != null && String(opts.metal_id).trim() !== '') {
            qs += '&metal_id=' + encodeURIComponent(String(opts.metal_id).trim());
        }
        return fetch('ajax/resolve-scanned-barcode.php?' + qs, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    return { scanned: scanned, barcode: scanned, resolved_barcode: null };
                }
                var full = String(data.barcode || data.resolved_barcode || scanned).trim() || scanned;
                return {
                    scanned: scanned,
                    barcode: full,
                    resolved_barcode: data.resolved_barcode || (full !== scanned ? full : null),
                    exists_in_system: !!data.exists_in_system
                };
            })
            .catch(function () {
                return { scanned: scanned, barcode: scanned, resolved_barcode: null };
            });
    };
    global.auragoldApplyResolvedBarcodeToInput = function (inputEl, resolvedInfo) {
        if (!inputEl || !resolvedInfo) {
            return;
        }
        var full = String(resolvedInfo.barcode || resolvedInfo.resolved_barcode || '').trim();
        if (full) {
            inputEl.value = full;
        }
    };
    /** Resolve 4-digit scan (0005) then run callback with full barcode (BBR00005). */
    global.auragoldRunResolvedBarcodeFetch = function (scanned, opts, inputEl, done) {
        opts = opts || {};
        var s = normalizeScannedBarcode(scanned);
        if (typeof done !== 'function') {
            return;
        }
        if (!s) {
            done(s);
            return;
        }
        if (/^[A-Za-z]/.test(s)) {
            done(s);
            return;
        }
        global.auragoldResolveScannedBarcodeRemote(s, opts).then(function (info) {
            global.auragoldApplyResolvedBarcodeToInput(inputEl, info);
            done(info && info.barcode ? info.barcode : s);
        }).catch(function () {
            done(s);
        });
    };
    /** branch_id + metal_id query string for get-product-by-barcode.php */
    global.auragoldBuildProductByBarcodeQuery = function (lookupBarcode, extraParams) {
        extraParams = extraParams || {};
        var parts = ['barcode=' + encodeURIComponent(String(lookupBarcode || '').trim())];
        var branchId = parseInt((typeof global.AURAGOLD_WORKING_BRANCH_ID !== 'undefined') ? global.AURAGOLD_WORKING_BRANCH_ID : 0, 10);
        if (branchId > 0) {
            parts.push('branch_id=' + branchId);
        }
        var metalId = (typeof global.currentMetalId !== 'undefined' && global.currentMetalId != null && String(global.currentMetalId).trim() !== '')
            ? String(global.currentMetalId).trim()
            : '';
        if (metalId) {
            parts.push('metal_id=' + encodeURIComponent(metalId));
        }
        Object.keys(extraParams).forEach(function (k) {
            var v = extraParams[k];
            if (v != null && v !== '') {
                parts.push(k + '=' + encodeURIComponent(String(v)));
            }
        });
        return parts.join('&');
    };
    /** After get-product-by-barcode JSON: show full barcode in modal input when scan was 4-digit. */
    global.auragoldApplyProductFetchResolvedToInput = function (inputEl, data, scannedRaw) {
        if (!inputEl || !data) {
            return;
        }
        if (data.resolved_barcode) {
            inputEl.value = String(data.resolved_barcode).trim();
        } else if (data.barcode && /^\d{1,4}$/.test(String(scannedRaw || ''))) {
            inputEl.value = String(data.barcode).trim();
        }
    };
    /** Wrap page fetchProductByBarcodeAndAdd so 4-digit scans expand before lookup. */
    global.auragoldWrapFetchProductByBarcodeWithResolve = function (fn, getInputEl) {
        if (typeof fn !== 'function') {
            return fn;
        }
        getInputEl = getInputEl || function () {
            return document.getElementById('modalProductBarcode');
        };
        return function (barcode) {
            var scanned = normalizeScannedBarcode(barcode);
            if (!scanned) {
                return;
            }
            var inputEl = getInputEl();
            var opts = {
                branch_id: (typeof global.AURAGOLD_WORKING_BRANCH_ID !== 'undefined') ? global.AURAGOLD_WORKING_BRANCH_ID : 0,
                metal_id: (typeof global.currentMetalId !== 'undefined') ? global.currentMetalId : ''
            };
            auragoldRunResolvedBarcodeFetch(scanned, opts, inputEl, function (resolved) {
                fn(resolved || scanned);
            });
        };
    };
    global.auragoldInitModalProductBarcodeScanner = initModalProductBarcodeScanner;
    global.auragoldModalBarcodeBatchIsActive = batchIsActive;
    global.auragoldClearModalProductBarcodeInput = clearInput;
    global.auragoldStartModalBarcodeBatch = startBatch;
    global.auragoldModalBarcodeBatchOnFetchComplete = onFetchComplete;
    global.auragoldShowModalBarcodeBatchLoader = showLoader;
    global.auragoldHideModalBarcodeBatchLoader = hideLoader;
    /** True when metal tab is Diamond & Stones (characteristic-only dedupe applies there). */
    global.auragoldProductMetalIsDiamondStones = function (metalId) {
        if (metalId == null || metalId === '' || typeof global.metals === 'undefined' || !global.metals) {
            return false;
        }
        var idStr = String(metalId);
        for (var mi = 0; mi < global.metals.length; mi++) {
            if (String(global.metals[mi].id) !== idStr) {
                continue;
            }
            var nm = String(global.metals[mi].display_name || global.metals[mi].name || '').toLowerCase();
            return nm.indexOf('diamond') !== -1;
        }
        return false;
    };
})(typeof window !== 'undefined' ? window : this);
