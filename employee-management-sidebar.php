<?php
/**
 * Employee Management left sidebar — include on employee-*.php pages.
 * Highlights current page via basename of PHP_SELF.
 */
require_once __DIR__ . '/includes/auragold_employee_management_menu.php';
require_once __DIR__ . '/includes/auragold_sidebar_nav_permissions.php';

$current_page = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$em_menu_items = auragold_employee_management_menu_items();
$em_sidebar_title = function_exists('auragold_t')
    ? htmlspecialchars(auragold_t('nav.employee_management'), ENT_QUOTES, 'UTF-8')
    : 'Employee Management';
$em_collapse_show = function_exists('auragold_t')
    ? htmlspecialchars(auragold_t('set_software.collapse_show'), ENT_QUOTES, 'UTF-8')
    : 'Show menu';
$em_collapse_hide = function_exists('auragold_t')
    ? htmlspecialchars(auragold_t('set_software.collapse_hide'), ENT_QUOTES, 'UTF-8')
    : 'Hide menu';
$em_menu_open = function_exists('auragold_t')
    ? htmlspecialchars(auragold_t('set_software.open_menu'), ENT_QUOTES, 'UTF-8')
    : 'Open Employee Management menu';
$em_menu_close = function_exists('auragold_t')
    ? htmlspecialchars(auragold_t('set_software.close_menu'), ENT_QUOTES, 'UTF-8')
    : 'Close menu';
?>
<div class="set-software-drawer-backdrop" id="employeeManagementDrawerBackdrop" aria-hidden="true"></div>
<aside class="set-software-sidebar" id="employee-management-nav-aside" aria-label="<?php echo $em_sidebar_title; ?>">
    <div class="set-software-sidebar-mobile-head d-lg-none">
        <div class="set-software-sidebar-title"><?php echo $em_sidebar_title; ?></div>
        <button type="button" class="set-software-drawer-close" id="employeeManagementDrawerClose" aria-label="<?php echo $em_menu_close; ?>">
            <i class="feather icon-x" aria-hidden="true"></i>
        </button>
    </div>
    <div class="set-software-sidebar-head d-none d-lg-flex">
        <div class="set-software-sidebar-title"><?php echo $em_sidebar_title; ?></div>
        <button type="button" class="set-software-collapse-tab" title="<?php echo $em_collapse_hide; ?>" aria-expanded="true" aria-controls="employee-management-nav-aside" data-auragold-title-show="<?php echo $em_collapse_show; ?>" data-auragold-title-hide="<?php echo $em_collapse_hide; ?>"><i class="feather icon-chevron-left" aria-hidden="true"></i></button>
    </div>
    <nav class="set-software-sidebar-menu" aria-label="<?php echo $em_sidebar_title; ?>">
        <?php foreach ($em_menu_items as $emItem):
            if (!auragold_employee_management_can_view_page($emItem['key'])) {
                continue;
            }
            $emFile = (string) ($emItem['file'] ?? '');
            $emHref = (string) ($emItem['href'] ?? $emFile);
            if ($emFile === '' && ($emHref === '' || $emHref === '#')) {
                // Placeholder menu entries (no page yet) — still list them.
                $emHref = '#';
            } elseif ($emFile === '') {
                continue;
            } else {
                $emHref = $emFile;
            }
            $emActive = ($emFile !== '' && $current_page === $emFile);
            $emIcon = htmlspecialchars((string) ($emItem['icon'] ?? 'icon-circle'), ENT_QUOTES, 'UTF-8');
            $emLabel = htmlspecialchars((string) ($emItem['label'] ?? ''), ENT_QUOTES, 'UTF-8');
        ?>
        <a href="<?php echo htmlspecialchars($emHref, ENT_QUOTES, 'UTF-8'); ?>" class="set-software-nav-item<?php echo $emActive ? ' active' : ''; ?>">
            <span><i class="feather <?php echo $emIcon; ?>"></i> <?php echo $emLabel; ?></span>
            <i class="feather icon-chevron-right"></i>
        </a>
        <?php endforeach; ?>
    </nav>
</aside>
<script>
(function () {
    var emMenuOpenLabel = <?php echo json_encode($em_menu_open, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var emMenuTitle = <?php echo json_encode($em_sidebar_title, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var storageKey = 'employeeManagementSidebarCollapsed';

    function emIsMobile() {
        return window.matchMedia('(max-width: 991.98px)').matches;
    }

    function initEmployeeManagementCollapse(wrap) {
        var tab = wrap.querySelector('.set-software-collapse-tab');
        if (!tab || tab.dataset.emCollapseBound) return;
        tab.dataset.emCollapseBound = '1';
        var icon = tab.querySelector('i');
        var aside = wrap.querySelector('.set-software-sidebar');
        var titleShow = tab.getAttribute('data-auragold-title-show') || 'Show menu';
        var titleHide = tab.getAttribute('data-auragold-title-hide') || 'Hide menu';
        function apply(collapsed) {
            if (emIsMobile()) collapsed = false;
            wrap.classList.toggle('set-software-sidebar-collapsed', collapsed);
            tab.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            tab.title = collapsed ? titleShow : titleHide;
            if (aside) aside.setAttribute('aria-hidden', collapsed ? 'true' : 'false');
            if (icon) icon.className = 'feather ' + (collapsed ? 'icon-chevron-right' : 'icon-chevron-left');
            if (!emIsMobile()) {
                try { localStorage.setItem(storageKey, collapsed ? '1' : '0'); } catch (e) {}
            }
        }
        var stored = null;
        try { stored = localStorage.getItem(storageKey); } catch (e) {}
        if (stored === '1' && !emIsMobile()) apply(true);
        tab.addEventListener('click', function () {
            if (emIsMobile()) return;
            apply(!wrap.classList.contains('set-software-sidebar-collapsed'));
        });
    }

    function initEmployeeManagementMobileDrawer(wrap) {
        if (wrap.dataset.emMobileBound) return;
        wrap.dataset.emMobileBound = '1';
        var backdrop = document.getElementById('employeeManagementDrawerBackdrop');
        var aside = document.getElementById('employee-management-nav-aside');
        var closeBtn = document.getElementById('employeeManagementDrawerClose');
        var openBtn = document.getElementById('employeeManagementMobileMenuBtn');

        if (!openBtn) {
            var main = wrap.querySelector('.set-software-main');
            if (main) {
                var bar = document.createElement('div');
                bar.className = 'set-software-mobile-toolbar d-lg-none';
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'set-software-mobile-menu-btn';
                btn.id = 'employeeManagementMobileMenuBtn';
                btn.setAttribute('aria-label', emMenuOpenLabel);
                btn.setAttribute('aria-expanded', 'false');
                btn.setAttribute('aria-controls', 'employee-management-nav-aside');
                btn.innerHTML = '<i class="feather icon-menu" aria-hidden="true"></i><span></span>';
                btn.querySelector('span').textContent = emMenuTitle;
                bar.appendChild(btn);
                main.insertBefore(bar, main.firstChild);
                openBtn = btn;
            }
        }

        function openDrawer() {
            if (!emIsMobile()) return;
            document.body.classList.add('set-software-drawer-open');
            wrap.classList.remove('set-software-sidebar-collapsed');
            if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
            if (backdrop) backdrop.setAttribute('aria-hidden', 'false');
            if (aside) aside.setAttribute('aria-hidden', 'false');
        }
        function closeDrawer() {
            document.body.classList.remove('set-software-drawer-open');
            if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
            if (backdrop) backdrop.setAttribute('aria-hidden', 'true');
        }

        if (openBtn) {
            openBtn.addEventListener('click', function () {
                if (document.body.classList.contains('set-software-drawer-open')) closeDrawer();
                else openDrawer();
            });
        }
        if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
        if (backdrop) backdrop.addEventListener('click', closeDrawer);
        if (aside) {
            aside.addEventListener('click', function (e) {
                var link = e.target.closest('a.set-software-nav-item, a.set-software-nav-sub-item');
                if (link && emIsMobile()) closeDrawer();
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && document.body.classList.contains('set-software-drawer-open')) {
                closeDrawer();
            }
        });
        var mq = window.matchMedia('(max-width: 991.98px)');
        var onMq = function () { if (!emIsMobile()) closeDrawer(); };
        if (typeof mq.addEventListener === 'function') mq.addEventListener('change', onMq);
        else if (typeof mq.addListener === 'function') mq.addListener(onMq);
    }

    function bootEmployeeManagementNav() {
        document.querySelectorAll('.set-software-wrapper').forEach(function (wrap) {
            if (!wrap.querySelector('#employee-management-nav-aside')) return;
            initEmployeeManagementCollapse(wrap);
            initEmployeeManagementMobileDrawer(wrap);
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootEmployeeManagementNav);
    } else {
        bootEmployeeManagementNav();
    }
})();
</script>
