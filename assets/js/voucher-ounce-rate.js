(function (window, $) {
    'use strict';

    var GRAM_PER_OZ = 31.1035;
    var OUNCE_METAL_RATE_DIVISOR = 32;
    var RATE_DECIMALS = 3;
    var LOSS_DECIMALS = 2;

    function formatDecimal(val, decimals) {
        if (val === '' || val == null) return '';
        var n = parseFloat(String(val).replace(/,/g, ''));
        if (isNaN(n)) return String(val).trim();
        var d = decimals == null ? RATE_DECIMALS : decimals;
        var s = n.toFixed(d);
        if (d > 0) {
            s = s.replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
        }
        return s;
    }

    function formatMetalDataForDisplay(data) {
        data = $.extend({}, data || {});
        if (data.us_oz != null && String(data.us_oz).trim() !== '') {
            data.us_oz = formatDecimal(data.us_oz, RATE_DECIMALS);
        }
        if (data.usd_rate != null && String(data.usd_rate).trim() !== '') {
            data.usd_rate = formatDecimal(data.usd_rate, RATE_DECIMALS);
        }
        if (data.at_gm != null && String(data.at_gm).trim() !== '') {
            data.at_gm = formatDecimal(data.at_gm, RATE_DECIMALS);
        }
        if (data.loss_rate != null && String(data.loss_rate).trim() !== '') {
            data.loss_rate = formatDecimal(data.loss_rate, LOSS_DECIMALS);
        }
        return data;
    }

    function prefixCfg(prefix) {
        prefix = prefix || 'si';
        return {
            prefix: prefix,
            enabled: document.getElementById(prefix + 'OunceRateEnabled'),
            display: document.getElementById(prefix + 'OunceRateDisplay'),
            json: document.getElementById(prefix + 'OunceRateJson'),
            modal: document.getElementById(prefix + 'OunceRateModal'),
            infoBtn: document.getElementById(prefix + 'OunceRateInfoBtn'),
            modalTitle: $('#' + prefix + 'OunceRateModal .si-ounce-modal-title')
        };
    }

    function getCurrencyRate() {
        var el = document.getElementById('currencyRate');
        var r = el ? parseFloat(el.value) : NaN;
        if (!isNaN(r) && r > 0) return r;
        return 1;
    }

    function updateCurSymbols() {
        var sym = '₹';
        var cur = document.getElementById('currency');
        if (cur && cur.options && cur.selectedIndex >= 0) {
            var t = (cur.options[cur.selectedIndex].text || '').trim();
            if (/^USD/i.test(t)) sym = '$';
            else if (/^AED/i.test(t)) sym = 'AED';
            else if (/^INR/i.test(t) || /₹/.test(t)) sym = '₹';
        }
        document.querySelectorAll('.si-oz-cur-symbol').forEach(function (n) {
            n.textContent = sym;
        });
    }

    function paneIdForMetal(prefix, metal) {
        return prefix + 'OzPane' + (metal === 'gold' ? 'Gold' : 'Silver');
    }

    function readMetalForm($modal, metal) {
        var prefix = ($modal.attr('id') || 'siOunceRateModal').replace('OunceRateModal', '');
        if (prefix === '') prefix = 'si';
        var $pane = $modal.find('#' + paneIdForMetal(prefix, metal));
        if (!$pane.length) {
            $pane = $modal.find('.tab-pane').eq(metal === 'gold' ? 0 : 1);
        }
        return {
            daily_date: ($pane.find('.si-oz-daily-date[data-metal="' + metal + '"]').val() || '').trim(),
            us_oz: ($pane.find('.si-oz-us-oz[data-metal="' + metal + '"]').val() || '').trim(),
            loss_rate: ($pane.find('.si-oz-loss[data-metal="' + metal + '"]').val() || '').trim(),
            usd_rate: ($pane.find('.si-oz-usd-rate[data-metal="' + metal + '"]').val() || '').trim(),
            at_gm: ($pane.find('.si-oz-at-gm[data-metal="' + metal + '"]').val() || '').trim()
        };
    }

    function writeMetalForm($modal, metal, data) {
        data = data || {};
        var prefix = ($modal.attr('id') || 'siOunceRateModal').replace('OunceRateModal', '');
        if (prefix === '') prefix = 'si';
        var $pane = $modal.find('#' + paneIdForMetal(prefix, metal));
        if (!$pane.length) {
            $pane = $modal.find('.tab-pane').eq(metal === 'gold' ? 0 : 1);
        }
        data = formatMetalDataForDisplay(data);
        $pane.find('.si-oz-daily-date[data-metal="' + metal + '"]').val(data.daily_date != null ? data.daily_date : '');
        $pane.find('.si-oz-us-oz[data-metal="' + metal + '"]').val(data.us_oz != null ? data.us_oz : '');
        $pane.find('.si-oz-loss[data-metal="' + metal + '"]').val(data.loss_rate != null ? data.loss_rate : '');
        $pane.find('.si-oz-usd-rate[data-metal="' + metal + '"]').val(data.usd_rate != null ? data.usd_rate : '');
        $pane.find('.si-oz-at-gm[data-metal="' + metal + '"]').val(data.at_gm != null ? data.at_gm : '');
    }

    function resolveAtGm(metalData) {
        var atGm = parseFloat(metalData && metalData.at_gm) || 0;
        if (atGm > 0) return atGm;
        return OUNCE_METAL_RATE_DIVISOR;
    }

    /** US$ Rate = US Gold / @ GM (default @ GM = 32). */
    function calcMetalDerived(metalData) {
        var oz = parseFloat(metalData.us_oz) || 0;
        if (oz <= 0) {
            return { usd_rate: '', at_gm: '' };
        }
        var atGm = resolveAtGm(metalData);
        var usdRate = oz / atGm;
        return {
            usd_rate: formatDecimal(usdRate, RATE_DECIMALS),
            at_gm: formatDecimal(atGm, RATE_DECIMALS)
        };
    }

    function ensureDerivedRates(data, preserveAtGm) {
        data = data || {};
        var oz = parseFloat(data.us_oz) || 0;
        if (oz <= 0) return data;
        var derived = calcMetalDerived(data);
        if (!preserveAtGm || !data.at_gm || String(data.at_gm).trim() === '') {
            data.at_gm = derived.at_gm;
        }
        data.usd_rate = formatDecimal(oz / resolveAtGm(data), RATE_DECIMALS);
        return data;
    }

    function dateKey(iso) {
        return String(iso || '').trim().substring(0, 10);
    }

    function ensureByDate(payload) {
        if (!payload.by_date || typeof payload.by_date !== 'object') {
            payload.by_date = {};
        }
        return payload.by_date;
    }

    function getCachedMetalForDate(payload, date, metal) {
        var dk = dateKey(date);
        if (!dk) return null;
        var bucket = ensureByDate(payload)[dk];
        if (!bucket || !bucket[metal] || typeof bucket[metal] !== 'object') return null;
        return bucket[metal];
    }

    function setCachedMetalForDate(payload, date, metal, data) {
        var dk = dateKey(date);
        if (!dk || !data) return;
        var byDate = ensureByDate(payload);
        if (!byDate[dk]) byDate[dk] = {};
        byDate[dk][metal] = $.extend({}, data);
    }

    function metalCacheHasValues(cached) {
        if (!cached || typeof cached !== 'object') return false;
        return ['us_oz', 'loss_rate', 'usd_rate', 'at_gm'].some(function (k) {
            return cached[k] != null && String(cached[k]).trim() !== '';
        });
    }

    function mergeMetalData(existing, incoming) {
        existing = existing || {};
        incoming = incoming || {};
        var out = $.extend({}, existing);
        ['daily_date', 'us_oz', 'loss_rate', 'usd_rate', 'at_gm'].forEach(function (k) {
            if (incoming[k] != null && String(incoming[k]).trim() !== '') {
                out[k] = incoming[k];
            }
        });
        if (incoming.daily_date) out.daily_date = incoming.daily_date;
        return out;
    }

    function applyMetalDataToForm($modal, metal, data, preserveAtGm) {
        data = data || {};
        if (!data.daily_date) {
            var $date = $modal.find('.si-oz-daily-date[data-metal="' + metal + '"]');
            if ($date.length) data.daily_date = $date.val() || '';
        }
        data = ensureDerivedRates(data, !!preserveAtGm);
        writeMetalForm($modal, metal, data);
    }

    function getInvoiceOrderDate() {
        var el = document.getElementById('orderDate');
        return el ? dateKey(el.value) : '';
    }

    function getSelectedCurrencyId() {
        var cur = document.getElementById('currency');
        if (!cur || cur.selectedIndex < 0) return 0;
        var opt = cur.options[cur.selectedIndex];
        return parseInt(opt.getAttribute('data-currency-id') || '0', 10) || 0;
    }

    function resolveMetalBlockForDate(payload, metal) {
        payload = payload || {};
        var block = payload[metal] || {};
        var dk = dateKey(block.daily_date) || getInvoiceOrderDate() || dateKey(new Date().toISOString());
        var cached = getCachedMetalForDate(payload, dk, metal);
        if (metalCacheHasValues(cached)) {
            return mergeMetalData({ daily_date: dk }, cached);
        }
        if (metalCacheHasValues(block) && dateKey(block.daily_date) === dk) {
            return mergeMetalData({ daily_date: dk }, block);
        }
        return { daily_date: dk };
    }

    function persistMetalRateToMaster(metal, data) {
        var oz = parseFloat(data.us_oz);
        if (isNaN(oz) || oz <= 0) return;
        var ids = window.SI_OUNCE_METAL_IDS || {};
        var metalId = ids[metal] || 0;
        if (!metalId) return;
        $.ajax({
            url: 'ajax/metal-exchange-rate.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'upsert_by_date',
                metal_id: metalId,
                rate_date: dateKey(data.daily_date),
                ounce_rate: oz,
                currency_id: getSelectedCurrencyId(),
                metal_rate_per_gram: data.at_gm || '',
                unit_conversion_rate: GRAM_PER_OZ,
                rate_at: 1
            }
        });
    }

    function fetchMetalRateForDate(prefix, metal, rateDate, done) {
        var finish = typeof done === 'function' ? done : function () {};
        var dk = dateKey(rateDate);
        if (!dk) {
            finish(null);
            return;
        }
        var ids = window.SI_OUNCE_METAL_IDS || {};
        var metalId = ids[metal] || 0;
        $.ajax({
            url: 'ajax/metal-exchange-rate.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'lookup',
                metal_id: metalId,
                rate_date: dk
            }
        }).done(function (res) {
            if (res && res.status === 'success') {
                finish(res);
            } else {
                finish(null);
            }
        }).fail(function () { finish(null); });
    }

    function loadMetalForDate(prefix, metal, rateDate, options) {
        options = options || {};
        var cfg = prefixCfg(prefix);
        var $modal = $('#' + prefix + 'OunceRateModal');
        if (!$modal.length) return;

        var dk = dateKey(rateDate);
        if (!dk) return;

        var payload = readPayload(cfg);
        var cached = getCachedMetalForDate(payload, dk, metal);
        if (metalCacheHasValues(cached)) {
            var cachedData = mergeMetalData({ daily_date: dk }, cached);
            applyMetalDataToForm($modal, metal, cachedData, !!(cachedData.at_gm && cachedData.us_oz));
            if (options.writePayload) {
                setCachedMetalForDate(payload, dk, metal, readMetalForm($modal, metal));
                if (metal === 'gold') payload.gold = readMetalForm($modal, 'gold');
                if (metal === 'silver') payload.silver = readMetalForm($modal, 'silver');
                writePayload(cfg, payload, true);
            }
            return;
        }

        var currentForm = readMetalForm($modal, metal);
        if (metalCacheHasValues(currentForm) && dateKey(currentForm.daily_date) === dk) {
            return;
        }

        fetchMetalRateForDate(prefix, metal, dk, function (res) {
            var data = { daily_date: dk };
            var hasServer = false;
            if (res && res.ounce_rate && parseFloat(res.ounce_rate) > 0) {
                data.us_oz = String(res.ounce_rate);
                hasServer = true;
            }
            if (res && res.metal_rate_per_gram && parseFloat(res.metal_rate_per_gram) > 0) {
                data.at_gm = String(parseFloat(res.metal_rate_per_gram).toFixed(3));
                hasServer = true;
            }
            if (!hasServer) {
                return;
            }
            applyMetalDataToForm($modal, metal, mergeMetalData(cached, data), !!(data.at_gm && data.us_oz));
            if (options.writePayload) {
                var p2 = readPayload(cfg);
                setCachedMetalForDate(p2, dk, metal, readMetalForm($modal, metal));
                if (metal === 'gold') p2.gold = readMetalForm($modal, 'gold');
                if (metal === 'silver') p2.silver = readMetalForm($modal, 'silver');
                writePayload(cfg, p2, true);
            }
        });
    }

    function notifyOunceRateChanged() {
        if (typeof window.auragoldRefreshModalRowsOunceMetalRate === 'function') {
            window.auragoldRefreshModalRowsOunceMetalRate();
        }
    }

    function readPayload(cfg) {
        if (!cfg.json) return { enabled: false, gold: {}, silver: {}, by_date: {} };
        try {
            var p = JSON.parse(cfg.json.value || '{}');
            if (!p || typeof p !== 'object') return { enabled: false, gold: {}, silver: {}, by_date: {} };
            p.gold = p.gold || {};
            p.silver = p.silver || {};
            ensureByDate(p);
            return p;
        } catch (e) {
            return { enabled: false, gold: {}, silver: {}, by_date: {} };
        }
    }

    function getOunceUsRateFromPayload(payload, metalKey) {
        payload = payload || {};
        var block = metalKey === 'silver' ? (payload.silver || {}) : (payload.gold || {});
        var usOz = parseFloat(block.us_oz);
        if (!isNaN(usOz) && usOz > 0) {
            return usOz;
        }
        return null;
    }

    function calcMetalRateFromUsOz(usOz) {
        var n = parseFloat(usOz);
        if (isNaN(n) || n <= 0) return null;
        return n / OUNCE_METAL_RATE_DIVISOR;
    }

    window.auragoldOunceRateIsActive = function (prefix) {
        var cfg = prefixCfg(prefix || 'si');
        return !!(cfg.enabled && cfg.enabled.checked);
    };

    window.auragoldOunceRateGetMetalRate = function (prefix, metalKey) {
        if (!window.auragoldOunceRateIsActive(prefix)) return null;
        var cfg = prefixCfg(prefix || 'si');
        var payload = readPayload(cfg);
        var usOz = getOunceUsRateFromPayload(payload, metalKey);
        if (usOz == null && metalKey !== 'silver') {
            var inline = parseFloat(getDisplayValue(cfg));
            if (!isNaN(inline) && inline > 0) usOz = inline;
        }
        return calcMetalRateFromUsOz(usOz);
    };

    function storedDisplayRate(payload) {
        payload = payload || {};
        var gold = payload.gold || {};
        var v = gold.us_oz;
        if (v !== undefined && v !== null && String(v).trim() !== '') {
            return formatDecimal(v, RATE_DECIMALS);
        }
        return '0';
    }

    function syncDisplayFromPayload(cfg) {
        var payload = readPayload(cfg);
        var on = !!(cfg.enabled && cfg.enabled.checked);
        setDisplayValue(cfg, on ? storedDisplayRate(payload) : '0');
    }

    function setDisplayValue(cfg, value) {
        if (!cfg.display) return;
        var v = value == null || value === '' ? '' : formatDecimal(value, RATE_DECIMALS);
        if (cfg.display.tagName === 'INPUT') {
            cfg.display.value = v;
        } else {
            cfg.display.textContent = v || '0';
        }
    }

    function getDisplayValue(cfg) {
        if (!cfg.display) return '';
        if (cfg.display.tagName === 'INPUT') {
            return String(cfg.display.value || '').trim();
        }
        return String(cfg.display.textContent || '').trim();
    }

    function writePayload(cfg, payload, skipDisplay) {
        if (!cfg.json) return;
        cfg.json.value = JSON.stringify(payload || {});
        if (!skipDisplay) {
            syncDisplayFromPayload(cfg);
        }
    }

    function syncInlineRate(cfg) {
        if (cfg.enabled && !cfg.enabled.checked) return;
        var formatted = formatDecimal(getDisplayValue(cfg), RATE_DECIMALS);
        setDisplayValue(cfg, formatted);
        var payload = readPayload(cfg);
        payload.gold = payload.gold || {};
        payload.gold.us_oz = formatted;
        writePayload(cfg, payload, true);
        notifyOunceRateChanged();
    }
    function syncInputEnabledState(cfg) {
        if (!cfg.display) return;
        var on = !!(cfg.enabled && cfg.enabled.checked);
        cfg.display.classList.toggle('si-ounce-rate-input-active', on);
        cfg.display.readOnly = !on;
    }

    function syncEnabled(cfg) {
        var payload = readPayload(cfg);
        var on = !!(cfg.enabled && cfg.enabled.checked);

        if (!on) {
            var current = getDisplayValue(cfg);
            if (current && current !== '0') {
                payload.gold = payload.gold || {};
                payload.gold.us_oz = current;
            }
        }

        payload.enabled = on;
        syncInputEnabledState(cfg);
        writePayload(cfg, payload, true);
        syncDisplayFromPayload(cfg);
        notifyOunceRateChanged();
    }
    function recalcModal($modal, metal) {
        var data = readMetalForm($modal, metal);
        var hadAtGm = parseFloat(data.at_gm) > 0;
        var derived = calcMetalDerived(data);
        var $pane = $modal.find('.tab-pane').eq(metal === 'gold' ? 0 : 1);
        $pane.find('.si-oz-usd-rate[data-metal="' + metal + '"]').val(derived.usd_rate);
        if (!hadAtGm) {
            $pane.find('.si-oz-at-gm[data-metal="' + metal + '"]').val(derived.at_gm);
        }
    }

    function loadModalFromPayload(prefix) {
        var cfg = prefixCfg(prefix);
        var $modal = $('#' + prefix + 'OunceRateModal');
        if (!$modal.length || !cfg.json) return;
        var payload = readPayload(cfg);
        updateCurSymbols();
        ['gold', 'silver'].forEach(function (m) {
            var resolved = resolveMetalBlockForDate(payload, m);
            if (metalCacheHasValues(resolved)) {
                applyMetalDataToForm($modal, m, resolved, !!(resolved.at_gm && resolved.us_oz));
            } else {
                writeMetalForm($modal, m, resolved);
                if (resolved.daily_date) {
                    loadMetalForDate(prefix, m, resolved.daily_date);
                }
            }
            var $dateEl = $modal.find('.si-oz-daily-date[data-metal="' + m + '"]');
            if ($dateEl.length) {
                $dateEl.data('prev-date', dateKey($dateEl.val()));
            }
        });
    }

    function saveModal(prefix) {
        prefix = prefix || 'si';
        var cfg = prefixCfg(prefix);
        var $modal = $('#' + prefix + 'OunceRateModal');
        if (!cfg.json) return;
        if (!$modal.length) return;

        var payload = readPayload(cfg);
        payload.gold = formatMetalDataForDisplay(ensureDerivedRates(readMetalForm($modal, 'gold'), true));
        payload.silver = formatMetalDataForDisplay(ensureDerivedRates(readMetalForm($modal, 'silver'), true));
        if (payload.gold.daily_date) {
            setCachedMetalForDate(payload, payload.gold.daily_date, 'gold', payload.gold);
        }
        if (payload.silver.daily_date) {
            setCachedMetalForDate(payload, payload.silver.daily_date, 'silver', payload.silver);
        }
        var goldOz = parseFloat(payload.gold.us_oz);
        if (!isNaN(goldOz) && goldOz > 0 && cfg.enabled && !cfg.enabled.checked) {
            cfg.enabled.checked = true;
        }
        payload.enabled = !!(cfg.enabled && cfg.enabled.checked);
        writePayload(cfg, payload);
        syncInputEnabledState(cfg);
        if (!isNaN(goldOz) && goldOz > 0) {
            persistMetalRateToMaster('gold', payload.gold);
        }
        var silverOz = parseFloat(payload.silver.us_oz);
        if (!isNaN(silverOz) && silverOz > 0) {
            persistMetalRateToMaster('silver', payload.silver);
        }
        $modal.modal('hide');
        notifyOunceRateChanged();
    }

    function openModal(prefix) {
        prefix = prefix || 'si';
        var cfg = prefixCfg(prefix);
        var $modal = $('#' + prefix + 'OunceRateModal');
        if (!$modal.length) return;
        if (!$modal.parent().is('body')) {
            $modal.appendTo('body');
        }
        loadModalFromPayload(prefix);
        var $goldTab = $modal.find('[href="#' + prefix + 'OzPaneGold"]');
        if ($goldTab.length && typeof $goldTab.tab === 'function') {
            $goldTab.tab('show');
        }
        cfg.modalTitle.text('Gold Ounce Rate');
        $modal.modal('show');
    }

    function refreshDashboard(prefix, metal) {
        var ids = window.SI_OUNCE_METAL_IDS || {};
        var metalId = ids[metal] || 0;
        var $modal = $('#' + prefix + 'OunceRateModal');
        var rateDate = $modal.find('.si-oz-daily-date[data-metal="' + metal + '"]').val() || '';
        $.ajax({
            url: 'ajax/metal-exchange-rate.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'lookup',
                metal_id: metalId,
                rate_date: dateKey(rateDate)
            }
        }).done(function (res) {
            if (res && res.status === 'success' && res.ounce_rate && parseFloat(res.ounce_rate) > 0) {
                var data = { us_oz: String(res.ounce_rate), daily_date: dateKey(rateDate) };
                if (res.metal_rate_per_gram && parseFloat(res.metal_rate_per_gram) > 0) {
                    data.at_gm = String(parseFloat(res.metal_rate_per_gram).toFixed(3));
                }
                applyMetalDataToForm($modal, metal, data);
            }
        });
    }

    function bind(prefix) {
        prefix = prefix || 'si';
        var cfg = prefixCfg(prefix);
        var $modal = $('#' + prefix + 'OunceRateModal');
        var infoSelector = '#' + prefix + 'OunceRateInfoBtn';
        var saveSelector = '#' + prefix + 'OunceRateModal .si-oz-save-btn';

        $(document).off('click.auragoldOunceRate', infoSelector).on('click.auragoldOunceRate', infoSelector, function (e) {
            e.preventDefault();
            e.stopPropagation();
            openModal(prefix);
        });

        $(document).off('click.auragoldOunceSave', saveSelector).on('click.auragoldOunceSave', saveSelector, function (e) {
            e.preventDefault();
            e.stopPropagation();
            var p = $(this).data('prefix') || prefix;
            saveModal(p);
        });

        if (cfg.infoBtn) {
            cfg.infoBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                openModal(prefix);
            });
        }

        if (cfg.enabled) {
            cfg.enabled.addEventListener('change', function () {
                syncEnabled(cfg);
            });
        }
        if (cfg.display) {
            cfg.display.addEventListener('input', function () {
                syncInlineRate(cfg);
            });
            cfg.display.addEventListener('change', function () {
                syncInlineRate(cfg);
            });
        }
        syncInputEnabledState(cfg);
        syncDisplayFromPayload(cfg);

        if (!$modal.length) return;

        $modal.off('shown.bs.tab.auragoldOunce', 'a[data-toggle="tab"]').on('shown.bs.tab.auragoldOunce', 'a[data-toggle="tab"]', function () {
            var target = $(this).attr('href') || '';
            var title = /Silver/i.test(target) ? 'Silver Ounce Rate' : 'Gold Ounce Rate';
            cfg.modalTitle.text(title);
        });

        $modal.off('input.auragoldOunce change.auragoldOunce', '.si-oz-us-oz, .si-oz-loss, .si-oz-at-gm').on('input.auragoldOunce change.auragoldOunce', '.si-oz-us-oz, .si-oz-loss, .si-oz-at-gm', function () {
            recalcModal($modal, $(this).data('metal'));
        });

        $modal.off('focusin.auragoldOunceDate', '.si-oz-daily-date').on('focusin.auragoldOunceDate', '.si-oz-daily-date', function () {
            $(this).data('prev-date', dateKey($(this).val()));
        });

        $modal.off('change.auragoldOunceDate', '.si-oz-daily-date').on('change.auragoldOunceDate', '.si-oz-daily-date', function () {
            var metal = $(this).data('metal');
            var newDate = dateKey($(this).val());
            var prevDate = dateKey($(this).data('prev-date'));
            if (prevDate && prevDate !== newDate) {
                var stash = readMetalForm($modal, metal);
                stash.daily_date = prevDate;
                var stashPayload = readPayload(cfg);
                setCachedMetalForDate(stashPayload, prevDate, metal, stash);
                writePayload(cfg, stashPayload, true);
            }
            loadMetalForDate(prefix, metal, newDate);
            $(this).data('prev-date', newDate);
        });

        $modal.off('click.auragoldOunce', '.si-oz-today-btn').on('click.auragoldOunce', '.si-oz-today-btn', function () {
            var metal = $(this).data('metal');
            var d = new Date();
            var iso = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            $modal.find('.si-oz-daily-date[data-metal="' + metal + '"]').val(iso);
            loadMetalForDate(prefix, metal, iso);
        });

        $modal.off('click.auragoldOunce', '.si-oz-refresh-btn').on('click.auragoldOunce', '.si-oz-refresh-btn', function () {
            refreshDashboard(prefix, $(this).data('metal'));
        });

        $(document).off('change.auragoldOunceCur input.auragoldOunceCur', '#currency, #currencyRate').on('change.auragoldOunceCur input.auragoldOunceCur', '#currency, #currencyRate', updateCurSymbols);
        updateCurSymbols();
    }

    window.auragoldOunceRateGetPayload = function (prefix) {
        var cfg = prefixCfg(prefix || 'si');
        if (cfg.enabled && cfg.enabled.checked) {
            syncInlineRate(cfg);
        }
        var payload = readPayload(cfg);
        payload.enabled = !!(cfg.enabled && cfg.enabled.checked);
        return payload;
    };

    window.auragoldOunceRateApplyPayload = function (prefix, payload) {
        var cfg = prefixCfg(prefix || 'si');
        payload = payload || {};
        if (cfg.enabled) cfg.enabled.checked = !!payload.enabled;
        writePayload(cfg, payload, true);
        syncInputEnabledState(cfg);
        syncDisplayFromPayload(cfg);
        notifyOunceRateChanged();
    };

    window.auragoldInitOunceRate = function (prefix) {
        bind(prefix || 'si');
    };

    $(function () {
        if (document.getElementById('siOunceRateInfoBtn') || document.getElementById('siOunceRateEnabled')) {
            window.auragoldInitOunceRate('si');
        }
    });
}(window, window.jQuery));
