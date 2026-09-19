<?php

/**
 * Bank statement Excel parse + match against GoldMatrix ledger rows.
 */
require_once __DIR__ . '/auragold_bank_reconciliation.php';

if (!function_exists('auragold_bank_reconciliation_excel_headers')) {
    function auragold_bank_reconciliation_excel_headers(): array
    {
        return ['Date', 'Reference No', 'Description', 'Withdrawal', 'Deposit'];
    }
}

if (!function_exists('auragold_bank_reconciliation_parse_amount')) {
    function auragold_bank_reconciliation_parse_amount($val): float
    {
        if ($val === null || $val === '') {
            return 0.0;
        }
        if (is_numeric($val)) {
            return round((float) $val, 2);
        }
        $s = trim((string) $val);
        $s = str_replace([',', ' '], ['', ''], $s);
        if ($s === '' || $s === '-') {
            return 0.0;
        }
        return round((float) $s, 2);
    }
}

if (!function_exists('auragold_bank_reconciliation_parse_date_cell')) {
    function auragold_bank_reconciliation_parse_date_cell($val): string
    {
        if ($val === null || $val === '') {
            return '';
        }
        if (is_numeric($val)) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $val);
                return $dt->format('Y-m-d');
            } catch (Throwable $e) {
                return '';
            }
        }
        $s = trim((string) $val);
        if ($s === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $s, $m)) {
            $y = (int) $m[3];
            if ($y < 100) {
                $y += ($y >= 70 ? 1900 : 2000);
            }
            return sprintf('%04d-%02d-%02d', $y, (int) $m[2], (int) $m[1]);
        }
        $ts = strtotime($s);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }
        return '';
    }
}

if (!function_exists('auragold_bank_reconciliation_normalize_header_key')) {
    function auragold_bank_reconciliation_normalize_header_key(string $h): string
    {
        $h = strtolower(trim(preg_replace('/\s+/', ' ', $h)));
        $map = [
            'txn date' => 'date',
            'transaction date' => 'date',
            'value date' => 'date',
            'ref' => 'reference',
            'reference' => 'reference',
            'reference no' => 'reference',
            'reference number' => 'reference',
            'cheque no' => 'reference',
            'narration' => 'description',
            'particulars' => 'description',
            'remarks' => 'description',
            'withdrawal' => 'withdrawal',
            'withdrawal dr' => 'withdrawal',
            'withdrawal (dr)' => 'withdrawal',
            'debit' => 'withdrawal',
            'dr' => 'withdrawal',
            'deposit' => 'deposit',
            'deposit cr' => 'deposit',
            'deposit (cr)' => 'deposit',
            'credit' => 'deposit',
            'cr' => 'deposit',
            'ledger debit' => 'ledger_debit',
            'ledger credit' => 'ledger_credit',
            'software debit' => 'ledger_debit',
            'software credit' => 'ledger_credit',
        ];
        return $map[$h] ?? $h;
    }
}

if (!function_exists('auragold_bank_reconciliation_sheet_cell_value')) {
    function auragold_bank_reconciliation_sheet_cell_value($sheet, int $col, int $row)
    {
        $ref = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
        return $sheet->getCell($ref)->getValue();
    }
}

if (!function_exists('auragold_bank_reconciliation_parse_excel_rows')) {
    /**
     * @return array{rows: list<array<string,mixed>>, errors: list<string>}
     */
    function auragold_bank_reconciliation_parse_excel_rows(string $filePath): array
    {
        require_once __DIR__ . '/../vendor/autoload.php';

        $errors = [];
        $rows = [];

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        } catch (Throwable $e) {
            return ['rows' => [], 'errors' => ['Could not read Excel file: ' . $e->getMessage()]];
        }

        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = (int) $sheet->getHighestRow();
        $highestCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

        $headerMap = [];
        $headerRow = 0;
        for ($r = 1; $r <= min(15, $highestRow); $r++) {
            $tmp = [];
            for ($c = 1; $c <= $highestCol; $c++) {
                $cell = trim((string) auragold_bank_reconciliation_sheet_cell_value($sheet, $c, $r));
                if ($cell !== '') {
                    $key = auragold_bank_reconciliation_normalize_header_key($cell);
                    $tmp[$key] = $c;
                }
            }
            if (isset($tmp['date']) && (isset($tmp['withdrawal']) || isset($tmp['deposit']) || isset($tmp['ledger_debit']) || isset($tmp['ledger_credit']))) {
                $headerMap = $tmp;
                $headerRow = $r;
                break;
            }
        }

        if ($headerRow <= 0) {
            return ['rows' => [], 'errors' => ['Header row not found. Use columns: Date, Reference No, Description, Withdrawal, Deposit (see sample file).']];
        }

        for ($r = $headerRow + 1; $r <= $highestRow; $r++) {
            $dateRaw = auragold_bank_reconciliation_sheet_cell_value($sheet, (int) $headerMap['date'], $r);
            $date = auragold_bank_reconciliation_parse_date_cell($dateRaw);
            $ref = isset($headerMap['reference']) ? trim((string) auragold_bank_reconciliation_sheet_cell_value($sheet, (int) $headerMap['reference'], $r)) : '';
            $desc = isset($headerMap['description']) ? trim((string) auragold_bank_reconciliation_sheet_cell_value($sheet, (int) $headerMap['description'], $r)) : '';

            $withdrawal = 0.0;
            $deposit = 0.0;
            $ledger_debit = 0.0;
            $ledger_credit = 0.0;

            if (isset($headerMap['withdrawal'])) {
                $withdrawal = auragold_bank_reconciliation_parse_amount(auragold_bank_reconciliation_sheet_cell_value($sheet, (int) $headerMap['withdrawal'], $r));
            }
            if (isset($headerMap['deposit'])) {
                $deposit = auragold_bank_reconciliation_parse_amount(auragold_bank_reconciliation_sheet_cell_value($sheet, (int) $headerMap['deposit'], $r));
            }
            if (isset($headerMap['ledger_debit'])) {
                $ledger_debit = auragold_bank_reconciliation_parse_amount(auragold_bank_reconciliation_sheet_cell_value($sheet, (int) $headerMap['ledger_debit'], $r));
            }
            if (isset($headerMap['ledger_credit'])) {
                $ledger_credit = auragold_bank_reconciliation_parse_amount(auragold_bank_reconciliation_sheet_cell_value($sheet, (int) $headerMap['ledger_credit'], $r));
            }

            if ($date === '' && $ref === '' && $desc === '' && $withdrawal <= 0 && $deposit <= 0 && $ledger_debit <= 0 && $ledger_credit <= 0) {
                continue;
            }
            if ($date === '') {
                $errors[] = 'Row ' . $r . ': invalid or missing date.';
                continue;
            }

            $rows[] = [
                'row_num'       => $r,
                'date'          => $date,
                'reference'     => $ref,
                'description'   => $desc,
                'withdrawal'    => $withdrawal,
                'deposit'       => $deposit,
                'ledger_debit'  => $ledger_debit,
                'ledger_credit' => $ledger_credit,
            ];
        }

        if (empty($rows) && empty($errors)) {
            $errors[] = 'No transaction rows found in the uploaded file.';
        }

        return ['rows' => $rows, 'errors' => $errors];
    }
}

if (!function_exists('auragold_bank_reconciliation_match_rows')) {
    /**
     * Match bank statement rows to software ledger rows.
     *
     * Bank: Withdrawal = money out → software credit; Deposit = money in → software debit.
     * Also supports exported GoldMatrix columns (ledger debit/credit direct match).
     *
     * @param list<array<string,mixed>> $softwareRows
     * @param list<array<string,mixed>> $bankRows
     */
    function auragold_bank_reconciliation_match_rows(array $softwareRows, array $bankRows): array
    {
        $used_sw = [];
        $used_bk = [];
        $matched = [];
        $eps = 0.02;

        $try_match = function (array $bank, array $sw) use ($eps): bool {
            $bd = substr((string) ($bank['date'] ?? ''), 0, 10);
            $sd = substr((string) ($sw['date'] ?? ''), 0, 10);
            if ($bd === '' || $sd === '' || $bd !== $sd) {
                return false;
            }

            $b_wd = (float) ($bank['withdrawal'] ?? 0);
            $b_dep = (float) ($bank['deposit'] ?? 0);
            $b_ld = (float) ($bank['ledger_debit'] ?? 0);
            $b_lc = (float) ($bank['ledger_credit'] ?? 0);

            $s_d = (float) ($sw['debit'] ?? 0);
            $s_c = (float) ($sw['credit'] ?? 0);

            if ($b_ld > $eps || $b_lc > $eps) {
                if ($b_ld > $eps && abs($b_ld - $s_d) <= $eps) {
                    return true;
                }
                if ($b_lc > $eps && abs($b_lc - $s_c) <= $eps) {
                    return true;
                }
            }

            if ($b_dep > $eps && abs($b_dep - $s_d) <= $eps) {
                return true;
            }
            if ($b_wd > $eps && abs($b_wd - $s_c) <= $eps) {
                return true;
            }

            return false;
        };

        foreach ($bankRows as $bi => $bank) {
            $found = false;
            foreach ($softwareRows as $si => $sw) {
                if (!empty($used_sw[$si])) {
                    continue;
                }
                if ($try_match($bank, $sw)) {
                    $used_sw[$si] = true;
                    $used_bk[$bi] = true;
                    $matched[] = [
                        'bank'     => $bank,
                        'software' => $sw,
                    ];
                    $found = true;
                    break;
                }
            }
        }

        $missing_in_software = [];
        foreach ($bankRows as $bi => $bank) {
            if (empty($used_bk[$bi])) {
                $missing_in_software[] = $bank;
            }
        }

        $missing_in_bank = [];
        foreach ($softwareRows as $si => $sw) {
            if (empty($used_sw[$si])) {
                $missing_in_bank[] = $sw;
            }
        }

        return [
            'matched'               => $matched,
            'missing_in_software'   => $missing_in_software,
            'missing_in_bank'       => $missing_in_bank,
            'counts'                => [
                'bank_total'            => count($bankRows),
                'software_total'        => count($softwareRows),
                'matched'               => count($matched),
                'missing_in_software'   => count($missing_in_software),
                'missing_in_bank'       => count($missing_in_bank),
            ],
        ];
    }
}
