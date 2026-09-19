<?php
/**
 * Copy to: eway_whitebooks_credentials.local.php (same folder). The .local.php file is gitignored.
 *
 * Header-based API (no separate auth): email, client_id, client_secret, gstin header = branch GSTIN.
 * Optional: WHITEBOOKS_IP_ADDRESS (defaults to $_SERVER['SERVER_ADDR']).
 */
if (!defined('WHITEBOOKS_EMAIL')) {
    define('WHITEBOOKS_EMAIL', 'YOUR_PORTAL_LOGIN_EMAIL@EXAMPLE.COM');
}
if (!defined('WHITEBOOKS_CLIENT_ID')) {
    define('WHITEBOOKS_CLIENT_ID', 'YOUR_CLIENT_ID');
}
if (!defined('WHITEBOOKS_CLIENT_SECRET')) {
    define('WHITEBOOKS_CLIENT_SECRET', 'YOUR_CLIENT_SECRET');
}
// Optional fallback when tbl_branches.gst_no is empty (15 characters)
// if (!defined('WHITEBOOKS_AUTH_GSTIN')) {
//     define('WHITEBOOKS_AUTH_GSTIN', '29XXXXXXXXXXXXX');
// }
