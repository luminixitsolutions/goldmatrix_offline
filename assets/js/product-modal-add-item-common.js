/**
 * Product Modal Add Item - Common Calculation & Logic
 * Shared across Sale Invoice, Purchase Invoice, and other pages that use the Add Item modal.
 * Include this file before page-specific scripts that use the modal.
 */

(function() {
    'use strict';

    // ========== CONSTANTS ==========
    /** Canonical DOM order for Product Selection modal (must match thead row 2 + checkbox + actions). */
    const DIAMOND_TAB_VISIBLE_COLUMNS = ['checkbox','id','rfid','voucher-type','photo','barcode','design-no','huid','item-code','category','product-category','calculation','product','location','pkt-wt','pkt-less-wt','gross-wt','stone-weight','less-wt','net-wt','quantity','rate','fc-amount','diamond-line-metal-value','rapnet-valuation','setting-charge','stone-amount','mark-up-amount','mark-up-per','amount','metal-qty','metal-weight','carat','purity','purity-wt','gold-loss1','gold-loss2','metal-loss-value','wastage-per','wastage-wt','metal-rate','metal-value','purchase-rate','metal-cost','requested-purity','requested','final-wt','alloy-wt','platinum-weight','platinum-karat','platinum-purity','platinum-purity-wt','platinum-rate','platinum-wastage-per','platinum-wastage-wt','platinum-amount','discount-type','discount-per','discount-amount','discount','making-type','making-rate','making-discount-amt','making-amount','making-actual-value','making-cost','min-price','minimum','stone-charge-type','stone-rate','stone-cost','diamond-amount','purchase-amount','sale-percent','sale-amount','sale-amount-with','net-amt','tax-type','tax-percent','tax','other-charge-type','other-weight','other-rate','other-info','other-amount','certificate-amount','certificate-no','certificate-link','video-link','cut','color','seive-size','size','shape','clarity','unit-price','hallmark-amount','hallmark-rate','net-amt-tax','reverse','images','actions'];
    /** Default column order for non–Diamond & Stones (Gold/Silver/…) metal tab — includes Stock Journal right side (net-amt-tax, reverse, images, actions). */
    const METAL_GROUP_VISIBLE_COLUMNS = [
        'checkbox',
        'id',
        'calculation',
        'product',
        'location',
        'quantity',
        'metal-qty',
        'metal-group',
        'metal-weight',
        'carat',
        'purity',
        'purity-wt',
        'metal-rate',
        'metal-value',
        'purchase-rate',
        'metal-cost',
        'amount',
        'gold-loss1',
        'gold-loss2',
        'metal-loss-value',
        'wastage-per',
        'wastage-wt',
        'final-wt',
        'purchase-amount',
        'sale-percent',
        'sale-amount',
        'sale-amount-with',
        'net-amt',
        'tax-type',
        'tax-percent',
        'tax',
        'net-amt-tax',
        'reverse',
        'images',
        'actions'
    ];
    const METAL_GROUP_HEADER_LABELS = {
        'checkbox': 'Active',
        'id': 'Id',
        'category': 'Diamond category',
        'product-category': 'Product category',
        'calculation': 'Calculation',
        'product': 'Product',
        'location': 'Location',
        'quantity': 'Qty',
        'metal-qty': 'Metal Qty',
        'metal-group': 'Metal (group)',
        'metal-weight': 'Weight',
        'carat': 'Karat',
        'purity': 'Purity %',
        'purity-wt': 'Purity Wt',
        'metal-rate': 'Metal Rate',
        'metal-value': 'Metal Value',
        'purchase-rate': 'Purchase Rate',
        'metal-cost': 'Metal Cost',
        'amount': 'Amount',
        'gold-loss1': 'Gold Loss 1',
        'gold-loss2': 'Gold Loss 2',
        'metal-loss-value': 'Loss Value',
        'wastage-per': 'Wastage %',
        'wastage-wt': 'Wastage Wt',
        'final-wt': 'Final Wt',
        'purchase-amount': 'Purchase Amount',
        'sale-percent': 'Sale Percentages',
        'sale-amount': 'Sale Amount',
        'sale-amount-with': 'Sale Amount With Tax',
        'net-amt': 'Net Amt',
        'tax-type': 'Tax Type',
        'tax-percent': 'Tax %',
        'tax': 'Tax',
        'net-amt-tax': 'Net Amt+Tax',
        'reverse': 'Reverse',
        'images': 'Images',
        'actions': 'Action'
    };
    const METAL_GROUP_PANEL_COLUMNS = [
        { dataColumn: 'quantity', label: 'Qty' },
        { dataColumn: 'metal-qty', label: 'Metal Qty' },
        { dataColumn: 'metal-weight', label: 'Weight' },
        { dataColumn: 'carat', label: 'Karat' },
        { dataColumn: 'purity', label: 'Purity %' },
        { dataColumn: 'purity-wt', label: 'Purity Wt' },
        { dataColumn: 'metal-rate', label: 'Metal Rate' },
        { dataColumn: 'metal-value', label: 'Metal Value' },
        { dataColumn: 'purchase-rate', label: 'Purchase Rate' },
        { dataColumn: 'metal-cost', label: 'Metal Cost' },
        { dataColumn: 'amount', label: 'Amount' },
        { dataColumn: 'gold-loss1', label: 'Gold Loss 1' },
        { dataColumn: 'gold-loss2', label: 'Gold Loss 2' },
        { dataColumn: 'metal-loss-value', label: 'Loss Value' },
        { dataColumn: 'wastage-per', label: 'Wastage %' },
        { dataColumn: 'wastage-wt', label: 'Wastage Wt' },
        { dataColumn: 'final-wt', label: 'Final Wt' },
        { dataColumn: 'purchase-amount', label: 'Purchase Amount' },
        { dataColumn: 'sale-percent', label: 'Sale Percentages' },
        { dataColumn: 'sale-amount', label: 'Sale Amount' },
        { dataColumn: 'sale-amount-with', label: 'Sale Amount With Tax' },
        { dataColumn: 'net-amt', label: 'Net Amt' },
        { dataColumn: 'tax-type', label: 'Tax Type' },
        { dataColumn: 'tax-percent', label: 'Tax %' },
        { dataColumn: 'tax', label: 'Tax' },
        { dataColumn: 'net-amt-tax', label: 'Net Amt+Tax' },
        { dataColumn: 'reverse', label: 'Reverse' }
    ];
    const DIAMOND_TAB_HEADER_LABELS = {
        'checkbox': 'Active', 'id': 'Id', 'rfid': 'RFIDCode', 'voucher-type': 'Style', 'photo': 'Photo', 'barcode': 'Barcode No.', 'design-no': 'Design No', 'huid': 'HUID No.', 'item-code': 'Item Code', 'location': 'Location', 'category': 'Diamond Category', 'product-category': 'Product category', 'calculation': 'Calculation Type', 'product': 'Product', 'quantity': 'Quantity', 'metal-qty': 'Metal Qty', 'carat': 'Carat', 'pkt-wt': 'Pkt. Wt.', 'pkt-less-wt': 'PKt. Less Wt.', 'gross-wt': 'Gross Wt.', 'less-wt': 'D.Weight', 'fc-amount': 'FC Amount', 'diamond-line-metal-value': 'Metal Value', 'rapnet-valuation': 'RapNet Valuation', 'mark-up-amount': 'Mark Up Amount', 'mark-up-per': 'Mark Up %', 'gold-loss1': 'Loss Wt.', 'gold-loss2': 'Loss Wt. Per', 'metal-loss-value': 'Loss Value', 'setting-charge': 'Setting Charge', 'net-wt': 'Net Wt.', 'stone-weight': 'Carat', 'metal-weight': 'Gold Wt', 'purity': 'Purity %', 'purity-wt': 'Purity Wt', 'wastage-per': 'Wastage Per', 'wastage-wt': 'Wastage Wt', 'metal-rate': 'Metal Rate', 'final-wt': 'Final Wt.', 'rate': 'Rate', 'metal-value': 'Gold Amount', 'amount': 'Amount', 'platinum-weight': 'Weight', 'platinum-karat': 'Karat', 'platinum-purity': 'Purity %', 'platinum-purity-wt': 'Purity Wt', 'platinum-rate': 'Rate', 'platinum-wastage-per': 'Wastage Per', 'platinum-wastage-wt': 'Wastage Wt', 'platinum-amount': 'Amount', 'certificate-amount': 'Certificate Amount', 'certificate-no': 'Certificate No.', 'certificate-link': 'Certificate Link', 'video-link': 'Video Link', 'seive-size': 'SeiveSize', 'unit-price': 'Unit Price', 'metal-group': 'Metal (group)', 'discount-type': 'Discount (group) - Type', 'discount-per': 'Discount (group) - Per.', 'discount-amount': 'Disc. base (for %)', 'discount': 'Discount (group)', 'making-type': 'Making (group) - Type', 'making-rate': 'Making (group) - Rate', 'making-amount': 'Making (group) - Amount', 'making-actual-value': 'Making (group) - Actual Value', 'min-price': 'Minimum Price', 'minimum': 'Minimum Price Code', 'stone-charge-type': 'Type', 'stone-rate': 'Rate', 'stone-amount': 'Setting Charge Amount', 'diamond-amount': 'Amount', 'purchase-amount': 'Purchase Amount', 'sale-percent': 'Sale Percentages', 'sale-amount': 'Sale Amount', 'sale-amount-with': 'Sale Amount With Tax', 'net-amt': 'Net Amt', 'tax-type': 'Tax', 'tax-percent': 'Tax %', 'tax': 'Tax', 'other-charge-type': 'Other Charge Type', 'other-weight': 'Other Weight', 'other-rate': 'Other Rate', 'other-info': 'Other Info', 'other-amount': 'Other Amount', 'hallmark-amount': 'Hallmark Amount', 'hallmark-rate': 'HallMark Rate', 'net-amt-tax': 'Net Amt+Tax', 'reverse': 'Reverse', 'actions': 'action', 'cut': 'Cut', 'color': 'Color', 'size': 'Size', 'shape': 'Shape', 'clarity': 'Clarity'
    };
    const DIAMOND_CATEGORY_OPTIONS = [
        { value: 'Diamonds', name: 'Diamonds' },
        { value: 'GemStones', name: 'GemStones' },
        { value: 'Jewellery', name: 'Jewellery' }
    ];
    /** Loose / Certified Diamond & Stones tab — row filter + diamond category column. */
    const LOOSE_DIAMOND_CATEGORY_OPTIONS = [
        { value: 'Diamond', name: 'Diamond' },
        { value: 'Stone', name: 'Stone' }
    ];
    const DIAMOND_CATEGORY_PLACEHOLDER = 'Select Diamond Category';
    const DIAMOND_CALCULATION_OPTIONS = ['Carat X Rate', 'Rate X Gross Wt', 'Rate X Purity Wt', 'Rate X Net Wt', 'Rate X Final Wt', 'Fix', 'Qty X Rate', 'Stone Charge', 'Attach Image Type'];
    /** Diamond Category = Jewellery (six weight/rate modes + Fix). */
    const JEWELLERY_DIAMOND_CATEGORY_CALCULATION_OPTIONS = ['Carat X Rate', 'Rate X Gross Wt', 'Rate X Purity Wt', 'Rate X Net Wt', 'Rate X Final Wt', 'Fix'];
    /** Diamond Category = Diamonds or GemStones. */
    const DIAMONDS_GEMSTONES_CALCULATION_OPTIONS = ['Carat X Rate', 'Fix', 'Qty X Rate', 'Quantity X Rate'];
    /** @deprecated use DIAMONDS_GEMSTONES_CALCULATION_OPTIONS */
    const DIAMONDS_CATEGORY_CALCULATION_OPTIONS = DIAMONDS_GEMSTONES_CALCULATION_OPTIONS;
    const FULL_CALCULATION_OPTIONS = ['Carat X Rate', 'Rate X Gross Wt', 'Rate X Purity Wt', 'Rate X Net Wt', 'Rate X Final Wt', 'Fix', 'Qty X Rate', 'Stone Charge', 'Attach Image Type'];
    const JEWELLERY_CALCULATION_OPTIONS = ['Metal Rate x Metal Weight', 'Metal Carat x Metal Rate', 'Metal Rate x Metal Purity'];

    // Expose constants to window for pages that need them
    window.DIAMOND_TAB_VISIBLE_COLUMNS = DIAMOND_TAB_VISIBLE_COLUMNS;
    window.METAL_GROUP_VISIBLE_COLUMNS = METAL_GROUP_VISIBLE_COLUMNS;
    window.METAL_GROUP_HEADER_LABELS = METAL_GROUP_HEADER_LABELS;
    window.METAL_GROUP_PANEL_COLUMNS = METAL_GROUP_PANEL_COLUMNS;
    window.DIAMOND_TAB_HEADER_LABELS = DIAMOND_TAB_HEADER_LABELS;
    window.DIAMOND_CATEGORY_OPTIONS = DIAMOND_CATEGORY_OPTIONS;
    window.LOOSE_DIAMOND_CATEGORY_OPTIONS = LOOSE_DIAMOND_CATEGORY_OPTIONS;
    window.DIAMOND_CATEGORY_PLACEHOLDER = DIAMOND_CATEGORY_PLACEHOLDER;
    window.DIAMOND_CALCULATION_OPTIONS = DIAMOND_CALCULATION_OPTIONS;
    window.JEWELLERY_DIAMOND_CATEGORY_CALCULATION_OPTIONS = JEWELLERY_DIAMOND_CATEGORY_CALCULATION_OPTIONS;
    window.DIAMONDS_GEMSTONES_CALCULATION_OPTIONS = DIAMONDS_GEMSTONES_CALCULATION_OPTIONS;
    window.DIAMONDS_CATEGORY_CALCULATION_OPTIONS = DIAMONDS_CATEGORY_CALCULATION_OPTIONS;
    window.FULL_CALCULATION_OPTIONS = FULL_CALCULATION_OPTIONS;
    window.JEWELLERY_CALCULATION_OPTIONS = JEWELLERY_CALCULATION_OPTIONS;

    // ========== UTILITY ==========
    /** Keep up to 6 decimal places (opening stock / gross wt). Do not round to 3dp. */
    function auragoldFormatWeight(v) {
        if (v === '' || v == null) return '0';
        var raw = String(v).trim().replace(/,/g, '');
        if (raw !== '' && isFinite(Number(raw)) && raw.search(/e/i) === -1) {
            var neg = raw.charAt(0) === '-';
            var abs = raw.replace(/^[+-]/, '');
            var parts = abs.split('.');
            var intPart = parts[0].replace(/^0+(?=\d)/, '') || '0';
            var dec = parts[1] || '';
            if (dec.length > 6) dec = dec.substring(0, 6);
            dec = dec.replace(/0+$/, '');
            var out = intPart + (dec ? '.' + dec : '');
            if (neg && out !== '0') out = '-' + out;
            return out;
        }
        var x = parseFloat(v);
        if (!isFinite(x)) return '0';
        var s = x.toFixed(6).replace(/\.?0+$/, '');
        return s === '-0' ? '0' : s;
    }
    window.auragoldFormatWeight = auragoldFormatWeight;

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    window.escapeHtml = escapeHtml;

    /** True only for the dedicated "Diamond & Stones" metal tab (matches tbl_metal.display_name). Not names like "Silver Diamond". */
    function isDiamondStonesMetalDisplayName(name) {
        if (typeof name !== 'string') return false;
        return name.trim().toLowerCase().replace(/\s+/g, ' ') === 'diamond & stones';
    }
    window.isDiamondStonesMetalDisplayName = isDiamondStonesMetalDisplayName;

    /** "Loos Diamond" / "Loose Diamond" and similar (tbl_metal display_name). */
    function isLoosOrLooseDiamondMetalDisplayName(name) {
        if (typeof name !== 'string') return false;
        var n = name.trim().toLowerCase().replace(/\s+/g, ' ');
        if (n === 'loos diamond' || n === 'loose diamond') return true;
        if (n.indexOf('diamond') === -1) return false;
        return (/\bloos\b/.test(n) || /\bloose\b/.test(n));
    }
    window.isLoosOrLooseDiamondMetalDisplayName = isLoosOrLooseDiamondMetalDisplayName;

    function getActiveProductModalMetalName() {
        var activeTabBtn = document.querySelector('.product-category-tabs .category-tab-btn.active')
            || document.querySelector('#productSelectionModal .category-tab-btn.active');
        return (activeTabBtn && activeTabBtn.getAttribute('data-metal-name')) || (typeof currentMetalName !== 'undefined' ? currentMetalName : '');
    }
    window.getActiveProductModalMetalName = getActiveProductModalMetalName;

    function isCatalogueModalProductRow(row) {
        if (!row) return false;
        return row.hasAttribute('data-catalogue-set-index') || row.hasAttribute('data-catalogue-design');
    }
    window.isCatalogueModalProductRow = isCatalogueModalProductRow;

    /** Rows to commit: catalogue rows always; others only if visible on active metal tab. */
    function getCommitProductModalRows(allRows) {
        return Array.prototype.slice.call(allRows || []).filter(function (row) {
            if (!row) return false;
            if (isCatalogueModalProductRow(row)) return true;
            return row.style.display !== 'none';
        });
    }
    window.getCommitProductModalRows = getCommitProductModalRows;

    function isGoldOrSilverMetalTab() {
        var n = (getActiveProductModalMetalName() || '').toLowerCase().trim();
        return n === 'gold' || n === 'silver';
    }
    window.isGoldOrSilverMetalTab = isGoldOrSilverMetalTab;

    /** Imitation / Watches and Other / Services: purchase & sale amounts are typed (not metal-calc only). */
    function auragoldIsImitationOrOtherMetalTab() {
        var n = (getActiveProductModalMetalName() || '').toLowerCase().trim();
        if (!n) return false;
        if (n.indexOf('imitation') !== -1 || n.indexOf('watch') !== -1) return true;
        if (n.indexOf('other') !== -1 || n.indexOf('service') !== -1) return true;
        return false;
    }
    window.auragoldIsImitationOrOtherMetalTab = auragoldIsImitationOrOtherMetalTab;

    /** Stock Journal create / add-items pages. */
    function auragoldIsStockJournalContext() {
        if (window.isStockJournalPage === true) return true;
        if (typeof window.PRODUCT_MODAL_COLUMNS_PAGE === 'string' && /stock-journal/i.test(window.PRODUCT_MODAL_COLUMNS_PAGE)) return true;
        try {
            return !!(window.location && /stock-journal/i.test(String(window.location.pathname || '')));
        } catch (e) { return false; }
    }
    window.auragoldIsStockJournalContext = auragoldIsStockJournalContext;

    /** Product Opening voucher (stock journal URL or SJ defaults). */
    function auragoldIsProductOpeningContext() {
        if (window.PRODUCT_OPENING_SINGLE_PRODUCT === true) return true;
        if (typeof window.SJ_DEFAULT_VOUCHER_TYPE === 'string' && window.SJ_DEFAULT_VOUCHER_TYPE === 'product_opening') return true;
        try {
            var p = new URLSearchParams(window.location.search || '');
            return p.get('voucher') === 'product_opening';
        } catch (e) { return false; }
    }
    window.auragoldIsProductOpeningContext = auragoldIsProductOpeningContext;

    /** Pages/tabs where Purchase Amount & Sale Amount are user-typed. */
    function auragoldIsSaleInvoiceContext() {
        if (window.isSaleInvoicePage === true) return true;
        if (typeof window.PRODUCT_MODAL_COLUMNS_PAGE === 'string' && /sale-invoice/i.test(window.PRODUCT_MODAL_COLUMNS_PAGE)) return true;
        try {
            return !!(window.location && /sale-invoice\.php/i.test(String(window.location.pathname || '')));
        } catch (e) { return false; }
    }
    window.auragoldIsSaleInvoiceContext = auragoldIsSaleInvoiceContext;

    /** Pages/tabs where Purchase Amount, Sale Percentages & Sale Amount are user-typed. */
    function auragoldAllowManualPurchaseSaleAmounts() {
        return auragoldIsImitationOrOtherMetalTab()
            || auragoldIsProductOpeningContext()
            || auragoldIsStockJournalContext()
            || auragoldIsSaleInvoiceContext();
    }
    window.auragoldAllowManualPurchaseSaleAmounts = auragoldAllowManualPurchaseSaleAmounts;

    /** Parse "10", "10%", etc. to numeric percent. */
    function auragoldParsePercentInput(val) {
        if (val == null) return 0;
        var s = String(val).trim().replace(/%/g, '');
        if (!s) return 0;
        var n = parseFloat(s);
        return isNaN(n) ? 0 : n;
    }
    window.auragoldParsePercentInput = auragoldParsePercentInput;

    function auragoldSalePercentSettingsMap() {
        var m = window.AURAGOLD_SALE_PERCENT_SETTINGS;
        if (m && typeof m === 'object') {
            return {
                by_metal: (m.by_metal && typeof m.by_metal === 'object') ? m.by_metal : {},
                by_product: (m.by_product && typeof m.by_product === 'object') ? m.by_product : {}
            };
        }
        return { by_metal: {}, by_product: {} };
    }

    function auragoldLookupSalePercentSetting(row) {
        if (!row) return null;
        var map = auragoldSalePercentSettingsMap();
        var pid = parseInt(row.getAttribute('data-product-id') || '', 10) || 0;
        if (pid <= 0) {
            var idCell = row.querySelector('[data-column="id"]');
            if (idCell) {
                pid = parseInt(String(idCell.textContent || idCell.innerText || '').trim(), 10) || 0;
            }
        }
        if (pid > 0 && map.by_product[String(pid)]) {
            return map.by_product[String(pid)];
        }
        var metalName = (row.getAttribute('data-metal-name') || '').trim();
        if (!metalName && typeof getActiveProductModalMetalName === 'function') {
            metalName = String(getActiveProductModalMetalName() || '').trim();
        }
        if (metalName && map.by_metal[metalName]) {
            return map.by_metal[metalName];
        }
        return null;
    }

    /** Fill sale % from Set Software master when row has no manual override. */
    function auragoldApplyConfiguredSalePercent(row, force) {
        if (!row) return false;
        var salePercentInput = row.querySelector('[data-column="sale-percent"] input');
        if (!salePercentInput) return false;
        if (!force && row.getAttribute('data-manual-sale-percent') === '1') return false;
        if (!force && auragoldIsInputActivelyEdited(salePercentInput)) return false;
        var setting = auragoldLookupSalePercentSetting(row);
        if (!setting) {
            row.removeAttribute('data-sale-percent-tax-mode');
            return false;
        }
        var pct = parseFloat(setting.sale_percent) || 0;
        if (pct <= 0) {
            row.removeAttribute('data-sale-percent-tax-mode');
            return false;
        }
        salePercentInput.value = pct.toFixed(2);
        row.setAttribute('data-sale-percent-tax-mode', setting.tax_mode === 'with_tax' ? 'with_tax' : 'without_tax');
        return true;
    }
    window.auragoldApplyConfiguredSalePercent = auragoldApplyConfiguredSalePercent;

    /** Whether making fields from a sale-% master rule apply on this page (sales vs purchase). */
    function auragoldSalePercentMakingAppliesToContext(setting) {
        if (!setting) return false;
        var on = String(setting.apply_making_on || 'sales').toLowerCase().trim();
        if (on === 'purchase' || on === 'purchases' || on === 'buy') {
            return typeof auragoldIsPurchaseInvoiceContext === 'function' && auragoldIsPurchaseInvoiceContext();
        }
        // Default: sales — apply on sale invoices and non-purchase voucher pages
        if (typeof auragoldIsPurchaseInvoiceContext === 'function' && auragoldIsPurchaseInvoiceContext()) {
            return false;
        }
        return true;
    }
    window.auragoldSalePercentMakingAppliesToContext = auragoldSalePercentMakingAppliesToContext;

    /**
     * Fill Making Type + Making Rate from Set Software → Set Sale Percentage
     * when the rule matches this product/metal and apply_making_on matches the page.
     */
    function auragoldApplyConfiguredMaking(row, force) {
        if (!row) return false;
        var makingTypeSelect = row.querySelector('[data-column="making-type"] select');
        var makingRateInput = row.querySelector('[data-column="making-rate"] input');
        if (!makingTypeSelect && !makingRateInput) return false;
        if (!force && row.getAttribute('data-manual-making') === '1') return false;
        if (!force && makingRateInput && auragoldIsInputActivelyEdited(makingRateInput)) return false;
        var setting = auragoldLookupSalePercentSetting(row);
        if (!setting || !auragoldSalePercentMakingAppliesToContext(setting)) return false;

        var makingType = String(setting.making_type || '').trim() || 'Fix';
        var makingRate = parseFloat(setting.making_rate);
        if (isNaN(makingRate)) makingRate = 0;

        var changed = false;
        if (makingTypeSelect) {
            if (typeof window.setModalSelectIfOptionExists === 'function') {
                window.setModalSelectIfOptionExists(row, 'making-type', makingType);
            } else {
                makingTypeSelect.value = makingType;
            }
            if (String(makingTypeSelect.value || '') !== makingType) {
                // Option may use different casing; try exact option match
                var opts = makingTypeSelect.options;
                for (var i = 0; i < opts.length; i++) {
                    if (String(opts[i].value || '').toLowerCase() === makingType.toLowerCase()) {
                        makingTypeSelect.selectedIndex = i;
                        break;
                    }
                }
            }
            changed = true;
        }
        if (makingRateInput) {
            makingRateInput.value = makingRate.toFixed(2);
            changed = true;
        }
        if (changed) {
            row.setAttribute('data-sale-percent-making-applied', '1');
        }
        return changed;
    }
    window.auragoldApplyConfiguredMaking = auragoldApplyConfiguredMaking;

    /** Apply sale % + making from master in one step (after product select / barcode). */
    function auragoldApplyConfiguredSalePercentAndMaking(row, force) {
        var a = auragoldApplyConfiguredSalePercent(row, force);
        var b = auragoldApplyConfiguredMaking(row, force);
        return a || b;
    }
    window.auragoldApplyConfiguredSalePercentAndMaking = auragoldApplyConfiguredSalePercentAndMaking;

    /** Normalize saved making type labels to modal select option values. */
    function auragoldNormalizeMakingType(raw) {
        if (raw == null || raw === '') return 'Fix';
        var s = String(raw).trim();
        if (!s) return 'Fix';
        var key = s.toLowerCase().replace(/[\s_-]+/g, ' ').trim();
        var map = {
            'fix': 'Fix',
            'per gram': 'Per Gram',
            'per piece': 'Per Piece',
            'per kilogram': 'Per Kilogram',
            'per percent': 'Per Percent',
            'mrp': 'MRP',
            'm.kt': 'M.KT',
            'mkt': 'M.KT'
        };
        return map[key] || s;
    }
    window.auragoldNormalizeMakingType = auragoldNormalizeMakingType;

    /** Purchase / sale % / sale amount linkage (imitation tabs, SJ, sale invoice, purchase invoice). */
    function auragoldAllowSalePercentPurchaseCalc() {
        return auragoldAllowManualPurchaseSaleAmounts() || auragoldIsPurchaseInvoiceContext();
    }
    window.auragoldAllowSalePercentPurchaseCalc = auragoldAllowSalePercentPurchaseCalc;

    /** Base amount for sale % markup: manual purchase first, then net/tax per tax_mode. */
    function auragoldGetSalePercentCalcBase(row, purchaseAmountInput) {
        if (!row) return 0;
        var taxMode = row.getAttribute('data-sale-percent-tax-mode') || 'without_tax';
        var purchaseVal = purchaseAmountInput ? (parseFloat(purchaseAmountInput.value) || 0) : 0;
        if (purchaseVal > 0 && (row.getAttribute('data-manual-purchase-amount') === '1' || auragoldIsPurchaseInvoiceContext())) {
            return purchaseVal;
        }
        if (taxMode === 'with_tax') {
            var netTaxInp = row.querySelector('[data-column="net-amt-tax"] input');
            var withTaxBase = netTaxInp ? (parseFloat(netTaxInp.value) || 0) : 0;
            if (withTaxBase > 0) return withTaxBase;
            var netInp = row.querySelector('[data-column="net-amt"] input');
            var taxInp = row.querySelector('[data-column="tax"] input');
            var netVal = netInp ? (parseFloat(netInp.value) || 0) : purchaseVal;
            var taxVal = taxInp ? (parseFloat(taxInp.value) || 0) : 0;
            if (netVal + taxVal > 0) return netVal + taxVal;
        }
        if (purchaseVal > 0) return purchaseVal;
        var netOnly = row.querySelector('[data-column="net-amt"] input');
        return netOnly ? (parseFloat(netOnly.value) || 0) : 0;
    }
    window.auragoldGetSalePercentCalcBase = auragoldGetSalePercentCalcBase;

    /** Sale Amount = base + (base × Sale % / 100). Base depends on tax_mode from master. */
    function auragoldApplySalePercentFromPurchase(row) {
        if (!row || !auragoldAllowSalePercentPurchaseCalc()) return false;
        if (typeof auragoldApplyConfiguredSalePercentAndMaking === 'function') {
            auragoldApplyConfiguredSalePercentAndMaking(row, false);
        } else {
            auragoldApplyConfiguredSalePercent(row, false);
        }
        var salePercentInput = row.querySelector('[data-column="sale-percent"] input');
        var purchaseAmountInput = row.querySelector('[data-column="purchase-amount"] input');
        var saleAmountInput = row.querySelector('[data-column="sale-amount"] input');
        if (!salePercentInput || !purchaseAmountInput || !saleAmountInput) return false;
        var pct = auragoldParsePercentInput(salePercentInput.value);
        if (pct <= 0) return false;
        if (row.getAttribute('data-manual-sale-amount') === '1') return false;
        var base = auragoldGetSalePercentCalcBase(row, purchaseAmountInput);
        if (base <= 0) return false;
        var saleAmt = base + (base * (pct / 100));
        saleAmountInput.value = saleAmt.toFixed(2);
        return true;
    }
    window.auragoldApplySalePercentFromPurchase = auragoldApplySalePercentFromPurchase;

    /** Reverse: Sale % = ((Sale Amount − Purchase) / Purchase) × 100 when user types Sale Amount. */
    function auragoldApplySalePercentFromSaleAmount(row) {
        if (!row || !auragoldAllowSalePercentPurchaseCalc()) return false;
        if (row.getAttribute('data-manual-sale-amount') !== '1') return false;
        var salePercentInput = row.querySelector('[data-column="sale-percent"] input');
        var purchaseAmountInput = row.querySelector('[data-column="purchase-amount"] input');
        var saleAmountInput = row.querySelector('[data-column="sale-amount"] input');
        if (!salePercentInput || !purchaseAmountInput || !saleAmountInput) return false;
        if (auragoldIsInputActivelyEdited(salePercentInput)) return false;
        // Markup is always vs Purchase Amount (not tax-inclusive net, which would force ~0% after reverse tax).
        var base = parseFloat(purchaseAmountInput.value) || 0;
        if (base <= 0) {
            base = auragoldGetSalePercentCalcBase(row, purchaseAmountInput);
        }
        var saleAmt = parseFloat(saleAmountInput.value) || 0;
        if (base <= 0 || saleAmt <= 0) return false;
        var taxMode = row.getAttribute('data-sale-percent-tax-mode') || 'without_tax';
        if (taxMode === 'with_tax' && row.getAttribute('data-manual-sale-amount-with') === '1') {
            var saleWithInp = row.querySelector('[data-column="sale-amount-with"] input');
            var inclusive = saleWithInp ? (parseFloat(saleWithInp.value) || 0) : 0;
            if (inclusive > 0) saleAmt = inclusive;
        }
        var pct = ((saleAmt - base) / base) * 100;
        salePercentInput.value = pct.toFixed(2);
        row.setAttribute('data-manual-sale-percent', '1');
        return true;
    }
    window.auragoldApplySalePercentFromSaleAmount = auragoldApplySalePercentFromSaleAmount;

    /**
     * Reverse tax: Sale Amount With Tax is tax-inclusive.
     * Sale Amount = inclusive / (1 + Tax%/100); Tax = inclusive − Sale Amount; Sale % from purchase.
     * @returns {{ netAmt: number, tax: number, netAmtTax: number }|null}
     */
    function auragoldApplySaleAmountFromSaleAmountWithTax(row, vatPercent, taxType, makingAmount) {
        if (!row || auragoldIsPurchaseInvoiceContext()) return null;
        if (row.getAttribute('data-manual-sale-amount-with') !== '1') return null;
        var saleWithInp = row.querySelector('[data-column="sale-amount-with"] input');
        var saleAmountInput = row.querySelector('[data-column="sale-amount"] input');
        var purchaseAmountInput = row.querySelector('[data-column="purchase-amount"] input');
        if (!saleWithInp || !saleAmountInput) return null;
        var inclusive = parseFloat(saleWithInp.value) || 0;
        if (inclusive <= 0) return null;
        vatPercent = parseFloat(vatPercent) || 0;
        taxType = taxType || 'tax_of_netamount';
        makingAmount = parseFloat(makingAmount) || 0;
        var netAmt = inclusive;
        var tax = 0;
        if (taxType === 'tax_of_netamount' && vatPercent > 0) {
            var factor = 1 + (vatPercent / 100);
            netAmt = inclusive / factor;
            tax = inclusive - netAmt;
        } else if (taxType === 'tax_on_making' && vatPercent > 0) {
            tax = makingAmount * (vatPercent / 100);
            netAmt = Math.max(0, inclusive - tax);
        } else {
            netAmt = inclusive;
            tax = 0;
        }
        if (netAmt < 0) netAmt = 0;
        if (tax < 0) tax = 0;
        var netAmtTax = (taxType === 'tax_of_netamount' && vatPercent > 0) ? inclusive : (netAmt + tax);
        // Keep Purchase Amount as cost base so Sale % can recalculate.
        if (purchaseAmountInput && (parseFloat(purchaseAmountInput.value) || 0) > 0) {
            row.setAttribute('data-manual-purchase-amount', '1');
        }
        saleAmountInput.value = netAmt.toFixed(2);
        row.setAttribute('data-manual-sale-amount', '1');
        auragoldApplySalePercentFromSaleAmount(row);
        return { netAmt: netAmt, tax: tax, netAmtTax: netAmtTax };
    }
    window.auragoldApplySaleAmountFromSaleAmountWithTax = auragoldApplySaleAmountFromSaleAmountWithTax;

    /** True when line total should come from metal rate × weight (not stock journal sale_amount). */
    function auragoldModalRowUsesMetalRatePricing(row, sj) {
        var calc = '';
        if (sj && sj.calculation != null && String(sj.calculation).trim() !== '') {
            calc = String(sj.calculation).trim();
        } else if (row) {
            var calcSel = row.querySelector('[data-column="calculation"] select');
            calc = calcSel ? String(calcSel.value || '').trim() : '';
        }
        if (!calc) calc = 'Rate X Gross Wt';
        var metalCalcs = [
            'Rate X Gross Wt', 'Rate X Net Wt', 'Rate X Final Wt', 'Rate X Purity Wt',
            'Weight X Rate', 'Metal Rate x Metal Weight', 'Metal Rate x Metal Purity',
            'Metal Carat x Metal Rate'
        ];
        return metalCalcs.indexOf(calc) !== -1;
    }

    function auragoldModalRowIsGoldOrSilverMetal(row) {
        if (!row) return false;
        var metalId = row.getAttribute('data-metal-id');
        if (typeof window.metals !== 'undefined' && window.metals && metalId) {
            var m = window.metals.find(function(x) { return String(x.id) === String(metalId); });
            if (m) {
                var nm = String(m.display_name || m.name || '').toLowerCase();
                if (nm.indexOf('gold') !== -1 || nm.indexOf('silver') !== -1) return true;
            }
        }
        var dmn = row.getAttribute('data-metal-name');
        if (dmn) {
            var dl = String(dmn).toLowerCase();
            return dl.indexOf('gold') !== -1 || dl.indexOf('silver') !== -1;
        }
        return false;
    }

    /** Sale invoice Gold/Silver barcode: net = metal rate × wt + making (not stock journal purchase/sale). */
    function auragoldSkipStockJournalAmountLockOnRow(row, sj) {
        return auragoldIsSaleInvoiceContext()
            && auragoldModalRowIsGoldOrSilverMetal(row)
            && auragoldModalRowUsesMetalRatePricing(row, sj);
    }
    window.auragoldSkipStockJournalAmountLockOnRow = auragoldSkipStockJournalAmountLockOnRow;

    /** After stock journal fields are applied on barcode scan: keep purchase/sale amounts (do not zero on calc). */
    function auragoldFinalizeStockJournalModalRow(row, sj) {
        if (!row || !sj || typeof sj !== 'object') return;
        if (!auragoldAllowManualPurchaseSaleAmounts()) return;
        function sjNum(k) {
            if (!Object.prototype.hasOwnProperty.call(sj, k)) return 0;
            var v = sj[k];
            if (v === null || v === '') return 0;
            return parseFloat(v) || 0;
        }
        var skipAmountLock = auragoldSkipStockJournalAmountLockOnRow(row, sj);
        if (skipAmountLock) {
            row.removeAttribute('data-manual-purchase-amount');
            row.removeAttribute('data-manual-sale-amount');
            row.removeAttribute('data-manual-sale-amount-with');
        }
        if (sjNum('sale_amount') > 0 && !skipAmountLock) {
            var sw = (sj.sale_amount_with != null && sj.sale_amount_with !== '') ? sj.sale_amount_with
                : ((sj.net_amt_with_tax != null && sj.net_amt_with_tax !== '') ? sj.net_amt_with_tax : null);
            if (sw != null && sw !== '' && typeof setModalCellInput === 'function') {
                setModalCellInput(row, 'sale-amount-with', sw);
            }
        }
        if (sjNum('purchase_amount') > 0 && !skipAmountLock) {
            row.setAttribute('data-manual-purchase-amount', '1');
        }
        if (sjNum('sale_amount') > 0 && !skipAmountLock) {
            row.setAttribute('data-manual-sale-amount', '1');
        }
        if (auragoldParsePercentInput(sj.sale_percent) > 0) {
            row.setAttribute('data-manual-sale-percent', '1');
        }
        var sjMakingType = sj.making_type != null ? String(sj.making_type).trim() : '';
        if (sjMakingType !== '' || sjNum('making_rate') > 0 || sjNum('making_amount') > 0) {
            row.setAttribute('data-manual-making', '1');
        }
    }
    window.auragoldFinalizeStockJournalModalRow = auragoldFinalizeStockJournalModalRow;

    /** Re-apply manual flags when calc runs after a stock journal barcode load. */
    function auragoldEnsureStockJournalManualAmountFlags(row, purchaseAmountInput, saleAmountInput, salePercentInput) {
        if (!row || !auragoldAllowManualPurchaseSaleAmounts()) return;
        if (!row.getAttribute('data-stock-journal-id')) return;
        if (auragoldSkipStockJournalAmountLockOnRow(row, null)) return;
        if (purchaseAmountInput && (parseFloat(purchaseAmountInput.value) || 0) > 0) {
            row.setAttribute('data-manual-purchase-amount', '1');
        }
        if (saleAmountInput && (parseFloat(saleAmountInput.value) || 0) > 0) {
            row.setAttribute('data-manual-sale-amount', '1');
        }
        var spInp = salePercentInput || row.querySelector('[data-column="sale-percent"] input');
        if (spInp && auragoldParsePercentInput(spInp.value) > 0) {
            row.setAttribute('data-manual-sale-percent', '1');
        }
    }
    window.auragoldEnsureStockJournalManualAmountFlags = auragoldEnsureStockJournalManualAmountFlags;

    function auragoldSetAmountInputEditable(input, editable) {
        if (!input) return;
        if (editable) {
            input.readOnly = false;
            input.removeAttribute('readonly');
            if (input.style) {
                if (input.style.background === 'rgb(241, 245, 249)' || input.style.background === '#f1f5f9') {
                    input.style.background = '';
                }
                if (input.style.cursor === 'not-allowed') input.style.cursor = '';
            }
        } else {
            input.readOnly = true;
            input.setAttribute('readonly', 'readonly');
        }
    }

    /** Bind input listeners when Purchase/Sale Amount are user-typed. */
    function auragoldBindManualAmountFieldListeners(row) {
        if (!row || !auragoldAllowSalePercentPurchaseCalc()) return;
        function addListeners(input, callback) {
            if (input) {
                input.addEventListener('input', callback);
                input.addEventListener('change', callback);
            }
        }
        function deferRowCalc() {
            setTimeout(function() { calculateModalRowNetWeight(row); }, 0);
        }
        var saleAmtInp = row.querySelector('[data-column="sale-amount"] input');
        var purchaseAmtInp = row.querySelector('[data-column="purchase-amount"] input');
        var salePercentInp = row.querySelector('[data-column="sale-percent"] input');
        var saleWithInp = row.querySelector('[data-column="sale-amount-with"] input');
        var taxPercentInp = row.querySelector('[data-column="tax-percent"] input');
        if (salePercentInp && !salePercentInp._auragoldImitationSalePercentBound) {
            salePercentInp._auragoldImitationSalePercentBound = true;
            addListeners(salePercentInp, function() {
                row.setAttribute('data-manual-sale-percent', '1');
                row.removeAttribute('data-manual-sale-amount');
                row.removeAttribute('data-manual-sale-amount-with');
                calculateModalRowNetWeight(row);
            });
        }
        if (saleAmtInp && !saleAmtInp._auragoldImitationSaleBound) {
            saleAmtInp._auragoldImitationSaleBound = true;
            addListeners(saleAmtInp, function() {
                row.setAttribute('data-manual-sale-amount', '1');
                row.removeAttribute('data-manual-sale-amount-with');
                calculateModalRowNetWeight(row);
                if (auragoldIsPurchaseInvoiceContext()) {
                    if (typeof updateSummaryPanel === 'function') updateSummaryPanel();
                    if (typeof updateSummaryRow === 'function') updateSummaryRow();
                }
            });
            saleAmtInp.addEventListener('blur', function() {
                if (row.getAttribute('data-manual-sale-amount') === '1') {
                    deferRowCalc();
                }
            });
        }
        if (saleWithInp && !saleWithInp._auragoldSaleAmountWithBound && !auragoldIsPurchaseInvoiceContext()) {
            saleWithInp._auragoldSaleAmountWithBound = true;
            addListeners(saleWithInp, function() {
                row.setAttribute('data-manual-sale-amount-with', '1');
                row.setAttribute('data-manual-sale-amount', '1');
                // Lock purchase so calc does not overwrite cost base (keeps Sale % meaningful).
                var pInp = row.querySelector('[data-column="purchase-amount"] input');
                if (pInp && (parseFloat(pInp.value) || 0) > 0) {
                    row.setAttribute('data-manual-purchase-amount', '1');
                }
                calculateModalRowNetWeight(row);
            });
            saleWithInp.addEventListener('blur', function() {
                if (row.getAttribute('data-manual-sale-amount-with') === '1') {
                    deferRowCalc();
                }
            });
        }
        if (taxPercentInp && !taxPercentInp._auragoldManualTaxPercentBound && !auragoldIsPurchaseInvoiceContext()) {
            taxPercentInp._auragoldManualTaxPercentBound = true;
            addListeners(taxPercentInp, function() {
                row.setAttribute('data-manual-tax-percent', '1');
                calculateModalRowNetWeight(row);
            });
        }
        if (purchaseAmtInp && !purchaseAmtInp._auragoldImitationPurchaseBound) {
            purchaseAmtInp._auragoldImitationPurchaseBound = true;
            addListeners(purchaseAmtInp, function() {
                row.setAttribute('data-manual-purchase-amount', '1');
                row.removeAttribute('data-manual-sale-amount');
                row.removeAttribute('data-manual-sale-amount-with');
                calculateModalRowNetWeight(row);
            });
            purchaseAmtInp.addEventListener('blur', function() {
                if ((parseFloat(purchaseAmtInp.value) || 0) > 0) {
                    row.setAttribute('data-manual-purchase-amount', '1');
                    row.removeAttribute('data-manual-sale-amount');
                    row.removeAttribute('data-manual-sale-amount-with');
                    deferRowCalc();
                }
            });
        }
        var makingTypeSel = row.querySelector('[data-column="making-type"] select');
        var makingRateInp = row.querySelector('[data-column="making-rate"] input');
        if (makingTypeSel && !makingTypeSel._auragoldManualMakingBound) {
            makingTypeSel._auragoldManualMakingBound = true;
            makingTypeSel.addEventListener('change', function() {
                row.setAttribute('data-manual-making', '1');
            });
        }
        if (makingRateInp && !makingRateInp._auragoldManualMakingBound) {
            makingRateInp._auragoldManualMakingBound = true;
            addListeners(makingRateInp, function() {
                row.setAttribute('data-manual-making', '1');
            });
        }
        if (typeof auragoldApplyConfiguredSalePercentAndMaking === 'function') {
            auragoldApplyConfiguredSalePercentAndMaking(row, false);
        } else if (typeof auragoldApplyConfiguredSalePercent === 'function') {
            auragoldApplyConfiguredSalePercent(row, false);
        }
    }
    window.auragoldBindManualAmountFieldListeners = auragoldBindManualAmountFieldListeners;

    /** Unlock Purchase/Sale Amount for Product Opening, Stock Journal, Imitation/Other; keep PI sale fields editable. */
    function auragoldApplyManualAmountFieldsEditability(root) {
        var scope = root;
        var singleRow = root && root.classList && root.classList.contains('product-row') ? root : null;
        if (!scope || !scope.querySelectorAll) {
            scope = document.getElementById('productSelectionModal')
                || document.getElementById('productListTablePage')
                || document;
        }
        var manualAmt = auragoldAllowManualPurchaseSaleAmounts();
        var salePercentCalc = auragoldAllowSalePercentPurchaseCalc();
        var purchaseInvoice = auragoldIsPurchaseInvoiceContext();
        var unlockPurchase = manualAmt || purchaseInvoice;
        var unlockSale = manualAmt || purchaseInvoice;
        var unlockSalePercent = manualAmt || purchaseInvoice;
        var unlockSaleWith = manualAmt && !purchaseInvoice;
        var unlockTaxPercent = unlockSaleWith;
        scope.querySelectorAll('[data-column="purchase-amount"] input').forEach(function(inp) {
            auragoldSetAmountInputEditable(inp, unlockPurchase);
        });
        scope.querySelectorAll('[data-column="sale-percent"] input').forEach(function(inp) {
            auragoldSetAmountInputEditable(inp, unlockSalePercent);
        });
        scope.querySelectorAll('[data-column="sale-amount"] input').forEach(function(inp) {
            auragoldSetAmountInputEditable(inp, unlockSale);
        });
        scope.querySelectorAll('[data-column="sale-amount-with"] input').forEach(function(inp) {
            auragoldSetAmountInputEditable(inp, unlockSaleWith);
            if (unlockSaleWith) {
                inp.title = 'Tax-inclusive total: Sale Amount = this ÷ (1 + Tax%/100)';
            }
        });
        scope.querySelectorAll('[data-column="tax-percent"] input').forEach(function(inp) {
            auragoldSetAmountInputEditable(inp, unlockTaxPercent);
            if (unlockTaxPercent) {
                inp.style.background = '';
                inp.style.cursor = '';
                inp.title = 'Tax % used for reverse calc from Sale Amount With Tax';
            }
        });
        if (salePercentCalc) {
            var rowsForBind = singleRow ? [singleRow] : Array.prototype.slice.call(
                scope.querySelectorAll('#productListBody tr.product-row, tr.product-row') || []
            );
            rowsForBind.forEach(function(r) {
                auragoldBindManualAmountFieldListeners(r);
            });
        }
    }
    window.auragoldApplyManualAmountFieldsEditability = auragoldApplyManualAmountFieldsEditability;

    function syncModalGroupSingleItemCheckboxForActiveTab() {
        var wrap = document.getElementById('modalGroupSingleItemWrap');
        if (wrap) {
            wrap.style.display = isGoldOrSilverMetalTab() ? '' : 'none';
        }
        if (typeof auragoldApplyManualAmountFieldsEditability === 'function') {
            auragoldApplyManualAmountFieldsEditability();
        }
        if (typeof auragoldApplyConfiguredSalePercentAndMaking === 'function') {
            var scope = document.getElementById('productSelectionModal')
                || document.getElementById('productListTablePage')
                || document.getElementById('productListTable')
                || document;
            scope.querySelectorAll('tr.product-row, tr[data-product-id]').forEach(function (row) {
                auragoldApplyConfiguredSalePercentAndMaking(row, false);
            });
        } else if (typeof auragoldApplyConfiguredSalePercent === 'function') {
            var scopeSp = document.getElementById('productSelectionModal')
                || document.getElementById('productListTablePage')
                || document.getElementById('productListTable')
                || document;
            scopeSp.querySelectorAll('tr.product-row, tr[data-product-id]').forEach(function (row) {
                auragoldApplyConfiguredSalePercent(row, false);
            });
        }
    }
    window.syncModalGroupSingleItemCheckboxForActiveTab = syncModalGroupSingleItemCheckboxForActiveTab;

    function shouldGroupModalItemsAsSingleRow() {
        if (!isGoldOrSilverMetalTab()) return true;
        var el = document.getElementById('modalGroupSingleItem');
        return el ? el.checked : true;
    }
    window.shouldGroupModalItemsAsSingleRow = shouldGroupModalItemsAsSingleRow;

    /** Gold/Silver: merge or add separate rows based on "Group Single Item" checkbox; other tabs always merge. */
    function auragoldAddModalRowsToProductTable(modalRowsData, metalId, loadOpts) {
        if (!modalRowsData || modalRowsData.length === 0) return;
        var groupAsSingle = shouldGroupModalItemsAsSingleRow();
        if (groupAsSingle && typeof addMergedProductsToTable === 'function') {
            addMergedProductsToTable(modalRowsData, metalId, loadOpts);
        } else if (typeof addProductToTableFromModalRow === 'function') {
            modalRowsData.forEach(function(rowData) {
                addProductToTableFromModalRow(rowData, metalId, loadOpts);
            });
        }
    }
    window.auragoldAddModalRowsToProductTable = auragoldAddModalRowsToProductTable;

    function isLooseDiamondTabActive() {
        var name = getActiveProductModalMetalName();
        return isLoosOrLooseDiamondMetalDisplayName(name) && !isDiamondStonesMetalDisplayName(name);
    }
    window.isLooseDiamondTabActive = isLooseDiamondTabActive;

    function getDiamondCategoryOptionsForActiveTab() {
        return isLooseDiamondTabActive() ? LOOSE_DIAMOND_CATEGORY_OPTIONS : DIAMOND_CATEGORY_OPTIONS;
    }
    window.getDiamondCategoryOptionsForActiveTab = getDiamondCategoryOptionsForActiveTab;

    /** Map DB / legacy diamond_category to loose-tab select value (Diamond | Stone). */
    function normalizeDiamondCategoryForLooseTab(val) {
        var v = (val || '').trim();
        if (!v) return '';
        var lower = v.toLowerCase();
        if (lower === 'diamond' || lower === 'diamonds') return 'Diamond';
        if (lower === 'stone' || lower === 'stones' || lower === 'gemstones' || lower === 'gem stones') return 'Stone';
        return v;
    }
    window.normalizeDiamondCategoryForLooseTab = normalizeDiamondCategoryForLooseTab;

    function normalizeDiamondCategoryKey(catVal) {
        var v = (catVal || '').trim();
        if (!v) return '';
        var lower = v.toLowerCase();
        if (lower === 'diamond' || lower === 'diamonds') return 'Diamonds';
        if (lower === 'stone' || lower === 'stones' || lower === 'gemstones' || lower === 'gem stones') return 'GemStones';
        if (lower === 'jewellery') return 'Jewellery';
        return v;
    }
    window.normalizeDiamondCategoryKey = normalizeDiamondCategoryKey;

    function isJewelleryDiamondCategory(catVal) {
        return normalizeDiamondCategoryKey(catVal) === 'Jewellery';
    }
    function isDiamondsLineCategory(catVal) {
        return normalizeDiamondCategoryKey(catVal) === 'Diamonds';
    }
    function isGemStonesLineCategory(catVal) {
        return normalizeDiamondCategoryKey(catVal) === 'GemStones';
    }
    function isDiamondOrGemStoneLineCategory(catVal) {
        var k = normalizeDiamondCategoryKey(catVal);
        return k === 'Diamonds' || k === 'GemStones';
    }
    function isAnyDiamondTabCategory(catVal) {
        var k = normalizeDiamondCategoryKey(catVal);
        return k === 'Jewellery' || k === 'Diamonds' || k === 'GemStones';
    }
    window.isJewelleryDiamondCategory = isJewelleryDiamondCategory;
    window.isDiamondsLineCategory = isDiamondsLineCategory;
    window.isGemStonesLineCategory = isGemStonesLineCategory;
    window.isDiamondOrGemStoneLineCategory = isDiamondOrGemStoneLineCategory;
    window.isAnyDiamondTabCategory = isAnyDiamondTabCategory;

    /** Read numeric value from a modal/product-list cell (input or text). */
    function modalRowCellNumber(row, col) {
        if (!row) return 0;
        var c = row.querySelector('[data-column="' + col + '"]');
        if (!c) return 0;
        var inp = c.querySelector('input');
        if (inp) return parseFloat(inp.value) || 0;
        var t = (c.textContent || '').replace(/,/g, '').trim();
        return parseFloat(t) || 0;
    }
    window.modalRowCellNumber = modalRowCellNumber;

    /** Sale invoice rows: when Sale Amount is set, tax/net-amt-tax must use it (not component sum 0). */
    function auragoldRowSaleInvoiceTaxableNet(row) {
        if (!row || !auragoldAllowManualPurchaseSaleAmounts() || auragoldIsPurchaseInvoiceContext()) {
            return null;
        }
        var saleAmt = modalRowCellNumber(row, 'sale-amount');
        return saleAmt > 0 ? saleAmt : null;
    }

    /** Write net-amt, tax, net-amt-tax (+ sale-amount-with on sale invoice) from taxable net base. */
    function auragoldApplyRowNetAmtTaxFromBase(row, netAmt) {
        if (!row) return;
        netAmt = parseFloat(netAmt) || 0;
        if (netAmt < 0) netAmt = 0;

        var taxTypeSelect = row.querySelector('[data-column="tax-type"] select');
        var taxType = taxTypeSelect ? (taxTypeSelect.value || 'no_tax') : 'no_tax';
        var taxPercentInput = row.querySelector('[data-column="tax-percent"] input');
        var vatPercent = 0;
        if (typeof window.getSaleInvoiceRowEffectiveTaxPercent === 'function') {
            var effGst = window.getSaleInvoiceRowEffectiveTaxPercent(row, taxPercentInput);
            if (effGst !== null && effGst !== undefined && !isNaN(effGst) && effGst > 0) {
                vatPercent = effGst;
            }
        }
        if (vatPercent <= 0 && taxPercentInput) {
            vatPercent = parseFloat(taxPercentInput.value) || 0;
        }
        if (vatPercent <= 0 && typeof auragoldSaleInvoiceVatPercentFallback === 'function') {
            vatPercent = auragoldSaleInvoiceVatPercentFallback(row);
        }

        var making = modalRowCellNumber(row, 'making-amount');
        var tax = 0;
        if (taxType === 'tax_of_netamount') {
            tax = netAmt * (vatPercent / 100);
        } else if (taxType === 'tax_on_making') {
            tax = making * (vatPercent / 100);
        }
        var netAmtTax = netAmt + tax;

        var taxInput = row.querySelector('[data-column="tax"] input');
        var netAmtInp = row.querySelector('[data-column="net-amt"] input');
        var netAmtTaxInp = row.querySelector('[data-column="net-amt-tax"] input');
        var saleWithInp = row.querySelector('[data-column="sale-amount-with"] input');
        if (taxInput) taxInput.value = tax.toFixed(2);
        if (netAmtInp) netAmtInp.value = netAmt.toFixed(2);
        if (netAmtTaxInp) netAmtTaxInp.value = netAmtTax.toFixed(2);
        if (!auragoldIsPurchaseInvoiceContext() && saleWithInp) {
            saleWithInp.value = netAmtTax.toFixed(2);
        }
    }
    window.auragoldApplyRowNetAmtTaxFromBase = auragoldApplyRowNetAmtTaxFromBase;

    /**
     * Diamonds / GemStones line total (JewelStep): Fix/Carat lines store value in net-amt or amount, not always diamond-amount.
     */
    function modalRowDiamondComponentLineAmount(row) {
        if (!row) return 0;
        var net = modalRowCellNumber(row, 'net-amt');
        if (net > 0) return net;
        var amt = modalRowCellNumber(row, 'amount');
        if (amt > 0) return amt;
        var da = modalRowCellNumber(row, 'diamond-amount');
        if (da > 0) return da;
        var lineMetal = modalRowCellNumber(row, 'diamond-line-metal-value');
        if (lineMetal > 0) return lineMetal;
        return modalRowCellNumber(row, 'metal-value');
    }
    window.modalRowDiamondComponentLineAmount = modalRowDiamondComponentLineAmount;

    function modalRowCategoryValue(row) {
        var catSel = row ? row.querySelector('[data-column="category"] select') : null;
        return normalizeDiamondCategoryKey((catSel && catSel.value) ? String(catSel.value).trim() : '');
    }

    /** Carat for "Carat X Rate" — stone-weight (D.Weight) or carat column; never multiplied by quantity. */
    function getCaratForCaratXRateCalc(row) {
        if (!row) return 0;
        var stoneWtInp = row.querySelector('[data-column="stone-weight"] input');
        var stoneWt = stoneWtInp ? (parseFloat(stoneWtInp.value) || 0) : 0;
        var caratSel = row.querySelector('[data-column="carat"] select');
        var caratFromSel = caratSel ? (parseFloat(caratSel.value) || 0) : 0;
        var caratInp = row.querySelector('[data-column="carat"] input');
        var caratFromInp = caratInp ? (parseFloat(caratInp.value) || 0) : 0;
        var caratCol = caratFromSel > 0 ? caratFromSel : caratFromInp;
        if (stoneWt > 0) return stoneWt;
        if (caratCol > 0) {
            if (stoneWtInp) stoneWtInp.value = parseFloat(caratCol.toFixed(3)).toString();
            return caratCol;
        }
        return 0;
    }
    window.getCaratForCaratXRateCalc = getCaratForCaratXRateCalc;

    function isQtyXRateCalculationType(calculationType) {
        var t = String(calculationType || '').trim().toLowerCase();
        return t === 'qty x rate' || t === 'quantity x rate';
    }
    window.isQtyXRateCalculationType = isQtyXRateCalculationType;

    /** Qty for Qty X Rate — quantity column, else metal-qty (Imitation / Watches), default 1. */
    function getQuantityForQtyXRateCalc(row) {
        if (!row) return 1;
        var qtyInp = row.querySelector('[data-column="quantity"] input');
        if (qtyInp) {
            var q = parseFloat(qtyInp.value);
            if (!isNaN(q) && q > 0) return q;
        }
        var mqInp = row.querySelector('[data-column="metal-qty"] input');
        if (mqInp) {
            var mq = parseFloat(mqInp.value);
            if (!isNaN(mq) && mq > 0) return mq;
        }
        return 1;
    }
    window.getQuantityForQtyXRateCalc = getQuantityForQtyXRateCalc;

    function isKnownDiamondCategoryValue(v) {
        v = (v || '').trim();
        if (!v) return false;
        var i;
        for (i = 0; i < DIAMOND_CATEGORY_OPTIONS.length; i++) {
            if (DIAMOND_CATEGORY_OPTIONS[i].value === v) return true;
        }
        for (i = 0; i < LOOSE_DIAMOND_CATEGORY_OPTIONS.length; i++) {
            if (LOOSE_DIAMOND_CATEGORY_OPTIONS[i].value === v) return true;
        }
        return false;
    }
    window.isKnownDiamondCategoryValue = isKnownDiamondCategoryValue;

    function itemDiamondCategoryKey(it) {
        if (!it) return '';
        return (it.diamond_category || it.category || '').toString().trim();
    }
    window.itemDiamondCategoryKey = itemDiamondCategoryKey;

    /** Pick product-modal metal tab from saved lines (sale order / invoice items). */
    function resolveBestMetalTabIdFromItems(items, metalsList) {
        if (!items || !items.length) return null;
        metalsList = metalsList || (typeof metals !== 'undefined' ? metals : []);
        if (!metalsList || !metalsList.length) return null;

        function metalIdFromItem(it) {
            if (!it) return '';
            var m = it.metal_id != null && it.metal_id !== '' ? String(it.metal_id) : '';
            return m;
        }

        var hasLooseCat = items.some(function(it) {
            var c = itemDiamondCategoryKey(it).toLowerCase();
            return c === 'diamond' || c === 'stone';
        });
        if (hasLooseCat) {
            for (var li = 0; li < metalsList.length; li++) {
                var ln = (metalsList[li].display_name || metalsList[li].name || '').toString();
                if (isLoosOrLooseDiamondMetalDisplayName(ln) && !isDiamondStonesMetalDisplayName(ln)) {
                    return String(metalsList[li].id);
                }
            }
        }

        var hasDiamondStonesCat = items.some(function(it) {
            var c = itemDiamondCategoryKey(it);
            return c === 'Jewellery' || c === 'Diamonds' || c === 'GemStones';
        });
        if (hasDiamondStonesCat) {
            for (var di = 0; di < metalsList.length; di++) {
                var dn = (metalsList[di].display_name || metalsList[di].name || '').toString();
                if (isDiamondStonesMetalDisplayName(dn)) {
                    return String(metalsList[di].id);
                }
            }
        }

        var metalCounts = {};
        items.forEach(function(it) {
            var m = metalIdFromItem(it);
            if (m) metalCounts[m] = (metalCounts[m] || 0) + 1;
        });
        var bestMetalId = null;
        var maxCount = 0;
        for (var mk in metalCounts) {
            if (metalCounts[mk] > maxCount) {
                maxCount = metalCounts[mk];
                bestMetalId = mk;
            }
        }
        if (bestMetalId) return bestMetalId;
        var firstMid = metalIdFromItem(items[0]);
        return firstMid || null;
    }
    window.resolveBestMetalTabIdFromItems = resolveBestMetalTabIdFromItems;

    function applyPreferredProductModalMetalTab(metalId) {
        if (metalId == null || metalId === '') return;
        window.preferredProductModalMetalId = String(metalId);
        if (typeof switchToMetalTab === 'function') {
            switchToMetalTab(String(metalId));
        }
    }
    window.applyPreferredProductModalMetalTab = applyPreferredProductModalMetalTab;

    function syncModalDiamondCategoryFilterForActiveTab() {
        var sel = document.getElementById('modalDiamondCategoryFilter');
        var filterRow = document.getElementById('modalDiamondCategoryFilterRow');
        var show = typeof isDiamondTabActive === 'function' && isDiamondTabActive();
        if (filterRow) {
            filterRow.style.display = show ? '' : 'none';
        }
        if (!sel || !show) {
            return;
        }
        var current = (sel.value || '').trim();
        var options = getDiamondCategoryOptionsForActiveTab();
        sel.innerHTML = '<option value="">All categories</option>';
        options.forEach(function(opt) {
            sel.appendChild(new Option(opt.name, opt.value));
        });
        if (current && options.some(function(o) { return o.value === current; })) {
            sel.value = current;
        } else {
            sel.value = '';
        }
    }
    window.syncModalDiamondCategoryFilterForActiveTab = syncModalDiamondCategoryFilterForActiveTab;

    function auragoldGetModalDiamondCategoryFilter() {
        var sel = document.getElementById('modalDiamondCategoryFilter');
        if (!sel) return '';
        var v = (sel.value || '').trim();
        if (!v) return '';
        var allowed = getDiamondCategoryOptionsForActiveTab().map(function(o) { return o.value; });
        return allowed.indexOf(v) !== -1 ? v : '';
    }
    window.auragoldGetModalDiamondCategoryFilter = auragoldGetModalDiamondCategoryFilter;

    // Populate category dropdown: Diamond tab = Diamonds/GemStones/Jewellery; Loose tab = Diamond/Stone; else API categories
    function populateCategorySelectForModal(select, isDiamondTab) {
        if (!select) return;
        if (isDiamondTab) {
            var looseTab = typeof isLooseDiamondTabActive === 'function' && isLooseDiamondTabActive();
            var options = looseTab ? LOOSE_DIAMOND_CATEGORY_OPTIONS : DIAMOND_CATEGORY_OPTIONS;
            var defaultVal = looseTab ? 'Diamond' : 'Jewellery';
            var currentVal = (select.value || '').trim();
            select.innerHTML = '<option value="">' + DIAMOND_CATEGORY_PLACEHOLDER + '</option>';
            options.forEach(function(opt) {
                select.appendChild(new Option(opt.name, opt.value));
            });
            if (looseTab && currentVal) {
                var mapped = normalizeDiamondCategoryForLooseTab(currentVal);
                if (mapped && options.some(function(o) { return o.value === mapped; })) {
                    currentVal = mapped;
                }
            }
            if (currentVal && options.some(function(o) { return o.value === currentVal; })) {
                select.value = currentVal;
            } else {
                select.value = defaultVal;
            }
            select.classList.add('diamond-category-select');
            select.classList.remove('category-select');
        } else {
            select.classList.remove('diamond-category-select');
            select.classList.add('category-select');
            if (typeof window.populateSelect === 'function') {
                window.populateSelect(select, auragoldPageCategoriesList(), 'id', 'name', 'Select Category');
            }
        }
    }
    window.populateCategorySelectForModal = populateCategorySelectForModal;

    function auragoldPageCategoriesList() {
        if (typeof window.categories !== 'undefined' && Array.isArray(window.categories)) {
            return window.categories;
        }
        if (typeof categories !== 'undefined' && Array.isArray(categories)) {
            window.categories = categories;
            return categories;
        }
        window.categories = [];
        return window.categories;
    }

    function auragoldSyncPageCategoriesList(list) {
        var arr = Array.isArray(list) ? list.slice() : [];
        window.categories = arr;
        if (typeof categories !== 'undefined' && Array.isArray(categories)) {
            categories.length = 0;
            arr.forEach(function (c) { categories.push(c); });
        }
        return arr;
    }

    function auragoldRepopulateCategorySelectElement(select, list, selectId) {
        if (!select || select.classList.contains('diamond-category-select')) return;
        var prev = select.value || '';
        if (typeof window.populateSelect === 'function') {
            window.populateSelect(select, list, 'id', 'name', 'Select Category');
        }
        if (selectId != null && String(selectId) !== '') {
            select.value = String(selectId);
        } else if (prev) {
            try { select.value = prev; } catch (e) {}
        }
    }

    /** After Add Category: refresh Product category + category column dropdowns and in-memory master list. */
    function auragoldRefreshCategoryMasterDropdowns(newCategoryId, newCategoryName, selectNew) {
        var list = auragoldPageCategoriesList();
        if (newCategoryId != null && newCategoryName) {
            var exists = list.some(function (c) { return String(c.id) === String(newCategoryId); });
            if (!exists) {
                list.push({ id: newCategoryId, name: String(newCategoryName) });
            }
        }
        auragoldSyncPageCategoriesList(list);
        document.querySelectorAll('.product-category-select, .category-select').forEach(function (select) {
            auragoldRepopulateCategorySelectElement(select, list, selectNew ? newCategoryId : null);
        });
        var parentSelect = document.getElementById('categoryParentId');
        if (parentSelect && newCategoryId != null && newCategoryName) {
            var hasParent = false;
            for (var i = 0; i < parentSelect.options.length; i++) {
                if (String(parentSelect.options[i].value) === String(newCategoryId)) {
                    hasParent = true;
                    break;
                }
            }
            if (!hasParent) {
                parentSelect.appendChild(new Option(String(newCategoryName), String(newCategoryId)));
            }
        }
        var productCategory = document.getElementById('productCategory');
        if (productCategory) {
            auragoldRepopulateCategorySelectElement(productCategory, list, selectNew ? newCategoryId : null);
        }
    }
    window.auragoldRefreshCategoryMasterDropdowns = auragoldRefreshCategoryMasterDropdowns;

    function auragoldReloadCategoriesFromServer(selectNewId) {
        return fetch('ajax/category.php?action=list', { method: 'GET', credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data || data.status !== 'success' || !Array.isArray(data.categories)) return;
                auragoldSyncPageCategoriesList(data.categories);
                document.querySelectorAll('.product-category-select, .category-select').forEach(function (select) {
                    auragoldRepopulateCategorySelectElement(select, data.categories, selectNewId);
                });
                var parentSelect = document.getElementById('categoryParentId');
                if (parentSelect) {
                    var none = parentSelect.querySelector('option[value="0"]');
                    parentSelect.innerHTML = '';
                    if (none) parentSelect.appendChild(none);
                    else parentSelect.appendChild(new Option('None', '0'));
                    data.categories.forEach(function (cat) {
                        parentSelect.appendChild(new Option(cat.name, cat.id));
                    });
                }
                var productCategory = document.getElementById('productCategory');
                if (productCategory) {
                    auragoldRepopulateCategorySelectElement(productCategory, data.categories, selectNewId);
                }
            })
            .catch(function (err) { console.error('Error reloading categories:', err); });
    }
    window.auragoldReloadCategoriesFromServer = auragoldReloadCategoriesFromServer;

    function isDiamondTabActive() {
        var name = getActiveProductModalMetalName();
        return isDiamondStonesMetalDisplayName(name) || isLoosOrLooseDiamondMetalDisplayName(name);
    }
    window.isDiamondTabActive = isDiamondTabActive;

    // Set Calculation Type dropdown options. When master exists, only include names present in `fallback` (and preserve master order).
    function getCalculationModeOptionsFromMaster(fallback) {
        if (!fallback || !fallback.length) {
            return [];
        }
        var m = (typeof window !== 'undefined' && window.AURAGOLD_CALCULATION_MODES);
        if (m && Array.isArray(m) && m.length) {
            var setFb = {};
            for (var fi = 0; fi < fallback.length; fi++) {
                setFb[fallback[fi]] = true;
            }
            var names = m.map(function (r) { return (r && (r.name != null ? String(r.name) : (r.code != null ? String(r.code) : ''))).trim(); }).filter(Boolean);
            var out = [];
            for (var i = 0; i < names.length; i++) {
                if (setFb[names[i]]) {
                    out.push(names[i]);
                }
            }
            if (out.length) {
                return out;
            }
        }
        return fallback.slice();
    }
    function applyCalculationSelectOptionsForTab(select, isDiamondTab) {
        if (!select) return;
        var baseDiamond = getCalculationModeOptionsFromMaster(DIAMOND_CALCULATION_OPTIONS);
        var baseFull = getCalculationModeOptionsFromMaster(FULL_CALCULATION_OPTIONS);
        var baseLooseDiamond = getCalculationModeOptionsFromMaster(DIAMONDS_GEMSTONES_CALCULATION_OPTIONS);
        var opts = isDiamondTab
            ? ((typeof isLooseDiamondTabActive === 'function' && isLooseDiamondTabActive()) ? baseLooseDiamond : baseDiamond)
            : baseFull;
        var current = select.value;
        select.innerHTML = '';
        for (var i = 0; i < opts.length; i++) {
            var opt = document.createElement('option');
            opt.value = opts[i];
            opt.textContent = opts[i];
            select.appendChild(opt);
        }
        if (opts.indexOf(current) !== -1) select.value = current;
        else if (opts.length) select.selectedIndex = 0;
    }
    window.applyCalculationSelectOptionsForTab = applyCalculationSelectOptionsForTab;

    function applyCalculationSelectOptionsForRow(select, row, isDiamondTab) {
        if (!select) return;
        var opts;
        if (!isDiamondTab || !row) {
            opts = FULL_CALCULATION_OPTIONS;
        } else {
            var catSel = row.querySelector('[data-column="category"] select');
            var catVal = (catSel && catSel.value) ? (catSel.value || '').trim() : '';
            if (isJewelleryDiamondCategory(catVal)) {
                opts = JEWELLERY_DIAMOND_CATEGORY_CALCULATION_OPTIONS.slice();
            } else if (isDiamondOrGemStoneLineCategory(catVal)) {
                opts = DIAMONDS_GEMSTONES_CALCULATION_OPTIONS.slice();
            } else if (typeof isLooseDiamondTabActive === 'function' && isLooseDiamondTabActive()) {
                opts = DIAMONDS_GEMSTONES_CALCULATION_OPTIONS.slice();
            } else {
                opts = JEWELLERY_DIAMOND_CATEGORY_CALCULATION_OPTIONS.slice();
            }
        }
        var current = select.value;
        select.innerHTML = '';
        for (var i = 0; i < opts.length; i++) {
            var opt = document.createElement('option');
            opt.value = opts[i];
            opt.textContent = opts[i];
            select.appendChild(opt);
        }
        if (opts.indexOf(current) !== -1) select.value = current;
        else if (opts.length) select.selectedIndex = 0;
    }
    window.applyCalculationSelectOptionsForRow = applyCalculationSelectOptionsForRow;

    // ========== MODAL ROW CALCULATION ==========
    function addModalRowCalculationListeners(row) {
        function addListeners(input, callback) {
            if (input) { input.addEventListener('input', callback); input.addEventListener('change', callback); }
        }
        function addSelectListeners(select, callback) {
            if (select) select.addEventListener('change', callback);
        }
        const grossWtInput = row.querySelector('[data-column="gross-wt"] input');
        const lessWtInput = row.querySelector('[data-column="less-wt"] input');
        const purityInput = row.querySelector('[data-column="purity"] input');
        const wastagePerInput = row.querySelector('[data-column="wastage-per"] input');
        const rateInput = row.querySelector('[data-column="rate"] input');
        const metalRateInput = row.querySelector('[data-column="metal-rate"] input');
        const metalWeightInput = row.querySelector('[data-column="metal-weight"] input');
        const calculationSelect = row.querySelector('[data-column="calculation"] select');
        const amountInput = row.querySelector('[data-column="amount"] input');
        const netAmtInput = row.querySelector('[data-column="net-amt"] input');
        const discountTypeSelect = row.querySelector('[data-column="discount-type"] select');
        const discountPerInput = row.querySelector('[data-column="discount-per"] input');
        const makingTypeSelect = row.querySelector('[data-column="making-type"] select');
        const makingRateInput = row.querySelector('[data-column="making-rate"] input');
        const makingDiscountAmtInput = row.querySelector('[data-column="making-discount-amt"] input');
        const stoneChargeTypeSelect = row.querySelector('[data-column="stone-charge-type"] select');
        const stoneWeightInput = row.querySelector('[data-column="stone-weight"] input');
        const stoneRateInput = row.querySelector('[data-column="stone-rate"] input');
        const otherWeightInput = row.querySelector('[data-column="other-weight"] input');
        const otherRateInput = row.querySelector('[data-column="other-rate"] input');
        const diamondAmountInput = row.querySelector('[data-column="diamond-amount"] input');
        const quantityInput = row.querySelector('[data-column="quantity"] input');
        const caratSelect = row.querySelector('[data-column="carat"] select');

        function formatWt(v) {
            try { return auragoldFormatWeight(v); } catch (e) { return '0'; }
        }
        /** Diamonds / GemStones: carat↔D.Wt (÷5 / ×5) and gross-wt follows D.Wt. Jewellery: same carat↔D.Wt only; gross-wt unchanged. */
        function shouldSyncDiamondCaratAndDWt() {
            try {
                var cat = row.querySelector('[data-column="category"] select');
                if (cat) {
                    var cv = (cat.value || '').trim();
                    if (cv && isAnyDiamondTabCategory(cv)) return true;
                }
                if (typeof isDiamondTabActive === 'function' && isDiamondTabActive()) return true;
                return false;
            } catch (e) { return false; }
        }
        function isJewelleryCategoryRow() {
            try {
                var cat = row.querySelector('[data-column="category"] select');
                return cat && isJewelleryDiamondCategory((cat.value || '').trim());
            } catch (e) { return false; }
        }
        function syncCaratColumnToStoneWeight() {
            try {
                if (!shouldSyncDiamondCaratAndDWt() || isJewelleryCategoryRow()) return;
                if (!stoneWeightInput) return;
                var c = 0;
                if (caratSelect) c = parseFloat(caratSelect.value) || 0;
                var caratInpEl = row.querySelector('[data-column="carat"] input');
                if (c <= 0 && caratInpEl) c = parseFloat(caratInpEl.value) || 0;
                if (c > 0) {
                    stoneWeightInput.value = formatWt(c);
                    syncDiamondCaratAndLessWt('carat');
                }
            } catch (e) {}
        }
        function syncDiamondCaratAndLessWt(source) {
            try {
                if (!shouldSyncDiamondCaratAndDWt()) return;
                var grossInp = row.querySelector('[data-column="gross-wt"] input');
                if (!stoneWeightInput || !lessWtInput) return;
                var jewelleryOnly = isJewelleryCategoryRow();
                if (!jewelleryOnly && !grossInp) return;
                if (source === 'carat') {
                    var carat = parseFloat(stoneWeightInput.value) || 0;
                    var dWt = carat / 5;
                    lessWtInput.value = formatWt(dWt);
                    if (!jewelleryOnly && grossInp) grossInp.value = formatWt(dWt);
                } else if (source === 'less-wt') {
                    var lessWt = parseFloat(lessWtInput.value) || 0;
                    stoneWeightInput.value = formatWt(lessWt * 5);
                    if (!jewelleryOnly && grossInp) grossInp.value = formatWt(lessWt);
                }
            } catch (e) {}
        }

        function clearImitationManualAmountFlags() {
            if (!row) return;
            row.removeAttribute('data-manual-sale-amount');
            row.removeAttribute('data-manual-sale-amount-with');
            row.removeAttribute('data-manual-purchase-amount');
        }
        function calcAfterClearingImitationManualAmounts() {
            clearImitationManualAmountFlags();
            calculateModalRowNetWeight(row);
        }

        addListeners(grossWtInput, function() { calcAfterClearingImitationManualAmounts(); });
        addListeners(lessWtInput, function() { syncDiamondCaratAndLessWt('less-wt'); calcAfterClearingImitationManualAmounts(); if (typeof updateJewelleryDiamondCaratFromDiamondAndGemstone === 'function') updateJewelleryDiamondCaratFromDiamondAndGemstone(); });
        addListeners(purityInput, function() { calcAfterClearingImitationManualAmounts(); });
        addListeners(wastagePerInput, function() { calcAfterClearingImitationManualAmounts(); });
        addListeners(rateInput, function() { calcAfterClearingImitationManualAmounts(); });
        addListeners(metalRateInput, function() { calcAfterClearingImitationManualAmounts(); });
        addListeners(metalWeightInput, function() { calcAfterClearingImitationManualAmounts(); });
        addSelectListeners(calculationSelect, function() { calcAfterClearingImitationManualAmounts(); });
        addListeners(amountInput, function() { calculateModalRowNetWeight(row); });
        addListeners(netAmtInput, function() { calculateModalRowNetWeight(row); });
        addSelectListeners(discountTypeSelect, function() { calculateModalRowNetWeight(row); });
        addListeners(discountPerInput, function() { calculateModalRowNetWeight(row); });
        addSelectListeners(makingTypeSelect, function() { calcAfterClearingImitationManualAmounts(); });
        addListeners(makingRateInput, function() { calcAfterClearingImitationManualAmounts(); });
        addListeners(makingDiscountAmtInput, function() { calcAfterClearingImitationManualAmounts(); });
        addListeners(quantityInput, function() { calculateModalRowNetWeight(row); });
        row.querySelectorAll('[data-column="metal-qty"] input').forEach(function (metalQtyInput) {
            addListeners(metalQtyInput, function() { calculateModalRowNetWeight(row); });
            if (!metalQtyInput._auragoldMetalQtyBlurBound) {
                metalQtyInput._auragoldMetalQtyBlurBound = true;
                metalQtyInput.addEventListener('blur', function () {
                    if (typeof window.auragoldEnsureModalRowMetalQtyDefault === 'function') {
                        window.auragoldEnsureModalRowMetalQtyDefault(row);
                    }
                    calculateModalRowNetWeight(row);
                });
            }
        });
        row.querySelectorAll('[data-column="purchase-rate"] input').forEach(function (purchaseRateInput) {
            addListeners(purchaseRateInput, function() { calculateModalRowNetWeight(row); });
        });
        const settingChargeInput = row.querySelector('[data-column="setting-charge"] input');
        addListeners(settingChargeInput, function() { calculateModalRowNetWeight(row); });
        addSelectListeners(caratSelect, function() {
            syncCaratColumnToStoneWeight();
            auragoldSyncPurityFromCaratSelect(row);
            if (typeof window.applyDashboardMetalRateFromCaratSelect === 'function') {
                window.applyDashboardMetalRateFromCaratSelect(row, function() {
                    calculateModalRowNetWeight(row);
                });
            } else {
                calculateModalRowNetWeight(row);
            }
        });
        var caratInpListen = row.querySelector('[data-column="carat"] input');
        addListeners(caratInpListen, function() {
            syncCaratColumnToStoneWeight();
            calculateModalRowNetWeight(row);
        });
        addSelectListeners(stoneChargeTypeSelect, function() { calculateModalRowNetWeight(row); });
        addListeners(stoneWeightInput, function() { syncDiamondCaratAndLessWt('carat'); calculateModalRowNetWeight(row); if (typeof updateJewelleryDiamondCaratFromDiamondAndGemstone === 'function') updateJewelleryDiamondCaratFromDiamondAndGemstone(); });
        addListeners(stoneRateInput, function() { calculateModalRowNetWeight(row); if (typeof updateJewelleryDiamondCaratFromDiamondAndGemstone === 'function') updateJewelleryDiamondCaratFromDiamondAndGemstone(); });
        const categorySelect = row.querySelector('[data-column="category"] select');
        addSelectListeners(categorySelect, function() {
            if (typeof applyCalculationSelectOptionsForRow === 'function') applyCalculationSelectOptionsForRow(calculationSelect, row, typeof isDiamondTabActive === 'function' && isDiamondTabActive());
            if (typeof updateJewelleryDiamondCaratFromDiamondAndGemstone === 'function') updateJewelleryDiamondCaratFromDiamondAndGemstone();
            calculateModalRowNetWeight(row);
            if (typeof syncDiamondTabSharedBarcodes === 'function') syncDiamondTabSharedBarcodes();
        });
        const barcodeFieldInput = row.querySelector('[data-column="barcode"] input');
        addListeners(barcodeFieldInput, function() {
            if (row.getAttribute('data-detail-modal-sync') === '1') return;
            if (typeof syncDiamondTabSharedBarcodes === 'function') syncDiamondTabSharedBarcodes();
        });
        addListeners(otherWeightInput, function() { calculateModalRowNetWeight(row); });
        addListeners(otherRateInput, function() { calculateModalRowNetWeight(row); });
        addListeners(diamondAmountInput, function() { calculateModalRowNetWeight(row); if (typeof updateJewelleryDiamondCaratFromDiamondAndGemstone === 'function') updateJewelleryDiamondCaratFromDiamondAndGemstone(); });
        const taxTypeSelect = row.querySelector('[data-column="tax-type"] select');
        addSelectListeners(taxTypeSelect, function() { calculateModalRowNetWeight(row); });
        const taxPercentInput = row.querySelector('[data-column="tax-percent"] input');
        addListeners(taxPercentInput, function() { calculateModalRowNetWeight(row); });
        const taxInput = row.querySelector('[data-column="tax"] input');
        addListeners(taxInput, function() { calculateModalRowNetWeight(row); });
        const reverseInput = row.querySelector('[data-column="reverse"] input');
        if (reverseInput) addListeners(reverseInput, function() { calculateModalRowNetWeight(row); });
        const netAmtTaxInputForRev = row.querySelector('[data-column="net-amt-tax"] input');
        if (netAmtTaxInputForRev) addListeners(netAmtTaxInputForRev, function() { calculateModalRowNetWeight(row); });
        auragoldApplyManualAmountFieldsEditability(row);
        if (typeof applyCalculationSelectOptionsForRow === 'function') applyCalculationSelectOptionsForRow(calculationSelect, row, typeof isDiamondTabActive === 'function' && isDiamondTabActive());
        if (typeof window.ensureProductRowViewIcon === 'function') {
            window.ensureProductRowViewIcon(row);
        }
    }
    window.addModalRowCalculationListeners = addModalRowCalculationListeners;

    /** Group discount: Per. is % of base from Type (legacy On Percentage = metal value only). */
    function getDiscountBaseByType(discountType, ctx) {
        var t = discountType || 'Fix';
        if (t === 'Fix') return 0;
        if (t === 'On Percentage') return ctx.metalValue;
        if (t === 'On Making Amount') return ctx.makingAmount;
        if (t === 'On Diamond Amount') return ctx.diamondAmount;
        if (t === 'On Stone Amount') return ctx.stoneAmount;
        if (t === 'On Net Amount') return ctx.netBasePreDiscount;
        return ctx.amountBase;
    }
    function computePctDiscountFromType(discountType, discountPer, ctx) {
        var t = discountType || 'Fix';
        if (t === 'Fix') {
            var fixAmt = parseFloat(discountPer);
            return isNaN(fixAmt) ? 0 : fixAmt;
        }
        var base = getDiscountBaseByType(discountType, ctx);
        var per = parseFloat(discountPer) || 0;
        return base * (per / 100);
    }

    /** Max GST % stored on row (product opening) — used when resolve/line-sum fails. */
    function auragoldGstPercentFromRowDataAttrs(row) {
        if (!row || !row.getAttribute) return NaN;
        var L = parseFloat(row.getAttribute('data-gst-local-pct'));
        var I = parseFloat(row.getAttribute('data-gst-interstate-pct'));
        var S = parseFloat(row.getAttribute('data-gst-invoice-slab-pct'));
        var mli = Math.max(isNaN(L) ? 0 : L, isNaN(I) ? 0 : I);
        if (mli > 0) {
            return mli;
        }
        if (!isNaN(S) && S > 0) {
            return S;
        }
        return NaN;
    }

    /** Normalize one line from data-product-taxes (supports tax_type/tax_value from PHP). */
    function auragoldNormalizeProductTaxLineLocal(t) {
        if (!t || typeof t !== 'object') return null;
        if (typeof window.auragoldNormalizeProductTaxLineForGst === 'function') {
            return window.auragoldNormalizeProductTaxLineForGst(t);
        }
        var tt = String(t.tax_type != null ? t.tax_type : (t.name != null ? t.name : '')).trim();
        var pct = parseFloat(t.tax_value != null ? t.tax_value : (t.percent != null ? t.percent : t.default_value));
        if (isNaN(pct)) pct = 0;
        var tn = tt.toLowerCase();
        var scope = String(t.gst_supply_scope || '').trim();
        if (tn === 'igst') scope = 'out_of_state';
        else if (tn === 'cgst' || tn === 'sgst') scope = 'local_state';
        return {
            name: tt !== '' ? tt : String(t.name || ''),
            tax_type: tt,
            tax_value: pct,
            percent: pct,
            default_value: pct,
            gst_supply_scope: scope
        };
    }

    /** GST lines only from data-product-taxes (tbl_product_tax JSON). No Tax Master, no data-gst-line-taxes fallback. */
    function auragoldGstLineTaxesArrayForRow(row) {
        var raw = [];
        if (row && row.getAttribute) {
            var pt = row.getAttribute('data-product-taxes');
            if (pt) {
                try { raw = JSON.parse(pt); } catch (e) { raw = []; }
            }
        }
        if (!Array.isArray(raw)) raw = [];
        return raw.map(function(line) {
            return auragoldNormalizeProductTaxLineLocal(line);
        }).filter(function(x) {
            return x && (x.gst_supply_scope === 'local_state' || x.gst_supply_scope === 'out_of_state');
        });
    }
    window.auragoldGstLineTaxesArrayForRow = auragoldGstLineTaxesArrayForRow;

    /** Sum CGST+SGST vs IGST from product lines on row only (tbl_product_tax–sourced JSON). */
    function auragoldGstTaxTypeKey(t) {
        return String(t && t.tax_type != null ? t.tax_type : (t.name != null ? t.name : '')).trim().toLowerCase();
    }

    function auragoldGstLinePct(tax) {
        var v = parseFloat(tax.tax_value != null ? tax.tax_value : (tax.percent != null ? tax.percent : tax.default_value));
        return isNaN(v) ? 0 : v;
    }

    function auragoldGstPercentFromTaxMasterProductTaxes(row) {
        var fromRow = auragoldGstLineTaxesArrayForRow(row);
        var taxes = fromRow.filter(function(t) {
            var n = auragoldGstTaxTypeKey(t);
            return n === 'cgst' || n === 'sgst' || n === 'igst';
        });
        if (!taxes.length) return 0;
        var custEl = document.getElementById('customerBillingState');
        var custStr = custEl ? String(custEl.value || '').trim() : '';
        if (!custStr) custStr = String((window.customerState != null ? window.customerState : '') || '').trim();
        var ownerStr = String((window.ownerState != null ? window.ownerState : '') || '').trim();
        if (!ownerStr && window.AURAGOLD_SALE_INVOICE_OWNER_STATE) {
            ownerStr = String(window.AURAGOLD_SALE_INVOICE_OWNER_STATE || '').trim();
        }
        function norm(x) { return String(x || '').trim().toLowerCase().replace(/\s+/g, ' '); }
        var map = window.AURAGOLD_GST_STATE_BY_NAME || {};
        var oN = norm(ownerStr);
        var cN = norm(custStr);
        var statesKnown = oN && cN;
        var total = 0;
        function linePct(tax) {
            return auragoldGstLinePct(tax);
        }
        var namedCgstSgst = 0;
        var namedIgst = 0;
        taxes.forEach(function(tax) {
            var n = auragoldGstTaxTypeKey(tax);
            var v = linePct(tax);
            if (n === 'cgst' || n === 'sgst') namedCgstSgst += v;
            if (n === 'igst') namedIgst += v;
        });
        if (!statesKnown) {
            if (namedCgstSgst > 0 && namedIgst > 0) {
                return Math.max(namedCgstSgst, namedIgst);
            }
            if (namedCgstSgst > 0) {
                return namedCgstSgst;
            }
            if (namedIgst > 0) {
                return namedIgst;
            }
            var locSum = 0;
            var interSum = 0;
            taxes.forEach(function(tax) {
                var scope = String(tax.gst_supply_scope || '').trim();
                var v = linePct(tax);
                if (scope === 'local_state') locSum += v;
                if (scope === 'out_of_state') interSum += v;
            });
            return Math.max(locSum, interSum);
        }
        var oid = map[oN];
        var cid = map[cN];
        var isLocal = (oid != null && cid != null && oid === cid) || (oN === cN);
        if (isLocal && namedCgstSgst > 0) {
            return namedCgstSgst;
        }
        if (!isLocal && namedIgst > 0) {
            return namedIgst;
        }
        taxes.forEach(function(tax) {
            var scope = String(tax.gst_supply_scope || '').trim();
            var v = linePct(tax);
            if (isLocal && scope === 'local_state') total += v;
            if (!isLocal && scope === 'out_of_state') total += v;
        });
        return total;
    }
    window.auragoldGstPercentFromTaxMasterProductTaxes = auragoldGstPercentFromTaxMasterProductTaxes;

    /** Product row attrs → resolve → scoped sum from data-product-taxes only (no master %). */
    function auragoldSaleInvoiceVatPercentFallback(row) {
        var fromAttrs = auragoldGstPercentFromRowDataAttrs(row);
        if (!isNaN(fromAttrs) && fromAttrs > 0) return fromAttrs;
        if (typeof window.auragoldSaleInvoiceResolveGstEffectivePercent === 'function') {
            var eff = window.auragoldSaleInvoiceResolveGstEffectivePercent(row);
            if (!isNaN(eff) && eff > 0) return eff;
        }
        var tm = auragoldGstPercentFromTaxMasterProductTaxes(row);
        if (tm > 0) return tm;
        return 0;
    }

    /** Sale invoice: owner vs customer state → local_state (CGST+SGST) or out_of_state (IGST); % from product rows only. */
    function auragoldApplyGstTaxPercentFromRowScope(row) {
        if (!row || !row.querySelector) return;
        if (row.getAttribute('data-manual-tax-percent') === '1') return;
        if (typeof window.applyGSTForRow === 'function') {
            window.applyGSTForRow(row);
            return;
        }
        var taxPercentInputGst = row.querySelector('[data-column="tax-percent"] input');
        if (!taxPercentInputGst) return;
        var taxes = auragoldGstLineTaxesArrayForRow(row);
        function linePct(tax) {
            return auragoldGstLinePct(tax);
        }
        if (!taxes || !taxes.length) {
            if (typeof window.auragoldSaleInvoiceResolveGstEffectivePercent === 'function') {
                var effFallback = window.auragoldSaleInvoiceResolveGstEffectivePercent(row);
                if (!isNaN(effFallback) && effFallback > 0) {
                    taxPercentInputGst.value = effFallback.toFixed(2);
                    taxPercentInputGst.dataset.baseGst = String(effFallback);
                }
            }
        } else {
            var custEl = document.getElementById('customerBillingState');
            var custStr = custEl ? String(custEl.value || '').trim() : '';
            if (!custStr) custStr = String((window.customerState != null ? window.customerState : '') || '').trim();
            var ownerStr = String((window.ownerState != null ? window.ownerState : '') || '').trim();
            if (!ownerStr && window.AURAGOLD_SALE_INVOICE_OWNER_STATE) {
                ownerStr = String(window.AURAGOLD_SALE_INVOICE_OWNER_STATE || '').trim();
            }
            function norm(x) { return String(x || '').trim().toLowerCase().replace(/\s+/g, ' '); }
            var map = window.AURAGOLD_GST_STATE_BY_NAME || {};
            var oN = norm(ownerStr);
            var cN = norm(custStr);
            var statesKnown = oN && cN;
            var isLocal = false;
            if (statesKnown) {
                var oid = map[oN];
                var cid = map[cN];
                isLocal = (oid != null && cid != null && oid === cid) || (oN === cN);
            }
            var totalPercent = 0;
            if (!statesKnown) {
                var slab = parseFloat(row.getAttribute('data-gst-invoice-slab-pct')) || 0;
                var loc = parseFloat(row.getAttribute('data-gst-local-pct')) || 0;
                var inter = parseFloat(row.getAttribute('data-gst-interstate-pct')) || 0;
                totalPercent = slab > 0 ? slab : Math.max(loc, inter);
            } else {
                var gstOnly = taxes.filter(function(t) {
                    var n = auragoldGstTaxTypeKey(t);
                    return n === 'cgst' || n === 'sgst' || n === 'igst';
                });
                gstOnly.forEach(function(tax) {
                    var scope = String(tax.gst_supply_scope || '').trim();
                    var val = linePct(tax);
                    if (isLocal && scope === 'local_state') totalPercent += val;
                    if (!isLocal && scope === 'out_of_state') totalPercent += val;
                });
            }
            if (totalPercent <= 0) {
                if (typeof window.auragoldSaleInvoiceResolveGstEffectivePercent === 'function') {
                    var effLine = window.auragoldSaleInvoiceResolveGstEffectivePercent(row);
                    if (!isNaN(effLine) && effLine > 0) totalPercent = effLine;
                }
                if (totalPercent <= 0) {
                    var fa = auragoldGstPercentFromRowDataAttrs(row);
                    if (!isNaN(fa) && fa > 0) totalPercent = fa;
                }
            }
            if (totalPercent > 0) {
                taxPercentInputGst.value = totalPercent.toFixed(2);
                taxPercentInputGst.dataset.baseGst = String(totalPercent);
            }
        }
    }
    window.auragoldApplyGstTaxPercentFromRowScope = auragoldApplyGstTaxPercentFromRowScope;

    /** Voucher setting wastage_wt_calculation for row metal (tbl_voucher_settings, branch + metal_wise): GoldWt | FinalWt */
    function auragoldGetVoucherWastageWtCalculation(row) {
        var mode = 'GoldWt';
        if (typeof window.voucherSettingsByMetal !== 'object' || !window.voucherSettingsByMetal) return mode;
        var metalWise = 'Gold';
        var metalId = row && row.getAttribute ? row.getAttribute('data-metal-id') : null;
        if ((!metalId || metalId === '') && typeof currentMetalId !== 'undefined' && currentMetalId != null && currentMetalId !== '') {
            metalId = String(currentMetalId);
        }
        if (window.metals && metalId != null && metalId !== '') {
            var metal = window.metals.find(function(m) { return String(m.id) === String(metalId); });
            if (metal && (metal.display_name || metal.name)) metalWise = metal.display_name || metal.name;
        } else if (typeof currentMetalName === 'string' && currentMetalName.trim()) {
            metalWise = currentMetalName.trim();
        }
        var mwLower = String(metalWise).toLowerCase().trim();
        var vs = window.voucherSettingsByMetal[metalWise];
        if (!vs && window.voucherSettingsByMetal) {
            for (var k in window.voucherSettingsByMetal) {
                if (window.voucherSettingsByMetal.hasOwnProperty(k) && String(k).toLowerCase().trim() === mwLower) {
                    vs = window.voucherSettingsByMetal[k];
                    break;
                }
            }
        }
        if (!vs && mwLower.indexOf('diamond') !== -1) {
            vs = window.voucherSettingsByMetal['Diamond & Stones'] || window.voucherSettingsByMetal['Diamond & Stone'];
        }
        if (vs && vs.wastage_wt_calculation === 'FinalWt') mode = 'FinalWt';
        return mode;
    }
    window.auragoldGetVoucherWastageWtCalculation = auragoldGetVoucherWastageWtCalculation;

    /** Purchase invoice: Sale Amount fields are manual (default 0), not copied from Net Amt. */
    function auragoldIsPurchaseInvoiceContext() {
        if (window.isPurchaseInvoicePage === true) return true;
        if (typeof window.PRODUCT_MODAL_COLUMNS_PAGE === 'string' && window.PRODUCT_MODAL_COLUMNS_PAGE.indexOf('purchase-invoice') !== -1) return true;
        if (typeof window.SJ_DEFAULT_VOUCHER_TYPE === 'string' && window.SJ_DEFAULT_VOUCHER_TYPE === 'purchase_invoice') return true;
        try {
            if (window.location && /purchase-invoice\.php/i.test(String(window.location.pathname || ''))) return true;
            var p = new URLSearchParams(window.location.search || '');
            if (p.get('voucher') === 'purchase_invoice') return true;
        } catch (e) { /* ignore */ }
        return false;
    }
    window.auragoldIsPurchaseInvoiceContext = auragoldIsPurchaseInvoiceContext;

    /** Purchase invoice / PI stock journal: Metal Value = (Weight ÷ Metal Qty) × Purchase Rate */
    function auragoldCalcMetalValueFromPurchaseRate(row) {
        if (!row) return null;
        if (typeof auragoldIsPurchaseInvoiceContext === 'function' && !auragoldIsPurchaseInvoiceContext()) {
            return null;
        }
        var purchaseRateInp = row.querySelector('[data-column="purchase-rate"] input');
        var purchaseRate = purchaseRateInp ? (parseFloat(purchaseRateInp.value) || 0) : 0;
        if (purchaseRate <= 0) return null;
        var metalQtyInp = row.querySelector('[data-column="metal-qty"] input');
        var metalQty = metalQtyInp ? (parseFloat(metalQtyInp.value) || 0) : 0;
        if (metalQty <= 0) metalQty = 1;
        var weightInp = row.querySelector('[data-column="metal-weight"] input');
        var weight = weightInp ? (parseFloat(weightInp.value) || 0) : 0;
        if (weight <= 0) {
            var grossInp = row.querySelector('[data-column="gross-wt"] input');
            weight = grossInp ? (parseFloat(grossInp.value) || 0) : 0;
        }
        if (weight <= 0) return null;
        return (weight / metalQty) * purchaseRate;
    }
    window.auragoldCalcMetalValueFromPurchaseRate = auragoldCalcMetalValueFromPurchaseRate;

    /** Purchase invoice stock journal: Actual Making Value = frozen purchase making rate × stock journal weight (never uses editable Making Rate). */
    function auragoldCalcMakingActualValueFromPurchaseRate(row) {
        if (!row) return null;
        if (typeof auragoldIsPurchaseInvoiceContext === 'function' && !auragoldIsPurchaseInvoiceContext()) {
            return null;
        }
        var attrRate = row.getAttribute('data-purchase-making-rate');
        if (attrRate == null || attrRate === '' || isNaN(parseFloat(attrRate))) {
            return null;
        }
        var purchaseMakingRate = parseFloat(attrRate) || 0;
        if (purchaseMakingRate <= 0) return null;
        var weightInp = row.querySelector('[data-column="metal-weight"] input');
        var weight = weightInp ? (parseFloat(weightInp.value) || 0) : 0;
        if (weight <= 0) {
            var grossInp = row.querySelector('[data-column="gross-wt"] input');
            weight = grossInp ? (parseFloat(grossInp.value) || 0) : 0;
        }
        if (weight <= 0) return null;
        return purchaseMakingRate * weight;
    }
    window.auragoldCalcMakingActualValueFromPurchaseRate = auragoldCalcMakingActualValueFromPurchaseRate;

    function auragoldApplyPurchaseMakingActualValueField(row) {
        if (!row || !row.querySelector) return;
        if (typeof auragoldIsPurchaseInvoiceContext === 'function' && !auragoldIsPurchaseInvoiceContext()) {
            return;
        }
        var actualInp = row.querySelector('[data-column="making-actual-value"] input');
        if (!actualInp) return;
        actualInp.readOnly = true;
        actualInp.setAttribute('readonly', 'readonly');
        var piAct = auragoldCalcMakingActualValueFromPurchaseRate(row);
        if (piAct != null) {
            actualInp.value = piAct.toFixed(2);
        }
    }
    window.auragoldApplyPurchaseMakingActualValueField = auragoldApplyPurchaseMakingActualValueField;

    /** True when the user is actively typing in this input — do not overwrite their value mid-edit. */
    function auragoldIsInputActivelyEdited(el) {
        try {
            return !!(el && typeof document !== 'undefined' && document.activeElement === el);
        } catch (e) {
            return false;
        }
    }

    // Calculate ALL values for modal product rows - COMPREHENSIVE CALCULATION
    function calculateModalRowNetWeight(row) {
        if (row && row.jquery) row = row[0];
        if (!row || !row.querySelector) return;
        auragoldApplyGstTaxPercentFromRowScope(row);
        const grossWtInput = row.querySelector('[data-column="gross-wt"] input');
        const lessWtInput = row.querySelector('[data-column="less-wt"] input');
        const purityInput = row.querySelector('[data-column="purity"] input');
        const netWtInput = row.querySelector('[data-column="net-wt"] input');
        const purityWtInput = row.querySelector('[data-column="purity-wt"] input');
        const wastagePerInput = row.querySelector('[data-column="wastage-per"] input');
        const wastageWtInput = row.querySelector('[data-column="wastage-wt"] input');
        const finalWtInput = row.querySelector('[data-column="final-wt"] input');
        const alloyWtInput = row.querySelector('[data-column="alloy-wt"] input');
        const rateInput = row.querySelector('[data-column="rate"] input');
        const metalValueInput = row.querySelector('[data-column="metal-value"] input');
        const amountInput = row.querySelector('[data-column="amount"] input');
        const netAmtInput = row.querySelector('[data-column="net-amt"] input');
        const taxInput = row.querySelector('[data-column="tax"] input');
        const netAmtTaxInput = row.querySelector('[data-column="net-amt-tax"] input');
        const discountTypeSelect = row.querySelector('[data-column="discount-type"] select');
        const discountPerInput = row.querySelector('[data-column="discount-per"] input');
        const discountAmountInput = row.querySelector('[data-column="discount-amount"] input');
        const discountInput = row.querySelector('[data-column="discount"] input');
        const makingTypeSelect = row.querySelector('[data-column="making-type"] select');
        const makingRateInput = row.querySelector('[data-column="making-rate"] input');
        const makingDiscountAmtInput = row.querySelector('[data-column="making-discount-amt"] input');
        const makingAmountInput = row.querySelector('[data-column="making-amount"] input');
        const makingActualValueInput = row.querySelector('[data-column="making-actual-value"] input');
        const makingCostInput = row.querySelector('[data-column="making-cost"] input');
        const quantityInput = row.querySelector('[data-column="quantity"] input');
        const caratSelect = row.querySelector('[data-column="carat"] select');
        const stoneChargeTypeSelect = row.querySelector('[data-column="stone-charge-type"] select');
        const stoneWeightInput = row.querySelector('[data-column="stone-weight"] input');
        const stoneRateInput = row.querySelector('[data-column="stone-rate"] input');
        const stoneAmountInput = row.querySelector('[data-column="stone-amount"] input');
        const otherChargeTypeSelect = row.querySelector('[data-column="other-charge-type"] select');
        const otherWeightInput = row.querySelector('[data-column="other-weight"] input');
        const otherRateInput = row.querySelector('[data-column="other-rate"] input');
        const otherAmountInput = row.querySelector('[data-column="other-amount"] input');
        const diamondAmountInput = row.querySelector('[data-column="diamond-amount"] input');
        const purchaseAmountInput = row.querySelector('[data-column="purchase-amount"] input');
        const saleAmountInput = row.querySelector('[data-column="sale-amount"] input');
        const saleAmountWithInput = row.querySelector('[data-column="sale-amount-with"] input');
        if (!purityInput) return;
        auragoldEnsureStockJournalManualAmountFlags(row, purchaseAmountInput, saleAmountInput, row.querySelector('[data-column="sale-percent"] input'));
        const metalWeightInput = row.querySelector('[data-column="metal-weight"] input');
        const metalRateInputEarly = row.querySelector('[data-column="metal-rate"] input');
        const reverseInput = row.querySelector('[data-column="reverse"] input');
        const reverseVal = reverseInput ? (parseFloat(reverseInput.value) || 0) : 0;
        // Only apply reverse balance when user has entered a non-zero Reverse amount (avoid -600 when Reverse is empty or 0)
        var reverseBalanceMode = reverseInput && (parseFloat(reverseInput.value) || 0) !== 0;
        // When user uses Reverse balance (balance = reverse - net_amt_tax → Making Amount), skip voucher-based reverse (MakingRate/Rate/DiscountAmount)
        // Default MakingRate matches PHP/tbl_voucher_settings default — NOT DiscountAmount (avoids applying reverse to discount when voucher row/metal key missing)
        var reverseTargetCol = 'MakingRate';
        if (!reverseBalanceMode && reverseVal > 0 && typeof window.voucherSettingsByMetal === 'object' && window.voucherSettingsByMetal !== null) {
            var metalId = row.getAttribute('data-metal-id') || (typeof currentMetalId !== 'undefined' ? currentMetalId : null);
            var metalWise = 'Gold';
            if (window.metals && metalId != null && metalId !== '') {
                var metal = window.metals.find(function(m) { return String(m.id) === String(metalId); });
                if (metal && (metal.display_name || metal.name)) metalWise = metal.display_name || metal.name;
            }
            var vs = window.voucherSettingsByMetal[metalWise];
            if (!vs && window.voucherSettingsByMetal) {
                var mwLower = String(metalWise).toLowerCase().trim();
                for (var k in window.voucherSettingsByMetal) {
                    if (window.voucherSettingsByMetal.hasOwnProperty(k) && String(k).toLowerCase().trim() === mwLower) {
                        vs = window.voucherSettingsByMetal[k];
                        break;
                    }
                }
            }
            if (vs && vs.reverse_calculation_result_column) {
                reverseTargetCol = vs.reverse_calculation_result_column;
            }
            if (reverseTargetCol === 'MakingRate' && makingRateInput) makingRateInput.value = reverseVal.toFixed(2);
            if (reverseTargetCol === 'Rate') {
                if (metalRateInputEarly) metalRateInputEarly.value = reverseVal.toFixed(2);
                if (rateInput) rateInput.value = reverseVal.toFixed(2);
            }
        } else if (!reverseBalanceMode && reverseVal > 0 && reverseTargetCol === 'MakingRate' && makingRateInput) {
            // Page has no voucher JSON: same default as DB (MakingRate)
            makingRateInput.value = reverseVal.toFixed(2);
        }
        // Metal / scrap rows: billing uses Weight (metal) but Gross could stay stale (e.g. product default) — keep Gross = Weight + D.Weight so Gross matches Net + Less.
        var metalWtForGrossSync = metalWeightInput ? (parseFloat(metalWeightInput.value) || 0) : 0;
        var lessWtForGrossSync = lessWtInput ? (parseFloat(lessWtInput.value) || 0) : 0;
        var catSelForGrossSync = row.querySelector('[data-column="category"] select');
        var catTrimForGrossSync = (catSelForGrossSync && catSelForGrossSync.value) ? String(catSelForGrossSync.value).trim() : '';
        if (grossWtInput && metalWeightInput && metalWtForGrossSync > 0.00001) {
            if (!isAnyDiamondTabCategory(catTrimForGrossSync)) {
                grossWtInput.value = auragoldFormatWeight(metalWtForGrossSync + lessWtForGrossSync);
            }
        }
        const grossWt = grossWtInput ? (parseFloat(grossWtInput.value) || 0) : 0;
        const lessWt = lessWtInput ? (parseFloat(lessWtInput.value) || 0) : 0;
        let purity = parseFloat(purityInput.value) || 0;
        const wastagePer = parseFloat(wastagePerInput?.value) || 0;
        const goldRate = parseFloat(rateInput?.value) || 0;
        var isDiamondOrGemStone = false;
        var catVal = '';
        try {
            var catSel = row.querySelector('[data-column="category"] select');
            catVal = (catSel && catSel.value) ? (catSel.value || '').trim() : '';
            isDiamondOrGemStone = isDiamondOrGemStoneLineCategory(catVal);
        } catch (e) {}
        if (purity > 1) purity = purity / 100;
        const metalWtForCalc = metalWeightInput ? (parseFloat(metalWeightInput.value) || 0) : 0;
        var netWt;
        if (isDiamondOrGemStone) {
            netWt = lessWt;
        } else {
            netWt = Math.max(0, grossWt - lessWt);
            if (netWt <= 0 && metalWtForCalc > 0) netWt = metalWtForCalc;
        }
        if (netWtInput) netWtInput.value = auragoldFormatWeight(netWt);
        var baseGoldWt = netWt > 0.00001 ? netWt : (metalWtForCalc > 0 ? metalWtForCalc : 0);
        var metalWeightEditing = auragoldIsInputActivelyEdited(metalWeightInput);
        if (!isDiamondOrGemStone && isJewelleryDiamondCategory(catVal) && netWt > 0 && metalWeightInput && !metalWeightEditing) {
            metalWeightInput.value = auragoldFormatWeight(netWt);
            baseGoldWt = netWt;
        }
        var purityVal = parseFloat(purityInput.value) || 0;
        var purityMult = (purityVal > 1) ? (purityVal / 100) : purityVal;
        var wastageWtMode = auragoldGetVoucherWastageWtCalculation(row);
        var wastageWt;
        var purityWt;
        var finalWt;
        if (wastageWtMode === 'GoldWt' && !isDiamondOrGemStone) {
            wastageWt = baseGoldWt * (wastagePer / 100);
            if (wastageWtInput) wastageWtInput.value = auragoldFormatWeight(wastageWt);
            var metalWithWastage = baseGoldWt + wastageWt;
            // Keep Weight editable: do not force "0" (or recalculated wt) while the user is clearing/typing
            if (metalWeightInput && !metalWeightEditing) {
                metalWeightInput.value = auragoldFormatWeight(metalWithWastage);
            }
            purityWt = metalWithWastage * purityMult;
            if (purityWtInput) purityWtInput.value = auragoldFormatWeight(purityWt);
            finalWt = purityWt;
        } else {
            var weightForPurity = baseGoldWt > 0 ? baseGoldWt : (metalWtForCalc > 0 ? metalWtForCalc : netWt);
            purityWt = isDiamondOrGemStone ? (netWt * purity) : (weightForPurity * purityMult);
            if (purityWtInput) purityWtInput.value = auragoldFormatWeight(purityWt);
            var wastageBase = (wastageWtMode === 'FinalWt') ? purityWt : baseGoldWt;
            wastageWt = wastageBase * (wastagePer / 100);
            if (wastageWtInput) wastageWtInput.value = auragoldFormatWeight(wastageWt);
            finalWt = (wastageWtMode === 'FinalWt') ? (purityWt + wastageWt) : purityWt;
        }
        if (finalWtInput) finalWtInput.value = auragoldFormatWeight(finalWt);
        if (alloyWtInput && (!alloyWtInput.value || alloyWtInput.value.trim() === '')) alloyWtInput.value = '0.000';
        const calculationSelect = row.querySelector('[data-column="calculation"] select');
        const categorySelect = row.querySelector('[data-column="category"] select');
        const calculationType = calculationSelect ? calculationSelect.value : 'Rate X Gross Wt';
        const categoryId = categorySelect ? categorySelect.value : '';
        const metalRateInput = row.querySelector('[data-column="metal-rate"] input');
        const metalRate = parseFloat(metalRateInput?.value) || 0;
        const metalWeightForCalc = parseFloat(row.querySelector('[data-column="metal-weight"] input')?.value) || 0;
        const isJewellery = isJewelleryDiamondCategory(categoryId);
        const isDiamondOrStone = isDiamondOrGemStoneLineCategory(categoryId);
        const rateForMetalValue = isDiamondOrStone ? goldRate : metalRate;
        let metalValue = 0;
        if (calculationType === 'Fix') metalValue = rateForMetalValue;
        else if (calculationType === 'Carat X Rate') metalValue = getCaratForCaratXRateCalc(row) * goldRate;
        else if (isQtyXRateCalculationType(calculationType)) {
            var qtyRateVal = rateForMetalValue > 0 ? rateForMetalValue : goldRate;
            metalValue = getQuantityForQtyXRateCalc(row) * qtyRateVal;
        } else if (calculationType === 'Stone Charge') {
            const stoneWeight = parseFloat(stoneWeightInput?.value) || 0;
            const stoneRate = parseFloat(stoneRateInput?.value) || 0;
            const stoneAmount = stoneWeight * stoneRate;
            metalValue = stoneAmount;
            if (stoneAmountInput) stoneAmountInput.value = stoneAmount.toFixed(2);
        } else if (calculationType === 'Weight X Rate') metalValue = rateForMetalValue * finalWt;
        else if (calculationType === 'Rate X Gross Wt') {
            const grossForCalc = isDiamondOrStone ? grossWt : ((metalWeightForCalc > 0) ? metalWeightForCalc : grossWt);
            metalValue = rateForMetalValue * grossForCalc;
        } else if (calculationType === 'Rate X Purity Wt') metalValue = rateForMetalValue * purityWt;
        else if (calculationType === 'Rate X Net Wt') metalValue = rateForMetalValue * netWt;
        else if (calculationType === 'Rate X Final Wt') metalValue = rateForMetalValue * finalWt;
        else if (calculationType === 'Metal Rate x Metal Weight') metalValue = metalRate * metalWeightForCalc;
        else if (calculationType === 'Metal Carat x Metal Rate') {
            const caratSel = row.querySelector('[data-column="carat"] select');
            metalValue = (caratSel ? parseFloat(caratSel.value) || 0 : 0) * metalRate;
        } else if (calculationType === 'Metal Rate x Metal Purity') metalValue = metalRate * purityWt;
        else metalValue = rateForMetalValue * (parseFloat(finalWtInput?.value) || netWt);
        if (calculationType !== 'Stone Charge') {
            var piMetalVal = auragoldCalcMetalValueFromPurchaseRate(row);
            if (piMetalVal != null) metalValue = piMetalVal;
        }
        if (metalValueInput) metalValueInput.value = metalValue.toFixed(2);
        let makingAmount = 0, makingActualValue = 0, makingCost = 0;
        if (makingTypeSelect && makingRateInput) {
            const makingType = makingTypeSelect.value || 'Fix';
            const makingRate = parseFloat(makingRateInput.value) || 0;
            const makingDiscountAmt = parseFloat(makingDiscountAmtInput?.value) || 0;
            const netWtVal = parseFloat(netWtInput?.value) || 0;
            const finalWtVal = parseFloat(finalWtInput?.value) || netWtVal;
            const quantity = parseFloat(quantityInput?.value) || 1;
            const caratValue = caratSelect ? parseFloat(caratSelect.value) || 0 : 0;
            // Per Gram / Per Kilogram: use metal weight (same basis as Rate X Gross Wt), not purity/final wt
            var wtForMaking = metalWeightForCalc > 0 ? metalWeightForCalc : (netWtVal > 0 ? netWtVal : finalWtVal);
            switch (makingType) {
                case 'Fix': makingAmount = makingRate; break;
                case 'Per Gram': makingAmount = makingRate * wtForMaking; break;
                case 'Per Piece': makingAmount = makingRate * quantity; break;
                case 'Per Kilogram': makingAmount = makingRate * (wtForMaking / 1000); break;
                case 'Per Percent': makingAmount = metalValue * (makingRate / 100); break;
                case 'MRP': makingAmount = makingRate; break;
                case 'M.KT': makingAmount = makingRate * caratValue; break;
                default: makingAmount = makingRate;
            }
            makingAmount = Math.max(0, makingAmount - makingDiscountAmt);
            makingCost = makingAmount;
            if (makingAmountInput) makingAmountInput.value = makingAmount.toFixed(2);
            if (makingCostInput) makingCostInput.value = makingCost.toFixed(2);
        }
        if (typeof auragoldApplyPurchaseMakingActualValueField === 'function') {
            auragoldApplyPurchaseMakingActualValueField(row);
        } else if (makingActualValueInput && makingTypeSelect && makingRateInput) {
            makingActualValue = makingAmount;
            makingActualValueInput.value = makingActualValue.toFixed(2);
        }
        let stoneAmount = 0;
        const settingChargeInput = row.querySelector('[data-column="setting-charge"] input');
        const isDiamondCatForSetting = isDiamondOrGemStoneLineCategory(categoryId);
        if (settingChargeInput && isDiamondCatForSetting) {
            const diamondQty = parseFloat(quantityInput?.value) || 1;
            const settingCharge = parseFloat(settingChargeInput.value) || 0;
            stoneAmount = diamondQty * settingCharge;
            if (stoneAmountInput) stoneAmountInput.value = stoneAmount.toFixed(2);
        } else if (stoneChargeTypeSelect && stoneRateInput) {
            const stoneChargeType = stoneChargeTypeSelect.value || 'Fix';
            const stoneRate = parseFloat(stoneRateInput.value) || 0;
            stoneAmount = (stoneChargeType === 'Fix') ? stoneRate : (parseFloat(stoneWeightInput?.value) || 0) * stoneRate;
            if (stoneAmountInput) stoneAmountInput.value = stoneAmount.toFixed(2);
        }
        if (calculationType === 'Stone Charge') {
            metalValue = stoneAmount;
            if (metalValueInput) metalValueInput.value = metalValue.toFixed(2);
        }
        let otherAmount = 0;
        if (otherWeightInput && otherRateInput) otherAmount = (parseFloat(otherWeightInput.value) || 0) * (parseFloat(otherRateInput.value) || 0);
        if (otherAmountInput) otherAmountInput.value = otherAmount.toFixed(2);

        const diamondAmount = parseFloat(diamondAmountInput?.value) || 0;
        const stonePartForAmount = (calculationType === 'Stone Charge' ? 0 : stoneAmount);
        const amountBase = metalValue + makingAmount + stonePartForAmount + otherAmount;
        const netBasePreDiscount = amountBase + diamondAmount;
        const discountCtx = {
            metalValue: metalValue,
            makingAmount: makingAmount,
            stoneAmount: stoneAmount,
            otherAmount: otherAmount,
            diamondAmount: diamondAmount,
            amountBase: amountBase,
            netBasePreDiscount: netBasePreDiscount
        };
        let discount1 = 0;
        let discount2 = 0;
        if (discountTypeSelect && discountPerInput) {
            var dt1 = discountTypeSelect.value || 'Fix';
            var base1 = getDiscountBaseByType(dt1, discountCtx);
            var per1 = parseFloat(discountPerInput.value) || 0;
            discount1 = computePctDiscountFromType(dt1, discountPerInput.value, discountCtx);
            if (discountAmountInput) {
                if (dt1 === 'Fix') {
                    discountAmountInput.value = '0.00';
                    discountAmountInput.setAttribute('title', 'Fix: enter fixed discount amount (₹) in Per. column.');
                } else if (per1 > 0) {
                    discountAmountInput.value = base1.toFixed(2);
                    discountAmountInput.setAttribute('title', 'Base for discount % (depends on Type: On Amount = metal+making+stone+other). Not from Reverse voucher setting.');
                } else {
                    discountAmountInput.value = '0.00';
                    discountAmountInput.setAttribute('title', 'Disc. base stays 0 until you enter Per. (%) for a percentage discount.');
                }
            }
        }
        var totalDiscount = discount1;
        if (discountInput) discountInput.value = totalDiscount.toFixed(2);
        // Reverse (DiscountAmount): add to display discount only; do not deduct from line net amount (per business rule)
        if (!reverseBalanceMode && reverseVal > 0 && reverseTargetCol === 'DiscountAmount') {
            if (discountInput) discountInput.value = (totalDiscount + reverseVal).toFixed(2);
        }

        // Do NOT deduct discount from line Amount/Net Amt — discount is informational; line total stays metal+making+stone+other
        let calculatedAmount = metalValue + makingAmount + (calculationType === 'Stone Charge' ? 0 : stoneAmount) + otherAmount;
        if (calculatedAmount < 0) calculatedAmount = 0;
        let netAmt = calculatedAmount + diamondAmount;
        var isManualAmtCtx = auragoldAllowManualPurchaseSaleAmounts();
        var salePercentCtx = auragoldAllowSalePercentPurchaseCalc();
        var isPurchasePiCtx = auragoldIsPurchaseInvoiceContext();
        var useMetalRateNetOnSale = auragoldSkipStockJournalAmountLockOnRow(row, null);
        var manualSaleAmt = salePercentCtx && row.getAttribute('data-manual-sale-amount') === '1' && !useMetalRateNetOnSale;
        var manualPurchaseAmt = salePercentCtx && row.getAttribute('data-manual-purchase-amount') === '1' && !useMetalRateNetOnSale;
        var salePercentApplied = false;
        var piCostBase = netAmt;
        if (salePercentCtx && !useMetalRateNetOnSale) {
            if (manualSaleAmt) {
                auragoldApplySalePercentFromSaleAmount(row);
            } else {
                salePercentApplied = auragoldApplySalePercentFromPurchase(row);
            }
        }
        if (manualSaleAmt && saleAmountInput) {
            netAmt = parseFloat(saleAmountInput.value) || 0;
            if (netAmt < 0) netAmt = 0;
            calculatedAmount = Math.max(0, netAmt - diamondAmount);
        } else if (salePercentApplied && saleAmountInput) {
            netAmt = parseFloat(saleAmountInput.value) || 0;
            if (netAmt < 0) netAmt = 0;
            calculatedAmount = Math.max(0, netAmt - diamondAmount);
        } else if (manualPurchaseAmt && purchaseAmountInput) {
            netAmt = parseFloat(purchaseAmountInput.value) || 0;
            if (netAmt < 0) netAmt = 0;
            calculatedAmount = Math.max(0, netAmt - diamondAmount);
        }
        // Jewellery row: Amount column shows diamond_amount only (Diamond group sum), not total
        if (amountInput) amountInput.value = (categoryId === 'Jewellery' && diamondAmount > 0) ? diamondAmount.toFixed(2) : calculatedAmount.toFixed(2);
        if (netAmtInput) netAmtInput.value = netAmt.toFixed(2);
        var manualSaleWithEarly = !isPurchasePiCtx && salePercentCtx && row.getAttribute('data-manual-sale-amount-with') === '1';
        // Do not overwrite Purchase Amount when user is driving price via Sale Amount / Sale Amount With Tax.
        if (purchaseAmountInput && !manualPurchaseAmt && !manualSaleWithEarly && !manualSaleAmt
            && !auragoldIsInputActivelyEdited(purchaseAmountInput)) {
            if (isPurchasePiCtx && salePercentApplied && piCostBase > 0) {
                purchaseAmountInput.value = piCostBase.toFixed(2);
            } else {
                purchaseAmountInput.value = netAmt.toFixed(2);
            }
        }
        if (!isPurchasePiCtx) {
            if (saleAmountInput && !manualSaleAmt && !salePercentApplied && !auragoldIsInputActivelyEdited(saleAmountInput)) {
                saleAmountInput.value = netAmt.toFixed(2);
            }
            if (saleAmountWithInput && !salePercentApplied && !manualSaleAmt) {
                saleAmountWithInput.value = netAmt.toFixed(2);
            }
        }
        const taxTypeSelect = row.querySelector('[data-column="tax-type"] select');
        const taxType = taxTypeSelect ? taxTypeSelect.value : 'no_tax';
        const taxPercentInput = row.querySelector('[data-column="tax-percent"] input');
        if (typeof window.setSaleInvoiceGstTaxPercentDisplay === 'function' && taxPercentInput
            && row.getAttribute('data-manual-tax-percent') !== '1') {
            window.setSaleInvoiceGstTaxPercentDisplay(row, taxPercentInput);
        }
        var vatPercent = 0;
        if (typeof window.applyGSTForRow === 'function' && taxPercentInput) {
            vatPercent = parseFloat(taxPercentInput.value) || 0;
        } else if (typeof window.getSaleInvoiceRowEffectiveTaxPercent === 'function') {
            var effGst = window.getSaleInvoiceRowEffectiveTaxPercent(row, taxPercentInput);
            if (effGst !== null && effGst !== undefined && !isNaN(effGst) && effGst > 0) {
                vatPercent = effGst;
            }
        }
        if (vatPercent <= 0 && taxPercentInput) {
            var tv0 = String(taxPercentInput.value || '').trim();
            if (tv0.indexOf('+') !== -1) {
                var ps0 = tv0.split('+');
                var a0 = parseFloat(ps0[0].trim()) || 0;
                var b0 = parseFloat(ps0[1] ? ps0[1].trim() : '') || 0;
                if (a0 > 0 && b0 > 0) vatPercent = a0 + b0;
                else if (a0 > 0) vatPercent = a0 * 2;
            } else {
                vatPercent = parseFloat(taxPercentInput.value) || 0;
            }
        }
        if (vatPercent <= 0) vatPercent = auragoldSaleInvoiceVatPercentFallback(row);
        // Keep Tax % in sync with the rate used for line tax (product GST or legacy 5% VAT fallback).
        // Skip overwrite when user typed Tax % for reverse Sale Amount With Tax calc.
        if (taxPercentInput && (taxType === 'tax_of_netamount' || taxType === 'tax_on_making')
            && row.getAttribute('data-manual-tax-percent') !== '1'
            && !auragoldIsInputActivelyEdited(taxPercentInput)) {
            taxPercentInput.value = vatPercent.toFixed(2);
            if (taxPercentInput.dataset) taxPercentInput.dataset.baseGst = String(vatPercent);
        }
        if (row.getAttribute('data-manual-tax-percent') === '1' && taxPercentInput) {
            vatPercent = parseFloat(taxPercentInput.value) || 0;
        }
        var manualSaleWith = !isPurchasePiCtx && salePercentCtx && row.getAttribute('data-manual-sale-amount-with') === '1';
        var reverseFromInclusive = null;
        if (manualSaleWith) {
            reverseFromInclusive = auragoldApplySaleAmountFromSaleAmountWithTax(row, vatPercent, taxType, makingAmount);
        }
        let tax = (taxType === 'tax_of_netamount') ? (netAmt * (vatPercent / 100)) : (taxType === 'tax_on_making') ? (makingAmount * (vatPercent / 100)) : 0;
        let netAmtTax = tax + netAmt;
        if (reverseFromInclusive) {
            netAmt = reverseFromInclusive.netAmt;
            tax = reverseFromInclusive.tax;
            netAmtTax = reverseFromInclusive.netAmtTax;
            calculatedAmount = Math.max(0, netAmt - diamondAmount);
            if (amountInput) amountInput.value = (categoryId === 'Jewellery' && diamondAmount > 0) ? diamondAmount.toFixed(2) : calculatedAmount.toFixed(2);
            if (netAmtInput) netAmtInput.value = netAmt.toFixed(2);
            if (saleAmountInput && !auragoldIsInputActivelyEdited(saleAmountInput)) {
                saleAmountInput.value = netAmt.toFixed(2);
            }
            // Recalc Sale % after Sale Amount is finalized (purchase kept as cost base).
            auragoldApplySalePercentFromSaleAmount(row);
        }
        if (taxInput) taxInput.value = tax.toFixed(2);
        if (netAmtTaxInput) netAmtTaxInput.value = netAmtTax.toFixed(2);
        if (saleAmountWithInput) {
            if (manualSaleWith) {
                // Keep user-entered tax-inclusive total (do not overwrite with forward calc).
                if (!auragoldIsInputActivelyEdited(saleAmountWithInput)) {
                    var keptInclusive = parseFloat(saleAmountWithInput.value) || 0;
                    if (keptInclusive > 0) saleAmountWithInput.value = keptInclusive.toFixed(2);
                }
            } else if (isPurchasePiCtx) {
                if (salePercentApplied || manualSaleAmt) {
                    saleAmountWithInput.value = netAmtTax.toFixed(2);
                }
            } else {
                saleAmountWithInput.value = netAmtTax.toFixed(2);
            }
        }

        // Reverse balance mode:
        // You enter `Reverse = Net Amt+Tax` (net-amt-tax).
        // So we compute the required `makingAmount` so that after tax recalculation, net-amt-tax matches `reverseInput`.
        if (reverseBalanceMode) {
            var revAmt = parseFloat(reverseInput.value) || 0;

            // base0 = net components excluding making; netAmt = base0 + makingAmount
            // (matches later: amountBase2 = metalValue + makingAmount + stonePartForAmount + otherAmount; netAmt = amountBase2 + diamondAmount)
            var base0 = metalValue + stonePartForAmount + otherAmount + diamondAmount;

            var factor = 1 + (vatPercent / 100); // used in both tax_of_netamount and tax_on_making

            var coefficient = 1; // derivative of netAmtTax w.r.t makingAmount
            if (taxType === 'tax_of_netamount') coefficient = factor;
            else if (taxType === 'tax_on_making') coefficient = factor;
            else coefficient = 1;

            // Solve makingAmount from:
            // tax_of_netamount: netAmtTax = (base0 + makingAmount) * factor
            // tax_on_making:    netAmtTax = base0 + makingAmount * factor
            // no_tax:          netAmtTax = base0 + makingAmount
            if (taxType === 'tax_of_netamount') {
                makingAmount = (revAmt / factor) - base0;
            } else if (taxType === 'tax_on_making') {
                makingAmount = (revAmt - base0) / factor;
            } else {
                makingAmount = revAmt - base0;
            }

            // Recompute with the solved makingAmount
            var amountBase2 = metalValue + makingAmount + stonePartForAmount + otherAmount;
            netAmt = amountBase2 + diamondAmount;

            // amount/ net amounts update (same as main flow)
            if (amountInput) amountInput.value = (categoryId === 'Jewellery' && diamondAmount > 0) ? diamondAmount.toFixed(2) : amountBase2.toFixed(2);
            if (netAmtInput) netAmtInput.value = netAmt.toFixed(2);
            if (purchaseAmountInput && !manualPurchaseAmt && !auragoldIsInputActivelyEdited(purchaseAmountInput)) {
                purchaseAmountInput.value = netAmt.toFixed(2);
            }
            salePercentApplied = auragoldApplySalePercentFromPurchase(row);
            if (!isPurchasePiCtx && saleAmountInput && !manualSaleAmt && !salePercentApplied && !auragoldIsInputActivelyEdited(saleAmountInput)) {
                saleAmountInput.value = netAmt.toFixed(2);
            }

            // Disc. base: 0 for Fix or 0% ; show base only when % discount Per. > 0
            if (discountAmountInput) {
                var dt1rb = discountTypeSelect ? (discountTypeSelect.value || 'Fix') : 'Fix';
                var per1rb = discountPerInput ? (parseFloat(discountPerInput.value) || 0) : 0;
                if (dt1rb === 'Fix' || per1rb <= 0) discountAmountInput.value = '0.00';
                else discountAmountInput.value = amountBase2.toFixed(2);
            }

            // Recompute tax and netAmtTax after making update
            tax = (taxType === 'tax_of_netamount') ? (netAmt * (vatPercent / 100)) : (taxType === 'tax_on_making') ? (makingAmount * (vatPercent / 100)) : 0;
            netAmtTax = tax + netAmt;

            // Minor rounding compensation so net-amt-tax displayed matches reverse to 2 decimals
            var delta = revAmt - netAmtTax;
            if (Math.abs(delta) > 0.00001) {
                makingAmount = makingAmount + (delta / coefficient);
                amountBase2 = metalValue + makingAmount + stonePartForAmount + otherAmount;
                netAmt = amountBase2 + diamondAmount;
                tax = (taxType === 'tax_of_netamount') ? (netAmt * (vatPercent / 100)) : (taxType === 'tax_on_making') ? (makingAmount * (vatPercent / 100)) : 0;
                netAmtTax = tax + netAmt;
            }

            if (makingAmountInput) makingAmountInput.value = makingAmount.toFixed(2);
            if (typeof auragoldApplyPurchaseMakingActualValueField === 'function') {
                auragoldApplyPurchaseMakingActualValueField(row);
            } else if (makingActualValueInput) {
                makingActualValueInput.value = makingAmount.toFixed(2);
            }
            if (makingCostInput) makingCostInput.value = makingAmount.toFixed(2);

            if (taxInput) taxInput.value = tax.toFixed(2);
            if (netAmtTaxInput) netAmtTaxInput.value = revAmt.toFixed(2);
            if (!isPurchasePiCtx && saleAmountWithInput) saleAmountWithInput.value = revAmt.toFixed(2);
        }

        var rowTbody = row.closest('tbody');
        if (rowTbody && (rowTbody.id === 'productListBody' || rowTbody.id === 'productListBodyPage')) {
            if (categoryId !== 'Jewellery' && typeof updateJewelleryDiamondCaratFromDiamondAndGemstone === 'function') {
                updateJewelleryDiamondCaratFromDiamondAndGemstone();
            } else if (isAnyDiamondTabCategory(categoryId)) {
                if (typeof updateDiamondTabFcAmountAndLineMetalValue === 'function') updateDiamondTabFcAmountAndLineMetalValue();
                if (typeof updateJewelleryNetAmountAndFinal === 'function') updateJewelleryNetAmountAndFinal();
            }
        }
        if (typeof window.auragoldEnsureModalRowMetalQtyDefault === 'function') {
            window.auragoldEnsureModalRowMetalQtyDefault(row);
        }
    }
    window.calculateModalRowNetWeight = calculateModalRowNetWeight;

    // ========== PRODUCT LIST ROW CALCULATION (main table) ==========
    function addRowCalculationListeners(row) {
        function clearPreserveAndCalc() {
            if (row) row.removeAttribute('data-preserve-line-amounts');
            calculateRowAmounts(row);
        }
        const editableFields = row.querySelectorAll('.editable-field');
        editableFields.forEach(function(field) {
            field.addEventListener('input', function() { clearPreserveAndCalc(); });
            field.addEventListener('change', function() { clearPreserveAndCalc(); });
            field.addEventListener('focus', function() { this.style.background = '#fff'; this.style.border = '1px solid #11294b'; });
            field.addEventListener('blur', function() { this.style.background = 'transparent'; this.style.border = 'none'; });
        });
        function addCalcListener(el, fn) {
            if (el) { el.addEventListener('input', fn); el.addEventListener('change', fn); el.addEventListener('keyup', fn); }
        }
        addCalcListener(row.querySelector('[data-field="gross_wt"]'), function() { clearPreserveAndCalc(); });
        addCalcListener(row.querySelector('[data-field="less_wt"]'), function() { clearPreserveAndCalc(); });
        addCalcListener(row.querySelector('[data-field="purity"]'), function() { clearPreserveAndCalc(); });
        addCalcListener(row.querySelector('[data-column="metal-rate"] input'), function() { clearPreserveAndCalc(); });
        addCalcListener(row.querySelector('[data-column="metal-weight"] input'), function() { clearPreserveAndCalc(); });
        addCalcListener(row.querySelector('[data-column="quantity"] input'), function() { clearPreserveAndCalc(); });
        addCalcListener(row.querySelector('[data-column="setting-charge"] input'), function() { clearPreserveAndCalc(); });
        if (auragoldIsPurchaseInvoiceContext()) {
            function piSaleAmountPlChanged() {
                if (typeof updateSummaryPanel === 'function') updateSummaryPanel();
                if (typeof updateSummaryRow === 'function') updateSummaryRow();
            }
            addCalcListener(row.querySelector('[data-column="sale-amount"] input'), piSaleAmountPlChanged);
            addCalcListener(row.querySelector('[data-column="sale-amount-with"] input'), piSaleAmountPlChanged);
        }
        const calculationSelect = row.querySelector('[data-column="calculation"] select');
        if (calculationSelect) {
            calculationSelect.addEventListener('change', function() {
                row.setAttribute('data-calculation-type', this.value || 'Rate X Gross Wt');
                clearPreserveAndCalc();
            });
        }
        const categorySelect = row.querySelector('[data-column="category"] select');
        if (categorySelect) {
            categorySelect.addEventListener('change', function() { clearPreserveAndCalc(); });
        }
        const taxTypeSelectRow = row.querySelector('[data-column="tax-type"] select');
        if (taxTypeSelectRow) {
            taxTypeSelectRow.addEventListener('change', function() { clearPreserveAndCalc(); });
        }
        if (typeof window.ensureProductRowViewIcon === 'function') {
            window.ensureProductRowViewIcon(row);
        }
    }
    window.addRowCalculationListeners = addRowCalculationListeners;

    function calculateRowAmounts(row) {
        auragoldApplyGstTaxPercentFromRowScope(row);
        // Edit invoice reload: weights may be 0 while DB has correct net/tax — do not overwrite until user edits the row.
        if (row && row.getAttribute('data-preserve-line-amounts') === '1') {
            var taxPercentInputPl0 = row.querySelector('[data-column="tax-percent"] input');
            if (typeof window.setSaleInvoiceGstTaxPercentDisplay === 'function' && taxPercentInputPl0) {
                window.setSaleInvoiceGstTaxPercentDisplay(row, taxPercentInputPl0);
            }
            return;
        }
        const grossInp = row.querySelector('[data-field="gross_wt"]') || row.querySelector('[data-column="gross-wt"] input');
        const lessInp = row.querySelector('[data-field="less_wt"]') || row.querySelector('[data-column="less-wt"] input');
        const metalCellEarly = row.querySelector('[data-column="metal-weight"]');
        const metalInpEarly = metalCellEarly ? metalCellEarly.querySelector('input') : null;
        const catSelEarly = row.querySelector('[data-column="category"] select');
        var catTrimEarly = catSelEarly ? String(catSelEarly.value || '').trim() : '';
        var mwEarly = 0;
        if (metalInpEarly) mwEarly = parseFloat(metalInpEarly.value) || 0;
        else if (metalCellEarly) mwEarly = parseFloat(String(metalCellEarly.textContent || '').replace(/,/g, '')) || 0;
        var lessEarly = lessInp ? (parseFloat(lessInp.value) || 0) : 0;
        if (grossInp && mwEarly > 0.00001) {
            if (!isAnyDiamondTabCategory(catTrimEarly)) {
                grossInp.value = auragoldFormatWeight(mwEarly + lessEarly);
            }
        }
        const grossWt = parseFloat(grossInp?.value) || 0;
        const lessWt = parseFloat(lessInp?.value) || 0;
        const netWt = Math.max(0, grossWt - lessWt);
        const finalWtField = row.querySelector('[data-field="final_wt"]') || row.querySelector('[data-column="final-wt"] input');
        const userFinalWt = finalWtField ? (parseFloat(finalWtField.value) || 0) : 0;
        const purityInput = row.querySelector('[data-field="purity"]') || row.querySelector('[data-column="purity"] input');
        let purity = parseFloat(purityInput?.value) || 0;
        const making = parseFloat(row.querySelector('[data-field="making"]')?.value) || 0;
        const stoneCharges = parseFloat(row.querySelector('[data-field="stone_charges"]')?.value) || 0;
        const otherCharges = parseFloat(row.querySelector('[data-field="other_charges"]')?.value) || 0;
        const diamondValue = parseFloat(row.querySelector('[data-field="diamond_value"]')?.value) || 0;
        const gemstoneValue = parseFloat(row.querySelector('[data-field="gemstone_value"]')?.value) || 0;
        if (!purity || purity === 0) purity = parseFloat(row.getAttribute('data-purity')) || 0;
        if (purity > 1) purity = purity / 100;
        row.setAttribute('data-purity', purity);
        const rate = parseFloat(row.getAttribute('data-rate')) || 0;
        const categorySelect = row.querySelector('[data-column="category"] select');
        const categoryId = categorySelect ? categorySelect.value : '';
        const metalRateInput = row.querySelector('[data-column="metal-rate"] input');
        const metalRate = metalRateInput ? (parseFloat(metalRateInput.value) || 0) : 0;
        const metalWeightCell = row.querySelector('[data-column="metal-weight"]');
        const metalWeightInput = row.querySelector('[data-column="metal-weight"] input');
        var metalWt = metalWeightInput ? (parseFloat(metalWeightInput.value) || 0) : 0;
        if (!metalWt && metalWeightCell) {
            metalWt = parseFloat(metalWeightCell.textContent) || 0;
        }
        const rateForMetalValue = (isJewelleryDiamondCategory(categoryId) || metalWt > 0) ? metalRate : rate;
        const calculationSelect = row.querySelector('[data-column="calculation"] select');
        const calculationType = calculationSelect ? (calculationSelect.value || 'Rate X Gross Wt') : (row.getAttribute('data-calculation-type') || 'Rate X Gross Wt');
        var purityDecimal = parseFloat(purityInput?.value);
        if (isNaN(purityDecimal) || purityDecimal === 0) purityDecimal = purity;
        if (purityDecimal > 1) purityDecimal = purityDecimal / 100;
        var weightForPurity;
        if (userFinalWt > 0 && userFinalWt <= netWt + 1e-6) {
            weightForPurity = userFinalWt;
        } else if (metalWt > 0) {
            weightForPurity = metalWt;
        } else {
            weightForPurity = netWt;
        }
        var baseGoldWtPl = netWt > 0.00001 ? netWt : (metalWt > 0 ? metalWt : 0);
        const wastagePerPl = parseFloat(row.querySelector('[data-column="wastage-per"] input')?.value) || 0;
        var wastageWtModePl = auragoldGetVoucherWastageWtCalculation(row);
        var wastageWtPl;
        var pureWt;
        var calculatedFinalWt;
        const metalWeightInputPl = row.querySelector('[data-column="metal-weight"] input');
        if (wastageWtModePl === 'GoldWt') {
            wastageWtPl = baseGoldWtPl * (wastagePerPl / 100);
            const wastageWtFieldPl = row.querySelector('[data-column="wastage-wt"] input');
            if (wastageWtFieldPl) wastageWtFieldPl.value = auragoldFormatWeight(wastageWtPl);
            var metalWithWastagePl = baseGoldWtPl + wastageWtPl;
            if (metalWeightInputPl && !auragoldIsInputActivelyEdited(metalWeightInputPl)) {
                metalWeightInputPl.value = auragoldFormatWeight(metalWithWastagePl);
            }
            pureWt = metalWithWastagePl * purityDecimal;
            calculatedFinalWt = pureWt;
        } else {
            pureWt = weightForPurity * purityDecimal;
            var wastageBasePl = (wastageWtModePl === 'FinalWt') ? pureWt : baseGoldWtPl;
            wastageWtPl = wastageBasePl * (wastagePerPl / 100);
            const wastageWtFieldPl = row.querySelector('[data-column="wastage-wt"] input');
            if (wastageWtFieldPl) wastageWtFieldPl.value = auragoldFormatWeight(wastageWtPl);
            calculatedFinalWt = (wastageWtModePl === 'FinalWt') ? (pureWt + wastageWtPl) : pureWt;
        }
        if (finalWtField) finalWtField.value = auragoldFormatWeight(calculatedFinalWt);
        const effectiveFinalWt = calculatedFinalWt;
        if (metalWeightInputPl) metalWt = parseFloat(metalWeightInputPl.value) || metalWt;
        let metalValue = 0;
        if (calculationType === 'Fix') metalValue = rateForMetalValue;
        else if (calculationType === 'Carat X Rate') metalValue = getCaratForCaratXRateCalc(row) * rate;
        else if (isQtyXRateCalculationType(calculationType)) {
            var qtyRateValPl = rateForMetalValue > 0 ? rateForMetalValue : rate;
            metalValue = getQuantityForQtyXRateCalc(row) * qtyRateValPl;
        } else if (calculationType === 'Stone Charge') metalValue = stoneCharges;
        else if (calculationType === 'Weight X Rate') metalValue = rateForMetalValue * effectiveFinalWt;
        else if (calculationType === 'Rate X Gross Wt') {
            var billGrossWt;
            if (userFinalWt > 0 && userFinalWt <= netWt + 1e-6) billGrossWt = userFinalWt;
            else if (metalWt > 0) billGrossWt = metalWt;
            else billGrossWt = grossWt;
            metalValue = rateForMetalValue * billGrossWt;
        }
        else if (calculationType === 'Rate X Purity Wt') metalValue = rateForMetalValue * pureWt;
        else if (calculationType === 'Rate X Net Wt') metalValue = rateForMetalValue * netWt;
        else if (calculationType === 'Rate X Final Wt') metalValue = rateForMetalValue * effectiveFinalWt;
        else if (calculationType === 'Metal Rate x Metal Weight') metalValue = metalRate * metalWt;
        else if (calculationType === 'Metal Rate x Metal Purity') metalValue = metalRate * pureWt;
        else metalValue = rateForMetalValue * effectiveFinalWt;
        if (calculationType !== 'Stone Charge') {
            var piMetalValPl = auragoldCalcMetalValueFromPurchaseRate(row);
            if (piMetalValPl != null) metalValue = piMetalValPl;
        }
        function readNumericCell(column, fallback) {
            const cell = row.querySelector('[data-column="' + column + '"]');
            if (!cell) return fallback;
            const inp = cell.querySelector('input');
            if (inp) {
                var v = parseFloat(inp.value);
                return isNaN(v) ? fallback : v;
            }
            var t = parseFloat(cell.textContent);
            return isNaN(t) ? fallback : t;
        }
        const makingAmountCol = row.querySelector('[data-column="making-amount"]');
        const makingAmount = makingAmountCol ? readNumericCell('making-amount', making) : making;
        let stoneAmount = readNumericCell('stone-amount', stoneCharges);
        const settingChargeInpPl = row.querySelector('[data-column="setting-charge"] input');
        if (settingChargeInpPl && isDiamondOrGemStoneLineCategory(categoryId)) {
            const diamondQtyPl = parseFloat(row.querySelector('[data-column="quantity"] input')?.value) || 1;
            stoneAmount = diamondQtyPl * (parseFloat(settingChargeInpPl.value) || 0);
        }
        const otherAmount = readNumericCell('other-amount', otherCharges);
        const diamondAmount = readNumericCell('diamond-amount', diamondValue);
        const discount = readNumericCell('discount', 0);
        let amount = metalValue + makingAmount + (calculationType === 'Stone Charge' ? 0 : stoneAmount) + otherAmount + diamondAmount - discount;
        if (amount < 0) amount = 0;
        const netAmt = amount;
        const taxTypeSelectPl = row.querySelector('[data-column="tax-type"] select');
        const taxTypePl = taxTypeSelectPl ? taxTypeSelectPl.value : 'tax_of_netamount';
        const taxPercentInputPl = row.querySelector('[data-column="tax-percent"] input');
        if (typeof window.setSaleInvoiceGstTaxPercentDisplay === 'function' && taxPercentInputPl) {
            window.setSaleInvoiceGstTaxPercentDisplay(row, taxPercentInputPl);
        }
        var vatPercentPl = 0;
        if (typeof window.applyGSTForRow === 'function' && taxPercentInputPl) {
            vatPercentPl = parseFloat(taxPercentInputPl.value) || 0;
        } else if (typeof window.getSaleInvoiceRowEffectiveTaxPercent === 'function') {
            var effPl = window.getSaleInvoiceRowEffectiveTaxPercent(row, taxPercentInputPl);
            if (effPl !== null && effPl !== undefined && !isNaN(effPl) && effPl > 0) {
                vatPercentPl = effPl;
            }
        }
        if (vatPercentPl <= 0 && taxPercentInputPl) {
            var tv1 = String(taxPercentInputPl.value || '').trim();
            if (tv1.indexOf('+') !== -1) {
                var ps1 = tv1.split('+');
                var a1 = parseFloat(ps1[0].trim()) || 0;
                var b1 = parseFloat(ps1[1] ? ps1[1].trim() : '') || 0;
                if (a1 > 0 && b1 > 0) vatPercentPl = a1 + b1;
                else if (a1 > 0) vatPercentPl = a1 * 2;
            } else {
                vatPercentPl = parseFloat(taxPercentInputPl.value) || 0;
            }
        }
        if (vatPercentPl <= 0 && (taxTypePl === 'tax_of_netamount' || taxTypePl === 'tax_on_making')) {
            vatPercentPl = auragoldSaleInvoiceVatPercentFallback(row);
        }
        if (taxPercentInputPl && (taxTypePl === 'tax_of_netamount' || taxTypePl === 'tax_on_making')) {
            taxPercentInputPl.value = vatPercentPl.toFixed(2);
            if (taxPercentInputPl.dataset) taxPercentInputPl.dataset.baseGst = String(vatPercentPl);
        }
        let tax = 0;
        if (taxTypePl === 'tax_of_netamount') {
            tax = netAmt * (vatPercentPl / 100);
        } else if (taxTypePl === 'tax_on_making') {
            tax = makingAmount * (vatPercentPl / 100);
        }
        const taxFieldPl = row.querySelector('[data-field="tax"]');
        if (taxFieldPl) taxFieldPl.value = tax.toFixed(2);
        const netAmtWithTax = netAmt + tax;
        const purchaseAmount = netAmt;
        var isPurchasePiPl = auragoldIsPurchaseInvoiceContext();
        const saleAmount = isPurchasePiPl ? readNumericCell('sale-amount', 0) : netAmt;
        const saleAmountWith = isPurchasePiPl ? readNumericCell('sale-amount-with', 0) : netAmtWithTax;
        const netWtCell = row.querySelector('[data-column="net-wt"]');
        if (netWtCell) {
            // For modes where metal value uses fine/final weight, show that in Net Wt. so the column matches Rate × weight (avoids gross−less=10 while amount uses 5).
            var netDisplayVal = netWt;
            if (calculationType === 'Weight X Rate' || calculationType === 'Rate X Purity Wt' || calculationType === 'Rate X Final Wt') {
                netDisplayVal = effectiveFinalWt;
            } else if (userFinalWt > 0 && userFinalWt <= netWt + 1e-6) {
                netDisplayVal = weightForPurity;
            } else if (metalWt > 0) {
                netDisplayVal = weightForPurity;
            }
            netWtCell.textContent = auragoldFormatWeight(netDisplayVal);
        }
        const pureWtCell = row.querySelector('[data-column="pure-wt"]');
        if (pureWtCell) pureWtCell.textContent = auragoldFormatWeight(pureWt);
        const purityWtCell = row.querySelector('[data-column="purity-wt"]');
        if (purityWtCell) {
            const purityWtInput = purityWtCell.querySelector('input');
            if (purityWtInput) purityWtInput.value = auragoldFormatWeight(pureWt);
            else purityWtCell.textContent = auragoldFormatWeight(pureWt);
        }
        const rateCell = row.querySelector('[data-column="rate"]');
        if (rateCell) rateCell.textContent = rate.toFixed(2);
        const metalValueInputEl = row.querySelector('[data-column="metal-value"] input');
        if (metalValueInputEl) metalValueInputEl.value = metalValue.toFixed(2);
        else {
            const metalValueCell = row.querySelector('[data-column="metal-value"]');
            if (metalValueCell) metalValueCell.textContent = metalValue.toFixed(2);
        }
        function writeCellAmount(column, val, decimals) {
            const cell = row.querySelector('[data-column="' + column + '"]');
            if (!cell) return;
            const s = (decimals === 3) ? auragoldFormatWeight(val) : val.toFixed(2);
            const inp = cell.querySelector('input');
            if (inp) inp.value = s;
            else {
                const sp = cell.querySelector('span');
                if (sp) sp.textContent = s;
                else cell.textContent = s;
            }
        }
        writeCellAmount('amount', amount, 2);
        const netAmtInput = row.querySelector('[data-column="net-amt"] input');
        const netAmtCell = row.querySelector('[data-column="net-amt"]');
        if (netAmtInput) netAmtInput.value = netAmt.toFixed(2);
        else if (netAmtCell) writeCellAmount('net-amt', netAmt, 2);
        const netAmtTaxInput = row.querySelector('[data-column="net-amt-tax"] input');
        const netAmtTaxCell = row.querySelector('[data-column="net-amt-tax"]');
        if (netAmtTaxInput) netAmtTaxInput.value = netAmtWithTax.toFixed(2);
        else if (netAmtTaxCell) writeCellAmount('net-amt-tax', netAmtWithTax, 2);
        writeCellAmount('making-amount', makingAmount, 2);
        writeCellAmount('stone-amount', stoneAmount, 2);
        writeCellAmount('other-amount', otherAmount, 2);
        writeCellAmount('diamond-amount', diamondAmount, 2);
        writeCellAmount('discount', discount, 2);
        const purchaseAmountInput = row.querySelector('[data-column="purchase-amount"] input');
        const saleAmountInput = row.querySelector('[data-column="sale-amount"] input');
        const saleAmountWithInput = row.querySelector('[data-column="sale-amount-with"] input');
        const purchaseAmountCell = row.querySelector('[data-column="purchase-amount"]');
        const saleAmountCell = row.querySelector('[data-column="sale-amount"]');
        const saleAmountWithCell = row.querySelector('[data-column="sale-amount-with"]');
        if (purchaseAmountInput) purchaseAmountInput.value = purchaseAmount.toFixed(2);
        else if (purchaseAmountCell) purchaseAmountCell.textContent = purchaseAmount.toFixed(2);
        if (!isPurchasePiPl) {
            if (saleAmountInput) saleAmountInput.value = saleAmount.toFixed(2);
            else if (saleAmountCell) saleAmountCell.textContent = saleAmount.toFixed(2);
            if (saleAmountWithInput) saleAmountWithInput.value = saleAmountWith.toFixed(2);
            else if (saleAmountWithCell) saleAmountWithCell.textContent = saleAmountWith.toFixed(2);
        }
        if (typeof window.afterRowAmountsCalculated === 'function') window.afterRowAmountsCalculated(row);
    }
    window.calculateRowAmounts = calculateRowAmounts;

    // ========== MODAL ROW DATA EXTRACTION ==========
    function getModalRowDataFromRow(row, skipBarcodeFetch) {
        const productId = row.getAttribute('data-product-id');
        const characteristicId = row.getAttribute('data-characteristic-id');
        const metalId = row.getAttribute('data-metal-id') || '';
        const getValue = function(column, isNumber) {
            const cell = row.querySelector('td[data-column="' + column + '"]') || row.querySelector('[data-column="' + column + '"]');
            if (!cell) return isNumber ? 0 : '';
            const input = cell.querySelector('input');
            const select = cell.querySelector('select');
            if (input) return isNumber ? (parseFloat(input.value) || 0) : (input.value || '');
            if (select) return isNumber ? (parseFloat(select.value) || 0) : (select.value || '');
            return isNumber ? (parseFloat(cell.textContent.trim()) || 0) : (cell.textContent.trim() || '');
        };
        var barcodeInp = row.querySelector('[data-column="barcode"] input');
        var barcodeCell = row.querySelector('[data-column="barcode"]');
        let barcode = (barcodeInp && (barcodeInp.value || '').trim()) || (barcodeCell && (barcodeCell.textContent || '').trim()) || getValue('barcode', false);
        if (typeof barcode === 'string') barcode = barcode.trim(); else barcode = '';
        if (!skipBarcodeFetch && (!barcode || !barcode.length) && productId && characteristicId) {
            try {
                const xhr = new XMLHttpRequest();
                var metalQ = (String(metalId).trim() !== '') ? ('&metal_id=' + encodeURIComponent(String(metalId).trim())) : '';
                xhr.open('GET', 'ajax/get-product-details.php?product_id=' + encodeURIComponent(productId) + '&characteristic_id=' + encodeURIComponent(characteristicId) + metalQ, false);
                xhr.send();
                if (xhr.status === 200) {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success && data.product) {
                        if (data.product.barcode) barcode = data.product.barcode;
                        if (data.product.characteristic_id && String(data.product.characteristic_id) !== String(characteristicId)) {
                            var newCid = String(data.product.characteristic_id);
                            row.setAttribute('data-characteristic-id', newCid);
                            var cbSync = row.querySelector('.product-checkbox');
                            if (cbSync) cbSync.setAttribute('data-characteristic-id', newCid);
                        }
                    }
                }
            } catch (e) {}
        }
        var srcAgainst = row.getAttribute('data-source-against-item-id');
        var srcSoItem = row.getAttribute('data-source-sale-order-item-id');
        // Select value + visible label (Carat dropdown: id vs "24K")
        var getSelectMeta = function(column) {
            var cell = row.querySelector('td[data-column="' + column + '"]') || row.querySelector('[data-column="' + column + '"]');
            if (!cell) return { value: '', label: '' };
            var select = cell.querySelector('select');
            if (select) {
                var val = select.value || '';
                var label = '';
                if (select.selectedIndex >= 0 && select.options[select.selectedIndex]) {
                    label = (select.options[select.selectedIndex].text || '').trim();
                    if (label && /^(select|—|-)$/i.test(label)) label = '';
                }
                return { value: val, label: label || val };
            }
            var input = cell.querySelector('input');
            if (input) {
                var iv = (input.value || '').trim();
                return { value: iv, label: iv };
            }
            var txt = (cell.textContent || '').trim();
            return { value: txt, label: txt };
        };
        var caratMeta = getSelectMeta('carat');
        var locationMeta = getSelectMeta('location');
        var makingType = getValue('making-type', false) || 'Fix';
        var makingRate = getValue('making-rate', true);
        var makingAmt = getValue('making-amount', true);
        if (!(makingAmt > 0) && String(makingType) === 'Fix' && makingRate > 0) {
            makingAmt = makingRate;
        }
        return {
            product_id: productId,
            characteristic_id: row.getAttribute('data-characteristic-id') || characteristicId || '',
            metal_id: metalId,
            source_against_item_id: (srcAgainst != null && String(srcAgainst).trim() !== '') ? String(srcAgainst).trim() : '',
            source_sale_order_item_id: (srcSoItem != null && String(srcSoItem).trim() !== '') ? String(srcSoItem).trim() : '',
            product_name: getValue('product', false),
            barcode: barcode || '',
            item_code: getValue('item-code', false),
            product_category_id: getValue('product-category', false),
            category: getValue('category', false),
            location: locationMeta.value,
            location_id: locationMeta.value,
            location_name: locationMeta.label,
            carat: caratMeta.value,
            carat_id: caratMeta.value,
            carat_name: caratMeta.label,
            quantity: getValue('quantity', true),
            gross_wt: (function () {
                var m = getValue('metal-weight', true);
                var g = getValue('gross-wt', true);
                return (parseFloat(m) > 0) ? m : g;
            })(),
            less_wt: getValue('less-wt', true),
            purity: getValue('purity', true),
            final_wt: getValue('final-wt', true),
            net_wt: getValue('net-wt', true),
            pure_wt: getValue('purity-wt', true),
            rate: getValue('rate', true),
            metal_rate: getValue('metal-rate', true),
            metal_value: getValue('metal-value', true),
            purchase_rate: getValue('purchase-rate', true),
            metal_qty: (parseFloat(getValue('metal-qty', true)) || 1),
            metal_weight: getValue('metal-weight', true),
            amount: getValue('amount', true),
            discount: getValue('discount', true),
            discount_type: getValue('discount-type', false),
            discount_per: getValue('discount-per', true),
            discount_amount: getValue('discount-amount', true),
            making_type: makingType,
            making_rate: makingRate,
            making_amount: makingAmt,
            making_discount_amt: getValue('making-discount-amt', true),
            making_actual_value: getValue('making-actual-value', true),
            making_cost: getValue('making-cost', true),
            wastage_per: getValue('wastage-per', true),
            wastage_wt: getValue('wastage-wt', true),
            stone_amount: getValue('stone-amount', true),
            stone_weight: getValue('stone-weight', true),
            // Diamond tab: stone-weight column = Diamond Carat; less-wt = D.Weight
            diamond_carat: getValue('stone-weight', true),
            diamond_wt: getValue('less-wt', true),
            stone_charge_type: getValue('stone-charge-type', false),
            stone_rate: getValue('stone-rate', true),
            stone_cost: getValue('stone-cost', true),
            other_amount: getValue('other-amount', true),
            other_charge_type: getValue('other-charge-type', false),
            other_weight: getValue('other-weight', true),
            other_rate: getValue('other-rate', true),
            other_info: getValue('other-info', false),
            diamond_amount: getValue('diamond-amount', true),
            tax: getValue('tax', true),
            tax_type: getValue('tax-type', false),
            tax_percent: getValue('tax-percent', true),
            net_amt: getValue('net-amt', true),
            net_amt_tax: getValue('net-amt-tax', true),
            purchase_amount: getValue('purchase-amount', true),
            sale_percent: getValue('sale-percent', true),
            sale_amount: getValue('sale-amount', true),
            sale_amount_with: getValue('sale-amount-with', true),
            reverse: getValue('reverse', true),
            design_no: getValue('design-no', false),
            huid: getValue('huid', false),
            calculation_type: getValue('calculation', false) || 'Rate X Gross Wt',
            pkt_wt: getValue('pkt-wt', true),
            pkt_less_wt: getValue('pkt-less-wt', true),
            hallmark_amount: getValue('hallmark-amount', true),
            hallmark_rate: getValue('hallmark-rate', true),
            gold_loss1: getValue('gold-loss1', true),
            gold_loss2: getValue('gold-loss2', true),
            metal_loss_value: getValue('metal-loss-value', true),
            metal_cost: getValue('metal-cost', true),
            setting_charge: getValue('setting-charge', true),
            fc_amount: getValue('fc-amount', true),
            diamond_line_metal_value: getValue('diamond-line-metal-value', true),
            rapnet_valuation: getValue('rapnet-valuation', true),
            mark_up_amount: getValue('mark-up-amount', true),
            mark_up_per: getValue('mark-up-per', true),
            requested_purity: getValue('requested-purity', true),
            requested: getValue('requested', true),
            alloy_wt: getValue('alloy-wt', true),
            platinum_weight: getValue('platinum-weight', true),
            platinum_karat: getValue('platinum-karat', false),
            platinum_purity: getValue('platinum-purity', true),
            platinum_purity_wt: getValue('platinum-purity-wt', true),
            platinum_rate: getValue('platinum-rate', true),
            platinum_wastage_per: getValue('platinum-wastage-per', true),
            platinum_wastage_wt: getValue('platinum-wastage-wt', true),
            platinum_amount: getValue('platinum-amount', true),
            certificate_amount: getValue('certificate-amount', true),
            certificate_no: getValue('certificate-no', false),
            certificate_link: getValue('certificate-link', false),
            video_link: getValue('video-link', false),
            cut_id: getValue('cut', false),
            color_id: getValue('color', false),
            seive_size_id: getValue('seive-size', false),
            size_id: getValue('size', false),
            shape_id: getValue('shape', false),
            clarity_id: getValue('clarity', false),
            unit_price: getValue('unit-price', true),
            min_price: getValue('min-price', true),
            minimum: getValue('minimum', true),
            barcode_prefix: row.getAttribute('data-barcode-prefix') || '',
            barcode_digits: row.getAttribute('data-barcode-digits') || '',
            gst_local_percent: row.getAttribute('data-gst-local-pct') || '',
            gst_interstate_percent: row.getAttribute('data-gst-interstate-pct') || '',
            gst_invoice_slab_percent: row.getAttribute('data-gst-invoice-slab-pct') || '',
            gst_line_taxes: row.getAttribute('data-gst-line-taxes') || '',
            product_taxes: row.getAttribute('data-product-taxes') != null && row.getAttribute('data-product-taxes') !== '' ? row.getAttribute('data-product-taxes') : '[]',
            group_image: row.getAttribute('data-group-image') || '',
            stock_journal_id: row.getAttribute('data-stock-journal-id') || ''
        };
    }
    window.getModalRowDataFromRow = getModalRowDataFromRow;

    function savedItemToModalRowData(item) {
        var lessWt = parseFloat(item.less_weight || item.less_wt) || 0;
        var grossWt = parseFloat(item.gross_weight || item.gross_wt) || 0;
        var netWt = parseFloat(item.net_weight || item.net_wt) || 0;
        var pureWt = parseFloat(item.pure_weight || item.purity_weight || item.pure_wt) || 0;
        var pktWt = parseFloat(item.pkt_wt || item.pkt_weight || 0) || 0;
        var finalWt = parseFloat(item.final_weight || item.final_wt) || 0;
        var metalWtRaw = parseFloat(item.metal_weight);
        var metalWt = (!isNaN(metalWtRaw) && metalWtRaw > 0) ? metalWtRaw : 0;
        // Scrap stock-in (partial): PHP adds remaining_gross_wt; saved line may still hold full invoice gross — cap to balance so UI matches banner.
        var cap = null;
        if (item.remaining_gross_wt != null && item.remaining_gross_wt !== '') {
            var c = parseFloat(item.remaining_gross_wt);
            if (!isNaN(c) && c >= 0) cap = c;
        }
        if (cap !== null) {
            if (grossWt > cap + 1e-9) grossWt = cap;
            if (pktWt > cap + 1e-9) pktWt = cap;
            if (metalWt > cap + 1e-9) metalWt = cap;
            netWt = Math.max(0, grossWt - lessWt);
            if (finalWt > cap + 1e-9) finalWt = cap;
            if (pureWt > cap + 1e-9) pureWt = cap;
        }
        var diamondAmount = parseFloat(item.diamond_amount || item.diamond_value) || 0;
        var category = (item.diamond_category || item.category || '').toString().trim();
        var amountVal = parseFloat(item.amount) || 0;
        if (category === 'Jewellery' && diamondAmount > 0) amountVal = diamondAmount;
        if (!amountVal) {
            amountVal = parseFloat(item.net_amount || item.net_amt) || 0;
        }
        if (!amountVal) {
            amountVal = parseFloat(item.net_amt_weight || item.net_amt_with_tax || item.net_amt_tax) || 0;
        }
        var pid = item.product_id;
        if (pid == null || pid === '') {
            pid = '';
        }
        var metalWeightOut = metalWt > 0 ? metalWt : (grossWt || netWt);
        return {
            id: (item.id != null && item.id !== '') ? item.id : '',
            product_id: pid,
            characteristic_id: item.product_characteristic_id || item.characteristic_id || '',
            metal_id: item.metal_id || '',
            product_name: item.product_name || item.name || '',
            barcode: item.barcode || item.barcode_no || '',
            short_code: item.short_code || '',
            rfid: item.rfid || item.rfid_code || '',
            voucher_type: item.voucher_type || item.voucher_type_id || '',
            huid: item.huid || item.huid_no || '',
            category: item.diamond_category || item.category || item.category_id || '',
            diamond_category: item.diamond_category || item.category || '',
            location: item.location || item.location_id || '',
            carat_id: (item.carat_id != null && item.carat_id !== '') ? item.carat_id : ((item.carat != null && item.carat !== '') ? item.carat : ''),
            carat: (item.carat != null && item.carat !== '') ? item.carat : '',
            carat_name: item.carat_name || '',
            stone_weight: parseFloat(item.stone_weight) || 0,
            pkt_wt: pktWt,
            pkt_less_wt: parseFloat(item.pkt_less_wt || item.pkt_less_weight || 0) || 0,
            quantity: parseFloat(item.quantity) || 0,
            gross_wt: grossWt,
            less_wt: lessWt,
            purity: parseFloat(item.purity) || 0,
            final_wt: finalWt,
            net_wt: netWt,
            pure_wt: pureWt,
            metal_qty: parseFloat(item.metal_qty) != null && item.metal_qty !== '' ? (parseFloat(item.metal_qty) || 1) : 1,
            metal_weight: metalWeightOut,
            metal_rate: (function () {
                var mr = parseFloat(item.metal_rate);
                var r = parseFloat(item.rate);
                if (!isNaN(mr) && mr > 0) return mr;
                if (!isNaN(r) && r > 0) return r;
                return parseFloat(item.metal_rate || item.rate) || 0;
            })(),
            rate: (function () {
                var r = parseFloat(item.rate);
                var mr = parseFloat(item.metal_rate);
                if (!isNaN(r) && r > 0) return r;
                if (!isNaN(mr) && mr > 0) return mr;
                return parseFloat(item.rate || item.metal_rate) || 0;
            })(),
            metal_value: (function () {
                var mv = parseFloat(item.metal_value) || 0;
                if (mv > 0) return mv;
                var mr = parseFloat(item.metal_rate);
                var r = parseFloat(item.rate);
                var eff = (!isNaN(mr) && mr > 0) ? mr : ((!isNaN(r) && r > 0) ? r : 0);
                if (eff > 0 && pureWt > 0) return eff * pureWt;
                return 0;
            })(),
            amount: amountVal,
            discount: parseFloat(item.discount || item.discounted_amt) || 0,
            making_type: auragoldNormalizeMakingType(item.making_type),
            making_rate: (function () {
                var mr = parseFloat(item.making_rate);
                if (!isNaN(mr) && item.making_rate !== '' && item.making_rate != null) return mr;
                return parseFloat(item.making_amount || item.making) || 0;
            })(),
            making_amount: parseFloat(item.making_amount || item.making) || 0,
            making_discount_amt: parseFloat(item.making_discount_amt || item.making_discount_amount) || 0,
            making_actual_value: (function () {
                var v = parseFloat(item.making_actual_value);
                if (!isNaN(v) && item.making_actual_value !== '' && item.making_actual_value != null) return v;
                return parseFloat(item.making_amount || item.making) || 0;
            })(),
            making_cost: (function () {
                var v = parseFloat(item.making_cost);
                if (!isNaN(v) && item.making_cost !== '' && item.making_cost != null) return v;
                return parseFloat(item.making_amount || item.making) || 0;
            })(),
            stone_amount: parseFloat(item.stone_amount || item.stone_charges) || 0,
            other_amount: parseFloat(item.other_amount || item.other_charges) || 0,
            diamond_amount: parseFloat(item.diamond_amount || item.diamond_value) || 0,
            tax: parseFloat(item.tax_amount || item.tax) || 0,
            net_amt: (function () {
                var n = parseFloat(item.net_amount || item.net_amt) || 0;
                return n || amountVal;
            })(),
            // tbl_* lines often store inclusive total as net_amt_wt; UI column is net-amt-tax
            net_amt_tax: (function () {
                var v = parseFloat(item.net_amt_with_tax || item.net_amt_tax || item.net_amt_weight || item.net_amt_wt) || 0;
                if (v > 0) return v;
                var net = parseFloat(item.net_amount || item.net_amt) || 0;
                var tax = parseFloat(item.tax_amount || item.tax) || 0;
                var amt = parseFloat(item.amount) || 0;
                if (net + tax > 0) return net + tax;
                if (amt + tax > 0) return amt + tax;
                return amt || net || 0;
            })(),
            purchase_amount: (function () {
                var p = parseFloat(item.purchase_amount) || 0;
                if (p > 0) return p;
                return amountVal || parseFloat(item.net_amount || item.net_amt) || 0;
            })(),
            sale_percent: parseFloat(item.sale_percent) || 0,
            sale_amount: (function () {
                var s = parseFloat(item.sale_amount) || 0;
                if (s > 0) return s;
                return parseFloat(item.net_amount || item.net_amt) || amountVal || 0;
            })(),
            sale_amount_with: (function () {
                var s = parseFloat(item.sale_amount_with) || 0;
                if (s > 0) return s;
                var v = parseFloat(item.net_amt_with_tax || item.net_amt_tax) || 0;
                return v || amountVal || 0;
            })(),
            reverse: parseFloat(item.reverse) || 0,
            design_no: item.design_no || '',
            calculation_type: (item.calculation_type || item.calculation || 'Rate X Gross Wt').toString().trim(),
            source_against_item_id: (item.source_against_item_id != null && item.source_against_item_id !== '') ? item.source_against_item_id : '',
            source_sale_order_item_id: (item.source_sale_order_item_id != null && item.source_sale_order_item_id !== '') ? item.source_sale_order_item_id : '',
            merge_group_index: (item.merge_group_index != null && item.merge_group_index !== '') ? item.merge_group_index : null,
            // Sale order line photos (PHP sets group_image from tbl_sale_order_items.images JSON)
            group_image: item.group_image != null && item.group_image !== '' ? item.group_image : '',
            barcode_prefix: item.barcode_prefix || '',
            barcode_digits: item.barcode_digits != null && item.barcode_digits !== '' ? item.barcode_digits : '',
            gst_local_percent: item.gst_local_percent != null && item.gst_local_percent !== '' ? item.gst_local_percent : '',
            gst_interstate_percent: item.gst_interstate_percent != null && item.gst_interstate_percent !== '' ? item.gst_interstate_percent : '',
            gst_invoice_slab_percent: (function () {
                if (item.gst_invoice_slab_percent != null && item.gst_invoice_slab_percent !== '') return item.gst_invoice_slab_percent;
                var a = parseFloat(item.gst_local_percent) || 0;
                var b = parseFloat(item.gst_interstate_percent) || 0;
                return (Math.max(a, b) > 0) ? String(Math.max(a, b)) : '';
            })(),
            gst_line_taxes: (item.gst_line_taxes != null && item.gst_line_taxes !== '') ? String(item.gst_line_taxes) : '',
            product_taxes: (item.product_taxes != null && item.product_taxes !== '') ? String(item.product_taxes) : '[]',
            extra_fields: (function () {
                if (item.extra_fields && typeof item.extra_fields === 'object') {
                    return item.extra_fields;
                }
                var raw = item.extra_fields_json;
                if (typeof raw === 'string' && raw.trim() !== '') {
                    try {
                        var parsed = JSON.parse(raw);
                        if (parsed && typeof parsed === 'object') return parsed;
                    } catch (e) {}
                }
                return {};
            })()
        };
    }
    window.savedItemToModalRowData = savedItemToModalRowData;

    function getItemAndProductFromModalRowData(d) {
        var item = {
            product_id: d.product_id,
            product_characteristic_id: d.characteristic_id,
            metal_id: d.metal_id || '',
            product_name: d.product_name,
            barcode_no: d.barcode,
            design_no: d.design_no || '',
            huid_no: (d.huid != null && String(d.huid).trim() !== '') ? String(d.huid).trim() : '',
            category: (d.category != null && d.category !== '') ? String(d.category).trim() : '',
            location_id: d.location || d.location_id || '',
            carat_id: (d.carat_id != null && d.carat_id !== '') ? d.carat_id : (d.carat != null ? d.carat : ''),
            carat: (d.carat_name != null && d.carat_name !== '') ? d.carat_name : (d.carat != null ? d.carat : ''),
            carat_name: (d.carat_name != null && d.carat_name !== '') ? d.carat_name : (d.carat != null ? d.carat : ''),
            quantity: parseFloat(d.quantity) || 1,
            gross_weight: parseFloat(d.gross_wt) || 0,
            less_weight: parseFloat(d.less_wt) || 0,
            purity: parseFloat(d.purity) || 0,
            final_weight: parseFloat(d.final_wt) || 0,
            net_weight: parseFloat(d.net_wt) || 0,
            purity_weight: parseFloat(d.pure_wt) || 0,
            rate: parseFloat(d.rate) || 0,
            metal_rate: parseFloat(d.metal_rate) || 0,
            metal_value: parseFloat(d.metal_value) || 0,
            metal_qty: (d.metal_qty != null && d.metal_qty !== '') ? (parseFloat(d.metal_qty) || 1) : 1,
            metal_weight: parseFloat(d.metal_weight) || 0,
            amount: parseFloat(d.amount) || 0,
            making_amount: parseFloat(d.making_amount) || parseFloat(d.making) || 0,
            making_type: auragoldNormalizeMakingType(d.making_type),
            making_rate: parseFloat(d.making_rate) != null && d.making_rate !== '' ? (parseFloat(d.making_rate) || 0) : (parseFloat(d.making_amount) || parseFloat(d.making) || 0),
            making_discount_amount: parseFloat(d.making_discount_amt || d.making_discount_amount) || 0,
            making_actual_value: parseFloat(d.making_actual_value) || parseFloat(d.making_amount || d.making) || 0,
            making_cost: parseFloat(d.making_cost) || parseFloat(d.making_amount || d.making) || 0,
            stone_amount: parseFloat(d.stone_amount) || 0,
            stone_weight: parseFloat(d.stone_weight) || 0,
            other_amount: parseFloat(d.other_amount) || 0,
            diamond_amount: parseFloat(d.diamond_amount) || 0,
            net_amount: parseFloat(d.net_amt) || 0,
            net_amount_tax: parseFloat(d.net_amt_tax) || 0,
            tax_amount: parseFloat(d.tax) || 0,
            tax_type: (d.tax_type != null && d.tax_type !== '') ? String(d.tax_type).trim() : 'tax_of_netamount',
            calculation: (d.calculation_type != null && d.calculation_type !== '') ? String(d.calculation_type).trim() : 'Rate X Gross Wt',
            pkt_weight: parseFloat(d.pkt_wt) || 0,
            pkt_less_weight: parseFloat(d.pkt_less_wt) || 0,
            purchase_amount: parseFloat(d.purchase_amount) || 0,
            sale_percent: parseFloat(d.sale_percent) || 0,
            sale_amount: parseFloat(d.sale_amount) || 0,
            sale_amount_with: parseFloat(d.sale_amount_with) || 0,
            reverse: parseFloat(d.reverse) || 0,
            hallmark_amount: parseFloat(d.hallmark_amount) || 0,
            hallmark_rate: parseFloat(d.hallmark_rate) || 0
        };
        var product = {
            id: d.product_id,
            name: d.product_name || '',
            characteristic_id: d.characteristic_id || '',
            opening_weight: d.gross_wt,
            opening_purity: d.purity,
            final_weight: d.final_wt,
            rate: d.rate,
            value: d.amount,
            article: d.design_no,
            vat_value: d.vat_value != null ? d.vat_value : (d.tax_percent != null ? d.tax_percent : ''),
            total_tax_percent: d.total_tax_percent != null ? d.total_tax_percent : (d.vat_value != null ? d.vat_value : (d.tax_percent != null ? d.tax_percent : '')),
            gst_local_percent: d.gst_local_percent != null && d.gst_local_percent !== '' ? d.gst_local_percent : '',
            gst_interstate_percent: d.gst_interstate_percent != null && d.gst_interstate_percent !== '' ? d.gst_interstate_percent : '',
            gst_invoice_slab_percent: d.gst_invoice_slab_percent != null && d.gst_invoice_slab_percent !== '' ? d.gst_invoice_slab_percent : ''
        };
        return { item: item, product: product };
    }
    window.getItemAndProductFromModalRowData = getItemAndProductFromModalRowData;

    /**
     * Column sync contract (product modal + stock journal drag):
     * - Each logical column uses the same `data-column` key on <th> (row 2) and <td> in tbody rows.
     * - stock-journal-column-drag.js reorders only td[data-column]; cells without data-column are left
     *   in place (e.g. colspan placeholders). Leading non-data td stay before re-inserted data cells.
     * - Calculations and layout code resolve fields via row.querySelector('[data-column="…"]') — keys
     *   must match thead and PRODUCT_MODAL_COLUMN_GROUPS / page override (e.g. stock-journal-create).
     */
    // ========== PRODUCT MODAL COLUMN GROUPS (shared: change here, reflects everywhere) ==========
    var PRODUCT_MODAL_COLUMN_GROUPS = {
        'basic-information': ['id', 'rfid', 'voucher-type', 'photo', 'barcode', 'design-no', 'huid', 'item-code', 'category', 'product-category', 'calculation', 'product', 'location'],
        'diamond-group': ['pkt-wt', 'pkt-less-wt', 'gross-wt', 'stone-weight', 'less-wt', 'net-wt', 'quantity', 'rate', 'fc-amount', 'diamond-line-metal-value', 'rapnet-valuation', 'setting-charge', 'stone-amount', 'mark-up-amount', 'mark-up-per', 'amount'],
        'metal-group': ['metal-qty', 'metal-weight', 'carat', 'purity', 'purity-wt', 'gold-loss1', 'gold-loss2', 'metal-loss-value', 'wastage-per', 'wastage-wt', 'metal-rate', 'metal-value', 'purchase-rate', 'metal-cost'],
        'request-final-group': ['requested-purity', 'requested', 'final-wt', 'alloy-wt'],
        'platinum-group': ['platinum-weight', 'platinum-karat', 'platinum-purity', 'platinum-purity-wt', 'platinum-rate', 'platinum-wastage-per', 'platinum-wastage-wt', 'platinum-amount'],
        'discount-group': ['discount-type', 'discount-per', 'discount-amount', 'discount'],
        'making-group': ['making-type', 'making-rate', 'making-discount-amt', 'making-amount', 'making-actual-value', 'making-cost'],
        'minimum-group': ['min-price', 'minimum'],
        'stone-group': ['stone-charge-type', 'stone-rate', 'stone-cost', 'diamond-amount'],
        'amounts': ['purchase-amount', 'sale-percent', 'sale-amount', 'sale-amount-with', 'net-amt', 'tax-type', 'tax-percent', 'tax'],
        'other-charge-group': ['other-charge-type', 'other-weight', 'other-rate', 'other-info', 'other-amount'],
        'cert-spec-group': ['certificate-amount', 'certificate-no', 'certificate-link', 'video-link', 'cut', 'color', 'seive-size', 'size', 'shape', 'clarity', 'unit-price'],
        /* Separate groups: Hallmark must stay before Net Amt+Tax / Reverse (enforced when reordering columns in stock-journal-column-drag.js). */
        'hallmark': ['hallmark-amount', 'hallmark-rate'],
        'net-reverse': ['net-amt-tax', 'reverse']
    };
    window.PRODUCT_MODAL_COLUMN_GROUPS = PRODUCT_MODAL_COLUMN_GROUPS;

    /** Keys under the Diamond group header (common-modal): hide on non–Diamond & Stones tabs. */
    function getDiamondGroupColumnKeys() {
        var g = window.PRODUCT_MODAL_COLUMN_GROUPS && window.PRODUCT_MODAL_COLUMN_GROUPS['diamond-group'];
        return (g && g.length) ? g.slice() : ['pkt-wt', 'pkt-less-wt', 'gross-wt', 'stone-weight', 'less-wt', 'net-wt', 'quantity', 'rate', 'fc-amount', 'diamond-line-metal-value', 'rapnet-valuation', 'setting-charge', 'stone-amount', 'mark-up-amount', 'mark-up-per', 'amount'];
    }
    window.getDiamondGroupColumnKeys = getDiamondGroupColumnKeys;

    /**
     * Gold/Silver/Platinum: merge server-saved column prefs on top of METAL_GROUP_VISIBLE_COLUMNS.
     * Keys missing from the save default to on (1); keys present in the save (including category:0) are respected so Show/Hide persists.
     */
    function mergeProductModalMetalTabPrefs(tk, tabKey) {
        var metalVisibleSet = {};
        var mcols = window.METAL_GROUP_VISIBLE_COLUMNS;
        if (mcols && mcols.length) {
            mcols.forEach(function (col) { metalVisibleSet[col] = 1; });
        } else {
            ['checkbox', 'id', 'category', 'calculation', 'product', 'location', 'quantity'].forEach(function (c) { metalVisibleSet[c] = 1; });
        }
        var amountsCols = (window.PRODUCT_MODAL_COLUMN_GROUPS && window.PRODUCT_MODAL_COLUMN_GROUPS.amounts) || [
            'purchase-amount', 'sale-percent', 'sale-amount', 'sale-amount-with', 'net-amt', 'tax-type', 'tax-percent', 'tax'
        ];
        amountsCols.forEach(function (col) { metalVisibleSet[col] = 1; });
        var saved = window.productModalColumnVisibilityByTab && (window.productModalColumnVisibilityByTab[tk] || window.productModalColumnVisibilityByTab[tabKey]);
        var merged = Object.assign({}, metalVisibleSet, (saved && typeof saved === 'object') ? saved : {});
        amountsCols.forEach(function (col) { merged[col] = 1; });
        return merged;
    }
    window.mergeProductModalMetalTabPrefs = mergeProductModalMetalTabPrefs;

    /** Amounts columns (incl. Sale Percentages) stay visible on every metal tab. */
    window.PRODUCT_MODAL_AMOUNTS_ALWAYS_VISIBLE = [
        'purchase-amount', 'sale-percent', 'sale-amount', 'sale-amount-with', 'net-amt', 'tax-type', 'tax-percent', 'tax'
    ];
    function auragoldProductModalApplyAlwaysVisibleAmountColumns(prefs) {
        var out = prefs && typeof prefs === 'object' ? Object.assign({}, prefs) : {};
        (window.PRODUCT_MODAL_AMOUNTS_ALWAYS_VISIBLE || []).forEach(function (col) {
            out[col] = 1;
        });
        return out;
    }
    window.auragoldProductModalApplyAlwaysVisibleAmountColumns = auragoldProductModalApplyAlwaysVisibleAmountColumns;

    /**
     * Modals that do not split metal-qty vs diamond quantity (e.g. repair-order, old-jewelry-scrap):
     * only these columns are treated as diamond/stone-only so Gold/Silver rows keep quantity/rate/amount.
     */
    function getMergedLayoutDiamondOnlyColumnKeys() {
        return ['pkt-wt', 'pkt-less-wt', 'stone-weight', 'diamond-amount'];
    }
    window.getMergedLayoutDiamondOnlyColumnKeys = getMergedLayoutDiamondOnlyColumnKeys;

    /** Sync master group checkboxes in Show/Hide Columns with child column checkboxes (common-modal grouped dropdown). */
    function syncProductModalColumnGroupMasterCheckboxes() {
        var settingsDropdown = document.getElementById('modalTableSettingsDropdown')
            || document.getElementById('modalTableSettingsDropdownModal');
        if (!settingsDropdown) return;
        var columnGroups = window.PRODUCT_MODAL_COLUMN_GROUPS || {};
        Object.keys(columnGroups).forEach(function(groupKey) {
            var cols = columnGroups[groupKey];
            if (!cols || !cols.length) return;
            var groupCb = settingsDropdown.querySelector('input[type="checkbox"][data-group="' + groupKey + '"]');
            if (!groupCb || groupCb.getAttribute('data-column')) return;
            var anyChecked = cols.some(function(c) {
                var cb = settingsDropdown.querySelector('input[data-column="' + c + '"]');
                return cb && cb.checked;
            });
            groupCb.checked = anyChecked;
            cols.forEach(function(c) {
                var cb = settingsDropdown.querySelector('input[data-column="' + c + '"]');
                if (cb) {
                    var disabled = !groupCb.checked;
                    cb.disabled = disabled;
                    var item = cb.closest('.table-settings-item');
                    if (item) item.classList.toggle('sub-column-disabled', disabled);
                }
            });
        });
    }
    window.syncProductModalColumnGroupMasterCheckboxes = syncProductModalColumnGroupMasterCheckboxes;

    var PRODUCT_MODAL_GROUP_LABELS_DEFAULT = {
        'basic-information': 'Basic Information',
        'diamond-group': 'Diamond group',
        'metal-group': 'Metal group',
        'request-final-group': 'Request & Final Wt.',
        'platinum-group': 'Platinum (group)',
        'discount-group': 'Discount (group)',
        'making-group': 'Making (group)',
        'minimum-group': 'Minimum',
        'stone-group': 'Stone group',
        'amounts': 'Amounts',
        'other-charge-group': 'Other Charge (group)',
        'cert-spec-group': 'Certificate & spec',
        'hallmark': 'Hallmark',
        'net-reverse': 'Net Amt+Tax / Reverse'
    };

    /** Move column items out of group section before rebuild (re-open Show/Hide was deleting checkboxes). */
    function auragoldFlattenProductModalSettingsGroups(settingsDropdown) {
        if (!settingsDropdown) return;
        var existingSection = settingsDropdown.querySelector('.table-settings-groups-section');
        if (!existingSection) return;
        var listHost = settingsDropdown.querySelector('.table-settings-dropdown-body') || settingsDropdown;
        existingSection.querySelectorAll('.table-settings-item').forEach(function(item) {
            item.classList.remove('table-settings-sub-column');
            if (item.getAttribute('data-column')) {
                item.removeAttribute('data-group');
            }
            listHost.appendChild(item);
        });
        existingSection.remove();
    }
    window.auragoldFlattenProductModalSettingsGroups = auragoldFlattenProductModalSettingsGroups;

    function auragoldSetProductModalSubColumnDisabledState(settingsDropdown, groupKey, disabled) {
        if (!settingsDropdown) return;
        var columnGroups = window.PRODUCT_MODAL_COLUMN_GROUPS || {};
        var cols = columnGroups[groupKey];
        if (!cols || !cols.length) return;
        cols.forEach(function(c) {
            var cb = settingsDropdown.querySelector('input[data-column="' + c + '"]');
            if (cb) {
                cb.disabled = disabled;
                var item = cb.closest('.table-settings-item');
                if (item) item.classList.toggle('sub-column-disabled', disabled);
            }
        });
    }
    window.auragoldSetProductModalSubColumnDisabledState = auragoldSetProductModalSubColumnDisabledState;

    function auragoldUpdateAllProductModalSubColumnDisabledStates(settingsDropdown) {
        if (!settingsDropdown) return;
        var columnGroups = window.PRODUCT_MODAL_COLUMN_GROUPS || {};
        Object.keys(columnGroups).forEach(function(groupKey) {
            var groupCb = settingsDropdown.querySelector('.table-settings-groups-section input[data-group="' + groupKey + '"]');
            var groupChecked = groupCb ? groupCb.checked : true;
            auragoldSetProductModalSubColumnDisabledState(settingsDropdown, groupKey, !groupChecked);
        });
    }
    window.auragoldUpdateAllProductModalSubColumnDisabledStates = auragoldUpdateAllProductModalSubColumnDisabledStates;

    /** Build grouped Show/Hide Columns panel (safe to call every time the gear menu opens). */
    function auragoldEnsureProductModalGroupCheckboxesInDropdown(settingsDropdown, groupLabels) {
        if (!settingsDropdown) return;
        groupLabels = groupLabels || PRODUCT_MODAL_GROUP_LABELS_DEFAULT;
        auragoldFlattenProductModalSettingsGroups(settingsDropdown);
        var columnGroups = window.PRODUCT_MODAL_COLUMN_GROUPS || {};
        var listHost = settingsDropdown.querySelector('.table-settings-dropdown-body') || settingsDropdown;
        var itemsByColumn = {};
        settingsDropdown.querySelectorAll('.table-settings-item input[data-column]').forEach(function(inp) {
            var item = inp.closest('.table-settings-item');
            if (item) itemsByColumn[inp.getAttribute('data-column')] = item;
        });
        var wrapper = document.createElement('div');
        wrapper.className = 'table-settings-groups-section';
        wrapper.innerHTML = '<div class="table-settings-groups-title" style="font-weight: 700; margin: 0.5rem 0 0.25rem 0; font-size: 0.8rem; color: #64748b;">Column groups</div>';
        Object.keys(columnGroups).forEach(function(groupKey) {
            var block = document.createElement('div');
            block.className = 'table-settings-group-block';
            block.setAttribute('data-group', groupKey);
            var label = groupLabels[groupKey] || groupKey;
            var id = 'modal-col-group-' + groupKey;
            var groupRow = document.createElement('div');
            groupRow.className = 'table-settings-item table-settings-group-item';
            groupRow.setAttribute('data-group', groupKey);
            groupRow.innerHTML = '<input type="checkbox" id="' + id + '" data-group="' + groupKey + '" checked><label for="' + id + '">' + String(label).replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</label>';
            block.appendChild(groupRow);
            (columnGroups[groupKey] || []).forEach(function(col) {
                var item = itemsByColumn[col];
                if (item) {
                    item.classList.add('table-settings-sub-column');
                    item.setAttribute('data-group', groupKey);
                    block.appendChild(item);
                    delete itemsByColumn[col];
                }
            });
            wrapper.appendChild(block);
        });
        var insertPoint = listHost.firstChild;
        var orphanOrder = ['checkbox', 'actions'];
        orphanOrder.forEach(function(col) {
            if (itemsByColumn[col]) {
                listHost.insertBefore(itemsByColumn[col], insertPoint);
                insertPoint = itemsByColumn[col].nextSibling;
            }
        });
        Object.keys(itemsByColumn).forEach(function(col) {
            if (orphanOrder.indexOf(col) === -1) {
                listHost.insertBefore(itemsByColumn[col], insertPoint);
                insertPoint = itemsByColumn[col].nextSibling;
            }
        });
        listHost.insertBefore(wrapper, insertPoint);
        auragoldUpdateAllProductModalSubColumnDisabledStates(settingsDropdown);
    }
    window.auragoldEnsureProductModalGroupCheckboxesInDropdown = auragoldEnsureProductModalGroupCheckboxesInDropdown;

    /** Keep in-memory tab prefs in sync immediately (debounced save alone caused revert on tab switch). */
    function auragoldSyncProductModalColumnPrefsToMemory(tabKey, settingsDropdown) {
        settingsDropdown = settingsDropdown || document.getElementById('modalTableSettingsDropdown')
            || document.getElementById('modalTableSettingsDropdownModal');
        if (!settingsDropdown) return;
        var tk = (tabKey === undefined || tabKey === null) ? '' : String(tabKey);
        if (!window.productModalColumnVisibilityByTab) window.productModalColumnVisibilityByTab = {};
        if (!window.productModalColumnVisibilityByTab[tk]) window.productModalColumnVisibilityByTab[tk] = {};
        settingsDropdown.querySelectorAll('input[type="checkbox"][data-column]').forEach(function(cb) {
            var col = cb.getAttribute('data-column');
            if (col) window.productModalColumnVisibilityByTab[tk][col] = cb.checked ? 1 : 0;
        });
    }
    window.auragoldSyncProductModalColumnPrefsToMemory = auragoldSyncProductModalColumnPrefsToMemory;

    /** Product Selection grids: modal (#productListTable) and stock journal main card (#productListTablePage). */
    function getProductSelectionTables() {
        return Array.prototype.slice.call(document.querySelectorAll('#productListTable, #productListTablePage')).filter(Boolean);
    }
    window.getProductSelectionTables = getProductSelectionTables;

    /**
     * Thead logical column order for a product grid: checkbox (row-1) + row-2 th[data-column] in DOM order + row-1 only (images, actions) at the end.
     * Used to reorder tbody cells so they stay aligned when headers change (Sale modal, Stock Journal page, etc.).
     */
    function getProductModalTheadDataColumnOrder(table) {
        if (!table) return [];
        var order = [];
        if (table.querySelector('thead th[data-column="checkbox"][rowspan]')) {
            order.push('checkbox');
        }
        var r2 = table.querySelector('thead tr:nth-child(2)');
        if (r2) {
            r2.querySelectorAll('th[data-column]').forEach(function(th) {
                var c = th.getAttribute('data-column');
                if (c) order.push(c);
            });
        }
        if (order.indexOf('images') === -1 && table.querySelector('thead th[data-column="images"][rowspan]')) {
            order.push('images');
        }
        if (order.indexOf('actions') === -1 && table.querySelector('thead th[data-column="actions"][rowspan]')) {
            order.push('actions');
        }
        return order;
    }
    window.getProductModalTheadDataColumnOrder = getProductModalTheadDataColumnOrder;

    function reorderModalRowCellsToMatchHeader(row, table) {
        if (!row || !row.querySelector) return;
        var tbl = table || (row.closest ? row.closest('table') : null);
        if (!tbl) return;
        var order = getProductModalTheadDataColumnOrder(tbl);
        if (!order.length) return;
        var map = {};
        row.querySelectorAll('td[data-column]').forEach(function(td) {
            var c = td.getAttribute('data-column');
            if (c) map[c] = td;
        });
        var seen = {};
        order.forEach(function(k) {
            if (!map[k]) {
                var td = document.createElement('td');
                td.setAttribute('data-column', k);
                if (k === 'checkbox') {
                    td.style.textAlign = 'center';
                    td.style.background = '#fff';
                    td.innerHTML = '<input type="checkbox" class="product-checkbox">';
                } else if (k === 'actions') {
                    td.style.textAlign = 'center';
                } else if (k === 'photo') {
                    td.className = 'product-row-photo';
                    td.style.textAlign = 'center';
                    td.style.verticalAlign = 'middle';
                    td.innerHTML = '<span class="product-photo-placeholder text-muted" style="font-size:0.65rem;">—</span>';
                } else if (k === 'sale-percent') {
                    td.innerHTML = '<input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;" placeholder="%" title="Sale % on Purchase Amount">';
                } else if (k === 'id') {
                    td.textContent = '';
                } else {
                    td.innerHTML = '<input type="text" class="form-control form-control-sm" value="" style="width:80px;font-size:0.7rem;">';
                }
                map[k] = td;
            }
            row.appendChild(map[k]);
            seen[k] = true;
        });
        Object.keys(map).forEach(function(k) {
            if (!seen[k] && map[k] && map[k].parentNode === row) {
                row.appendChild(map[k]);
            }
        });
    }
    window.reorderModalRowCellsToMatchHeader = reorderModalRowCellsToMatchHeader;

    /**
     * Stamp data-group on row-2 headers and product-row cells from PRODUCT_MODAL_COLUMN_GROUPS (debug + DnD helpers).
     * @param {HTMLElement} [rowOpt] - if set, only stamp td inside this tr; else all product rows + headers.
     */
    function stampProductModalDataGroupOnCells(rowOpt) {
        var tables = getProductSelectionTables();
        if (!tables.length) return;
        var groups = window.PRODUCT_MODAL_COLUMN_GROUPS || PRODUCT_MODAL_COLUMN_GROUPS;
        var colToGroup = {};
        Object.keys(groups).forEach(function(gk) {
            (groups[gk] || []).forEach(function(c) { colToGroup[c] = gk; });
        });
        tables.forEach(function(table) {
            var r2 = table.querySelector('thead tr:nth-child(2)');
            if (r2) {
                r2.querySelectorAll('th[data-column]').forEach(function(th) {
                    var c = th.getAttribute('data-column');
                    if (c && colToGroup[c]) th.setAttribute('data-group', colToGroup[c]);
                    else th.removeAttribute('data-group');
                });
            }
        });
        var rowNodes;
        if (rowOpt) {
            rowNodes = [rowOpt];
        } else {
            rowNodes = [];
            tables.forEach(function(table) {
                rowNodes = rowNodes.concat(Array.prototype.slice.call(table.querySelectorAll('tbody tr.product-row')));
            });
        }
        rowNodes.forEach(function(row) {
            if (!row || !row.querySelectorAll) return;
            row.querySelectorAll('td[data-column]').forEach(function(td) {
                var c = td.getAttribute('data-column');
                if (c && colToGroup[c]) td.setAttribute('data-group', colToGroup[c]);
                else td.removeAttribute('data-group');
            });
        });
        markProductModalGroupEndColumns();
    }
    window.stampProductModalDataGroupOnCells = stampProductModalDataGroupOnCells;

    /** All thead column keys (checkbox/actions included via rowspan). */
    function getProductModalHeaderColumnKeys() {
        var tables = getProductSelectionTables();
        if (!tables.length) return [];
        var keys = {};
        tables[0].querySelectorAll('thead [data-column]').forEach(function(el) {
            var c = el.getAttribute('data-column');
            if (c) keys[c] = true;
        });
        return Object.keys(keys);
    }
    window.getProductModalHeaderColumnKeys = getProductModalHeaderColumnKeys;

    /**
     * Single source of truth: show/hide every th/td (and nested inputs) for this column in the product modal table.
     */
    function toggleColumnVisibility(columnName, isVisible) {
        if (columnName == null || columnName === '') return;
        var esc = String(columnName).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        getProductSelectionTables().forEach(function(table) {
            table.querySelectorAll('[data-column="' + esc + '"]').forEach(function(el) {
                el.style.display = isVisible ? '' : 'none';
                el.classList.toggle('hidden', !isVisible);
                el.querySelectorAll('input, select').forEach(function(inp) {
                    inp.style.setProperty('display', isVisible ? '' : 'none', 'important');
                });
            });
        });
        if (typeof fixProductModalHeader === 'function') {
            fixProductModalHeader();
        }
    }
    window.toggleColumnVisibility = toggleColumnVisibility;

    /** Column widths: drag handle on row-2 headers (same pattern as admin/payment-voucher.php pvInstallColumnResizers). */
    var pmSaveWidthsTimer = null;
    function productModalWidthsStorageKey() {
        var path = (typeof location !== 'undefined' && location.pathname) ? location.pathname : 'default';
        return 'pm-col-widths:' + path + ':v1';
    }
    function productModalLoadColumnWidths() {
        try {
            var raw = localStorage.getItem(productModalWidthsStorageKey());
            if (!raw) return null;
            var o = JSON.parse(raw);
            return o && typeof o === 'object' ? o : null;
        } catch (eW) {
            return null;
        }
    }
    function productModalSaveColumnWidths(widths) {
        try {
            localStorage.setItem(productModalWidthsStorageKey(), JSON.stringify(widths));
        } catch (eS) {}
    }
    function productModalDebouncedSaveColumnWidths() {
        if (pmSaveWidthsTimer) clearTimeout(pmSaveWidthsTimer);
        pmSaveWidthsTimer = setTimeout(function () {
            productModalSaveColumnWidths(productModalCollectWidthsFromTable());
        }, 350);
    }
    function productModalSetColumnWidthPx(table, colKey, px) {
        if (!table || !colKey) return;
        var n;
        if (colKey === 'actions') {
            n = Math.max(72, Math.min(160, Math.round(px)));
        } else {
            n = Math.max(48, Math.min(900, Math.round(px)));
        }
        var esc = String(colKey).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        table.querySelectorAll('th[data-column="' + esc + '"], td[data-column="' + esc + '"]').forEach(function (cell) {
            cell.style.width = n + 'px';
            cell.style.minWidth = n + 'px';
            cell.style.maxWidth = n + 'px';
        });
    }
    function productModalApplyColumnWidths(table, widths) {
        if (!table || !widths || typeof widths !== 'object') return;
        Object.keys(widths).forEach(function (key) {
            var px = parseInt(widths[key], 10);
            if (key === 'actions') {
                if (px >= 60) productModalSetColumnWidthPx(table, key, px);
                return;
            }
            if (!px || px < 48) return;
            productModalSetColumnWidthPx(table, key, px);
        });
    }
    /** Row with per-column labels (th[data-column]); skips thead filter rows that contain inputs. */
    function productModalGetIndividualHeaderRow(table) {
        if (!table) return null;
        var trs = table.querySelectorAll('thead tr');
        if (!trs.length) return null;
        if (trs.length === 1) return trs[0];
        var best = null;
        var bestN = -1;
        for (var i = 0; i < trs.length; i++) {
            var tr = trs[i];
            if (tr.querySelector('th input:not([type="hidden"]), th select, th textarea')) {
                continue;
            }
            var n = tr.querySelectorAll('th[data-column]').length;
            if (n > bestN) {
                bestN = n;
                best = tr;
            }
        }
        return best || table.querySelector('thead tr:nth-child(2)') || trs[0];
    }
    function productModalCollectWidthsFromTable() {
        var tables = getProductSelectionTables();
        if (!tables.length) return {};
        var table = tables[0];
        var out = {};
        var headerRow = productModalGetIndividualHeaderRow(table);
        if (!headerRow) return out;
        headerRow.querySelectorAll('th[data-column]').forEach(function (th) {
            var k = th.getAttribute('data-column');
            if (!k) return;
            var w = th.offsetWidth;
            if (k === 'actions') {
                if (w >= 60) out[k] = w;
            } else if (w >= 48) {
                out[k] = w;
            }
        });
        return out;
    }
    function productModalRemoveColumnResizers(table) {
        if (!table) return;
        table.querySelectorAll('thead .pm-col-resizer').forEach(function (el) {
            el.remove();
        });
        table.querySelectorAll('thead th[data-pm-resize-anchor="1"]').forEach(function (th) {
            th.removeAttribute('data-pm-resize-anchor');
            th.style.removeProperty('position');
        });
    }
    /** Absolute .pm-col-resizer needs a positioned ancestor; sticky th already qualifies. Only static cells need relative. */
    function productModalEnsureThPositionForColResize(th) {
        if (!th) return;
        var pos = window.getComputedStyle(th).position;
        if (pos === 'static') {
            th.style.position = 'relative';
            th.setAttribute('data-pm-resize-anchor', '1');
        }
    }
    function productModalInstallColumnResizers(table) {
        if (!table) return;
        productModalRemoveColumnResizers(table);
        var headerRow = productModalGetIndividualHeaderRow(table);
        if (!headerRow) return;
        headerRow.querySelectorAll('th[data-column]').forEach(function (th) {
            var col = th.getAttribute('data-column');
            if (!col || col === 'actions') return;
            productModalEnsureThPositionForColResize(th);
            var grip = document.createElement('span');
            grip.className = 'pm-col-resizer';
            grip.setAttribute('title', 'Drag right edge to resize column');
            grip.setAttribute('aria-hidden', 'true');
            th.appendChild(grip);
            grip.addEventListener('mousedown', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var startX = e.pageX;
                var startW = th.offsetWidth;
                function onMove(e2) {
                    var nw = startW + (e2.pageX - startX);
                    productModalSetColumnWidthPx(table, col, nw);
                }
                function onUp() {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    document.body.style.cursor = '';
                    productModalDebouncedSaveColumnWidths();
                }
                document.body.style.cursor = 'col-resize';
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        });
    }
    function installProductModalColumnResizers() {
        var saved = productModalLoadColumnWidths();
        getProductSelectionTables().forEach(function (table) {
            if (saved) productModalApplyColumnWidths(table, saved);
            productModalInstallColumnResizers(table);
        });
    }
    window.installProductModalColumnResizers = installProductModalColumnResizers;

    /** After any column show/hide: refresh group colspans, placeholder colspan, sticky-right mode, then light body cell order. */
    function syncProductModalColumnLayoutAfterToggle() {
        updateGroupHeaderVisibility();
        installProductModalColumnResizers();
        fixBodyAlignment();
    }
    window.syncProductModalColumnLayoutAfterToggle = syncProductModalColumnLayoutAfterToggle;

    function isModalColumnHeaderPhysicallyVisible(th) {
        if (!th) return false;
        var cs = window.getComputedStyle(th);
        return !th.classList.contains('hidden') &&
            th.style.display !== 'none' &&
            cs.display !== 'none';
    }

    function countVisibleColumnsInGroup(groupColumns) {
        var tables = getProductSelectionTables();
        if (!tables.length) return 0;
        var headerRows = tables[0].querySelectorAll('thead tr');
        var individualHeaderRow = headerRows.length > 1 ? headerRows[1] : null;
        if (!individualHeaderRow) return 0;
        var visibleCount = 0;
        for (var i = 0; i < groupColumns.length; i++) {
            var columnName = groupColumns[i];
            var checkbox = document.querySelector('#modalTableSettingsDropdown input[data-column="' + columnName + '"], #modalTableSettingsDropdownModal input[data-column="' + columnName + '"]') ||
                document.querySelector('#modal-col-' + columnName) ||
                document.querySelector('#modal-col-m-' + columnName);
            var columnHeader = individualHeaderRow.querySelector('th[data-column="' + columnName + '"]');
            var headerVisible = isModalColumnHeaderPhysicallyVisible(columnHeader);
            if (checkbox) {
                if (checkbox.checked && headerVisible) visibleCount++;
            } else if (headerVisible) {
                visibleCount++;
            }
        }
        return visibleCount;
    }

    /** Resolve group id for a column key from PRODUCT_MODAL_COLUMN_GROUPS. */
    function getProductModalColumnGroupKey(columnKey) {
        if (!columnKey) return null;
        var groups = window.PRODUCT_MODAL_COLUMN_GROUPS || PRODUCT_MODAL_COLUMN_GROUPS;
        var keys = Object.keys(groups);
        for (var i = 0; i < keys.length; i++) {
            var k = keys[i];
            if ((groups[k] || []).indexOf(columnKey) !== -1) return k;
        }
        return null;
    }

    /**
     * Mark the rightmost *visible* column of each group in DOM order with .group-end (group divider).
     * Uses data-group on headers when present; otherwise falls back to PRODUCT_MODAL_COLUMN_GROUPS.
     * Sub-column drag only changes DOM order — this recalculates from actual header order, not static array order.
     */
    function markProductModalGroupEndColumns() {
        var tables = getProductSelectionTables();
        if (!tables.length) return;
        tables.forEach(function(table) {
            table.querySelectorAll('.modal-col-group-end, .group-end').forEach(function(el) {
                el.classList.remove('modal-col-group-end', 'group-end');
            });
        });
        tables.forEach(function(table) {
        var headerRow = table.querySelector('thead tr:nth-child(2)');
        if (!headerRow) return;

        function headerCellVisible(th) {
            if (!th || th.classList.contains('hidden')) return false;
            var cs = window.getComputedStyle(th);
            return cs.display !== 'none' && cs.visibility !== 'collapse';
        }

        function bodyCellVisible(td) {
            if (!td || td.classList.contains('hidden')) return false;
            var cs = window.getComputedStyle(td);
            return cs.display !== 'none' && cs.visibility !== 'collapse';
        }

        var ordered = [];
        headerRow.querySelectorAll('th[data-column]').forEach(function(th) {
            var colKey = th.getAttribute('data-column');
            if (!colKey || colKey === 'actions') return;
            if (!headerCellVisible(th)) return;
            var g = th.getAttribute('data-group') || getProductModalColumnGroupKey(colKey);
            if (!g) return;
            ordered.push({ th: th, colKey: colKey, group: g });
        });

        for (var i = 0; i < ordered.length; i++) {
            var cur = ordered[i];
            var next = ordered[i + 1];
            if (!next || next.group !== cur.group) {
                cur.th.classList.add('group-end');
                var esc = String(cur.colKey).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
                table.querySelectorAll('tbody td[data-column="' + esc + '"]').forEach(function(td) {
                    if (bodyCellVisible(td)) td.classList.add('group-end');
                });
            }
        }
        });
    }
    window.markProductModalGroupEndColumns = markProductModalGroupEndColumns;

    /**
     * Keep each group header colspan aligned with a single contiguous run of row-2 th in DOM order
     * (stamped data-group). Counting only the map of keys could mismatch DOM order and misalign
     * Hallmark / Net+Reverse with Other Charge (overlap / wrong colspans).
     */
    function fixProductModalHeader() {
        var tables = getProductSelectionTables();
        if (!tables.length) return;
        if (typeof stampProductModalDataGroupOnCells === 'function') {
            stampProductModalDataGroupOnCells();
        }
        tables.forEach(function(table) {
            var thead = table.querySelector('thead');
            if (!thead) return;
            var groupRow = thead.querySelector('tr:first-child');
            var columnRow = thead.querySelector('tr:nth-child(2)');
            if (!groupRow || !columnRow) return;

            var gmap = window.PRODUCT_MODAL_COLUMN_GROUPS;
            var thList = Array.prototype.slice.call(columnRow.querySelectorAll('th[data-column]'));
            var colspanByGroup = Object.create(null);

            if (thList.length) {
                var idx = 0;
                while (idx < thList.length) {
                    var th0 = thList[idx];
                    var c0 = th0.getAttribute('data-column');
                    if (c0 === 'actions' || c0 === 'images') {
                        idx++;
                        continue;
                    }
                    var g = th0.getAttribute('data-group');
                    if (!g) {
                        idx++;
                        continue;
                    }
                    var runStart = idx;
                    var runEnd = idx + 1;
                    while (runEnd < thList.length) {
                        var tn = thList[runEnd];
                        if (tn.getAttribute('data-group') === g) {
                            runEnd++;
                        } else {
                            break;
                        }
                    }
                    var vis = 0;
                    for (var k = runStart; k < runEnd; k++) {
                        if (isModalColumnHeaderPhysicallyVisible(thList[k])) {
                            vis++;
                        }
                    }
                    colspanByGroup[g] = (colspanByGroup[g] != null) ? (colspanByGroup[g] + vis) : vis;
                    idx = runEnd;
                }
            }

            var useDomRuns = thList.length > 0 && Object.keys(colspanByGroup).length > 0;

            groupRow.querySelectorAll('th[data-group]').forEach(function(groupTh) {
                var groupName = groupTh.getAttribute('data-group');
                if (!groupName) return;
                var esc = groupName.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
                var count;
                if (useDomRuns && Object.prototype.hasOwnProperty.call(colspanByGroup, groupName) && colspanByGroup[groupName] > 0) {
                    count = colspanByGroup[groupName];
                } else {
                    count = 0;
                    var colKeys = gmap && gmap[groupName] ? gmap[groupName] : null;
                    if (colKeys && colKeys.length) {
                        for (var j = 0; j < colKeys.length; j++) {
                            var ckey = colKeys[j];
                            var cesc = String(ckey).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
                            var h2 = columnRow.querySelector('th[data-column="' + cesc + '"]');
                            if (h2 && isModalColumnHeaderPhysicallyVisible(h2)) {
                                count++;
                            }
                        }
                    } else {
                        var cells = columnRow.querySelectorAll('th[data-group="' + esc + '"]');
                        for (var i = 0; i < cells.length; i++) {
                            if (isModalColumnHeaderPhysicallyVisible(cells[i])) {
                                count++;
                            }
                        }
                    }
                }
                if (count > 0) {
                    groupTh.setAttribute('colspan', String(count));
                    groupTh.style.display = '';
                    groupTh.classList.remove('hidden');
                } else {
                    groupTh.style.display = 'none';
                    groupTh.classList.add('hidden');
                    groupTh.setAttribute('colspan', '1');
                }
            });
        });
    }
    window.fixProductModalHeader = fixProductModalHeader;

    /** Reorder product-row cells to match thead, remove duplicate td[data-column]; no innerHTML (stock journal / fit tables stay responsive). */
    function fixBodyAlignment() {
        getProductSelectionTables().forEach(function(table) {
            table.querySelectorAll('tbody tr.product-row').forEach(function(row) {
                if (typeof reorderModalRowCellsToMatchHeader === 'function') {
                    reorderModalRowCellsToMatchHeader(row, table);
                }
                var seen = {};
                row.querySelectorAll('td[data-column]').forEach(function(td) {
                    var col = td.getAttribute('data-column');
                    if (!col) return;
                    if (seen[col]) {
                        td.remove();
                        return;
                    }
                    seen[col] = true;
                });
            });
        });
    }
    window.fixBodyAlignment = fixBodyAlignment;

    function updateGroupHeaderVisibility() {
        fixProductModalHeader();
        updateProductModalPlaceholderColspan();
        adjustProductModalStickyRightColumns();
        markProductModalGroupEndColumns();
    }
    /** Placeholder / loading rows may use a wide colspan; sync to visible column count (stock journal product grid without checkbox: 73 with Images). */
    function updateProductModalPlaceholderColspan() {
        getProductSelectionTables().forEach(function(table) {
            var n = 0;
            table.querySelectorAll('thead tr:nth-child(2) th[data-column]').forEach(function(th) {
                if (isModalColumnHeaderPhysicallyVisible(th)) n++;
            });
            var cb = table.querySelector('thead th[data-column="checkbox"]');
            if (cb && isModalColumnHeaderPhysicallyVisible(cb)) n++;
            var ac = table.querySelector('thead th[data-column="actions"]');
            if (ac && isModalColumnHeaderPhysicallyVisible(ac)) n++;
            var im = table.querySelector('thead th[data-column="images"][rowspan]');
            if (im && isModalColumnHeaderPhysicallyVisible(im)) n++;
            if (n < 1) n = 1;
            table.querySelectorAll('tbody > tr:not(.product-row) td[colspan]').forEach(function(td) {
                td.setAttribute('colspan', String(n));
            });
        });
    }
    /**
     * Dynamic sticky right columns: only when table overflows; right values computed from actual column widths.
     * Order from the right edge: actions, images, reverse, net-amt-tax, net-amt. Group header net-reverse aligns after actions+images.
     */
    function adjustProductModalStickyRightColumns() {
        getProductSelectionTables().forEach(function(table) {
        var wrap = document.getElementById('productListTableScrollWrapper') || table.parentElement;
        if (!wrap) return;

        /* Stock journal: CSS in stock-journal-create.php stacks Actions → Images → Reverse → Net Amt+Tax with calc(--sj-sticky-*). JS that only measured actions+reverse+net-amt-tax overwrote right/z-index and hid Net Amt+Tax under Images. */
        if (table.classList.contains('product-list-table-fit')) {
            requestAnimationFrame(function() {
                ['actions', 'images', 'reverse', 'net-amt-tax', 'net-amt'].forEach(function(col) {
                    table.querySelectorAll('th[data-column="' + col + '"], td[data-column="' + col + '"]').forEach(function(el) {
                        el.style.position = '';
                        el.style.right = '';
                        el.style.zIndex = '';
                        el.style.background = '';
                        el.classList.remove('sticky-right');
                    });
                });
                var gh = table.querySelector('th[data-group="net-reverse"]');
                if (gh) {
                    gh.style.position = '';
                    gh.style.right = '';
                    gh.style.zIndex = '';
                    gh.style.background = '';
                    gh.classList.remove('sticky-right');
                }
            });
            return;
        }

        requestAnimationFrame(function() {
            var isScrollable = table.scrollWidth > wrap.clientWidth + 10;
            var stickyCols = ['actions', 'images', 'reverse', 'net-amt-tax', 'net-amt'];
            var zIndexByCol = { 'actions': 10, 'images': 9, 'reverse': 8, 'net-amt-tax': 7, 'net-amt': 6 };
            var rightOffset = 0;
            var groupHeaderRight = 0;

            stickyCols.forEach(function(col) {
                var elements = table.querySelectorAll('th[data-column="' + col + '"], td[data-column="' + col + '"]');
                var columnWidth = 80;
                var firstEl = null;
                elements.forEach(function(el) {
                    if (el.offsetParent !== null) {
                        if (!firstEl) firstEl = el;
                    }
                });
                if (firstEl) columnWidth = firstEl.offsetWidth || 80;
                else columnWidth = 0;

                elements.forEach(function(el) {
                    if (isScrollable && el.offsetParent !== null) {
                        el.style.position = 'sticky';
                        el.style.right = rightOffset + 'px';
                        el.style.zIndex = String(zIndexByCol[col] || 5);
                        el.style.background = (el.tagName === 'TH') ? '#a68a4a' : '#fff';
                        el.classList.add('sticky-right');
                    } else {
                        el.style.position = 'relative';
                        el.style.right = 'auto';
                        el.style.zIndex = '';
                        el.style.background = '';
                        el.classList.remove('sticky-right');
                    }
                });
                rightOffset += columnWidth;
                if (col === 'images') groupHeaderRight = rightOffset;
            });

            var groupHeader = table.querySelector('th[data-group="net-reverse"]');
            if (groupHeader) {
                if (isScrollable && groupHeader.offsetParent !== null) {
                    groupHeader.style.position = 'sticky';
                    groupHeader.style.right = groupHeaderRight + 'px';
                    groupHeader.style.zIndex = '7';
                    groupHeader.style.background = '#a68a4a';
                    groupHeader.classList.add('sticky-right');
                } else {
                    groupHeader.style.position = 'relative';
                    groupHeader.style.right = 'auto';
                    groupHeader.style.zIndex = '';
                    groupHeader.style.background = '';
                    groupHeader.classList.remove('sticky-right');
                }
            }
        });
        });
    }
    function initProductModalLayoutObserver() {
        if (typeof MutationObserver === 'undefined') return;
        ['productListBody', 'productListBodyPage'].forEach(function(id) {
            var body = document.getElementById(id);
            if (!body || body.getAttribute('data-layout-observer-inited') === '1') return;
            /** RAF: batch rapid mutations. Guard: re-entrancy if sync triggers scripts that touch the DOM. */
            var raf = null;
            var syncing = 0;
            var syncLayout = function() {
                if (syncing) return;
                if (raf) return;
                raf = (typeof requestAnimationFrame === 'function')
                    ? requestAnimationFrame(function() {
                        raf = null;
                        syncing = 1;
                        try {
                            if (typeof syncProductModalColumnLayoutAfterToggle === 'function') {
                                syncProductModalColumnLayoutAfterToggle();
                            } else if (typeof updateGroupHeaderVisibility === 'function') {
                                updateGroupHeaderVisibility();
                            }
                        } finally {
                            syncing = 0;
                        }
                    })
                    : setTimeout(function() {
                        raf = null;
                        syncing = 1;
                        try {
                            if (typeof syncProductModalColumnLayoutAfterToggle === 'function') {
                                syncProductModalColumnLayoutAfterToggle();
                            } else if (typeof updateGroupHeaderVisibility === 'function') {
                                updateGroupHeaderVisibility();
                            }
                        } finally {
                            syncing = 0;
                        }
                    }, 0);
            };
            /** Subtree: false so fixBodyAlignment() (td reorder via innerHTML inside tr) does not re-fire this and loop forever. */
            var observer = new MutationObserver(function() { syncLayout(); });
            observer.observe(body, { childList: true, subtree: false });
            body.setAttribute('data-layout-observer-inited', '1');
            syncLayout();
        });
    }
    window.countVisibleColumnsInGroup = countVisibleColumnsInGroup;
    window.updateGroupHeaderVisibility = updateGroupHeaderVisibility;
    window.updateProductModalPlaceholderColspan = updateProductModalPlaceholderColspan;
    window.adjustProductModalStickyRightColumns = adjustProductModalStickyRightColumns;
    window.initProductModalLayoutObserver = initProductModalLayoutObserver;
    setTimeout(initProductModalLayoutObserver, 0);

    /**
     * Non–Diamond metal tabs: apply short header labels to product-modal th cells (matches METAL_GROUP_HEADER_LABELS).
     */
    function applyMetalGroupHeaderLabelsToGrids() {
        if (typeof isDiamondTabActive === 'function' && isDiamondTabActive()) return;
        var L = window.METAL_GROUP_HEADER_LABELS;
        if (!L || typeof L !== 'object') return;
        ['productListTable', 'productListTablePage'].forEach(function (tid) {
            var table = document.getElementById(tid);
            if (!table) return;
            table.querySelectorAll('thead th[data-column].product-modal-th-cell .product-modal-th-label').forEach(function (span) {
                var th = span.closest('th[data-column]');
                if (!th) return;
                var col = th.getAttribute('data-column');
                if (col && Object.prototype.hasOwnProperty.call(L, col) && L[col] !== undefined) {
                    span.textContent = L[col];
                    th.setAttribute('title', L[col]);
                }
            });
        });
    }
    window.applyMetalGroupHeaderLabelsToGrids = applyMetalGroupHeaderLabelsToGrids;
    /**
     * Reference order for any screen that enforces a metal (non-diamond) default (e.g. stock journal prefs reset).
     */
    window.getMetalGroupDefaultColumnKeyOrder = function() {
        return (window.METAL_GROUP_VISIBLE_COLUMNS && window.METAL_GROUP_VISIBLE_COLUMNS.slice) ? window.METAL_GROUP_VISIBLE_COLUMNS.slice() : [];
    };

    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('shown.bs.modal', '#productSelectionModal', function() {
            if (typeof auragoldApplyManualAmountFieldsEditability === 'function') {
                auragoldApplyManualAmountFieldsEditability();
            }
            if (typeof auragoldPopulateModalSpecSelectsAllRows === 'function') {
                auragoldPopulateModalSpecSelectsAllRows();
            }
            if (typeof applyMetalGroupHeaderLabelsToGrids === 'function') {
                applyMetalGroupHeaderLabelsToGrids();
            }
            /** Stock Journal: throttled runStockJournalProductRowAlignmentPipeline in page script — avoid double sync+fix on open. */
            if (typeof window.runStockJournalProductRowAlignmentPipeline === 'function') {
                window.runStockJournalProductRowAlignmentPipeline();
                return;
            }
            if (typeof syncProductModalColumnLayoutAfterToggle === 'function') {
                syncProductModalColumnLayoutAfterToggle();
            } else if (typeof fixProductModalHeader === 'function') {
                fixProductModalHeader();
            }
        });

        /** Keep product selection open when Add Product / Add Category / Add Image opens on top (Bootstrap hides parent by default). */
        if (!window._auragoldProductSelectionNestedModalsBound) {
            window._auragoldProductSelectionNestedModalsBound = true;
            var $ = jQuery;
            var nestedParentSel = '#productSelectionModal';
            var nestedChildSels = ['#productCreationModal', '#categoryCreationModal', '#addImageModal'];

            function nestedChildModalVisible() {
                for (var i = 0; i < nestedChildSels.length; i++) {
                    if ($(nestedChildSels[i]).hasClass('show')) {
                        return true;
                    }
                }
                return false;
            }

            function restoreProductSelectionParent() {
                var $parent = $(nestedParentSel);
                if (!$parent.length || !$parent.data('auragold-nested-parent')) {
                    return;
                }
                if (!$parent.hasClass('show')) {
                    $parent.addClass('show');
                    $parent.css('display', 'block');
                    $parent.attr('aria-hidden', 'false');
                }
                $('body').addClass('modal-open');
                if ($('.modal-backdrop').length === 0) {
                    $('body').append('<div class="modal-backdrop fade show"></div>');
                }
            }

            $(nestedParentSel).on('hide.bs.modal', function(e) {
                if ($(document.body).data('auragold-nested-child-opening') || nestedChildModalVisible()) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    return false;
                }
            });
            $(nestedParentSel).on('hidden.bs.modal', function() {
                $(this).removeData('auragold-nested-parent');
            });

            nestedChildSels.forEach(function(childSel) {
                var $child = $(childSel);
                if (!$child.length) {
                    return;
                }
                $child.on('show.bs.modal', function() {
                    var $parent = $(nestedParentSel);
                    if ($parent.hasClass('show')) {
                        $(document.body).data('auragold-nested-child-opening', true);
                        $parent.data('auragold-nested-parent', true);
                    }
                });
                $child.on('shown.bs.modal', function() {
                    $(document.body).removeData('auragold-nested-child-opening');
                    restoreProductSelectionParent();
                });
                $child.on('hidden.bs.modal', function() {
                    restoreProductSelectionParent();
                });
            });
        }

        /** Product category column uses .add-product-category-icon (tbl_categories); Diamond category uses .add-category-icon. */
        if (!window._auragoldAddProductCategoryIconBound) {
            window._auragoldAddProductCategoryIconBound = true;
            jQuery(document).on('click', '.add-product-category-icon', function(e) {
                e.stopPropagation();
                e.preventDefault();
                jQuery('#categoryCreationModal').modal('show');
                if (typeof window.loadParentCategories === 'function') {
                    window.loadParentCategories();
                }
            });
        }
    }

    /**
     * Diamond tab: Jewellery rows mirror summed Diamond Carat / D.Weight / diamond-amount from Diamonds + GemStones rows.
     * When there are NO Diamonds/GemStones lines in the modal, do NOT overwrite Jewellery — user can enter carat/D.Weight manually.
     */
    function updateJewelleryDiamondCaratFromDiamondAndGemstone() {
        ['productListBody', 'productListBodyPage'].forEach(function(tbodyId) {
        var tbody = document.getElementById(tbodyId);
        if (!tbody) return;
        var rows = tbody.querySelectorAll('.product-row');
        var sumCarat = 0;
        var sumDWeight = 0;
        var sumAmountFromDiamondAndStone = 0;
        var jewelleryRows = [];
        var hasDiamondOrGemStoneRows = false;
        for (var i = 0; i < rows.length; i++) {
            var r = rows[i];
            var catVal = modalRowCategoryValue(r);
            if (isDiamondOrGemStoneLineCategory(catVal)) {
                hasDiamondOrGemStoneRows = true;
                var swInp = r.querySelector('[data-column="stone-weight"] input');
                if (swInp) sumCarat += parseFloat(swInp.value) || 0;
                var lessInp = r.querySelector('[data-column="less-wt"] input');
                if (lessInp) sumDWeight += parseFloat(lessInp.value) || 0;
                sumAmountFromDiamondAndStone += modalRowDiamondComponentLineAmount(r);
            } else if (isJewelleryDiamondCategory(catVal)) {
                jewelleryRows.push(r);
            }
        }
        var formattedCarat = (isNaN(sumCarat) ? 0 : sumCarat).toFixed(3);
        var formattedDWeight = (isNaN(sumDWeight) ? 0 : sumDWeight).toFixed(3);
        var jewelleryDiamondGroupAmount = isNaN(sumAmountFromDiamondAndStone) ? 0 : sumAmountFromDiamondAndStone;
        for (var j = 0; j < jewelleryRows.length; j++) {
            var jr = jewelleryRows[j];
            var jCaratInp = jr.querySelector('[data-column="stone-weight"] input');
            var jLessInp = jr.querySelector('[data-column="less-wt"] input');
            var jDiamondAmtInp = jr.querySelector('[data-column="diamond-amount"] input');
            if (hasDiamondOrGemStoneRows) {
                if (jCaratInp) jCaratInp.value = formattedCarat;
                if (jLessInp) jLessInp.value = formattedDWeight;
                if (jDiamondAmtInp) jDiamondAmtInp.value = jewelleryDiamondGroupAmount.toFixed(2);
            }
            if (typeof calculateModalRowNetWeight === 'function') calculateModalRowNetWeight(jr);
        }
        });
        updateDiamondTabFcAmountAndLineMetalValue();
        if (typeof window.updateJewelleryNetAmountAndFinal === 'function') window.updateJewelleryNetAmountAndFinal();
    }
    window.updateJewelleryDiamondCaratFromDiamondAndGemstone = updateJewelleryDiamondCaratFromDiamondAndGemstone;

    /**
     * JewelStep-style Diamond tab: FC Amount on Jewellery = sum of Diamonds + GemStones line totals;
     * Metal Value (diamond-line-metal-value) per component row; Jewellery row shows the same sum.
     */
    function updateDiamondTabFcAmountAndLineMetalValue() {
        ['productListBody', 'productListBodyPage'].forEach(function(tbodyId) {
            var tbody = document.getElementById(tbodyId);
            if (!tbody) return;
            var rows = tbody.querySelectorAll('.product-row');
            var componentRows = [];
            var jewelleryRows = [];
            var sumComponentLine = 0;
            var hasCompositeGrid = false;
            for (var i = 0; i < rows.length; i++) {
                var r = rows[i];
                var catVal = modalRowCategoryValue(r);
                if (!isAnyDiamondTabCategory(catVal)) continue;
                hasCompositeGrid = true;
                if (isDiamondOrGemStoneLineCategory(catVal)) {
                    componentRows.push(r);
                    var lineAmt = modalRowDiamondComponentLineAmount(r);
                    sumComponentLine += lineAmt;
                    var lineMetalInp = r.querySelector('[data-column="diamond-line-metal-value"] input');
                    if (lineMetalInp) lineMetalInp.value = lineAmt.toFixed(2);
                    var fcComp = r.querySelector('[data-column="fc-amount"] input');
                    if (fcComp) fcComp.value = '0.00';
                } else if (isJewelleryDiamondCategory(catVal)) {
                    jewelleryRows.push(r);
                }
            }
            if (!hasCompositeGrid || !componentRows.length) return;
            jewelleryRows.forEach(function(jr) {
                var fcInp = jr.querySelector('[data-column="fc-amount"] input');
                if (fcInp) fcInp.value = sumComponentLine.toFixed(2);
                var lineMetalInp = jr.querySelector('[data-column="diamond-line-metal-value"] input');
                if (lineMetalInp) lineMetalInp.value = sumComponentLine.toFixed(2);
            });
        });
    }
    window.updateDiamondTabFcAmountAndLineMetalValue = updateDiamondTabFcAmountAndLineMetalValue;

    /**
     * Diamond tab composite: Jewellery Net Amt = sum(Diamonds + GemStones line totals) + Jewellery row metal/making/stone/other;
     * component rows keep their own net amounts.
     */
    function updateJewelleryNetAmountAndFinal() {
        ['productListBody', 'productListBodyPage'].forEach(function(tbodyId) {
            var tbody = document.getElementById(tbodyId);
            if (!tbody) return;
            var rows = tbody.querySelectorAll('.product-row');
            var jewelleryRows = [];
            var componentRows = [];
            var sumComponentNet = 0;
            var hasCompositeGrid = false;
            for (var i = 0; i < rows.length; i++) {
                var r = rows[i];
                var catVal = modalRowCategoryValue(r);
                if (!isAnyDiamondTabCategory(catVal)) continue;
                hasCompositeGrid = true;
                if (isDiamondOrGemStoneLineCategory(catVal)) {
                    componentRows.push(r);
                    sumComponentNet += modalRowDiamondComponentLineAmount(r);
                } else if (isJewelleryDiamondCategory(catVal)) {
                    jewelleryRows.push(r);
                }
            }
            if (!hasCompositeGrid) return;

            if (componentRows.length && jewelleryRows.length) {
                jewelleryRows.forEach(function(jr) {
                    var metalVal = modalRowCellNumber(jr, 'metal-value');
                    var making = modalRowCellNumber(jr, 'making-amount');
                    var stone = modalRowCellNumber(jr, 'stone-amount');
                    var other = modalRowCellNumber(jr, 'other-amount');
                    var discount = modalRowCellNumber(jr, 'discount');
                    var jewelleryExtras = metalVal + making + stone + other - discount;
                    if (jewelleryExtras < 0) jewelleryExtras = 0;
                    var netAmt = sumComponentNet + jewelleryExtras;
                    if (netAmt < 0) netAmt = 0;
                    var saleNet = auragoldRowSaleInvoiceTaxableNet(jr);
                    auragoldApplyRowNetAmtTaxFromBase(jr, saleNet !== null ? saleNet : netAmt);
                    var purchaseInp = jr.querySelector('[data-column="purchase-amount"] input');
                    if (purchaseInp && saleNet === null) {
                        purchaseInp.value = netAmt.toFixed(2);
                    }
                });
                updateDiamondTabFcAmountAndLineMetalValue();
                return;
            }

            for (var j = 0; j < rows.length; j++) {
                var row = rows[j];
                var cat = modalRowCategoryValue(row);
                if (!isAnyDiamondTabCategory(cat)) continue;
                var saleNet = auragoldRowSaleInvoiceTaxableNet(row);
                if (saleNet !== null) {
                    auragoldApplyRowNetAmtTaxFromBase(row, saleNet);
                    continue;
                }
                var metalVal = modalRowCellNumber(row, 'metal-value');
                var making = modalRowCellNumber(row, 'making-amount');
                var stone = modalRowCellNumber(row, 'stone-amount');
                var diamond = modalRowCellNumber(row, 'diamond-amount');
                var other = modalRowCellNumber(row, 'other-amount');
                var discount = modalRowCellNumber(row, 'discount');
                var netAmt = metalVal + making + stone + diamond + other - discount;
                if (netAmt < 0) netAmt = 0;
                auragoldApplyRowNetAmtTaxFromBase(row, netAmt);
            }
            updateDiamondTabFcAmountAndLineMetalValue();
        });
    }
    window.updateJewelleryNetAmountAndFinal = updateJewelleryNetAmountAndFinal;

    /**
     * Diamond & Stones tab: one barcode for the whole composite (Jewellery + Diamonds + GemStones lines).
     * Anchor barcode: first non-empty on a Jewellery row, else first on a Diamonds row, else first on a GemStones row.
     * That value is copied to every Jewellery / Diamonds / GemStones row in the Product Selection grid.
     */
    function syncDiamondTabSharedBarcodes() {
        ['productListBody', 'productListBodyPage'].forEach(function(tbodyId) {
        var tbody = document.getElementById(tbodyId);
        if (!tbody) return;
        var rows = Array.prototype.slice.call(tbody.querySelectorAll('.product-row'));
        if (!rows.length) return;
        // Do not rely on isDiamondTabActive(); detect Diamond & Stones grid by category values or diamond-category-select
        var hasDiamondGridRow = rows.some(function(r) {
            var sel = r.querySelector('[data-column="category"] select');
            if (sel && sel.classList.contains('diamond-category-select')) return true;
            var v = (sel && sel.value) ? String(sel.value).trim() : '';
            return v === 'Jewellery' || v === 'Diamonds' || v === 'GemStones';
        });
        if (!hasDiamondGridRow) return;

        function rowCategory(row) {
            var sel = row.querySelector('[data-column="category"] select');
            return (sel && sel.value) ? String(sel.value).trim() : '';
        }
        function rowBarcode(row) {
            var inp = row.querySelector('[data-column="barcode"] input');
            return inp ? String(inp.value || '').trim() : '';
        }
        function setBarcode(row, code) {
            var inp = row.querySelector('[data-column="barcode"] input');
            if (inp) inp.value = code;
            if (code) row.setAttribute('data-barcode', code);
        }
        function isCompositeCat(c) {
            return c === 'Jewellery' || c === 'Diamonds' || c === 'GemStones';
        }
        function syncBarcodeGroup(groupRows) {
            if (!groupRows || !groupRows.length) return;
            var anchor = '';
            for (var pi = 0; pi < groupRows.length; pi++) {
                if (!isCompositeCat(rowCategory(groupRows[pi]))) continue;
                var bp = rowBarcode(groupRows[pi]);
                if (!bp) continue;
                if (!/^DIAA/i.test(bp)) {
                    anchor = bp;
                    break;
                }
            }
            var order = ['Jewellery', 'Diamonds', 'GemStones'];
            if (!anchor) {
                for (var o = 0; o < order.length; o++) {
                    var want = order[o];
                    for (var i = 0; i < groupRows.length; i++) {
                        if (rowCategory(groupRows[i]) === want) {
                            var b = rowBarcode(groupRows[i]);
                            if (b) {
                                anchor = b;
                                break;
                            }
                        }
                    }
                    if (anchor) break;
                }
            }
            if (!anchor) return;
            groupRows.forEach(function(row) {
                var c = rowCategory(row);
                if (isCompositeCat(c)) {
                    setBarcode(row, anchor);
                }
            });
        }

        var hasCatalogueSets = rows.some(function(r) {
            return r.hasAttribute('data-catalogue-set-index');
        });
        if (hasCatalogueSets) {
            var bySet = {};
            rows.forEach(function(row) {
                var setKey = row.getAttribute('data-catalogue-set-index');
                if (setKey === null || setKey === '') setKey = '__default__';
                if (!bySet[setKey]) bySet[setKey] = [];
                bySet[setKey].push(row);
            });
            Object.keys(bySet).forEach(function(setKey) {
                syncBarcodeGroup(bySet[setKey]);
            });
            return;
        }

        syncBarcodeGroup(rows);
        });
    }
    window.syncDiamondTabSharedBarcodes = syncDiamondTabSharedBarcodes;

    /**
     * Barcodes already present on other invoice/modal rows (excludes one row, e.g. current).
     */
    function collectUsedBarcodesForInvoiceRows(excludeRow) {
        var used = [];
        var seen = {};
        function add(val) {
            var t = String(val || '').trim();
            if (!t || seen[t]) return;
            seen[t] = 1;
            used.push(t);
        }
        var roots = [
            document.getElementById('productListBody'),
            document.getElementById('productListBodyPage'),
            document.getElementById('productTableBody')
        ];
        roots.forEach(function(root) {
            if (!root) return;
            root.querySelectorAll('tr').forEach(function(tr) {
                if (excludeRow && tr === excludeRow) return;
                if (tr.classList.contains('no-drag')) return;
                var inp = tr.querySelector('[data-column="barcode"] input');
                if (inp) add(inp.value);
                else {
                    var cell = tr.querySelector('[data-column="barcode"]');
                    if (cell) add(cell.textContent);
                }
            });
        });
        return used;
    }
    window.collectUsedBarcodesForInvoiceRows = collectUsedBarcodesForInvoiceRows;

    function getNextBarcodeFromServer(opts, cb) {
        opts = opts || {};
        var prefix = String(opts.prefix != null ? opts.prefix : '').trim();
        var digit = parseInt(opts.digit, 10) || 0;
        var used = Array.isArray(opts.used) ? opts.used : [];
        var candidate = String(opts.candidate != null ? opts.candidate : '').trim();
        var fd = new FormData();
        if (prefix) fd.append('prefix', prefix);
        if (digit > 0) fd.append('digit', String(digit));
        if (candidate) fd.append('candidate', candidate);
        used.forEach(function(u) {
            if (u) fd.append('used[]', u);
        });
        var xhr = new XMLHttpRequest();
        var url = (typeof window.AURAGOLD_GET_NEXT_BARCODE_URL === 'string' && window.AURAGOLD_GET_NEXT_BARCODE_URL)
            ? window.AURAGOLD_GET_NEXT_BARCODE_URL
            : 'ajax/get-next-barcode.php';
        xhr.open('POST', url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            var barcode = '';
            try {
                var data = JSON.parse(xhr.responseText || '{}');
                if (data.success && data.barcode) barcode = String(data.barcode).trim();
            } catch (e) {}
            if (typeof cb === 'function') cb(barcode, xhr.status);
        };
        xhr.send(fd);
    }
    window.getNextBarcodeFromServer = getNextBarcodeFromServer;

    /**
     * Resolve barcode_prefix / barcode_digits from modal row data or get-product-details (product opening / characteristic).
     */
    function resolveBarcodePrefixDigitForModal(modalRowData, cb) {
        modalRowData = modalRowData || {};
        var prefix = (modalRowData.barcode_prefix != null && String(modalRowData.barcode_prefix).trim() !== '')
            ? String(modalRowData.barcode_prefix).trim() : '';
        var digit = parseInt(modalRowData.barcode_digits, 10);
        if (prefix && !isNaN(digit) && digit >= 1) {
            if (typeof cb === 'function') cb(prefix, digit);
            return;
        }
        var pid = modalRowData.product_id;
        var cid = modalRowData.characteristic_id;
        if (!pid || !cid) {
            if (typeof cb === 'function') cb('', 0);
            return;
        }
        var mid = (modalRowData.metal_id != null && String(modalRowData.metal_id).trim() !== '') ? String(modalRowData.metal_id).trim() : '';
        var xhr = new XMLHttpRequest();
        var urlPd = 'ajax/get-product-details.php?product_id=' + encodeURIComponent(pid) + '&characteristic_id=' + encodeURIComponent(cid) + (mid ? ('&metal_id=' + encodeURIComponent(mid)) : '');
        xhr.open('GET', urlPd, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            var p = '';
            var d = 0;
            try {
                var data = JSON.parse(xhr.responseText || '{}');
                if (data.success && data.product) {
                    p = String(data.product.barcode_prefix || '').trim();
                    d = parseInt(data.product.barcode_digits, 10) || 0;
                }
            } catch (e) {}
            if (typeof cb === 'function') cb(p, d);
        };
        xhr.send();
    }
    window.resolveBarcodePrefixDigitForModal = resolveBarcodePrefixDigitForModal;

    /**
     * Product List row from modal: allocate a new barcode (prefix/digits from opening) unique among other list rows + stock rules via get-next-barcode.
     */
    function assignUniqueBarcodeToProductListRow(row, modalRowData) {
        if (!row || !modalRowData) return;
        if (typeof isDiamondTabActive === 'function' && isDiamondTabActive()) return;
        if (typeof getNextBarcodeFromServer !== 'function' || typeof collectUsedBarcodesForInvoiceRows !== 'function') return;
        var candidateEarly = String(modalRowData.barcode || row.getAttribute('data-barcode') || '').trim();
        // Keep the piece's tag when selling stock: get-next-barcode would allocate the next serial if this tag still "exists" in inventory.
        if (candidateEarly !== '') {
            row.setAttribute('data-barcode', candidateEarly);
            var spanE = row.querySelector('[data-column="barcode"] span');
            var inpE = row.querySelector('[data-column="barcode"] input');
            if (inpE) inpE.value = candidateEarly;
            else if (spanE) spanE.textContent = candidateEarly;
            try {
                var giE = row.getAttribute('data-group-items');
                if (giE) {
                    var arrE = JSON.parse(giE);
                    if (Array.isArray(arrE) && arrE.length > 0) {
                        arrE[0].barcode = candidateEarly;
                        row.setAttribute('data-group-items', JSON.stringify(arrE));
                    }
                }
            } catch (e) {}
            modalRowData.barcode = candidateEarly;
            if (typeof refreshProductListBarcodeGroups === 'function') refreshProductListBarcodeGroups();
            return;
        }
        resolveBarcodePrefixDigitForModal(modalRowData, function(prefix, digit) {
            var used = collectUsedBarcodesForInvoiceRows(row);
            var candidate = String(modalRowData.barcode || row.getAttribute('data-barcode') || '').trim();
            getNextBarcodeFromServer({ prefix: prefix, digit: digit, used: used, candidate: candidate }, function(barcode) {
                if (!barcode) return;
                row.setAttribute('data-barcode', barcode);
                var span = row.querySelector('[data-column="barcode"] span');
                var inp = row.querySelector('[data-column="barcode"] input');
                if (inp) inp.value = barcode;
                else if (span) span.textContent = barcode;
                try {
                    var gi = row.getAttribute('data-group-items');
                    if (gi) {
                        var arr = JSON.parse(gi);
                        if (Array.isArray(arr) && arr.length > 0) {
                            arr[0].barcode = barcode;
                            row.setAttribute('data-group-items', JSON.stringify(arr));
                        }
                    }
                } catch (e) {}
                modalRowData.barcode = barcode;
                if (typeof refreshProductListBarcodeGroups === 'function') refreshProductListBarcodeGroups();
            });
        });
    }
    window.assignUniqueBarcodeToProductListRow = assignUniqueBarcodeToProductListRow;

    /**
     * Merged Product List row: one new barcode per grouped modal item (same uniqueness rules).
     */
    function assignUniqueBarcodesToMergedProductListRow(row, modalRowsData) {
        if (!row || !modalRowsData || !modalRowsData.length) return;
        if (typeof isDiamondTabActive === 'function' && isDiamondTabActive()) return;
        if (typeof getNextBarcodeFromServer !== 'function' || typeof collectUsedBarcodesForInvoiceRows !== 'function') return;
        var used = collectUsedBarcodesForInvoiceRows(row);
        var i = 0;
        function step() {
            if (i >= modalRowsData.length) {
                var parts = modalRowsData.map(function(d) { return String(d.barcode || '').trim(); }).filter(Boolean);
                var joined = parts.join(', ');
                row.setAttribute('data-barcode', parts[0] || '');
                var span = row.querySelector('[data-column="barcode"] span');
                var inp = row.querySelector('[data-column="barcode"] input');
                if (inp) inp.value = joined;
                else if (span) span.textContent = joined;
                row.setAttribute('data-group-items', JSON.stringify(modalRowsData));
                if (typeof refreshProductListBarcodeGroups === 'function') refreshProductListBarcodeGroups();
                return;
            }
            var d = modalRowsData[i];
            var candMerged = String(d.barcode || '').trim();
            if (candMerged !== '') {
                d.barcode = candMerged;
                if (used.indexOf(candMerged) === -1) used.push(candMerged);
                i++;
                step();
                return;
            }
            resolveBarcodePrefixDigitForModal(d, function(prefix, digit) {
                var candidate = String(d.barcode || '').trim();
                getNextBarcodeFromServer({ prefix: prefix, digit: digit, used: used.slice(), candidate: candidate }, function(barcode) {
                    if (barcode) {
                        d.barcode = barcode;
                        used.push(barcode);
                    }
                    i++;
                    step();
                });
            });
        }
        step();
    }
    window.assignUniqueBarcodesToMergedProductListRow = assignUniqueBarcodesToMergedProductListRow;

    /**
     * Assign sequential unique barcodes to multiple main-table rows (e.g. catalogue qty copies).
     */
    function assignUniqueBarcodesSequentialForProductListRows(pairs) {
        if (!pairs || !pairs.length) return;
        if (typeof getNextBarcodeFromServer !== 'function' || typeof collectUsedBarcodesForInvoiceRows !== 'function') return;
        var used = collectUsedBarcodesForInvoiceRows(null);
        var idx = 0;
        function step() {
            if (idx >= pairs.length) return;
            var pair = pairs[idx];
            var row = pair && pair.row;
            var modalRowData = pair && pair.modalRowData;
            if (!row || !modalRowData) {
                idx++;
                step();
                return;
            }
            var candSeq = String(modalRowData.barcode || row.getAttribute('data-barcode') || '').trim();
            if (candSeq !== '') {
                row.setAttribute('data-barcode', candSeq);
                var spanKeep = row.querySelector('[data-column="barcode"] span');
                var inpKeep = row.querySelector('[data-column="barcode"] input');
                if (inpKeep) inpKeep.value = candSeq;
                else if (spanKeep) spanKeep.textContent = candSeq;
                if (used.indexOf(candSeq) === -1) used.push(candSeq);
                idx++;
                step();
                return;
            }
            resolveBarcodePrefixDigitForModal(modalRowData, function(prefix, digit) {
                getNextBarcodeFromServer({ prefix: prefix, digit: digit, used: used.slice() }, function(barcode) {
                    if (barcode) {
                        row.setAttribute('data-barcode', barcode);
                        var span = row.querySelector('[data-column="barcode"] span');
                        var inp = row.querySelector('[data-column="barcode"] input');
                        if (inp) inp.value = barcode;
                        else if (span) span.textContent = barcode;
                        modalRowData.barcode = barcode;
                        used.push(barcode);
                        try {
                            var gi = row.getAttribute('data-group-items');
                            if (gi) {
                                var arr = JSON.parse(gi);
                                if (Array.isArray(arr) && arr.length > 0) {
                                    arr.forEach(function(x) { if (x) x.barcode = barcode; });
                                    row.setAttribute('data-group-items', JSON.stringify(arr));
                                }
                            }
                        } catch (e) {}
                    }
                    idx++;
                    step();
                });
            });
        }
        step();
    }
    window.assignUniqueBarcodesSequentialForProductListRows = assignUniqueBarcodesSequentialForProductListRows;

    /**
     * After manual product pick: resolve barcode via server (unique in doc + not in stock). Skips barcode scan flow and Diamond & Stones tab (shared tag handled on save).
     */
    function afterPopulateRowWithProductUniqueBarcode(row, product, opts) {
        opts = opts || {};
        if (opts.fromBarcode || !row || !product) return;
        if (typeof isDiamondTabActive === 'function' && isDiamondTabActive()) {
            if (typeof syncDiamondTabSharedBarcodes === 'function') syncDiamondTabSharedBarcodes();
            return;
        }
        var prefix = (product.barcode_prefix != null && String(product.barcode_prefix).trim() !== '')
            ? String(product.barcode_prefix).trim() : '';
        var digit = parseInt(product.barcode_digits, 10);
        if (isNaN(digit) || digit < 1) digit = 0;
        var used = collectUsedBarcodesForInvoiceRows(row);
        var inp = row.querySelector('[data-column="barcode"] input');
        var candidate = inp ? String(inp.value || '').trim() : '';
        getNextBarcodeFromServer({ prefix: prefix, digit: digit, used: used, candidate: candidate }, function(barcode) {
            if (!barcode || !inp) return;
            inp.value = barcode;
            if (typeof syncDiamondTabSharedBarcodes === 'function') syncDiamondTabSharedBarcodes();
            try {
                var ev = document.createEvent('Event');
                ev.initEvent('change', true, true);
                inp.dispatchEvent(ev);
            } catch (e2) {
                try { inp.dispatchEvent(new Event('change', { bubbles: true })); } catch (e3) {}
            }
        });
    }
    window.afterPopulateRowWithProductUniqueBarcode = afterPopulateRowWithProductUniqueBarcode;

    // ========== Dashboard metal rates (tbl_dashboard_metal_rates) → Rate / Metal Rate on Karat change ==========
    var __auragoldDashboardRatesCache = null;
    var __auragoldDashboardRatesCacheBranchKey = null;
    var __auragoldDashboardRatesPromise = null;

    /** Same branch scope as dashboard.php / stock journal (AURAGOLD_STOCK_JOURNAL_BRANCH_ID). */
    function auragoldDashboardMetalRatesQueryString() {
        var bid = 0;
        if (typeof window.AURAGOLD_STOCK_JOURNAL_BRANCH_ID !== 'undefined' && window.AURAGOLD_STOCK_JOURNAL_BRANCH_ID) {
            bid = parseInt(window.AURAGOLD_STOCK_JOURNAL_BRANCH_ID, 10) || 0;
        }
        if (bid <= 0 && typeof window.AURAGOLD_DASH_BRANCH_ID !== 'undefined' && window.AURAGOLD_DASH_BRANCH_ID) {
            bid = parseInt(window.AURAGOLD_DASH_BRANCH_ID, 10) || 0;
        }
        return bid > 0 ? ('?branch_id=' + encodeURIComponent(String(bid))) : '';
    }

    function auragoldDashboardCaratLabelsMatchJS(cardLabel, rowCarat) {
        var a = String(cardLabel).trim();
        var b = String(rowCarat).trim();
        if (!a || !b) return false;
        if (a.toLowerCase() === b.toLowerCase()) return true;
        var al = a.toLowerCase();
        var bl = b.toLowerCase();
        if (bl.indexOf(al) === 0 || al.indexOf(bl) === 0) return true;
        return false;
    }

    function normalizeGoldKaratKeyForDashboard(name) {
        var up = String(name).trim().toUpperCase().replace(/\s+/g, '');
        if (!up) return '';
        var m = up.match(/^(\d{1,2})K$/);
        if (m) return m[1] + 'K';
        m = up.match(/^(\d{1,2})/);
        if (m && /K|KT|KARAT/.test(up)) return m[1] + 'K';
        return '';
    }

    function findDashboardRateForLabel(ratesMap, labelRaw) {
        if (!ratesMap || !labelRaw) return null;
        var t = String(labelRaw).trim();
        if (!t) return null;
        if (Object.prototype.hasOwnProperty.call(ratesMap, t)) return ratesMap[t];
        var keys = Object.keys(ratesMap);
        var i;
        for (i = 0; i < keys.length; i++) {
            if (keys[i].toLowerCase() === t.toLowerCase()) return ratesMap[keys[i]];
        }
        var nk = normalizeGoldKaratKeyForDashboard(t);
        if (nk && Object.prototype.hasOwnProperty.call(ratesMap, nk)) return ratesMap[nk];
        for (i = 0; i < keys.length; i++) {
            if (auragoldDashboardCaratLabelsMatchJS(keys[i], t)) return ratesMap[keys[i]];
        }
        return null;
    }

    function fetchDashboardMetalRatesMaps() {
        var branchKey = auragoldDashboardMetalRatesQueryString();
        if (__auragoldDashboardRatesCache && __auragoldDashboardRatesCacheBranchKey === branchKey) {
            return Promise.resolve(__auragoldDashboardRatesCache);
        }
        if (__auragoldDashboardRatesPromise) {
            return __auragoldDashboardRatesPromise;
        }
        __auragoldDashboardRatesPromise = fetch('ajax/get-dashboard-metal-rates.php' + branchKey, { credentials: 'same-origin', method: 'GET' })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                __auragoldDashboardRatesPromise = null;
                if (data && data.status === 'ok' && data.rates) {
                    __auragoldDashboardRatesCache = data.rates;
                    __auragoldDashboardRatesCacheBranchKey = branchKey;
                    return __auragoldDashboardRatesCache;
                }
                return null;
            })
            .catch(function() {
                __auragoldDashboardRatesPromise = null;
                return null;
            });
        return __auragoldDashboardRatesPromise;
    }

    /**
     * When user selects Karat on Gold / Silver tab, fill Metal Rate and Rate from dashboard saved rates.
     * @param {HTMLElement} row
     * @param {function} done always called (after async or immediately)
     */
    function auragoldSyncPurityFromCaratSelect(row) {
        if (!row || !row.querySelector) {
            return;
        }
        var sel = row.querySelector('[data-column="carat"] select') || row.querySelector('.carat-select');
        if (!sel || !sel.value) {
            return;
        }
        var opt = sel.options[sel.selectedIndex];
        if (!opt) {
            return;
        }
        var p = opt.getAttribute('data-purity');
        if (p === null || String(p).trim() === '') {
            return;
        }
        var n = parseFloat(p);
        var display = !isNaN(n) ? (n > 1 ? n.toFixed(2) : String(n)) : String(p);
        var purityInput = row.querySelector('[data-column="purity"] input') || row.querySelector('[data-field="purity"]');
        if (purityInput) {
            purityInput.value = display;
        }
        row.setAttribute('data-purity', !isNaN(n) ? n : p);
    }
    /**
     * After karat is set (scan or manual): sync purity % and dashboard metal rate, then run callback.
     */
    function auragoldApplyCaratDerivedFieldsOnModalRow(row, done) {
        var finish = typeof done === 'function' ? done : function() {};
        if (!row || !row.querySelector) {
            finish();
            return;
        }
        auragoldSyncPurityFromCaratSelect(row);
        if (typeof applyDashboardMetalRateFromCaratSelect === 'function') {
            applyDashboardMetalRateFromCaratSelect(row, finish);
        } else {
            finish();
        }
    }
    window.auragoldApplyCaratDerivedFieldsOnModalRow = auragoldApplyCaratDerivedFieldsOnModalRow;

    window.auragoldSyncPurityFromCaratSelect = auragoldSyncPurityFromCaratSelect;

    /**
     * When ounce rate is active: Metal Rate = US Gold (or US Silver) / 32.
     * @returns {boolean} true if ounce rate was applied to the row
     */
    function auragoldApplyOunceRateToModalRowMetalRate(row) {
        if (!row || !row.querySelector || typeof window.auragoldOunceRateGetMetalRate !== 'function') {
            return false;
        }
        if (!window.auragoldOunceRateIsActive('si')) {
            return false;
        }

        var metalId = row.getAttribute('data-metal-id');
        if ((metalId === null || metalId === '') && typeof currentMetalId !== 'undefined') {
            metalId = currentMetalId;
        }
        var metalName = '';
        if (typeof window.metals !== 'undefined' && window.metals && metalId != null && metalId !== '') {
            var mm = window.metals.find(function (x) { return String(x.id) === String(metalId); });
            if (mm) metalName = mm.display_name || mm.name || mm.system_name || '';
        }
        if (!metalName && typeof currentMetalName !== 'undefined') {
            metalName = currentMetalName || '';
        }
        if (!metalName && row.getAttribute) {
            var dmn = row.getAttribute('data-metal-name');
            if (dmn) metalName = String(dmn).trim();
        }
        var mn = String(metalName).toLowerCase();
        var metalKey = null;
        if (mn.indexOf('gold') !== -1) {
            metalKey = 'gold';
        } else if (mn.indexOf('silver') !== -1) {
            metalKey = 'silver';
        } else {
            return false;
        }

        var ozRate = window.auragoldOunceRateGetMetalRate('si', metalKey);
        if (ozRate == null || isNaN(ozRate)) {
            return false;
        }

        var metalRateInput = row.querySelector('[data-column="metal-rate"] input');
        var rateInput = row.querySelector('[data-column="rate"] input');
        var formatted = ozRate.toFixed(4);
        if (metalRateInput) metalRateInput.value = formatted;
        if (rateInput) rateInput.value = formatted;
        return true;
    }
    window.auragoldApplyOunceRateToModalRowMetalRate = auragoldApplyOunceRateToModalRowMetalRate;

    window.auragoldRefreshModalRowsOunceMetalRate = function () {
        var modal = document.getElementById('productSelectionModal');
        if (!modal) return;
        var rows = modal.querySelectorAll('#productListTable tbody tr.product-row, #productListTable tbody tr');
        rows.forEach(function (row) {
            if (!row.querySelector('[data-column="metal-rate"]')) return;
            if (typeof window.auragoldOunceRateIsActive === 'function' && window.auragoldOunceRateIsActive('si')) {
                var applied = auragoldApplyOunceRateToModalRowMetalRate(row);
                if (applied && typeof calculateModalRowNetWeight === 'function') {
                    calculateModalRowNetWeight(row);
                }
            } else if (typeof applyDashboardMetalRateFromCaratSelect === 'function') {
                applyDashboardMetalRateFromCaratSelect(row, function () {
                    if (typeof calculateModalRowNetWeight === 'function') calculateModalRowNetWeight(row);
                });
            }
        });
    };

    function applyDashboardMetalRateFromCaratSelect(row, done) {
        var finish = typeof done === 'function' ? done : function() {};
        try {
            if (!row || !row.querySelector) {
                finish();
                return;
            }
            if (auragoldApplyOunceRateToModalRowMetalRate(row)) {
                finish();
                return;
            }
            var sel = row.querySelector('[data-column="carat"] select') || row.querySelector('.carat-select');
            if (!sel || !sel.value) {
                finish();
                return;
            }
            var opt = sel.options[sel.selectedIndex];
            var labelText = opt ? String(opt.text || opt.textContent || '').trim() : '';
            if (!labelText || /^select/i.test(labelText)) {
                finish();
                return;
            }

            var metalId = row.getAttribute('data-metal-id');
            if ((metalId === null || metalId === '') && typeof currentMetalId !== 'undefined') {
                metalId = currentMetalId;
            }
            var metalName = '';
            if (typeof window.metals !== 'undefined' && window.metals && metalId != null && metalId !== '') {
                var mm = window.metals.find(function(x) { return String(x.id) === String(metalId); });
                if (mm) {
                    metalName = mm.display_name || mm.name || mm.system_name || '';
                }
            }
            if (!metalName && typeof currentMetalName !== 'undefined') {
                metalName = currentMetalName || '';
            }
            if (!metalName && row.getAttribute) {
                var dmn = row.getAttribute('data-metal-name');
                if (dmn) {
                    metalName = String(dmn).trim();
                }
            }
            var mn = String(metalName).toLowerCase();
            var metalKey = null;
            if (mn.indexOf('gold') !== -1) {
                metalKey = 'gold';
            } else if (mn.indexOf('silver') !== -1) {
                metalKey = 'silver';
            } else {
                finish();
                return;
            }

            fetchDashboardMetalRatesMaps().then(function(rates) {
                if (!rates || !rates[metalKey]) {
                    finish();
                    return;
                }
                var raw = findDashboardRateForLabel(rates[metalKey], labelText);
                if (raw == null || isNaN(parseFloat(raw))) {
                    finish();
                    return;
                }
                var n = parseFloat(raw);
                var metalRateInput = row.querySelector('[data-column="metal-rate"] input');
                var rateInput = row.querySelector('[data-column="rate"] input');
                if (metalRateInput) metalRateInput.value = n.toFixed(2);
                if (rateInput) rateInput.value = n.toFixed(2);
                finish();
            }).catch(function() { finish(); });
        } catch (e) {
            finish();
        }
    }
    window.applyDashboardMetalRateFromCaratSelect = applyDashboardMetalRateFromCaratSelect;
    window.fetchDashboardMetalRatesMaps = fetchDashboardMetalRatesMaps;

})();

/**
 * Floating “Moving column …” chip during Product Selection modal header drag (same UX as stock-journal-column-drag.js).
 * Requires CSS for .product-modal-col-drag-ghost (included in includes/common-modal.php).
 */
(function(global) {
    'use strict';
    var ghostEl = null;
    function removeAllNodes() {
        try {
            document.querySelectorAll('.product-modal-col-drag-ghost').forEach(function(n) {
                if (n && n.parentNode) n.parentNode.removeChild(n);
            });
        } catch (eRm) {}
        ghostEl = null;
    }
    function plainHeaderLabel(th) {
        if (!th) return '';
        var t = th.cloneNode(true);
        t.querySelectorAll('.product-modal-col-drag-handle, .add-category-icon, .add-product-category-icon, .add-product-icon, .add-location-icon, .feather').forEach(function(x) {
            x.remove();
        });
        return (t.textContent || '').replace(/\s+/g, ' ').trim();
    }
    global.productModalColDragUi = {
        removeAll: removeAllNodes,
        hide: function() {
            if (ghostEl && ghostEl.parentNode) ghostEl.parentNode.removeChild(ghostEl);
            ghostEl = null;
            removeAllNodes();
        },
        move: function(e) {
            if (!ghostEl || !e) return;
            ghostEl.style.left = (e.clientX + 14) + 'px';
            ghostEl.style.top = (e.clientY + 14) + 'px';
        },
        show: function(th, e) {
            removeAllNodes();
            var g = document.createElement('div');
            g.className = 'product-modal-col-drag-ghost product-modal-col-drag-ghost--minimal';
            g.setAttribute('role', 'status');
            g.setAttribute('aria-live', 'polite');
            var label = plainHeaderLabel(th);
            if (label.length > 40) label = label.slice(0, 38) + '\u2026';
            g.textContent = label || (th && th.getAttribute('data-column')) || '';
            g.style.position = 'fixed';
            g.style.zIndex = '10060';
            g.style.pointerEvents = 'none';
            document.body.appendChild(g);
            ghostEl = g;
            if (e) {
                ghostEl.style.left = (e.clientX + 14) + 'px';
                ghostEl.style.top = (e.clientY + 14) + 'px';
            }
        }
    };
})(typeof window !== 'undefined' ? window : this);

/**
 * Barcode photos from Gold & Silver / tbl_stock_journal_images — shared by invoice/quotation pages.
 * Uses ajax/gas-list-stock-journal-images.php and get-product-by-barcode (journal_image_* on product payload).
 */
(function(global) {
    'use strict';
    function auragoldCoParseGroupImageAttr(raw) {
        if (raw == null || String(raw).trim() === '') return '';
        var s = String(raw).trim();
        if (s.charAt(0) === '{') {
            try { return JSON.parse(s); } catch (e) { return s; }
        }
        return s;
    }

    function auragoldMainTableBarcodeFromRow(tr) {
        if (!tr) return '';
        var a = String(tr.getAttribute('data-barcode') || '').trim();
        if (a) return a;
        var sp = tr.querySelector('[data-column="barcode"] span');
        if (sp) return String(sp.textContent || '').trim();
        return '';
    }

    function auragoldRefreshProductTableRowPhotoFromJournal(tr) {
        if (!tr || typeof global.fetch !== 'function') return;
        var bc = auragoldMainTableBarcodeFromRow(tr);
        var jidRaw = String(tr.getAttribute('data-stock-journal-id') || '').trim();
        var itemIdNum = parseInt(jidRaw, 10);
        var hasItemId = !isNaN(itemIdNum) && itemIdNum > 0;
        var bcOk = bc && bc !== '\u2014' && bc !== '\u2013';
        if (!bcOk && !hasItemId) return;
        var boot = global.PRODUCT_LIST_TABLE_BOOT || {};
        var noImg = boot.noImageSrc || 'no_image.jpg';
        var photoTd = tr.querySelector('td[data-column="photo"]');
        if (!photoTd) return;
        function keepExistingGroupImage() {
            var g = tr.getAttribute('data-group-image');
            if (!g || !String(g).trim()) return false;
            var t = String(g).trim();
            if (t === '{}' || t === 'null') return false;
            return true;
        }
        function setImg(url) {
            var u = url ? String(url).trim() : '';
            if (!u) u = noImg;
            var im = photoTd.querySelector('img');
            if (!im) {
                photoTd.innerHTML = '<img src="" alt="" style="max-width: 50px; max-height: 50px; object-fit: contain;">';
                im = photoTd.querySelector('img');
            }
            if (im) {
                im.src = u;
                im.style.display = '';
                if (u !== noImg) im.onerror = function() { this.onerror = null; this.src = noImg; };
                else im.onerror = null;
            }
        }
        var fd = new FormData();
        if (bcOk) fd.append('barcode_no', bc);
        if (hasItemId) fd.append('item_id', String(itemIdNum));
        var bcSnapshot = bcOk ? bc : '';
        global.fetch('ajax/gas-list-stock-journal-images.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (bcSnapshot && auragoldMainTableBarcodeFromRow(tr) !== bcSnapshot) return;
                if (!bcSnapshot && hasItemId && String(tr.getAttribute('data-stock-journal-id') || '').trim() !== jidRaw) return;
                if (!data || data.status !== 'success' || !data.images || !data.images.length) {
                    if (keepExistingGroupImage()) return;
                    setImg(noImg);
                    return;
                }
                var u0 = data.images[0].url || '';
                if (!u0) {
                    if (keepExistingGroupImage()) return;
                    setImg(noImg);
                    return;
                }
                setImg(u0);
                var urls = data.images.map(function(x) { return x.url || ''; }).filter(Boolean);
                if (urls.length) {
                    try {
                        tr.setAttribute('data-group-image', JSON.stringify({ primary: urls[0], images: urls }));
                        tr.setAttribute('data-journal-photo', '1');
                    } catch (e2) {}
                }
            })
            .catch(function() {
                if (bcSnapshot && auragoldMainTableBarcodeFromRow(tr) !== bcSnapshot) return;
                if (!bcSnapshot && hasItemId && String(tr.getAttribute('data-stock-journal-id') || '').trim() !== jidRaw) return;
                if (keepExistingGroupImage()) return;
                setImg(noImg);
            });
    }

    function auragoldApplyGroupImageToModalRowPhoto(row, groupImageRaw) {
        if (!row || groupImageRaw == null || String(groupImageRaw).trim() === '') return;
        var boot = (typeof global.PRODUCT_LIST_TABLE_BOOT === 'object' && global.PRODUCT_LIST_TABLE_BOOT) ? global.PRODUCT_LIST_TABLE_BOOT : null;
        var noImg = (boot && boot.noImageSrc) ? String(boot.noImageSrc) : 'no_image.jpg';
        var payload = auragoldCoParseGroupImageAttr(groupImageRaw);
        var primary = '';
        var urls = [];
        if (typeof payload === 'string') {
            primary = payload.trim();
            if (primary) urls = [primary];
        } else if (payload && typeof payload === 'object') {
            if (payload.primary) primary = String(payload.primary).trim();
            if (payload.images && Array.isArray(payload.images)) {
                urls = payload.images.map(function(u) { return u ? String(u).trim() : ''; }).filter(Boolean);
            }
            if (!primary && urls.length) primary = urls[0];
        }
        if (!primary) return;
        var photoCell = row.querySelector('[data-column="photo"].product-row-photo') || row.querySelector('td[data-column="photo"]');
        if (!photoCell) return;
        var img = photoCell.querySelector('.product-photo-thumb') || photoCell.querySelector('img');
        var placeholder = photoCell.querySelector('.product-photo-placeholder') || photoCell.querySelector('.text-muted');
        if (img) {
            img.src = primary;
            img.style.display = '';
            img.onerror = function() { this.onerror = null; this.src = noImg; };
        }
        if (placeholder) placeholder.style.display = 'none';
        var storePayload = { primary: primary, images: urls.length ? urls.slice() : [primary] };
        try { row.setAttribute('data-group-image', JSON.stringify(storePayload)); } catch (e1) {}
        row.removeAttribute('data-journal-photo');
    }

    function auragoldApplyJournalImagesToModalRowPhoto(row, product) {
        if (!row || !product) return;
        var boot = (typeof global.PRODUCT_LIST_TABLE_BOOT === 'object' && global.PRODUCT_LIST_TABLE_BOOT) ? global.PRODUCT_LIST_TABLE_BOOT : null;
        var noImg = (boot && boot.noImageSrc) ? String(boot.noImageSrc) : 'no_image.jpg';
        var urls = product.journal_image_urls;
        if (!urls || !Array.isArray(urls)) urls = [];
        var primary = (product.journal_image_primary != null && String(product.journal_image_primary).trim() !== '')
            ? String(product.journal_image_primary).trim() : (urls[0] || '');
        var photoCell = row.querySelector('[data-column="photo"].product-row-photo') || row.querySelector('td[data-column="photo"]');
        if (!photoCell) return;
        var img = photoCell.querySelector('.product-photo-thumb');
        var placeholder = photoCell.querySelector('.product-photo-placeholder');
        if (primary) {
            if (img) {
                img.src = primary;
                img.style.display = '';
                img.onerror = function() { this.onerror = null; this.src = noImg; };
            }
            if (placeholder) placeholder.style.display = 'none';
            var payload = { primary: primary, images: urls.length ? urls.slice() : [primary] };
            try { row.setAttribute('data-group-image', JSON.stringify(payload)); } catch (e1) {}
            var isRealJournal = primary.indexOf('no_image') === -1 && primary.indexOf('no-image') === -1;
            if (isRealJournal) row.setAttribute('data-journal-photo', '1');
            else row.removeAttribute('data-journal-photo');
        } else {
            if (img) {
                img.src = noImg;
                img.style.display = '';
                img.onerror = null;
            }
            if (placeholder) placeholder.style.display = 'none';
            row.removeAttribute('data-group-image');
            row.removeAttribute('data-journal-photo');
            var bcFall = (product.barcode && String(product.barcode).trim()) ? String(product.barcode).trim() : '';
            if (!bcFall && row) {
                var inpBF = row.querySelector('[data-column="barcode"] input');
                if (inpBF) bcFall = String(inpBF.value || '').trim();
            }
            if (bcFall && typeof global.fetch === 'function') {
                var fd2 = new FormData();
                fd2.append('barcode_no', bcFall);
                global.fetch('ajax/gas-list-stock-journal-images.php', { method: 'POST', body: fd2, credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data || data.status !== 'success' || !data.images || !data.images.length) return;
                        var urls2 = data.images.map(function(x) { return x.url || ''; }).filter(Boolean);
                        if (!urls2.length) return;
                        if (!row || !row.parentNode) return;
                        var inpNow = row.querySelector('[data-column="barcode"] input');
                        var stillBc = inpNow ? String(inpNow.value || '').trim() : '';
                        if (stillBc && stillBc !== bcFall) return;
                        auragoldApplyJournalImagesToModalRowPhoto(row, { journal_image_primary: urls2[0], journal_image_urls: urls2, barcode: bcFall });
                    })
                    .catch(function() {});
            }
        }
    }

    global.auragoldCoParseGroupImageAttr = auragoldCoParseGroupImageAttr;
    global.auragoldMainTableBarcodeFromRow = auragoldMainTableBarcodeFromRow;
    global.auragoldRefreshProductTableRowPhotoFromJournal = auragoldRefreshProductTableRowPhotoFromJournal;
    global.auragoldApplyGroupImageToModalRowPhoto = auragoldApplyGroupImageToModalRowPhoto;
    global.auragoldApplyJournalImagesToModalRowPhoto = auragoldApplyJournalImagesToModalRowPhoto;
})(typeof window !== 'undefined' ? window : this);

/**
 * Reliable hit-testing for Product Selection modal row-2 column headers while dragging.
 * elementFromPoint + closest() often misses (sticky row-1, scrollers, nested targets).
 */
(function(global) {
    'use strict';
    global.findProductModalRow2DropTh = function(headerRow2, clientX, clientY) {
        if (!headerRow2 || clientX == null || clientY == null) return null;
        var stack;
        try {
            stack = document.elementsFromPoint(clientX, clientY);
        } catch (e) {
            stack = null;
        }
        if (stack && stack.length) {
            for (var i = 0; i < stack.length; i++) {
                var n = stack[i];
                if (n && n.nodeType === 1 && n.tagName === 'TH' && headerRow2.contains(n)) {
                    var key = n.getAttribute('data-column');
                    if (key) return n;
                }
            }
        }
        var candidates = headerRow2.querySelectorAll('th[data-column]');
        for (var j = 0; j < candidates.length; j++) {
            var thEl = candidates[j];
            if (thEl.classList && thEl.classList.contains('hidden')) continue;
            var r = thEl.getBoundingClientRect();
            if (clientX >= r.left && clientX <= r.right && clientY >= r.top && clientY <= r.bottom) {
                return thEl;
            }
        }
        return null;
    };

    function auragoldFillSpecSelectElement(sel, list, placeholder) {
        if (!sel || !list || !list.length) return;
        var prev = sel.value;
        sel.innerHTML = '';
        var opt0 = document.createElement('option');
        opt0.value = '';
        opt0.textContent = placeholder || 'Select';
        sel.appendChild(opt0);
        for (var i = 0; i < list.length; i++) {
            var item = list[i];
            if (!item) continue;
            var opt = document.createElement('option');
            var idVal = item.id != null && item.id !== '' ? String(item.id) : '';
            var nameVal = item.name != null && String(item.name).trim() !== '' ? String(item.name).trim() : idVal;
            opt.value = idVal !== '' ? idVal : nameVal;
            opt.textContent = nameVal || opt.value;
            sel.appendChild(opt);
        }
        if (prev) {
            sel.value = prev;
            if (sel.value !== prev) {
                for (var j = 0; j < sel.options.length; j++) {
                    if (sel.options[j].textContent === prev || sel.options[j].value === prev) {
                        sel.value = sel.options[j].value;
                        break;
                    }
                }
            }
        }
    }

    /** Populate Cut / Color / Shape / Clarity / Seive / Size from window.AURAGOLD_MASTERS_* (common-modal-product-selection.php). */
    function auragoldPopulateModalSpecSelectsForRow(row) {
        if (!row || !row.querySelector) return;
        var map = [
            { attr: 'cut', list: window.AURAGOLD_MASTERS_CUTS || [], ph: 'Select Cut' },
            { attr: 'color', list: window.AURAGOLD_MASTERS_COLORS || [], ph: 'Select Color' },
            { attr: 'shape', list: window.AURAGOLD_MASTERS_SHAPES || [], ph: 'Select Shape' },
            { attr: 'clarity', list: window.AURAGOLD_MASTERS_CLARITIES || [], ph: 'Select Clarity' },
            { attr: 'seive', list: window.AURAGOLD_MASTERS_SIEVE_SIZES || [], ph: 'Select Seive' },
            { attr: 'size', list: window.AURAGOLD_MASTERS_SIZES || [], ph: 'Select Size' }
        ];
        for (var i = 0; i < map.length; i++) {
            var m = map[i];
            var sel = row.querySelector('select.auragold-spec-select[data-auragold-spec="' + m.attr + '"]');
            if (!sel || !m.list || !m.list.length) continue;
            if (sel.options.length > 1 && sel.getAttribute('data-auragold-spec-filled') === '1') continue;
            if (typeof window.populateSelect === 'function') {
                window.populateSelect(sel, m.list, 'id', 'name', m.ph);
            } else {
                auragoldFillSpecSelectElement(sel, m.list, m.ph);
            }
            sel.setAttribute('data-auragold-spec-filled', '1');
        }
    }
    window.auragoldPopulateModalSpecSelectsForRow = auragoldPopulateModalSpecSelectsForRow;

    function auragoldPopulateModalSpecSelectsAllRows() {
        ['productListBody', 'productListBodyPage'].forEach(function(tbodyId) {
            var tbody = document.getElementById(tbodyId);
            if (!tbody) return;
            tbody.querySelectorAll('.product-row').forEach(function(r) {
                auragoldPopulateModalSpecSelectsForRow(r);
            });
        });
    }
    window.auragoldPopulateModalSpecSelectsAllRows = auragoldPopulateModalSpecSelectsAllRows;

    if (typeof document !== 'undefined') {
        document.addEventListener('DOMContentLoaded', function() {
            var modal = document.getElementById('productSelectionModal');
            if (!modal) return;
            modal.addEventListener('click', function(e) {
                var btn = e.target.closest('.category-tab-btn');
                if (!btn || !modal.contains(btn)) return;
                setTimeout(function() {
                    syncModalDiamondCategoryFilterForActiveTab();
                    syncModalGroupSingleItemCheckboxForActiveTab();
                    var tbody = document.getElementById('productListBody');
                    if (!tbody || typeof populateCategorySelectForModal !== 'function') return;
                    var diamondTab = typeof isDiamondTabActive === 'function' && isDiamondTabActive();
                    tbody.querySelectorAll('[data-column="category"] select').forEach(function(sel) {
                        var v = (sel.value || '').trim();
                        var tr = sel.closest('tr.product-row');
                        var dataDc = tr && tr.getAttribute('data-diamond-category') ? tr.getAttribute('data-diamond-category').trim() : '';
                        var force = sel.classList.contains('diamond-category-select')
                            || isKnownDiamondCategoryValue(v)
                            || isKnownDiamondCategoryValue(dataDc);
                        if (diamondTab || force) {
                            populateCategorySelectForModal(sel, true);
                        }
                    });
                }, 0);
            });
            syncModalDiamondCategoryFilterForActiveTab();
            syncModalGroupSingleItemCheckboxForActiveTab();
            modal.addEventListener('shown.bs.modal', function() {
                syncModalGroupSingleItemCheckboxForActiveTab();
            });
        });
    }

    /** Match saved karat/carat (id, "24K", or purity 99.90) to carat dropdown options. */
    function auragoldMatchCaratSelectValue(caratSelect, karatVal) {
        if (!caratSelect || karatVal == null || karatVal === '') return false;
        var raw = String(karatVal).trim();
        if (!raw || raw === '0') return false;
        var opts = caratSelect.options;
        for (var i = 0; i < opts.length; i++) {
            if (opts[i].value === raw) {
                caratSelect.selectedIndex = i;
                return true;
            }
        }
        if (typeof window.selectOptionByValueOrText === 'function') {
            window.selectOptionByValueOrText(caratSelect, raw);
            if (caratSelect.value) return true;
        } else {
            for (var j = 0; j < opts.length; j++) {
                var txt = String(opts[j].textContent || '').trim();
                if (txt === raw || txt.toLowerCase() === raw.toLowerCase()) {
                    caratSelect.selectedIndex = j;
                    return true;
                }
            }
        }
        var num = parseFloat(raw.replace(/[^0-9.]/g, ''));
        if (isNaN(num)) return false;
        for (var k = 0; k < opts.length; k++) {
            var o = opts[k];
            var nameNum = parseFloat(String(o.textContent || '').replace(/[^0-9.]/g, ''));
            if (!isNaN(nameNum) && Math.abs(nameNum - num) < 0.001) {
                caratSelect.selectedIndex = k;
                return true;
            }
            var pur = parseFloat(o.getAttribute('data-purity') || '');
            if (!isNaN(pur) && Math.abs(pur - num) < 0.051) {
                caratSelect.selectedIndex = k;
                return true;
            }
        }
        return false;
    }

    /** Restore karat dropdown on a product-selection modal row (edit / saved invoice load). */
    function auragoldRestoreCaratSelectOnModalRow(row, hints) {
        if (!row) return;
        hints = hints || {};
        var caratSelect = row.querySelector('.carat-select') || row.querySelector('[data-column="carat"] select');
        if (!caratSelect) return;
        if (typeof window.populateCaratSelectForModalRow === 'function') {
            window.populateCaratSelectForModalRow(caratSelect, row);
        }
        var candidates = [hints.carat_id, hints.carat, hints.carat_name];
        for (var c = 0; c < candidates.length; c++) {
            var v = candidates[c];
            if (v != null && v !== '' && String(v) !== '0') {
                if (auragoldMatchCaratSelectValue(caratSelect, v)) return;
            }
        }
        if (hints.purity != null && hints.purity !== '' && parseFloat(hints.purity) > 0) {
            auragoldMatchCaratSelectValue(caratSelect, hints.purity);
        }
    }
    window.auragoldRestoreCaratSelectOnModalRow = auragoldRestoreCaratSelectOnModalRow;

    /** Write a value into a product-selection modal row cell (input/select/text). */
    function setModalCellValue(row, column, value, isNumber) {
        if (!row || !column) return;
        var cell = row.querySelector('td[data-column="' + column + '"]') || row.querySelector('[data-column="' + column + '"]');
        if (!cell) return;
        var str = (value == null || value === '') ? '' : String(value);
        if (isNumber && str !== '' && !isNaN(parseFloat(str))) {
            str = String(parseFloat(str));
        }
        var input = cell.querySelector('input');
        var select = cell.querySelector('select');
        if (input) {
            input.value = str;
            return;
        }
        if (select) {
            if (typeof window.setModalSelectIfOptionExists === 'function') {
                window.setModalSelectIfOptionExists(row, column, str);
            } else {
                select.value = str;
            }
            return;
        }
        cell.textContent = str;
    }

    /**
     * Populate a product-selection modal row from saved modal row data (e.g. jewellery catalogue BOM).
     */
    function applyModalRowDataToSelectionRow(row, md) {
        if (!row || !md || typeof md !== 'object') return;

        var productId = md.product_id != null ? String(md.product_id) : '';
        var charId = md.characteristic_id != null ? String(md.characteristic_id) : '';
        var metalId = md.metal_id != null ? String(md.metal_id) : '';
        row.setAttribute('data-product-id', productId);
        row.setAttribute('data-characteristic-id', charId);
        if (metalId) row.setAttribute('data-metal-id', metalId);
        if (md.barcode) row.setAttribute('data-barcode', String(md.barcode));
        if (md.stock_journal_id) row.setAttribute('data-stock-journal-id', String(md.stock_journal_id));
        if (md.gst_local_percent != null && md.gst_local_percent !== '') {
            row.setAttribute('data-gst-local-pct', String(md.gst_local_percent));
        }
        if (md.gst_interstate_percent != null && md.gst_interstate_percent !== '') {
            row.setAttribute('data-gst-interstate-pct', String(md.gst_interstate_percent));
        }
        if (md.gst_invoice_slab_percent != null && md.gst_invoice_slab_percent !== '') {
            row.setAttribute('data-gst-invoice-slab-pct', String(md.gst_invoice_slab_percent));
        }
        if (md.gst_line_taxes != null && md.gst_line_taxes !== '') {
            row.setAttribute('data-gst-line-taxes', String(md.gst_line_taxes));
        }
        if (md.product_taxes != null && md.product_taxes !== '') {
            row.setAttribute('data-product-taxes', String(md.product_taxes));
        }

        var cb = row.querySelector('.product-checkbox');
        if (cb) {
            cb.setAttribute('data-product-id', productId);
            cb.setAttribute('data-characteristic-id', charId);
        }
        var idCell = row.querySelector('[data-column="id"]');
        if (idCell && productId) idCell.textContent = productId;

        setModalCellValue(row, 'product', md.product_name || '', false);
        setModalCellValue(row, 'barcode', md.barcode || '', false);
        setModalCellValue(row, 'design-no', md.design_no || '', false);
        setModalCellValue(row, 'item-code', md.item_code || md.short_code || '', false);
        setModalCellValue(row, 'rfid', md.rfid || '', false);
        setModalCellValue(row, 'huid', md.huid || '', false);
        setModalCellValue(row, 'quantity', md.quantity != null ? md.quantity : 1, true);
        setModalCellValue(row, 'gross-wt', md.gross_wt, true);
        setModalCellValue(row, 'less-wt', md.less_wt, true);
        setModalCellValue(row, 'purity', md.purity, true);
        setModalCellValue(row, 'final-wt', md.final_wt, true);
        setModalCellValue(row, 'net-wt', md.net_wt, true);
        setModalCellValue(row, 'purity-wt', md.pure_wt, true);
        setModalCellValue(row, 'pkt-wt', md.pkt_wt, true);
        setModalCellValue(row, 'pkt-less-wt', md.pkt_less_wt, true);
        setModalCellValue(row, 'stone-weight', md.stone_weight, true);
        setModalCellValue(row, 'rate', md.rate, true);
        setModalCellValue(row, 'metal-rate', md.metal_rate != null ? md.metal_rate : md.rate, true);
        setModalCellValue(row, 'metal-value', md.metal_value, true);
        setModalCellValue(row, 'purchase-rate', md.purchase_rate != null ? md.purchase_rate : (md.metal_rate != null ? md.metal_rate : md.rate), true);
        setModalCellValue(row, 'metal-qty', md.metal_qty != null ? md.metal_qty : 1, true);
        setModalCellValue(row, 'metal-weight', md.metal_weight, true);
        setModalCellValue(row, 'amount', md.amount, true);
        setModalCellValue(row, 'making-amount', md.making_amount, true);
        setModalCellValue(row, 'making-rate', md.making_rate, true);
        setModalCellValue(row, 'making-discount-amt', md.making_discount_amt != null ? md.making_discount_amt : md.making_discount_amount, true);
        setModalCellValue(row, 'making-actual-value', md.making_actual_value, true);
        setModalCellValue(row, 'making-cost', md.making_cost, true);
        if (md.making_type && typeof window.setModalSelectIfOptionExists === 'function') {
            window.setModalSelectIfOptionExists(row, 'making-type', auragoldNormalizeMakingType(md.making_type));
            row.setAttribute('data-manual-making', '1');
        }
        setModalCellValue(row, 'stone-amount', md.stone_amount, true);
        setModalCellValue(row, 'other-amount', md.other_amount, true);
        setModalCellValue(row, 'diamond-amount', md.diamond_amount, true);
        setModalCellValue(row, 'tax', md.tax, true);
        setModalCellValue(row, 'net-amt', md.net_amt, true);
        setModalCellValue(row, 'net-amt-tax', md.net_amt_tax, true);
        setModalCellValue(row, 'purchase-amount', md.purchase_amount, true);
        setModalCellValue(row, 'sale-percent', md.sale_percent, true);
        setModalCellValue(row, 'sale-amount', md.sale_amount, true);
        setModalCellValue(row, 'sale-amount-with', md.sale_amount_with, true);
        setModalCellValue(row, 'reverse', md.reverse, true);

        if (md.category) {
            var catSel = row.querySelector('[data-column="category"] select');
            if (catSel && typeof window.populateCategorySelectForModal === 'function') {
                window.populateCategorySelectForModal(catSel, true);
            }
            setModalCellValue(row, 'category', md.category, false);
        }
        if (md.location) setModalCellValue(row, 'location', md.location, false);
        if (md.product_category_id) setModalCellValue(row, 'product-category', md.product_category_id, false);
        if (md.calculation_type) setModalCellValue(row, 'calculation', md.calculation_type, false);

        if (typeof auragoldRestoreCaratSelectOnModalRow === 'function') {
            auragoldRestoreCaratSelectOnModalRow(row, {
                carat_id: md.carat_id,
                carat: md.carat,
                carat_name: md.carat_name,
                purity: md.purity
            });
        } else if (md.carat) {
            setModalCellValue(row, 'carat', md.carat, false);
        }

        if (md.group_image != null && String(md.group_image).trim() !== '') {
            if (typeof window.auragoldApplyGroupImageToModalRowPhoto === 'function') {
                window.auragoldApplyGroupImageToModalRowPhoto(row, md.group_image);
            }
        }

        if (typeof window.auragoldPopulateModalSpecSelectsForRow === 'function') {
            window.auragoldPopulateModalSpecSelectsForRow(row);
        }
        if (typeof window.auragoldApplyConfiguredSalePercentAndMaking === 'function') {
            window.auragoldApplyConfiguredSalePercentAndMaking(row, false);
        } else if (typeof window.auragoldApplyConfiguredMaking === 'function') {
            window.auragoldApplyConfiguredMaking(row, false);
        }
        if (typeof window.addModalRowCalculationListeners === 'function') {
            window.addModalRowCalculationListeners(row);
        }
        if (typeof window.calculateModalRowNetWeight === 'function') {
            window.calculateModalRowNetWeight(row);
        }
        if (typeof window.reorderModalRowCellsToMatchHeader === 'function') {
            window.reorderModalRowCellsToMatchHeader(row);
        }
    }
    window.applyModalRowDataToSelectionRow = applyModalRowDataToSelectionRow;
    window.setModalCellValue = setModalCellValue;

    // Keep Purchase/Sale Amount editable state in sync when metal tabs change
    if (typeof document !== 'undefined' && !window._auragoldImitationAmountTabBound) {
        window._auragoldImitationAmountTabBound = true;
        document.addEventListener('click', function(e) {
            var btn = e.target && e.target.closest ? e.target.closest('.category-tab-btn') : null;
            if (!btn) return;
            setTimeout(function() {
                if (typeof auragoldApplyManualAmountFieldsEditability === 'function') {
                    auragoldApplyManualAmountFieldsEditability();
                }
            }, 0);
        }, true);
        function auragoldInitManualAmountFieldsEditability() {
            if (typeof auragoldApplyManualAmountFieldsEditability === 'function') {
                auragoldApplyManualAmountFieldsEditability();
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', auragoldInitManualAmountFieldsEditability);
        } else {
            setTimeout(auragoldInitManualAmountFieldsEditability, 0);
        }
        // Product opening rows are often filled async after load
        setTimeout(auragoldInitManualAmountFieldsEditability, 500);
        setTimeout(auragoldInitManualAmountFieldsEditability, 1500);
    }

    /** Shared carat/karat dropdown (Masters → tbl_carat) for voucher pages that set window.carats + window.metals. */
    if (typeof window.populateCaratSelectForModalRow !== 'function') {
        function auragoldPageCaratsList() {
            return (window.carats && window.carats.length) ? window.carats : [];
        }
        function auragoldCaratsMasterUsesMetalIdsShared() {
            var list = auragoldPageCaratsList();
            for (var i = 0; i < list.length; i++) {
                var m = list[i].metal_id;
                if (m != null && String(m).trim() !== '' && String(m) !== '0') return true;
            }
            return false;
        }
        function auragoldMetalTabCategoryFromMetalIdShared(metalId) {
            var idStr = metalId != null ? String(metalId) : '';
            if (!idStr || !window.metals || !window.metals.length) return '';
            for (var i = 0; i < window.metals.length; i++) {
                var x = window.metals[i];
                if (String(x.id) !== idStr) continue;
                var label = (x.display_name || x.system_name || x.name || '').toLowerCase();
                if (!label) return '';
                if (label.indexOf('diamond') !== -1) return 'diamond';
                if (label.indexOf('gold') !== -1) return 'gold';
                if (label.indexOf('silver') !== -1) return 'silver';
                if (label.indexOf('platinum') !== -1) return 'platinum';
                if (label.indexOf('imitation') !== -1 || label.indexOf('watch') !== -1) return 'other';
                if (label.indexOf('other') !== -1 || label.indexOf('service') !== -1) return 'other';
                return '';
            }
            return '';
        }
        function auragoldMetalTabCategoryFromLabelShared(label) {
            var ln = String(label != null ? label : '').toLowerCase();
            if (!ln) return '';
            if (ln.indexOf('diamond') !== -1) return 'diamond';
            if (ln.indexOf('gold') !== -1) return 'gold';
            if (ln.indexOf('silver') !== -1) return 'silver';
            if (ln.indexOf('platinum') !== -1) return 'platinum';
            if (ln.indexOf('imitation') !== -1 || ln.indexOf('watch') !== -1) return 'other';
            if (ln.indexOf('other') !== -1 || ln.indexOf('service') !== -1) return 'other';
            return '';
        }
        function auragoldInferCaratNameKindShared(name) {
            var raw = String(name != null ? name : '').trim();
            if (!raw) return '';
            var n = raw.toLowerCase();
            if (/\d+(\.\d+)?\s*ct\b/i.test(n)) return 'diamond';
            if (/\d+\s*k\b/i.test(raw)) return 'gold';
            if (/^\d{3,4}(\.\d+)?$/i.test(raw.replace(/\s+/g, ''))) return 'fineness';
            return '';
        }
        function auragoldFinenessCodeShared(name) {
            var raw = String(name != null ? name : '').trim().replace(/\s+/g, '');
            var m = raw.match(/^(\d{3,4}(?:\.\d+)?)/i);
            return m ? m[1].toUpperCase() : '';
        }
        function auragoldCaratMatchesTabCategoryShared(crow, tabCat) {
            if (!tabCat || tabCat === 'other') return true;
            var nm = (crow && crow.name != null) ? String(crow.name) : '';
            var kind = auragoldInferCaratNameKindShared(nm);
            if (tabCat === 'gold') return kind === 'gold';
            if (tabCat === 'diamond') return kind === 'diamond';
            var code = auragoldFinenessCodeShared(nm);
            if (tabCat === 'silver') {
                if (kind !== 'fineness' && !code) return false;
                return /^(999|995|990|980|970|960|958|940|925|920|915|910|905|900|875|840|835|800)$/.test(code);
            }
            if (tabCat === 'platinum') {
                if (kind !== 'fineness' && !code) return false;
                return /^(9995|999|990|980|970|960|950|900|850|800)$/.test(code);
            }
            return true;
        }
        function auragoldMetalDisplayNameByIdShared(metalId) {
            var idStr = metalId != null ? String(metalId).trim() : '';
            if (!idStr || !window.metals || !window.metals.length) return '';
            for (var i = 0; i < window.metals.length; i++) {
                var x = window.metals[i];
                if (String(x.id) === idStr) {
                    return String(x.display_name || x.system_name || x.name || '').trim();
                }
            }
            return '';
        }
        function auragoldCaratRowMatchesModalMetalShared(crow, tabMetalId) {
            if (!crow) return false;
            var cm = crow.metal_id;
            var hasMetalLink = !(cm == null || String(cm).trim() === '' || String(cm) === '0');
            if (hasMetalLink && String(cm) === String(tabMetalId)) return true;
            var tabName = auragoldMetalDisplayNameByIdShared(tabMetalId).toLowerCase();
            var caratMetalName = String(crow.metal_name || crow.metal_display_name || '').trim().toLowerCase();
            if (tabName && caratMetalName && tabName === caratMetalName) return true;
            var tabCat = auragoldMetalTabCategoryFromMetalIdShared(tabMetalId);
            if (!tabCat && tabMetalId && window.currentMetalId != null &&
                String(window.currentMetalId) === String(tabMetalId) && window.currentMetalName) {
                tabCat = auragoldMetalTabCategoryFromLabelShared(window.currentMetalName);
            }
            if (tabCat && caratMetalName && auragoldMetalTabCategoryFromLabelShared(caratMetalName) === tabCat) return true;
            if (tabCat) return auragoldCaratMatchesTabCategoryShared(crow, tabCat);
            return !hasMetalLink;
        }
        function auragoldCaratsFilteredForModalMetalShared(metalId) {
            var list = auragoldPageCaratsList();
            if (!list.length) return [];
            var idKey = metalId != null ? String(metalId).trim() : '';
            if (auragoldCaratsMasterUsesMetalIdsShared()) {
                if (!idKey) return list.slice();
                var filtered = list.filter(function (c) { return auragoldCaratRowMatchesModalMetalShared(c, idKey); });
                if (filtered.length) return filtered;
                var tabCat = auragoldMetalTabCategoryFromMetalIdShared(idKey);
                if (!tabCat && window.currentMetalId != null && String(window.currentMetalId) === idKey && window.currentMetalName) {
                    tabCat = auragoldMetalTabCategoryFromLabelShared(window.currentMetalName);
                }
                var byCat = list.filter(function (c) {
                    return tabCat ? auragoldCaratMatchesTabCategoryShared(c, tabCat) : true;
                });
                return byCat.length ? byCat : list.slice();
            }
            var tabCat2 = auragoldMetalTabCategoryFromMetalIdShared(idKey);
            if (!tabCat2 && idKey && window.currentMetalId != null && String(window.currentMetalId) === idKey && window.currentMetalName) {
                tabCat2 = auragoldMetalTabCategoryFromLabelShared(window.currentMetalName);
            }
            if (!tabCat2) return list.slice();
            var byTab = list.filter(function (c) { return auragoldCaratMatchesTabCategoryShared(c, tabCat2); });
            return byTab.length ? byTab : list.slice();
        }
        window.populateCaratSelectForModalRow = function (caratSelect, row) {
            if (!caratSelect || typeof window.populateSelect !== 'function') return;
            var mid = '';
            if (row && row.getAttribute) mid = row.getAttribute('data-metal-id') || '';
            if (!mid && window.currentMetalId != null && window.currentMetalId !== '') mid = String(window.currentMetalId);
            var prev = '';
            try { prev = caratSelect.value || ''; } catch (e) { prev = ''; }
            window.populateSelect(caratSelect, auragoldCaratsFilteredForModalMetalShared(mid), 'id', 'name', 'Select Karat');
            if (prev) {
                try {
                    caratSelect.value = prev;
                    if (caratSelect.value !== prev) caratSelect.value = '';
                } catch (e2) {}
            }
        };
        window.refreshProductModalCaratSelectsForVisibleRows = function () {
            var tbody = document.getElementById('productListBody');
            if (!tbody) return;
            tbody.querySelectorAll('tr.product-row').forEach(function (row) {
                if (row.style.display === 'none') return;
                var sel = row.querySelector('.carat-select');
                if (sel) window.populateCaratSelectForModalRow(sel, row);
            });
        };
    }
})(typeof window !== 'undefined' ? window : this);
