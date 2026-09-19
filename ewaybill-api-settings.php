<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auragold_require_login.php';
require_once __DIR__ . '/includes/ewaybill_api_helper.php';
auragold_require_login_or_exit();

require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();

$flash = ['type' => '', 'message' => ''];
if (isset($_SESSION['ewaybill_flash']) && is_array($_SESSION['ewaybill_flash'])) {
    $flash = array_merge(
        $flash,
        array_intersect_key(
            $_SESSION['ewaybill_flash'],
            array_flip(['type', 'message'])
        )
    );
    unset($_SESSION['ewaybill_flash']);
}

$conn = isset($conn) && $conn instanceof mysqli ? $conn : (isset($conn_master) && $conn_master instanceof mysqli ? $conn_master : null);
if ($conn === null) {
    die('Database connection is not available.');
}

$fieldKeys = [
    'base_url'      => 'Base URL',
    'email'         => 'E-mail (registered on WhiteBooks)',
    'username'      => 'Username (GSP)',
    'password'      => 'Password (leave blank to keep stored value)',
    'ip_address'     => 'IP address (header)',
    'client_id'     => 'Client ID',
    'client_secret' => 'Client secret (leave blank to keep stored value)',
    'gstin'         => 'GSTIN',
];
$c = ewaybill_merged_config($conn);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (($_POST['ewaybill_action'] ?? '') === 'save_settings')) {
    $gstIn = isset($_POST['eway']['gstin']) ? trim((string) $_POST['eway']['gstin']) : '';
    if ($gstIn !== '' && function_exists('ewaybill_is_acceptable_eway_api_gstin') && !ewaybill_is_acceptable_eway_api_gstin($gstIn)) {
        $_SESSION['ewaybill_flash'] = [
            'type'    => 'danger',
            'message' => 'GSTIN was not saved: use exactly 15 characters matching your WhiteBooks credential set. Production GSTINs follow NIC format (…Q1Z5); sandbox often uses IDs like …Q000 — both are accepted for this field.',
        ];
        header('Location: ewaybill-api-settings.php');
        exit;
    }
    $ok = true;
    foreach (array_keys($fieldKeys) as $key) {
        if (!isset($_POST['eway'][$key])) {
            continue;
        }
        if ($key === 'password' || $key === 'client_secret') {
            $s = trim((string) $_POST['eway'][$key]);
            if ($s === '') {
                continue;
            }
            if (!ewaybill_upsert_setting($conn, $key, $s)) {
                $ok = false;
            }
        } else {
            $s = trim((string) $_POST['eway'][$key]);
            if (!ewaybill_upsert_setting($conn, $key, $s)) {
                $ok = false;
            }
        }
    }
    $_SESSION['ewaybill_flash'] = [
        'type'    => $ok ? 'success' : 'danger',
        'message' => $ok ? 'e-Way Bill API settings have been saved.' : 'Some settings could not be saved. Check the database and try again.',
    ];
    header('Location: ewaybill-api-settings.php');
    exit;
}

$c = ewaybill_merged_config($conn);

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title><?php echo function_exists('auragold_t') ? htmlspecialchars((string) auragold_t('set_software.eway_bill_page_title'), ENT_QUOTES, 'UTF-8') : 'e-Way Bill API - Set Software - ' . auragold_app_name(); ?></title>
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
.auragold-eway-page { padding: 24px; max-width: 720px; }
.eway-panel { max-width: 720px; }
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
    <h4 class="mb-3"><?php echo function_exists('auragold_t') ? htmlspecialchars((string) auragold_t('set_software.eway_bill_api'), ENT_QUOTES, 'UTF-8') : 'e-Way Bill API'; ?></h4>
    <p class="text-muted small">Configure connection values for the WhiteBooks sandbox or production API. These values are merged with <code>admin/config/ewaybill_config.php</code> (non-empty values saved here override the file). Use the Authentication page to obtain and store access tokens after saving.</p>

    <?php if ($flash['message'] !== ''): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    <?php endif; ?>

    <div class="card eway-panel shadow-sm">
        <div class="card-body">
            <form method="post" action="ewaybill-api-settings.php" autocomplete="off" class="eway-bill-form">
                <input type="hidden" name="ewaybill_action" value="save_settings">
                <?php foreach ($fieldKeys as $key => $label): ?>
                    <div class="form-group">
                        <label for="f_<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
                        <?php
                        $val = (string) ($c[$key] ?? '');
                        if ($key === 'password' || $key === 'client_secret') {
                            ?>
                            <input type="password" class="form-control" id="f_<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" name="eway[<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>]" value="" placeholder="<?php echo $val !== '' ? ewaybill_mask_secret($val) : ''; ?>">
                            <?php
                        } else {
                            ?>
                            <input type="text" class="form-control" id="f_<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" name="eway[<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>]" value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php
                        }
                        ?>
                    </div>
                    <?php if ($key === 'ip_address' && function_exists('ewaybill_is_whitebooks_sandbox_mode') && ewaybill_is_whitebooks_sandbox_mode($c)): ?>
                        <p class="text-muted small mt-n2 mb-3">Sandbox URL: outgoing API calls send header <code>ip_address: 0.0.0.0</code> (matches WhiteBooks sandbox IP(header) setting). You can keep <code>0.0.0.0</code> stored above.</p>
                    <?php endif; ?>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primary">Save settings</button>
                <a class="btn btn-outline-primary ml-2" href="ewaybill-authentication.php"><?php echo function_exists('auragold_t') ? htmlspecialchars((string) auragold_t('set_software.eway_bill_auth'), ENT_QUOTES, 'UTF-8') : 'e-Way Bill authentication'; ?></a>
            </form>
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
