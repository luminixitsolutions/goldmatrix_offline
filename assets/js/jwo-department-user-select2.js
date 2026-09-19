/**
 * Job Work Order — Name (department user / ledger) searchable Select2 dropdown.
 */
(function () {
    'use strict';

    function $sel() {
        if (typeof jQuery === 'undefined') return null;
        return jQuery('#jwoDepartmentUser');
    }

    function hasSelect2() {
        return typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.select2;
    }

    function ajaxParams() {
        var deptEl = document.getElementById('jwoDepartment');
        var deptVal = deptEl && deptEl.value ? String(deptEl.value).trim() : '';
        var allLedgers = !deptVal || parseInt(deptVal, 10) < 1;
        return {
            all_ledgers: allLedgers ? 1 : 0,
            department_id: allLedgers ? '' : deptVal
        };
    }

    function formatUserOption(u) {
        if (!u) return '';
        var text = String(u.user_name || u.text || '').trim();
        if (u.mobile_no) {
            text += (text ? ' — ' : '') + String(u.mobile_no).trim();
        }
        return text;
    }

    function destroyJwoDepartmentUserSelect2() {
        var $s = $sel();
        if (!$s || !$s.length || !hasSelect2()) return;
        if ($s.hasClass('select2-hidden-accessible')) {
            $s.off('.jwoDeptUser');
            $s.select2('destroy');
        }
    }

    function jwoDeptUserSelect2Instance() {
        var el = document.getElementById('jwoDepartmentUser');
        if (!el || el.tagName !== 'SELECT' || !hasSelect2()) return null;
        var inst = jQuery(el).data('select2');
        return (inst && typeof inst.isOpen === 'function' && inst.isOpen()) ? inst : null;
    }

    function jwoDeptUserHasSelectableHighlight(inst) {
        if (!inst || !inst.$results || !inst.$results.length) return false;
        var hl = inst.$results.find('.select2-results__option--highlighted').first();
        if (!hl.length) return false;
        if (hl.hasClass('select2-results__message')) return false;
        if (hl.attr('aria-disabled') === 'true') return false;
        return true;
    }

    function isJwoDeptUserSelect2SearchField(fieldEl) {
        var inst = jwoDeptUserSelect2Instance();
        if (!inst || !inst.$dropdown || !inst.$dropdown.length || !fieldEl) return false;
        return jQuery.contains(inst.$dropdown[0], fieldEl);
    }

    function openJwoLedgerModalWithSearchTerm(term) {
        term = String(term || '').trim();
        var el = document.getElementById('jwoDepartmentUser');
        if (el && hasSelect2() && jQuery(el).hasClass('select2-hidden-accessible')) {
            jQuery(el).select2('close');
        }
        if (term) {
            window._jwoDeptUserEnterSearchTerm = term;
        }
        if (typeof window.openJwoJobWorkerLedgerModalFromUi === 'function') {
            window.openJwoJobWorkerLedgerModalFromUi();
            return;
        }
        if (typeof window.openJwoJobWorkerLedgerModal === 'function') {
            window.openJwoJobWorkerLedgerModal(term);
            return;
        }
        if (typeof window.openNewPartyModalWithName === 'function') {
            window.openNewPartyModalWithName(term);
        }
    }

    function initJwoDepartmentUserSelect2() {
        if (!window.jwoHasDepartmentUserTables) return;
        var el = document.getElementById('jwoDepartmentUser');
        if (!el || el.tagName !== 'SELECT' || !hasSelect2()) return;

        var $s = jQuery(el);
        destroyJwoDepartmentUserSelect2();

        $s.select2({
            placeholder: 'Select name...',
            allowClear: true,
            width: '100%',
            minimumInputLength: 0,
            dropdownParent: jQuery(document.body),
            containerCssClass: 'jwo-dept-user-select2-container',
            dropdownCssClass: 'jwo-dept-user-select2-dropdown',
            language: {
                inputTooShort: function () {
                    return 'Type to search accounts…';
                },
                searching: function () {
                    return 'Searching…';
                },
                noResults: function () {
                    return 'No account found';
                }
            },
            ajax: {
                url: 'ajax/search-jwo-department-users.php',
                dataType: 'json',
                delay: 280,
                data: function (params) {
                    return jQuery.extend({
                        q: params.term || '',
                        format: 'select2'
                    }, ajaxParams());
                },
                processResults: function (data) {
                    var rows = (data && data.results) ? data.results : [];
                    return {
                        results: rows,
                        pagination: { more: !!(data && data.pagination && data.pagination.more) }
                    };
                },
                cache: true
            },
            templateResult: function (item) {
                if (!item.id) {
                    return item.text;
                }
                var name = item.user_name || item.text || '';
                var $wrap = jQuery('<div>');
                $wrap.append(jQuery('<div>').css({ fontWeight: '600' }).text(name));
                if (item.mobile_no) {
                    $wrap.append(jQuery('<div>').css({ fontSize: '0.8rem', color: '#64748b' }).text(item.mobile_no));
                }
                if (item.alternate_name) {
                    $wrap.append(jQuery('<div>').css({ fontSize: '0.75rem', color: '#94a3b8' }).text(item.alternate_name));
                }
                return $wrap;
            },
            templateSelection: function (item) {
                return item.user_name || item.text || item.id || '';
            }
        });

        $s.on('change.jwoDeptUser', function () {
            var v = String(jQuery(this).val() || '').trim();
            if (typeof window.jwoOnDepartmentUserChanged === 'function') {
                window.jwoOnDepartmentUserChanged(v);
            }
        });

        jQuery('.top-navbar').off('mouseenter.jwoDeptUser').on('mouseenter.jwoDeptUser', function () {
            if ($s.hasClass('select2-hidden-accessible')) {
                $s.select2('close');
            }
        });
    }

    function setJwoDepartmentUserValue(userId, displayName) {
        var el = document.getElementById('jwoDepartmentUser');
        if (!el || el.tagName !== 'SELECT') return;
        var uid = userId ? String(parseInt(userId, 10)) : '';
        if (!uid || uid === 'NaN' || uid === '0') {
            if (hasSelect2() && jQuery(el).hasClass('select2-hidden-accessible')) {
                jQuery(el).val(null).trigger('change');
            } else {
                el.value = '';
            }
            return;
        }
        var label = String(displayName || '').trim() || ('User #' + uid);
        var $s = jQuery(el);
        if (!$s.find('option[value="' + uid + '"]').length) {
            $s.append(new Option(label, uid, true, true));
        }
        if (hasSelect2() && $s.hasClass('select2-hidden-accessible')) {
            $s.val(uid).trigger('change');
        } else {
            el.value = uid;
        }
    }

    function getJwoDepartmentUserSearchTerm() {
        if (window._jwoDeptUserEnterSearchTerm) {
            var cached = String(window._jwoDeptUserEnterSearchTerm || '').trim();
            window._jwoDeptUserEnterSearchTerm = '';
            if (cached) return cached;
        }
        var inst = jwoDeptUserSelect2Instance();
        if (inst && inst.$dropdown && inst.$dropdown.length) {
            var $field = inst.$dropdown.find('.select2-search__field');
            if ($field.length) {
                return String($field.val() || '').trim();
            }
        }
        var el = document.getElementById('jwoDepartmentUser');
        if (!el || el.tagName !== 'SELECT') return '';
        var opt = el.options[el.selectedIndex];
        return opt && opt.value ? String(opt.text || '').trim() : '';
    }

    function bindEnterOpensJwoLedgerModal() {
        jQuery(document).off('keydown.jwoDeptUserEnter').on('keydown.jwoDeptUserEnter', '.select2-container--open .select2-search__field', function (e) {
            if (!window.jwoHasDepartmentUserTables) return;
            if (e.key !== 'Enter') return;
            if (!isJwoDeptUserSelect2SearchField(this)) return;
            var term = String(this.value || '').trim();
            var el = document.getElementById('jwoDepartmentUser');
            if (!el || el.value) return;
            if (jwoDeptUserHasSelectableHighlight(jwoDeptUserSelect2Instance())) return;
            if (!term) return;
            e.preventDefault();
            e.stopPropagation();
            openJwoLedgerModalWithSearchTerm(term);
        });
    }

    function bindJwoDepartmentUserSearchTextEnter() {
        jQuery(document).off('keydown.jwoDeptUserSearchEnter').on('keydown.jwoDeptUserSearchEnter', '#jwoDepartmentUserSearch', function (e) {
            if (e.key !== 'Enter' || this.readOnly || this.disabled) return;
            var term = String(this.value || '').trim();
            var hid = document.getElementById('jwoDepartmentUser');
            if (hid && String(hid.value || '').trim()) return;
            var sugs = document.getElementById('jwoDepartmentUserSuggestions');
            if (sugs && sugs.style.display !== 'none') {
                if (sugs.querySelector('.customer-suggestion-item.focused, .jwo-dept-user-suggestion.focused')) {
                    return;
                }
                if (sugs.querySelectorAll('.customer-suggestion-item, .jwo-dept-user-suggestion, [data-user-id]').length > 0) {
                    return;
                }
            }
            if (!term) return;
            e.preventDefault();
            e.stopPropagation();
            if (sugs) sugs.style.display = 'none';
            openJwoLedgerModalWithSearchTerm(term);
        });
    }

    window.initJwoDepartmentUserSelect2 = initJwoDepartmentUserSelect2;
    window.destroyJwoDepartmentUserSelect2 = destroyJwoDepartmentUserSelect2;
    window.setJwoDepartmentUserValue = setJwoDepartmentUserValue;
    window.getJwoDepartmentUserSearchTerm = getJwoDepartmentUserSearchTerm;

    function bootJwoDepartmentUserSelect2() {
        if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.select2) {
            setTimeout(bootJwoDepartmentUserSelect2, 40);
            return;
        }
        jQuery(function () {
            if (window.jwoHasDepartmentUserTables) {
                initJwoDepartmentUserSelect2();
            }
            bindEnterOpensJwoLedgerModal();
            bindJwoDepartmentUserSearchTextEnter();
        });
    }

    bootJwoDepartmentUserSelect2();
})();
