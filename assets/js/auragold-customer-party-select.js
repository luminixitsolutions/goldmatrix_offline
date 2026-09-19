/**
 * After Ledger Details save: select the new/edited party in #customerId Select2 (all voucher pages).
 */
(function () {
    'use strict';

    if (window.__auragoldCustomerPartySelectLoaded) {
        return;
    }
    window.__auragoldCustomerPartySelectLoaded = true;

    var pendingSaveData = null;

    function partySelectElement() {
        return document.getElementById('customerId');
    }

    function isPartySelect2Ready(el) {
        if (!el || el.tagName !== 'SELECT') {
            return true;
        }
        if (typeof window.jQuery === 'undefined' || !window.jQuery.fn || !window.jQuery.fn.select2) {
            return false;
        }
        return window.jQuery(el).hasClass('select2-hidden-accessible');
    }

    function partySelectionMatches(customerId) {
        var el = partySelectElement();
        if (!el) {
            return true;
        }
        var sid = String(parseInt(customerId, 10));
        if (el.tagName !== 'SELECT') {
            return String(el.value || '').trim() === sid;
        }
        if (typeof window.jQuery === 'undefined') {
            return String(el.value || '').trim() === sid;
        }
        return String(window.jQuery(el).val() || '').trim() === sid;
    }

    window.auragoldApplySavedCustomerToPartyDropdown = function (data, attempt) {
        attempt = attempt || 0;

        if (!data || !data.customer_id) {
            return;
        }
        if (window.auragoldVendorSaveTarget === 'product') {
            return;
        }

        var cid = data.customer_id;
        var cname = (data.customer_name || '').trim();
        var el = partySelectElement();

        if (!el) {
            return;
        }

        if (el.tagName === 'SELECT' && !isPartySelect2Ready(el) && attempt < 12) {
            setTimeout(function () {
                window.auragoldApplySavedCustomerToPartyDropdown(data, attempt + 1);
            }, 100);
            return;
        }

        if (typeof selectedCustomerId !== 'undefined') {
            selectedCustomerId = cid;
        }
        window.selectedCustomerId = cid;

        if (typeof window.setAuragoldPartyValue === 'function') {
            window.setAuragoldPartyValue(cid, cname);
        } else if (typeof window.setSaleInvoiceCustomerValue === 'function') {
            window.setSaleInvoiceCustomerValue(cid, cname);
        } else if (attempt < 12) {
            setTimeout(function () {
                window.auragoldApplySavedCustomerToPartyDropdown(data, attempt + 1);
            }, 100);
            return;
        } else {
            var cn = document.getElementById('customerName');
            if (cn && cname) {
                cn.value = cname;
            }
            var sid = String(parseInt(cid, 10));
            if (el.tagName === 'SELECT' && window.jQuery) {
                var $el = window.jQuery(el);
                if (!$el.find('option[value="' + sid + '"]').length) {
                    $el.append(new Option(cname || ('#' + sid), sid, true, true));
                }
                $el.val(sid).trigger('change');
            } else {
                el.value = cid;
                if (window.jQuery) {
                    window.jQuery(el).trigger('change');
                }
            }
        }

        if (el.tagName === 'SELECT' && !partySelectionMatches(cid) && attempt < 12) {
            setTimeout(function () {
                window.auragoldApplySavedCustomerToPartyDropdown(data, attempt + 1);
            }, 100);
            return;
        }

        if (typeof window.updateSaleInvoiceAddItemButtonState === 'function') {
            window.updateSaleInvoiceAddItemButtonState();
        }

        setTimeout(function () {
            if (typeof loadCustomerBalance === 'function') {
                loadCustomerBalance();
            }
            if (typeof window.auragoldSaleInvoiceRefreshGstForAllRows === 'function') {
                window.auragoldSaleInvoiceRefreshGstForAllRows();
            }
        }, 100);

        if (data.gstin != null && String(data.gstin).trim() !== '') {
            var cg = document.getElementById('customerGstin');
            if (cg) {
                cg.value = String(data.gstin).trim().toUpperCase();
            }
        }
    };

    function shouldAutoSelectFromSaveResponse(data) {
        if (!data || !data.customer_id) {
            return false;
        }
        if (data.status !== 'success' && data.success !== true) {
            return false;
        }
        if (window.auragoldVendorSaveTarget === 'product') {
            return false;
        }
        if (!partySelectElement()) {
            return false;
        }
        return true;
    }

    function scheduleApplyFromSaveResponse(data) {
        if (!shouldAutoSelectFromSaveResponse(data)) {
            return;
        }
        pendingSaveData = data;

        function runApply() {
            if (!pendingSaveData) {
                return;
            }
            var payload = pendingSaveData;
            pendingSaveData = null;
            window.auragoldApplySavedCustomerToPartyDropdown(payload);
        }

        if (typeof window.jQuery !== 'undefined') {
            var $modal = window.jQuery('#customerCreationModal');
            if ($modal.length && ($modal.hasClass('show') || $modal.is(':visible'))) {
                $modal.one('hidden.bs.modal.auragoldPartyApply', function () {
                    setTimeout(runApply, 80);
                });
                return;
            }
        }
        setTimeout(runApply, 200);
    }

    if (typeof window.fetch === 'function') {
        var origFetch = window.fetch;
        window.fetch = function (input, init) {
            var url = '';
            if (typeof input === 'string') {
                url = input;
            } else if (input && typeof input.url === 'string') {
                url = input.url;
            }
            var isCustomerSave = url.indexOf('customer-save.php') !== -1;
            var promise = origFetch.apply(this, arguments);
            if (!isCustomerSave) {
                return promise;
            }
            return promise.then(function (response) {
                try {
                    if (response && response.ok && typeof response.clone === 'function') {
                        response.clone().json().then(scheduleApplyFromSaveResponse).catch(function () {});
                    }
                } catch (e) { /* ignore */ }
                return response;
            });
        };
    }
})();
