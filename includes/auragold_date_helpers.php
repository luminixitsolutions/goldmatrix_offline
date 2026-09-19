<?php
/**
 * Date helpers for voucher due/order dates.
 */
if (!function_exists('auragold_ymd_or_default')) {
    /**
     * Return a valid Y-m-d date, or $default when empty/invalid/0000-00-00.
     * Pass '' as $default to keep Due Date blank on new vouchers.
     *
     * @param mixed       $raw
     * @param string|null $default
     */
    function auragold_ymd_or_default($raw, $default = null): string
    {
        if ($default === null) {
            $default = '';
        } else {
            $default = (string) $default;
        }
        $d = substr(trim((string) $raw), 0, 10);
        if ($d === '' || $d === '0000-00-00' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            if ($default === '' || $default === '0000-00-00') {
                return '';
            }
            $df = substr(trim($default), 0, 10);
            if ($df === '' || $df === '0000-00-00' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $df)) {
                return '';
            }

            return $df;
        }
        $y = (int) substr($d, 0, 4);
        $m = (int) substr($d, 5, 2);
        $day = (int) substr($d, 8, 2);
        if (!checkdate($m, $day, $y)) {
            if ($default === '' || $default === '0000-00-00') {
                return '';
            }
            $df = substr(trim($default), 0, 10);

            return ($df !== '' && $df !== '0000-00-00' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $df)) ? $df : '';
        }

        return $d;
    }
}

if (!function_exists('auragold_parse_ui_date_to_ymd')) {
    /**
     * Convert UI dates (d-m-Y, d/m/Y, Y-m-d) to Y-m-d for MySQL DATE columns.
     * Returns '' when empty/invalid.
     *
     * @param mixed $raw
     */
    function auragold_parse_ui_date_to_ymd($raw): string
    {
        $s = trim((string) $raw);
        if ($s === '' || $s === '0000-00-00') {
            return '';
        }
        // Already Y-m-d (optionally with time)
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
            $y = (int) $m[1];
            $mo = (int) $m[2];
            $d = (int) $m[3];
            return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : '';
        }
        // d-m-Y or d/m/Y (also dd-mm-yyyy)
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $s, $m)) {
            $d = (int) $m[1];
            $mo = (int) $m[2];
            $y = (int) $m[3];
            return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : '';
        }
        $ts = strtotime($s);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }
        return '';
    }
}

if (!function_exists('auragold_due_date_value')) {
    /**
     * Due date for forms: saved date when valid, otherwise blank (never auto today).
     *
     * @param mixed $raw
     */
    function auragold_due_date_value($raw): string
    {
        return auragold_ymd_or_default($raw, '');
    }
}
