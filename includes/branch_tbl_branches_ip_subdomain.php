<?php
/**
 * Optional tbl_branches columns: public IP and branch host (e.g. pune.example.com).
 * Used with conn_master (registry) and with branch DBs before tbl_branches mirror (SELECT *).
 */
if (!function_exists('auragold_ensure_branches_ip_subdomain_columns')) {
    /**
     * Add ip_address and subdomain_url to $databaseName.tbl_branches if missing.
     */
    function auragold_ensure_branches_ip_subdomain_columns(mysqli $link, string $databaseName): bool {
        $databaseName = trim($databaseName);
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $databaseName)) {
            return false;
        }
        $d    = '`' . str_replace('`', '``', $databaseName) . '`';
        $tref = $d . '.`tbl_branches`';
        $tb   = @mysqli_query($link, "SHOW TABLES FROM $d LIKE 'tbl_branches'");
        if (!$tb || mysqli_num_rows($tb) < 1) {
            if ($tb) {
                mysqli_free_result($tb);
            }
            return false;
        }
        mysqli_free_result($tb);
        $q1 = @mysqli_query($link, "SHOW COLUMNS FROM $tref LIKE 'ip_address'");
        if (!$q1 || mysqli_num_rows($q1) < 1) {
            if ($q1) {
                mysqli_free_result($q1);
            }
            if (!@mysqli_query(
                $link,
                "ALTER TABLE $tref ADD COLUMN `ip_address` VARCHAR(45) NULL DEFAULT NULL COMMENT 'Branch / server public IP (optional)'"
            )) {
                return false;
            }
        } else {
            mysqli_free_result($q1);
        }
        $q2 = @mysqli_query($link, "SHOW COLUMNS FROM $tref LIKE 'subdomain_url'");
        if (!$q2 || mysqli_num_rows($q2) < 1) {
            if ($q2) {
                mysqli_free_result($q2);
            }
            if (!@mysqli_query(
                $link,
                "ALTER TABLE $tref ADD COLUMN `subdomain_url` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Branch host, e.g. pune.goldmatrix.com (optional; routing uses code = first label)'"
            )) {
                return false;
            }
        } else {
            mysqli_free_result($q2);
        }
        return true;
    }
}

if (!function_exists('auragold_ensure_branches_ip_subdomain_columns_on_registry')) {
    function auragold_ensure_branches_ip_subdomain_columns_on_registry(mysqli $link): bool {
        if (!defined('AURAGOLD_REGISTRY_DB')) {
            return false;
        }
        return auragold_ensure_branches_ip_subdomain_columns($link, (string) AURAGOLD_REGISTRY_DB);
    }
}

if (!function_exists('auragold_ensure_branches_ip_subdomain_for_mirror_dbs')) {
    /**
     * Keep registry and target branch schema aligned before INSERT … SELECT * for tbl_branches.
     */
    function auragold_ensure_branches_ip_subdomain_for_mirror_dbs(
        mysqli $link,
        string $sourceDb,
        string $targetDb
    ): void {
        $sourceDb = trim($sourceDb);
        $targetDb = trim($targetDb);
        if (preg_match('/^[a-zA-Z0-9_]+$/', $sourceDb)) {
            auragold_ensure_branches_ip_subdomain_columns($link, $sourceDb);
        }
        if (preg_match('/^[a-zA-Z0-9_]+$/', $targetDb) && strcasecmp($sourceDb, $targetDb) !== 0) {
            auragold_ensure_branches_ip_subdomain_columns($link, $targetDb);
        }
    }
}

if (!function_exists('auragold_normalize_branch_host_for_storage')) {
    /**
     * Normalize a single "IP or host" field (IP, FQDN, or https:// URL) for tbl_branches.
     * Malformed URLs with no host (e.g. http:///, http://) return empty, not "/".
     */
    function auragold_normalize_branch_host_for_storage(string $raw): string {
        $s = trim($raw);
        if ($s === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $s)) {
            $host = parse_url($s, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $s = $host;
            } else {
                return '';
            }
        } else {
            $s = rtrim($s, '/');
            if (strpos($s, '/') !== false) {
                $s = (string) preg_replace('#/.*$#', '', $s);
            }
        }
        $s = trim($s);
        if ($s === '' || $s === '/' || $s === '//') {
            return '';
        }
        return $s;
    }
}

if (!function_exists('auragold_branch_host_label_plausible')) {
    /**
     * Hostname or IP only (no http:// in string).
     */
    function auragold_branch_host_label_plausible(string $n): bool {
        if ($n === '' || strlen($n) > 255) {
            return false;
        }
        if (filter_var($n, FILTER_VALIDATE_IP) !== false) {
            return true;
        }
        if (preg_match(
            '/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/i',
            $n
        )) {
            return true;
        }
        if (preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/i', $n)) {
            return true;
        }
        return strcasecmp($n, 'localhost') === 0;
    }
}

if (!function_exists('auragold_branch_ip_and_subdomain_for_storage')) {
    /**
     * @return array{ip_address:string,subdomain_url:string,rejected?:string} http(s):// is preserved in DB; optional rejected = validation message.
     */
    function auragold_branch_ip_and_subdomain_for_storage(string $raw): array {
        $in = trim($raw);
        if ($in === '') {
            return ['ip_address' => '', 'subdomain_url' => ''];
        }
        if (preg_match('#^https?://#i', $in)) {
            $p = @parse_url($in);
            if (!is_array($p)) {
                return [
                    'ip_address'   => '',
                    'subdomain_url' => '',
                    'rejected'     => 'Enter a valid IP address or hostname. URLs with no host (e.g. http:///) are not valid.',
                ];
            }
            $sch = isset($p['scheme']) ? strtolower((string) $p['scheme']) : '';
            if ($sch !== 'http' && $sch !== 'https') {
                return [
                    'ip_address'   => '',
                    'subdomain_url' => '',
                    'rejected'     => 'Only http:// and https:// URLs are allowed.',
                ];
            }
            $h = isset($p['host']) ? trim((string) $p['host']) : '';
            if ($h === '') {
                return [
                    'ip_address'   => '',
                    'subdomain_url' => '',
                    'rejected'     => 'Enter a valid IP address or hostname. URLs with no host (e.g. http:///) are not valid.',
                ];
            }
            if (!auragold_branch_host_label_plausible($h)) {
                return [
                    'ip_address'   => '',
                    'subdomain_url' => '',
                    'rejected'     => 'Enter a valid IP address or hostname (e.g. https://pune.goldmatrixsoftware.com or 192.0.2.1).',
                ];
            }
            $stored = $sch . '://' . $h;
            if (isset($p['port']) && (int) $p['port'] > 0) {
                $stored .= ':' . (int) $p['port'];
            }
            if (strlen($stored) > 255) {
                return [
                    'ip_address'   => '',
                    'subdomain_url' => '',
                    'rejected'     => 'Address is too long (max 255 characters).',
                ];
            }
            $forIp = strlen($stored) > 45 ? substr($stored, 0, 45) : $stored;
            return [
                'ip_address'   => $forIp,
                'subdomain_url' => $stored,
            ];
        }
        $n = auragold_normalize_branch_host_for_storage($in);
        if (trim($in) !== '' && $n === '') {
            return [
                'ip_address'   => '',
                'subdomain_url' => '',
                'rejected'     => 'Enter a valid IP address or hostname. URLs with no host (e.g. http:///) are not valid.',
            ];
        }
        if ($n === '') {
            return ['ip_address' => '', 'subdomain_url' => ''];
        }
        if (!auragold_branch_host_label_plausible($n)) {
            return [
                'ip_address'   => '',
                'subdomain_url' => '',
                'rejected'     => 'Enter a valid IP address or hostname (e.g. 192.0.2.1 or rkjewellers.goldmatrixsoftware.com).',
            ];
        }
        if (strlen($n) > 255) {
            $n = substr($n, 0, 255);
        }
        $forIp = strlen($n) > 45 ? substr($n, 0, 45) : $n;
        return [
            'ip_address'   => $forIp,
            'subdomain_url' => $n,
        ];
    }
}
