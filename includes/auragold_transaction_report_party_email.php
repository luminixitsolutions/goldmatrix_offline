<?php

/**
 * Resolve party email (tbl_customers.mail_id) for transaction report mail action.
 *
 * @return string trimmed email or ''
 */
function auragold_transaction_report_party_email($conn, string $type, int $id): string
{
    if (!$conn instanceof mysqli || $id <= 0) {
        return '';
    }
    $type = strtolower(trim($type));
    $eid = (int) $id;

    $q = static function ($conn, string $sql): string {
        $row = @getRecord($sql);
        if (!is_array($row)) {
            return '';
        }
        $m = trim((string) ($row['mail_id'] ?? ''));

        return filter_var($m, FILTER_VALIDATE_EMAIL) ? $m : '';
    };

    switch ($type) {
        case 'sale_invoice':
            return $q($conn, 'SELECT TRIM(COALESCE(c.mail_id, "")) AS mail_id FROM tbl_sale_invoices si LEFT JOIN tbl_customers c ON c.id = si.customer_id WHERE si.id = ' . $eid . ' LIMIT 1');
        case 'sale_order':
            return $q($conn, 'SELECT TRIM(COALESCE(c.mail_id, "")) AS mail_id FROM tbl_sale_orders so LEFT JOIN tbl_customers c ON c.id = so.customer_id WHERE so.id = ' . $eid . ' LIMIT 1');
        case 'sale_return':
            return $q($conn, 'SELECT TRIM(COALESCE(c.mail_id, "")) AS mail_id FROM tbl_sale_returns sr LEFT JOIN tbl_customers c ON c.id = sr.customer_id WHERE sr.id = ' . $eid . ' LIMIT 1');
        case 'sale_quotation':
            return $q($conn, 'SELECT TRIM(COALESCE(c.mail_id, "")) AS mail_id FROM tbl_sale_quotations sq LEFT JOIN tbl_customers c ON c.id = sq.customer_id WHERE sq.id = ' . $eid . ' LIMIT 1');
        case 'purchase_invoice':
            return $q($conn, 'SELECT TRIM(COALESCE(c.mail_id, "")) AS mail_id FROM tbl_purchase_invoices pi LEFT JOIN tbl_customers c ON c.id = pi.supplier_id WHERE pi.id = ' . $eid . ' LIMIT 1');
        case 'purchase_return':
            return $q($conn, 'SELECT TRIM(COALESCE(c.mail_id, "")) AS mail_id FROM tbl_purchase_returns pr LEFT JOIN tbl_customers c ON c.id = pr.supplier_id WHERE pr.id = ' . $eid . ' LIMIT 1');
        case 'purchase_quotation':
            return $q($conn, 'SELECT TRIM(COALESCE(c.mail_id, "")) AS mail_id FROM tbl_purchase_quotations pq LEFT JOIN tbl_customers c ON c.id = pq.supplier_id WHERE pq.id = ' . $eid . ' LIMIT 1');
        default:
            return '';
    }
}
