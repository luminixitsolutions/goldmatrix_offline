<?php
/**
 * Common Set Software sidebar – include in set-software.php, masters.php, etc.
 * Highlights current page via $current_page (basename of PHP_SELF).
 */
require_once __DIR__ . '/includes/session_login_type.php';
require_once __DIR__ . '/includes/auragold_set_software_menu_lock.php';

$current_page = basename($_SERVER['PHP_SELF']);
$show_branches_menu = !empty($_SESSION['Admin']);
$region_sub_pages = ['master-country.php', 'master-state.php', 'master-city.php'];
$region_nav_open = in_array($current_page, $region_sub_pages, true);
$ewaybill_sub_pages = ['ewaybill-api-settings.php', 'ewaybill-authentication.php'];
$branches_sub_pages = ['branches.php', 'set-software.php', 'barcode-prefix-settings.php', 'menu-settings.php'];

$ss_menu_conn = isset($conn) && $conn instanceof mysqli ? $conn : null;
$ss_locked_menu_map = [];
if ($ss_menu_conn) {
    foreach (auragold_set_software_menu_locked_keys($ss_menu_conn) as $lk) {
        $ss_locked_menu_map[$lk] = true;
    }
}

$auragold_ss_nav_link = static function (
    string $href,
    string $menuKey,
    string $labelHtml,
    string $iconClass,
    bool $isSub = false,
    bool $isActive = false
) use ($ss_menu_conn, $ss_locked_menu_map): void {
    $classes = $isSub ? 'set-software-nav-sub-item' : 'set-software-nav-item';
    if ($isActive) {
        $classes .= ' active';
    }

    $isLocked = $ss_menu_conn && !empty($ss_locked_menu_map[$menuKey])
        && !auragold_set_software_menu_is_session_unlocked($menuKey);
    // Already on this page — no password popup when clicking the active menu again.
    if ($isActive && $isLocked) {
        $isLocked = false;
    }
    if ($isLocked) {
        $classes .= ' set-software-nav-locked';
    }

    $lockIcon = $isLocked ? ' <i class="feather icon-lock ss-nav-lock-icon" aria-hidden="true"></i>' : '';
    $chevron = $isSub ? '' : '<i class="feather icon-chevron-right"></i>';

    if ($isLocked) {
        echo '<a href="#" class="' . $classes . '" data-ss-menu-key="' . htmlspecialchars($menuKey, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-ss-menu-href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-ss-menu-label="' . htmlspecialchars(strip_tags($labelHtml), ENT_QUOTES, 'UTF-8') . '">';
    } else {
        echo '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="' . $classes . '">';
    }

    if ($isSub) {
        echo $labelHtml . $lockIcon;
    } else {
        echo '<span><i class="feather ' . htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') . '"></i> '
            . $labelHtml . $lockIcon . '</span>' . $chevron;
    }
    echo '</a>';
};

$auragold_set_ss_title = function_exists('auragold_t')
    ? htmlspecialchars(auragold_t('set_software.title'), ENT_QUOTES, 'UTF-8')
    : 'Set Software';
$auragold_t_ss = static function ($key) {
    return function_exists('auragold_t')
        ? htmlspecialchars(auragold_t($key), ENT_QUOTES, 'UTF-8')
        : htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
};
$auragold_collapse_show = $auragold_t_ss('set_software.collapse_show');
$auragold_collapse_hide = $auragold_t_ss('set_software.collapse_hide');
$auragold_ss_menu_open = function_exists('auragold_t')
    ? htmlspecialchars(auragold_t('set_software.open_menu'), ENT_QUOTES, 'UTF-8')
    : 'Open Set Software menu';
$auragold_ss_menu_close = function_exists('auragold_t')
    ? htmlspecialchars(auragold_t('set_software.close_menu'), ENT_QUOTES, 'UTF-8')
    : 'Close menu';
?>
<div class="set-software-drawer-backdrop" id="setSoftwareDrawerBackdrop" aria-hidden="true"></div>
<!-- Left Set Software sidebar (common include) -->
<aside class="set-software-sidebar" id="set-software-nav-aside" aria-label="<?php echo $auragold_set_ss_title; ?>">
    <div class="set-software-sidebar-mobile-head d-lg-none">
        <div class="set-software-sidebar-title"><?php echo $auragold_set_ss_title; ?></div>
        <button type="button" class="set-software-drawer-close" id="setSoftwareDrawerClose" aria-label="<?php echo $auragold_ss_menu_close; ?>">
            <i class="feather icon-x" aria-hidden="true"></i>
        </button>
    </div>
    <div class="set-software-sidebar-head d-none d-lg-flex">
        <div class="set-software-sidebar-title"><?php echo $auragold_set_ss_title; ?></div>
        <button type="button" class="set-software-collapse-tab" title="<?php echo $auragold_collapse_hide; ?>" aria-expanded="true" aria-controls="set-software-nav-aside" data-auragold-title-show="<?php echo $auragold_collapse_show; ?>" data-auragold-title-hide="<?php echo $auragold_collapse_hide; ?>"><i class="feather icon-chevron-left" aria-hidden="true"></i></button>
    </div>
    <nav class="set-software-sidebar-menu" aria-label="<?php echo $auragold_set_ss_title; ?>">
    <?php
    $auragold_ss_nav_link(
        'font-settings.php',
        'font_setting',
        $auragold_t_ss('set_software.font_setting'),
        'icon-type',
        false,
        $current_page === 'font-settings.php'
    );
    $auragold_ss_nav_link(
        'language-settings.php',
        'language_setting',
        $auragold_t_ss('set_software.language_setting'),
        'icon-globe',
        false,
        $current_page === 'language-settings.php'
    );
    $auragold_ss_nav_link(
        'mail-settings.php',
        'mail_setting',
        $auragold_t_ss('set_software.mail_setting'),
        'icon-mail',
        false,
        $current_page === 'mail-settings.php'
    );
    $auragold_ss_nav_link('extra-fields.php', 'extra_fields', 'Extra Fields', 'icon-sliders', false, $current_page === 'extra-fields.php');
    $auragold_ss_nav_link('credit-card.php', 'credit_card', 'Credit Card', 'icon-credit-card', false, $current_page === 'credit-card.php');
    $auragold_ss_nav_link(
        'masters.php',
        'masters',
        $auragold_t_ss('set_software.masters'),
        'icon-grid',
        false,
        in_array($current_page, ['masters.php', 'metal-rates-url.php', 'set-sale-percentage.php'], true)
    );
    ?>

    <details class="set-software-nav-group"<?php echo $region_nav_open ? ' open' : ''; ?>>
        <summary class="set-software-nav-summary">
            <span><i class="feather icon-map-pin"></i> <?php echo $auragold_t_ss('set_software.region'); ?></span>
            <i class="feather icon-chevron-down set-software-nav-summary-chevron"></i>
        </summary>
        <div class="set-software-nav-sub">
            <?php
            $auragold_ss_nav_link('master-country.php', 'region.country', $auragold_t_ss('set_software.region_country'), '', true, $current_page === 'master-country.php');
            $auragold_ss_nav_link('master-state.php', 'region.state', $auragold_t_ss('set_software.region_state'), '', true, $current_page === 'master-state.php');
            $auragold_ss_nav_link('master-city.php', 'region.city', $auragold_t_ss('set_software.region_city'), '', true, $current_page === 'master-city.php');
            ?>
        </div>
    </details>
    <?php if ($show_branches_menu): ?>
    <?php
    $auragold_ss_nav_link(
        'branches.php',
        'branches',
        $auragold_t_ss('set_software.branches'),
        'icon-layers',
        false,
        in_array($current_page, $branches_sub_pages, true)
    );
    ?>
    <?php endif; ?>
    <?php
    $auragold_ss_nav_link(
        'accounting-masters.php',
        'accounting_masters',
        $auragold_t_ss('set_software.accounting_masters'),
        'icon-book',
        false,
        $current_page === 'accounting-masters.php'
    );
    $auragold_ss_nav_link(
        'exchange-rate.php',
        'exchange_rate',
        'Exchange Rate',
        'icon-trending-up',
        false,
        $current_page === 'exchange-rate.php'
    );
    $auragold_ss_nav_link(
        'voucher-setting.php',
        'voucher_setting',
        $auragold_t_ss('set_software.voucher_setting'),
        'icon-file-text',
        false,
        $current_page === 'voucher-setting.php'
    );
    $auragold_ss_nav_link(
        'bill-series.php',
        'bill_series',
        $auragold_t_ss('set_software.bill_series'),
        'icon-hash',
        false,
        $current_page === 'bill-series.php'
    );
    $auragold_ss_nav_link(
        'invoice-print-settings.php',
        'invoice_print_setting',
        $auragold_t_ss('set_software.invoice_print_setting'),
        'icon-printer',
        false,
        $current_page === 'invoice-print-settings.php'
    );
    $auragold_ss_nav_link(
        'reward-point-coupons-referral.php',
        'reward_point',
        $auragold_t_ss('set_software.reward_point_coupons_referral'),
        'icon-award',
        false,
        $current_page === 'reward-point-coupons-referral.php'
    );
    $auragold_ss_nav_link(
        'ewaybill-api-settings.php',
        'eway_bill.api',
        $auragold_t_ss('set_software.eway_bill_group'),
        'icon-package',
        false,
        in_array($current_page, $ewaybill_sub_pages, true)
    );
    ?>
    </nav>
</aside>

<div class="ss-menu-lock-modal-overlay" id="ssMenuLockOverlay" aria-hidden="true">
    <div class="ss-menu-lock-modal" role="dialog" aria-labelledby="ssMenuLockTitle">
        <button type="button" class="ss-menu-lock-close" id="ssMenuLockClose" aria-label="Close">&times;</button>
        <h2 id="ssMenuLockTitle"><?php echo $auragold_t_ss('set_software.menu_lock_gate_title'); ?></h2>
        <p class="ss-menu-lock-lead"><?php echo $auragold_t_ss('set_software.menu_lock_gate_lead'); ?></p>
        <p class="ss-menu-lock-name" id="ssMenuLockName"></p>
        <input type="password" class="form-control" id="ssMenuLockInput" autocomplete="current-password" placeholder="<?php echo $auragold_t_ss('set_software.menu_lock_password'); ?>">
        <p class="ss-menu-lock-err" id="ssMenuLockError" role="alert"></p>
        <p class="ss-menu-lock-hint"><?php echo $auragold_t_ss('set_software.menu_lock_master_hint'); ?></p>
        <div class="ss-menu-lock-btns">
            <button type="button" class="btn btn-primary" id="ssMenuLockSubmit"><?php echo $auragold_t_ss('set_software.menu_lock_unlock'); ?></button>
            <button type="button" class="btn btn-outline-secondary" id="ssMenuLockCancel">Cancel</button>
        </div>
    </div>
</div>

<script>
(function () {
    var ssMenuOpenLabel = <?php echo json_encode($auragold_ss_menu_open, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var ssMenuTitle = <?php echo json_encode($auragold_set_ss_title, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    function ssIsMobile() {
        return window.matchMedia('(max-width: 991.98px)').matches;
    }

    function initSetSoftwareCollapse(wrap) {
        var tab = wrap.querySelector('.set-software-collapse-tab');
        if (!tab || tab.dataset.ssCollapseBound) return;
        tab.dataset.ssCollapseBound = '1';
        var icon = tab.querySelector('i');
        var aside = wrap.querySelector('.set-software-sidebar');
        var titleShow = tab.getAttribute('data-auragold-title-show') || 'Show menu';
        var titleHide = tab.getAttribute('data-auragold-title-hide') || 'Hide menu';
        function apply(collapsed) {
            if (ssIsMobile()) {
                collapsed = false;
            }
            wrap.classList.toggle('set-software-sidebar-collapsed', collapsed);
            tab.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            tab.title = collapsed ? titleShow : titleHide;
            if (aside) aside.setAttribute('aria-hidden', collapsed ? 'true' : 'false');
            if (icon) {
                icon.className = 'feather ' + (collapsed ? 'icon-chevron-right' : 'icon-chevron-left');
            }
            if (!ssIsMobile()) {
                try { localStorage.setItem('setSoftwareSidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
            }
        }
        var stored = null;
        try { stored = localStorage.getItem('setSoftwareSidebarCollapsed'); } catch (e) {}
        if (stored === '1' && !ssIsMobile()) apply(true);
        tab.addEventListener('click', function () {
            if (ssIsMobile()) return;
            apply(!wrap.classList.contains('set-software-sidebar-collapsed'));
        });
    }

    function initSetSoftwareMobileDrawer(wrap) {
        if (wrap.dataset.ssMobileBound) return;
        wrap.dataset.ssMobileBound = '1';
        var backdrop = document.getElementById('setSoftwareDrawerBackdrop');
        var aside = document.getElementById('set-software-nav-aside');
        var closeBtn = document.getElementById('setSoftwareDrawerClose');
        var openBtn = document.getElementById('setSoftwareMobileMenuBtn');

        if (!openBtn) {
            var main = wrap.querySelector('.set-software-main');
            if (main) {
                var bar = document.createElement('div');
                bar.className = 'set-software-mobile-toolbar d-lg-none';
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'set-software-mobile-menu-btn';
                btn.id = 'setSoftwareMobileMenuBtn';
                btn.setAttribute('aria-label', ssMenuOpenLabel);
                btn.setAttribute('aria-expanded', 'false');
                btn.setAttribute('aria-controls', 'set-software-nav-aside');
                btn.innerHTML = '<i class="feather icon-menu" aria-hidden="true"></i><span></span>';
                btn.querySelector('span').textContent = ssMenuTitle;
                bar.appendChild(btn);
                main.insertBefore(bar, main.firstChild);
                openBtn = btn;
            }
        }

        function openDrawer() {
            if (!ssIsMobile()) return;
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
                if (e.target.closest('a.set-software-nav-locked')) return;
                var link = e.target.closest('a.set-software-nav-item, a.set-software-nav-sub-item');
                if (link && ssIsMobile()) closeDrawer();
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && document.body.classList.contains('set-software-drawer-open')) {
                closeDrawer();
            }
        });
        var mq = window.matchMedia('(max-width: 991.98px)');
        var onMq = function () {
            if (!ssIsMobile()) closeDrawer();
        };
        if (typeof mq.addEventListener === 'function') {
            mq.addEventListener('change', onMq);
        } else if (typeof mq.addListener === 'function') {
            mq.addListener(onMq);
        }
    }

    function initSetSoftwareMenuLockModal() {
        var overlay = document.getElementById('ssMenuLockOverlay');
        if (overlay && overlay.parentElement !== document.body) {
            document.body.appendChild(overlay);
        }
        var input = document.getElementById('ssMenuLockInput');
        var err = document.getElementById('ssMenuLockError');
        var nameEl = document.getElementById('ssMenuLockName');
        var submit = document.getElementById('ssMenuLockSubmit');
        var cancel = document.getElementById('ssMenuLockCancel');
        var close = document.getElementById('ssMenuLockClose');
        var pendingKey = '';
        var pendingHref = '';

        function showErr(msg) {
            if (err) err.textContent = msg || '';
        }

        function openModal(key, href, label) {
            pendingKey = key || '';
            pendingHref = href || '';
            if (nameEl) nameEl.textContent = label || '';
            if (input) {
                input.value = '';
                input.disabled = false;
            }
            if (submit) submit.disabled = false;
            showErr('');
            if (overlay) {
                overlay.classList.add('is-open');
                overlay.setAttribute('aria-hidden', 'false');
            }
            setTimeout(function () { if (input) input.focus(); }, 50);
        }

        function closeModal() {
            pendingKey = '';
            pendingHref = '';
            if (overlay) {
                overlay.classList.remove('is-open');
                overlay.setAttribute('aria-hidden', 'true');
            }
        }

        function doSubmit() {
            if (!pendingKey || !pendingHref || !input) return;
            var pw = String(input.value || '');
            if (!pw.trim()) {
                showErr('Enter password');
                return;
            }
            if (submit) submit.disabled = true;
            showErr('');
            var body = 'menu_key=' + encodeURIComponent(pendingKey) + '&password=' + encodeURIComponent(pw) + '&return=' + encodeURIComponent(pendingHref);
            fetch('ajax/verify-set-software-menu-password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body,
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.status === 'ok' && d.redirect) {
                        window.location.href = d.redirect;
                        return;
                    }
                    if (submit) submit.disabled = false;
                    showErr((d && d.message) ? d.message : 'Incorrect password');
                })
                .catch(function () {
                    if (submit) submit.disabled = false;
                    showErr('Network error');
                });
        }

        document.querySelectorAll('a.set-software-nav-locked').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (el.classList.contains('active')) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                openModal(
                    el.getAttribute('data-ss-menu-key') || '',
                    el.getAttribute('data-ss-menu-href') || '',
                    el.getAttribute('data-ss-menu-label') || ''
                );
            });
        });

        if (submit) submit.addEventListener('click', doSubmit);
        if (cancel) cancel.addEventListener('click', closeModal);
        if (close) close.addEventListener('click', closeModal);
        if (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    doSubmit();
                }
            });
        }
        if (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closeModal();
            });
        }
    }

    function bootSetSoftwareNav() {
        document.querySelectorAll('.set-software-wrapper').forEach(function (wrap) {
            initSetSoftwareCollapse(wrap);
            initSetSoftwareMobileDrawer(wrap);
        });
        initSetSoftwareMenuLockModal();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootSetSoftwareNav);
    } else {
        bootSetSoftwareNav();
    }
})();
</script>
