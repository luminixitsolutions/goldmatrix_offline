<?php
session_start();
require_once 'config.php';
if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();
auragold_ensure_branch_id_on_settings_tables($conn);
require_once __DIR__ . '/includes/auragold_voucher_settings_schema.php';
$settings_branch_id = auragold_resolve_voucher_settings_branch_id(
    isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : null
);
if ($settings_branch_id <= 0) {
    $settings_branch_id = auragold_settings_branch_id();
}
$settings_by_metal = getVoucherSettings($settings_branch_id); // keyed by metal: Gold, Silver, ...
$voucher_save_ajax_url = 'ajax/save-voucher-settings.php';
$metals = getVoucherSettingMetals();
$current_metal = 'Gold';
$vs = isset($settings_by_metal[$current_metal]) ? $settings_by_metal[$current_metal] : getVoucherSettingsDefaults();
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Voucher Setting - Set Software - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
    <style>
        /* Voucher Setting – primary #11294b (theme navy) */
        :root {
            --theme-navy: #11294b;
            --voucher-primary: #11294b;
            --voucher-primary-dark: #0d1f38;
            --voucher-primary-light: rgba(17, 41, 75, 0.12);
            --voucher-border: rgba(17, 41, 75, 0.4);
        }
        .voucher-setting-page { padding: 24px; max-width: 900px; margin: 0 auto; }
        .voucher-setting-page .card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .voucher-setting-page .card-body { padding: 24px; }
        .voucher-section-title {
            font-weight: 700;
            font-size: 1rem;
            color: var(--theme-navy);
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid rgba(17, 41, 75, 0.25);
        }
        /* Reusable toggle / tab buttons – pill style */
        .voucher-toggle-group { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .voucher-toggle-btn {
            padding: 8px 18px;
            border-radius: 20px;
            border: 1px solid var(--voucher-border);
            background: var(--voucher-primary-light);
            color: var(--voucher-primary-dark);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
        }
        .voucher-toggle-btn:hover {
            background: rgba(17, 41, 75, 0.2);
            border-color: var(--voucher-primary);
            color: var(--voucher-primary-dark);
        }
        .voucher-toggle-btn.active-btn {
            background: linear-gradient(135deg, var(--voucher-primary) 0%, var(--voucher-primary-dark) 100%);
            color: #fff;
            border-color: var(--voucher-primary);
        }
        .voucher-section { margin-bottom: 28px; }
        .voucher-section:last-child { margin-bottom: 0; }
        .voucher-page-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin-bottom: 24px; }
        .voucher-save-wrap { margin-top: 24px; padding-top: 20px; border-top: 1px solid #e2e8f0; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .voucher-save-btn { background: linear-gradient(135deg, var(--voucher-primary) 0%, var(--voucher-primary-dark) 100%); color: #fff; border: none; padding: 10px 24px; border-radius: 20px; font-size: 14px; font-weight: 600; cursor: pointer; transition: opacity 0.2s; }
        .voucher-save-btn:hover { opacity: 0.95; }
        .voucher-save-btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .voucher-save-msg { font-size: 13px; }
        .voucher-save-msg.success { color: #059669; }
        .voucher-save-msg.error { color: #dc2626; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="layout-content">
        <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
            <div class="set-software-wrapper">
                <?php include 'set-software-sidebar.php'; ?>
                <div class="set-software-main">
                    <?php include __DIR__ . '/includes/set-software-branch-banner.php'; ?>
                    <div class="voucher-setting-page">
                        <h1 class="voucher-page-title">Voucher Setting</h1>
                        <div class="card">
                            <div class="card-body">
                                <!-- 1. Metal Wise Setting (tabs: switch metal to edit its settings) -->
                                <div class="voucher-section">
                                    <div class="voucher-section-title">Metal Wise Setting</div>
                                    <div class="voucher-toggle-group voucher-metal-tabs" data-group="metalWise">
                                        <button type="button" class="voucher-toggle-btn active-btn" data-value="Gold">Gold</button>
                                        <button type="button" class="voucher-toggle-btn" data-value="Silver">Silver</button>
                                        <button type="button" class="voucher-toggle-btn" data-value="Platinum">Platinum</button>
                                        <button type="button" class="voucher-toggle-btn" data-value="Diamond & Stones">Diamond & Stones</button>
                                        <button type="button" class="voucher-toggle-btn" data-value="Imitation Or Watches">Imitation Or Watches</button>
                                        <button type="button" class="voucher-toggle-btn" data-value="Other Or Services">Other Or Services</button>
                                    </div>
                                </div>

                                <!-- 2. Minimum Amount Column -->
                                <div class="voucher-section">
                                    <div class="voucher-section-title">Minimum Amount Column</div>
                                    <div class="voucher-toggle-group" data-group="minimumAmountColumn">
                                        <?php $val = isset($vs['minimum_amount_column']) ? $vs['minimum_amount_column'] : 'Amount'; ?>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'Amount' ? ' active-btn' : ''; ?>" data-value="Amount">Amount</button>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'MakingAmount' ? ' active-btn' : ''; ?>" data-value="MakingAmount">MakingAmount</button>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'NetAmount' ? ' active-btn' : ''; ?>" data-value="NetAmount">NetAmount</button>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'NetAmountWithTax' ? ' active-btn' : ''; ?>" data-value="NetAmountWithTax">NetAmountWithTax</button>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'Rate' ? ' active-btn' : ''; ?>" data-value="Rate">Rate</button>
                                    </div>
                                </div>

                                <!-- 3. Reverse Calculation Result Column -->
                                <div class="voucher-section">
                                    <div class="voucher-section-title">Reverse Calculation Result Column</div>
                                    <div class="voucher-toggle-group" data-group="reverseCalculationResultColumn">
                                        <?php $val = isset($vs['reverse_calculation_result_column']) ? $vs['reverse_calculation_result_column'] : 'MakingRate'; ?>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'DiscountAmount' ? ' active-btn' : ''; ?>" data-value="DiscountAmount">DiscountAmount</button>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'MakingRate' ? ' active-btn' : ''; ?>" data-value="MakingRate">MakingRate</button>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'Rate' ? ' active-btn' : ''; ?>" data-value="Rate">Rate</button>
                                    </div>
                                </div>

                                <!-- 4. Default Discount Type -->
                                <div class="voucher-section">
                                    <div class="voucher-section-title">Default Discount Type</div>
                                    <div class="voucher-toggle-group" data-group="defaultDiscountType">
                                        <?php $val = isset($vs['default_discount_type']) ? $vs['default_discount_type'] : 'Fix'; ?>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'Fix' ? ' active-btn' : ''; ?>" data-value="Fix">Fix</button>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'On Amount' ? ' active-btn' : ''; ?>" data-value="On Amount">On Amount</button>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'On Making Amount' ? ' active-btn' : ''; ?>" data-value="On Making Amount">On Making Amount</button>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'On Diamond Amount' ? ' active-btn' : ''; ?>" data-value="On Diamond Amount">On Diamond Amount</button>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'On Stone Amount' ? ' active-btn' : ''; ?>" data-value="On Stone Amount">On Stone Amount</button>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'On Net Amount' ? ' active-btn' : ''; ?>" data-value="On Net Amount">On Net Amount</button>
                                    </div>
                                </div>

                                <!-- 5. Default Calculation Type -->
                                <div class="voucher-section">
                                    <div class="voucher-section-title">Default Calculation Type</div>
                                    <div class="voucher-toggle-group" data-group="defaultCalculationType">
                                        <?php $val = isset($vs['default_calculation_type']) ? $vs['default_calculation_type'] : 'Fix'; ?>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'Fix' ? ' active-btn' : ''; ?>" data-value="Fix">Fix</button>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'Quantity X Rate' ? ' active-btn' : ''; ?>" data-value="Quantity X Rate">Quantity X Rate</button>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'Carat X Rate' ? ' active-btn' : ''; ?>" data-value="Carat X Rate">Carat X Rate</button>
                                    </div>
                                </div>

                                <!-- 6. Stock Availability Check By -->
                                <div class="voucher-section">
                                    <div class="voucher-section-title">Stock Availability Check By</div>
                                    <div class="voucher-toggle-group" data-group="stockAvailabilityCheckBy">
                                        <?php $val = isset($vs['stock_availability_check_by']) ? $vs['stock_availability_check_by'] : 'Carat'; ?>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'Carat' ? ' active-btn' : ''; ?>" data-value="Carat">Carat</button>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'GrossWt' ? ' active-btn' : ''; ?>" data-value="GrossWt">GrossWt</button>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'Quantity' ? ' active-btn' : ''; ?>" data-value="Quantity">Quantity</button>
                                    </div>
                                </div>

                                <!-- 7. Wastage Wt Calculation (metal-wise via tabs above) -->
                                <div class="voucher-section">
                                    <div class="voucher-section-title">Wastage Wt Calculation</div>
                                    <div class="voucher-toggle-group" data-group="wastageWtCalculation">
                                        <?php $val = isset($vs['wastage_wt_calculation']) ? $vs['wastage_wt_calculation'] : 'GoldWt'; ?>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'GoldWt' ? ' active-btn' : ''; ?>" data-value="GoldWt">Metal Wt</button>
                                        <button type="button" class="voucher-toggle-btn<?php echo $val === 'FinalWt' ? ' active-btn' : ''; ?>" data-value="FinalWt">Final Wt</button>
                                    </div>
                                </div>

                                <div class="voucher-save-wrap">
                                    <button type="button" class="voucher-save-btn" id="voucherSaveBtn">Save Settings</button>
                                    <span class="voucher-save-msg" id="voucherSaveMsg" aria-live="polite"></span>
                                    <span style="font-size:12px;color:#64748b;width:100%;">Saves all 6 metals. Switch the metal tab to edit each one, then click Save once.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var voucherSettingsBranchId = <?php echo (int) $settings_branch_id; ?>;
        var voucherSaveUrl = <?php echo json_encode($voucher_save_ajax_url, JSON_UNESCAPED_SLASHES); ?>;
        // Settings per metal (from DB). Key = metal name, value = { minimum_amount_column, ... }
        var settingsByMetal = <?php echo json_encode($settings_by_metal, JSON_UNESCAPED_UNICODE); ?>;
        var currentMetal = 'Gold';

        console.log('Voucher settings branch_id:', voucherSettingsBranchId);
        console.log('Loaded settingsByMetal from DB:', settingsByMetal);

        function pickStored(s, key, fallback) {
            if (!s || s[key] === undefined || s[key] === null || s[key] === '') {
                return fallback;
            }
            return s[key];
        }

        function applyStoreRowToVoucherSettings(s) {
            voucherSettings.minimumAmountColumn = pickStored(s, 'minimum_amount_column', 'Amount');
            voucherSettings.reverseCalculationResultColumn = pickStored(s, 'reverse_calculation_result_column', 'MakingRate');
            voucherSettings.defaultDiscountType = pickStored(s, 'default_discount_type', 'Fix');
            voucherSettings.defaultCalculationType = pickStored(s, 'default_calculation_type', 'Fix');
            voucherSettings.stockAvailabilityCheckBy = pickStored(s, 'stock_availability_check_by', 'Carat');
            var w = pickStored(s, 'wastage_wt_calculation', 'GoldWt');
            voucherSettings.wastageWtCalculation = (w === 'FinalWt' || w === 'GoldWt') ? w : 'GoldWt';
        }

        var voucherSettings = {
            minimumAmountColumn: 'Amount',
            reverseCalculationResultColumn: 'MakingRate',
            defaultDiscountType: 'Fix',
            defaultCalculationType: 'Fix',
            stockAvailabilityCheckBy: 'Carat',
            wastageWtCalculation: 'GoldWt'
        };

        function sanitizeSettingsByMetalForSave() {
            var clean = {};
            Object.keys(settingsByMetal).forEach(function(metal) {
                var row = settingsByMetal[metal];
                if (!row || typeof row !== 'object') return;
                clean[metal] = {
                    minimum_amount_column: row.minimum_amount_column,
                    reverse_calculation_result_column: row.reverse_calculation_result_column,
                    default_discount_type: row.default_discount_type,
                    default_calculation_type: row.default_calculation_type,
                    stock_availability_check_by: row.stock_availability_check_by,
                    wastage_wt_calculation: row.wastage_wt_calculation
                };
            });
            return clean;
        }

        function getSelectedSettings() {
            return {
                minimumAmountColumn: voucherSettings.minimumAmountColumn,
                reverseCalculationResultColumn: voucherSettings.reverseCalculationResultColumn,
                defaultDiscountType: voucherSettings.defaultDiscountType,
                defaultCalculationType: voucherSettings.defaultCalculationType,
                stockAvailabilityCheckBy: voucherSettings.stockAvailabilityCheckBy,
                wastageWtCalculation: voucherSettings.wastageWtCalculation
            };
        }

        /** Keep in-memory store in sync when switching metal tabs or before save */
        function syncCurrentMetalToStore() {
            if (!currentMetal) return;
            if (!settingsByMetal[currentMetal]) {
                settingsByMetal[currentMetal] = {};
            }
            var o = settingsByMetal[currentMetal];
            var opts = getSelectedSettings();
            o.minimum_amount_column = opts.minimumAmountColumn;
            o.reverse_calculation_result_column = opts.reverseCalculationResultColumn;
            o.default_discount_type = opts.defaultDiscountType;
            o.default_calculation_type = opts.defaultCalculationType;
            o.stock_availability_check_by = opts.stockAvailabilityCheckBy;
            o.wastage_wt_calculation = opts.wastageWtCalculation;
        }

        function applySettingsToUI() {
            var groupMap = {
                minimumAmountColumn: 'minimumAmountColumn',
                reverseCalculationResultColumn: 'reverseCalculationResultColumn',
                defaultDiscountType: 'defaultDiscountType',
                defaultCalculationType: 'defaultCalculationType',
                stockAvailabilityCheckBy: 'stockAvailabilityCheckBy',
                wastageWtCalculation: 'wastageWtCalculation'
            };
            document.querySelectorAll('.voucher-toggle-group').forEach(function(container) {
                var group = container.getAttribute('data-group');
                if (group === 'metalWise') {
                    container.querySelectorAll('.voucher-toggle-btn').forEach(function(btn) {
                        btn.classList.toggle('active-btn', btn.getAttribute('data-value') === currentMetal);
                    });
                    return;
                }
                var key = groupMap[group];
                if (!key || !Object.prototype.hasOwnProperty.call(voucherSettings, key)) return;
                var value = voucherSettings[key];
                container.querySelectorAll('.voucher-toggle-btn').forEach(function(btn) {
                    btn.classList.toggle('active-btn', btn.getAttribute('data-value') === value);
                });
            });
        }

        function loadMetal(metal) {
            // Only sync when leaving another metal tab — never before initial Gold load (that overwrote DB values with defaults).
            if (metal !== currentMetal) {
                syncCurrentMetalToStore();
            }
            currentMetal = metal;
            applyStoreRowToVoucherSettings(settingsByMetal[metal] || {});
            applySettingsToUI();
            console.log('Voucher Setting – now editing:', currentMetal, getSelectedSettings());
        }

        function setActiveInGroup(container, value) {
            var group = container.getAttribute('data-group');
            if (!group) return;
            if (group === 'metalWise') {
                loadMetal(value);
                return;
            }
            var groupKeyMap = {
                minimumAmountColumn: 'minimumAmountColumn',
                reverseCalculationResultColumn: 'reverseCalculationResultColumn',
                defaultDiscountType: 'defaultDiscountType',
                defaultCalculationType: 'defaultCalculationType',
                stockAvailabilityCheckBy: 'stockAvailabilityCheckBy',
                wastageWtCalculation: 'wastageWtCalculation'
            };
            var key = groupKeyMap[group];
            if (key) {
                voucherSettings[key] = value;
                syncCurrentMetalToStore();
            }

            var buttons = container.querySelectorAll('.voucher-toggle-btn');
            buttons.forEach(function(btn) {
                btn.classList.toggle('active-btn', btn.getAttribute('data-value') === value);
            });
            console.log('Voucher Setting –', currentMetal, getSelectedSettings());
        }

        document.querySelectorAll('.voucher-toggle-group').forEach(function(group) {
            group.addEventListener('click', function(e) {
                var btn = e.target.closest('.voucher-toggle-btn');
                if (!btn) return;
                var value = btn.getAttribute('data-value');
                setActiveInGroup(group, value);
            });
        });

        // Save current metal's settings to database
        var saveBtn = document.getElementById('voucherSaveBtn');
        var saveMsg = document.getElementById('voucherSaveMsg');
        if (saveBtn && saveMsg) {
            saveBtn.addEventListener('click', function() {
                syncCurrentMetalToStore();
                var formData = new FormData();
                formData.append('save_all', '1');
                formData.append('settings_by_metal_json', JSON.stringify(sanitizeSettingsByMetalForSave()));
                var branchId = voucherSettingsBranchId;
                var sb = document.getElementById('settingsBranchId');
                if (sb && sb.value) {
                    branchId = parseInt(sb.value, 10) || branchId;
                }
                formData.append('settings_branch_id', String(branchId));

                saveBtn.disabled = true;
                saveMsg.textContent = '';
                saveMsg.className = 'voucher-save-msg';

                console.log('Saving voucher settings branch_id:', branchId, sanitizeSettingsByMetalForSave());

                fetch(voucherSaveUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
                    .then(function(r) {
                        return r.text().then(function(text) {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                throw new Error(text && text.length < 200 ? text : 'Invalid server response');
                            }
                        });
                    })
                    .then(function(data) {
                        console.log('Save response:', data);
                        if (data.reloaded_settings) {
                            settingsByMetal = data.reloaded_settings;
                            console.log('Reloaded settings from server after save:', settingsByMetal);
                        }
                        if (data.status === 'success') {
                            saveMsg.textContent = data.message || 'Settings saved.';
                            saveMsg.className = 'voucher-save-msg success';
                            setTimeout(function() { location.reload(); }, 400);
                            return;
                        }
                        saveBtn.disabled = false;
                        if (data.status === 'partial') {
                            saveMsg.textContent = data.message || 'Settings partially saved.';
                            saveMsg.className = 'voucher-save-msg error';
                        } else {
                            saveMsg.textContent = data.message || 'Save failed.';
                            saveMsg.className = 'voucher-save-msg error';
                        }
                    })
                    .catch(function(err) {
                        saveBtn.disabled = false;
                        saveMsg.textContent = 'Error: ' + (err.message || 'Request failed');
                        saveMsg.className = 'voucher-save-msg error';
                    });
            });
        }

        loadMetal('Gold');
    })();
    </script>
</body>
</html>
