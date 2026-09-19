<?php
/**
 * Ensure tbl_customers.nominee_data (JSON array of nominee rows).
 */
function auragold_ensure_customer_nominee_column($conn): void
{
    static $done = [];
    if (!$conn instanceof mysqli) {
        return;
    }
    $key = spl_object_hash($conn);
    if (!empty($done[$key])) {
        return;
    }
    $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customers LIKE 'nominee_data'");
    if ($c && mysqli_num_rows($c) === 0) {
        @mysqli_query(
            $conn,
            "ALTER TABLE tbl_customers ADD COLUMN `nominee_data` TEXT NULL DEFAULT NULL AFTER `share_holder_documents`"
        );
    }
    if ($c) {
        mysqli_free_result($c);
    }
    $done[$key] = true;
}

/**
 * Standard relationship options for ledger nominees.
 *
 * @return list<string>
 */
function auragold_nominee_relationship_options(): array
{
    return [
        'Spouse',
        'Son',
        'Daughter',
        'Father',
        'Mother',
        'Brother',
        'Sister',
        'Friend',
        'Other',
    ];
}
