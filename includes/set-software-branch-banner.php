<?php
/**
 * Branch context banner for Set Software settings pages (super-admin can switch branch).
 * Expects config.php loaded ($conn) and auragold_* helpers from auragold_branch_data_scope.php.
 */
if (!isset($settings_branch_id)) {
    if (isset($conn) && $conn instanceof mysqli) {
        auragold_ensure_branch_id_on_settings_tables($conn);
    }
    $settings_branch_id = function_exists('auragold_settings_branch_id') ? auragold_settings_branch_id() : 0;
}
$settings_branch_id = (int) $settings_branch_id;
$can_switch_settings_branch = function_exists('auragold_effective_branch_id') && auragold_effective_branch_id() <= 0;
$branches_for_settings = function_exists('getListMaster')
    ? getListMaster('SELECT id, name, code FROM tbl_branches ORDER BY name ASC')
    : [];
$settings_branch_label = '';
foreach ($branches_for_settings as $b) {
    if ((int) ($b['id'] ?? 0) === $settings_branch_id) {
        $settings_branch_label = trim((string) ($b['name'] ?? ''));
        if ($settings_branch_label === '' && !empty($b['code'])) {
            $settings_branch_label = (string) $b['code'];
        }
        break;
    }
}
if ($settings_branch_label === '' && $settings_branch_id > 0) {
    $settings_branch_label = 'Branch #' . $settings_branch_id;
}
$settings_banner_script = basename((string) ($_SERVER['PHP_SELF'] ?? 'index.php'));
$settings_banner_qs = $_GET;
$settings_banner_qs['branch_id'] = '__BID__';
$settings_banner_tpl = $settings_banner_script . '?' . http_build_query($settings_banner_qs);
?>
<div class="auragold-settings-branch-banner" style="display:flex;align-items:center;flex-wrap:wrap;gap:10px 16px;padding:10px 14px;margin-bottom:16px;background:linear-gradient(135deg,#f8fafc 0%,#eef2ff 100%);border:1px solid #e2e8f0;border-radius:10px;font-size:13px;color:#334155;">
    <span style="font-weight:600;color:#11294b;">Branch settings</span>
    <?php if ($can_switch_settings_branch && count($branches_for_settings) > 0) { ?>
        <label style="margin:0;display:inline-flex;align-items:center;gap:8px;">
            <span style="color:#64748b;">for</span>
            <select class="form-control form-control-sm" style="min-width:200px;display:inline-block;width:auto;" id="auragoldSettingsBranchSelect" aria-label="Branch for settings">
                <?php foreach ($branches_for_settings as $b) {
                    $bid = (int) ($b['id'] ?? 0);
                    if ($bid <= 0) {
                        continue;
                    }
                    $bn = trim((string) ($b['name'] ?? ''));
                    if ($bn === '' && !empty($b['code'])) {
                        $bn = (string) $b['code'];
                    }
                    if ($bn === '') {
                        $bn = 'Branch #' . $bid;
                    }
                    ?>
                    <option value="<?php echo $bid; ?>"<?php echo $bid === $settings_branch_id ? ' selected' : ''; ?>><?php echo htmlspecialchars($bn); ?></option>
                <?php } ?>
            </select>
        </label>
        <script>
        (function() {
            var sel = document.getElementById('auragoldSettingsBranchSelect');
            if (!sel) return;
            var tpl = <?php echo json_encode($settings_banner_tpl, JSON_UNESCAPED_SLASHES); ?>;
            sel.addEventListener('change', function() {
                var id = parseInt(sel.value, 10) || 0;
                if (id <= 0) return;
                var href = tpl.replace('__BID__', String(id));
                window.location.href = href;
            });
        })();
        </script>
    <?php } else { ?>
        <span style="color:#475569;"><strong><?php echo htmlspecialchars($settings_branch_label); ?></strong></span>
    <?php } ?>
</div>
<input type="hidden" name="settings_branch_id" id="settingsBranchId" value="<?php echo $settings_branch_id; ?>">
