<?php
/**
 * Shared "Previous Balance" panel — same layout as sale-invoice (Amount, Gold, Silver, Diamond, Gemstone + use amount row).
 * Optional vars before include:
 *   $pb_show_use_amount_row (bool, default true) — show "Amount to use" row when checkbox is on
 */
require_once __DIR__ . '/auragold_summary_metal_icons.php';
if (!isset($pb_show_use_amount_row)) {
    $pb_show_use_amount_row = true;
}
if (!isset($pb_show_use_previous_checkbox)) {
    $pb_show_use_previous_checkbox = true;
}
if (!defined('PB_PANEL_STYLES_EMITTED')) {
    define('PB_PANEL_STYLES_EMITTED', true);
    ?>
<style>
.previous-balance-panel .pb-panel-divider-wrap { margin: 0 0 0.75rem 0; overflow: hidden; }
.previous-balance-panel .pb-panel-divider { height: 4px; background: #e2e8f0; border-radius: 2px; position: relative; overflow: hidden; }
.previous-balance-panel .pb-panel-divider-gold { position: absolute; left: 0; top: 0; width: 32%; height: 100%; background: linear-gradient(90deg, #c9a227, #e8d48b); border-radius: 2px 0 0 2px; }
.previous-balance-panel .summary-label { font-weight: 600; text-transform: uppercase; font-size: 0.72rem; letter-spacing: 0.02em; color: #11294b; }
.previous-balance-panel h6.pb-panel-title { font-weight: 700; text-transform: uppercase; font-size: 0.78rem; letter-spacing: 0.04em; color: #11294b; display: flex; align-items: center; gap: 8px; }
/* Metal row icons — shared panel is used on pages without sale-invoice-workspace.css */
.previous-balance-panel .siw-summary-label-with-icon {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    line-height: 1.2;
}
.previous-balance-panel .siw-metal-icon {
    width: 18px;
    height: 18px;
    min-width: 18px;
    flex-shrink: 0;
    color: #c9a227;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    vertical-align: middle;
    overflow: hidden;
}
.previous-balance-panel .siw-metal-icon svg {
    width: 18px;
    height: 18px;
    display: block;
}
.previous-balance-panel > .d-flex.justify-content-between.align-items-center.mb-1 {
    min-height: 1.35rem;
    gap: 8px;
}
/* Keep checkbox + label aligned with other summary rows (no negative margin / overflow past panel edge) */
.previous-balance-panel .pb-use-previous-row {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    padding: 2px;
    margin-top: 0.35rem;
    width: 100%;
    box-sizing: border-box;
}
.previous-balance-panel .pb-use-previous-row label {
    display: flex;
    align-items: center;
    width: 100%;
    margin: 0;
    cursor: pointer;
}
.previous-balance-panel .pb-use-previous-row .form-check-input {
    margin: 0 8px 0 0 !important;
    flex-shrink: 0;
    float: none;
    position: static;
}
/* Do NOT use Bootstrap .d-flex on this row — it uses display:flex !important and overrides display:none */
.previous-balance-panel .pb-use-amount-row {
    display: none;
    margin-bottom: 0.25rem;
}
.previous-balance-panel .pb-use-amount-row.is-open {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.previous-balance-panel { position: relative; }
.previous-balance-panel .pb-panel-loader {
    display: none;
    position: absolute;
    inset: 0;
    z-index: 6;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 6px;
    background: rgba(248, 250, 252, 0.92);
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 600;
    color: #11294b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.previous-balance-panel .pb-panel-loader.pb-is-loading {
    display: flex;
}
.previous-balance-panel .pb-panel-loader-spinner {
    width: 26px;
    height: 26px;
    border: 3px solid #e2e8f0;
    border-top-color: #c9a227;
    border-radius: 50%;
    animation: pb-panel-spin 0.75s linear infinite;
}
@keyframes pb-panel-spin {
    to { transform: rotate(360deg); }
}
</style>
<?php
}
?>
<div class="summary-section previous-balance-panel">
    <div id="previousBalancePanelLoader" class="pb-panel-loader" aria-hidden="true" role="status">
        <span class="pb-panel-loader-spinner" aria-hidden="true"></span>
        <span>Loading balance…</span>
    </div>
    <h6 class="pb-panel-title mb-2 siw-summary-label-with-icon"><?php echo auragold_summary_metal_icon('wallet'); ?> Previous balance</h6>
    <div class="pb-panel-divider-wrap">
        <div class="pb-panel-divider"><span class="pb-panel-divider-gold" aria-hidden="true"></span></div>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="summary-label siw-summary-label-with-icon"><?php echo auragold_summary_metal_icon('amount'); ?> Amount</span>
        <span class="summary-value font-weight-bold" id="previousBalanceAmount" data-original-balance="0.00">0.00</span>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="summary-label siw-summary-label-with-icon"><?php echo auragold_summary_metal_icon('gold'); ?> Gold</span>
        <span class="summary-value font-weight-bold" id="previousBalanceGold" data-original-gold="0.000">0.000</span>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="summary-label siw-summary-label-with-icon"><?php echo auragold_summary_metal_icon('silver'); ?> Silver</span>
        <span class="summary-value font-weight-bold" id="previousBalanceSilver" data-original-silver="0.000">0.000</span>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="summary-label siw-summary-label-with-icon"><?php echo auragold_summary_metal_icon('diamond'); ?> Diamond</span>
        <span class="summary-value font-weight-bold" id="previousBalanceDiamond" data-original-diamond="0.000">0.000</span>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="summary-label siw-summary-label-with-icon"><?php echo auragold_summary_metal_icon('gemstone'); ?> Gemstone</span>
        <span class="summary-value font-weight-bold" id="previousBalanceGemstone" data-original-gemstone="0.000">0.000</span>
    </div>
    <?php if (!empty($pb_show_use_previous_checkbox)) : ?>
    <div class="summary-row pb-use-previous-row">
        <label class="mb-0">
            <input type="checkbox" id="usePreviousBalanceCheck" class="form-check-input" value="1" style="width: 1rem; height: 1rem;">
            <span class="summary-label mb-0">Use previous balance</span>
        </label>
    </div>
    <?php endif; ?>
    <?php if (!empty($pb_show_use_amount_row)) : ?>
    <div class="pb-use-amount-row" id="previousBalanceUseAmountRow">
        <span class="summary-label">Amount to use</span>
        <input type="number" class="form-control form-control-sm text-end" id="previousBalanceUseAmount" value="0.00" step="0.01" min="0" style="max-width: 120px;">
    </div>
    <?php endif; ?>
</div>
