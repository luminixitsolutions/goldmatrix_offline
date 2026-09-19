<?php
/**
 * One-time fix: so that Gold, Silver, and all tabs save their own column preferences
 * in tbl_user_column_preferences (instead of only the last tab overwriting).
 *
 * Run once from browser: http://localhost/auragold/admin/sql/fix_tab_wise_column_preferences.php
 * Or from CLI: php fix_tab_wise_column_preferences.php
 */
session_start();
$docroot = dirname(__DIR__);
if (!is_file($docroot . '/config.php')) {
    die('config.php not found');
}
require_once $docroot . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

$table = 'tbl_user_column_preferences';

// 1) Ensure tab_key column exists
$res = @mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE 'tab_key'");
if (!$res || mysqli_num_rows($res) === 0) {
    if (!mysqli_query($conn, "ALTER TABLE $table ADD COLUMN tab_key VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Tab: 1=Gold, 2=Silver, 3=Platinum (metal_id)'")) {
        echo "Error adding tab_key: " . mysqli_error($conn) . "\n";
        exit(1);
    }
    echo "Added column tab_key.\n";
} else {
    echo "Column tab_key already exists.\n";
}

// 2) Find unique index that does NOT include tab_key (so only one row per column = only one tab saved)
$res = mysqli_query($conn, "SHOW INDEX FROM $table WHERE Non_unique = 0");
if (!$res) {
    echo "Error: " . mysqli_error($conn) . "\n";
    exit(1);
}
$uniqueIndexes = [];
while ($row = mysqli_fetch_assoc($res)) {
    $name = $row['Key_name'];
    if ($row['Key_name'] === 'PRIMARY') continue;
    if (!isset($uniqueIndexes[$name])) $uniqueIndexes[$name] = [];
    $uniqueIndexes[$name][] = $row['Column_name'];
}

$oldIndexToDrop = null;
foreach ($uniqueIndexes as $indexName => $columns) {
    if (in_array('tab_key', $columns, true)) continue; // new key we want to add
    if (in_array('user_id', $columns, true) && in_array('page_name', $columns, true) && in_array('column_key', $columns, true)) {
        $oldIndexToDrop = $indexName;
        break;
    }
}

if ($oldIndexToDrop) {
    if (!mysqli_query($conn, "ALTER TABLE $table DROP INDEX `" . mysqli_real_escape_string($conn, $oldIndexToDrop) . "`")) {
        echo "Error dropping old index '$oldIndexToDrop': " . mysqli_error($conn) . "\n";
        exit(1);
    }
    echo "Dropped old unique index: $oldIndexToDrop (so each tab can have its own rows).\n";
} else {
    echo "No old unique index found on (user_id, page_name, column_key) without tab_key.\n";
}

// 3) Add new unique key (user_id, page_name, tab_key, column_key) if not exists
$res = mysqli_query($conn, "SHOW INDEX FROM $table WHERE Key_name = 'unique_user_page_tab_column'");
if (!$res || mysqli_num_rows($res) === 0) {
    if (!mysqli_query($conn, "ALTER TABLE $table ADD UNIQUE KEY `unique_user_page_tab_column` (user_id, page_name, tab_key, column_key)")) {
        echo "Error adding new unique key: " . mysqli_error($conn) . "\n";
        exit(1);
    }
    echo "Added unique key unique_user_page_tab_column (user_id, page_name, tab_key, column_key).\n";
} else {
    echo "Unique key unique_user_page_tab_column already exists.\n";
}

echo "\nDone. Gold, Silver, and all tabs will now save their own column check/uncheck.\n";
