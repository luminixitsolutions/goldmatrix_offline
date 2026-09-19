/**
 * Product search modal + populate row (extracted from sale-invoice.php).
 * Requires window.currentMetalId / window.currentMetalName (set by page modal script).
 */
(function () {
    'use strict';

    /** Bootstrap #productSelectionModal focus trap blocks nested overlay inputs unless suspended. */
    var GUARD_KEY = '__auragoldBootstrapModalFocusGuard';
    if (!window.auragoldSuspendBootstrapModalFocusTrap) {
        window.auragoldSuspendBootstrapModalFocusTrap = function (allowSelector) {
            allowSelector = String(allowSelector || '').trim();
            if (typeof jQuery !== 'undefined') {
                jQuery(document).off('focusin.modal focusin.bs.modal');
            }
            if (window[GUARD_KEY]) {
                document.removeEventListener('focusin', window[GUARD_KEY], true);
            }
            window[GUARD_KEY] = function (e) {
                if (!allowSelector) {
                    return;
                }
                var root = document.querySelector(allowSelector);
                if (root && root.contains(e.target)) {
                    e.stopImmediatePropagation();
                }
            };
            document.addEventListener('focusin', window[GUARD_KEY], true);
        };
        window.auragoldResumeBootstrapModalFocusTrap = function () {
            if (window[GUARD_KEY]) {
                document.removeEventListener('focusin', window[GUARD_KEY], true);
                window[GUARD_KEY] = null;
            }
            if (typeof jQuery === 'undefined') {
                return;
            }
            var productModal = document.getElementById('productSelectionModal');
            if (!productModal) {
                return;
            }
            var isOpen = productModal.classList.contains('show') || productModal.style.display === 'block';
            if (!isOpen) {
                return;
            }
            var inst = jQuery(productModal).data('bs.modal');
            if (inst && typeof inst._enforceFocus === 'function') {
                inst._enforceFocus();
            }
        };
    }

// Open product search modal for selecting a product
var currentProductRow = null;

function auragoldSetCurrentProductSearchRow(row) {
    currentProductRow = row;
    window._auragoldCurrentProductSearchRow = row;
}

function auragoldGetCurrentProductSearchRow() {
    return window._auragoldCurrentProductSearchRow || currentProductRow || null;
}

/** True when Product Selection modal is visible. */
function auragoldIsProductSelectionModalOpen() {
    var modal = document.getElementById('productSelectionModal');
    if (!modal) return false;
    return modal.classList.contains('show') || modal.style.display === 'block';
}

/**
 * Metal tab id for product search — row data-metal-id, active category tab, then window.currentMetalId.
 */
function auragoldResolveProductSearchMetalId(row) {
    var activeTab = document.querySelector('#productSelectionModal .category-tab-btn.active');
    var tabMid = activeTab ? String(activeTab.getAttribute('data-metal-id') || '').trim() : '';
    if (auragoldIsProductSelectionModalOpen() && tabMid) {
        return tabMid;
    }
    if (row && row.getAttribute) {
        var rowMid = String(row.getAttribute('data-metal-id') || '').trim();
        if (rowMid) return rowMid;
    }
    if (tabMid) return tabMid;
    if (typeof window.currentMetalId !== 'undefined' && window.currentMetalId != null && String(window.currentMetalId).trim() !== '') {
        return String(window.currentMetalId).trim();
    }
    if (typeof window.productSearchMetalId !== 'undefined' && window.productSearchMetalId != null && String(window.productSearchMetalId).trim() !== '') {
        return String(window.productSearchMetalId).trim();
    }
    return null;
}
window.auragoldResolveProductSearchMetalId = auragoldResolveProductSearchMetalId;

/** Active tab display name (for empty-state messages). */
function auragoldGetActiveProductSearchMetalLabel() {
    var activeTab = document.querySelector('#productSelectionModal .category-tab-btn.active');
    if (activeTab) {
        var n = String(activeTab.getAttribute('data-metal-name') || activeTab.textContent || '').trim();
        if (n) return n;
    }
    if (typeof window.currentMetalName === 'string' && window.currentMetalName.trim()) {
        return window.currentMetalName.trim();
    }
    return '';
}

/** Default every metal-qty input on the row to 1 when empty or zero (handles duplicate cells after column reorder). */
function auragoldEnsureModalRowMetalQtyDefault(row) {
    if (!row || !row.querySelectorAll) return;
    row.querySelectorAll('[data-column="metal-qty"] input').forEach(function (inp) {
        // Allow clearing the field while typing a new value — do not snap back to 1 mid-edit.
        try {
            if (inp && typeof document !== 'undefined' && document.activeElement === inp) return;
        } catch (e) { /* ignore */ }
        var raw = String(inp.value || '').trim();
        var n = parseFloat(raw);
        if (!raw || isNaN(n) || n <= 0) {
            inp.value = '1';
        }
    });
}

function auragoldBindProductSearchResultItems(container) {
    if (!container) return;
    container.querySelectorAll('.product-search-item').forEach(function (item) {
        if (item._auragoldProductSearchBound) return;
        item._auragoldProductSearchBound = true;
        item.style.userSelect = 'none';
        item.style.webkitUserSelect = 'none';
        item.addEventListener('mousedown', function (e) {
            e.preventDefault();
        });
        item.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var pick = typeof window.selectProductFromSearch === 'function'
                ? window.selectProductFromSearch
                : selectProductFromSearch;
            var parseFn = typeof window.parseProductSearchItemFromClick === 'function'
            ? window.parseProductSearchItemFromClick
            : parseProductSearchItemFromClick;
        pick(parseFn(item));
        });
    });
}

function openProductSearchModal(row) {
    auragoldSetCurrentProductSearchRow(row);
    var diamondCategory = '';
    if (row) {
        var categorySelect = row.querySelector('[data-column="category"] select');
        diamondCategory = categorySelect ? (categorySelect.value || '').trim() : '';
    }
    if (!diamondCategory && typeof window.isLooseDiamondTabActive === 'function' && window.isLooseDiamondTabActive()) {
        diamondCategory = 'Diamond';
    }
    window.productSearchDiamondCategory = diamondCategory || '';
    window.productSearchMetalId = auragoldResolveProductSearchMetalId(row);

    var searchModalZ = document.body.classList.contains('product-row-detail-modal-open') ? 10900 : 10700;
    
    // Create modal HTML
    const modalHtml = `
        <div id="productSearchModal" style="
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: ${searchModalZ};
            display: flex;
            align-items: center;
            justify-content: center;
        ">
            <div style="
                background: white;
                border-radius: 8px;
                padding: 20px;
                max-width: 600px;
                width: 90%;
                max-height: 80vh;
                overflow: hidden;
                display: flex;
                flex-direction: column;
            ">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-shrink: 0;">
                    <h5 style="margin: 0;">Search and Select Product</h5>
                    <button id="closeProductSearchModal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">×</button>
                </div>
                <div style="margin-bottom: 15px; flex-shrink: 0;">
                    <input type="text" id="productSearchInput" placeholder="Search by name, article, or SKU..." 
                           class="form-control" autocomplete="off" style="width: 100%; padding: 0.5rem;">
                </div>
                <div id="productSearchResults" style="
                    flex: 1;
                    overflow-y: auto;
                    border: 1px solid #e2e8f0;
                    border-radius: 4px;
                    padding: 10px;
                ">
                    <div class="text-muted text-center" style="padding: 20px;">Type to search products...</div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('productSearchModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Bootstrap #productSelectionModal focus trap blocks typing in this overlay unless suspended.
    if (typeof window.auragoldSuspendBootstrapModalFocusTrap === 'function') {
        window.auragoldSuspendBootstrapModalFocusTrap('#productSearchModal');
    }
    
    // Focus on search input
    const searchInput = document.getElementById('productSearchInput');
    if (searchInput) {
        setTimeout(function () {
            searchInput.focus();
            searchInput.select && searchInput.select();
        }, 0);
    }
    
    // Close modal handlers
    document.getElementById('closeProductSearchModal').addEventListener('click', closeProductSearchModal);
    document.getElementById('productSearchModal').addEventListener('click', function(e) {
        if (e.target.id === 'productSearchModal') {
            closeProductSearchModal();
        }
    });
    
    // Search functionality
    let searchTimeout;
    if (searchInput) {
        searchInput.addEventListener('keydown', function (e) {
            e.stopPropagation();
        });
        searchInput.addEventListener('keyup', function (e) {
            e.stopPropagation();
        });
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const searchTerm = this.value.trim();
            searchTimeout = setTimeout(function() {
                searchProducts(searchTerm);
            }, 250);
        });
    }
    
    // Load initial products (empty search)
    searchProducts('');
}

// Search products via AJAX (filter by current tab metal when opened from Product Selection modal)
function searchProducts(searchTerm) {
    const resultsDiv = document.getElementById('productSearchResults');
    if (!resultsDiv) return;
    resultsDiv.innerHTML = '<div class="text-muted text-center" style="padding: 20px;">Searching...</div>';

    var row = auragoldGetCurrentProductSearchRow();
    const metalId = auragoldResolveProductSearchMetalId(row);
    if (auragoldIsProductSelectionModalOpen() && !metalId) {
        resultsDiv.innerHTML = '<div class="text-muted text-center" style="padding: 20px;">Select a product category tab first (Gold, Silver, Imitation, etc.).</div>';
        return;
    }

    let url = 'ajax/search-products.php?search=' + encodeURIComponent(searchTerm) + '&limit=50';
    if (metalId) {
        url += '&metal_id=' + encodeURIComponent(metalId);
    }
    const diamondCat = (typeof window.productSearchDiamondCategory !== 'undefined') ? (window.productSearchDiamondCategory || '') : '';
    if (diamondCat && ['Diamonds', 'GemStones', 'Jewellery'].indexOf(diamondCat) !== -1) {
        url += '&diamond_category=' + encodeURIComponent(diamondCat);
    }
    if (typeof window.AURAGOLD_WORKING_BRANCH_ID !== 'undefined' && window.AURAGOLD_WORKING_BRANCH_ID > 0) {
        url += '&branch_id=' + encodeURIComponent(window.AURAGOLD_WORKING_BRANCH_ID);
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.products && data.products.length > 0) {
                let html = '<div style="display: flex; flex-direction: column; gap: 8px;">';
                data.products.forEach(function(product) {
                    const productName = product.name + (product.metal_name ? ' - ' + product.metal_name : '');
                    const displayText = productName + (product.article ? ' (' + product.article + ')' : '');
                    var searchMetal = (product.metal_id != null && product.metal_id !== '') ? product.metal_id : metalId;
                    var payloadEnc = '';
                    try { payloadEnc = encodeURIComponent(JSON.stringify(product)); } catch (e) { payloadEnc = ''; }
                    html += `
                        <div class="product-search-item" 
                             data-product-id="${(product.product_id != null && product.product_id !== '') ? product.product_id : product.id}" 
                             data-characteristic-id="${product.characteristic_id || ''}"
                             data-metal-id="${searchMetal != null && searchMetal !== '' ? searchMetal : ''}"
                             data-product-payload="${payloadEnc}"
                             style="
                                 padding: 12px;
                                 border: 1px solid #e2e8f0;
                                 border-radius: 4px;
                                 cursor: pointer;
                                 transition: all 0.2s;
                                 background: #fff;
                                 user-select: none;
                             "
                             onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#c5a864';"
                             onmouseout="this.style.background='#fff'; this.style.borderColor='#e2e8f0';">
                            <div style="font-weight: 600; color: #1e293b; font-size: 0.9rem;">${escapeHtml(displayText)}</div>
                            ${product.sku_code ? '<div style="font-size: 0.8rem; color: #64748b; margin-top: 4px;">SKU: ' + escapeHtml(product.sku_code) + '</div>' : ''}
                            ${product.opening_weight ? '<div style="font-size: 0.75rem; color: #94a3b8; margin-top: 2px;">Weight: ' + product.opening_weight + ' | Purity: ' + (product.opening_purity || 1) + '</div>' : ''}
                        </div>
                    `;
                });
                html += '</div>';
                resultsDiv.innerHTML = html;
                auragoldBindProductSearchResultItems(resultsDiv);
            } else {
                var tabLabel = auragoldGetActiveProductSearchMetalLabel();
                var emptyMsg = tabLabel
                    ? ('No products found for ' + tabLabel + (searchTerm ? ' matching your search' : '') + '.')
                    : (searchTerm ? 'No products found' : 'No products found for this category');
                resultsDiv.innerHTML = '<div class="text-muted text-center" style="padding: 20px;">' + escapeHtml(emptyMsg) + '</div>';
            }
        })
        .catch(error => {
            console.error('Error searching products:', error);
            resultsDiv.innerHTML = '<div class="text-danger text-center" style="padding: 20px;">Error searching products</div>';
        });
}

// Build product object from search result row (payload JSON or data-* fallbacks).
function parseProductSearchItemFromClick(item) {
    var product = {};
    if (!item) return product;
    var enc = item.getAttribute('data-product-payload');
    try {
        if (enc) product = JSON.parse(decodeURIComponent(enc));
    } catch (err) {}
    if (!product || typeof product !== 'object') product = {};
    if ((product.id == null || product.id === '') && (product.product_id == null || product.product_id === '')) {
        var pid = item.getAttribute('data-product-id');
        if (pid) product.id = pid;
    }
    if (product.characteristic_id == null || product.characteristic_id === '') {
        var cid = item.getAttribute('data-characteristic-id');
        if (cid) product.characteristic_id = cid;
    }
    if (product.metal_id == null || product.metal_id === '') {
        var mid = item.getAttribute('data-metal-id');
        if (mid) product.metal_id = mid;
    }
    return product;
}

// Select product from search results — fetch full get-product-details so product.taxes (tbl_product_tax) is present
function selectProductFromSearch(product) {
    var row = auragoldGetCurrentProductSearchRow();
    if (!row) return;
    currentProductRow = row;
    
    var rawPid = product && (product.product_id != null && String(product.product_id).trim() !== '' ? product.product_id : product.id);
    var pidNum = parseInt(String(rawPid != null ? rawPid : ''), 10);
    if (isNaN(pidNum) || pidNum <= 0) {
        console.warn('[GST] product search: invalid or missing product_id', product);
        closeProductSearchModal();
        return;
    }
    var cid = product && product.characteristic_id != null && product.characteristic_id !== '' ? String(product.characteristic_id) : '';
    var midFromRow = (row.getAttribute('data-metal-id') || '').trim();
    var midFromProduct = product && product.metal_id != null && String(product.metal_id).trim() !== '' ? String(product.metal_id).trim() : '';
    var midFromTab = (typeof window.currentMetalId !== 'undefined' && window.currentMetalId != null && window.currentMetalId !== '') ? String(window.currentMetalId) : '';
    var metalIdStr = midFromRow || midFromProduct || midFromTab || '';
    var midNum = parseInt(metalIdStr, 10);
    
    function applyFullProduct(full) {
        try {
            var popFn = (typeof window.populateRowWithProduct === 'function')
                ? window.populateRowWithProduct
                : populateRowWithProduct;
            popFn(row, full);
        } catch (err) {
            console.error('populateRowWithProduct failed', err);
        } finally {
            closeProductSearchModal();
        }
        if (typeof window.refreshMobileInlineProductFormIfOpen === 'function') {
            window.refreshMobileInlineProductFormIfOpen(row);
        }
        setTimeout(function() {
            const locationSelect = row.querySelector('[data-column="location"] select, .location-select');
            if (locationSelect) locationSelect.focus();
        }, 50);
    }
    
    var ajaxData = { product_id: pidNum };
    if (cid) ajaxData.characteristic_id = cid;
    if (!isNaN(midNum) && midNum > 0) ajaxData.metal_id = midNum;
    
    function onDetailsSuccess(response) {
        if (window.AURAGOLD_LOG_PRODUCT_SELECT !== false) {
            console.log('[get-product-details] full JSON response', response);
        }
        if (!response || !response.success || !response.product) {
            console.warn('[get-product-details] using search list payload:', response && response.message, product);
            applyFullProduct(product);
            return;
        }
        applyFullProduct(response.product);
    }
    
    if (typeof jQuery !== 'undefined' && jQuery.ajax) {
        jQuery.ajax({
            url: 'ajax/get-product-details.php',
            method: 'GET',
            data: ajaxData,
            dataType: 'json'
        }).done(onDetailsSuccess).fail(function(xhr) {
            if (window.AURAGOLD_LOG_PRODUCT_SELECT !== false) {
                console.warn('[get-product-details] request failed, using list/search payload. Status:', xhr && xhr.status, ajaxData);
            }
            applyFullProduct(product);
        });
    } else {
        var url = typeof window.auragoldGetProductDetailsUrl === 'function'
            ? window.auragoldGetProductDetailsUrl(pidNum, cid, (!isNaN(midNum) && midNum > 0) ? midNum : '')
            : '';
        if (!url) {
            if (window.AURAGOLD_LOG_PRODUCT_SELECT !== false) {
                console.warn('[get-product-details] no URL, using list payload:', product);
            }
            applyFullProduct(product);
            return;
        }
        fetch(url)
            .then(function(r) { return r.json(); })
            .then(onDetailsSuccess)
            .catch(function(err) {
                if (window.AURAGOLD_LOG_PRODUCT_SELECT !== false) {
                    console.warn('[get-product-details] fetch failed, using list payload. Error:', err, product);
                }
                applyFullProduct(product);
            });
    }
}

if (!window._auragoldProductSearchItemClickBound) {
    window._auragoldProductSearchItemClickBound = true;
    document.addEventListener('click', function(e) {
        var item = e.target.closest('#productSearchModal .product-search-item');
        if (!item) return;
        e.preventDefault();
        e.stopPropagation();
        var pick = typeof window.selectProductFromSearch === 'function'
            ? window.selectProductFromSearch
            : selectProductFromSearch;
        var parseFn = typeof window.parseProductSearchItemFromClick === 'function'
            ? window.parseProductSearchItemFromClick
            : parseProductSearchItemFromClick;
        pick(parseFn(item));
    }, true);
}

/** Set <select> by option value, or by matching option text (for category/location stored as name in stock journal). */
function selectOptionByValueOrText(select, raw) {
    if (!select || raw == null || raw === '') return;
    const s = String(raw).trim();
    if (!s) return;
    for (let i = 0; i < select.options.length; i++) {
        if (select.options[i].value === s) {
            select.selectedIndex = i;
            return;
        }
    }
    for (let i = 0; i < select.options.length; i++) {
        const t = (select.options[i].textContent || '').trim();
        if (t === s || t.replace(/\s+/g, ' ') === s) {
            select.selectedIndex = i;
            return;
        }
    }
    const sLower = s.toLowerCase();
    for (let i = 0; i < select.options.length; i++) {
        if (String(select.options[i].value || '').toLowerCase() === sLower) {
            select.selectedIndex = i;
            return;
        }
    }
    for (let i = 0; i < select.options.length; i++) {
        const t = (select.options[i].textContent || '').trim();
        if (t.toLowerCase() === sLower) {
            select.selectedIndex = i;
            return;
        }
    }
}

/** Match karat from tbl_stock_journal (id, "24", "24K", or purity 99.90) to carat dropdown. */
function selectCaratFromStockJournal(caratSelect, karatVal) {
    if (!caratSelect || karatVal == null || karatVal === '') return;
    const raw = String(karatVal).trim();
    if (!raw) return;
    if ([...caratSelect.options].some(function(o) { return o.value === raw; })) {
        caratSelect.value = raw;
        return;
    }
    const num = parseFloat(raw.replace(/[^0-9.]/g, ''));
    if (isNaN(num)) return;
    for (let i = 0; i < caratSelect.options.length; i++) {
        const o = caratSelect.options[i];
        const nameNum = parseFloat(String(o.textContent || '').replace(/[^0-9.]/g, ''));
        if (!isNaN(nameNum) && Math.abs(nameNum - num) < 0.001) {
            caratSelect.selectedIndex = i;
            return;
        }
        /* sj.karat often stores purity (99.90) while option text is "24K" — match data-purity. */
        const pur = parseFloat(o.getAttribute('data-purity') || '');
        if (!isNaN(pur) && Math.abs(pur - num) < 0.051) {
            caratSelect.selectedIndex = i;
            return;
        }
    }
}

function setModalCellInput(row, column, value) {
    if (value == null || value === '') return;
    const cell = row.querySelector('[data-column="' + column + '"]');
    if (!cell) return;
    const inp = cell.querySelector('input, textarea');
    if (inp && inp.type !== 'checkbox') {
        inp.value = value;
    }
}

function setModalSelectIfOptionExists(row, column, value) {
    if (value == null || value === '') return;
    const sel = row.querySelector('[data-column="' + column + '"] select');
    if (!sel) return;
    const s = String(value).trim();
    if (!s) return;
    // Match by value or by visible text (purchase saves "Jewellery"/"Diamonds" as labels; option.value may differ).
    selectOptionByValueOrText(sel, s);
}

/** Voucher default discount type for current row metal (matches product-modal / reverse logic). Fallback Fix. */
function getVoucherDefaultDiscountTypeForModalRow(row) {
    var fallback = 'Fix';
    if (typeof window.voucherSettingsByMetal !== 'object' || window.voucherSettingsByMetal === null) {
        return fallback;
    }
    var metalWise = 'Gold';
    var metalId = row && row.getAttribute ? row.getAttribute('data-metal-id') : null;
    if (typeof window.metals !== 'undefined' && window.metals && metalId != null && metalId !== '') {
        var metal = window.metals.find(function(m) { return String(m.id) === String(metalId); });
        if (metal && (metal.display_name || metal.name)) {
            metalWise = metal.display_name || metal.name;
        }
    }
    var vs = window.voucherSettingsByMetal[metalWise];
    if (!vs && window.voucherSettingsByMetal) {
        var mwLower = String(metalWise).toLowerCase().trim();
        for (var k in window.voucherSettingsByMetal) {
            if (Object.prototype.hasOwnProperty.call(window.voucherSettingsByMetal, k) && String(k).toLowerCase().trim() === mwLower) {
                vs = window.voucherSettingsByMetal[k];
                break;
            }
        }
    }
    if (vs && vs.default_discount_type) {
        var d = String(vs.default_discount_type).trim();
        // Legacy DB / voucher "On Amount" → use Fix for new lines & scans
        if (d && d !== 'On Amount') return d;
    }
    return fallback;
}

/**
 * Fill modal row fields from tbl_stock_journal row (returned as product.stock_journal from get-product-by-barcode.php).
 */
function applyStockJournalToModalRow(row, sj) {
    if (!row || !sj || typeof sj !== 'object') return;
    const textCols = [
        ['rfid_code', 'rfid'],
        ['voucher_type', 'voucher-type'],
        ['huid_no', 'huid'],
        ['design_no', 'design-no'],
    ];
    textCols.forEach(function(pair) {
        var k = pair[0], col = pair[1];
        if (sj[k] != null && sj[k] !== '') setModalCellInput(row, col, sj[k]);
    });
    var inputMap = [
        ['pkt_wt', 'pkt-wt'], ['pkt_less_wt', 'pkt-less-wt'],
        ['gross_weight', 'gross-wt'], ['less_weight', 'less-wt'], ['net_weight', 'net-wt'],
        ['quantity', 'quantity'], ['rate', 'rate'], ['rate', 'metal-rate'], ['purity', 'purity'], ['purity_weight', 'purity-wt'],
        ['final_weight', 'final-wt'], ['wastage_per', 'wastage-per'], ['wastage_wt', 'wastage-wt'],
        ['alloy_wt', 'alloy-wt'], ['gold_loss_1', 'gold-loss1'], ['gold_loss_2', 'gold-loss2'],
        ['setting_charge', 'setting-charge'], ['metal_value', 'metal-value'], ['metal_cost', 'metal-cost'],
        ['amount', 'amount'], ['making_rate', 'making-rate'], ['making_amount', 'making-amount'],
        ['making_cost', 'making-cost'], ['minimum_price', 'min-price'],
        ['stone_weight', 'stone-weight'], ['stone_rate', 'stone-rate'], ['stone_amount', 'stone-amount'],
        ['stone_cost', 'stone-cost'], ['diamond_amount', 'diamond-amount'],
        ['purchase_amount', 'purchase-amount'], ['sale_percent', 'sale-percent'], ['sale_amount', 'sale-amount'],
        ['net_amount', 'net-amt'], ['net_amt_with_tax', 'net-amt-tax'], ['tax_amount', 'tax'],
        ['requested_purity', 'requested-purity'], ['requested', 'requested'],
        ['discount_per', 'discount-per'], ['discount_amount', 'discount-amount'], ['discount', 'discount'],
        ['hallmark_amount', 'hallmark-amount'], ['hallmark_rate', 'hallmark-rate'],
        ['reverse', 'reverse'], ['other_weight', 'other-weight'], ['other_rate', 'other-rate'],
        ['other_amount', 'other-amount'],
    ];
    inputMap.forEach(function(pair) {
        var k = pair[0], col = pair[1];
        if (!Object.prototype.hasOwnProperty.call(sj, k)) return;
        var v = sj[k];
        if (v === null || v === '') return;
        if (typeof v === 'string' && String(v).trim() === '') return;
        setModalCellInput(row, col, v);
    });
    if (sj.other_info != null && sj.other_info !== '') setModalCellInput(row, 'other-info', sj.other_info);
    var catSel = row.querySelector('[data-column="category"] select');
    if (catSel && sj.category) selectOptionByValueOrText(catSel, sj.category);
    var locSel = row.querySelector('[data-column="location"] select');
    if (locSel && sj.location) selectOptionByValueOrText(locSel, sj.location);
    var calcSel = row.querySelector('[data-column="calculation"] select');
    if (calcSel && sj.calculation) setModalSelectIfOptionExists(row, 'calculation', sj.calculation);
    var makingTypeSel = row.querySelector('[data-column="making-type"] select');
    if (makingTypeSel && sj.making_type) setModalSelectIfOptionExists(row, 'making-type', sj.making_type);
    // Discount Type: use voucher default (e.g. Fix), not legacy journal discount_type (often On Amount)
    var discSel = row.querySelector('[data-column="discount-type"] select');
    if (discSel) {
        setModalSelectIfOptionExists(row, 'discount-type', getVoucherDefaultDiscountTypeForModalRow(row));
    }
    var stoneChargeSel = row.querySelector('[data-column="stone-charge-type"] select');
    if (stoneChargeSel && sj.stone_charge_type) setModalSelectIfOptionExists(row, 'stone-charge-type', sj.stone_charge_type);
    var otherChargeSel = row.querySelector('[data-column="other-charge-type"] select');
    if (otherChargeSel && sj.other_charge_type) setModalSelectIfOptionExists(row, 'other-charge-type', sj.other_charge_type);
    var caratSel = row.querySelector('[data-column="carat"] select');
    if (caratSel && typeof populateCaratSelectForModalRow === 'function') populateCaratSelectForModalRow(caratSel, row);
    if (caratSel && (sj.karat != null && sj.karat !== '')) selectCaratFromStockJournal(caratSel, sj.karat);
    if (sj.id) row.setAttribute('data-stock-journal-id', String(sj.id));
    if (typeof auragoldFinalizeStockJournalModalRow === 'function') {
        auragoldFinalizeStockJournalModalRow(row, sj);
    }
}

// Populate row with product data
// opts.fromBarcode: true when loaded via barcode scan (merge stock journal + stock weights). Omit or false for manual product pick (defaults: Metal Qty 1, Weight 0, Purity % 1).
function populateRowWithProduct(row, product, opts) {
    opts = opts || {};
    const fromBarcode = !!opts.fromBarcode;
    // Update product name
    const productInput = row.querySelector('[data-column="product"] input');
    var pName = String(product.name || product.product_name || product.alternate_name || '').trim();
    var metalSuffix = product.metal_name ? (' - ' + product.metal_name) : '';
    const productName = pName ? (pName + metalSuffix) : String(metalSuffix).replace(/^\s*-\s*/, '').trim();
    if (productInput) {
        productInput.value = productName;
        productInput.readOnly = true;
    }
    if (productName) {
        row.setAttribute('data-product-name', productName);
    } else {
        row.removeAttribute('data-product-name');
    }
    
    // Update row data attributes
    row.setAttribute('data-product-id', product.id || '');
    row.setAttribute('data-characteristic-id', product.characteristic_id || '');
    if (product.purchase_invoice_item_id != null && product.purchase_invoice_item_id !== '') {
        row.setAttribute('data-purchase-invoice-item-id', String(product.purchase_invoice_item_id));
    } else {
        row.removeAttribute('data-purchase-invoice-item-id');
    }
    // Prefer product metal (from stock / characteristics) so barcode scans land on the correct tab (e.g. Diamond & Stones), not the tab that was active (e.g. Gold).
    var pid = (product.metal_id != null && product.metal_id !== '') ? String(product.metal_id) : '';
    row.setAttribute('data-metal-id', pid || (typeof window.currentMetalId !== 'undefined' ? window.currentMetalId : '') || '');
    var metalNameAttr = String(product.metal_name || product.metal_type || product.metal || '').trim();
    if (!metalNameAttr && typeof getActiveProductModalMetalName === 'function') {
        metalNameAttr = String(getActiveProductModalMetalName() || '').trim();
    }
    if (metalNameAttr) {
        row.setAttribute('data-metal-name', metalNameAttr);
    } else {
        row.removeAttribute('data-metal-name');
    }
    var bpx = (product.barcode_prefix != null && String(product.barcode_prefix).trim() !== '') ? String(product.barcode_prefix).trim() : '';
    var bdg = parseInt(product.barcode_digits, 10);
    if (bpx) row.setAttribute('data-barcode-prefix', bpx);
    else row.removeAttribute('data-barcode-prefix');
    if (!isNaN(bdg) && bdg >= 1) row.setAttribute('data-barcode-digits', String(bdg));
    else row.removeAttribute('data-barcode-digits');
    
    var caratSelectPm = row.querySelector('.carat-select');
    if (caratSelectPm && typeof populateCaratSelectForModalRow === 'function') {
        populateCaratSelectForModalRow(caratSelectPm, row);
    }
    
    // Update checkbox
    const checkbox = row.querySelector('.product-checkbox');
    if (checkbox) {
        checkbox.setAttribute('data-product-id', product.id || '');
        checkbox.setAttribute('data-characteristic-id', product.characteristic_id || '');
    }
    
    // Update ID column
    const idCell = row.querySelector('[data-column="id"]');
    if (idCell) {
        idCell.textContent = product.id || '';
    }
    
    var dcFromProductSi = product.diamond_category || product.category || (product.stock_journal && product.stock_journal.category) || '';
    var dcTrim = dcFromProductSi ? String(dcFromProductSi).trim() : '';
    // New rows often get API "Select Category" options when the active tab was not Diamond; purchase saves Jewellery/Diamonds/GemStones — rebuild options first.
    if (dcTrim && ['Jewellery', 'Diamonds', 'GemStones'].indexOf(dcTrim) !== -1) {
        var catSelDiamond = row.querySelector('[data-column="category"] select');
        if (catSelDiamond && typeof populateCategorySelectForModal === 'function') {
            populateCategorySelectForModal(catSelDiamond, true);
        }
    }
    if (dcTrim !== '') {
        setModalSelectIfOptionExists(row, 'category', dcTrim);
        var calcSelDcSi = row.querySelector('[data-column="calculation"] select');
        if (calcSelDcSi && typeof applyCalculationSelectOptionsForRow === 'function') {
            var useDiamondCalcOpts = ['Jewellery', 'Diamonds', 'GemStones'].indexOf(dcTrim) !== -1;
            applyCalculationSelectOptionsForRow(calcSelDcSi, row, useDiamondCalcOpts || (typeof isDiamondTabActive === 'function' && isDiamondTabActive()));
        }
    }
    
    // Update Design No (prefer stock journal design_no when present on barcode scan)
    const designNoInput = row.querySelector('[data-column="design-no"] input');
    if (designNoInput) {
        const sjDn = fromBarcode && product.stock_journal && product.stock_journal.design_no;
        if (sjDn) designNoInput.value = sjDn;
        else if (product.article) designNoInput.value = product.article;
    }
    
    // Gross weight: barcode = journal / product / opening; manual pick = 0
    const grossWtInput = row.querySelector('[data-column="gross-wt"] input');
    if (grossWtInput) {
        let gw = null;
        if (fromBarcode) {
            if (product.stock_journal && product.stock_journal.gross_weight != null && product.stock_journal.gross_weight !== '') {
                gw = product.stock_journal.gross_weight;
            } else if (product.gross_weight != null && product.gross_weight !== '') {
                gw = product.gross_weight;
            } else if (product.opening_weight != null && product.opening_weight !== '') {
                gw = product.opening_weight;
            }
        }
        grossWtInput.value = (gw != null && gw !== '') ? String(gw) : '0';
    }
    
    // Purity %: manual pick = 1; barcode = product / journal (applyStockJournal may refine)
    const purityInput = row.querySelector('[data-column="purity"] input');
    if (purityInput) {
        const p = (fromBarcode && product.opening_purity != null && product.opening_purity !== '') ? product.opening_purity : 1;
        purityInput.value = p;
    }
    
    // Update Barcode
    const barcodeInput = row.querySelector('[data-column="barcode"] input');
    if (barcodeInput && product.barcode) {
        barcodeInput.value = product.barcode;
    }
    if (product.barcode) {
        row.setAttribute('data-barcode', product.barcode);
    }
    
    // Final Weight: manual = 0; barcode = saved weights
    const finalWtInput = row.querySelector('[data-column="final-wt"] input');
    if (finalWtInput) {
        if (fromBarcode && product.final_weight) {
            finalWtInput.value = product.final_weight;
        } else if (fromBarcode && product.opening_weight) {
            finalWtInput.value = product.opening_weight;
        } else {
            finalWtInput.value = '0';
        }
    }
    
    // Update Rate
    const rateInput = row.querySelector('[data-column="rate"] input');
    if (rateInput && product.rate) {
        rateInput.value = product.rate;
    }
    // Metal Rate column (Jewellery calcs use metal-rate, not rate — sync from product / journal rate)
    const metalRateInput = row.querySelector('[data-column="metal-rate"] input');
    if (metalRateInput) {
        var mr = (product.metal_rate != null && product.metal_rate !== '') ? product.metal_rate : product.rate;
        if (mr != null && mr !== '' && !(typeof mr === 'string' && String(mr).trim() === '')) {
            metalRateInput.value = mr;
        }
    }
    
    // Metal Qty / Weight column: manual pick always defaults to 1; barcode = stock / journal
    var mq = 1;
    if (fromBarcode) {
        if (product.metal_qty != null && product.metal_qty !== '') mq = product.metal_qty;
        else if (product.opening_qty != null && product.opening_qty !== '') mq = product.opening_qty;
        else if (product.quantity != null && product.quantity !== '') mq = product.quantity;
        mq = (parseFloat(mq) > 0 ? parseFloat(mq) : 1);
    }
    row.querySelectorAll('[data-column="metal-qty"] input').forEach(function (metalQtyInput) {
        metalQtyInput.value = String(mq);
    });
    const metalWtInput = row.querySelector('[data-column="metal-weight"] input');
    if (metalWtInput) {
        let mw = 0;
        if (fromBarcode) {
            if (product.metal_weight != null && product.metal_weight !== '') mw = product.metal_weight;
            else if (product.stock_journal && product.stock_journal.gross_weight != null && product.stock_journal.gross_weight !== '') mw = product.stock_journal.gross_weight;
            else if (product.opening_weight != null && product.opening_weight !== '') mw = product.opening_weight;
            else if (product.gross_weight != null && product.gross_weight !== '') mw = product.gross_weight;
        }
        metalWtInput.value = mw;
    }
    
    // GST: data-* + data-gst-line-taxes from product; tax % field is set in calculateModalRowNetWeight (scope filter)
    const taxPercentInput = row.querySelector('[data-column="tax-percent"] input');
    row.setAttribute('data-gst-local-pct', (product.gst_local_percent != null && product.gst_local_percent !== '') ? String(product.gst_local_percent) : '');
    row.setAttribute('data-gst-interstate-pct', (product.gst_interstate_percent != null && product.gst_interstate_percent !== '') ? String(product.gst_interstate_percent) : '');
    row.setAttribute('data-gst-invoice-slab-pct', typeof window.auragoldGstInvoiceSlabFromProductPayload === 'function' ? window.auragoldGstInvoiceSlabFromProductPayload(product) : '');
    if (product.gst_tax_breakdown && typeof window.auragoldGstLineTaxesFromProductPayload === 'function') {
        var ltJson = window.auragoldGstLineTaxesFromProductPayload(product);
        if (ltJson) row.setAttribute('data-gst-line-taxes', ltJson);
        else row.removeAttribute('data-gst-line-taxes');
    } else {
        row.removeAttribute('data-gst-line-taxes');
    }
    if (typeof window.auragoldGstSetProductTaxesAttrOnRow === 'function') {
        window.auragoldGstSetProductTaxesAttrOnRow(row, product);
    }
    if (taxPercentInput && typeof window.setSaleInvoiceGstTaxPercentDisplay === 'function') {
        window.setSaleInvoiceGstTaxPercentDisplay(row, taxPercentInput);
    }
    
    if (fromBarcode && product.stock_journal && typeof product.stock_journal === 'object') {
        applyStockJournalToModalRow(row, product.stock_journal);
    } else {
        // No journal row: still apply voucher default (Fix) for discount type
        var defDt = typeof getVoucherDefaultDiscountTypeForModalRow === 'function' ? getVoucherDefaultDiscountTypeForModalRow(row) : 'Fix';
        setModalSelectIfOptionExists(row, 'discount-type', defDt);
        if (caratSelectPm && product.carat != null && product.carat !== '' && typeof selectCaratFromStockJournal === 'function') {
            selectCaratFromStockJournal(caratSelectPm, product.carat);
        }
    }
    // Journal apply can run before diamond options exist; re-apply saved Diamond Category from purchase / API.
    var dcAfterJournal = product.diamond_category || product.category || (product.stock_journal && product.stock_journal.category) || '';
    var dcAfterTrim = dcAfterJournal ? String(dcAfterJournal).trim() : '';
    if (dcAfterTrim && ['Jewellery', 'Diamonds', 'GemStones'].indexOf(dcAfterTrim) !== -1) {
        var catAfter = row.querySelector('[data-column="category"] select');
        if (catAfter && typeof populateCategorySelectForModal === 'function') {
            populateCategorySelectForModal(catAfter, true);
        }
        if (catAfter && typeof selectOptionByValueOrText === 'function') {
            selectOptionByValueOrText(catAfter, dcAfterTrim);
        }
    }
    var dcRowTag = product.diamond_category || product.category || (product.stock_journal && product.stock_journal.category) || '';
    var dcRowTagTrim = dcRowTag ? String(dcRowTag).trim() : '';
    if (dcRowTagTrim && ['Jewellery', 'Diamonds', 'GemStones'].indexOf(dcRowTagTrim) !== -1) {
        row.setAttribute('data-diamond-category', dcRowTagTrim);
    } else {
        row.removeAttribute('data-diamond-category');
    }
    
    // Trigger calculation to update all calculated fields
    if (typeof window.auragoldApplyConfiguredSalePercentAndMaking === 'function') {
        window.auragoldApplyConfiguredSalePercentAndMaking(row, false);
    } else if (typeof window.auragoldApplyConfiguredMaking === 'function') {
        window.auragoldApplyConfiguredMaking(row, false);
    }
    calculateModalRowNetWeight(row);
    auragoldEnsureModalRowMetalQtyDefault(row);
    if (typeof syncDiamondTabSharedBarcodes === 'function') syncDiamondTabSharedBarcodes();
    if (typeof window.auragoldApplyJournalImagesToModalRowPhoto === 'function') {
        window.auragoldApplyJournalImagesToModalRowPhoto(row, product);
    }
    if (typeof window.refreshProductRowDetailFormIfOpen === 'function') {
        window.refreshProductRowDetailFormIfOpen(row);
    }
}

// Close product search modal
function closeProductSearchModal() {
    const modal = document.getElementById('productSearchModal');
    if (modal) {
        modal.remove();
    }
    if (typeof window.auragoldResumeBootstrapModalFocusTrap === 'function') {
        window.auragoldResumeBootstrapModalFocusTrap();
    }
    auragoldSetCurrentProductSearchRow(null);
}

    let productJustSaved = false; // Flag to track if product was just saved

    function bindProductModalRowSearchHandlers(row) {
        if (!row) return;
        var productInput = row.querySelector('[data-column="product"] input');
        if (productInput) {
            productInput.addEventListener('click', function (e) {
                e.stopPropagation();
                openProductSearchModal(row);
            });
            productInput.style.cursor = 'pointer';
            productInput.readOnly = true;
        }
        row.addEventListener('click', function (e) {
            if (e.target.closest('[data-column="product"]')) {
                openProductSearchModal(row);
                return;
            }
            if (e.target.type === 'checkbox' || e.target.closest('[data-column="actions"]')) return;
        });
    }

    document.addEventListener('click', function (e) {
        var th = e.target.closest('#productSelectionModal th[data-column="product"] .add-product-icon');
        if (!th) return;
        e.stopPropagation();
        e.preventDefault();
        var tbody = document.getElementById('productListBody');
        var row = tbody ? tbody.querySelector('tr.product-row:last-child') : null;
        if (row) openProductSearchModal(row);
    });

    window.openProductSearchModal = openProductSearchModal;
    window.closeProductSearchModal = closeProductSearchModal;
    window.searchProducts = searchProducts;
    window.selectProductFromSearch = selectProductFromSearch;
    window.parseProductSearchItemFromClick = parseProductSearchItemFromClick;
    window.auragoldBindProductSearchResultItems = auragoldBindProductSearchResultItems;
    window.auragoldSetCurrentProductSearchRow = auragoldSetCurrentProductSearchRow;
    window.auragoldEnsureModalRowMetalQtyDefault = auragoldEnsureModalRowMetalQtyDefault;
    window.populateRowWithProduct = populateRowWithProduct;
    window.bindProductModalRowSearchHandlers = bindProductModalRowSearchHandlers;
    window.setModalCellInput = setModalCellInput;
    window.setModalSelectIfOptionExists = setModalSelectIfOptionExists;
    window.selectOptionByValueOrText = selectOptionByValueOrText;
    window.selectCaratFromStockJournal = selectCaratFromStockJournal;
    window.applyStockJournalToModalRow = applyStockJournalToModalRow;

    /** #editRowModal (edit icon) is injected by invoice pages with z-index 10000 — stack above product selection. */
    function onEditRowModalOpen() {
        document.body.classList.add('edit-row-modal-open');
        if (typeof window.auragoldSuspendBootstrapModalFocusTrap === 'function') {
            window.auragoldSuspendBootstrapModalFocusTrap('#editRowModal');
        }
    }

    function onEditRowModalClose() {
        document.body.classList.remove('edit-row-modal-open');
        if (typeof window.auragoldResumeBootstrapModalFocusTrap === 'function') {
            window.auragoldResumeBootstrapModalFocusTrap();
        }
    }

    if (!window.__auragoldEditRowModalStackFix) {
        window.__auragoldEditRowModalStackFix = true;

        var origCloseEditRowModal = window.closeEditRowModal;
        window.closeEditRowModal = function () {
            if (typeof origCloseEditRowModal === 'function') {
                origCloseEditRowModal.apply(this, arguments);
            } else {
                var modal = document.getElementById('editRowModal');
                if (modal) {
                    modal.remove();
                }
                window.currentEditingRow = null;
            }
            onEditRowModalClose();
        };

        if (typeof MutationObserver !== 'undefined') {
            new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node.nodeType === 1 && node.id === 'editRowModal') {
                            onEditRowModalOpen();
                        }
                    });
                    mutation.removedNodes.forEach(function (node) {
                        if (node.nodeType === 1 && node.id === 'editRowModal') {
                            onEditRowModalClose();
                        }
                    });
                });
            }).observe(document.body, { childList: true });
        }
    }
})();