<?php
/**
 * Temporary test: call cPanel set_privileges_on_database for database goldmatrix_rk.
 * Run from project root (production .env / config must point at live cPanel):
 *   php admin/tools/cpanel_test_privilege_goldmatrix_rk.php
 * Then confirm in cPanel → MySQL® Databases that goldmatrix_rk lists DB_USER under Privileged Users.
 * Remove this file when finished debugging.
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Run from CLI only.\n";
    exit(1);
}

$adminDir = dirname(__DIR__);
require_once $adminDir . '/config.php';
require_once $adminDir . '/includes/cpanel_mysql_create_database.php';

$testDb = 'goldmatrix_rk';

echo "database={$testDb}\n";
echo 'DB_USER=' . (defined('DB_USER') ? (string) DB_USER : '(undefined)') . "\n";
echo 'AURAGOLD_PROJECT=' . (defined('AURAGOLD_PROJECT') ? AURAGOLD_PROJECT : '(undefined)') . "\n\n";

$r = auragold_cpanel_uapi_mysql_set_privileges_for_app_user($testDb);

print_r($r);
echo "\nExit code: " . (empty($r['ok']) ? 1 : 0) . "\n";

exit(empty($r['ok']) ? 1 : 0);
