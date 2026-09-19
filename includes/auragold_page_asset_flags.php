<?php
/**
 * Per-page asset flags — avoid loading DataTables / export bundles globally.
 */
if (!function_exists('auragold_page_basename')) {
    function auragold_page_basename(): string
    {
        static $bn = null;
        if ($bn !== null) {
            return $bn;
        }
        $bn = basename((string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));

        return $bn;
    }
}

if (!function_exists('auragold_page_wants_datatables')) {
    function auragold_page_wants_datatables(): bool
    {
        $bn = auragold_page_basename();
        if ($bn === '') {
            return false;
        }
        if (preg_match('/-report\.php$/i', $bn)) {
            return true;
        }
        if (preg_match('/^master-/i', $bn)) {
            return true;
        }
        static $explicit = [
            'accountledger-report.php',
            'Ledger-Balance-Report.php',
            'ledger-balance-report.php',
            'customer-report.php',
            'day-report.php',
            'ageing-report.php',
            'transaction-report.php',
            'kyc-report.php',
            'reward-point-report.php',
            'installment-report.php',
            'department-report.php',
            'outsource-department-report.php',
            'closing-report.php',
            'trial-balance-detailed-report.php',
            'sale-analysis.php',
            'gold-silver-financial-analysis.php',
            'diamond-stone-financial-analysis.php',
            'imitation-watches-financial-analysis.php',
            'salesperson-performance.php',
            'vendor-report.php',
            'purchase-financial-analysis.php',
            'consignment-out-report.php',
            'stock-history.php',
            'stock-history-ledger.php',
            'stock-transfer-history.php',
            'stock-receive-history.php',
            'stock-transfer.php',
            'notifications.php',
            'crm.php',
            'user-management.php',
            'role-management.php',
            'permission-management.php',
            'whitelist-management.php',
            'blocklist-management.php',
            'masters.php',
            'product-opening.php',
            'account-ledger.php',
            'cheque-entry.php',
            'investment-fund.php',
            'jobwork-queue.php',
            'manufacturing-process.php',
        ];
        return in_array($bn, $explicit, true);
    }
}

if (!function_exists('auragold_page_wants_product_list_css')) {
    function auragold_page_wants_product_list_css(): bool
    {
        $bn = auragold_page_basename();
        if ($bn === '') {
            return false;
        }
        if (preg_match('/(invoice|order|quotation|return|voucher|catalogue|opening|transfer|consignment|barcode|stock-journal|material-|repair-|jobwork|pos-sale)/i', $bn)) {
            return true;
        }
        static $explicit = [
            'gold-and-silver.php',
            'diamond-and-stones.php',
            'platinum-stock.php',
            'product-opening.php',
            'jewelry-catalogue-create.php',
        ];
        return in_array($bn, $explicit, true);
    }
}

if (!function_exists('auragold_page_wants_financial_export_js')) {
    function auragold_page_wants_financial_export_js(): bool
    {
        $bn = auragold_page_basename();
        if ($bn === '') {
            return false;
        }
        if (preg_match('/-report\.php$/i', $bn)) {
            return true;
        }
        static $explicit = [
            'trial-balance.php',
            'balance-sheet.php',
            'profit-loss.php',
            'cash-flow.php',
            'fund-flow.php',
            'chart-of-account.php',
            'tax-return.php',
            'sale-analysis.php',
            'gold-silver-financial-analysis.php',
            'diamond-stone-financial-analysis.php',
            'imitation-watches-financial-analysis.php',
            'salesperson-performance.php',
            'vendor-report.php',
            'purchase-financial-analysis.php',
            'trial-balance-detailed-report.php',
            'accountledger-report.php',
            'ledger-balance-report.php',
            'Ledger-Balance-Report.php',
        ];
        return in_array($bn, $explicit, true);
    }
}
