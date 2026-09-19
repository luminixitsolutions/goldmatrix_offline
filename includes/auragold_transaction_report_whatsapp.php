<?php

/**
 * Plain-text WhatsApp message for transaction report rows.
 *
 * @param array<string,mixed> $t Transaction row from transaction-report.php
 */
function auragold_tr_whatsapp_plain_message(array $t, string $baseUrl = ''): string
{
    $party = trim((string) ($t['party_name'] ?? ''));
    if ($party === '') {
        $party = 'Customer';
    }

    $typeLabel = trim((string) ($t['type_label'] ?? ''));
    if ($typeLabel === '') {
        $typeLabel = 'Transaction';
    }

    $voucher = trim((string) ($t['voucher_no'] ?? ''));
    $dateRaw = trim((string) ($t['date'] ?? ''));
    $dateFmt = ($dateRaw !== '' && $dateRaw !== '0000-00-00') ? date('d-m-Y', strtotime($dateRaw)) : '';

    $amount = (float) ($t['amount'] ?? 0);
    $type = (string) ($t['type'] ?? '');
    $balance = (float) ($t['balance'] ?? 0);
    if ($type === 'purchase_invoice') {
        $balance = $amount;
    } elseif ($type === 'purchase_return') {
        $balance = -abs($balance);
    }

    $branch = trim((string) ($t['branch_name'] ?? ''));
    $salesPerson = trim((string) ($t['sales_person'] ?? ''));
    $extra = trim((string) ($t['voucher_ex_col3'] ?? $t['tx_ref_display'] ?? $t['against_of'] ?? $t['against_sale_order_no'] ?? $t['against_pi'] ?? ''));

    $company = defined('COMPANY_NAME') ? trim((string) COMPANY_NAME) : 'GoldMatrix';
    if ($company === '') {
        $company = 'GoldMatrix';
    }

    $lines = [];
    $lines[] = 'Dear ' . $party . ',';
    $lines[] = '';

    $docLine = 'Regarding your ' . $typeLabel;
    if ($voucher !== '') {
        $docLine .= ' (' . $voucher . ')';
    }
    $docLine .= '.';
    $lines[] = $docLine;
    $lines[] = '';

    if ($dateFmt !== '') {
        $lines[] = 'Date: ' . $dateFmt;
    }
    $lines[] = 'Amount: ₹' . number_format($amount, 2);
    if ($type !== 'jobwork_order') {
        $balPrefix = $balance < 0 ? '-₹' : '₹';
        $lines[] = 'Balance: ' . $balPrefix . number_format(abs($balance), 2);
    }
    if ($branch !== '' && $branch !== '—') {
        $lines[] = 'Branch: ' . $branch;
    }
    if ($salesPerson !== '' && strtoupper($salesPerson) !== 'NA') {
        $sp = preg_match('/^SP-/i', $salesPerson) ? $salesPerson : ('SP-' . $salesPerson);
        $lines[] = 'Sales Person: ' . $sp;
    }
    if ($extra !== '' && strtoupper($extra) !== 'NA') {
        $lines[] = 'Reference: ' . $extra;
    }

    $printLink = trim((string) ($t['print_link'] ?? ''));
    if ($printLink !== '' && $baseUrl !== '') {
        $printUrl = rtrim($baseUrl, '/') . '/' . ltrim($printLink, '/');
        $lines[] = '';
        $lines[] = 'View / print: ' . $printUrl;
    }

    $lines[] = '';
    $lines[] = '— ' . $company;

    return implode("\n", $lines);
}

/**
 * Fill party_mobile from tbl_customers when missing (match by party name).
 *
 * @param list<array<string,mixed>> $rows
 */
function transaction_report_enrich_party_mobile(array &$rows, $conn): void
{
    if (!($conn instanceof mysqli) || $rows === []) {
        return;
    }

    $names = [];
    foreach ($rows as $t) {
        if (!empty($t['party_mobile'])) {
            continue;
        }
        $name = trim((string) ($t['party_name'] ?? ''));
        if ($name !== '') {
            $names[$name] = true;
        }
    }
    if ($names === []) {
        return;
    }

    $mobileByName = [];
    $nameList = array_keys($names);
    foreach (array_chunk($nameList, 80) as $chunk) {
        $esc = [];
        foreach ($chunk as $n) {
            $esc[] = "'" . mysqli_real_escape_string($conn, $n) . "'";
        }
        $in = implode(',', $esc);
        $sql = "SELECT name,
            TRIM(COALESCE(NULLIF(TRIM(mobile_no), ''), NULLIF(TRIM(phone_no), ''), '')) AS party_mobile
            FROM tbl_customers
            WHERE (status IS NULL OR status = 1) AND name IN ($in)";
        $list = @getList($sql);
        if (!is_array($list)) {
            continue;
        }
        foreach ($list as $r) {
            $n = trim((string) ($r['name'] ?? ''));
            $m = trim((string) ($r['party_mobile'] ?? ''));
            if ($n !== '' && $m !== '' && !isset($mobileByName[$n])) {
                $mobileByName[$n] = $m;
            }
        }
    }

    foreach ($rows as $i => $t) {
        if (!empty($t['party_mobile'])) {
            continue;
        }
        $name = trim((string) ($t['party_name'] ?? ''));
        if ($name !== '' && isset($mobileByName[$name])) {
            $rows[$i]['party_mobile'] = $mobileByName[$name];
        }
    }
}
