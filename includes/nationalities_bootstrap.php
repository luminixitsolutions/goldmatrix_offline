<?php
/**
 * Seed tbl_nationalities when empty (matches docs/db auragold dumps) so ledger/customer modal
 * Share Holders nationality dropdown receives rows from getList(...).
 */
function auragold_ensure_tbl_nationalities_seeded(mysqli $conn): void
{
    static $done = [];
    $key = spl_object_hash($conn);
    if (!empty($done[$key])) {
        return;
    }

    $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_nationalities'");
    if (!$t || mysqli_num_rows($t) === 0) {
        if ($t) {
            mysqli_free_result($t);
        }
        $done[$key] = true;
        return;
    }
    mysqli_free_result($t);

    $cntRes = @mysqli_query($conn, 'SELECT COUNT(*) AS c FROM tbl_nationalities LIMIT 1');
    if (!$cntRes) {
        $done[$key] = true;
        return;
    }
    $row = mysqli_fetch_assoc($cntRes);
    mysqli_free_result($cntRes);
    $total = $row !== null ? (int) ($row['c'] ?? 0) : 0;

    $actRes = @mysqli_query(
        $conn,
        'SELECT COUNT(*) AS c FROM tbl_nationalities WHERE COALESCE(`status`, 1) = 1 LIMIT 1'
    );
    $active = 0;
    if ($actRes) {
        $ar = mysqli_fetch_assoc($actRes);
        mysqli_free_result($actRes);
        $active = $ar !== null ? (int) ($ar['c'] ?? 0) : 0;
    }

    if ($total > 0 && $active === 0) {
        @mysqli_query($conn, 'UPDATE tbl_nationalities SET `status` = 1');
        $done[$key] = true;
        return;
    }

    if ($total > 0) {
        $done[$key] = true;
        return;
    }

    $sql = <<<SQL
INSERT IGNORE INTO `tbl_nationalities` (`id`, `name`, `code`, `status`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Afghan', 'AF', 1, 1, NOW(), NULL),
(2, 'Albanian', 'AL', 1, 2, NOW(), NULL),
(3, 'Algerian', 'DZ', 1, 3, NOW(), NULL),
(4, 'American', 'US', 1, 4, NOW(), NULL),
(5, 'Argentine', 'AR', 1, 5, NOW(), NULL),
(6, 'Australian', 'AU', 1, 6, NOW(), NULL),
(7, 'Austrian', 'AT', 1, 7, NOW(), NULL),
(8, 'Bangladeshi', 'BD', 1, 8, NOW(), NULL),
(9, 'Belgian', 'BE', 1, 9, NOW(), NULL),
(10, 'Brazilian', 'BR', 1, 10, NOW(), NULL),
(11, 'British', 'GB', 1, 11, NOW(), NULL),
(12, 'Canadian', 'CA', 1, 12, NOW(), NULL),
(13, 'Chinese', 'CN', 1, 13, NOW(), NULL),
(14, 'Egyptian', 'EG', 1, 14, NOW(), NULL),
(15, 'Emirati', 'AE', 1, 15, NOW(), NULL),
(16, 'Filipino', 'PH', 1, 16, NOW(), NULL),
(17, 'French', 'FR', 1, 17, NOW(), NULL),
(18, 'German', 'DE', 1, 18, NOW(), NULL),
(19, 'Indian', 'IN', 1, 19, NOW(), NULL),
(20, 'Indonesian', 'ID', 1, 20, NOW(), NULL),
(21, 'Iranian', 'IR', 1, 21, NOW(), NULL),
(22, 'Iraqi', 'IQ', 1, 22, NOW(), NULL),
(23, 'Irish', 'IE', 1, 23, NOW(), NULL),
(24, 'Italian', 'IT', 1, 24, NOW(), NULL),
(25, 'Japanese', 'JP', 1, 25, NOW(), NULL),
(26, 'Jordanian', 'JO', 1, 26, NOW(), NULL),
(27, 'Kenyan', 'KE', 1, 27, NOW(), NULL),
(28, 'Kuwaiti', 'KW', 1, 28, NOW(), NULL),
(29, 'Lebanese', 'LB', 1, 29, NOW(), NULL),
(30, 'Malaysian', 'MY', 1, 30, NOW(), NULL),
(31, 'Mexican', 'MX', 1, 31, NOW(), NULL),
(32, 'Moroccan', 'MA', 1, 32, NOW(), NULL),
(33, 'Nepalese', 'NP', 1, 33, NOW(), NULL),
(34, 'Nigerian', 'NG', 1, 34, NOW(), NULL),
(35, 'Omani', 'OM', 1, 35, NOW(), NULL),
(36, 'Pakistani', 'PK', 1, 36, NOW(), NULL),
(37, 'Palestinian', 'PS', 1, 37, NOW(), NULL),
(38, 'Qatari', 'QA', 1, 38, NOW(), NULL),
(39, 'Russian', 'RU', 1, 39, NOW(), NULL),
(40, 'Saudi Arabian', 'SA', 1, 40, NOW(), NULL),
(41, 'Singaporean', 'SG', 1, 41, NOW(), NULL),
(42, 'South African', 'ZA', 1, 42, NOW(), NULL),
(43, 'South Korean', 'KR', 1, 43, NOW(), NULL),
(44, 'Spanish', 'ES', 1, 44, NOW(), NULL),
(45, 'Sri Lankan', 'LK', 1, 45, NOW(), NULL),
(46, 'Sudanese', 'SD', 1, 46, NOW(), NULL),
(47, 'Swiss', 'CH', 1, 47, NOW(), NULL),
(48, 'Syrian', 'SY', 1, 48, NOW(), NULL),
(49, 'Thai', 'TH', 1, 49, NOW(), NULL),
(50, 'Tunisian', 'TN', 1, 50, NOW(), NULL),
(51, 'Turkish', 'TR', 1, 51, NOW(), NULL),
(52, 'Ukrainian', 'UA', 1, 52, NOW(), NULL),
(53, 'Yemeni', 'YE', 1, 53, NOW(), NULL)
SQL;
    @mysqli_query($conn, $sql);
    @mysqli_query($conn, 'ALTER TABLE tbl_nationalities AUTO_INCREMENT = 54');

    $done[$key] = true;
}
