<?php
/**
 * Print views: show tbl_currency.symbol for the invoice/order currency (stored as master name).
 */
if (!function_exists('invoice_print_currency_symbol')) {
    function invoice_print_currency_symbol($conn, $invoice_currency)
    {
        $c = trim((string) $invoice_currency);
        if ($c === '') {
            $c = 'AED';
        }
        if (!$conn || !function_exists('getRecord')) {
            return $c;
        }
        $tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_currency'");
        if (!$tbl || mysqli_num_rows($tbl) === 0) {
            if ($tbl) {
                mysqli_free_result($tbl);
            }
            return $c;
        }
        mysqli_free_result($tbl);

        $e = mysqli_real_escape_string($conn, $c);
        $row = @getRecord("SELECT symbol, name FROM tbl_currency WHERE status = 1 AND LOWER(TRIM(name)) = LOWER(TRIM('$e')) LIMIT 1");
        if (!$row) {
            $row = @getRecord("SELECT symbol, name FROM tbl_currency WHERE status = 1 AND LOWER(TRIM(COALESCE(symbol,''))) = LOWER(TRIM('$e')) LIMIT 1");
        }
        if (!is_array($row)) {
            return $c;
        }
        $sym = trim((string) ($row['symbol'] ?? ''));
        if ($sym !== '') {
            return $sym;
        }
        $name = trim((string) ($row['name'] ?? ''));
        return $name !== '' ? $name : $c;
    }
}
