<?php

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auragold_require_login.php';
require_once __DIR__ . '/includes/auragold_branch_data_scope.php';
require_once __DIR__ . '/includes/auragold_barcode_prefix_settings.php';

auragold_require_login_or_exit();

if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();

auragold_ensure_branch_id_on_settings_tables($conn);
$settings_branch_id = auragold_settings_branch_id();
auragold_ensure_branch_barcode_prefix_columns();

$msg = '';
$err = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_barcode_prefix_settings'])) {
    $save_branch = (int) ($settings_branch_id ?? 0);
    if ($save_branch <= 0) {
        $err = 'Invalid branch. Please reload the page.';
    } elseif (auragold_save_branch_barcode_prefix_settings($save_branch, $_POST)) {
        header('Location: barcode-prefix-settings.php?branch_id=' . $save_branch . '&saved=1');
        exit;
    } else {
        $err = 'Could not save barcode prefix settings. Please try again.';
    }
}

if (isset($_GET['saved'])) {
    $msg = 'Barcode prefix settings saved for this branch.';
}

$settings = auragold_get_branch_barcode_prefix_settings($conn, $settings_branch_id);

$t = static function (string $key, string $fallback = ''): string {
    if (function_exists('auragold_t')) {
        $s = (string) auragold_t($key);
        if ($s !== '' && $s !== $key) {
            return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        }
    }
    return htmlspecialchars($fallback !== '' ? $fallback : $key, ENT_QUOTES, 'UTF-8');
};

$page_title = $t('barcode_prefix_settings.page_title', 'Barcode Prefix Setting - Set Software - ' . auragold_app_name());

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title><?php echo $page_title; ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
    <style>
        :root { --bps-navy: #11294b; }
        .barcode-prefix-page { padding: 24px; max-width: 960px; margin: 0 auto; }
        .barcode-prefix-page h1 { font-size: 1.45rem; font-weight: 700; color: #0f172a; margin: 0 0 8px; }
        .barcode-prefix-page .bps-lead { color: #64748b; font-size: 0.9rem; margin: 0 0 20px; line-height: 1.5; }
        .barcode-prefix-page .bps-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 24px; }
        .barcode-prefix-page .bps-sec { margin-top: 22px; padding-top: 18px; border-top: 1px solid #e2e8f0; }
        .barcode-prefix-page .bps-sec:first-child { margin-top: 0; padding-top: 0; border-top: 0; }
        .barcode-prefix-page .bps-sec h2 { font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--bps-navy); margin: 0 0 14px; }
        .barcode-prefix-page .bps-row { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 14px; }
        .barcode-prefix-page .bps-field { display: flex; flex-direction: column; gap: 4px; min-width: 180px; flex: 1; }
        .barcode-prefix-page .bps-field label { font-size: 0.8rem; font-weight: 600; color: #334155; }
        .barcode-prefix-page .bps-hint { font-size: 0.75rem; color: #94a3b8; margin-top: 4px; }
        .barcode-prefix-page .bps-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        .barcode-prefix-page .bps-table th { background: #f8fafc; color: #475569; font-weight: 600; text-align: left; padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
        .barcode-prefix-page .bps-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .barcode-prefix-page .bps-table tr:last-child td { border-bottom: 0; }
        .barcode-prefix-page .bps-table input { max-width: 140px; }
        .barcode-prefix-page .bps-btns { display: flex; gap: 10px; margin-top: 22px; padding-top: 18px; border-top: 1px solid #e2e8f0; }
        .barcode-prefix-page .bps-btn { border: none; border-radius: 8px; padding: 10px 22px; font-weight: 600; cursor: pointer; font-size: 0.9rem; }
        .barcode-prefix-page .bps-btn-primary { background: linear-gradient(135deg, #11294b, #0d1f38); color: #fff; }
        .barcode-prefix-page .bps-ok { color: #059669; font-size: 0.9rem; margin-bottom: 12px; }
        .barcode-prefix-page .bps-err { color: #dc2626; font-size: 0.9rem; margin-bottom: 12px; }
        .barcode-prefix-page .bps-example { font-family: ui-monospace, monospace; color: var(--bps-navy); font-weight: 600; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="layout-content">
        <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
            <div class="set-software-wrapper">
                <?php include 'set-software-sidebar.php'; ?>
                <div class="set-software-main">
                    <?php include __DIR__ . '/includes/set-software-branches-tabs.php'; ?>
                    <input type="hidden" name="settings_branch_id" id="settingsBranchId" value="<?php echo (int) $settings_branch_id; ?>">
                    <div class="barcode-prefix-page">
                        <h1><?php echo $t('barcode_prefix_settings.heading', 'Barcode Prefix Setting'); ?></h1>
                        <p class="bps-lead"><?php echo $t('barcode_prefix_settings.lead', 'Set the default barcode prefix and number of digits for the logged-in branch. Metal-wise prefixes are used when opening products and generating new barcodes.'); ?></p>

                        <?php if ($msg): ?><p class="bps-ok"><?php echo htmlspecialchars($msg); ?></p><?php endif; ?>
                        <?php if ($err): ?><p class="bps-err"><?php echo htmlspecialchars($err); ?></p><?php endif; ?>

                        <form class="bps-card" method="post" action="barcode-prefix-settings.php<?php echo $settings_branch_id > 0 ? '?branch_id=' . (int) $settings_branch_id : ''; ?>">
                            <input type="hidden" name="save_barcode_prefix_settings" value="1">

                            <div class="bps-sec">
                                <h2><?php echo $t('barcode_prefix_settings.default_section', 'Branch default'); ?></h2>
                                <div class="bps-row">
                                    <div class="bps-field">
                                        <label for="defaultPrefix"><?php echo $t('barcode_prefix_settings.default_prefix', 'Default barcode prefix'); ?></label>
                                        <input type="text" class="form-control" id="defaultPrefix" name="default_prefix" maxlength="50" value="<?php echo htmlspecialchars($settings['default_prefix']); ?>" placeholder="e.g. RN">
                                        <span class="bps-hint"><?php echo $t('barcode_prefix_settings.default_prefix_hint', 'Used when no metal-specific prefix is set.'); ?></span>
                                    </div>
                                    <div class="bps-field" style="max-width:180px">
                                        <label for="defaultDigits"><?php echo $t('barcode_prefix_settings.default_digits', 'No. of digits'); ?></label>
                                        <input type="number" class="form-control" id="defaultDigits" name="default_digits" min="1" max="32" value="<?php echo (int) $settings['default_digits']; ?>">
                                        <span class="bps-hint"><?php echo $t('barcode_prefix_settings.default_digits_hint', 'Numeric part length after prefix.'); ?></span>
                                    </div>
                                </div>
                                <p class="bps-hint">Example: prefix <span class="bps-example" id="bpsExample"><?php echo htmlspecialchars($settings['default_prefix'] . str_pad('1', (int) $settings['default_digits'], '0', STR_PAD_LEFT)); ?></span></p>
                            </div>

                            <?php if (!empty($settings['metals'])): ?>
                            <div class="bps-sec">
                                <h2><?php echo $t('barcode_prefix_settings.metal_section', 'Metal-wise prefix'); ?></h2>
                                <p class="bps-hint" style="margin-bottom:12px;"><?php echo $t('barcode_prefix_settings.metal_hint', 'These prefixes apply per metal type for product opening and auto barcode generation in this branch.'); ?></p>
                                <div class="table-responsive">
                                    <table class="bps-table">
                                        <thead>
                                            <tr>
                                                <th>Metal</th>
                                                <th>Prefix</th>
                                                <th>Digits</th>
                                                <th>Sample</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($settings['metals'] as $i => $m): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($m['display_name']); ?>
                                                    <input type="hidden" name="metal[<?php echo (int) $i; ?>][metal_id]" value="<?php echo (int) $m['metal_id']; ?>">
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm bps-metal-prefix" name="metal[<?php echo (int) $i; ?>][prefix]" maxlength="50" value="<?php echo htmlspecialchars($m['prefix']); ?>">
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control form-control-sm bps-metal-digits" name="metal[<?php echo (int) $i; ?>][digits]" min="1" max="32" value="<?php echo (int) $m['digits']; ?>">
                                                </td>
                                                <td><span class="bps-example bps-metal-sample"><?php echo htmlspecialchars($m['prefix'] . str_pad('1', (int) $m['digits'], '0', STR_PAD_LEFT)); ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="bps-btns">
                                <button type="submit" class="bps-btn bps-btn-primary"><?php echo $t('barcode_prefix_settings.save', 'Save'); ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer-script.php'; ?>
    <script>
    (function () {
        function padNum(n, len) {
            n = String(parseInt(n, 10) || 1);
            while (n.length < len) n = '0' + n;
            return n;
        }
        function updateDefaultExample() {
            var p = document.getElementById('defaultPrefix');
            var d = document.getElementById('defaultDigits');
            var el = document.getElementById('bpsExample');
            if (!p || !d || !el) return;
            var prefix = (p.value || '').trim() || 'RN';
            var digits = Math.max(1, Math.min(32, parseInt(d.value, 10) || 5));
            el.textContent = prefix + padNum(1, digits);
        }
        function updateMetalSamples() {
            document.querySelectorAll('.bps-table tbody tr').forEach(function (tr) {
                var prefixEl = tr.querySelector('.bps-metal-prefix');
                var digitsEl = tr.querySelector('.bps-metal-digits');
                var sampleEl = tr.querySelector('.bps-metal-sample');
                if (!prefixEl || !digitsEl || !sampleEl) return;
                var prefix = (prefixEl.value || '').trim() || 'RN';
                var digits = Math.max(1, Math.min(32, parseInt(digitsEl.value, 10) || 5));
                sampleEl.textContent = prefix + padNum(1, digits);
            });
        }
        var dp = document.getElementById('defaultPrefix');
        var dd = document.getElementById('defaultDigits');
        if (dp) dp.addEventListener('input', updateDefaultExample);
        if (dd) dd.addEventListener('input', updateDefaultExample);
        document.querySelectorAll('.bps-metal-prefix, .bps-metal-digits').forEach(function (el) {
            el.addEventListener('input', updateMetalSamples);
        });
    })();
    </script>
</body>
</html>
