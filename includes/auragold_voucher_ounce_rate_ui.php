<?php
/**
 * Ounce rate toggle + gold/silver modal (sale invoice and similar vouchers).
 *
 * Expects: $si_ounce_rate_state (array), optional $si_ounce_rate_id_prefix (default si)
 * Optional: $si_ounce_ui_part = 'field' | 'inner' | 'modal' | 'both' (default both)
 */
if (!isset($si_ounce_rate_state) || !is_array($si_ounce_rate_state)) {
    $si_ounce_rate_state = function_exists('auragold_voucher_ounce_rate_empty_payload')
        ? auragold_voucher_ounce_rate_empty_payload()
        : ['enabled' => false, 'gold' => [], 'silver' => []];
}
$si_oz_prefix = isset($si_ounce_rate_id_prefix) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $si_ounce_rate_id_prefix) : 'si';
if ($si_oz_prefix === '') {
    $si_oz_prefix = 'si';
}
$si_ounce_ui_part = isset($si_ounce_ui_part) ? (string) $si_ounce_ui_part : 'both';
$si_oz_enabled = !empty($si_ounce_rate_state['enabled']);
$si_oz_gold = is_array($si_ounce_rate_state['gold'] ?? null) ? $si_ounce_rate_state['gold'] : [];
$si_oz_silver = is_array($si_ounce_rate_state['silver'] ?? null) ? $si_ounce_rate_state['silver'] : [];
$si_oz_fmt = function_exists('auragold_format_ounce_decimal') ? 'auragold_format_ounce_decimal' : static function ($v) {
    return (string) $v;
};
$si_oz_display = $si_oz_enabled
    ? (($si_oz_gold['us_oz'] ?? '') !== '' ? $si_oz_fmt($si_oz_gold['us_oz'], 3) : '0')
    : '0';
$si_oz_json = json_encode($si_ounce_rate_state, JSON_UNESCAPED_UNICODE);
if ($si_oz_json === false) {
    $si_oz_json = '{}';
}
if ($si_ounce_ui_part === 'inner'):
?>
        <div class="si-ounce-rate-bar">
            <label class="si-ounce-toggle" for="<?php echo $si_oz_prefix; ?>OunceRateEnabled" title="Enable ounce rate">
                <input type="checkbox" class="si-ounce-toggle-input" id="<?php echo $si_oz_prefix; ?>OunceRateEnabled" aria-label="Enable ounce rate"<?php echo $si_oz_enabled ? ' checked' : ''; ?>>
                <span class="si-ounce-toggle-slider" aria-hidden="true"></span>
            </label>
            <input
                type="number"
                step="0.001"
                min="0"
                class="form-control form-control-sm si-ounce-rate-input"
                id="<?php echo $si_oz_prefix; ?>OunceRateDisplay"
                value="<?php echo htmlspecialchars($si_oz_display, ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="Enter ounce rate"
                autocomplete="off"
            >
            <button type="button" class="si-ounce-info-btn" id="<?php echo $si_oz_prefix; ?>OunceRateInfoBtn" title="Gold &amp; Silver ounce rate details" aria-label="Ounce rate details">
                <span>i</span>
            </button>
        </div>
        <input type="hidden" id="<?php echo $si_oz_prefix; ?>OunceRateJson" value="<?php echo htmlspecialchars($si_oz_json, ENT_QUOTES, 'UTF-8'); ?>">
<?php elseif ($si_ounce_ui_part === 'field' || $si_ounce_ui_part === 'both'):
?>
<div class="col-12 col-md-5 col-lg-5 si-ounce-rate-col">
    <div class="form-group mb-md-0 si-ounce-rate-field">
        <label for="<?php echo $si_oz_prefix; ?>OunceRateDisplay">Ounce Rate</label>
        <div class="si-ounce-rate-bar">
            <label class="si-ounce-toggle" for="<?php echo $si_oz_prefix; ?>OunceRateEnabled" title="Enable ounce rate">
                <input type="checkbox" class="si-ounce-toggle-input" id="<?php echo $si_oz_prefix; ?>OunceRateEnabled" aria-label="Enable ounce rate"<?php echo $si_oz_enabled ? ' checked' : ''; ?>>
                <span class="si-ounce-toggle-slider" aria-hidden="true"></span>
            </label>
            <input
                type="number"
                step="0.001"
                min="0"
                class="form-control form-control-sm si-ounce-rate-input"
                id="<?php echo $si_oz_prefix; ?>OunceRateDisplay"
                value="<?php echo htmlspecialchars($si_oz_display, ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="Enter ounce rate"
                autocomplete="off"
            >
            <button type="button" class="si-ounce-info-btn" id="<?php echo $si_oz_prefix; ?>OunceRateInfoBtn" title="Gold &amp; Silver ounce rate details" aria-label="Ounce rate details">
                <span>i</span>
            </button>
        </div>
        <input type="hidden" id="<?php echo $si_oz_prefix; ?>OunceRateJson" value="<?php echo htmlspecialchars($si_oz_json, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
</div>
<?php endif; if ($si_ounce_ui_part === 'modal' || $si_ounce_ui_part === 'both'): ?>
<div class="modal fade si-ounce-modal" id="<?php echo $si_oz_prefix; ?>OunceRateModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header si-ounce-modal-header">
                <h6 class="modal-title si-ounce-modal-title">Gold Ounce Rate</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body si-ounce-modal-body">
                <ul class="nav nav-tabs si-ounce-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="<?php echo $si_oz_prefix; ?>OzTabGold" data-toggle="tab" href="#<?php echo $si_oz_prefix; ?>OzPaneGold" role="tab">Gold Ounce Rate</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="<?php echo $si_oz_prefix; ?>OzTabSilver" data-toggle="tab" href="#<?php echo $si_oz_prefix; ?>OzPaneSilver" role="tab">Silver Ounce Rate</a>
                    </li>
                </ul>
                <div class="tab-content si-ounce-tab-content">
                    <?php
                    foreach (['gold' => 'Gold', 'silver' => 'Silver'] as $metalKey => $metalLabel):
                        $m = $metalKey === 'gold' ? $si_oz_gold : $si_oz_silver;
                        $m = is_array($m) ? $m : [];
                        if (($m['us_oz'] ?? '') !== '' && trim((string) ($m['usd_rate'] ?? '')) === '') {
                            $atGmVal = (float) ($m['at_gm'] ?? 0);
                            $m['usd_rate'] = function_exists('auragold_ounce_calc_usd_rate')
                                ? auragold_ounce_calc_usd_rate($m['us_oz'], $atGmVal > 0 ? $atGmVal : null)
                                : '';
                            if ($atGmVal <= 0 && function_exists('auragold_ounce_metal_rate_divisor')) {
                                $m['at_gm'] = auragold_format_ounce_decimal(auragold_ounce_metal_rate_divisor(), 3);
                            }
                        }
                        $paneId = $si_oz_prefix . 'OzPane' . ucfirst($metalKey);
                        $active = $metalKey === 'gold' ? ' show active' : '';
                        $daily = htmlspecialchars((string) ($m['daily_date'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="tab-pane fade<?php echo $active; ?>" id="<?php echo $paneId; ?>" role="tabpanel">
                        <div class="si-ounce-form-row si-ounce-form-row-full">
                            <label>Daily <?php echo htmlspecialchars($metalLabel, ENT_QUOTES, 'UTF-8'); ?></label>
                            <div class="si-ounce-field">
                                <div class="si-ounce-input-group">
                                    <input type="date" class="form-control form-control-sm si-oz-daily-date" data-metal="<?php echo $metalKey; ?>" value="<?php echo $daily; ?>">
                                    <button type="button" class="si-ounce-refresh-btn si-oz-today-btn" data-metal="<?php echo $metalKey; ?>" title="Today"><i class="feather icon-calendar"></i></button>
                                    <button type="button" class="si-ounce-refresh-btn si-oz-refresh-btn" data-metal="<?php echo $metalKey; ?>" title="Refresh from dashboard"><i class="feather icon-refresh-cw"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="si-ounce-form-grid">
                            <div class="si-ounce-form-row">
                                <label>US <?php echo htmlspecialchars($metalLabel, ENT_QUOTES, 'UTF-8'); ?></label>
                                <div class="si-ounce-field">
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.001" class="form-control si-oz-us-oz" data-metal="<?php echo $metalKey; ?>" value="<?php echo htmlspecialchars($m['us_oz'] !== '' && $m['us_oz'] !== null ? $si_oz_fmt($m['us_oz'], 3) : '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="input-group-append"><span class="input-group-text">oz</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="si-ounce-form-row">
                                <label>Loss Rate</label>
                                <div class="si-ounce-field">
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.01" class="form-control si-oz-loss" data-metal="<?php echo $metalKey; ?>" value="<?php echo htmlspecialchars($m['loss_rate'] !== '' && $m['loss_rate'] !== null ? $si_oz_fmt($m['loss_rate'], 2) : '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="input-group-append"><span class="input-group-text">%</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="si-ounce-form-row">
                                <label>US$ Rate</label>
                                <div class="si-ounce-field">
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.001" class="form-control si-oz-usd-rate" data-metal="<?php echo $metalKey; ?>" value="<?php echo htmlspecialchars($m['usd_rate'] !== '' && $m['usd_rate'] !== null ? $si_oz_fmt($m['usd_rate'], 3) : '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="input-group-append"><span class="input-group-text si-oz-cur-symbol">₹</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="si-ounce-form-row">
                                <label>@ GM</label>
                                <div class="si-ounce-field">
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.001" class="form-control si-oz-at-gm" data-metal="<?php echo $metalKey; ?>" value="<?php echo htmlspecialchars($m['at_gm'] !== '' && $m['at_gm'] !== null ? $si_oz_fmt($m['at_gm'], 3) : '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="input-group-append"><span class="input-group-text">@ GM</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm si-oz-save-btn" data-prefix="<?php echo htmlspecialchars($si_oz_prefix, ENT_QUOTES, 'UTF-8'); ?>">Save</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>