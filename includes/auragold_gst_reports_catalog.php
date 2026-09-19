<?php
/**
 * GST Reports catalogue — filing returns only:
 * GSTR-1, GSTR-3B, GSTR-2B Reconciliation, GSTR-9.
 *
 * @return array<string, array{key:string,label:string,purpose:string,group:string,icon:string}>
 */
function auragold_gst_reports_catalog(): array
{
    static $cat = null;
    if ($cat !== null) {
        return $cat;
    }

    $cat = [
        'gstr1' => [
            'key' => 'gstr1',
            'label' => 'GSTR-1',
            'purpose' => 'Outward supplies (B2B, B2C, HSN, notes)',
            'group' => 'returns',
            'icon' => 'icon-file-text',
        ],
        'gstr3b' => [
            'key' => 'gstr3b',
            'label' => 'GSTR-3B',
            'purpose' => 'Monthly summary — tax payable & ITC',
            'group' => 'returns',
            'icon' => 'icon-pie-chart',
        ],
        'gstr2b' => [
            'key' => 'gstr2b',
            'label' => 'GSTR-2B Reconciliation',
            'purpose' => 'Purchase books vs GSTR-2B',
            'group' => 'returns',
            'icon' => 'icon-repeat',
        ],
        'gstr9' => [
            'key' => 'gstr9',
            'label' => 'GSTR-9',
            'purpose' => 'Annual return summary',
            'group' => 'returns',
            'icon' => 'icon-calendar',
        ],
    ];

    return $cat;
}

/**
 * @return array<string, string>
 */
function auragold_gst_report_groups(): array
{
    return [
        'returns' => 'GST Returns',
    ];
}

/**
 * GSTR-1 internal sections (tabs).
 *
 * @return array<string, array{key:string,label:string}>
 */
function auragold_gst_gstr1_sections(): array
{
    return [
        'b2b' => ['key' => 'b2b', 'label' => 'B2B'],
        'b2c_large' => ['key' => 'b2c_large', 'label' => 'B2C Large'],
        'b2c_small' => ['key' => 'b2c_small', 'label' => 'B2C Small'],
        'credit_note' => ['key' => 'credit_note', 'label' => 'Credit Notes'],
        'debit_note' => ['key' => 'debit_note', 'label' => 'Debit Notes'],
        'hsn' => ['key' => 'hsn', 'label' => 'HSN Summary'],
    ];
}

function auragold_gst_report_is_valid(string $type): bool
{
    return isset(auragold_gst_reports_catalog()[$type]);
}

function auragold_gst_report_meta(string $type): ?array
{
    $cat = auragold_gst_reports_catalog();

    return $cat[$type] ?? null;
}

function auragold_gst_report_href(string $type): string
{
    return 'gst-report.php?type=' . rawurlencode($type);
}
