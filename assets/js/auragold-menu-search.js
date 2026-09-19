/**
 * Global header menu search — indexes visible top nav links and opens page on select.
 */
(function () {
    'use strict';

    function normText(s) {
        return String(s || '').replace(/\s+/g, ' ').trim();
    }

    function buildMenuSearchIndex() {
        var nav = document.getElementById('auragoldTopNav');
        if (!nav) {
            return [];
        }
        var items = [];
        var seen = {};

        function addItem(menu, group, label, href) {
            href = String(href || '').trim();
            if (!href || href === '#' || href.indexOf('javascript:') === 0) {
                return;
            }
            label = normText(label);
            if (!label) {
                return;
            }
            menu = normText(menu);
            group = normText(group);
            var key = href + '\0' + label;
            if (seen[key]) {
                return;
            }
            seen[key] = true;
            items.push({
                menu: menu,
                group: group,
                label: label,
                href: href,
                searchText: (menu + ' ' + group + ' ' + label).toLowerCase()
            });
        }

        nav.querySelectorAll('.nav-item').forEach(function (navItem) {
            var menuLink = navItem.querySelector(':scope > .nav-link');
            var menuLabel = menuLink ? normText(menuLink.textContent) : '';

            navItem.querySelectorAll('.mega-menu-column').forEach(function (col) {
                var groupEl = col.querySelector('.mega-menu-title');
                var group = groupEl ? normText(groupEl.textContent) : '';
                col.querySelectorAll('a.dropdown-item[href]').forEach(function (a) {
                    addItem(menuLabel, group, a.textContent, a.getAttribute('href'));
                });
            });

            navItem.querySelectorAll(':scope > .dropdown-menu a.dropdown-item[href]').forEach(function (a) {
                addItem(menuLabel, '', a.textContent, a.getAttribute('href'));
            });

            if (menuLink && menuLink.getAttribute('href') && menuLink.getAttribute('href') !== '#') {
                addItem('', '', menuLabel, menuLink.getAttribute('href'));
            }
        });

        items.sort(function (a, b) {
            var c = a.menu.localeCompare(b.menu);
            if (c !== 0) {
                return c;
            }
            c = a.group.localeCompare(b.group);
            if (c !== 0) {
                return c;
            }
            return a.label.localeCompare(b.label);
        });

        return items;
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function highlightMatch(text, q) {
        var t = escapeHtml(text);
        if (!q) {
            return t;
        }
        var re = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
        return t.replace(re, '<mark>$1</mark>');
    }

    function initMenuSearch() {
        var input = document.getElementById('auragoldMenuSearchInput');
        var results = document.getElementById('auragoldMenuSearchResults');
        var wrap = document.getElementById('auragoldMenuSearchWrap');
        if (!input || !results || !wrap) {
            return;
        }

        // Chrome may treat header text inputs as login fields; readonly until focus blocks autofill.
        input.setAttribute('readonly', 'readonly');
        input.addEventListener('focus', function () {
            input.removeAttribute('readonly');
        });

        var index = [];
        var activeIdx = -1;
        var visible = [];

        function rebuildIndex() {
            index = buildMenuSearchIndex();
        }

        function hideResults() {
            results.style.display = 'none';
            results.innerHTML = '';
            activeIdx = -1;
            visible = [];
        }

        function renderResults(list) {
            visible = list;
            activeIdx = list.length ? 0 : -1;
            if (!list.length) {
                results.innerHTML = '<div class="auragold-menu-search-empty">No matching menu found</div>';
                results.style.display = 'block';
                return;
            }
            var q = normText(input.value).toLowerCase();
            var html = '';
            list.slice(0, 24).forEach(function (item, i) {
                var path = item.menu;
                if (item.group) {
                    path += ' › ' + item.group;
                }
                html += '<button type="button" class="auragold-menu-search-item' + (i === 0 ? ' is-active' : '') + '" data-href="' + escapeHtml(item.href) + '" role="option">'
                    + '<span class="auragold-menu-search-item__label">' + highlightMatch(item.label, q) + '</span>'
                    + '<span class="auragold-menu-search-item__path">' + escapeHtml(path) + '</span>'
                    + '</button>';
            });
            results.innerHTML = html;
            results.style.display = 'block';
        }

        function search(q) {
            q = normText(q).toLowerCase();
            if (!q) {
                hideResults();
                return;
            }
            if (!index.length) {
                rebuildIndex();
            }
            var out = index.filter(function (item) {
                return item.searchText.indexOf(q) !== -1;
            });
            renderResults(out);
        }

        function goTo(href) {
            if (!href) {
                return;
            }
            window.location.href = href;
        }

        function setActive(idx) {
            if (!visible.length) {
                return;
            }
            if (idx < 0) {
                idx = visible.length - 1;
            }
            if (idx >= visible.length) {
                idx = 0;
            }
            activeIdx = idx;
            var buttons = results.querySelectorAll('.auragold-menu-search-item');
            buttons.forEach(function (btn, i) {
                btn.classList.toggle('is-active', i === activeIdx);
            });
            var activeBtn = buttons[activeIdx];
            if (activeBtn && activeBtn.scrollIntoView) {
                activeBtn.scrollIntoView({ block: 'nearest' });
            }
        }

        input.addEventListener('input', function () {
            search(input.value);
        });

        input.addEventListener('focus', function () {
            if (normText(input.value)) {
                search(input.value);
            }
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (results.style.display === 'none') {
                    search(input.value);
                } else {
                    setActive(activeIdx + 1);
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setActive(activeIdx - 1);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (activeIdx >= 0 && visible[activeIdx]) {
                    goTo(visible[activeIdx].href);
                } else if (visible.length === 1) {
                    goTo(visible[0].href);
                }
            } else if (e.key === 'Escape') {
                hideResults();
                input.blur();
            }
        });

        results.addEventListener('mousedown', function (e) {
            e.preventDefault();
        });

        results.addEventListener('click', function (e) {
            var btn = e.target.closest('.auragold-menu-search-item');
            if (!btn) {
                return;
            }
            goTo(btn.getAttribute('data-href'));
        });

        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target)) {
                hideResults();
            }
        });

        rebuildIndex();
        window.auragoldRebuildMenuSearchIndex = rebuildIndex;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMenuSearch);
    } else {
        initMenuSearch();
    }
})();
