<?php
require_once __DIR__ . '/includes/auragold_assets.php';
?>
<?php auragold_echo_script('assets/libs/popper/popper.js', true); ?>
<?php auragold_echo_script('assets/js/bootstrap.js', true); ?>
<?php auragold_echo_script('assets/js/sidenav.js', true); ?>
<?php auragold_echo_script('assets/libs/perfect-scrollbar/perfect-scrollbar.js', true); ?>
<?php auragold_echo_script('assets/js/layout-helpers.js', true); ?>
<?php auragold_echo_script('assets/js/demo.js', true); ?>
<?php auragold_echo_script('assets/libs/bootbox/bootbox.js', true); ?>
<?php auragold_echo_script('assets/libs/bootstrap-sweetalert/bootstrap-sweetalert.js', true); ?>
<?php auragold_echo_script('assets/js/pages/ui_modals.js', true); ?>
<?php auragold_echo_script('assets/js/material-ripple.js', true); ?>
<?php auragold_echo_script('assets/js/auragold-due-date-default.js', true); ?>
<?php
$__auragold_sp_cands = [];
if (function_exists('auragold_logged_in_sales_person_match_candidates')) {
    $__auragold_sp_cands = auragold_logged_in_sales_person_match_candidates();
} elseif (is_file(__DIR__ . '/includes/user_management_schema.php')) {
    require_once __DIR__ . '/includes/user_management_schema.php';
    if (function_exists('auragold_logged_in_sales_person_match_candidates')) {
        $__auragold_sp_cands = auragold_logged_in_sales_person_match_candidates();
    }
}
?>
<script>
window.AURAGOLD_LOGIN_SP_CANDIDATES = <?php echo json_encode(array_values($__auragold_sp_cands), JSON_UNESCAPED_UNICODE); ?>;
</script>
<?php auragold_echo_script('assets/js/auragold-default-sales-person.js', true); ?>
<?php auragold_echo_script('assets/js/auragold-customer-party-select.js', true); ?>
<?php auragold_echo_script('assets/js/auragold-payment-modal-common.js', true); ?>

<!-- Ledger Details / customer modal: mobile number digits only -->
<script>
(function () {
    function bindLedgerMobileDigits() {
        if (typeof jQuery === 'undefined') {
            return;
        }
        jQuery(document).off('input.auragoldLedgerMobile', '#ledgerMobileNo').on('input.auragoldLedgerMobile', '#ledgerMobileNo', function () {
            var digits = String(this.value).replace(/\D/g, '');
            if (this.value !== digits) {
                this.value = digits;
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindLedgerMobileDigits);
    } else {
        bindLedgerMobileDigits();
    }
})();
</script>
<?php require __DIR__ . '/includes/google_translate_scripts.php'; ?>
