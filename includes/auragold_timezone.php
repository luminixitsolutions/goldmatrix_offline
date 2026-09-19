<?php
/**
 * Branch / country timezone helpers.
 * Server may be India; transaction date/time must follow the logged-in branch timezone.
 * Do not use the client device clock.
 */
if (!defined('AURAGOLD_DEFAULT_TIMEZONE')) {
    define('AURAGOLD_DEFAULT_TIMEZONE', 'Asia/Kolkata');
}

if (!function_exists('auragold_timezone_options')) {
    /**
     * Curated IANA timezones for Company / Branch settings UI.
     *
     * @return array<string, string> timezone => label
     */
    function auragold_timezone_options(): array
    {
        return [
            'Asia/Kolkata'      => 'India (Asia/Kolkata)',
            'Asia/Dubai'        => 'UAE / Dubai (Asia/Dubai)',
            'Asia/Singapore'    => 'Singapore (Asia/Singapore)',
            'Asia/Hong_Kong'    => 'Hong Kong (Asia/Hong_Kong)',
            'Asia/Bangkok'      => 'Thailand (Asia/Bangkok)',
            'Asia/Jakarta'      => 'Indonesia — Jakarta (Asia/Jakarta)',
            'Asia/Riyadh'       => 'Saudi Arabia (Asia/Riyadh)',
            'Asia/Qatar'        => 'Qatar (Asia/Qatar)',
            'Asia/Kuwait'       => 'Kuwait (Asia/Kuwait)',
            'Asia/Bahrain'      => 'Bahrain (Asia/Bahrain)',
            'Asia/Muscat'       => 'Oman (Asia/Muscat)',
            'Asia/Karachi'      => 'Pakistan (Asia/Karachi)',
            'Asia/Colombo'      => 'Sri Lanka (Asia/Colombo)',
            'Asia/Kathmandu'    => 'Nepal (Asia/Kathmandu)',
            'Asia/Dhaka'        => 'Bangladesh (Asia/Dhaka)',
            'Europe/London'     => 'UK (Europe/London)',
            'Europe/Paris'      => 'France / CET (Europe/Paris)',
            'Europe/Berlin'     => 'Germany (Europe/Berlin)',
            'Africa/Nairobi'    => 'Kenya (Africa/Nairobi)',
            'America/New_York'  => 'USA — New York (America/New_York)',
            'America/Chicago'   => 'USA — Chicago (America/Chicago)',
            'America/Denver'    => 'USA — Denver (America/Denver)',
            'America/Los_Angeles' => 'USA — Los Angeles (America/Los_Angeles)',
            'America/Toronto'   => 'Canada — Toronto (America/Toronto)',
            'Australia/Sydney'  => 'Australia — Sydney (Australia/Sydney)',
            'UTC'               => 'UTC',
        ];
    }
}

if (!function_exists('auragold_timezone_normalize')) {
    /**
     * Validate / normalize an IANA timezone id. Invalid → Asia/Kolkata.
     */
    function auragold_timezone_normalize(?string $tz): string
    {
        $tz = trim((string) $tz);
        if ($tz === '') {
            return AURAGOLD_DEFAULT_TIMEZONE;
        }
        try {
            new DateTimeZone($tz);
            return $tz;
        } catch (Exception $e) {
            return AURAGOLD_DEFAULT_TIMEZONE;
        }
    }
}

if (!function_exists('auragold_timezone_guess_from_phone_code')) {
    /** Best-effort default when timezone column is empty (existing branches). */
    function auragold_timezone_guess_from_phone_code(?string $phoneCode): string
    {
        $c = preg_replace('/\D+/', '', (string) $phoneCode);
        $map = [
            '91'  => 'Asia/Kolkata',
            '971' => 'Asia/Dubai',
            '65'  => 'Asia/Singapore',
            '44'  => 'Europe/London',
            '1'   => 'America/New_York',
            '966' => 'Asia/Riyadh',
            '974' => 'Asia/Qatar',
            '965' => 'Asia/Kuwait',
            '973' => 'Asia/Bahrain',
            '968' => 'Asia/Muscat',
            '92'  => 'Asia/Karachi',
            '94'  => 'Asia/Colombo',
            '977' => 'Asia/Kathmandu',
            '880' => 'Asia/Dhaka',
            '852' => 'Asia/Hong_Kong',
            '66'  => 'Asia/Bangkok',
            '62'  => 'Asia/Jakarta',
            '61'  => 'Australia/Sydney',
            '254' => 'Africa/Nairobi',
        ];

        return $map[$c] ?? AURAGOLD_DEFAULT_TIMEZONE;
    }
}

if (!function_exists('auragold_timezone_for_branch_id')) {
    /**
     * Resolve timezone for a registry tbl_branches.id.
     */
    function auragold_timezone_for_branch_id(int $branchId): string
    {
        if ($branchId <= 0 || !function_exists('getRecordMaster')) {
            return AURAGOLD_DEFAULT_TIMEZONE;
        }
        static $cache = [];
        if (isset($cache[$branchId])) {
            return $cache[$branchId];
        }
        $hasTz = true;
        if (function_exists('auragold_branch_table_has_column')) {
            // optional — column may be checked elsewhere
        }
        $row = @getRecordMaster(
            'SELECT timezone, profile_phone_country_code FROM tbl_branches WHERE id = '
            . (int) $branchId . ' LIMIT 1'
        );
        if (!is_array($row)) {
            $cache[$branchId] = AURAGOLD_DEFAULT_TIMEZONE;
            return $cache[$branchId];
        }
        $tz = trim((string) ($row['timezone'] ?? ''));
        if ($tz === '') {
            $tz = auragold_timezone_guess_from_phone_code($row['profile_phone_country_code'] ?? '');
        }
        $cache[$branchId] = auragold_timezone_normalize($tz);
        return $cache[$branchId];
    }
}

if (!function_exists('auragold_branch_timezone')) {
    /**
     * Current request / session branch timezone (login working branch).
     */
    function auragold_branch_timezone(): string
    {
        $bid = 0;
        if (function_exists('auragold_effective_branch_id')) {
            $bid = (int) auragold_effective_branch_id();
        }
        if ($bid <= 0 && session_status() === PHP_SESSION_ACTIVE) {
            $bid = (int) ($_SESSION['working_branch_id'] ?? $_SESSION['branch_id'] ?? $_SESSION['auragold_login_branch_id'] ?? 0);
        }
        if ($bid > 0) {
            $tz = auragold_timezone_for_branch_id($bid);
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['auragold_timezone'] = $tz;
            }
            return $tz;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $sess = trim((string) ($_SESSION['auragold_timezone'] ?? ''));
            if ($sess !== '') {
                return auragold_timezone_normalize($sess);
            }
        }

        return AURAGOLD_DEFAULT_TIMEZONE;
    }
}

if (!function_exists('auragold_datetime_in_tz')) {
    /**
     * @return DateTimeImmutable
     */
    function auragold_datetime_in_tz(?string $tz = null): DateTimeImmutable
    {
        $tz = auragold_timezone_normalize($tz !== null ? $tz : auragold_branch_timezone());
        return new DateTimeImmutable('now', new DateTimeZone($tz));
    }
}

if (!function_exists('auragold_now')) {
    /** Current local date-time for the branch timezone (server clock + TZ, not client clock). */
    function auragold_now(string $format = 'Y-m-d H:i:s', ?string $tz = null): string
    {
        return auragold_datetime_in_tz($tz)->format($format);
    }
}

if (!function_exists('auragold_today')) {
    /** Current local date (Y-m-d) for the branch timezone. */
    function auragold_today(?string $tz = null): string
    {
        return auragold_now('Y-m-d', $tz);
    }
}

if (!function_exists('auragold_now_sql')) {
    /** Quoted datetime literal for SQL inserts (prefer over NOW() when TZ may differ). */
    function auragold_now_sql(?string $tz = null): string
    {
        return "'" . auragold_now('Y-m-d H:i:s', $tz) . "'";
    }
}

if (!function_exists('auragold_today_sql')) {
    /** Quoted date literal for SQL inserts (prefer over CURDATE()). */
    function auragold_today_sql(?string $tz = null): string
    {
        return "'" . auragold_today($tz) . "'";
    }
}

if (!function_exists('auragold_mysql_timezone_offset')) {
    /** MySQL SET time_zone offset for current instant in branch TZ (handles DST). */
    function auragold_mysql_timezone_offset(?string $tz = null): string
    {
        return auragold_datetime_in_tz($tz)->format('P'); // e.g. +05:30, +04:00
    }
}

if (!function_exists('auragold_apply_mysql_session_timezone')) {
    /**
     * Align MySQL NOW()/CURDATE() with branch local time for this connection.
     */
    function auragold_apply_mysql_session_timezone($mysqli, ?string $tz = null): void
    {
        if (!($mysqli instanceof mysqli)) {
            return;
        }
        $offset = auragold_mysql_timezone_offset($tz);
        if (!preg_match('/^[+-]\d{2}:\d{2}$/', $offset)) {
            return;
        }
        @mysqli_query($mysqli, "SET time_zone = '" . $offset . "'");
    }
}

if (!function_exists('auragold_bootstrap_branch_timezone')) {
    /**
     * Call once per request after session + DB connections are ready.
     * Sets PHP default timezone, session cache, and MySQL session time_zone.
     */
    function auragold_bootstrap_branch_timezone($conn = null, $conn_master = null): string
    {
        static $done = false;
        if ($done) {
            return auragold_branch_timezone();
        }
        $done = true;

        $tz = AURAGOLD_DEFAULT_TIMEZONE;
        if (session_status() === PHP_SESSION_ACTIVE
            && (!empty($_SESSION['Admin']) || !empty($_SESSION['user_id']) || !empty($_SESSION['branch_id']) || !empty($_SESSION['working_branch_id']))
        ) {
            $bid = 0;
            if (function_exists('auragold_effective_branch_id')) {
                $bid = (int) auragold_effective_branch_id();
            }
            if ($bid <= 0) {
                $bid = (int) ($_SESSION['working_branch_id'] ?? $_SESSION['branch_id'] ?? $_SESSION['auragold_login_branch_id'] ?? 0);
            }
            if ($bid > 0) {
                $tz = auragold_timezone_for_branch_id($bid);
            } else {
                $tz = auragold_timezone_normalize((string) ($_SESSION['auragold_timezone'] ?? ''));
            }
            $_SESSION['auragold_timezone'] = $tz;
        }

        $tz = auragold_timezone_normalize($tz);
        @date_default_timezone_set($tz);

        if ($conn instanceof mysqli) {
            auragold_apply_mysql_session_timezone($conn, $tz);
        }
        // Operational writes usually use $conn; still align master if it is the same operational link.
        if ($conn_master instanceof mysqli && $conn_master !== $conn) {
            // Keep registry connection on India default for meta timestamps unless it IS the working DB.
            // When working_db equals registry, $conn === $conn_master often — already handled above.
        }

        return $tz;
    }
}

if (!function_exists('auragold_timezone_js_globals')) {
    /**
     * Script snippet: expose branch today/now to front-end (do not use device clock).
     */
    function auragold_timezone_js_globals(): string
    {
        $tz = auragold_branch_timezone();
        $payload = [
            'timezone' => $tz,
            'today'    => auragold_today($tz),
            'now'      => auragold_now('Y-m-d H:i:s', $tz),
            'date_dmY' => auragold_now('d-m-Y', $tz),
            'time_hi'  => auragold_now('H:i', $tz),
            'time_ampm'=> auragold_now('h:i A', $tz),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if ($json === false) {
            $json = '{}';
        }
        return '<script>window.AURAGOLD_TZ=' . $json . ';</script>';
    }
}
