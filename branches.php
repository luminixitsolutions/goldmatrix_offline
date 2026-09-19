<?php

session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/session_login_type.php';
require_once __DIR__ . '/includes/branch_working_context.php';
require_once __DIR__ . '/includes/branch_product_delete_permission.php';
require_once __DIR__ . '/includes/branch_tbl_branches_ip_subdomain.php';
require_once __DIR__ . '/includes/branch_panel_password.php';

if (!empty($conn_master)) {
    auragold_ensure_branches_allow_product_delete_column($conn_master);
    auragold_ensure_branches_ip_subdomain_columns_on_registry($conn_master);
    auragold_ensure_tbl_branches_panel_password($conn_master);
}

if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();

$is_logged_in = true;

$branch_switch_err = '';
if (isset($_GET['login_error']) && is_string($_GET['login_error'])) {
    $branch_switch_err = trim($_GET['login_error']);
}
$switch_branch_prompt_id = isset($_GET['switch_branch_id']) ? (int) $_GET['switch_branch_id'] : 0;

$list_scope_main = auragold_branches_page_list_scope_main_id();

$branch_cols = 'id, name, code, status, main_branch_id, created_at, db_name, allow_product_delete, ip_address, subdomain_url';
/** Hide registry rows used as system/template login (tbl_branches.username), not the logged-in user name. */
$branch_master_hidden_user_sql = "LOWER(TRIM(IFNULL(username,''))) <> 'superbranch'";

if ($list_scope_main > 0) {
    // This main is chosen by working DB: show it even if username is the system template (e.g. superbranch).
    $all_mains = getListMaster(
        'SELECT ' . $branch_cols . ' FROM tbl_branches WHERE main_branch_id = 0 AND id = ' . (int) $list_scope_main
        . ' ORDER BY id ASC'
    );
    $all_subs = getListMaster(
        'SELECT ' . $branch_cols . ' FROM tbl_branches WHERE main_branch_id = ' . (int) $list_scope_main
        . " AND LOWER(TRIM(IFNULL(username,''))) <> 'superbranch' "
        . 'ORDER BY id ASC'
    );
} else {
    // Legacy tbl_users (or unmatched branch session): full list
    $all_mains = getListMaster(
        'SELECT ' . $branch_cols . ' FROM tbl_branches WHERE main_branch_id = 0 AND ' . $branch_master_hidden_user_sql . ' ORDER BY id ASC'
    );
    // Sub-rows: hide template sub-accounts (username superbranch). Parent main may use username
    // superbranch for system login — still list normal subs under that main.
    $all_subs = getListMaster(
        'SELECT ' . $branch_cols . ' FROM tbl_branches b WHERE b.main_branch_id > 0 '
        . "AND LOWER(TRIM(IFNULL(b.username,''))) <> 'superbranch' "
        . 'AND EXISTS (SELECT 1 FROM tbl_branches m WHERE m.id = b.main_branch_id AND IFNULL(m.main_branch_id, 0) = 0) '
        . 'ORDER BY b.main_branch_id ASC, b.id ASC'
    );
}

$subs_by_main = [];
foreach ($all_subs as $s) {
    $mid = (int) $s['main_branch_id'];
    if (!isset($subs_by_main[$mid])) {
        $subs_by_main[$mid] = [];
    }
    $subs_by_main[$mid][] = $s;
}

$branch_add_countries = [];
if (!empty($conn)) {
    require_once __DIR__ . '/includes/location-helpers.php';
    require_once __DIR__ . '/includes/international-dial-codes.php';
    auragold_bootstrap_location_data($conn);
    $branch_add_countries = getList('SELECT id, name FROM tbl_countries WHERE status = 1 ORDER BY name ASC');
}
if (!is_array($branch_add_countries)) {
    $branch_add_countries = [];
}
if (!function_exists('auragold_render_dial_code_select')) {
    require_once __DIR__ . '/includes/international-dial-codes.php';
}

function auragold_branches_page_row_host(array $row): string {
    $h = trim((string) ($row['subdomain_url'] ?? ''));
    if ($h === '') {
        $h = trim((string) ($row['ip_address'] ?? ''));
    }
    return $h;
}

function auragold_branches_page_row_visit_url(string $host): string {
    if ($host === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $host)) {
        return $host;
    }
    $scheme = (defined('AURAGOLD_BRANCH_URL_USE_HTTPS') && AURAGOLD_BRANCH_URL_USE_HTTPS) ? 'https' : 'http';
    return $scheme . '://' . $host;
}

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Branches - Set Software - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
    <link rel="stylesheet" href="css/branch-add-modal.css">
    <style>
        :root {
            --branches-navy: #11294b;
            --branches-navy-dark: #0d1f38;
        }
        .branches-page { padding: 24px; max-width: 1120px; margin: 0 auto; }
        .branches-page h1 { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin-bottom: 20px; }
        .branches-card {
            border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 20px; overflow: hidden;
        }
        .branches-card h2 {
            font-size: 15px; font-weight: 700; color: var(--branches-navy);
            margin: 0; padding: 14px 18px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
        }
        .branches-card .card-body { padding: 0; }
        .branches-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .branches-table th, .branches-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: middle; }
        .branches-table th { background: #fff; font-weight: 600; color: #374151; }
        .branches-table tbody tr:last-child td { border-bottom: none; }
        .branches-table tr.branch-row-main td { background: #fafafa; font-weight: 600; }
        .branches-table .muted { color: #94a3b8; font-weight: 400; }
        .branch-status-switch {
            position: relative; width: 44px; height: 24px; flex-shrink: 0;
        }
        .branch-status-switch input {
            opacity: 0; width: 0; height: 0; position: absolute;
        }
        .branch-status-switch .slider {
            position: absolute; cursor: pointer; inset: 0; background: #cbd5e1; border-radius: 24px; transition: 0.2s;
        }
        .branch-status-switch .slider:before {
            position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px;
            background: #fff; border-radius: 50%; transition: 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .branch-status-switch input:checked + .slider { background: linear-gradient(135deg, var(--branches-navy) 0%, var(--branches-navy-dark) 100%); }
        .branch-status-switch input:checked + .slider:before { transform: translateX(20px); }
        .branch-status-switch input:disabled + .slider { opacity: 0.5; cursor: not-allowed; }
        .badge-status { font-size: 11px; padding: 2px 8px; border-radius: 999px; font-weight: 600; }
        .badge-status.on { background: #d1fae5; color: #065f46; }
        .badge-status.off { background: #fee2e2; color: #991b1b; }
        .badge-type { font-size: 10px; padding: 2px 6px; border-radius: 4px; background: rgba(17, 41, 75, 0.12); color: var(--branches-navy); margin-left: 6px; vertical-align: middle; }
        .branches-empty { padding: 28px; text-align: center; color: #64748b; font-size: 14px; }
        .branch-open-link { font-weight: 600; color: var(--branches-navy); text-decoration: none; }
        .branch-open-link:hover { text-decoration: underline; }
        .btn-branch-delete {
            font-size: 12px; font-weight: 600; color: #b91c1c; background: none; border: none;
            padding: 4px 0; cursor: pointer; text-decoration: underline;
        }
        .btn-branch-delete:hover { color: #991b1b; }
        .btn-branch-delete:disabled { opacity: 0.5; cursor: not-allowed; text-decoration: none; }
        .branch-db-code { font-size: 11px; color: #475569; word-break: break-all; max-width: 160px; display: inline-block; }
        .btn-branch-restore-db {
            font-size: 11px; font-weight: 600; color: #0f172a;
            background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px;
            padding: 5px 10px; cursor: pointer; white-space: nowrap;
        }
        .btn-branch-restore-db:hover { background: #e2e8f0; }
        .btn-branch-restore-db:disabled { opacity: 0.5; cursor: not-allowed; }
        tr.branch-row-switchable { cursor: pointer; }
        tr.branch-row-switchable:hover td { background: #f1f5f9; }
        .branch-ip-copy {
            font-size: 12px; word-break: break-all; max-width: 220px; display: inline-block;
            color: #64748b; background: none; border: none; padding: 0; cursor: text;
            text-align: left; text-decoration: none; font-family: inherit; font-weight: 400;
        }
        .branch-ip-copy:hover { color: #64748b; text-decoration: none; }
        .btn-branch-go {
            font-size: 12px; font-weight: 600; white-space: nowrap;
            color: #fff; background: linear-gradient(135deg, #11294b 0%, #0d1f38 100%);
            border: 1px solid #11294b; border-radius: 8px; padding: 6px 10px;
            text-decoration: none; display: inline-block;
        }
        .btn-branch-go:hover { filter: brightness(1.06); color: #fff; text-decoration: none; }
        .btn-branch-set-pwd {
            font-size: 11px; font-weight: 600; white-space: nowrap;
            color: var(--branches-navy); background: #fff;
            border: 1px solid #cbd5e1; border-radius: 8px; padding: 5px 8px; cursor: pointer;
        }
        .btn-branch-set-pwd:hover { background: #f8fafc; }
        .branch-pwd-overlay {
            position: fixed; inset: 0; z-index: 2100; display: none;
            align-items: center; justify-content: center;
            background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(2px);
        }
        .branch-pwd-overlay.is-open { display: flex; }
        .branch-pwd-dialog {
            background: #fff; border-radius: 12px; width: min(420px, 92vw);
            box-shadow: 0 20px 50px rgba(0,0,0,0.2); border: 1px solid #e2e8f0; overflow: hidden;
        }
        .branch-pwd-dialog__head {
            padding: 14px 18px; border-bottom: 1px solid #e2e8f0;
            display: flex; align-items: center; justify-content: space-between;
        }
        .branch-pwd-dialog__title { margin: 0; font-size: 16px; font-weight: 700; color: #0f172a; }
        .branch-pwd-dialog__close {
            border: none; background: none; font-size: 22px; line-height: 1; cursor: pointer; color: #64748b;
        }
        .branch-pwd-dialog__body { padding: 16px 18px; }
        .branch-pwd-dialog__foot {
            padding: 12px 18px 16px; display: flex; gap: 8px; justify-content: flex-end;
            border-top: 1px solid #e2e8f0;
        }
        .branch-pwd-label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .branch-pwd-input {
            width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 8px;
            padding: 8px 10px; font-size: 14px;
        }
        .branch-pwd-hint { margin: 8px 0 0; font-size: 12px; color: #64748b; line-height: 1.4; }
        .branch-pwd-error { margin-top: 8px; font-size: 12px; color: #b91c1c; display: none; }
        .branch-pwd-error.is-visible { display: block; }
        .branch-pwd-btn {
            font-size: 13px; font-weight: 600; border-radius: 8px; padding: 8px 14px; cursor: pointer; border: 1px solid transparent;
        }
        .branch-pwd-btn--ghost { background: #fff; border-color: #cbd5e1; color: #334155; }
        .branch-pwd-btn--primary {
            color: #fff; background: linear-gradient(135deg, #11294b 0%, #0d1f38 100%); border-color: #11294b;
        }
        .branch-pwd-btn--primary:disabled {
            opacity: 0.75; cursor: not-allowed; filter: none;
        }
        .branch-pwd-btn-spinner {
            display: inline-block;
            width: 14px; height: 14px; margin-right: 6px; vertical-align: -2px;
            border: 2px solid rgba(255,255,255,0.35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: branch-pwd-btn-spin 0.65s linear infinite;
        }
        @keyframes branch-pwd-btn-spin {
            to { transform: rotate(360deg); }
        }
        .btn-branch-create-sub {
            font-size: 12px; font-weight: 600; white-space: nowrap;
            color: #fff; background: linear-gradient(135deg, #11294b 0%, #0d1f38 100%);
            border: 1px solid #11294b; border-radius: 8px; padding: 6px 10px; cursor: pointer;
            box-shadow: 0 1px 4px rgba(17, 41, 75, 0.2);
        }
        .btn-branch-create-sub:hover { filter: brightness(1.06); }
        .branch-delete-loading-overlay {
            position: fixed;
            inset: 0;
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }
        .branch-delete-loading-overlay.is-visible {
            display: flex;
        }
        .branch-delete-loading-box {
            background: #fff;
            border-radius: 12px;
            padding: 28px 32px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 360px;
            border: 1px solid #e2e8f0;
        }
        .branch-delete-loading-spinner {
            width: 44px;
            height: 44px;
            margin: 0 auto 16px;
            border: 3px solid #e2e8f0;
            border-top-color: #b91c1c;
            border-radius: 50%;
            animation: branch-delete-spin 0.75s linear infinite;
        }
        @keyframes branch-delete-spin {
            to { transform: rotate(360deg); }
        }
        .branch-delete-loading-text {
            margin: 0 0 8px;
            font-size: 15px;
            font-weight: 600;
            color: #0f172a;
        }
        .branch-delete-loading-hint {
            margin: 0;
            font-size: 13px;
            color: #64748b;
            line-height: 1.45;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="layout-content">
        <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
            <div class="set-software-wrapper">
                <?php include 'set-software-sidebar.php'; ?>
                <div class="set-software-main">
                    <?php include __DIR__ . '/includes/set-software-branches-tabs.php'; ?>
                    <div class="branches-page">
                        <div class="branch-add-toolbar">
                            <h1>Branches</h1>
                            <?php if ($is_logged_in && auragold_session_may_create_main_branch()): ?>
                                <div class="branch-add-toolbar-actions">
                                    <button type="button" class="btn-branch-add-open btn-branch-add-main" id="branchAddMainOpen" title="Create a new main branch with its own database">Create Main Branch</button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($branch_switch_err !== ''): ?>
                            <div class="alert alert-danger" style="font-size:13px;"><?php echo htmlspecialchars($branch_switch_err); ?></div>
                        <?php endif; ?>
                        <?php if (empty($all_mains)): ?>
                            <div class="branches-card"><div class="branches-empty">No branches in the database.</div></div>
                        <?php else: ?>
                            <?php foreach ($all_mains as $main): ?>
                                <?php $children = $subs_by_main[(int) $main['id']] ?? []; ?>
                                <div class="branches-card">
                                    <h2><?php echo htmlspecialchars($main['name']); ?><?php if (trim((string) $main['code']) !== ''): ?><span class="muted" style="font-weight:400;"> — <?php echo htmlspecialchars($main['code']); ?></span><?php endif; ?></h2>
                                    <div class="card-body">
                                        <table class="branches-table">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Code</th>
                                                    <th style="min-width:160px;">IP address</th>
                                                    <!-- <th style="width:150px;">Database</th>
                                                    <th style="width:120px;">Restore</th> -->
                                                    <th style="width:120px;">Status</th>
                                                    <th style="width:110px;">Open</th>
                                                    <th style="width:130px;">Allow product delete</th>
                                                    <th style="width:100px;">Active</th>
                                                    <th style="width:88px;">Delete</th>
                                                    <th style="width:150px;">Add sub-branch</th>
                                                    <th style="width:130px;">Go to branch</th>
                                                    <th style="width:120px;">Set password</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr class="branch-row-main branch-row-switchable" data-switch-url="switch_branch.php?id=<?php echo (int) $main['id']; ?>" data-branch-id="<?php echo (int) $main['id']; ?>" data-branch-name="<?php echo htmlspecialchars((string) $main['name'], ENT_QUOTES, 'UTF-8'); ?>" title="Click a row to work in that branch (same login)">
                                                    <td>
                                                        <a href="switch_branch.php?id=<?php echo (int) $main['id']; ?>" class="branch-open-link branch-switch-guarded" data-branch-id="<?php echo (int) $main['id']; ?>" data-branch-name="<?php echo htmlspecialchars((string) $main['name'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($main['name']); ?></a>
                                                        <span class="badge-type">Main</span>
                                                    </td>
                                                    <td class="muted"><?php echo htmlspecialchars($main['code'] !== '' && $main['code'] !== null ? $main['code'] : '—'); ?></td>
                                                    <td><?php
                                                        $mh = auragold_branches_page_row_host($main);
                                                        if ($mh !== ''): ?>
                                                            <span class="branch-ip-copy muted" data-copy="<?php echo htmlspecialchars($mh, ENT_QUOTES, 'UTF-8'); ?>" title="Click to copy"><?php echo htmlspecialchars($mh); ?></span>
                                                        <?php else: ?>
                                                            <span class="muted">—</span>
                                                        <?php endif; ?></td>
                                                    <td class="branch-status-cell">
                                                        <?php if ((int) $main['status'] === 1): ?>
                                                            <span class="badge-status on">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge-status off">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="switch_branch.php?id=<?php echo (int) $main['id']; ?>" class="branch-switch-btn branch-switch-guarded" data-branch-id="<?php echo (int) $main['id']; ?>" data-branch-name="<?php echo htmlspecialchars((string) $main['name'], ENT_QUOTES, 'UTF-8'); ?>">Open</a>
                                                    </td>
                                                    <td><span class="muted" title="Main branch: product delete permission applies to sub-branches">—</span></td>
                                                    <td><span class="muted" title="Main branch status cannot be changed here">—</span></td>
                                                    <td>
                                                        <?php if ($is_logged_in && auragold_session_is_admin_login_type() && auragold_session_is_superadmin()): ?>
                                                            <button type="button" class="btn-branch-delete branch-delete-btn"
                                                                data-branch-id="<?php echo (int) $main['id']; ?>"
                                                                data-branch-name="<?php echo htmlspecialchars((string) $main['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-delete-main="1"
                                                                title="Permanently delete this main branch, all sub-branches under it, and related data">Delete</button>
                                                        <?php else: ?>
                                                            <span class="muted" title="Only a superadmin can delete a main branch">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($is_logged_in && auragold_session_is_admin_login_type()): ?>
                                                            <button type="button"
                                                                class="btn-branch-create-sub branch-add-sub-for-main"
                                                                data-main-id="<?php echo (int) $main['id']; ?>"
                                                                data-main-name="<?php echo htmlspecialchars((string) $main['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                title="Create a new sub-branch under this main branch">+ Sub-branch</button>
                                                        <?php else: ?>
                                                            <span class="muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ((int) $main['status'] === 1): ?>
                                                            <a href="switch_branch.php?id=<?php echo (int) $main['id']; ?>" class="btn-branch-go branch-switch-guarded" data-branch-id="<?php echo (int) $main['id']; ?>" data-branch-name="<?php echo htmlspecialchars((string) $main['name'], ENT_QUOTES, 'UTF-8'); ?>">Go to branch</a>
                                                        <?php else: ?>
                                                            <span class="muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($is_logged_in && auragold_session_is_admin_login_type() && auragold_branch_panel_may_manage_password($main)): ?>
                                                            <button type="button" class="btn-branch-set-pwd branch-set-pwd-btn"
                                                                data-branch-id="<?php echo (int) $main['id']; ?>"
                                                                data-branch-name="<?php echo htmlspecialchars((string) $main['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                title="Set password required to open this branch panel">Set password</button>
                                                        <?php else: ?>
                                                            <span class="muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php foreach ($children as $sub): ?>
                                                    <tr<?php echo ((int) $sub['status'] === 1)
                                                        ? ' class="branch-row-switchable" data-switch-url="switch_branch.php?id=' . (int) $sub['id'] . '" data-branch-id="' . (int) $sub['id'] . '" data-branch-name="' . htmlspecialchars((string) $sub['name'], ENT_QUOTES, 'UTF-8') . '" title="Click a row to work in that branch (same login)"'
                                                        : ''; ?>>
                                                        <td>
                                                            <?php if ((int) $sub['status'] === 1): ?>
                                                                <a href="switch_branch.php?id=<?php echo (int) $sub['id']; ?>" class="branch-open-link branch-switch-guarded" data-branch-id="<?php echo (int) $sub['id']; ?>" data-branch-name="<?php echo htmlspecialchars((string) $sub['name'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($sub['name']); ?></a>
                                                            <?php else: ?>
                                                                <?php echo htmlspecialchars($sub['name']); ?>
                                                                <span class="muted" style="font-size:11px;"> (inactive)</span>
                                                            <?php endif; ?>
                                                            <span class="badge-type">Subbranch</span>
                                                        </td>
                                                        <td class="muted"><?php echo htmlspecialchars($sub['code'] !== '' && $sub['code'] !== null ? $sub['code'] : '—'); ?></td>
                                                        <td><?php
                                                            $sh = auragold_branches_page_row_host($sub);
                                                            if ($sh !== ''): ?>
                                                                <span class="branch-ip-copy muted" data-copy="<?php echo htmlspecialchars($sh, ENT_QUOTES, 'UTF-8'); ?>" title="Click to copy"><?php echo htmlspecialchars($sh); ?></span>
                                                            <?php else: ?>
                                                                <span class="muted">—</span>
                                                            <?php endif; ?></td>
                                                        <!-- <td>
                                                            <?php
                                                            $subDb = trim((string) ($sub['db_name'] ?? ''));
                                                            if ($subDb !== ''):
                                                                ?>
                                                                <span class="branch-db-code" title="<?php echo htmlspecialchars($subDb); ?>"><?php echo htmlspecialchars(strlen($subDb) > 28 ? substr($subDb, 0, 26) . '…' : $subDb); ?></span>
                                                            <?php else: ?>
                                                                <span class="muted">—</span>
                                                            <?php endif; ?>
                                                        </td> -->
                                                        <!-- <td>
                                                            <?php
                                                            $subDbRestore = trim((string) ($sub['db_name'] ?? ''));
                                                            if ($subDbRestore !== '' && $is_logged_in && auragold_session_is_admin_login_type()):
                                                                ?>
                                                                <button type="button" class="btn-branch-restore-db branch-restore-db-btn"
                                                                    data-branch-id="<?php echo (int) $sub['id']; ?>"
                                                                    data-db-name="<?php echo htmlspecialchars($subDbRestore, ENT_QUOTES, 'UTF-8'); ?>"
                                                                    title="Create all tables from the main database and copy masters, voucher settings, bill series, metal rates (use if the branch DB is empty)">Restore tables</button>
                                                            <?php else: ?>
                                                                <span class="muted">—</span>
                                                            <?php endif; ?>
                                                        </td> -->
                                                        <td class="branch-status-cell">
                                                            <?php if ((int) $sub['status'] === 1): ?>
                                                                <span class="badge-status on">Active</span>
                                                            <?php else: ?>
                                                                <span class="badge-status off">Inactive</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ((int) $sub['status'] === 1): ?>
                                                                <a href="switch_branch.php?id=<?php echo (int) $sub['id']; ?>" class="branch-switch-btn branch-switch-guarded" data-branch-id="<?php echo (int) $sub['id']; ?>" data-branch-name="<?php echo htmlspecialchars((string) $sub['name'], ENT_QUOTES, 'UTF-8'); ?>">Open</a>
                                                            <?php else: ?>
                                                                <span class="muted" title="Activate this branch first">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($is_logged_in): ?>
                                                                <label class="branch-allow-delete-wrap" title="Allow users logged into this sub-branch to delete products in that branch database">
                                                                    <input type="checkbox" class="branch-allow-delete-toggle" data-branch-id="<?php echo (int) $sub['id']; ?>" <?php echo ((int) ($sub['allow_product_delete'] ?? 0) === 1) ? 'checked' : ''; ?>>
                                                                    <span class="muted" style="margin-left:6px;">Yes</span>
                                                                </label>
                                                            <?php else: ?>
                                                                <span class="muted">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($is_logged_in): ?>
                                                                <label class="branch-status-switch" title="Enable / disable this sub-branch (not database switch)">
                                                                    <input type="checkbox" class="branch-status-toggle" data-branch-id="<?php echo (int) $sub['id']; ?>" <?php echo ((int) $sub['status'] === 1) ? 'checked' : ''; ?>>
                                                                    <span class="slider"></span>
                                                                </label>
                                                            <?php else: ?>
                                                                <span class="muted">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($is_logged_in && auragold_session_is_admin_login_type()): ?>
                                                                <button type="button" class="btn-branch-delete branch-delete-btn"
                                                                    data-branch-id="<?php echo (int) $sub['id']; ?>"
                                                                    data-branch-name="<?php echo htmlspecialchars((string) $sub['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                    title="Permanently delete this branch and all related data">Delete</button>
                                                            <?php else: ?>
                                                                <span class="muted">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><span class="muted">—</span></td>
                                                        <td>
                                                            <?php if ((int) $sub['status'] === 1): ?>
                                                                <a href="switch_branch.php?id=<?php echo (int) $sub['id']; ?>" class="btn-branch-go branch-switch-guarded" data-branch-id="<?php echo (int) $sub['id']; ?>" data-branch-name="<?php echo htmlspecialchars((string) $sub['name'], ENT_QUOTES, 'UTF-8'); ?>">Go to branch</a>
                                                            <?php else: ?>
                                                                <span class="muted">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($is_logged_in && auragold_session_is_admin_login_type() && auragold_branch_panel_may_manage_password($sub)): ?>
                                                                <button type="button" class="btn-branch-set-pwd branch-set-pwd-btn"
                                                                    data-branch-id="<?php echo (int) $sub['id']; ?>"
                                                                    data-branch-name="<?php echo htmlspecialchars((string) $sub['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                    title="Set password required to open this branch panel">Set password</button>
                                                            <?php else: ?>
                                                                <span class="muted">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($is_logged_in && auragold_session_is_admin_login_type()): ?>
    <!-- Add Branch: password + details modals -->
    <div id="branchAddPwdOverlay" class="branch-add-overlay" role="dialog" aria-modal="true" aria-labelledby="branchAddPwdTitle">
        <div class="branch-add-dialog">
            <div class="branch-add-dialog__head">
                <h2 id="branchAddPwdTitle" class="branch-add-dialog__title">Confirm access</h2>
                <button type="button" class="branch-add-dialog__close" id="branchAddPwdCancel" aria-label="Close">&times;</button>
            </div>
            <div class="branch-add-dialog__body">
                <p style="margin:0 0 12px;font-size:14px;color:#475569;">Enter the master password to add a branch.</p>
                <label class="branch-add-label" for="branchAddPwdInput">Password</label>
                <input type="password" id="branchAddPwdInput" class="branch-add-input" autocomplete="current-password">
                <div id="branchAddPwdError" class="branch-add-error" role="alert"></div>
            </div>
            <div class="branch-add-dialog__foot">
                <button type="button" class="branch-add-btn branch-add-btn--ghost" id="branchAddPwdCancelFoot">Cancel</button>
                <button type="button" class="branch-add-btn branch-add-btn--primary" id="branchAddPwdSubmit">Continue</button>
            </div>
        </div>
    </div>
    <div id="branchAddFormOverlay" class="branch-add-overlay" role="dialog" aria-modal="true" aria-labelledby="branchAddFormTitle">
        <div class="branch-add-dialog branch-add-dialog--wide">
            <div id="branchAddSavingOverlay" class="branch-add-saving-overlay" aria-hidden="true" hidden>
                <span class="branch-add-saving-spinner" aria-hidden="true"></span>
                <p class="branch-add-saving-text" id="branchAddSavingText">Please wait…</p>
                <p class="branch-add-saving-hint">Creating the branch and database tables. This may take a minute.</p>
            </div>
            <div class="branch-add-dialog__head">
                <h2 id="branchAddFormTitle" class="branch-add-dialog__title">Branch details</h2>
                <button type="button" class="branch-add-dialog__close" id="branchAddFormClose" aria-label="Close">&times;</button>
            </div>
            <form id="branchAddDetailsForm" novalidate>
                <div class="branch-add-dialog__body">
                    <div id="branchAddFormError" class="branch-add-error" role="alert"></div>
                    <p id="branchAddUnderMainNote" class="branch-add-under-main-note" hidden style="margin:0 0 14px;font-size:13px;color:#0f766e;font-weight:600;"></p>
                    <div class="branch-add-row">
                        <label class="branch-add-label" for="branchAddName">Branch name <span style="color:#b91c1c">*</span></label>
                        <input type="text" id="branchAddName" name="branch_name" class="branch-add-input" required maxlength="255">
                    </div>
                    <div class="branch-add-row branch-add-row--2col">
                        <div>
                            <label class="branch-add-label" for="branchAddPhoneCountryCode">Country code <span style="color:#b91c1c">*</span></label>
                            <select id="branchAddPhoneCountryCode" name="profile_phone_country_code" class="branch-add-input branch-add-select" required aria-required="true">
                                <?php auragold_render_dial_code_select('971'); ?>
                            </select>
                        </div>
                        <div>
                            <label class="branch-add-label" for="branchAddC1">Contact 1</label>
                            <input type="text" id="branchAddC1" name="contact1" class="branch-add-input" maxlength="50">
                        </div>
                    </div>
                    <div class="branch-add-row">
                        <label class="branch-add-label" for="branchAddC2">Contact 2</label>
                        <input type="text" id="branchAddC2" name="contact2" class="branch-add-input" maxlength="50">
                    </div>
                    <div class="branch-add-row">
                        <label class="branch-add-label" for="branchAddMail">Mail ID</label>
                        <input type="email" id="branchAddMail" name="mail_id" class="branch-add-input" maxlength="255">
                    </div>
                    <div class="branch-add-row branch-add-row--2col">
                        <div>
                            <label class="branch-add-label" for="branchAddDigits">No. of digits <span style="color:#b91c1c">*</span></label>
                            <input type="number" id="branchAddDigits" name="no_of_digits" class="branch-add-input" min="1" max="32" value="5" required>
                        </div>
                        <div>
                            <label class="branch-add-label" for="branchAddPrefix">Barcode prefix</label>
                            <input type="text" id="branchAddPrefix" name="barcode_prefix" class="branch-add-input" maxlength="50" placeholder="e.g. RG">
                        </div>
                    </div>
                    <div class="branch-add-row">
                        <label class="branch-add-label" for="branchAddAddr">Address</label>
                        <textarea id="branchAddAddr" name="address" class="branch-add-textarea" maxlength="2000"></textarea>
                    </div>
                    <div class="branch-add-row branch-add-row--2col">
                        <div>
                            <label class="branch-add-label" for="branchAddCountry">Country <span style="color:#b91c1c">*</span></label>
                            <select id="branchAddCountry" name="country_id" class="branch-add-input branch-add-select" required aria-required="true">

                                <option value="">— Select —</option>
                                <?php foreach ($branch_add_countries as $bc): ?>
                                    <option value="<?php echo (int) $bc['id']; ?>"><?php echo htmlspecialchars((string) $bc['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="branch-add-label" for="branchAddState">State <span style="color:#b91c1c">*</span></label>
                            <select id="branchAddState" name="state_id" class="branch-add-input branch-add-select" required aria-required="true" disabled>
                                <option value="">— Select country first —</option>
                            </select>
                        </div>
                    </div>
                    <div class="branch-add-row branch-add-row--2col">
                        <div>
                            <label class="branch-add-label" for="branchAddCity">City <span style="color:#b91c1c">*</span></label>
                            <select id="branchAddCity" name="city_id" class="branch-add-input branch-add-select" required aria-required="true" disabled>
                                <option value="">— Select state first —</option>
                            </select>
                        </div>
                        <div>
                            <label class="branch-add-label" for="branchAddZip">Zip code</label>
                            <input type="text" id="branchAddZip" name="zip_code" class="branch-add-input" maxlength="20">
                        </div>
                    </div>
                    <div class="branch-add-row">
                        <label class="branch-add-label" for="branchAddTimezone">Timezone <span style="color:#b91c1c">*</span></label>
                        <select id="branchAddTimezone" name="timezone" class="branch-add-input branch-add-select" required aria-required="true">
                            <?php
                            $branch_add_tz_opts = function_exists('auragold_timezone_options')
                                ? auragold_timezone_options()
                                : ['Asia/Kolkata' => 'India (Asia/Kolkata)'];
                            foreach ($branch_add_tz_opts as $tzVal => $tzLabel):
                            ?>
                            <option value="<?php echo htmlspecialchars($tzVal); ?>"<?php echo $tzVal === 'Asia/Kolkata' ? ' selected' : ''; ?>><?php echo htmlspecialchars($tzLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="display:block;margin-top:4px;color:#64748b;font-size:12px;">Used for local transaction date &amp; time (not India server time).</small>
                    </div>
                    <div class="branch-add-row">
                        <label class="branch-add-label" for="branchAddHost">IP address <span style="color:#b91c1c">*</span></label>
                        <input type="text" id="branchAddHost" name="branch_ip_host" class="branch-add-input" maxlength="255" required placeholder="e.g. 192.0.2.1, pune.goldmatrix.com, or https://pune.goldmatrix.com" autocomplete="off" autocorrect="off" spellcheck="false" aria-required="true" aria-describedby="branchAddHostError" aria-invalid="false">
                        <div id="branchAddHostError" class="branch-add-error branch-add-field-error" role="alert"></div>
                    </div>
                    <div class="branch-add-row branch-add-row--2col">
                        <div>
                            <label class="branch-add-label" for="branchAddDbName">Database name</label>
                            <input type="text" id="branchAddDbName" class="branch-add-input" maxlength="64" readonly autocomplete="off" tabindex="-1" aria-readonly="true">
                        </div>
                        <div>
                            <label class="branch-add-label" for="branchAddDbUser">DB user</label>
                            <input type="text" id="branchAddDbUser" class="branch-add-input" maxlength="32" readonly autocomplete="off" tabindex="-1" aria-readonly="true">
                        </div>
                    </div>
                    <div class="branch-add-row">
                        <label class="branch-add-label" for="branchAddDbPass">DB password</label>
                        <input type="text" id="branchAddDbPass" class="branch-add-input" maxlength="255" readonly autocomplete="off" tabindex="-1" value="Generated on save — copy from confirmation" aria-readonly="true">
                    </div>
                    <div class="branch-add-row">
                        <label class="branch-add-check">
                            <input type="checkbox" id="branchAddActive" name="active" value="1" checked>
                            Active
                        </label>
                    </div>
                </div>
                <div class="branch-add-dialog__foot">
                    <button type="button" class="branch-add-btn branch-add-btn--ghost" id="branchAddFormCancel">Cancel</button>
                    <button type="submit" class="branch-add-btn branch-add-btn--primary" id="branchAddFormSubmit">Save branch</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    <?php
    $ag_branch_base_host = '';
    if (defined('AURAGOLD_BRANCH_SUBDOMAIN_BASE_HOST')) {
        $ag_branch_base_host = trim((string) AURAGOLD_BRANCH_SUBDOMAIN_BASE_HOST);
    }
    if ($ag_branch_base_host !== '' && (stripos($ag_branch_base_host, 'http://') === 0 || stripos($ag_branch_base_host, 'https://') === 0)) {
        $ag_pu = @parse_url($ag_branch_base_host);
        if (is_array($ag_pu) && !empty($ag_pu['host'])) {
            $ag_branch_base_host = $ag_pu['host'];
        }
    }
    ?>
    window.AURAGOLD_BRANCH_UI = {
        project: <?php echo json_encode(defined('AURAGOLD_PROJECT') ? AURAGOLD_PROJECT : 'local'); ?>,
        dbPrefix: <?php echo json_encode(defined('AURAGOLD_DB_PREFIX') ? AURAGOLD_DB_PREFIX : 'auragold_'); ?>,
        branchSubdomainBaseHost: <?php echo json_encode($ag_branch_base_host); ?>,
        branchSubdomainUrlHttps: <?php echo (defined('AURAGOLD_BRANCH_URL_USE_HTTPS') && AURAGOLD_BRANCH_URL_USE_HTTPS) ? 'true' : 'false'; ?>
    };
    </script>
    <script src="assets/libs/bootstrap-sweetalert/bootstrap-sweetalert.js"></script>
    <script src="js/branch-add-modal.js"></script>
    <script>
    (function () {
        var pcf = document.getElementById('branchAddPwdCancelFoot');
        var po = document.getElementById('branchAddPwdOverlay');
        if (pcf && po) pcf.addEventListener('click', function () { po.classList.remove('is-open'); });
    })();
    </script>
    <?php endif; ?>

    <?php if ($is_logged_in): ?>
    <div id="branchGoPwdOverlay" class="branch-pwd-overlay" role="dialog" aria-modal="true" aria-labelledby="branchGoPwdTitle">
        <div class="branch-pwd-dialog">
            <div class="branch-pwd-dialog__head">
                <h2 id="branchGoPwdTitle" class="branch-pwd-dialog__title">Enter branch password</h2>
                <button type="button" class="branch-pwd-dialog__close" id="branchGoPwdClose" aria-label="Close">&times;</button>
            </div>
            <div class="branch-pwd-dialog__body">
                <p id="branchGoPwdBranchLabel" style="margin:0 0 12px;font-size:14px;color:#475569;"></p>
                <label class="branch-pwd-label" for="branchGoPwdInput">Password</label>
                <input type="password" id="branchGoPwdInput" class="branch-pwd-input" autocomplete="current-password">
                <div id="branchGoPwdError" class="branch-pwd-error" role="alert"></div>
            </div>
            <div class="branch-pwd-dialog__foot">
                <button type="button" class="branch-pwd-btn branch-pwd-btn--ghost" id="branchGoPwdCancel">Cancel</button>
                <button type="button" class="branch-pwd-btn branch-pwd-btn--primary" id="branchGoPwdSubmit">Continue</button>
            </div>
        </div>
    </div>
    <div id="branchSetPwdOverlay" class="branch-pwd-overlay" role="dialog" aria-modal="true" aria-labelledby="branchSetPwdTitle">
        <div class="branch-pwd-dialog">
            <div class="branch-pwd-dialog__head">
                <h2 id="branchSetPwdTitle" class="branch-pwd-dialog__title">Set branch password</h2>
                <button type="button" class="branch-pwd-dialog__close" id="branchSetPwdClose" aria-label="Close">&times;</button>
            </div>
            <div class="branch-pwd-dialog__body">
                <p id="branchSetPwdBranchLabel" style="margin:0 0 12px;font-size:14px;color:#475569;"></p>
                <label class="branch-pwd-label" for="branchSetPwdInput">New password</label>
                <input type="password" id="branchSetPwdInput" class="branch-pwd-input" autocomplete="off" autocapitalize="off" spellcheck="false">
                <p class="branch-pwd-hint">This password is required when opening the branch panel.</p>
                <div id="branchSetPwdError" class="branch-pwd-error" role="alert"></div>
            </div>
            <div class="branch-pwd-dialog__foot">
                <button type="button" class="branch-pwd-btn branch-pwd-btn--ghost" id="branchSetPwdReset">Reset to default</button>
                <button type="button" class="branch-pwd-btn branch-pwd-btn--ghost" id="branchSetPwdCancel">Cancel</button>
                <button type="button" class="branch-pwd-btn branch-pwd-btn--primary" id="branchSetPwdSubmit">Save</button>
            </div>
        </div>
    </div>
    <div id="branchDeleteLoadingOverlay" class="branch-delete-loading-overlay" aria-hidden="true" hidden>
        <div class="branch-delete-loading-box" role="status">
            <span class="branch-delete-loading-spinner" aria-hidden="true"></span>
            <p class="branch-delete-loading-text">Please wait…</p>
            <p class="branch-delete-loading-hint">Deleting branch and related data. This may take a minute.</p>
        </div>
    </div>
    <script>
    (function () {
        function setBranchDeleteLoading(isLoading) {
            var el = document.getElementById('branchDeleteLoadingOverlay');
            if (!el) {
                return;
            }
            if (isLoading) {
                el.removeAttribute('hidden');
                el.classList.add('is-visible');
                el.setAttribute('aria-hidden', 'false');
            } else {
                el.setAttribute('hidden', '');
                el.classList.remove('is-visible');
                el.setAttribute('aria-hidden', 'true');
            }
        }

        function badgeHtml(on) {
            return on
                ? '<span class="badge-status on">Active</span>'
                : '<span class="badge-status off">Inactive</span>';
        }
        document.querySelectorAll('.branch-status-toggle').forEach(function (input) {
            input.addEventListener('change', function () {
                var self = this;
                var id = self.getAttribute('data-branch-id');
                var status = self.checked ? 1 : 0;
                var body = 'id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(status);
                fetch('ajax/update-branch-status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body,
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (!d || d.status !== 'ok') {
                            self.checked = !self.checked;
                            alert((d && d.message) ? d.message : 'Could not update status');
                            return;
                        }
                        var row = self.closest('tr');
                        if (!row) return;
                        var badgeCell = row.querySelector('.branch-status-cell');
                        if (badgeCell) badgeCell.innerHTML = badgeHtml(status === 1);
                    })
                    .catch(function () {
                        self.checked = !self.checked;
                        alert('Network error');
                    });
            });
        });
        function copyTextToClipboard(text, onSuccess) {
            if (!text) {
                return;
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(onSuccess).catch(function () {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    try {
                        if (document.execCommand('copy')) {
                            onSuccess();
                        }
                    } catch (err) {}
                    document.body.removeChild(ta);
                });
                return;
            }
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                if (document.execCommand('copy')) {
                    onSuccess();
                }
            } catch (err) {}
            document.body.removeChild(ta);
        }
        document.querySelectorAll('.branch-ip-copy').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var text = el.getAttribute('data-copy') || el.textContent || '';
                var origTitle = el.getAttribute('title') || 'Click to copy';
                copyTextToClipboard(text.trim(), function () {
                    el.setAttribute('title', 'Copied!');
                    setTimeout(function () {
                        el.setAttribute('title', origTitle);
                    }, 1500);
                });
            });
        });
        document.querySelectorAll('tr.branch-row-switchable').forEach(function (tr) {
            tr.addEventListener('click', function (e) {
                if (e.target.closest('a, button, input, label, select, textarea, .branch-ip-copy')) {
                    return;
                }
                var id = tr.getAttribute('data-branch-id');
                var nm = tr.getAttribute('data-branch-name') || '';
                if (id && typeof window.branchOpenGoPasswordModal === 'function') {
                    window.branchOpenGoPasswordModal(id, nm);
                    return;
                }
                var u = tr.getAttribute('data-switch-url');
                if (u) {
                    window.location.href = u;
                }
            });
        });

        (function initBranchPanelPassword() {
            var goOverlay = document.getElementById('branchGoPwdOverlay');
            var goInput = document.getElementById('branchGoPwdInput');
            var goError = document.getElementById('branchGoPwdError');
            var goLabel = document.getElementById('branchGoPwdBranchLabel');
            var goSubmit = document.getElementById('branchGoPwdSubmit');
            var goCancel = document.getElementById('branchGoPwdCancel');
            var goClose = document.getElementById('branchGoPwdClose');
            var goSubmitDefaultHtml = goSubmit ? goSubmit.innerHTML : 'Continue';
            var goBranchId = 0;

            var setOverlay = document.getElementById('branchSetPwdOverlay');
            var setInput = document.getElementById('branchSetPwdInput');
            var setError = document.getElementById('branchSetPwdError');
            var setLabel = document.getElementById('branchSetPwdBranchLabel');
            var setSubmit = document.getElementById('branchSetPwdSubmit');
            var setBranchId = 0;
            var defaultPwd = <?php echo json_encode(AURAGOLD_BRANCH_PANEL_DEFAULT_PASSWORD, JSON_UNESCAPED_UNICODE); ?>;

            function showErr(el, msg) {
                if (!el) return;
                if (!msg) {
                    el.textContent = '';
                    el.classList.remove('is-visible');
                    return;
                }
                el.textContent = msg;
                el.classList.add('is-visible');
            }

            function setGoSubmitLoading(isLoading) {
                if (!goSubmit) return;
                if (isLoading) {
                    goSubmit.disabled = true;
                    goSubmit.innerHTML = '<span class="branch-pwd-btn-spinner" aria-hidden="true"></span> Please wait…';
                } else {
                    goSubmit.disabled = false;
                    goSubmit.innerHTML = goSubmitDefaultHtml;
                }
                if (goCancel) goCancel.disabled = !!isLoading;
                if (goClose) goClose.disabled = !!isLoading;
                if (goInput) goInput.disabled = !!isLoading;
            }

            function openGoModal(id, name) {
                if (!goOverlay) return;
                goBranchId = parseInt(id, 10) || 0;
                if (goLabel) {
                    goLabel.textContent = name ? ('Branch: ' + name) : 'Enter the password for this branch.';
                }
                if (goInput) {
                    goInput.value = '';
                    goInput.disabled = false;
                }
                setGoSubmitLoading(false);
                showErr(goError, '');
                goOverlay.classList.add('is-open');
                setTimeout(function () {
                    if (goInput) goInput.focus();
                }, 50);
            }

            function closeGoModal() {
                if (goOverlay) goOverlay.classList.remove('is-open');
                goBranchId = 0;
                setGoSubmitLoading(false);
            }

            window.branchOpenGoPasswordModal = openGoModal;

            function submitGoPassword() {
                if (!goBranchId || !goInput) return;
                var pw = String(goInput.value || '');
                if (!pw.trim()) {
                    showErr(goError, 'Enter the branch password');
                    return;
                }
                setGoSubmitLoading(true);
                showErr(goError, '');
                var body = 'id=' + encodeURIComponent(String(goBranchId)) + '&password=' + encodeURIComponent(pw);
                fetch('ajax/verify-branch-panel-password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body,
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d && d.status === 'ok' && d.redirect) {
                            window.location.href = d.redirect;
                            return;
                        }
                        setGoSubmitLoading(false);
                        showErr(goError, (d && d.message) ? d.message : 'Incorrect password');
                    })
                    .catch(function () {
                        setGoSubmitLoading(false);
                        showErr(goError, 'Network error');
                    });
            }

            document.querySelectorAll('.branch-switch-guarded').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var id = el.getAttribute('data-branch-id');
                    var nm = el.getAttribute('data-branch-name') || '';
                    openGoModal(id, nm);
                });
            });

            ['branchGoPwdClose', 'branchGoPwdCancel'].forEach(function (cid) {
                var b = document.getElementById(cid);
                if (b) b.addEventListener('click', closeGoModal);
            });
            if (goSubmit) goSubmit.addEventListener('click', submitGoPassword);
            if (goInput) {
                goInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        submitGoPassword();
                    }
                });
            }
            if (goOverlay) {
                goOverlay.addEventListener('click', function (e) {
                    if (e.target === goOverlay) closeGoModal();
                });
            }

            function openSetModal(id, name) {
                if (!setOverlay) return;
                setBranchId = parseInt(id, 10) || 0;
                if (setLabel) {
                    setLabel.textContent = name ? ('Branch: ' + name) : 'Set panel password for this branch.';
                }
                if (setInput) {
                    setInput.value = '';
                }
                showErr(setError, '');
                setOverlay.classList.add('is-open');
                setTimeout(function () {
                    if (setInput) setInput.focus();
                }, 50);
            }

            function closeSetModal() {
                if (setOverlay) setOverlay.classList.remove('is-open');
                setBranchId = 0;
            }

            document.querySelectorAll('.branch-set-pwd-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openSetModal(btn.getAttribute('data-branch-id'), btn.getAttribute('data-branch-name') || '');
                });
            });

            function saveSetPassword(pwd) {
                if (!setBranchId) return;
                pwd = String(pwd || '').trim();
                if (pwd === '') {
                    showErr(setError, 'Enter a password');
                    return;
                }
                if (setSubmit) setSubmit.disabled = true;
                showErr(setError, '');
                var body = 'id=' + encodeURIComponent(String(setBranchId)) + '&password=' + encodeURIComponent(pwd);
                fetch('ajax/set-branch-panel-password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body,
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (setSubmit) setSubmit.disabled = false;
                        if (d && d.status === 'ok') {
                            closeSetModal();
                            try { window.alert(d.message || 'Password saved'); } catch (e1) {}
                            return;
                        }
                        showErr(setError, (d && d.message) ? d.message : 'Could not save password');
                    })
                    .catch(function () {
                        if (setSubmit) setSubmit.disabled = false;
                        showErr(setError, 'Network error');
                    });
            }

            var setReset = document.getElementById('branchSetPwdReset');
            if (setReset) {
                setReset.addEventListener('click', function () {
                    saveSetPassword(defaultPwd);
                });
            }
            if (setSubmit) {
                setSubmit.addEventListener('click', function () {
                    saveSetPassword(setInput ? setInput.value : '');
                });
            }
            ['branchSetPwdClose', 'branchSetPwdCancel'].forEach(function (cid) {
                var b = document.getElementById(cid);
                if (b) b.addEventListener('click', closeSetModal);
            });
            if (setOverlay) {
                setOverlay.addEventListener('click', function (e) {
                    if (e.target === setOverlay) closeSetModal();
                });
            }

            var promptId = <?php echo (int) $switch_branch_prompt_id; ?>;
            if (promptId > 0) {
                var promptRow = document.querySelector('[data-branch-id="' + promptId + '"]');
                var promptName = promptRow ? (promptRow.getAttribute('data-branch-name') || '') : '';
                openGoModal(promptId, promptName);
            }
        })();
        document.querySelectorAll('.branch-allow-delete-toggle').forEach(function (input) {
            input.addEventListener('change', function () {
                var self = this;
                var id = self.getAttribute('data-branch-id');
                var allow = self.checked ? 1 : 0;
                var body = 'id=' + encodeURIComponent(id) + '&allow_product_delete=' + encodeURIComponent(allow);
                fetch('ajax/update-branch-allow-product-delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body,
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (!d || d.status !== 'ok') {
                            self.checked = !self.checked;
                            alert((d && d.message) ? d.message : 'Could not update setting');
                            return;
                        }
                    })
                    .catch(function () {
                        self.checked = !self.checked;
                        alert('Network error');
                    });
            });
        });
        document.querySelectorAll('.branch-delete-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var id = btn.getAttribute('data-branch-id');
                var nm = btn.getAttribute('data-branch-name') || '';
                var isMain = btn.getAttribute('data-delete-main') === '1';
                if (!id) {
                    return;
                }
                var w1 = isMain
                    ? ('This will PERMANENTLY delete MAIN branch “' + nm + '”, ALL sub-branches under it, and ALL related data:\n\n'
                        + '• Every sub-branch row and its data\n'
                        + '• Stock, invoices, and other transactions for those branches\n'
                        + '• Product links and branch-scoped master data\n'
                        + '• Users will be unassigned from those branches\n'
                        + '• Dedicated MySQL databases for this main and its subs will be dropped when configured\n\n'
                        + 'This cannot be undone. Do you want to continue?')
                    : ('This will PERMANENTLY delete branch “' + nm + '” and ALL related data in this application database:\n\n'
                        + '• Stock, invoices, and other transactions for this branch\n'
                        + '• Product links and branch-scoped master data\n'
                        + '• Users will be unassigned from this branch\n'
                        + '• If this branch has a dedicated MySQL database, it will be dropped\n\n'
                        + 'This cannot be undone. Do you want to continue?');
                if (!window.confirm(w1)) {
                    return;
                }
                if (!window.confirm('FINAL CONFIRMATION: All data for this branch will be permanently removed and cannot be recovered. Delete now?')) {
                    return;
                }
                var pw = window.prompt('Enter the same master password used when adding a branch:', '');
                if (pw === null || String(pw).trim() === '') {
                    return;
                }
                btn.disabled = true;
                setBranchDeleteLoading(true);
                var body = 'id=' + encodeURIComponent(id) + '&password=' + encodeURIComponent(String(pw).trim());
                fetch('api/delete_branch.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body,
                    credentials: 'same-origin'
                })
                    .then(function (r) {
                        return r.text().then(function (text) {
                            var d = null;
                            try {
                                d = text ? JSON.parse(text) : null;
                            } catch (parseErr) {
                                var snippet = (text && text.trim()) ? text.trim().substring(0, 220) : '';
                                var err = new Error(snippet || ('HTTP ' + r.status));
                                err.httpStatus = r.status;
                                throw err;
                            }
                            if (!r.ok && (!d || d.ok !== true)) {
                                var msg = (d && d.message) ? d.message : ('Request failed (HTTP ' + r.status + ')');
                                throw new Error(msg);
                            }
                            return d;
                        });
                    })
                    .then(function (d) {
                        if (d && d.ok) {
                            if (d.warning) {
                                try {
                                    window.alert(d.warning);
                                } catch (e) {}
                            }
                            window.location.reload();
                            return;
                        }
                        setBranchDeleteLoading(false);
                        btn.disabled = false;
                        alert((d && d.message) ? d.message : 'Could not delete branch');
                    })
                    .catch(function (err) {
                        setBranchDeleteLoading(false);
                        btn.disabled = false;
                        var msg = (err && err.message) ? String(err.message) : 'Network error';
                        if (/timed?\s*out|504|502|gateway/i.test(msg)) {
                            msg = 'Delete timed out on the server. The branch may still be deleting — refresh the page in a minute and check before trying again.';
                        }
                        alert(msg);
                    });
            });
        });
        document.querySelectorAll('.branch-restore-db-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var id = btn.getAttribute('data-branch-id');
                var dbn = btn.getAttribute('data-db-name') || '';
                if (!id) {
                    return;
                }
                var w =
                    'Restore will create missing tables in «' + dbn + '» from the main database and copy masters, regions, voucher & invoice settings, metal rates, and bill series (same as new branch setup). If tables already exist, nothing is changed.\n\nContinue?';
                if (!window.confirm(w)) {
                    return;
                }
                var pw = window.prompt('Master password (same as when adding a branch):', '');
                if (pw === null || String(pw).trim() === '') {
                    return;
                }
                btn.disabled = true;
                var body = 'id=' + encodeURIComponent(id) + '&password=' + encodeURIComponent(String(pw).trim());
                fetch('api/repair_branch_database.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body,
                    credentials: 'same-origin'
                })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (d) {
                        btn.disabled = false;
                        if (d && d.ok) {
                            try {
                                window.alert(d.message || 'Done.');
                            } catch (e1) {}
                            if (d.provisioned) {
                                window.location.reload();
                            }
                            return;
                        }
                        alert((d && d.message) ? d.message : 'Could not restore tables');
                    })
                    .catch(function () {
                        btn.disabled = false;
                        alert('Network error');
                    });
            });
        });
    })();
    </script>
    <?php endif; ?>
</body>
</html>
