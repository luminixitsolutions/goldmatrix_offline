<?php
/**
 * Searchable party (customer / supplier) Select2 field for voucher pages.
 */
if (!function_exists('auragold_party_select2_defaults')) {
    function auragold_party_select2_defaults(): array
    {
        return [
            'party_id' => 'customerId',
            'party_name' => 'customerName',
            'billing_state' => 'customerBillingState',
            'show_billing_state' => true,
            'show_add_btn' => true,
            'add_btn_id' => 'addCustomerBtn',
            'add_btn_title' => 'Add / Edit Customer',
            'placeholder' => 'Select customer...',
            'required' => true,
            'readonly' => false,
            'value_id' => '',
            'value_name' => '',
            'wrap_class' => 'auragold-party-select2-wrap',
        ];
    }
}

if (!function_exists('auragold_echo_party_select2_styles')) {
    function auragold_echo_party_select2_styles(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        echo '<style>
.auragold-party-select2-wrap { flex: 1; min-width: 0; }
.auragold-party-select2-wrap .select2-container { width: 100% !important; max-width: 100%; }
.auragold-party-select2-wrap .select2-container--default .select2-selection--single {
    height: calc(1.5em + 0.5rem + 2px);
    min-height: calc(1.5em + 0.5rem + 2px);
    border: 1px solid #ced4da;
    border-radius: 0.2rem;
    font-size: 0.875rem;
}
.auragold-party-select2-wrap .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: calc(1.5em + 0.5rem);
    padding-left: 0.5rem;
    color: #495057;
}
.auragold-party-select2-wrap .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: calc(1.5em + 0.5rem + 2px);
}
.select2-container.auragold-party-select2-container { z-index: 50 !important; }
.select2-dropdown.auragold-party-select2-dropdown { z-index: 50 !important; }
.top-navbar, #auragoldTopNav { position: relative; z-index: 1200 !important; }
.modal-backdrop { z-index: 1240 !important; }
.modal { z-index: 1250 !important; }
body.modal-open .top-navbar,
body.modal-open #auragoldTopNav { z-index: 1030 !important; }
</style>';
    }
}

if (!function_exists('auragold_party_select2_js_config')) {
    /**
     * JS config object for assets/js/auragold-party-select2.js
     *
     * @return array<string, string>
     */
    function auragold_party_select2_js_config(array $args = []): array
    {
        $a = array_merge(auragold_party_select2_defaults(), $args);
        $partyId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($a['party_id'] ?? 'customerId')) ?: 'customerId';
        $partyName = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($a['party_name'] ?? 'customerName')) ?: 'customerName';
        $billingState = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($a['billing_state'] ?? 'customerBillingState')) ?: 'customerBillingState';
        $wrapClass = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($a['wrap_class'] ?? 'auragold-party-select2-wrap')) ?: 'auragold-party-select2-wrap';
        $placeholder = (string) ($a['placeholder'] ?? 'Select customer...');
        $isSupplier = (stripos($placeholder, 'supplier') !== false);

        return [
            'partyId' => '#' . $partyId,
            'partyName' => '#' . $partyName,
            'billingState' => '#' . $billingState,
            'gstin' => '#customerGstin',
            'wrapClass' => $wrapClass,
            'containerClass' => $wrapClass === 'si-customer-select2-wrap'
                ? 'si-customer-select2-container'
                : 'auragold-party-select2-container',
            'dropdownClass' => $wrapClass === 'si-customer-select2-wrap'
                ? 'si-customer-select2-dropdown'
                : 'auragold-party-select2-dropdown',
            'searchUrl' => 'ajax/search-customers.php',
            'placeholder' => $placeholder,
            'noResultsText' => $isSupplier ? 'No supplier found' : 'No account found',
        ];
    }
}

if (!function_exists('auragold_echo_party_select2_config_script')) {
    function auragold_echo_party_select2_config_script(array $args = []): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $cfg = auragold_party_select2_js_config($args);
        echo '<script>window.AURAGOLD_PARTY_SELECT2 = '
            . json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)
            . ';</script>' . "\n";
    }
}

if (!function_exists('auragold_echo_party_select2_init')) {
    /** Load auragold-party-select2.js — call after js/previous-balance-common.js when the page uses previous balance. */
    function auragold_echo_party_select2_init(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $v = @filemtime(__DIR__ . '/../assets/js/auragold-party-select2.js');
        echo '<script src="assets/js/auragold-party-select2.js?v=' . (int) $v . '"></script>' . "\n";
    }
}

if (!function_exists('auragold_echo_party_select2_scripts')) {
    /**
     * @param bool $deferInitJs When true, only Select2 + config; call auragold_echo_party_select2_init() after previous-balance-common.js
     */
    function auragold_echo_party_select2_scripts(array $configArgs = [], bool $deferInitJs = false): void
    {
        static $done = false;
        if ($done) {
            if (!$deferInitJs) {
                auragold_echo_party_select2_init();
            }
            return;
        }
        $done = true;
        auragold_echo_party_select2_config_script($configArgs);
        echo '<link rel="stylesheet" href="assets/libs/select2/select2.css">' . "\n";
        echo '<script src="assets/libs/select2/select2.js"></script>' . "\n";
        if (!$deferInitJs) {
            auragold_echo_party_select2_init();
        }
    }
}

if (!function_exists('auragold_echo_party_select2_assets')) {
    function auragold_echo_party_select2_assets(): void
    {
        auragold_echo_party_select2_styles();
        auragold_echo_party_select2_scripts();
    }
}

if (!function_exists('auragold_party_select2_field')) {
    function auragold_party_select2_field(array $args = []): void
    {
        $a = array_merge(auragold_party_select2_defaults(), $args);
        $partyId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $a['party_id']) ?: 'customerId';
        $partyName = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $a['party_name']) ?: 'customerName';
        $billingState = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $a['billing_state']) ?: 'customerBillingState';
        $wrapClass = htmlspecialchars((string) $a['wrap_class'], ENT_QUOTES, 'UTF-8');
        $placeholder = htmlspecialchars((string) $a['placeholder'], ENT_QUOTES, 'UTF-8');
        $valueId = (int) ($a['value_id'] ?? 0);
        $valueName = htmlspecialchars((string) ($a['value_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $req = !empty($a['required']) ? ' required' : '';
        $dis = !empty($a['readonly']) ? ' disabled' : '';
        $addBtnId = htmlspecialchars((string) ($a['add_btn_id'] ?? 'addCustomerBtn'), ENT_QUOTES, 'UTF-8');
        $addTitle = htmlspecialchars((string) ($a['add_btn_title'] ?? 'Add / Edit Customer'), ENT_QUOTES, 'UTF-8');
        echo '<div style="display:flex;align-items:stretch;gap:4px;">';
        echo '<div class="' . $wrapClass . '">';
        echo '<select class="form-control form-control-sm" id="' . htmlspecialchars($partyId, ENT_QUOTES, 'UTF-8') . '" name="customer_id"' . $req . $dis . '>';
        echo '<option value="">' . $placeholder . '</option>';
        if ($valueId > 0 && $valueName !== '') {
            echo '<option value="' . $valueId . '" selected>' . $valueName . '</option>';
        } elseif ($valueId > 0) {
            echo '<option value="' . $valueId . '" selected>#' . $valueId . '</option>';
        }
        echo '</select>';
        echo '<input type="hidden" id="' . htmlspecialchars($partyName, ENT_QUOTES, 'UTF-8') . '" name="customer_name" value="' . $valueName . '">';
        if (!empty($a['show_billing_state'])) {
            echo '<input type="hidden" id="' . htmlspecialchars($billingState, ENT_QUOTES, 'UTF-8') . '" name="customer_billing_state" value="">';
        }
        echo '</div>';
        if (!empty($a['show_add_btn']) && empty($a['readonly'])) {
            echo '<button type="button" class="btn btn-sm btn-outline-secondary p-0" id="' . $addBtnId . '" title="' . $addTitle . '" style="width:32px;min-width:32px;line-height:1;align-self:stretch;"><i class="feather icon-plus"></i></button>';
        }
        echo '</div>';
    }
}
