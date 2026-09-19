/**
 * GoldMatrix — Google Translate widget helpers.
 * - Hides top translation banner (classic + new Google class names)
 * - Persists language via googtrans cookie + ajax/save-app-locale.php
 * - Arabic RTL / other languages LTR
 * - Protects business values with .notranslate
 */
(function (window, document) {
    'use strict';

    var CFG = window.AURAGOLD_GT || {};
    var ALLOWED = CFG.allowed || ['en', 'hi', 'mr', 'gu', 'ta', 'te', 'kn', 'bn', 'pa', 'ar'];
    var SAVE_URL = CFG.saveUrl || 'ajax/save-app-locale.php';
    var observerStarted = false;
    var lastSavedLang = '';
    var removeScheduled = false;
    var gtRelocateTimer = null;
    var GT_MOBILE_MQ = window.matchMedia('(max-width: 991.98px)');

    function isMobileGtViewport() {
        return GT_MOBILE_MQ.matches;
    }

    function relocateGoogleTranslateWidget() {
        var el = document.getElementById('google_translate_element');
        var desktop = document.getElementById('auragoldGtSlotDesktop');
        var mobile = document.getElementById('auragoldGtSlotMobile');
        if (!el || !desktop || !mobile) {
            return;
        }
        var target = isMobileGtViewport() ? mobile : desktop;
        if (el.parentElement !== target) {
            target.appendChild(el);
        }
    }

    function scheduleRelocateGoogleTranslateWidget() {
        if (gtRelocateTimer) {
            clearTimeout(gtRelocateTimer);
        }
        gtRelocateTimer = setTimeout(relocateGoogleTranslateWidget, 50);
    }

    function normalizeLang(code) {
        code = String(code || '').trim().toLowerCase();
        if (!code || code.indexOf('en') === 0) {
            return 'en';
        }
        // "Marathi" / "mr|mr" / "/en/mr"
        var m = code.match(/(?:^|\/|\|)([a-z]{2})(?:$|[^a-z])/i);
        if (m) {
            code = m[1].toLowerCase();
        }
        if (ALLOWED.indexOf(code) === -1) {
            return 'en';
        }
        return code;
    }

    function getCookie(name) {
        var parts = ('; ' + document.cookie).split('; ' + name + '=');
        if (parts.length < 2) {
            return '';
        }
        return decodeURIComponent(parts.pop().split(';').shift() || '');
    }

    function setCookie(name, value, days) {
        var maxAge = (days || 365) * 24 * 60 * 60;
        var secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = name + '=' + encodeURIComponent(value)
            + '; path=/; max-age=' + maxAge + '; SameSite=Lax' + secure;
    }

    function readGoogTransLang() {
        var raw = getCookie('googtrans') || '';
        // formats: /en/mr  or  /auto/ar
        var m = raw.match(/\/(?:[a-z]{2}|auto)\/([a-z]{2})/i);
        return m ? normalizeLang(m[1]) : 'en';
    }

    function writeGoogTransLang(lang) {
        lang = normalizeLang(lang);
        if (lang === 'en') {
            document.cookie = 'googtrans=; path=/; max-age=0; SameSite=Lax';
            return;
        }
        setCookie('googtrans', '/en/' + lang, 365);
    }

    function applyDir(lang) {
        lang = normalizeLang(lang);
        var html = document.documentElement;
        if (lang === 'ar') {
            html.setAttribute('dir', 'rtl');
            html.setAttribute('lang', 'ar');
            html.classList.add('auragold-gt-rtl');
            html.classList.remove('auragold-gt-ltr');
        } else {
            html.setAttribute('dir', 'ltr');
            html.setAttribute('lang', lang === 'en' ? 'en' : lang);
            html.classList.add('auragold-gt-ltr');
            html.classList.remove('auragold-gt-rtl');
        }
    }

    /**
     * Hide/remove Google Translate top banner strip.
     * Keeps language combo / menu usable.
     */
    function removeGoogleTranslateBanner() {
        var selectors = [
            '.goog-te-banner-frame',
            'iframe.goog-te-banner-frame',
            '.goog-te-banner-frame.skiptranslate',
            '.VIpgJd-ZVi9od-ORHb-OEVmcd',
            'iframe.VIpgJd-ZVi9od-ORHb-OEVmcd',
            'body > .skiptranslate:has(iframe.goog-te-banner-frame)',
            'body > .skiptranslate:has(.VIpgJd-ZVi9od-ORHb-OEVmcd)'
        ];

        selectors.forEach(function (selector) {
            try {
                document.querySelectorAll(selector).forEach(function (element) {
                    if (!element) return;
                    // Never touch the language menu iframe
                    var cls = String(element.className || '');
                    if (cls.indexOf('goog-te-menu-frame') !== -1) return;
                    if (element.id === 'google_translate_element') return;

                    element.style.setProperty('display', 'none', 'important');
                    element.style.setProperty('visibility', 'hidden', 'important');
                    element.style.setProperty('height', '0px', 'important');
                    element.style.setProperty('max-height', '0px', 'important');
                    element.style.setProperty('width', '0px', 'important');
                    element.style.setProperty('border', '0', 'important');
                    element.style.setProperty('opacity', '0', 'important');
                    element.style.setProperty('pointer-events', 'none', 'important');

                    // Remove banner iframe parents that are only wrappers
                    try {
                        if (element.tagName === 'IFRAME' && element.parentElement
                            && element.parentElement !== document.body
                            && element.parentElement.id !== 'google_translate_element') {
                            var p = element.parentElement;
                            var pCls = String(p.className || '');
                            if (pCls.indexOf('skiptranslate') !== -1 && !p.querySelector('.goog-te-menu-frame')) {
                                p.style.setProperty('display', 'none', 'important');
                                p.style.setProperty('height', '0px', 'important');
                            }
                        }
                    } catch (e1) { /* ignore */ }
                });
            } catch (eSel) { /* :has() unsupported — ignore */ }
        });

        // Tooltips / highlight chrome
        [
            '#goog-gt-tt',
            '.goog-te-balloon-frame',
            '.VIpgJd-yAWNEb-L7lbkb',
            '.goog-tooltip',
            '.goog-te-spinner-pos'
        ].forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (el) {
                el.style.setProperty('display', 'none', 'important');
            });
        });

        document.querySelectorAll('.goog-text-highlight').forEach(function (el) {
            el.style.setProperty('background', 'transparent', 'important');
            el.style.setProperty('box-shadow', 'none', 'important');
        });

        if (document.body) {
            document.body.style.setProperty('top', '0px', 'important');
            document.body.style.setProperty('margin-top', '0px', 'important');
        }
        if (document.documentElement) {
            document.documentElement.style.setProperty('top', '0px', 'important');
            document.documentElement.style.setProperty('margin-top', '0px', 'important');
        }
    }

    function scheduleRemoveBanner() {
        if (removeScheduled) return;
        removeScheduled = true;
        requestAnimationFrame(function () {
            removeScheduled = false;
            removeGoogleTranslateBanner();
        });
    }

    function startBannerObserver() {
        if (observerStarted || typeof MutationObserver === 'undefined') return;
        observerStarted = true;
        var obs = new MutationObserver(function () {
            scheduleRemoveBanner();
        });
        obs.observe(document.documentElement, {
            childList: true,
            subtree: true
        });
    }

    function persistLocale(lang) {
        lang = normalizeLang(lang);
        if (lang === lastSavedLang) return;
        lastSavedLang = lang;
        writeGoogTransLang(lang);
        applyDir(lang);
        CFG.locale = lang;

        // Best-effort save to existing tbl_settings.app_locale
        try {
            if (window.fetch) {
                var body = new FormData();
                body.append('app_locale', lang);
                fetch(SAVE_URL, {
                    method: 'POST',
                    body: body,
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).catch(function () { /* ignore */ });
            }
        } catch (e) { /* ignore */ }
    }

    function detectSelectedLang() {
        var combo = document.querySelector('#google_translate_element select.goog-te-combo');
        if (combo && combo.value) {
            return normalizeLang(combo.value);
        }
        return readGoogTransLang();
    }

    function syncComboToLocale(lang) {
        lang = normalizeLang(lang);
        var combo = document.querySelector('#google_translate_element select.goog-te-combo');
        if (!combo) return;
        var opts = combo.options;
        for (var i = 0; i < opts.length; i++) {
            if (normalizeLang(opts[i].value) === lang) {
                if (combo.value !== opts[i].value) {
                    combo.selectedIndex = i;
                    // Trigger Google without UI banner delay where possible
                    try {
                        var ev = document.createEvent('HTMLEvents');
                        ev.initEvent('change', true, true);
                        combo.dispatchEvent(ev);
                    } catch (e) {
                        combo.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
                break;
            }
        }
    }

    /**
     * Mark dynamic business fields so Google does not alter codes/amounts.
     */
    function protectBusinessData() {
        var selectors = [
            '.auragold-header-db-name',
            'input[id*="barcode" i]',
            'input[name*="barcode" i]',
            'input[id*="invoice" i]',
            'input[name*="invoice" i]',
            'input[id*="gstin" i]',
            'input[name*="gstin" i]',
            'input[id*="pan" i]',
            'input[name*="hsn" i]',
            'input[id*="hsn" i]',
            'input[name*="sku" i]',
            'input[id*="sku" i]',
            'input[type="number"]',
            'input[id*="weight" i]',
            'input[id*="purity" i]',
            'input[id*="amount" i]',
            'input[id*="rate" i]',
            'input[id*="mobile" i]',
            'input[id*="phone" i]',
            'input[type="email"]',
            'input[id*="email" i]',
            'td[data-col="barcode"]',
            'td[data-col="hsn"]',
            'td[data-col="sku"]',
            'td[data-col="amount"]',
            'td[data-col="net_amount"]',
            'td[data-col="gross_weight"]',
            'td[data-col="net_weight"]',
            'td[data-col="purity"]',
            'td[data-col="rate"]',
            '#orderNo',
            '#invoiceNo',
            '#refNo',
            '#customerGstin',
            '.notranslate'
        ];
        selectors.forEach(function (sel) {
            try {
                document.querySelectorAll(sel).forEach(function (el) {
                    el.classList.add('notranslate');
                    el.setAttribute('translate', 'no');
                });
            } catch (e) { /* ignore invalid selectors in old browsers */ }
        });
    }

    // Expose for googleTranslateElementInit callback
    window.removeGoogleTranslateBanner = removeGoogleTranslateBanner;
    window.auragoldHideGoogleTranslateBanner = removeGoogleTranslateBanner;

    window.googleTranslateElementInit = function googleTranslateElementInit() {
        if (!window.google || !google.translate || !google.translate.TranslateElement) {
            return;
        }
        var el = document.getElementById('google_translate_element');
        if (!el) {
            return;
        }
        try {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                includedLanguages: ALLOWED.join(','),
                autoDisplay: false
            }, 'google_translate_element');
        } catch (e) { /* ignore */ }

        removeGoogleTranslateBanner();
        startBannerObserver();
        protectBusinessData();

        var want = normalizeLang(CFG.locale || 'en');
        applyDir(want);
        lastSavedLang = want;

        // Restore saved language into the combo (cookie already set before script load)
        relocateGoogleTranslateWidget();
        setTimeout(function () {
            relocateGoogleTranslateWidget();
            if (want !== 'en') {
                syncComboToLocale(want);
            }
            removeGoogleTranslateBanner();
            protectBusinessData();
        }, 400);
        setTimeout(removeGoogleTranslateBanner, 1200);
    };

    // Before widget script finishes: set cookie so Google restores language without extra prompt
    (function bootstrapCookieAndDir() {
        var lang = normalizeLang(CFG.locale || readGoogTransLang() || 'en');
        CFG.locale = lang;
        if (lang !== 'en') {
            writeGoogTransLang(lang);
        }
        applyDir(lang);
        removeGoogleTranslateBanner();
    })();

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onReady(function () {
        relocateGoogleTranslateWidget();
        removeGoogleTranslateBanner();
        startBannerObserver();
        protectBusinessData();
        applyDir(normalizeLang(CFG.locale || 'en'));
        if (typeof GT_MOBILE_MQ.addEventListener === 'function') {
            GT_MOBILE_MQ.addEventListener('change', scheduleRelocateGoogleTranslateWidget);
        } else if (typeof GT_MOBILE_MQ.addListener === 'function') {
            GT_MOBILE_MQ.addListener(scheduleRelocateGoogleTranslateWidget);
        }
        window.addEventListener('resize', scheduleRelocateGoogleTranslateWidget);
    });

    window.addEventListener('load', function () {
        removeGoogleTranslateBanner();
        protectBusinessData();
    });

    // Language change from Google combo
    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || !t.classList || !t.classList.contains('goog-te-combo')) return;
        var lang = normalizeLang(t.value);
        persistLocale(lang);
        removeGoogleTranslateBanner();
        // Short follow-ups only (Google recreates banner after change)
        setTimeout(removeGoogleTranslateBanner, 100);
        setTimeout(removeGoogleTranslateBanner, 500);
        setTimeout(removeGoogleTranslateBanner, 1500);
        setTimeout(protectBusinessData, 600);
    }, true);

    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        if (t.closest('#google_translate_element, .goog-te-combo, .goog-te-menu-value, .goog-te-menu2')) {
            setTimeout(removeGoogleTranslateBanner, 50);
            setTimeout(removeGoogleTranslateBanner, 400);
        }
    }, true);

})(window, document);
