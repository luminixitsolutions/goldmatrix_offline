<?php
/**
 * Share holder / ledger document type master — table created via PHP (no separate SQL file).
 */
function auragold_ensure_tbl_document_types($conn): void
{
    static $done = [];
    if (!$conn instanceof mysqli) {
        return;
    }
    $key = spl_object_hash($conn);
    if (!empty($done[$key])) {
        return;
    }

    $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_document_types'");
    if (!$t || mysqli_num_rows($t) === 0) {
        if ($t) {
            mysqli_free_result($t);
        }
        @mysqli_query(
            $conn,
            "CREATE TABLE IF NOT EXISTS `tbl_document_types` (
                `id` int NOT NULL AUTO_INCREMENT,
                `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
                `status` tinyint(1) NOT NULL DEFAULT 1,
                `created_by` int DEFAULT NULL,
                `modified_by` int DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } else {
        mysqli_free_result($t);
    }

    if (function_exists('auragold_ensure_table_branch_id_column')) {
        auragold_ensure_table_branch_id_column($conn, 'tbl_document_types');
    }

    // One-time copy from legacy singular table so Masters keeps existing names (e.g. PAN, Aadhar).
    $legacy = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_document_type'");
    if ($legacy && mysqli_num_rows($legacy) > 0) {
        $oldRows = @mysqli_query(
            $conn,
            "SELECT name, branch_id FROM tbl_document_type WHERE status=1 AND name IS NOT NULL AND TRIM(name) <> ''"
        );
        if ($oldRows) {
            while ($row = mysqli_fetch_assoc($oldRows)) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $nameEsc = mysqli_real_escape_string($conn, $name);
                $exists = @mysqli_query(
                    $conn,
                    "SELECT id FROM tbl_document_types WHERE status=1 AND LOWER(TRIM(name))=LOWER('$nameEsc') LIMIT 1"
                );
                if ($exists && mysqli_num_rows($exists) > 0) {
                    mysqli_free_result($exists);
                    continue;
                }
                if ($exists) {
                    mysqli_free_result($exists);
                }
                $bid = isset($row['branch_id']) && $row['branch_id'] !== '' && $row['branch_id'] !== null
                    ? (int) $row['branch_id']
                    : 0;
                @mysqli_query(
                    $conn,
                    "INSERT INTO tbl_document_types (name, branch_id, status) VALUES ('$nameEsc', '$bid', 1)"
                );
            }
            mysqli_free_result($oldRows);
        }
    }
    if ($legacy) {
        mysqli_free_result($legacy);
    }

    $done[$key] = true;
}
