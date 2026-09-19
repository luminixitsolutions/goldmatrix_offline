<?php
/**
 * Location tables (states/cities) + customer address city columns.
 */
function auragold_ensure_customer_city_columns($conn)
{
    static $done = false;
    if ($done) {
        return;
    }
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customers LIKE 'billing_city'");
    if ($r && mysqli_num_rows($r) === 0) {
        @mysqli_query($conn, "ALTER TABLE tbl_customers ADD COLUMN billing_city VARCHAR(100) NULL AFTER billing_state");
    }
    $r2 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customers LIKE 'shipping_city'");
    if ($r2 && mysqli_num_rows($r2) === 0) {
        @mysqli_query($conn, "ALTER TABLE tbl_customers ADD COLUMN shipping_city VARCHAR(100) NULL AFTER shipping_state");
    }
    $done = true;
}

/**
 * Ledger header: phone dial code + state/city (cascading with country_id).
 */
function auragold_ensure_customer_ledger_location_columns($conn)
{
    static $done = false;
    if ($done || !$conn) {
        return;
    }
    auragold_ensure_customer_city_columns($conn);

    $add = function ($col, $def) use ($conn) {
        $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customers LIKE '" . mysqli_real_escape_string($conn, $col) . "'");
        if ($c && mysqli_num_rows($c) === 0) {
            @mysqli_query($conn, 'ALTER TABLE tbl_customers ADD COLUMN `' . $col . '` ' . $def);
        }
    };

    $add('phone_country_code', "VARCHAR(10) NULL DEFAULT '971'");
    $add('ledger_state_id', 'INT NOT NULL DEFAULT 0');
    $add('ledger_city_id', 'INT NOT NULL DEFAULT 0');
    $add('gstin', 'VARCHAR(20) NULL DEFAULT NULL');

    $done = true;
}

function auragold_ensure_location_tables($conn)
{
    static $done = false;
    if ($done) {
        return;
    }
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_states (
        id INT NOT NULL AUTO_INCREMENT,
        country_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY idx_state_country (country_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_cities (
        id INT NOT NULL AUTO_INCREMENT,
        state_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        status TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY idx_city_state (state_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $r = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_cities LIKE 'comment'");
    if ($r && mysqli_num_rows($r) === 0) {
        @mysqli_query($conn, "ALTER TABLE tbl_cities ADD COLUMN comment TEXT NULL AFTER name");
    }

    $r = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_countries LIKE 'comment'");
    if ($r && mysqli_num_rows($r) === 0) {
        @mysqli_query($conn, "ALTER TABLE tbl_countries ADD COLUMN comment TEXT NULL AFTER code3");
    }

    $r = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_states LIKE 'comment'");
    if ($r && mysqli_num_rows($r) === 0) {
        @mysqli_query($conn, "ALTER TABLE tbl_states ADD COLUMN comment TEXT NULL AFTER name");
    }

    $done = true;
}

function auragold_seed_location_data($conn)
{
    $check = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_states");
    $row = $check ? mysqli_fetch_assoc($check) : null;
    if ($row && (int) $row['c'] > 0) {
        return;
    }

    $uae = getRecord("SELECT id FROM tbl_countries WHERE name = 'United Arab Emirates' LIMIT 1");
    if ($uae && !empty($uae['id'])) {
        $uaeId = (int) $uae['id'];
        $uae_emirates = [
            'Abu Dhabi Emirate' => ['Abu Dhabi', 'Al Ain', 'Madinat Zayed'],
            'Dubai Emirate' => ['Dubai'],
            'Sharjah Emirate' => ['Sharjah', 'Khor Fakkan'],
            'Ajman Emirate' => ['Ajman'],
            'Umm Al Quwain Emirate' => ['Umm Al Quwain'],
            'Ras Al Khaimah Emirate' => ['Ras Al Khaimah'],
            'Fujairah Emirate' => ['Fujairah'],
        ];
        foreach ($uae_emirates as $stateName => $cities) {
            $sn = mysqli_real_escape_string($conn, $stateName);
            mysqli_query($conn, "INSERT INTO tbl_states (country_id, name) VALUES ($uaeId, '$sn')");
            $sid = mysqli_insert_id($conn);
            foreach ($cities as $cn) {
                $cn = mysqli_real_escape_string($conn, $cn);
                mysqli_query($conn, "INSERT INTO tbl_cities (state_id, name) VALUES ($sid, '$cn')");
            }
        }
    }

    $in = getRecord("SELECT id FROM tbl_countries WHERE name = 'India' LIMIT 1");
    if ($in && !empty($in['id'])) {
        $inId = (int) $in['id'];
        $in_states = [
            'Maharashtra' => ['Mumbai', 'Pune', 'Nagpur'],
            'Gujarat' => ['Ahmedabad', 'Surat', 'Vadodara'],
            'Kerala' => ['Kochi', 'Thiruvananthapuram', 'Kozhikode'],
            'Karnataka' => ['Bengaluru', 'Mysuru', 'Mangaluru'],
            'Tamil Nadu' => ['Chennai', 'Coimbatore', 'Madurai'],
            'Delhi' => ['New Delhi', 'North Delhi'],
            'West Bengal' => ['Kolkata', 'Howrah'],
            'Rajasthan' => ['Jaipur', 'Jodhpur', 'Udaipur'],
            'Uttar Pradesh' => ['Lucknow', 'Kanpur', 'Noida'],
            'Telangana' => ['Hyderabad', 'Warangal'],
            'Andhra Pradesh' => ['Visakhapatnam', 'Vijayawada'],
            'Punjab' => ['Ludhiana', 'Amritsar'],
            'Haryana' => ['Gurugram', 'Faridabad'],
            'Madhya Pradesh' => ['Indore', 'Bhopal'],
            'Bihar' => ['Patna', 'Gaya'],
            'Odisha' => ['Bhubaneswar', 'Cuttack'],
            'Assam' => ['Guwahati', 'Silchar'],
        ];
        foreach ($in_states as $stateName => $cities) {
            $sn = mysqli_real_escape_string($conn, $stateName);
            mysqli_query($conn, "INSERT INTO tbl_states (country_id, name) VALUES ($inId, '$sn')");
            $sid = mysqli_insert_id($conn);
            foreach ($cities as $cn) {
                $cn = mysqli_real_escape_string($conn, $cn);
                mysqli_query($conn, "INSERT INTO tbl_cities (state_id, name) VALUES ($sid, '$cn')");
            }
        }
    }

    $q = mysqli_query($conn, "SELECT c.id FROM tbl_countries c WHERE NOT EXISTS (SELECT 1 FROM tbl_states s WHERE s.country_id = c.id)");
    while ($q && ($r = mysqli_fetch_assoc($q))) {
        $cid = (int) $r['id'];
        mysqli_query($conn, "INSERT INTO tbl_states (country_id, name) VALUES ($cid, 'Other')");
        $sid = mysqli_insert_id($conn);
        mysqli_query($conn, "INSERT INTO tbl_cities (state_id, name) VALUES ($sid, 'Other')");
    }

    mysqli_query($conn, "INSERT INTO tbl_cities (state_id, name) SELECT s.id, 'Other' FROM tbl_states s WHERE NOT EXISTS (SELECT 1 FROM tbl_cities c WHERE c.state_id = s.id)");
}

function auragold_bootstrap_location_data($conn)
{
    auragold_ensure_location_tables($conn);
    auragold_ensure_customer_city_columns($conn);
    auragold_seed_location_data($conn);
}
