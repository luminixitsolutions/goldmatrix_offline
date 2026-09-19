<?php
/**
 * WhiteBooks e-Way Bill API — defaults and static configuration.
 * Secrets may be overridden from Admin → Utilities → e-Way Bill API Settings (database).
 * Do not commit production secrets to a public repository.
 *
 * NIC API root (v1.03): EWAY_BASE_URL must end with .../ewayapi (no trailing slash).
 * Full generate POST: {EWAY_BASE_URL}/genewaybill?email=...
 * Do not set WHITEBOOKS_GENERATE_URL to /authenticate or non-genewaybill paths.
 */
if (!defined('EWAY_BASE_URL')) {
    define('EWAY_BASE_URL', 'https://apisandbox.whitebooks.in/ewaybillapi/v1.03/ewayapi');
}
if (!defined('EWAY_EMAIL')) {
    define('EWAY_EMAIL', 'goldmatrixsupport@gmail.com');
}
if (!defined('EWAY_USERNAME')) {
    define('EWAY_USERNAME', 'BVMGSP');
}
if (!defined('EWAY_PASSWORD')) {
    define('EWAY_PASSWORD', 'Wbooks@0142');
}
if (!defined('EWAY_IP_ADDRESS')) {
    /** Sent as HTTP header ip_address; WhiteBooks sandbox uses 0.0.0.0 per portal settings. */
    define('EWAY_IP_ADDRESS', '0.0.0.0');
}
if (!defined('EWAY_CLIENT_ID')) {
    define('EWAY_CLIENT_ID', '');
}
if (!defined('EWAY_CLIENT_SECRET')) {
    define('EWAY_CLIENT_SECRET', '');
}
if (!defined('EWAY_GSTIN')) {
    /** WhiteBooks sandbox sample GSTIN (apisandbox.whitebooks.in); production must use your registered GSTIN. */
    define('EWAY_GSTIN', '29AAGCB1286Q000');
}
