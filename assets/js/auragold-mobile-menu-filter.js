/**
 * Hide top-nav items on mobile per Set Software → Mobile Menu Setting.
 * Desktop (≥992px): all items remain visible.
 */
(function () {
    'use strict';

    var cfg = window.auragoldMobileMenuFilter;
    if (!cfg) {
        return;
    }

    var mq = window.matchMedia('(max-width: 991.98px)');
    var basenameMap = cfg.basenameMap || {};
    var disabledModules = new Set(cfg.disabledModules || []);
    var disabledPages = new Set(cfg.disabledPages || []);

    function isMobile() {
        return mq.matches;
    }

    function pageKeyFromHref(href) {
        if (!href || href === '#') {
            return '';
        }
        var path = href.split('?')[0].split('#')[0];
        var parts = path.split('/');
        var bn = parts[parts.length - 1] || '';
        return basenameMap[bn] || '';
    }

    function applyMobileMenuFilter() {
        var topNav = document.getElementById('auragoldTopNav');
        if (!topNav) {
            return;
        }

        var hideClass = 'auragold-hide-mobile-nav';

        if (!isMobile() || (disabledModules.size === 0 && disabledPages.size === 0)) {
            topNav.querySelectorAll('.' + hideClass).forEach(function (el) {
                el.classList.remove(hideClass);
            });
            return;
        }

        topNav.querySelectorAll('[data-mm-module]').forEach(function (item) {
            var mod = item.getAttribute('data-mm-module') || '';
            if (mod && disabledModules.has(mod)) {
                item.classList.add(hideClass);
            } else {
                item.classList.remove(hideClass);
            }
        });

        topNav.querySelectorAll('[data-mm-page]').forEach(function (li) {
            var key = li.getAttribute('data-mm-page') || '';
            var mod = key.indexOf('.') > -1 ? key.split('.')[0] : '';
            if ((mod && disabledModules.has(mod)) || (key && disabledPages.has(key))) {
                li.classList.add(hideClass);
            } else {
                li.classList.remove(hideClass);
            }
        });

        topNav.querySelectorAll('a[href]').forEach(function (a) {
            var href = a.getAttribute('href') || '';
            if (!href || href === '#') {
                return;
            }
            var key = pageKeyFromHref(href);
            if (!key) {
                return;
            }
            var mod = key.split('.')[0];
            var li = a.closest('li');
            if (!li) {
                return;
            }
            if (disabledModules.has(mod) || disabledPages.has(key)) {
                li.classList.add(hideClass);
            } else if (!li.hasAttribute('data-mm-page')) {
                li.classList.remove(hideClass);
            }
        });

        topNav.querySelectorAll('.nav-item[data-mm-module]').forEach(function (item) {
            if (item.classList.contains(hideClass)) {
                return;
            }
            var mod = item.getAttribute('data-mm-module') || '';
            if (mod && disabledModules.has(mod)) {
                return;
            }
            var subItems = item.querySelectorAll('.dropdown-menu > li, .mega-menu-list > li, .mega-menu li');
            if (!subItems.length) {
                return;
            }
            var anyVisible = false;
            subItems.forEach(function (li) {
                if (!li.classList.contains(hideClass)) {
                    anyVisible = true;
                }
            });
            if (!anyVisible) {
                item.classList.add(hideClass);
            }
        });
    }

    function onLayoutChange() {
        applyMobileMenuFilter();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyMobileMenuFilter);
    } else {
        applyMobileMenuFilter();
    }

    window.addEventListener('resize', onLayoutChange, { passive: true });
    if (typeof mq.addEventListener === 'function') {
        mq.addEventListener('change', onLayoutChange);
    } else if (typeof mq.addListener === 'function') {
        mq.addListener(onLayoutChange);
    }

    document.addEventListener('click', function (e) {
        if (e.target && e.target.closest && e.target.closest('#auragoldMobileMenuBtn')) {
            requestAnimationFrame(applyMobileMenuFilter);
        }
    });

    window.auragoldApplyMobileMenuFilter = applyMobileMenuFilter;
})();
