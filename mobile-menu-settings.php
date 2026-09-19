<?php

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auragold_require_login.php';
require_once __DIR__ . '/includes/auragold_mobile_menu_settings.php';

auragold_require_login_or_exit();

$conn = isset($conn) && $conn instanceof mysqli ? $conn : null;
if ($conn === null) {
    die('Database connection is not available.');
}

auragold_ensure_mobile_menu_settings_table($conn);

$flash = ['type' => '', 'message' => ''];
if (isset($_SESSION['mobile_menu_settings_flash']) && is_array($_SESSION['mobile_menu_settings_flash'])) {
    $flash = array_merge(
        $flash,
        array_intersect_key(
            $_SESSION['mobile_menu_settings_flash'],
            array_flip(['type', 'message'])
        )
    );
    unset($_SESSION['mobile_menu_settings_flash']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (($_POST['mobile_menu_action'] ?? '') === 'save')) {
    $ok = auragold_mobile_menu_save_from_post($conn, $_POST);
    $_SESSION['mobile_menu_settings_flash'] = [
        'type'    => $ok ? 'success' : 'danger',
        'message' => $ok
            ? (function_exists('auragold_t') ? (string) auragold_t('mobile_menu_settings.saved') : 'Mobile menu settings saved.')
            : (function_exists('auragold_t') ? (string) auragold_t('mobile_menu_settings.save_error') : 'Could not save mobile menu settings.'),
    ];
    header('Location: mobile-menu-settings.php');
    exit;
}

$checkedModules = auragold_mobile_menu_form_checked_modules($conn);
$checkedPages   = auragold_mobile_menu_form_checked_pages($conn);
$menuTree       = auragold_sidebar_permission_tree_data();

$t = static function (string $key, string $fallback = ''): string {
    if (function_exists('auragold_t')) {
        $s = (string) auragold_t($key);
        if ($s !== '' && $s !== $key) {
            return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        }
    }

    return htmlspecialchars($fallback !== '' ? $fallback : $key, ENT_QUOTES, 'UTF-8');
};

$page_title = function_exists('auragold_t')
    ? (string) auragold_t('mobile_menu_settings.page_title')
    : 'Mobile Menu Setting - Set Software - ' . auragold_app_name();

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
</head>
<style>
html, body { height: 100%; overflow-x: hidden; }
.layout-content { height: calc(100vh - 60px); overflow: hidden; display: flex; flex-direction: column; }
.set-software-wrapper { flex: 1; min-height: 0; }
.set-software-main { overflow-y: auto; }
.auragold-mobile-menu-page { padding: 24px; max-width: 960px; }
.auragold-mobile-menu-page h1 { font-size: 1.35rem; font-weight: 700; color: #0f172a; margin: 0 0 8px; }
.auragold-mobile-menu-page .lead { color: #64748b; font-size: 0.9rem; margin-bottom: 20px; }
.mm-module-card { border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 14px; background: #fff; overflow: hidden; }
.mm-module-head { display: flex; align-items: center; gap: 10px; padding: 12px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
.mm-module-head label { margin: 0; font-weight: 700; color: #0f172a; cursor: pointer; flex: 1; }
.mm-pages { padding: 10px 16px 14px; display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 8px 16px; }
.mm-page-item label { display: flex; align-items: flex-start; gap: 8px; margin: 0; font-size: 0.875rem; color: #334155; cursor: pointer; }
.mm-page-item input { margin-top: 3px; flex-shrink: 0; }
.mm-toolbar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
.mm-toolbar .btn { font-size: 0.85rem; }
.mm-page-item.is-disabled { opacity: 0.45; pointer-events: none; }
</style>
<body>
<?php include 'sidebar.php'; ?>
<div class="layout-content">
    <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
        <div class="set-software-wrapper">
            <?php include 'set-software-sidebar.php'; ?>
            <div class="set-software-main">
                <?php include __DIR__ . '/includes/set-software-branch-banner.php'; ?>
                <div class="auragold-mobile-menu-page">
                    <h1><?php echo $t('mobile_menu_settings.heading', 'Mobile Menu Setting'); ?></h1>
                    <p class="lead"><?php echo $t('mobile_menu_settings.lead', 'Choose which main menus and sub-menus appear in the mobile navigation drawer. Desktop navigation always shows all menus allowed by user permissions.'); ?></p>

                    <?php if ($flash['message'] !== ''): ?>
                        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="mobile-menu-settings.php" id="mobile-menu-settings-form">
                        <input type="hidden" name="mobile_menu_action" value="save">

                        <div class="mm-toolbar">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="mm-select-all"><?php echo $t('mobile_menu_settings.select_all', 'Select all'); ?></button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="mm-clear-all"><?php echo $t('mobile_menu_settings.clear_all', 'Clear all'); ?></button>
                        </div>

                        <?php foreach ($menuTree as $mod): ?>
                            <?php
                            if (empty($mod['menu'])) {
                                continue;
                            }
                            $moduleKey = (string) ($mod['key'] ?? '');
                            if ($moduleKey === '') {
                                continue;
                            }
                            $moduleLabel = (string) ($mod['label'] ?? $moduleKey);
                            $moduleOn    = !empty($checkedModules[$moduleKey]);
                            ?>
                            <section class="mm-module-card" data-mm-module="<?php echo htmlspecialchars($moduleKey, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="mm-module-head">
                                    <input type="checkbox" class="mm-module-cb" name="mobile_menu_modules[]" value="<?php echo htmlspecialchars($moduleKey, ENT_QUOTES, 'UTF-8'); ?>" id="mm-mod-<?php echo htmlspecialchars($moduleKey, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $moduleOn ? ' checked' : ''; ?>>
                                    <label for="mm-mod-<?php echo htmlspecialchars($moduleKey, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($moduleLabel, ENT_QUOTES, 'UTF-8'); ?></label>
                                </div>
                                <div class="mm-pages">
                                    <?php foreach ($mod['pages'] ?? [] as $page): ?>
                                        <?php
                                        $pageKey = (string) ($page['key'] ?? '');
                                        if ($pageKey === '') {
                                            continue;
                                        }
                                        $pageId  = $moduleKey . '.' . $pageKey;
                                        $pageOn  = !empty($checkedPages[$pageId]);
                                        $group   = isset($page['group']) ? (string) $page['group'] : '';
                                        $label   = (string) ($page['label'] ?? $pageKey);
                                        if ($group !== '') {
                                            $label = $group . ' — ' . $label;
                                        }
                                        ?>
                                        <div class="mm-page-item<?php echo $moduleOn ? '' : ' is-disabled'; ?>" data-mm-page-wrap="<?php echo htmlspecialchars($pageId, ENT_QUOTES, 'UTF-8'); ?>">
                                            <label>
                                                <input type="checkbox" class="mm-page-cb" name="mobile_menu_pages[]" value="<?php echo htmlspecialchars($pageId, ENT_QUOTES, 'UTF-8'); ?>" data-module="<?php echo htmlspecialchars($moduleKey, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $pageOn ? ' checked' : ''; ?><?php echo $moduleOn ? '' : ' disabled'; ?>>
                                                <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>

                        <button type="submit" class="btn btn-primary"><?php echo $t('mobile_menu_settings.save', 'Save settings'); ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var form = document.getElementById('mobile-menu-settings-form');
    if (!form) return;

    function syncModuleCard(card) {
        var modCb = card.querySelector('.mm-module-cb');
        if (!modCb) return;
        var on = modCb.checked;
        card.querySelectorAll('.mm-page-item').forEach(function (wrap) {
            wrap.classList.toggle('is-disabled', !on);
            var pcb = wrap.querySelector('.mm-page-cb');
            if (pcb) {
                pcb.disabled = !on;
                if (!on) {
                    pcb.checked = false;
                }
            }
        });
    }

    form.querySelectorAll('.mm-module-card').forEach(function (card) {
        syncModuleCard(card);
        var modCb = card.querySelector('.mm-module-cb');
        if (modCb) {
            modCb.addEventListener('change', function () {
                if (modCb.checked) {
                    card.querySelectorAll('.mm-page-cb').forEach(function (pcb) {
                        pcb.checked = true;
                    });
                }
                syncModuleCard(card);
            });
        }
    });

    document.getElementById('mm-select-all')?.addEventListener('click', function () {
        form.querySelectorAll('.mm-module-cb, .mm-page-cb').forEach(function (cb) {
            cb.checked = true;
            cb.disabled = false;
        });
        form.querySelectorAll('.mm-module-card').forEach(function (card) {
            card.querySelectorAll('.mm-page-item').forEach(function (w) {
                w.classList.remove('is-disabled');
            });
        });
    });

    document.getElementById('mm-clear-all')?.addEventListener('click', function () {
        form.querySelectorAll('.mm-module-cb').forEach(function (cb) { cb.checked = false; });
        form.querySelectorAll('.mm-page-cb').forEach(function (cb) {
            cb.checked = false;
            cb.disabled = true;
        });
        form.querySelectorAll('.mm-page-item').forEach(function (w) {
            w.classList.add('is-disabled');
        });
    });
})();
</script>
</body>
</html>
