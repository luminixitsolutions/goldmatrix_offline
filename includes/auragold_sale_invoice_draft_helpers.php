<?php

/**
 * Sale invoice draft helpers — temporary saves without consuming bill series numbers.
 */

function auragold_sale_invoice_is_draft_status($status)
{
    return strtolower(trim((string) $status)) === 'draft';
}

function auragold_sale_invoice_is_draft_placeholder_no($invoice_no)
{
    $n = trim((string) $invoice_no);
    if ($n === '') {
        return true;
    }
    return (bool) preg_match('/^DRAFT-/i', $n) || (bool) preg_match('/^__DRAFT_/i', $n);
}

function auragold_sale_invoice_draft_label($id)
{
    return 'DRAFT-' . (int) $id;
}

function auragold_sale_invoice_draft_list_where_sql($conn, $alias = 'si')
{
    $branch = '';
    if (function_exists('auragold_sale_invoices_branch_where_sql')) {
        $branch = auragold_sale_invoices_branch_where_sql($conn, $alias);
    }
    $a = $alias;
    return " WHERE LOWER(TRIM({$a}.status)) = 'draft'"
        . " AND (TRIM(COALESCE({$a}.invoice_no, '')) = ''"
        . " OR {$a}.invoice_no LIKE 'DRAFT-%'"
        . " OR {$a}.invoice_no LIKE '__DRAFT_%')"
        . $branch;
}
