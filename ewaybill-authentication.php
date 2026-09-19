<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auragold_require_login.php';
require_once __DIR__ . '/includes/ewaybill_api_helper.php';
auragold_require_login_or_exit();

require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();

$conn = isset($conn) && $conn instanceof mysqli ? $conn : (isset($conn_master) && $conn_master instanceof mysqli ? $conn_master : null);
if ($conn === null) {
    die('Database connection is not available.');
}

$authResult = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (($_POST['ewaybill_action'] ?? '') === 'authenticate')) {
    if (function_exists('auragold_csrf_validate_or_die')) {
        auragold_csrf_validate_or_die();
    }
    $authResult = ewaybill_authenticate();
}

ewaybill_load_file_config();
$merged = ewaybill_merged_config($conn);
$tkrow = ewaybill_fetch_token_row_for_config(
    $conn,
    (string) ($merged['gstin'] ?? ''),
    (string) ($merged['username'] ?? '')
);
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title><?php echo function_exists('auragold_t') ? htmlspecialchars((string) auragold_t('set_software.eway_bill_auth_page_title'), ENT_QUOTES, 'UTF-8') : 'e-Way Bill authentication - Set Software - ' . auragold_app_name(); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
</head>
<style>
html, body { height: 100%; overflow-x: hidden; }
.layout-content { height: calc(100vh - 60px); overflow: hidden; display: flex; flex-direction: column; }
.set-software-wrapper { flex: 1; min-height: 0; }
.set-software-main { overflow-y: auto; }
.auragold-eway-page { padding: 24px; }
.eway-cfg-dl dt { font-weight: 600; }
.eway-cfg-dl dd { margin-bottom: .5rem; }
.eway-table-wrap { overflow-x: auto; }
</style>
<body>
<?php include 'sidebar.php'; ?>
<div class="layout-content">
    <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
        <div class="set-software-wrapper">
            <?php include 'set-software-sidebar.php'; ?>
            <div class="set-software-main">
                <?php include __DIR__ . '/includes/set-software-ewaybill-tabs.php'; ?>
                <div class="auragold-eway-page">
    <h4 class="mb-3"><?php echo function_exists('auragold_t') ? htmlspecialchars((string) auragold_t('set_software.eway_bill_auth'), ENT_QUOTES, 'UTF-8') : 'e-Way Bill authentication'; ?></h4>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">Current effective configuration (secrets masked)</div>
                <div class="card-body">
                    <dl class="row eway-cfg-dl small mb-0">
                        <dt class="col-sm-4">Base URL</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars((string) ($merged['base_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt class="col-sm-4">E-mail</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars((string) ($merged['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt class="col-sm-4">Username</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars((string) ($merged['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt class="col-sm-4">Password</dt>
                        <dd class="col-sm-8"><?php echo ewaybill_mask_secret((string) ($merged['password'] ?? '')); ?></dd>
                        <dt class="col-sm-4">IP (header, effective)</dt>
                        <dd class="col-sm-8"><?php
                            $effIp = function_exists('ewaybill_effective_ip_address_header') ? ewaybill_effective_ip_address_header($merged) : (string) ($merged['ip_address'] ?? '');
                            echo htmlspecialchars($effIp !== '' ? $effIp : '(empty)', ENT_QUOTES, 'UTF-8');
                            ?></dd>
                        <dt class="col-sm-4">Client ID</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars((string) ($merged['client_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt class="col-sm-4">Client secret</dt>
                        <dd class="col-sm-8"><?php echo ewaybill_mask_secret((string) ($merged['client_secret'] ?? '')); ?></dd>
                        <dt class="col-sm-4">GSTIN</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars((string) ($merged['gstin'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
                    </dl>
                    <a class="btn btn-sm btn-outline-secondary" href="ewaybill-api-settings.php">Edit API settings</a>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">Authenticate with WhiteBooks</div>
                <div class="card-body">
                    <form method="post" action="ewaybill-authentication.php">
                        <input type="hidden" name="ewaybill_action" value="authenticate">
                        <?php if (function_exists('auragold_csrf_field')): ?><?php echo auragold_csrf_field(); ?><?php endif; ?>
                        <button type="submit" class="btn btn-primary">Authenticate now</button>
                    </form>
                    <p class="text-muted small mt-3 mb-0">Sends a GET request to the sandbox or production <code>authenticate</code> endpoint. Result and any API message are shown below.</p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($authResult !== null && is_array($authResult)): ?>
        <?php
        $arOk = !empty($authResult['ok']);
        ?>
        <div class="alert alert-<?php echo $arOk ? 'success' : 'danger'; ?> mb-4" role="alert">
            <strong><?php echo $arOk ? 'Result: success' : 'Result: error'; ?></strong>
            <?php if (isset($authResult['http_code'])): ?>
                <span class="ml-1">(HTTP <?php echo (int) $authResult['http_code']; ?>)</span>
            <?php endif; ?>
            <div class="mt-2"><?php echo nl2br(htmlspecialchars((string) ($authResult['message'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header">Saved token (<?php echo htmlspecialchars((string) ($merged['gstin'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> / <?php echo htmlspecialchars((string) ($merged['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)</div>
        <div class="card-body p-0">
            <?php if (is_array($tkrow) && !empty($tkrow)): ?>
                <div class="table-responsive eway-table-wrap">
                    <table class="table table-sm table-striped mb-0">
                        <tbody>
                        <?php foreach (['id', 'gstin', 'email', 'username', 'auth_token', 'sek', 'token_expiry', 'status', 'created_at', 'updated_at'] as $col): ?>
                            <?php if (array_key_exists($col, $tkrow)): ?>
                            <tr>
                                <th class="pl-3 text-nowrap" scope="row"><?php echo htmlspecialchars($col, ENT_QUOTES, 'UTF-8'); ?></th>
                                <td class="small">
                                <?php
                                $v = (string) ($tkrow[$col] ?? '');
                                if ($col === 'auth_token' || $col === 'sek' || (stripos($col, 'json') !== false)) {
                                    echo ewaybill_mask_secret($v);
                                } else {
                                    echo nl2br(htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
                                }
                                ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <tr>
                            <th class="pl-3" scope="row">response_json</th>
                            <td class="small"><pre class="mb-0 small" style="white-space:pre-wrap;max-height:12rem;overflow:auto;"><?php
                            $j = (string) ($tkrow['response_json'] ?? '');
                            echo $j === '' ? '—' : htmlspecialchars(ewaybill_clip_text($j, 4000), ENT_QUOTES, 'UTF-8');
                            ?></pre></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="p-3 mb-0 text-muted">No row in <code>tbl_ewaybill_api_tokens</code> for this GSTIN and username yet. The table is created automatically on first use; run a successful authentication after saving API settings.</p>
            <?php endif; ?>
        </div>
    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'footer-script.php'; ?>
</body>
</html>
