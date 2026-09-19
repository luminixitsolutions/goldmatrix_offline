/**
 * Metal tabs for Add Product modal (#productCreationModal).
 * Adapted from product-opening.php initPoPcMetalTabs — scoped to the modal only.
 */
(function (window, $) {
    'use strict';
    if (!$ || !$.fn) return;

    var ROOT = '#productCreationModal';
    var NS = '.auragoldProductModalPcTabs';

    var PO_PC_FIELD_LABELS = {
        'barcode-digits': 'Barcode Digits',
        'barcode-prefix': 'Barcode Prefix',
        barcode: 'Barcode',
        hsn: 'HSN Code',
        unit: 'Unit',
        sku: 'SKU / Product Code',
        making: 'Making On',
        diamond: 'Diamond Category',
        location: 'Location',
        carat: 'Purity / Karat',
        discount: 'Discount',
        'purity-sale': 'Purity (Sale)',
        'purity-purchase': 'Purity (Purchase)',
        'wastage-sale': 'Wastage (Sale)',
        'wastage-purchase': 'Wastage (Purchase)',
        'wt-per-piece': 'Wt / Piece',
        'opening-weight': 'Opening Weight',
        'opening-purity': 'Opening Purity',
        'opening-qty': 'Opening Qty',
        'opening-finalwt': 'Final Weight',
        'opening-rate': 'Rate',
        'opening-value': 'Value',
        serialized: 'Serialized Barcode',
        cut: 'Cut',
        shape: 'Shape',
        color: 'Color',
        clarity: 'Clarity',
        sieve: 'Sieve',
        size: 'Size',
        stylecode: 'Style Code'
    };
    var PO_PC_REQUIRED_COLS = ['carat', 'hsn', 'barcode-prefix', 'barcode-digits'];
    var PO_PC_PRIMARY_COLS = ['barcode-digits', 'barcode-prefix', 'hsn', 'carat', 'diamond'];
    var PO_PC_FIELD_ORDER = [
        'barcode-digits', 'barcode-prefix', 'hsn', 'carat', 'diamond',
        'unit', 'sku', 'making', 'location', 'discount',
        'purity-sale', 'purity-purchase', 'wastage-sale', 'wastage-purchase', 'wt-per-piece',
        'opening-weight', 'opening-purity', 'opening-qty', 'opening-finalwt', 'opening-rate', 'opening-value',
        'serialized', 'cut', 'shape', 'color', 'clarity', 'sieve', 'size', 'stylecode', 'barcode'
    ];

    function $root() {
        return $(ROOT);
    }

    function $nav() {
        return $root().find('#poPcTabsNav');
    }

    function $rows() {
        return $root().find('#productCharacteristicsBody tr');
    }

    function poPcEscAttr(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function poPcIsDiamondMetal(metalName) {
        var n = String(metalName || '').toLowerCase();
        return n.indexOf('diamond') >= 0 || n.indexOf('stone') >= 0;
    }

    function poPcIsLooseDiamondMetal(metalName) {
        var n = String(metalName || '').trim().toLowerCase().replace(/\s+/g, ' ');
        if (!n || n === 'diamond & stones' || n.indexOf('diamond') === -1) return false;
        return /\b(loose|loos|certified)\b/.test(n);
    }

    function poPcGetDiamondCategorySelect($row) {
        return $row.find('td[data-col="diamond"] select[name*="[diamond_category]"]').first();
    }

    function poPcEnsureLooseDiamondCategorySelect($row, metal) {
        if (!poPcIsLooseDiamondMetal(metal)) {
            $row.removeClass('po-pc-loose-diamond-row');
            return;
        }
        $row.addClass('po-pc-loose-diamond-row');
        var $td = $row.find('td[data-col="diamond"]');
        var $sel = poPcGetDiamondCategorySelect($row);
        if (!$sel.length) {
            var $inp = $td.find('input[name*="[diamond_category]"]').first();
            var name = $inp.attr('name') || '';
            $inp.remove();
            $sel = $('<select class="form-control form-control-sm po-pc-diamond-category-select po-pc-loose-diamond-category-select"></select>');
            if (name) $sel.attr('name', name);
            var $box = $td.find('.po-pc-field-box');
            if ($box.length) $box.append($sel);
            else $td.append($sel);
        }
        $sel.empty().append('<option value="Diamonds">Diamonds</option>');
        $sel.val('Diamonds');
        $sel.addClass('po-pc-loose-diamond-category-select');
    }

    function poPcMetalImageSrc($row) {
        return String($row.attr('data-metal-image') || '').trim();
    }

    function poPcMetalTabClass(metalName) {
        var n = String(metalName || '').toLowerCase();
        if (n.indexOf('diamond') >= 0 || n.indexOf('stone') >= 0) return 'metal-diamond';
        if (n.indexOf('platinum') >= 0) return 'metal-platinum';
        if (n.indexOf('silver') >= 0) return 'metal-silver';
        if (n.indexOf('gold') >= 0) return 'metal-gold';
        return 'metal-other';
    }

    function poPcMetalTabIcon(metalName) {
        var cls = poPcMetalTabClass(metalName);
        if (cls === 'metal-diamond') return '◆';
        if (cls === 'metal-other') return '●';
        return '▮';
    }

    function poPcBuildTabIconHtml(imgSrc, iconCls, metal) {
        if (imgSrc) {
            return '<span class="po-pc-tab-icon ' + iconCls + '"><img src="' + poPcEscAttr(imgSrc) + '" alt="' + poPcEscAttr(metal) + '"></span>';
        }
        return '<span class="po-pc-tab-icon ' + iconCls + '"><span class="po-pc-tab-fallback">' + poPcMetalTabIcon(metal) + '</span></span>';
    }

    function poPcShortMetalName(metalName) {
        var n = String(metalName || '').trim();
        if (n.length <= 14) return n.toUpperCase();
        if (/^diamond/i.test(n)) return 'DIAMOND';
        if (/^imitation/i.test(n)) return 'IMITATION';
        if (/^other/i.test(n)) return 'OTHER';
        return n.split(/\s+/)[0].toUpperCase();
    }

    function getMainPcRowMetalName($row) {
        if (!$row || !$row.length) return '';
        var $h = $row.find('td[data-col="metal"] input[type="hidden"][name*="[metal]"]');
        if (!$h.length) $h = $row.find('td[data-col="metal"] input[type="hidden"]').last();
        if ($h.length) return String($h.val() || '').trim();
        return String($row.find('td[data-col="metal"]').text() || '').trim();
    }

    function getMainPcRowMetalLabel($row) {
        if (!$row || !$row.length) return '';
        var $label = $row.find('.pc-metal-label');
        if ($label.length) return String($label.text() || '').trim();
        return getMainPcRowMetalName($row);
    }

    function reorderPoPcRowFields($row) {
        var placed = {};
        PO_PC_FIELD_ORDER.forEach(function (col) {
            var $td = $row.find('td[data-col="' + col + '"]');
            if ($td.length) {
                $row.append($td);
                placed[col] = true;
            }
        });
        $row.find('td[data-col]').each(function () {
            var c = String($(this).data('col') || '');
            if (!c || c === 'check' || c === 'metal' || placed[c]) return;
            $row.append(this);
        });
    }

    function wrapPoPcFieldBoxes($row) {
        $row.find('td[data-col]').each(function () {
            var col = String($(this).data('col') || '');
            if (!col || col === 'check' || col === 'metal') return;
            if (PO_PC_PRIMARY_COLS.indexOf(col) >= 0) {
                $(this).addClass('po-pc-field-primary');
            } else {
                $(this).addClass('po-pc-extra-field');
            }
            if ($(this).children('.po-pc-field-box').length) return;
            var $inner = $(this).contents().detach();
            $(this).empty().append($('<div class="po-pc-field-box"></div>').append($inner));
        });
    }

    function decoratePoPcRowFields($row) {
        var metal = getMainPcRowMetalName($row);
        $row.toggleClass('po-pc-is-diamond-metal', poPcIsDiamondMetal(metal));

        if (!$row.find('.po-pc-detail-title').length) {
            $row.prepend('<h4 class="po-pc-detail-title"></h4>');
        }
        $row.find('.po-pc-detail-title').text(metal + ' Details');

        var $checkTd = $row.find('td[data-col="check"]');
        if ($row.find('.po-pc-metal-toggle').length) {
            var $cb = $row.find('.po-pc-metal-toggle input[type="checkbox"]');
            if ($cb.length && $checkTd.length) {
                $checkTd.empty().append($cb);
            }
            $row.find('.po-pc-metal-toggle').remove();
        }

        poPcEnsureLooseDiamondCategorySelect($row, metal);

        $row.find('td[data-col]').each(function () {
            var col = String($(this).data('col') || '');
            if (!col || col === 'check' || col === 'metal') return;
            if ($(this).find('.po-pc-field-label').length) return;
            var label = PO_PC_FIELD_LABELS[col] || col;
            var req = PO_PC_REQUIRED_COLS.indexOf(col) >= 0 ? ' <span class="req">*</span>' : '';
            $(this).prepend('<label class="po-pc-field-label">' + label + req + '</label>');
        });

        reorderPoPcRowFields($row);
        wrapPoPcFieldBoxes($row);
        var $bdBox = $row.find('td[data-col="barcode-digits"] .po-pc-field-box');
        if ($bdBox.length && !$bdBox.find('.po-pc-field-hint').length) {
            $bdBox.append('<span class="po-pc-field-hint">Number of digits in barcode</span>');
        }
    }

    function getPoPcMetalCheck($row) {
        return $row.find('.po-pc-metal-toggle input[type="checkbox"], td[data-col="check"] input[type="checkbox"]').first();
    }

    function syncPoPcTabSelectionState() {
        var $n = $nav();
        $rows().each(function (i) {
            var checked = getPoPcMetalCheck($(this)).is(':checked');
            var $tab = $n.find('.po-pc-tab').eq(i);
            $tab.toggleClass('is-selected', checked);
            $tab.find('.po-pc-tab-checkbox').prop('checked', checked);
        });
    }

    function activatePoPcMetalTab(index) {
        var $r = $rows();
        if (!$r.length) return;
        index = Math.max(0, Math.min(index, $r.length - 1));
        var $row = $r.eq(index);
        $nav().find('.po-pc-tab').removeClass('active').eq(index).addClass('active');
        $r.removeClass('po-pc-row-active').eq(index).addClass('po-pc-row-active');
        decoratePoPcRowFields($row);
        syncPoPcTabSelectionState();
    }

    function setPoPcMetalSelected(index, selected) {
        var $r = $rows();
        if (!$r.length) return;
        index = Math.max(0, Math.min(index, $r.length - 1));
        var $check = getPoPcMetalCheck($r.eq(index));
        if (!$check.length) return;
        $check.prop('checked', !!selected);
        syncPoPcTabSelectionState();
    }

    function initProductModalPcMetalTabs() {
        var $modal = $root();
        if (!$modal.length) return;

        var $n = $nav();
        var $r = $rows();
        if (!$n.length || !$r.length) return;

        var prevActive = parseInt($n.find('.po-pc-tab.active').attr('data-row-idx'), 10);
        var activeIdx = isFinite(prevActive) ? prevActive : -1;

        $n.empty();
        $r.each(function (i) {
            var $row = $(this);
            var metal = getMainPcRowMetalName($row);
            var metalLabel = getMainPcRowMetalLabel($row);
            if (activeIdx < 0 && getPoPcMetalCheck($row).is(':checked')) {
                activeIdx = i;
            }
            var iconCls = poPcMetalTabClass(metal);
            var imgSrc = poPcMetalImageSrc($row);
            var isChecked = getPoPcMetalCheck($row).is(':checked');
            var $tab = $('<div class="po-pc-tab" role="button" tabindex="0"></div>');
            $tab.attr('data-row-idx', i);
            $tab.append(
                '<label class="po-pc-tab-check" title="Include this metal in product">' +
                '<input type="checkbox" class="po-pc-tab-checkbox"' + (isChecked ? ' checked' : '') +
                ' aria-label="Include ' + poPcEscAttr(metalLabel) + ' in product">' +
                '</label>'
            );
            $tab.append(poPcBuildTabIconHtml(imgSrc, iconCls, metal));
            $tab.append('<span class="po-pc-tab-name">' + $('<div>').text(poPcShortMetalName(metalLabel)).html() + '</span>');
            $tab.attr('title', metalLabel);
            $n.append($tab);
            decoratePoPcRowFields($row);
        });

        if (activeIdx < 0) activeIdx = 0;

        $n.off('click.poPcTabCheck').on('click.poPcTabCheck', '.po-pc-tab-checkbox, .po-pc-tab-check', function (e) {
            e.stopPropagation();
        });
        $n.off('change.poPcTabCheck').on('change.poPcTabCheck', '.po-pc-tab-checkbox', function (e) {
            e.stopPropagation();
            var idx = parseInt($(this).closest('.po-pc-tab').attr('data-row-idx'), 10) || 0;
            setPoPcMetalSelected(idx, this.checked);
            if (this.checked) {
                activatePoPcMetalTab(idx);
            }
        });
        $n.off('click.poPcTab').on('click.poPcTab', '.po-pc-tab', function (e) {
            if ($(e.target).closest('.po-pc-tab-check').length) return;
            activatePoPcMetalTab(parseInt($(this).attr('data-row-idx'), 10) || 0);
        });
        $n.off('keydown.poPcTab').on('keydown.poPcTab', '.po-pc-tab', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                if ($(e.target).closest('.po-pc-tab-check').length) return;
                e.preventDefault();
                activatePoPcMetalTab(parseInt($(this).attr('data-row-idx'), 10) || 0);
            }
        });

        $modal.off('change.poPcTabCheckHidden').on('change.poPcTabCheckHidden', 'td[data-col="check"] input[type="checkbox"]', syncPoPcTabSelectionState);
        $modal.off('change.poPcDiamondCategory').on('change.poPcDiamondCategory', '.po-pc-diamond-category-select', function () {
            var $row = $(this).closest('tr');
            if ($row.length) decoratePoPcRowFields($row);
        });

        if (!getPoPcMetalCheck($r.eq(activeIdx)).is(':checked')) {
            setPoPcMetalSelected(activeIdx, true);
        }
        activatePoPcMetalTab(activeIdx);
    }

    function syncProductBarcodeFields() {
        var $modal = $root();
        var enabled = $modal.find('#productIsBarcode').is(':checked');
        $modal.find('[data-col="barcode-digits"] input, [data-col="barcode-prefix"] input, [data-col="barcode"] input').prop('disabled', !enabled);
        if (!enabled) {
            $modal.find('[data-col="barcode"] input').val('');
        }
    }

    function updateProductModalTotalTaxPct() {
        var total = 0;
        $root().find('.po-tax-card tbody tr, .tax-table-wrapper tbody tr').each(function () {
            var $row = $(this);
            var $cb = $row.find('td:first input[type="checkbox"]');
            if ($cb.length && $cb.is(':checked')) {
                total += parseFloat($row.find('input[type="number"], input[name*="value"]').first().val()) || 0;
            }
        });
        $root().find('#productModalTotalTaxPct').text(total.toFixed(2) + '%');
    }

    function bindProductModalExtras() {
        var $modal = $root();
        if (!$modal.length) return;

        $modal.off('change' + NS + 'Barcode', '#productIsBarcode')
            .on('change' + NS + 'Barcode', '#productIsBarcode', syncProductBarcodeFields);

        $modal.off('change' + NS + 'Tax input' + NS + 'Tax', '.tax-table-wrapper input, .po-tax-card input')
            .on('change' + NS + 'Tax input' + NS + 'Tax', '.tax-table-wrapper input, .po-tax-card input', updateProductModalTotalTaxPct);

        syncProductBarcodeFields();
        updateProductModalTotalTaxPct();
    }

    function boot() {
        if (!$root().length) return;
        bindProductModalExtras();
        initProductModalPcMetalTabs();
    }

    $(function () {
        boot();
        $(document).on('shown.bs.modal' + NS, ROOT, function () {
            bindProductModalExtras();
            initProductModalPcMetalTabs();
            if (window.feather && typeof window.feather.replace === 'function') {
                try { window.feather.replace(); } catch (err) { /* ignore */ }
            }
        });
    });

    window.initProductModalPcMetalTabs = initProductModalPcMetalTabs;
    window.auragoldProductModalPcTabsBoot = boot;
})(window, window.jQuery);
