<?php

/**
 * Transaction report — email template preview & send helpers.
 */

if (!function_exists('auragold_get_mail_settings_row')) {
    require_once __DIR__ . '/auragold_mail_settings_schema.php';
}
if (!function_exists('auragold_mail_settings_merge_for_send')) {
    require_once __DIR__ . '/auragold_mail_settings_defaults.php';
}

function auragold_tr_email_type_labels(): array
{
    return [
        'sale_invoice' => 'Sale Invoice',
        'sale_order' => 'Sale Order',
        'sale_return' => 'Sale Return',
        'sale_quotation' => 'Sale Quotation',
        'purchase_invoice' => 'Purchase Invoice',
        'purchase_return' => 'Purchase Return',
        'purchase_quotation' => 'Purchase Quotation',
    ];
}

function auragold_tr_email_print_path(string $type, int $id): string
{
    $id = (int) $id;
    switch ($type) {
        case 'sale_invoice':
            return '/sale-invoice-print.php?id=' . $id;
        case 'sale_order':
            return '/sale-order-print.php?id=' . $id;
        case 'sale_return':
            return '/sale-return.php?id=' . $id . '&print=1';
        case 'sale_quotation':
            return '/sale-quotations.php?id=' . $id . '&print=1';
        case 'purchase_invoice':
            return '/purchase-invoice-print.php?id=' . $id;
        case 'purchase_return':
            return '/purchase-return.php?id=' . $id . '&print=1';
        case 'purchase_quotation':
            return '/purchase-quotation.php?id=' . $id . '&print=1';
        default:
            return '/';
    }
}

function auragold_tr_email_meta_sql(string $type, int $id): string
{
    $id = (int) $id;
    switch ($type) {
        case 'sale_invoice':
            return 'SELECT invoice_no AS doc_no, customer_name AS party, invoice_date AS doc_date, grand_total FROM tbl_sale_invoices WHERE id = ' . $id . ' LIMIT 1';
        case 'sale_order':
            return 'SELECT order_no AS doc_no, customer_name AS party, order_date AS doc_date, grand_total FROM tbl_sale_orders WHERE id = ' . $id . ' LIMIT 1';
        case 'sale_return':
            return 'SELECT return_no AS doc_no, customer_name AS party, return_date AS doc_date, grand_total FROM tbl_sale_returns WHERE id = ' . $id . ' LIMIT 1';
        case 'sale_quotation':
            return 'SELECT quotation_no AS doc_no, customer_name AS party, quotation_date AS doc_date, grand_total FROM tbl_sale_quotations WHERE id = ' . $id . ' LIMIT 1';
        case 'purchase_invoice':
            return 'SELECT invoice_no AS doc_no, supplier_name AS party, invoice_date AS doc_date, grand_total FROM tbl_purchase_invoices WHERE id = ' . $id . ' LIMIT 1';
        case 'purchase_return':
            return 'SELECT return_no AS doc_no, supplier_name AS party, return_date AS doc_date, grand_total FROM tbl_purchase_returns WHERE id = ' . $id . ' LIMIT 1';
        case 'purchase_quotation':
            return 'SELECT quotation_no AS doc_no, supplier_name AS party, quotation_date AS doc_date, grand_total FROM tbl_purchase_quotations WHERE id = ' . $id . ' LIMIT 1';
        default:
            return 'SELECT "" AS doc_no, "" AS party, NULL AS doc_date, 0 AS grand_total FROM tbl_sale_invoices WHERE 1=0 LIMIT 1';
    }
}

/**
 * @return array{doc_no:string,party:string,doc_date:string,grand_total:float}|null
 */
function auragold_tr_email_fetch_meta($conn, string $type, int $id): ?array
{
    if (!$conn instanceof mysqli || $id <= 0) {
        return null;
    }
    $row = @getRecord(auragold_tr_email_meta_sql($type, $id));
    if (!is_array($row)) {
        return null;
    }
    $dateRaw = trim((string) ($row['doc_date'] ?? ''));
    $dateFmt = $dateRaw !== '' ? date('d-m-Y', strtotime($dateRaw)) : '';

    return [
        'doc_no' => trim((string) ($row['doc_no'] ?? '')),
        'party' => trim((string) ($row['party'] ?? '')),
        'doc_date' => $dateFmt,
        'grand_total' => (float) ($row['grand_total'] ?? 0),
    ];
}

function auragold_tr_email_company_name(string $type): string
{
    $print = function_exists('getInvoicePrintSettingsForDocument')
        ? getInvoicePrintSettingsForDocument($type)
        : [];
    $fromSettings = trim((string) ($print['company_name'] ?? ''));
    if ($fromSettings !== '') {
        return $fromSettings;
    }

    return defined('COMPANY_NAME') ? (string) COMPANY_NAME : 'Gold Matrix';
}

/**
 * @return array<string,string>
 */
function auragold_tr_email_template_vars($conn, string $type, int $id): array
{
    $meta = auragold_tr_email_fetch_meta($conn, $type, $id) ?? [
        'doc_no' => '',
        'party' => '',
        'doc_date' => '',
        'grand_total' => 0,
    ];
    $labels = auragold_tr_email_type_labels();
    $print = function_exists('getInvoicePrintSettingsForDocument')
        ? getInvoicePrintSettingsForDocument($type)
        : [];
    $docTitle = trim((string) ($print['invoice_title'] ?? ''));
    if ($docTitle === '' && function_exists('getInvoicePrintDefaultDocumentTitle')) {
        $docTitle = getInvoicePrintDefaultDocumentTitle($type);
    }

    return [
        'customer_name' => $meta['party'],
        'invoice_no' => $meta['doc_no'],
        'invoice_date' => $meta['doc_date'],
        'grand_total' => number_format($meta['grand_total'], 2),
        'company_name' => auragold_tr_email_company_name($type),
        'document_title' => $docTitle,
        'document_type' => $labels[$type] ?? str_replace('_', ' ', ucwords($type, '_')),
    ];
}

function auragold_tr_email_fallback_subject(string $type, array $vars): string
{
    $company = $vars['company_name'] ?? '';
    $docNo = $vars['invoice_no'] ?? '';
    $docLabel = $vars['document_type'] ?? $type;

    if ($type === 'sale_invoice') {
        $subject = ($docNo !== '' ? 'Sale Invoice ' . $docNo : 'Sale Invoice');
        return $subject . ($company !== '' ? ' — ' . $company : '');
    }

    $subject = $docLabel . ($docNo !== '' ? ' — ' . $docNo : '');
    return $subject . ($company !== '' ? ' — ' . $company : '');
}

function auragold_tr_email_fallback_body(string $type, array $vars, string $printUrl = ''): string
{
    $party = htmlspecialchars($vars['customer_name'] !== '' ? $vars['customer_name'] : 'Customer', ENT_QUOTES, 'UTF-8');
    $docNo = htmlspecialchars($vars['invoice_no'] ?? '', ENT_QUOTES, 'UTF-8');
    $docLabel = htmlspecialchars($vars['document_type'] ?? str_replace('_', ' ', $type), ENT_QUOTES, 'UTF-8');
    $company = htmlspecialchars($vars['company_name'] ?? '', ENT_QUOTES, 'UTF-8');

    if ($type === 'sale_invoice') {
        $invPart = $docNo !== '' ? ' ' . $docNo : '';
        return '<p>Dear ' . $party . ',</p>'
            . '<p>Please find attached a copy of your <strong>Sale Invoice' . $invPart . '</strong>.</p>'
            . '<p>Thank you for your business.</p>'
            . '<p>— ' . $company . '</p>';
    }

    $body = '<p>Dear ' . $party . ',</p>'
        . '<p>Please refer to your <strong>' . $docLabel . '</strong>'
        . ($docNo !== '' ? ' <strong>' . $docNo . '</strong>' : '') . '.</p>';
    if ($printUrl !== '') {
        $body .= '<p>You can open or print the voucher from:<br><a href="' . htmlspecialchars($printUrl, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($printUrl, ENT_QUOTES, 'UTF-8') . '</a></p>';
    }
    $body .= '<p>— ' . $company . '</p>';

    return $body;
}

/**
 * Build rendered subject/body from print settings template (or fallback).
 *
 * @return array{subject:string,body:string,vars:array<string,string>,template_used:bool}
 */
function auragold_tr_email_render_message($conn, string $type, int $id): array
{
    $vars = auragold_tr_email_template_vars($conn, $type, $id);
    $tpl = function_exists('getInvoicePrintEmailMessageTemplate')
        ? getInvoicePrintEmailMessageTemplate($type)
        : ['subject' => '', 'body' => ''];
    $rawSubject = trim((string) ($tpl['subject'] ?? ''));
    $rawBody = trim((string) ($tpl['body'] ?? ''));
    $templateUsed = ($rawSubject !== '' || $rawBody !== '');

    $printPath = auragold_tr_email_print_path($type, $id);
    $printUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . $printPath;

    if ($rawSubject !== '' && function_exists('auragold_invoice_email_template_render')) {
        $subject = auragold_invoice_email_template_render($rawSubject, $vars);
    } else {
        $subject = auragold_tr_email_fallback_subject($type, $vars);
    }

    if ($rawBody !== '' && function_exists('auragold_invoice_email_template_render')) {
        $body = auragold_invoice_email_template_render($rawBody, $vars);
    } else {
        $body = auragold_tr_email_fallback_body($type, $vars, $printUrl);
    }

    return [
        'subject' => $subject,
        'body' => $body,
        'vars' => $vars,
        'template_used' => $templateUsed,
    ];
}

/**
 * Full preview payload for the transaction report email modal.
 *
 * @return array{ok:bool,message?:string,recipient?:string,subject?:string,body?:string,doc_no?:string,party?:string,document_type_label?:string,has_pdf_attachment?:bool,template_used?:bool}
 */
function auragold_tr_email_build_preview($conn, string $type, int $id): array
{
    $allowed = array_keys(auragold_tr_email_type_labels());
    if ($id <= 0 || !in_array($type, $allowed, true)) {
        return ['ok' => false, 'message' => 'Invalid transaction.'];
    }

    $meta = auragold_tr_email_fetch_meta($conn, $type, $id);
    if ($meta === null) {
        return ['ok' => false, 'message' => 'Transaction not found.'];
    }

    $recipient = auragold_transaction_report_party_email($conn, $type, $id);
    $rendered = auragold_tr_email_render_message($conn, $type, $id);
    $labels = auragold_tr_email_type_labels();
    $mailCfg = auragold_mail_settings_merge_for_send(auragold_get_mail_settings_row($conn));
    $mailReady = auragold_mail_settings_is_ready($mailCfg);

    return [
        'ok' => true,
        'recipient' => $recipient,
        'subject' => $rendered['subject'],
        'body' => $rendered['body'],
        'doc_no' => $meta['doc_no'],
        'party' => $meta['party'],
        'document_type_label' => $labels[$type] ?? $type,
        'has_pdf_attachment' => ($type === 'sale_invoice'),
        'template_used' => $rendered['template_used'],
        'mail_uses_gmail' => auragold_mail_is_gmail_smtp($mailCfg),
        'mail_smtp_host' => trim((string) ($mailCfg['smtp_host'] ?? '')),
        'mail_ready' => !empty($mailReady['ok']),
        'mail_config_message' => empty($mailReady['ok']) ? ($mailReady['message'] ?? '') : '',
    ];
}
