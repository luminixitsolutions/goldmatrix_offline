/**
 * Add Branch: password gate + branch form (fetch API).
 */
(function () {
    'use strict';

    var pwdOverlay = document.getElementById('branchAddPwdOverlay');
    var formOverlay = document.getElementById('branchAddFormOverlay');
    if (!pwdOverlay || !formOverlay) return;

    var openBtn = document.getElementById('branchAddOpen');
    var mainOpenBtn = document.getElementById('branchAddMainOpen');
    /** @type {'sub'|'main'} */
    var branchSaveMode = 'sub';
    var targetMainBranchId = 0;
    var targetMainBranchName = '';

    var formTitleEl = document.getElementById('branchAddFormTitle');
    var formSubmitEl = document.getElementById('branchAddFormSubmit');
    var savingTextEl = document.getElementById('branchAddSavingText');

    function applyBranchModalTitles() {
        var isMain = branchSaveMode === 'main';
        if (formTitleEl) {
            formTitleEl.textContent = isMain ? 'New main branch' : 'Branch details';
        }
        if (formSubmitEl) {
            formSubmitEl.textContent = isMain ? 'Create main branch' : 'Save branch';
        }
        if (savingTextEl) {
            savingTextEl.textContent = isMain
                ? 'Creating database and copying masters…'
                : 'Please wait…';
        }
    }

    function updateUnderMainNote() {
        var note = document.getElementById('branchAddUnderMainNote');
        if (!note) {
            return;
        }
        if (branchSaveMode === 'sub' && targetMainBranchId > 0) {
            note.hidden = false;
            note.textContent = targetMainBranchName
                ? 'New sub-branch under: ' + targetMainBranchName
                : 'Sub-branch will be created under the selected main branch.';
        } else {
            note.hidden = true;
            note.textContent = '';
        }
    }

    var pwdInput = document.getElementById('branchAddPwdInput');
    var pwdError = document.getElementById('branchAddPwdError');
    var pwdSubmit = document.getElementById('branchAddPwdSubmit');
    var pwdCancel = document.getElementById('branchAddPwdCancel');

    var form = document.getElementById('branchAddDetailsForm');
    var formError = document.getElementById('branchAddFormError');
    var hostError = document.getElementById('branchAddHostError');
    var hostInput = document.getElementById('branchAddHost');
    var formSubmit = document.getElementById('branchAddFormSubmit');
    var formCancel = document.getElementById('branchAddFormCancel');
    var formClose = document.getElementById('branchAddFormClose');
    var savingOverlay = document.getElementById('branchAddSavingOverlay');
    var hostUrlManuallyEdited = false;
    var hostInputProgrammatic = false;

    var verifiedPassword = '';

    function setBranchFormSaving(isSaving) {
        if (savingOverlay) {
            if (isSaving) {
                savingOverlay.removeAttribute('hidden');
                savingOverlay.classList.add('is-visible');
                savingOverlay.setAttribute('aria-hidden', 'false');
            } else {
                savingOverlay.setAttribute('hidden', '');
                savingOverlay.classList.remove('is-visible');
                savingOverlay.setAttribute('aria-hidden', 'true');
            }
        }
        if (formSubmit) formSubmit.disabled = !!isSaving;
        if (formCancel) formCancel.disabled = !!isSaving;
        if (formClose) formClose.disabled = !!isSaving;
    }

    function resetStateSelect() {
        var sel = document.getElementById('branchAddState');
        if (!sel) return;
        sel.innerHTML = '<option value="">— Select country first —</option>';
        sel.disabled = true;
        resetCitySelect();
    }

    function resetCitySelect() {
        var sel = document.getElementById('branchAddCity');
        if (!sel) return;
        sel.innerHTML = '<option value="">— Select state first —</option>';
        sel.disabled = true;
    }

    function loadStatesForCountry(countryId) {
        var sel = document.getElementById('branchAddState');
        if (!sel) return;
        var cid = parseInt(countryId, 10) || 0;
        if (cid <= 0) {
            resetStateSelect();
            return;
        }
        sel.disabled = true;
        sel.innerHTML = '<option value="">Loading…</option>';
        resetCitySelect();
        fetch('ajax/get-states-by-country.php?country_id=' + encodeURIComponent(cid), {
            credentials: 'same-origin',
        })
            .then(function (r) {
                return parseJsonSafe(r);
            })
            .then(function (d) {
                if (d && d.status === 'success' && Array.isArray(d.states)) {
                    sel.innerHTML = '<option value="">— Select —</option>';
                    d.states.forEach(function (row) {
                        var opt = document.createElement('option');
                        opt.value = String(row.id);
                        opt.textContent = row.name || '';
                        sel.appendChild(opt);
                    });
                    sel.disabled = false;
                } else {
                    sel.innerHTML = '<option value="">No states found</option>';
                    sel.disabled = false;
                }
            })
            .catch(function () {
                sel.innerHTML = '<option value="">Could not load states</option>';
                sel.disabled = true;
            });
    }

    function loadCitiesForState(stateId) {
        var sel = document.getElementById('branchAddCity');
        if (!sel) return;
        var sid = parseInt(stateId, 10) || 0;
        if (sid <= 0) {
            resetCitySelect();
            return;
        }
        sel.disabled = true;
        sel.innerHTML = '<option value="">Loading…</option>';
        fetch('ajax/get-cities-by-state.php?state_id=' + encodeURIComponent(sid), {
            credentials: 'same-origin',
        })
            .then(function (r) {
                return parseJsonSafe(r);
            })
            .then(function (d) {
                if (d && d.status === 'success' && Array.isArray(d.cities)) {
                    sel.innerHTML = '<option value="">— Select —</option>';
                    d.cities.forEach(function (row) {
                        var opt = document.createElement('option');
                        opt.value = String(row.id);
                        opt.textContent = row.name || '';
                        sel.appendChild(opt);
                    });
                    sel.disabled = false;
                } else {
                    sel.innerHTML = '<option value="">No cities found</option>';
                    sel.disabled = false;
                }
            })
            .catch(function () {
                sel.innerHTML = '<option value="">Could not load cities</option>';
                sel.disabled = true;
            });
    }

    var countrySel = document.getElementById('branchAddCountry');
    if (countrySel) {
        countrySel.addEventListener('change', function () {
            loadStatesForCountry(countrySel.value);
        });
    }

    var stateSel = document.getElementById('branchAddState');
    if (stateSel) {
        stateSel.addEventListener('change', function () {
            loadCitiesForState(stateSel.value);
        });
    }

    // Suggest timezone from phone country dial code (user can still change it)
    var phoneCodeSel = document.getElementById('branchAddPhoneCountryCode');
    var tzSel = document.getElementById('branchAddTimezone');
    var phoneToTz = {
        '91': 'Asia/Kolkata',
        '971': 'Asia/Dubai',
        '65': 'Asia/Singapore',
        '44': 'Europe/London',
        '1': 'America/New_York',
        '966': 'Asia/Riyadh',
        '974': 'Asia/Qatar',
        '965': 'Asia/Kuwait',
        '973': 'Asia/Bahrain',
        '968': 'Asia/Muscat',
        '92': 'Asia/Karachi',
        '94': 'Asia/Colombo',
        '977': 'Asia/Kathmandu',
        '880': 'Asia/Dhaka',
        '852': 'Asia/Hong_Kong',
        '66': 'Asia/Bangkok',
        '62': 'Asia/Jakarta',
        '61': 'Australia/Sydney',
        '254': 'Africa/Nairobi'
    };
    function suggestTimezoneFromPhoneCode() {
        if (!phoneCodeSel || !tzSel) return;
        var code = String(phoneCodeSel.value || '').replace(/\D+/g, '');
        var tz = phoneToTz[code] || 'Asia/Kolkata';
        if ([].some.call(tzSel.options, function (o) { return o.value === tz; })) {
            tzSel.value = tz;
        }
    }
    if (phoneCodeSel) {
        phoneCodeSel.addEventListener('change', suggestTimezoneFromPhoneCode);
    }

    function show(el) {
        if (el) el.classList.add('is-open');
    }

    function hide(el) {
        if (el) el.classList.remove('is-open');
    }

    function setPwdErr(msg) {
        if (!pwdError) return;
        if (msg) {
            pwdError.textContent = msg;
            pwdError.classList.add('is-visible');
        } else {
            pwdError.textContent = '';
            pwdError.classList.remove('is-visible');
        }
    }

    function setFormErr(msg) {
        if (hostError) {
            hostError.textContent = '';
            hostError.classList.remove('is-visible');
        }
        if (hostInput) {
            hostInput.setAttribute('aria-invalid', 'false');
        }
        if (!formError) return;
        if (msg) {
            formError.textContent = msg;
            formError.classList.add('is-visible');
        } else {
            formError.textContent = '';
            formError.classList.remove('is-visible');
        }
    }

    function setHostFieldErr(msg) {
        if (formError) {
            formError.textContent = '';
            formError.classList.remove('is-visible');
        }
        if (!hostError) return;
        if (msg) {
            hostError.textContent = msg;
            hostError.classList.add('is-visible');
            if (hostInput) {
                hostInput.setAttribute('aria-invalid', 'true');
            }
        } else {
            hostError.textContent = '';
            hostError.classList.remove('is-visible');
            if (hostInput) {
                hostInput.setAttribute('aria-invalid', 'false');
            }
        }
    }

    function parseJsonSafe(r) {
        return r.text().then(function (text) {
            var t = (text || '').trim();
            if (!t) {
                var st = r.status || 0;
                throw new Error(
                    'Empty server response (HTTP ' +
                        st +
                        '). Often a PHP fatal error — check Laragon/php error log or enable display_errors for admin/api/save_branch.php.'
                );
            }
            try {
                return JSON.parse(t);
            } catch (e) {
                throw new Error(t.substring(0, 200));
            }
        });
    }

    function slugFromBranchName(name) {
        var s = (name || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        if (!s) {
            s = 'branch';
        }
        if (s.length > 50) {
            s = s.slice(0, 50);
        }
        return s.replace(/^_+|_+$/g, '') || 'branch';
    }

    function normalizeSubdomainBaseHost(raw) {
        var s = (raw == null) ? '' : String(raw).trim();
        if (s === '') {
            return '';
        }
        if (/^https?:\/\//i.test(s)) {
            try {
                var u = new URL(s);
                s = (u && u.hostname) ? u.hostname : s;
            } catch (e0) {
                s = s.replace(/^https?:\/\//i, '').split('/')[0] || '';
            }
        } else {
            s = s.split('/')[0] || s;
        }
        if (s.indexOf(':') !== -1) {
            s = s.split(':')[0] || s;
        }
        return s.trim();
    }

    function subdomainLabelFromBranchName(name) {
        if (!(name && String(name).trim())) {
            return '';
        }
        return slugFromBranchName(name).replace(/_+/g, '-');
    }

    function setBranchHostField(value) {
        if (!hostInput) {
            return;
        }
        hostInputProgrammatic = true;
        hostInput.value = value || '';
        hostInputProgrammatic = false;
    }

    function updateBranchHostPreview() {
        var ui = window.AURAGOLD_BRANCH_UI || {};
        var baseHost = normalizeSubdomainBaseHost(ui.branchSubdomainBaseHost);
        if (baseHost === '' || !hostInput) {
            return;
        }
        if (hostUrlManuallyEdited) {
            return;
        }
        var nameEl = document.getElementById('branchAddName');
        var name = nameEl ? nameEl.value : '';
        if (!name || !String(name).trim()) {
            setBranchHostField('');
            return;
        }
        var label = subdomainLabelFromBranchName(name);
        if (!label) {
            return;
        }
        if (label.length > 60) {
            label = label.slice(0, 60);
        }
        var useHttps = ui.branchSubdomainUrlHttps !== false;
        var url = (useHttps ? 'https' : 'http') + '://' + label + '.' + baseHost;
        setBranchHostField(url);
    }

    function updateBranchDbPreview() {
        var nameEl = document.getElementById('branchAddName');
        var dbn = document.getElementById('branchAddDbName');
        var dbu = document.getElementById('branchAddDbUser');
        var dbp = document.getElementById('branchAddDbPass');
        var ui = window.AURAGOLD_BRANCH_UI || { project: 'local', dbPrefix: 'auragold_' };
        var rawPrefix = (ui.dbPrefix != null && String(ui.dbPrefix) !== '') ? String(ui.dbPrefix) : 'auragold_';
        var prefix = rawPrefix.replace(/\s+$/g, '');
        if (prefix !== '' && prefix.slice(-1) !== '_') {
            prefix += '_';
        }
        var slug = slugFromBranchName(nameEl ? nameEl.value : '');
        var base = prefix + slug;
        if (dbn) {
            dbn.value = base.length > 64 ? base.slice(0, 64) : base;
        }
        var isProd = String(ui.project || '').toLowerCase() === 'prod';
        if (dbu) {
            if (isProd) {
                dbu.value = base.length > 32 ? base.slice(0, 32) : base;
            } else {
                dbu.value = 'root';
            }
        }
        if (dbp) {
            dbp.value = isProd
                ? 'Generated on save — copy from confirmation'
                : '(blank — stored empty; app uses main DB account)';
        }
        updateBranchHostPreview();
    }

    function openBranchAddFlow(mode, mainId, mainName) {
        hostUrlManuallyEdited = false;
        branchSaveMode = mode === 'main' ? 'main' : 'sub';
        if (mode === 'main') {
            targetMainBranchId = 0;
            targetMainBranchName = '';
        } else if (typeof mainId === 'number' && !isNaN(mainId) && mainId > 0) {
            targetMainBranchId = mainId;
            targetMainBranchName = mainName ? String(mainName) : '';
        } else {
            targetMainBranchId = 0;
            targetMainBranchName = '';
        }
        verifiedPassword = '';
        if (pwdInput) pwdInput.value = '';
        setPwdErr('');
        show(pwdOverlay);
        setTimeout(function () {
            if (pwdInput) pwdInput.focus();
        }, 50);
    }

    if (openBtn) {
        openBtn.addEventListener('click', function () {
            openBranchAddFlow('sub');
        });
    }

    if (mainOpenBtn) {
        mainOpenBtn.addEventListener('click', function () {
            openBranchAddFlow('main');
        });
    }

    document.addEventListener('click', function (e) {
        var t = e.target && e.target.closest ? e.target.closest('.branch-add-sub-for-main') : null;
        if (!t) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        var mid = parseInt(t.getAttribute('data-main-id'), 10) || 0;
        var mname = t.getAttribute('data-main-name') || '';
        if (mid <= 0) {
            return;
        }
        openBranchAddFlow('sub', mid, mname);
    });

    if (pwdCancel) {
        pwdCancel.addEventListener('click', function () {
            hide(pwdOverlay);
        });
    }

    if (formCancel) {
        formCancel.addEventListener('click', function () {
            hide(formOverlay);
            verifiedPassword = '';
        });
    }

    if (formClose) {
        formClose.addEventListener('click', function () {
            hide(formOverlay);
            verifiedPassword = '';
        });
    }

    if (pwdInput && pwdSubmit) {
        pwdInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                pwdSubmit.click();
            }
        });
    }

    if (pwdSubmit) {
        pwdSubmit.addEventListener('click', function () {
            var pw = pwdInput ? pwdInput.value.trim() : '';
            setPwdErr('');
            if (!pw) {
                setPwdErr('Enter the password.');
                return;
            }
            pwdSubmit.disabled = true;
            var fd = new FormData();
            fd.append('password', pw);
            fetch('api/verify_branch_password.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) {
                    return parseJsonSafe(r);
                })
                .then(function (d) {
                    pwdSubmit.disabled = false;
                    if (d && d.ok) {
                        verifiedPassword = pw;
                        hide(pwdOverlay);
                        hostUrlManuallyEdited = false;
                        if (form) form.reset();
                        resetStateSelect();
                        var phoneCc = document.getElementById('branchAddPhoneCountryCode');
                        if (phoneCc) phoneCc.value = '971';
                        var activeCb = document.getElementById('branchAddActive');
                        if (activeCb) activeCb.checked = true;
                        setFormErr('');
                        updateBranchDbPreview();
                        applyBranchModalTitles();
                        updateUnderMainNote();
                        show(formOverlay);
                    } else {
                        setPwdErr((d && d.message) ? d.message : 'Invalid Password');
                    }
                })
                .catch(function (err) {
                    pwdSubmit.disabled = false;
                    setPwdErr(err && err.message ? err.message : 'Request failed');
                });
        });
    }

    var branchNameInput = document.getElementById('branchAddName');
    if (branchNameInput) {
        branchNameInput.addEventListener('input', updateBranchDbPreview);
        branchNameInput.addEventListener('change', updateBranchDbPreview);
    }

    if (hostInput) {
        hostInput.addEventListener('input', function () {
            if (hostInputProgrammatic) {
                setHostFieldErr('');
                return;
            }
            hostUrlManuallyEdited = true;
            setHostFieldErr('');
        });
        hostInput.addEventListener('change', function () {
            if (hostInputProgrammatic) {
                setHostFieldErr('');
                return;
            }
            setHostFieldErr('');
        });
    }

    if (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            setFormErr('');

            var branchName = (document.getElementById('branchAddName') || {}).value;
            branchName = (branchName || '').trim();
            var digitsRaw = (document.getElementById('branchAddDigits') || {}).value;
            var digits = parseInt(digitsRaw, 10);
            var mail = ((document.getElementById('branchAddMail') || {}).value || '').trim();
            var ipHost = (hostInput ? hostInput.value : (document.getElementById('branchAddHost') || {}).value) || '';
            ipHost = (ipHost || '').trim();

            if (!branchName) {
                setFormErr('Branch name is required.');
                return;
            }
            if (!ipHost) {
                setHostFieldErr('IP address is required. Enter a valid IP, hostname, or full URL (http/https).');
                if (hostInput) {
                    hostInput.focus();
                }
                return;
            }
            if (!digitsRaw || isNaN(digits) || digits < 1 || digits > 32) {
                setFormErr('Number of digits must be between 1 and 32.');
                return;
            }
            if (mail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(mail)) {
                setFormErr('Enter a valid email or leave it empty.');
                return;
            }

            var phoneCcEl = document.getElementById('branchAddPhoneCountryCode');
            var phoneCc = phoneCcEl ? String(phoneCcEl.value || '').trim() : '';
            var countryId = parseInt((document.getElementById('branchAddCountry') || {}).value, 10) || 0;
            var stateId = parseInt((document.getElementById('branchAddState') || {}).value, 10) || 0;
            var cityId = parseInt((document.getElementById('branchAddCity') || {}).value, 10) || 0;
            if (!phoneCc) {
                setFormErr('Country code is required.');
                if (phoneCcEl) phoneCcEl.focus();
                return;
            }
            if (countryId <= 0) {
                setFormErr('Country is required.');
                var cEl = document.getElementById('branchAddCountry');
                if (cEl) cEl.focus();
                return;
            }
            if (stateId <= 0) {
                setFormErr('State is required.');
                var sEl = document.getElementById('branchAddState');
                if (sEl) sEl.focus();
                return;
            }
            if (cityId <= 0) {
                setFormErr('City is required.');
                var cityEl = document.getElementById('branchAddCity');
                if (cityEl) cityEl.focus();
                return;
            }

            if (!verifiedPassword) {
                setFormErr('Password expired. Close and open Add sub-branch again.');
                return;
            }

            var fd = new FormData(form);
            fd.append('password', verifiedPassword);
            if (branchSaveMode === 'sub' && targetMainBranchId > 0) {
                fd.append('for_main_branch_id', String(targetMainBranchId));
            }

            var saveUrl = branchSaveMode === 'main' ? 'api/save_main_branch.php' : 'api/save_branch.php';
            setBranchFormSaving(true);
            fetch(saveUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) {
                    return parseJsonSafe(r);
                })
                .then(function (d) {
                    setBranchFormSaving(false);
                    if (d && d.ok) {
                        var isMain = branchSaveMode === 'main';
                        var text;
                        var alertType = 'success';
                        if (isMain && d.user_seeded === false) {
                            text =
                                'The main branch was created, but the login could not be set up in the new database. ' +
                                'If you cannot log in, ask an administrator to add users in that database.';
                            alertType = 'warning';
                        } else {
                            text = isMain
                                ? 'New main branch created successfully.'
                                : 'New branch created successfully.';
                        }
                        var title = alertType === 'warning' ? 'Notice' : 'Success';

                        function afterAck() {
                            hide(formOverlay);
                            verifiedPassword = '';
                            window.location.reload();
                        }

                        if (typeof swal === 'function') {
                            try {
                                swal(
                                    {
                                        title: title,
                                        text: text,
                                        type: alertType,
                                        confirmButtonText: 'OK',
                                    },
                                    function () {
                                        afterAck();
                                    }
                                );
                            } catch (eSw) {
                                try {
                                    window.alert(text);
                                } catch (e1) {}
                                afterAck();
                            }
                        } else {
                            try {
                                window.alert(text);
                            } catch (e0) {}
                            afterAck();
                        }
                    } else {
                        var em = (d && d.message) ? String(d.message) : 'Could not save branch';
                        if (em.indexOf('IP address is required') !== -1) {
                            setHostFieldErr(em);
                            if (hostInput) {
                                hostInput.focus();
                            }
                        } else {
                            setFormErr(em);
                        }
                    }
                })
                .catch(function (err) {
                    setBranchFormSaving(false);
                    setFormErr(err && err.message ? err.message : 'Request failed');
                });
        });
    }
})();
