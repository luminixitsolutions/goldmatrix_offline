(function () {
    'use strict';

    function ajaxBase() {
        var s = document.querySelector('script[src*="customer-ledger-address.js"]');
        if (s && s.src) {
            var u = s.src.replace(/[^/]+$/, '');
            return u.replace(/js\/?$/, '');
        }
        return '';
    }

    function fetchJson(url) {
        return fetch(url, { method: 'GET', credentials: 'same-origin' }).then(function (r) {
            return r.json();
        });
    }

    function fillSelectState(sel, placeholder, rows, selectedName) {
        if (!sel) return;
        sel.innerHTML = '';
        var ph = document.createElement('option');
        ph.value = '';
        ph.textContent = placeholder;
        sel.appendChild(ph);
        (rows || []).forEach(function (row) {
            var o = document.createElement('option');
            o.value = row.name;
            o.textContent = row.name;
            o.setAttribute('data-state-id', String(row.id));
            sel.appendChild(o);
        });
        if (selectedName) {
            sel.value = selectedName;
            if (sel.value !== selectedName) {
                var o2 = document.createElement('option');
                o2.value = selectedName;
                o2.textContent = selectedName;
                o2.setAttribute('data-state-id', '');
                sel.appendChild(o2);
                sel.value = selectedName;
            }
        }
    }

    function fillSelectCity(sel, placeholder, rows, selectedName) {
        if (!sel) return;
        sel.innerHTML = '';
        var ph = document.createElement('option');
        ph.value = '';
        ph.textContent = placeholder;
        sel.appendChild(ph);
        (rows || []).forEach(function (row) {
            var o = document.createElement('option');
            o.value = row.name;
            o.textContent = row.name;
            o.setAttribute('data-city-id', String(row.id));
            sel.appendChild(o);
        });
        if (selectedName) {
            sel.value = selectedName;
            if (sel.value !== selectedName) {
                var o2 = document.createElement('option');
                o2.value = selectedName;
                o2.textContent = selectedName;
                sel.appendChild(o2);
                sel.value = selectedName;
            }
        }
    }

    function getSelectedCountryId(countrySelect) {
        if (!countrySelect || countrySelect.selectedIndex < 0) return 0;
        var opt = countrySelect.options[countrySelect.selectedIndex];
        if (!opt || !opt.value) return 0;
        var id = opt.getAttribute('data-country-id');
        if (id) return parseInt(id, 10);
        var v = parseInt(opt.value, 10);
        return v > 0 ? v : 0;
    }

    function getSelectedStateId(stateSelect) {
        if (!stateSelect || stateSelect.selectedIndex < 0) return 0;
        var opt = stateSelect.options[stateSelect.selectedIndex];
        if (!opt || !opt.value) return 0;
        var id = opt.getAttribute('data-state-id');
        if (id) return parseInt(id, 10);
        var v = parseInt(opt.value, 10);
        return v > 0 ? v : 0;
    }

    function fillLedgerStateSelect(sel, placeholder, rows, selectedId) {
        if (!sel) return;
        sel.innerHTML = '';
        var ph = document.createElement('option');
        ph.value = '';
        ph.textContent = placeholder;
        sel.appendChild(ph);
        (rows || []).forEach(function (row) {
            var o = document.createElement('option');
            o.value = String(row.id);
            o.textContent = row.name;
            o.setAttribute('data-state-id', String(row.id));
            sel.appendChild(o);
        });
        if (selectedId) {
            sel.value = String(selectedId);
        }
    }

    function fillLedgerCitySelect(sel, placeholder, rows, selectedId) {
        if (!sel) return;
        sel.innerHTML = '';
        var ph = document.createElement('option');
        ph.value = '';
        ph.textContent = placeholder;
        sel.appendChild(ph);
        (rows || []).forEach(function (row) {
            var o = document.createElement('option');
            o.value = String(row.id);
            o.textContent = row.name;
            sel.appendChild(o);
        });
        if (selectedId) {
            sel.value = String(selectedId);
        }
    }

    function loadLedgerHeaderStates(countrySelect, stateSelect, citySelect, selectedStateId, selectedCityId) {
        var cid = getSelectedCountryId(countrySelect);
        var base = ajaxBase();
        if (!cid) {
            fillLedgerStateSelect(stateSelect, 'Select State', [], '');
            fillLedgerCitySelect(citySelect, 'Select City', [], '');
            return Promise.resolve();
        }
        return fetchJson(base + 'ajax/get-states-by-country.php?country_id=' + encodeURIComponent(cid))
            .then(function (data) {
                var rows = (data && data.states) ? data.states : [];
                fillLedgerStateSelect(stateSelect, 'Select State', rows, selectedStateId || '');
                var sid = getSelectedStateId(stateSelect);
                if (sid) {
                    return loadLedgerHeaderCities(stateSelect, citySelect, selectedCityId || '');
                }
                fillLedgerCitySelect(citySelect, 'Select City', [], selectedCityId || '');
            })
            .catch(function () {
                fillLedgerStateSelect(stateSelect, 'Select State', [], '');
                fillLedgerCitySelect(citySelect, 'Select City', [], '');
            });
    }

    function loadLedgerHeaderCities(stateSelect, citySelect, selectedCityId) {
        var sid = getSelectedStateId(stateSelect);
        var base = ajaxBase();
        if (!sid) {
            fillLedgerCitySelect(citySelect, 'Select City', [], '');
            return Promise.resolve();
        }
        return fetchJson(base + 'ajax/get-cities-by-state.php?state_id=' + encodeURIComponent(sid))
            .then(function (data) {
                var rows = (data && data.cities) ? data.cities : [];
                fillLedgerCitySelect(citySelect, 'Select City', rows, selectedCityId || '');
            })
            .catch(function () {
                fillLedgerCitySelect(citySelect, 'Select City', [], '');
            });
    }

    function bindLedgerHeader(countrySel, stateSel, citySel) {
        if (!countrySel || !stateSel || !citySel) return;
        countrySel.addEventListener('change', function () {
            loadLedgerHeaderStates(countrySel, stateSel, citySel, '', '');
        });
        stateSel.addEventListener('change', function () {
            loadLedgerHeaderCities(stateSel, citySel, '');
        });
    }

    function loadStates(countrySelect, stateSelect, citySelect, selectedStateName, selectedCityName) {
        var cid = getSelectedCountryId(countrySelect);
        var base = ajaxBase();
        if (!cid) {
            fillSelectState(stateSelect, 'Select State', [], '');
            fillSelectCity(citySelect, 'Select City', [], '');
            return Promise.resolve();
        }
        return fetchJson(base + 'ajax/get-states-by-country.php?country_id=' + encodeURIComponent(cid))
            .then(function (data) {
                var rows = (data && data.states) ? data.states : [];
                fillSelectState(stateSelect, 'Select State', rows, selectedStateName || '');
                var sid = getSelectedStateId(stateSelect);
                if (sid) {
                    return loadCities(stateSelect, citySelect, selectedCityName || '');
                }
                fillSelectCity(citySelect, 'Select City', [], selectedCityName || '');
            })
            .catch(function () {
                fillSelectState(stateSelect, 'Select State', [], '');
                fillSelectCity(citySelect, 'Select City', [], '');
            });
    }

    function loadCities(stateSelect, citySelect, selectedCityName) {
        var sid = getSelectedStateId(stateSelect);
        var base = ajaxBase();
        if (!sid) {
            fillSelectCity(citySelect, 'Select City', [], '');
            return Promise.resolve();
        }
        return fetchJson(base + 'ajax/get-cities-by-state.php?state_id=' + encodeURIComponent(sid))
            .then(function (data) {
                var rows = (data && data.cities) ? data.cities : [];
                fillSelectCity(citySelect, 'Select City', rows, selectedCityName || '');
            })
            .catch(function () {
                fillSelectCity(citySelect, 'Select City', [], '');
            });
    }

    function bindPair(countrySel, stateSel, citySel) {
        if (!countrySel || !stateSel || !citySel) return;
        countrySel.addEventListener('change', function () {
            loadStates(countrySel, stateSel, citySel, '', '');
        });
        stateSel.addEventListener('change', function () {
            loadCities(stateSel, citySel, '');
        });
    }

    function bindCascadePairOnce(countrySel, stateSel, citySel, bindFn) {
        if (!countrySel || !stateSel || !citySel || countrySel.getAttribute('data-ledger-cascade-bound') === '1') {
            return false;
        }
        bindFn(countrySel, stateSel, citySel);
        countrySel.setAttribute('data-ledger-cascade-bound', '1');
        return true;
    }

    function initCascades() {
        ensureAddCityModal();
        bindCascadePairOnce(
            document.getElementById('billingCountry'),
            document.getElementById('billingState'),
            document.getElementById('billingCity'),
            bindPair
        );
        bindCascadePairOnce(
            document.getElementById('shippingCountry'),
            document.getElementById('shippingState'),
            document.getElementById('shippingCity'),
            bindPair
        );
        bindCascadePairOnce(
            document.getElementById('country'),
            document.getElementById('ledgerState'),
            document.getElementById('ledgerCity'),
            bindLedgerHeader
        );
    }

    function scheduleCascadeInitRetries() {
        if (window._ledgerAddrCascadeRetryScheduled) {
            return;
        }
        window._ledgerAddrCascadeRetryScheduled = true;
        var tries = 0;
        var iv = setInterval(function () {
            initCascades();
            tries++;
            var billingReady = !document.getElementById('billingCountry') ||
                document.getElementById('billingCountry').getAttribute('data-ledger-cascade-bound') === '1';
            var ledgerReady = !document.getElementById('country') ||
                document.getElementById('country').getAttribute('data-ledger-cascade-bound') === '1';
            if ((billingReady && ledgerReady) || tries > 80) {
                clearInterval(iv);
            }
        }, 250);
    }

    function resetAddressDropdowns() {
        ['billingState', 'billingCity', 'shippingState', 'shippingCity'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            if (id.indexOf('State') >= 0) {
                el.innerHTML = '<option value="">Select State</option>';
            } else {
                el.innerHTML = '<option value="">Select City</option>';
            }
        });
    }

    function resetLedgerHeaderDropdowns() {
        var ls = document.getElementById('ledgerState');
        var lci = document.getElementById('ledgerCity');
        if (ls) {
            ls.innerHTML = '<option value="">Select State</option>';
        }
        if (lci) {
            lci.innerHTML = '<option value="">Select City</option>';
        }
        var pcc = document.getElementById('phoneCountryCode');
        if (pcc) {
            pcc.value = '971';
        }
    }

    window.prefillLedgerHeaderAsync = function (c) {
        if (!c) return Promise.resolve();
        var pcc = document.getElementById('phoneCountryCode');
        if (pcc) {
            pcc.value = (c.phone_country_code != null && String(c.phone_country_code) !== '') ? String(c.phone_country_code) : '971';
        }
        var lc = document.getElementById('country');
        var ls = document.getElementById('ledgerState');
        var lci = document.getElementById('ledgerCity');
        if (!lc || !ls || !lci) return Promise.resolve();
        if (c.country_id) {
            lc.value = String(c.country_id);
        }
        var sid = (c.ledger_state_id != null && parseInt(c.ledger_state_id, 10) > 0) ? parseInt(c.ledger_state_id, 10) : '';
        var cid = (c.ledger_city_id != null && parseInt(c.ledger_city_id, 10) > 0) ? parseInt(c.ledger_city_id, 10) : '';
        return loadLedgerHeaderStates(lc, ls, lci, sid, cid);
    };

    window.applyLedgerHeaderDefaultsFromProfile = function (d) {
        if (!d || !d.ok || d.empty) return Promise.resolve();
        var pcc = document.getElementById('phoneCountryCode');
        if (pcc && d.profile_phone_country_code) {
            pcc.value = String(d.profile_phone_country_code);
        }
        var mcc = document.getElementById('mobileCountryCode');
        if (mcc && d.profile_phone_country_code) {
            mcc.value = String(d.profile_phone_country_code);
        }
        var lc = document.getElementById('country');
        var ls = document.getElementById('ledgerState');
        var lci = document.getElementById('ledgerCity');
        if (!lc || !ls || !lci) return Promise.resolve();
        if (d.profile_country_id > 0) {
            lc.value = String(d.profile_country_id);
        }
        return loadLedgerHeaderStates(lc, ls, lci,
            d.profile_state_id > 0 ? d.profile_state_id : '',
            d.profile_city_id > 0 ? d.profile_city_id : '');
    };

    /**
     * Prefill billing + shipping country/state/city from branch shop profile (my-profile).
     * Dropdowns use country name as value; state/city names come from the API.
     */
    window.applyBillingShippingDefaultsFromProfile = function (d) {
        if (!d || !d.ok || d.empty) return Promise.resolve();
        if (!(d.profile_country_id > 0) && !(d.profile_country_name && String(d.profile_country_name).trim() !== '')) {
            return Promise.resolve();
        }
        function pickCountryOption(countrySel) {
            if (!countrySel) return;
            if (d.profile_country_id > 0) {
                for (var i = 0; i < countrySel.options.length; i++) {
                    var o = countrySel.options[i];
                    var did = o.getAttribute('data-country-id');
                    if (did && parseInt(did, 10) === d.profile_country_id) {
                        countrySel.value = o.value;
                        return;
                    }
                }
            }
            if (d.profile_country_name) {
                countrySel.value = d.profile_country_name;
            }
        }
        function fillTriple(countrySel, stateSel, citySel) {
            if (!countrySel || !stateSel || !citySel) return Promise.resolve();
            pickCountryOption(countrySel);
            var sn = (d.profile_state_name != null) ? String(d.profile_state_name) : '';
            var cn = (d.profile_city_name != null) ? String(d.profile_city_name) : '';
            return loadStates(countrySel, stateSel, citySel, sn, cn);
        }
        var bc = document.getElementById('billingCountry');
        var bs = document.getElementById('billingState');
        var bci = document.getElementById('billingCity');
        var sc = document.getElementById('shippingCountry');
        var ss = document.getElementById('shippingState');
        var sci = document.getElementById('shippingCity');
        return Promise.all([
            fillTriple(bc, bs, bci),
            fillTriple(sc, ss, sci),
        ]);
    };

    window.prefillCustomerLedgerAddressesAsync = function (c) {
        if (!c) return Promise.resolve();
        var bc = document.getElementById('billingCountry');
        var bs = document.getElementById('billingState');
        var bci = document.getElementById('billingCity');
        var sc = document.getElementById('shippingCountry');
        var ss = document.getElementById('shippingState');
        var sci = document.getElementById('shippingCity');

        var p1 = Promise.resolve();
        if (bc && bs && bci) {
            if (c.billing_country) {
                bc.value = c.billing_country;
            }
            p1 = loadStates(bc, bs, bci, c.billing_state || '', c.billing_city || '');
        }

        var p2 = Promise.resolve();
        if (sc && ss && sci) {
            if (c.shipping_country) {
                sc.value = c.shipping_country;
            }
            p2 = loadStates(sc, ss, sci, c.shipping_state || '', c.shipping_city || '');
        }

        return Promise.all([p1, p2]);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initCascades();
            scheduleCascadeInitRetries();
        });
    } else {
        initCascades();
        scheduleCascadeInitRetries();
    }

    var form = document.getElementById('customerCreationForm');
    if (form) {
        form.addEventListener('reset', function () {
            setTimeout(resetAddressDropdowns, 0);
        });
    }

    function patchFill() {
        if (typeof window.fillCustomerForm !== 'function') {
            return;
        }
        if (window.fillCustomerForm._ledgerAddrWrapped) {
            return;
        }
        var orig = window.fillCustomerForm;
        var wrapped = function (c) {
            orig(c);
            if (typeof window.prefillCustomerLedgerAddressesAsync === 'function') {
                window.prefillCustomerLedgerAddressesAsync(c);
            }
            if (typeof window.prefillLedgerHeaderAsync === 'function') {
                window.prefillLedgerHeaderAsync(c);
            }
        };
        wrapped._ledgerAddrWrapped = true;
        window.fillCustomerForm = wrapped;
    }

    function patchClear() {
        if (window._ledgerAddrClearPatched || typeof window.clearCustomerForm !== 'function') {
            return;
        }
        var orig = window.clearCustomerForm;
        window.clearCustomerForm = function () {
            orig();
            resetAddressDropdowns();
            resetLedgerHeaderDropdowns();
        };
        window._ledgerAddrClearPatched = true;
    }

    var tries = 0;
    var iv = setInterval(function () {
        patchFill();
        patchClear();
        tries++;
        if (window._ledgerAddrClearPatched && window.fillCustomerForm && window.fillCustomerForm._ledgerAddrWrapped) {
            clearInterval(iv);
        }
        if (tries > 80) {
            clearInterval(iv);
        }
    }, 250);

    /* ---- Add city (under selected state) ---- */
    var addCityContext = null;

    function ensureAddCityModal() {
        if (document.getElementById('addCityModal')) {
            return;
        }
        var html =
            '<div class="modal fade" id="addCityModal" tabindex="-1" role="dialog" aria-hidden="true">' +
            '  <div class="modal-dialog modal-dialog-centered" role="document">' +
            '    <div class="modal-content" style="border-radius: 8px;">' +
            '      <div class="modal-header" style="background: #11294b; color: #fff; border: none;">' +
            '        <h5 class="modal-title">Add City</h5>' +
            '        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>' +
            '      </div>' +
            '      <div class="modal-body">' +
            '        <div class="form-group">' +
            '          <label>Country <span class="text-danger">*</span></label>' +
            '          <select class="form-control" id="addCityCountry"></select>' +
            '        </div>' +
            '        <div class="form-group">' +
            '          <label>State <span class="text-danger">*</span></label>' +
            '          <select class="form-control" id="addCityState"><option value="">Select State</option></select>' +
            '        </div>' +
            '        <div class="form-group d-flex flex-wrap align-items-end">' +
            '          <div style="flex: 1; min-width: 120px;">' +
            '            <label>Name <span class="text-danger">*</span></label>' +
            '            <input type="text" class="form-control" id="addCityName" maxlength="255" placeholder="City name">' +
            '          </div>' +
            '          <div class="form-check ml-3 mt-3 mt-md-0">' +
            '            <input class="form-check-input" type="checkbox" id="addCityActive" checked>' +
            '            <label class="form-check-label" for="addCityActive">Active</label>' +
            '          </div>' +
            '        </div>' +
            '        <div class="form-group">' +
            '          <label>Comment</label>' +
            '          <textarea class="form-control" id="addCityComment" rows="2" placeholder="Optional"></textarea>' +
            '        </div>' +
            '      </div>' +
            '      <div class="modal-footer" style="border-top: 1px solid #e2e8f0;">' +
            '        <button type="button" class="btn btn-outline-secondary btn-sm" id="addCityClearBtn">Clear</button>' +
            '        <button type="button" class="btn btn-primary btn-sm" id="addCitySaveBtn" style="background: #11294b; border: none;">Save</button>' +
            '      </div>' +
            '    </div>' +
            '  </div>' +
            '</div>';
        var wrap = document.createElement('div');
        wrap.innerHTML = html;
        document.body.appendChild(wrap.firstElementChild);

        var countryEl = document.getElementById('addCityCountry');
        var stateEl = document.getElementById('addCityState');
        if (countryEl) {
            countryEl.addEventListener('change', function () {
                var cid = getSelectedCountryId(countryEl);
                var base = ajaxBase();
                if (!cid) {
                    fillSelectState(stateEl, 'Select State', [], '');
                    return;
                }
                fetchJson(base + 'ajax/get-states-by-country.php?country_id=' + encodeURIComponent(cid))
                    .then(function (data) {
                        var rows = (data && data.states) ? data.states : [];
                        fillSelectState(stateEl, 'Select State', rows, '');
                    })
                    .catch(function () {
                        fillSelectState(stateEl, 'Select State', [], '');
                    });
            });
        }

        var clearBtn = document.getElementById('addCityClearBtn');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                var n = document.getElementById('addCityName');
                var c = document.getElementById('addCityComment');
                var a = document.getElementById('addCityActive');
                if (n) {
                    n.value = '';
                }
                if (c) {
                    c.value = '';
                }
                if (a) {
                    a.checked = true;
                }
            });
        }

        var saveBtn = document.getElementById('addCitySaveBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', submitAddCity);
        }

        if (window.jQuery) {
            window.jQuery('#addCityModal').on('hidden.bs.modal', function () {
                if (window.jQuery('.modal.show').length) {
                    window.jQuery('body').addClass('modal-open');
                }
            });
        }
    }

    function cloneCountryOptions(sourceSelect, destSelect) {
        if (!sourceSelect || !destSelect) {
            return;
        }
        destSelect.innerHTML = '';
        for (var i = 0; i < sourceSelect.options.length; i++) {
            var o = sourceSelect.options[i];
            var n = document.createElement('option');
            n.value = o.value;
            n.textContent = o.textContent;
            var did = o.getAttribute('data-country-id');
            if (did) {
                n.setAttribute('data-country-id', did);
            }
            destSelect.appendChild(n);
        }
    }

    function openAddCityModal(prefix) {
        ensureAddCityModal();
        var countrySel = document.getElementById(prefix === 'billing' ? 'billingCountry' : 'shippingCountry');
        var stateSel = document.getElementById(prefix === 'billing' ? 'billingState' : 'shippingState');
        var citySel = document.getElementById(prefix === 'billing' ? 'billingCity' : 'shippingCity');
        if (!countrySel || !stateSel || !citySel) {
            return;
        }
        if (!getSelectedCountryId(countrySel)) {
            alert('Please select a country first.');
            return;
        }
        if (!getSelectedStateId(stateSel)) {
            alert('Please select a state first. New cities are saved under the selected state.');
            return;
        }

        addCityContext = {
            prefix: prefix,
            stateSelectId: stateSel.id,
            citySelectId: citySel.id,
        };

        var modalCountry = document.getElementById('addCityCountry');
        var modalState = document.getElementById('addCityState');
        cloneCountryOptions(countrySel, modalCountry);
        if (modalCountry) {
            modalCountry.value = countrySel.value;
        }

        var selectedStateName = stateSel.value || '';
        var cid = getSelectedCountryId(modalCountry);
        var base = ajaxBase();
        fetchJson(base + 'ajax/get-states-by-country.php?country_id=' + encodeURIComponent(cid))
            .then(function (data) {
                var rows = (data && data.states) ? data.states : [];
                fillSelectState(modalState, 'Select State', rows, selectedStateName);
                var nameEl = document.getElementById('addCityName');
                var comEl = document.getElementById('addCityComment');
                var actEl = document.getElementById('addCityActive');
                if (nameEl) {
                    nameEl.value = '';
                }
                if (comEl) {
                    comEl.value = '';
                }
                if (actEl) {
                    actEl.checked = true;
                }
                if (window.jQuery) {
                    window.jQuery('#addCityModal').modal('show');
                }
            })
            .catch(function () {
                alert('Could not load states.');
            });
    }

    function submitAddCity() {
        var modalState = document.getElementById('addCityState');
        var nameEl = document.getElementById('addCityName');
        var comEl = document.getElementById('addCityComment');
        var actEl = document.getElementById('addCityActive');
        if (!addCityContext || !modalState || !nameEl) {
            return;
        }
        var sid = getSelectedStateId(modalState);
        var nm = (nameEl.value || '').trim();
        if (!sid) {
            alert('Please select a state.');
            return;
        }
        if (!nm) {
            alert('Please enter the city name.');
            nameEl.focus();
            return;
        }

        var fd = new FormData();
        fd.append('state_id', String(sid));
        fd.append('name', nm);
        fd.append('comment', (comEl && comEl.value) ? comEl.value : '');
        fd.append('active', actEl && actEl.checked ? '1' : '0');

        var base = ajaxBase();
        var saveBtn = document.getElementById('addCitySaveBtn');
        if (saveBtn) {
            saveBtn.disabled = true;
        }
        fetch(base + 'ajax/save-city.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                if (saveBtn) {
                    saveBtn.disabled = false;
                }
                if (!data || !data.success) {
                    alert((data && data.message) ? data.message : 'Could not save city.');
                    return;
                }
                var ctx = addCityContext;
                var stateSel = document.getElementById(ctx.stateSelectId);
                var citySel = document.getElementById(ctx.citySelectId);
                var countrySel = document.getElementById(ctx.prefix === 'billing' ? 'billingCountry' : 'shippingCountry');
                var modalCountry = document.getElementById('addCityCountry');
                var modalState = document.getElementById('addCityState');
                if (stateSel && citySel && countrySel && modalCountry && modalState && data.city && data.city.name) {
                    if (modalCountry.value !== countrySel.value) {
                        countrySel.value = modalCountry.value;
                    }
                    var stateName = '';
                    var so = modalState.options[modalState.selectedIndex];
                    if (so) {
                        stateName = so.value || '';
                    }
                    loadStates(countrySel, stateSel, citySel, stateName, data.city.name);
                }
                if (window.jQuery) {
                    window.jQuery('#addCityModal').modal('hide');
                }
                addCityContext = null;
            })
            .catch(function () {
                if (saveBtn) {
                    saveBtn.disabled = false;
                }
                alert('Network error while saving city.');
            });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.city-add-btn');
        if (!btn) {
            return;
        }
        var target = btn.getAttribute('data-target');
        if (target !== 'billing' && target !== 'shipping') {
            return;
        }
        e.preventDefault();
        openAddCityModal(target);
    });

    function patchOpenCustomerModalMode() {
        if (typeof window.openCustomerModalForAdd === 'function' && !window._ledgerOpenAddPatched) {
            var oa = window.openCustomerModalForAdd;
            window.openCustomerModalForAdd = function () {
                window._ledgerCustomerModalMode = 'add';
                return oa.apply(this, arguments);
            };
            window._ledgerOpenAddPatched = true;
        }
        if (typeof window.openCustomerModalForEdit === 'function' && !window._ledgerOpenEditPatched) {
            var oe = window.openCustomerModalForEdit;
            window.openCustomerModalForEdit = function () {
                window._ledgerCustomerModalMode = 'edit';
                return oe.apply(this, arguments);
            };
            window._ledgerOpenEditPatched = true;
        }
    }

    var triesOpen = 0;
    var ivOpen = setInterval(function () {
        patchOpenCustomerModalMode();
        triesOpen++;
        if (triesOpen > 80) {
            clearInterval(ivOpen);
        }
    }, 250);

    /**
     * common-modal.php loads this script before footer-script.php (jQuery). Bind after jQuery exists.
     */
    function bindLedgerModalProfileDefaultsOnce() {
        if (window._ledgerProfileDefaultsModalBound) {
            return;
        }
        var $ = window.jQuery;
        if (!$ || !$.fn || !$.fn.modal) {
            return;
        }
        window._ledgerProfileDefaultsModalBound = true;
        $(document).on('shown.bs.modal', '#customerCreationModal', function () {
            var hid = document.getElementById('ledgerCustomerId');
            if (hid && String(hid.value || '').trim() !== '') {
                return;
            }
            if (window._ledgerCustomerModalMode === 'edit') {
                return;
            }
            var base = ajaxBase();
            fetch(base + 'ajax/get-branch-profile-location-defaults.php', { credentials: 'same-origin' })
                .then(function (r) {
                    return r.json();
                })
                .then(function (d) {
                    if (typeof window.applyLedgerHeaderDefaultsFromProfile === 'function') {
                        window.applyLedgerHeaderDefaultsFromProfile(d);
                    }
                    if (typeof window.applyBillingShippingDefaultsFromProfile === 'function') {
                        window.applyBillingShippingDefaultsFromProfile(d);
                    }
                })
                .catch(function () {});
        });
    }
    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
        bindLedgerModalProfileDefaultsOnce();
    } else {
        var triesJq = 0;
        var ivJq = setInterval(function () {
            triesJq++;
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
                clearInterval(ivJq);
                bindLedgerModalProfileDefaultsOnce();
            }
            if (triesJq > 400) {
                clearInterval(ivJq);
            }
        }, 25);
    }
})();
