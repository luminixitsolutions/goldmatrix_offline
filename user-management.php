<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session_login_type.php';
require_once __DIR__ . '/includes/user_management_schema.php';
require_once __DIR__ . '/includes/branch_working_context.php';
require_once __DIR__ . '/includes/roles_schema.php';
require_once __DIR__ . '/includes/auragold_employee_management_schema.php';

if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}
if (!auragold_session_is_admin_login_type()) {
    header('Location: dashboard.php');
    exit;
}

// tbl_users lives on the operational connection ($conn), same as login — not the registry master alone.
auragold_ensure_user_management_columns($conn);
auragold_ensure_roles_table($conn_master);
$um_employee_branch_id = auragold_em_resolve_branch_id();
auragold_em_ensure_tables($conn);
auragold_em_seed_defaults($conn, $um_employee_branch_id);
$um_departments = auragold_em_get_master_list($conn, 'tbl_employee_departments', $um_employee_branch_id);
$um_designations = auragold_em_get_master_list($conn, 'tbl_employee_designations', $um_employee_branch_id);

/** Active roles from Role Management (tbl_roles) for the user form dropdown. */
$um_roles = getListMaster('SELECT id, role_name FROM tbl_roles WHERE is_active = 1 ORDER BY role_name ASC');
if (!is_array($um_roles)) {
    $um_roles = [];
}
if ($um_roles === []) {
    // Fallback if roles table is empty
    $um_roles = [
        ['id' => 0, 'role_name' => 'Admin'],
        ['id' => 0, 'role_name' => 'Branch Manager'],
        ['id' => 0, 'role_name' => 'Sales Person'],
    ];
}

$um_users_table = 'tbl_users';
$um_connection_db = '';
if (isset($conn) && $conn instanceof mysqli) {
    $dbres = @mysqli_query($conn, 'SELECT DATABASE() AS d');
    if ($dbres && ($drow = mysqli_fetch_assoc($dbres))) {
        $um_connection_db = trim((string) ($drow['d'] ?? ''));
        mysqli_free_result($dbres);
    }
}
if ($um_connection_db === '' && defined('AURAGOLD_REGISTRY_DB')) {
    $um_connection_db = (string) AURAGOLD_REGISTRY_DB;
}

$um_mysql_connection_label = !empty($_SESSION['working_db']) && is_array($_SESSION['working_db'])
    ? 'working session (same as dashboard — branch app DB)'
    : 'registry / main (operational)';

$um_scope = auragold_um_user_management_scope_sub_branch($conn_master);
$users    = getList(
    'SELECT * FROM ' . $um_users_table . ' WHERE 1=1' . auragold_um_sql_users_scope_and($conn_master) . ' ORDER BY id ASC'
);

// Branch picker: main branch with nested sub-branches (scoped like branches.php).
$um_branch_groups = auragold_um_branch_picker_groups($conn, $conn_master);

$page_title = 'User Management — ' . auragold_app_name();
$auragold_admin_tab = 'users';
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include __DIR__ . '/header-script.php'; ?>
    <style>
        :root {
            --um-purple: #11294b;
            --um-purple-dark: #0d1f38;
            --um-purple-soft: #e9ecef;
            --um-border: #e2e8f0;
            --um-text: #334155;
            --um-muted: #64748b;
        }
        .um-page {
            padding: 20px 24px 100px;
            max-width: 1480px;
            margin: 0 auto;
        }
        .um-top-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .um-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            flex: 1;
            min-width: 0;
        }
        .um-tab {
            border: none;
            background: #fff;
            color: var(--um-text);
            font-size: 13px;
            font-weight: 600;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            border: 1px solid var(--um-border);
        }
        .um-tabs a.um-tab {
            text-decoration: none;
            color: var(--um-text);
            display: inline-block;
            box-sizing: border-box;
        }
        .um-tabs a.um-tab.active {
            color: #fff;
        }
        .um-tab:hover:not(:disabled) {
            background: #f8fafc;
        }
        .um-tab.active {
            background: var(--um-purple);
            color: #fff;
            border-color: var(--um-purple);
        }
        .um-tab:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }
        .um-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        .um-icon-btn {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 1px solid var(--um-border);
            background: #fff;
            color: var(--um-text);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
        }
        .um-icon-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
        .um-icon-btn .um-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            min-width: 16px;
            height: 16px;
            padding: 0 4px;
            font-size: 10px;
            font-weight: 700;
            line-height: 16px;
            text-align: center;
            background: #ef4444;
            color: #fff;
            border-radius: 999px;
        }
        .um-btn-user {
            border: 2px solid var(--um-purple);
            background: #fff;
            color: var(--um-purple);
            font-weight: 600;
            font-size: 13px;
            padding: 8px 18px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
        }
        .um-btn-user:hover {
            background: var(--um-purple-soft);
        }
        .um-card {
            background: #fff;
            border: 1px solid var(--um-border);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }
        .um-table-wrap {
            overflow-x: auto;
        }
        .um-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .um-table thead th {
            text-align: left;
            padding: 14px 16px;
            font-weight: 600;
            color: var(--um-text);
            background: #fafafa;
            border-bottom: 1px solid var(--um-border);
            white-space: nowrap;
        }
        .um-table thead th .um-sort {
            display: inline-flex;
            flex-direction: column;
            margin-left: 4px;
            vertical-align: middle;
            line-height: 0.65;
            color: var(--um-muted);
            font-size: 10px;
        }
        .um-table thead th .um-sort i { font-size: 11px; }
        .um-table thead th.um-th-actions {
            width: 48px;
            text-align: right;
        }
        .um-table tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--um-border);
            color: var(--um-text);
            vertical-align: middle;
        }
        .um-table tbody tr:nth-child(even) td {
            background: #fafafa;
        }
        .um-table tbody tr:nth-child(odd) td {
            background: #fff;
        }
        .um-empty-cell {
            padding: 48px 24px !important;
            text-align: center;
            color: var(--um-muted);
            font-size: 14px;
            border-bottom: none !important;
            background: #fff !important;
        }
        .um-status-cell {
            text-align: center;
        }
        .um-status-cell input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--um-purple);
            cursor: pointer;
        }
        .um-actions-cell {
            text-align: center;
        }
        .um-btn-delete {
            border: none;
            background: transparent;
            color: #dc2626;
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            line-height: 1;
        }
        .um-btn-delete:hover {
            background: #fef2f2;
        }
        .um-btn-delete:disabled {
            opacity: 0.35;
            cursor: not-allowed;
        }
        .um-btn-edit {
            border: none;
            background: transparent;
            color: #11294b;
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            line-height: 1;
            margin-right: 4px;
        }
        .um-btn-edit:hover {
            background: #e9ecef;
        }
        .um-footer-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            border-top: 1px solid var(--um-border);
            background: #fafafa;
            font-size: 13px;
            color: var(--um-muted);
        }
        .um-footer-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .um-footer-bar select {
            border: 1px solid var(--um-border);
            border-radius: 6px;
            padding: 6px 10px;
            font-size: 12px;
            background: #fff;
            color: var(--um-text);
        }
        .um-pager {
            display: flex;
            gap: 4px;
            align-items: center;
        }
        .um-pager button {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid var(--um-border);
            background: #fff;
            color: var(--um-text);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        .um-pager button:hover:not(:disabled) {
            background: #f1f5f9;
        }
        .um-pager button:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .um-pager .um-page-num {
            min-width: 32px;
            height: 32px;
            border-radius: 999px;
            border: none;
            background: var(--um-purple);
            color: #fff;
            font-weight: 600;
            font-size: 12px;
            cursor: default;
        }
        .um-fab {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 100;
            border: 2px solid var(--um-purple);
            background: #fff;
            color: var(--um-purple);
            font-weight: 600;
            font-size: 13px;
            padding: 10px 18px;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(107, 70, 193, 0.2);
            cursor: pointer;
        }
        .um-fab:hover {
            background: var(--um-purple-soft);
        }
        .um-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
            background: rgba(15, 23, 42, 0.45);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .um-modal-backdrop.open {
            display: flex;
        }
        .um-modal {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.18);
            width: 100%;
            max-width: 520px;
            max-height: 92vh;
            overflow: auto;
        }
        .um-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 18px;
            border-bottom: 1px solid var(--um-border);
        }
        .um-modal-header h2 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: #1e293b;
        }
        .um-modal-close {
            border: none;
            background: transparent;
            padding: 6px;
            cursor: pointer;
            color: var(--um-muted);
            line-height: 1;
        }
        .um-modal-close:hover {
            color: #0f172a;
        }
        .um-modal-body {
            padding: 18px;
        }
        .um-form-group {
            margin-bottom: 14px;
        }
        .um-form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
        }
        .um-req {
            color: #dc2626;
        }
        .um-form-group input[type="text"],
        .um-form-group input[type="email"],
        .um-form-group input[type="password"],
        .um-form-group input[type="number"],
        .um-form-group select {
            width: 100%;
            border: 1px solid var(--um-border);
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
            color: var(--um-text);
            background: #fff;
            box-sizing: border-box;
            -moz-appearance: textfield;
            appearance: textfield;
        }
        .um-form-group input[type="number"]::-webkit-outer-spin-button,
        .um-form-group input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .um-form-group input:focus,
        .um-form-group select:focus {
            outline: none;
            border-color: var(--um-purple);
            box-shadow: 0 0 0 3px var(--um-purple-soft);
        }
        .um-form-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .um-form-row .um-form-group {
            flex: 1;
            min-width: 0;
        }
        .um-phone-row {
            display: flex;
            gap: 8px;
            align-items: stretch;
        }
        .um-phone-row select {
            width: 88px;
            flex-shrink: 0;
        }
        .um-phone-row input {
            flex: 1;
            min-width: 0;
        }
        .um-role-row {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }
        .um-role-row select {
            flex: 1;
            min-width: 200px;
        }
        .um-check-inline {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--um-text);
            white-space: nowrap;
        }
        .um-check-inline input {
            width: 18px;
            height: 18px;
            accent-color: var(--um-purple);
        }
        .um-branch-checkboxes {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 180px;
            overflow-y: auto;
            padding: 10px 12px;
            border: 1px solid var(--um-border);
            border-radius: 8px;
            background: #fff;
        }
        .um-branch-label {
            font-weight: 500;
        }
        .um-branch-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .um-branch-group + .um-branch-group {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--um-border);
        }
        .um-branch-main {
            font-weight: 600;
            color: var(--um-text);
        }
        .um-branch-subs {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-left: 24px;
            padding-left: 12px;
            border-left: 2px solid var(--um-purple-soft);
        }
        .um-branch-sub {
            font-weight: 500;
            color: var(--um-muted);
        }
        .um-pw-wrap {
            position: relative;
        }
        .um-pw-wrap input {
            padding-right: 40px;
        }
        /* Stop Chrome/Safari from masking “password”-named fields when showing plain text in edit mode */
        input.um-pw-plain {
            -webkit-text-security: none !important;
        }
        .um-pw-toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: var(--um-muted);
            cursor: pointer;
            padding: 4px;
        }
        .um-pw-toggle:hover {
            color: var(--um-text);
        }
        .um-gen-pw {
            font-size: 13px;
            font-weight: 600;
            color: #2563eb;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            margin-top: 4px;
        }
        .um-gen-pw:hover {
            text-decoration: underline;
        }
        .um-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 14px 18px;
            border-top: 1px solid var(--um-border);
            background: #fafafa;
        }
        .um-btn-close {
            border: 2px solid var(--um-purple);
            background: #fff;
            color: var(--um-purple);
            font-weight: 600;
            font-size: 13px;
            padding: 8px 18px;
            border-radius: 8px;
            cursor: pointer;
        }
        .um-btn-close:hover {
            background: var(--um-purple-soft);
        }
        .um-btn-save {
            border: none;
            background: var(--um-purple);
            color: #fff;
            font-weight: 600;
            font-size: 13px;
            padding: 8px 18px;
            border-radius: 8px;
            cursor: pointer;
        }
        .um-btn-save:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }
        .um-form-error {
            color: #dc2626;
            font-size: 13px;
            margin-top: 8px;
            display: none;
        }
        .um-form-error.show {
            display: block;
        }
        .um-data-source {
            width: 100%;
            margin: 0 0 14px;
            padding: 10px 14px;
            font-size: 12px;
            color: var(--um-muted);
            background: #f8fafc;
            border: 1px solid var(--um-border);
            border-radius: 8px;
            line-height: 1.5;
        }
        .um-data-source code {
            font-size: 12px;
            background: #eef2f7;
            padding: 2px 6px;
            border-radius: 4px;
            color: #0f172a;
        }
        .um-data-source strong {
            color: var(--um-text);
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="layout-content">
        <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
            <div class="um-page">
                <!-- <div class="um-data-source" role="status" title="tbl_users uses the same operational MySQL connection as the rest of the app: your branch app database after branch login, otherwise the main registry database.">
                    <strong>User records source:</strong>
                    database <code><?php echo htmlspecialchars($um_connection_db !== '' ? $um_connection_db : '(unknown)', ENT_QUOTES, 'UTF-8'); ?></code>
                    · table <code><?php echo htmlspecialchars($um_users_table, ENT_QUOTES, 'UTF-8'); ?></code>
                    · <span>MySQL connection: <strong><?php echo htmlspecialchars($um_mysql_connection_label, ENT_QUOTES, 'UTF-8'); ?></strong></span>
                </div> -->
                <div class="um-top-bar">
                    <?php include __DIR__ . '/includes/administration_tabs.php'; ?>
                    <?php if (!empty($um_scope)): ?>
                    <p style="width:100%;margin:0 0 10px;font-size:13px;color:var(--um-muted);">
                        Showing users assigned to <strong style="color:var(--um-text);"><?php echo htmlspecialchars($um_scope['name'], ENT_QUOTES, 'UTF-8'); ?></strong> only (sub-branch view).
                    </p>
                    <?php endif; ?>
                    <div class="um-actions">
                        <button type="button" class="um-icon-btn" id="umFilterBtn" title="Filters">
                            <i class="feather icon-filter"></i>
                            <span class="um-badge">1</span>
                        </button>
                        <button type="button" class="um-icon-btn" id="umRefreshBtn" title="Refresh" onclick="location.reload()">
                            <i class="feather icon-refresh-cw"></i>
                        </button>
                        <button type="button" class="um-btn-user" id="umAddUserBtn">+ User</button>
                    </div>
                </div>

                <div class="um-card">
                    <div class="um-table-wrap">
                        <table class="um-table">
                            <thead>
                                <tr>
                                    <th>Name <span class="um-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                                    <th>Branch</th>
                                    <th>Mail ID <span class="um-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                                    <th>Contact <span class="um-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                                    <th>User Role <span class="um-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                                    <th>Monthly Salary</th>
                                    <th class="um-status-cell">Status</th>
                                    <th class="um-actions-cell">Actions</th>
                                    <th class="um-th-actions"><i class="feather icon-settings" style="color:#11294b;" title="Column settings"></i></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $n = is_array($users) ? count($users) : 0;
                                if ($n === 0):
                                ?>
                                <tr>
                                    <td colspan="9" class="um-empty-cell">No Rows To Show</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $u): ?>
                                        <?php
                                        $fn   = trim((string) ($u['Fname'] ?? ''));
                                        $ln   = trim((string) ($u['Lname'] ?? ''));
                                        $disp = trim($fn . ' ' . $ln);
                                        if ($disp === '') {
                                            $disp = trim((string) ($u['Username'] ?? ''));
                                        }
                                        $email = trim((string) ($u['EmailId'] ?? ''));
                                        $phone = trim((string) ($u['Phone'] ?? ''));
                                        $role  = trim((string) ($u['user_role'] ?? ''));
                                        if ($role === '') {
                                            $role = 'Admin';
                                        }
                                        $br = auragold_um_display_branch_names_for_user_row($conn_master, $u);
                                        $ub_ids = auragold_um_parse_branch_ids_string((string) ($u['user_branch_ids'] ?? ''));
                                        $ub_ids_str = !empty($ub_ids) ? implode(',', $ub_ids) : '';
                                        $st = (string) ($u['Status'] ?? '0');
                                        $ok = ($st === '1' || strcasecmp($st, 'active') === 0);
                                        $uid = (int) ($u['id'] ?? 0);
                                        $src = isset($_SESSION['login_source']) ? (string) $_SESSION['login_source'] : '';
                                        $sid = (int) ($_SESSION['user_id'] ?? 0);
                                        $is_self = ($src === 'user' && $sid === $uid);
                                        $uname = trim((string) ($u['Username'] ?? ''));
                                        $pw_raw = '';
                                        foreach (['Password', 'password'] as $_pwk) {
                                            if (isset($u[$_pwk]) && (string) $u[$_pwk] !== '') {
                                                $pw_raw = (string) $u[$_pwk];
                                                break;
                                            }
                                        }
                                        $pw_b64 = base64_encode($pw_raw);
                                        $salary = (float) ($u['monthly_salary'] ?? 0);
                                        ?>
                                        <tr data-user-id="<?php echo $uid; ?>">
                                            <td><?php echo htmlspecialchars($disp, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($br, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($email !== '' ? $email : '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($phone !== '' ? $phone : '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td style="text-align:right;font-variant-numeric:tabular-nums;"><?php echo htmlspecialchars(number_format($salary, 2), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="um-status-cell">
                                                <input type="checkbox" class="um-status-cb" data-id="<?php echo $uid; ?>" <?php echo $ok ? 'checked' : ''; ?> <?php echo $is_self ? 'disabled title="Cannot deactivate own account"' : ''; ?>>
                                            </td>
                                            <td class="um-actions-cell">
                                                <button type="button" class="um-btn-edit um-edit-user" title="Edit user"
                                                    data-id="<?php echo $uid; ?>"
                                                    data-full-name="<?php echo htmlspecialchars($disp, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-mail="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-phone="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-role="<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-branches="<?php echo htmlspecialchars($br, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-branch-ids="<?php echo htmlspecialchars($ub_ids_str, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-username="<?php echo htmlspecialchars($uname, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-active="<?php echo $ok ? '1' : '0'; ?>"
                                                    data-monthly-salary="<?php echo htmlspecialchars(number_format($salary, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-department-id="<?php echo (int) ($u['department_id'] ?? 0); ?>"
                                                    data-designation-id="<?php echo (int) ($u['designation_id'] ?? 0); ?>"
                                                    data-password-b64="<?php echo htmlspecialchars($pw_b64, ENT_QUOTES, 'UTF-8'); ?>"
                                                    aria-label="Edit"><i class="feather icon-edit-2"></i></button>
                                                <a class="um-btn-edit" style="text-decoration:none;margin-right:2px;" href="permission-management.php?user_id=<?php echo $uid; ?>" title="Branch permissions"><i class="feather icon-shield"></i></a>
                                                <button type="button" class="um-btn-delete um-delete-user" data-id="<?php echo $uid; ?>" <?php echo $is_self ? 'disabled title="Cannot delete own account"' : ''; ?> aria-label="Delete"><i class="feather icon-trash-2"></i></button>
                                            </td>
                                            <td></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="um-footer-bar">
                        <?php if ($n === 0): ?>
                        <span>Showing 0 to 0 of 0 entries</span>
                        <?php else: ?>
                        <span>Showing 1 to <?php echo (int) $n; ?> of <?php echo (int) $n; ?> entries</span>
                        <?php endif; ?>
                        <div class="um-footer-right">
                            <select aria-label="Page size" disabled>
                                <option>Show All Items</option>
                            </select>
                            <div class="um-pager">
                                <button type="button" disabled title="First" aria-label="First page"><i class="feather icon-chevrons-left"></i></button>
                                <button type="button" disabled title="Previous" aria-label="Previous page"><i class="feather icon-chevron-left"></i></button>
                                <button type="button" class="um-page-num" disabled>1</button>
                                <button type="button" disabled title="Next" aria-label="Next page"><i class="feather icon-chevron-right"></i></button>
                                <button type="button" disabled title="Last" aria-label="Last page"><i class="feather icon-chevrons-right"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="um-fab" id="umFabTaskBtn">New Task / Event</button>

                <div id="umAddModal" class="um-modal-backdrop" aria-hidden="true">
                    <div class="um-modal" role="dialog" aria-modal="true" aria-labelledby="umAddTitle">
                        <div class="um-modal-header">
                            <h2 id="umAddTitle">Add User</h2>
                            <button type="button" class="um-modal-close" id="umModalX" aria-label="Close"><i class="feather icon-x"></i></button>
                        </div>
                        <form id="umAddForm" novalidate>
                            <input type="hidden" id="umEditId" name="id" value="0">
                            <div class="um-modal-body">
                                <div class="um-form-group">
                                    <label for="umFullName">Full Name <span class="um-req">*</span></label>
                                    <input type="text" id="umFullName" name="full_name" required maxlength="200" autocomplete="name">
                                </div>
                                <div class="um-form-group">
                                    <label for="umMail">Mail ID <span class="um-req">*</span></label>
                                    <input type="email" id="umMail" name="mail_id" required maxlength="100" autocomplete="email">
                                </div>
                                <div class="um-form-group">
                                    <label>Contact <span class="um-req">*</span></label>
                                    <div class="um-phone-row">
                                        <select id="umPhoneCc" name="phone_cc" aria-label="Country code">
                                            <option value="+91">+91</option>
                                            <option value="+1">+1</option>
                                            <option value="+44">+44</option>
                                            <option value="+971">+971</option>
                                        </select>
                                        <input type="text" id="umPhone" name="phone" required maxlength="20" inputmode="tel" autocomplete="tel" placeholder="Phone number">
                                    </div>
                                </div>
                                <!-- Role field hidden from UI; kept in the form so saves keep working and
                                     editing an existing user preserves their current role. -->
                                <div class="um-form-group" style="display:none;" aria-hidden="true">
                                    <select id="umRole" name="user_role">
                                        <?php foreach ($um_roles as $um_role_row):
                                            $um_role_name = trim((string) ($um_role_row['role_name'] ?? ''));
                                            if ($um_role_name === '') {
                                                continue;
                                            }
                                        ?>
                                        <option value="<?php echo htmlspecialchars($um_role_name, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($um_role_name, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="um-form-group">
                                    <label class="um-check-inline">
                                        <input type="checkbox" id="umActive" name="active" checked>
                                        Active
                                    </label>
                                </div>
                                <div class="um-form-group">
                                    <label for="umDepartment">Department</label>
                                    <select id="umDepartment" name="department_id">
                                        <option value="0">— Select department —</option>
                                        <?php foreach ($um_departments as $um_department): ?>
                                        <option value="<?php echo (int) ($um_department['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($um_department['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="um-form-group">
                                    <label for="umDesignation">Designation</label>
                                    <select id="umDesignation" name="designation_id">
                                        <option value="0">— Select designation —</option>
                                        <?php foreach ($um_designations as $um_designation): ?>
                                        <option value="<?php echo (int) ($um_designation['id'] ?? 0); ?>"
                                            data-department-id="<?php echo (int) ($um_designation['department_id'] ?? 0); ?>">
                                            <?php echo htmlspecialchars((string) ($um_designation['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="um-form-group">
                                    <label id="umBranchFieldLabel" style="display:block;font-weight:600;margin-bottom:8px;color:var(--um-text);">Branch <span class="um-req">*</span></label>
                                    <div class="um-branch-checkboxes" id="umBranchBoxes" role="group" aria-labelledby="umBranchFieldLabel">
                                        <?php foreach ($um_branch_groups as $um_branch_group): ?>
                                            <?php
                                            $um_main = $um_branch_group['main'] ?? [];
                                            $um_subs = $um_branch_group['subs'] ?? [];
                                            $um_main_id = (int) ($um_main['id'] ?? 0);
                                            $um_main_name = trim((string) ($um_main['name'] ?? ''));
                                            if ($um_main_id <= 0 || $um_main_name === '') {
                                                continue;
                                            }
                                            ?>
                                            <div class="um-branch-group">
                                                <label class="um-check-inline um-branch-label um-branch-main">
                                                    <input type="checkbox" class="um-branch-cb" name="branches[]" value="<?php echo $um_main_id; ?>" data-branch-name="<?php echo htmlspecialchars($um_main_name, ENT_QUOTES, 'UTF-8'); ?>" data-branch-type="main">
                                                    <?php echo htmlspecialchars($um_main_name, ENT_QUOTES, 'UTF-8'); ?>
                                                </label>
                                                <?php if (!empty($um_subs)): ?>
                                                    <div class="um-branch-subs">
                                                        <?php foreach ($um_subs as $um_sub): ?>
                                                            <?php
                                                            $um_sub_id = (int) ($um_sub['id'] ?? 0);
                                                            $um_sub_name = trim((string) ($um_sub['name'] ?? ''));
                                                            if ($um_sub_id <= 0 || $um_sub_name === '') {
                                                                continue;
                                                            }
                                                            ?>
                                                            <label class="um-check-inline um-branch-label um-branch-sub">
                                                                <input type="checkbox" class="um-branch-cb" name="branches[]" value="<?php echo $um_sub_id; ?>" data-branch-name="<?php echo htmlspecialchars($um_sub_name, ENT_QUOTES, 'UTF-8'); ?>" data-branch-type="sub">
                                                                <?php echo htmlspecialchars($um_sub_name, ENT_QUOTES, 'UTF-8'); ?>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <p style="font-size:12px;color:var(--um-muted);margin:8px 0 0;">Select the main branch and/or its sub-branches.</p>
                                </div>
                                <div class="um-form-group">
                                    <label for="umUsername">User Name <span class="um-req">*</span></label>
                                    <input type="text" id="umUsername" name="username" required maxlength="100" autocomplete="username" placeholder="Login username">
                                </div>
                                <div class="um-form-group">
                                    <label for="umMonthlySalary">Monthly Salary</label>
                                    <input type="number" id="umMonthlySalary" name="monthly_salary" step="0.01" min="0" value="0" placeholder="0.00">
                                    <p style="font-size:12px;color:var(--um-muted);margin:6px 0 0;">Used as basic salary in Employee Salary / Payroll.</p>
                                </div>
                                <div class="um-form-group">
                                    <label for="umPassword">Password <span class="um-req" id="umPwStar1">*</span></label>
                                    <div class="um-pw-wrap">
                                        <input type="password" id="umPassword" name="password" required maxlength="50" autocomplete="new-password">
                                        <button type="button" class="um-pw-toggle" data-pw="umPassword" aria-label="Show password"><i class="feather icon-eye"></i></button>
                                    </div>
                                </div>
                                <div class="um-form-group">
                                    <label for="umPassword2">Confirm Password <span class="um-req" id="umPwStar2">*</span></label>
                                    <div class="um-pw-wrap">
                                        <input type="password" id="umPassword2" name="confirm_password" required maxlength="50" autocomplete="new-password">
                                        <button type="button" class="um-pw-toggle" data-pw="umPassword2" aria-label="Show password"><i class="feather icon-eye"></i></button>
                                    </div>
                                    <button type="button" class="um-gen-pw" id="umGenPw">Generate Random Password</button>
                                </div>
                                <div class="um-form-error" id="umFormError"></div>
                            </div>
                            <div class="um-modal-footer">
                                <button type="button" class="um-btn-close" id="umModalClose">Close</button>
                                <button type="submit" class="um-btn-save" id="umModalSave">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var modal = document.getElementById('umAddModal');
        var openBtn = document.getElementById('umAddUserBtn');
        var closeBtn = document.getElementById('umModalClose');
        var xBtn = document.getElementById('umModalX');
        var form = document.getElementById('umAddForm');
        var errEl = document.getElementById('umFormError');
        var departmentSelect = document.getElementById('umDepartment');
        var designationSelect = document.getElementById('umDesignation');

        function filterDesignations() {
            var departmentId = parseInt(departmentSelect.value, 10) || 0;
            Array.prototype.forEach.call(designationSelect.options, function (option, index) {
                if (index === 0) {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }
                var belongsTo = parseInt(option.getAttribute('data-department-id'), 10) || 0;
                var show = departmentId > 0 && belongsTo === departmentId;
                option.hidden = !show;
                option.disabled = !show;
            });
            var selected = designationSelect.options[designationSelect.selectedIndex];
            if (selected && selected.disabled) {
                designationSelect.value = '0';
            }
        }
        departmentSelect.addEventListener('change', function () {
            designationSelect.value = '0';
            filterDesignations();
        });
        filterDesignations();

        function parsePhone(s) {
            s = (s || '').trim();
            if (!s) {
                return { cc: '+91', num: '' };
            }
            var parts = s.split(/\s+/);
            if (parts[0] && parts[0].charAt(0) === '+') {
                return { cc: parts[0], num: parts.slice(1).join('').replace(/\s/g, '') };
            }
            return { cc: '+91', num: s.replace(/\s/g, '') };
        }

        function syncPwToggleIcons() {
            document.querySelectorAll('.um-pw-toggle').forEach(function (btn) {
                var id = btn.getAttribute('data-pw');
                var inp = document.getElementById(id);
                var ic = btn.querySelector('i');
                if (!inp || !ic) return;
                ic.className = (inp.type === 'password') ? 'feather icon-eye' : 'feather icon-eye-off';
            });
        }

        /** Edit user: show real password — browsers mask name="password" unless renamed + type text + autocomplete off */
        function applyEditPasswordPlain(pw) {
            var p1 = document.getElementById('umPassword');
            var p2 = document.getElementById('umPassword2');
            p1.classList.add('um-pw-plain');
            p2.classList.add('um-pw-plain');
            p1.name = '_auragold_pw_edit_1';
            p2.name = '_auragold_pw_edit_2';
            p1.setAttribute('autocomplete', 'off');
            p2.setAttribute('autocomplete', 'off');
            p1.setAttribute('spellcheck', 'false');
            p2.setAttribute('spellcheck', 'false');
            p1.value = pw;
            p2.value = pw;
            p1.type = 'text';
            p2.type = 'text';
            requestAnimationFrame(function () {
                p1.type = 'text';
                p2.type = 'text';
                syncPwToggleIcons();
            });
        }

        function setPasswordMode(edit) {
            var s1 = document.getElementById('umPwStar1');
            var s2 = document.getElementById('umPwStar2');
            var p1 = document.getElementById('umPassword');
            var p2 = document.getElementById('umPassword2');
            if (edit) {
                if (s1) {
                    s1.style.display = 'none';
                }
                if (s2) {
                    s2.style.display = 'none';
                }
                p1.removeAttribute('required');
                p2.removeAttribute('required');
                p1.value = '';
                p2.value = '';
                p1.placeholder = '';
                p2.placeholder = '';
            } else {
                if (s1) {
                    s1.style.display = 'inline';
                }
                if (s2) {
                    s2.style.display = 'inline';
                }
                p1.setAttribute('required', 'required');
                p2.setAttribute('required', 'required');
                p1.placeholder = '';
                p2.placeholder = '';
                p1.classList.remove('um-pw-plain');
                p2.classList.remove('um-pw-plain');
                p1.name = 'password';
                p2.name = 'confirm_password';
                p1.setAttribute('autocomplete', 'new-password');
                p2.setAttribute('autocomplete', 'new-password');
                p1.type = 'password';
                p2.type = 'password';
                syncPwToggleIcons();
            }
        }

        function openModalAdd() {
            form.reset();
            document.getElementById('umEditId').value = '0';
            document.getElementById('umAddTitle').textContent = 'Add User';
            document.getElementById('umActive').checked = true;
            document.getElementById('umMonthlySalary').value = '0';
            departmentSelect.value = '0';
            designationSelect.value = '0';
            filterDesignations();
            setPasswordMode(false);
            errEl.style.display = 'none';
            errEl.textContent = '';
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            document.getElementById('umFullName').focus();
        }

        function openModalEdit(btn) {
            document.getElementById('umEditId').value = btn.getAttribute('data-id') || '0';
            document.getElementById('umAddTitle').textContent = 'Edit User';
            setPasswordMode(true);
            errEl.style.display = 'none';
            errEl.textContent = '';
            document.getElementById('umFullName').value = btn.getAttribute('data-full-name') || '';
            document.getElementById('umMail').value = btn.getAttribute('data-mail') || '';
            var p = parsePhone(btn.getAttribute('data-phone') || '');
            var ccSel = document.getElementById('umPhoneCc');
            var found = false;
            var k;
            for (k = 0; k < ccSel.options.length; k++) {
                if (ccSel.options[k].value === p.cc) {
                    found = true;
                    break;
                }
            }
            if (!found && p.cc) {
                var opt = document.createElement('option');
                opt.value = p.cc;
                opt.textContent = p.cc;
                ccSel.appendChild(opt);
            }
            ccSel.value = p.cc;
            document.getElementById('umPhone').value = p.num;
            var roleSel = document.getElementById('umRole');
            var roleVal = btn.getAttribute('data-role') || 'Admin';
            var roleFound = false;
            var ri;
            for (ri = 0; ri < roleSel.options.length; ri++) {
                if (roleSel.options[ri].value === roleVal) {
                    roleFound = true;
                    break;
                }
            }
            if (!roleFound && roleVal) {
                var roleOpt = document.createElement('option');
                roleOpt.value = roleVal;
                roleOpt.textContent = roleVal;
                roleSel.appendChild(roleOpt);
            }
            roleSel.value = roleVal;
            document.getElementById('umActive').checked = btn.getAttribute('data-active') === '1';
            var idsStr = btn.getAttribute('data-branch-ids') || '';
            var idParts = idsStr.split(',').map(function (x) {
                return parseInt(x.trim(), 10);
            }).filter(function (n) {
                return n > 0;
            });
            var brStr = btn.getAttribute('data-branches') || '';
            var brParts = brStr.split(',').map(function (x) {
                return x.trim();
            }).filter(Boolean);
            document.querySelectorAll('.um-branch-cb').forEach(function (cb) {
                var vid = parseInt(cb.value, 10);
                if (idParts.length) {
                    cb.checked = idParts.indexOf(vid) !== -1;
                } else {
                    var nm = (cb.getAttribute('data-branch-name') || '').trim();
                    cb.checked = nm !== '' && brParts.indexOf(nm) !== -1;
                }
            });
            document.getElementById('umUsername').value = btn.getAttribute('data-username') || '';
            document.getElementById('umMonthlySalary').value = btn.getAttribute('data-monthly-salary') || '0';
            departmentSelect.value = btn.getAttribute('data-department-id') || '0';
            filterDesignations();
            designationSelect.value = btn.getAttribute('data-designation-id') || '0';
            var b64 = btn.getAttribute('data-password-b64');
            var pw = '';
            try {
                if (b64) {
                    pw = atob(b64.replace(/\s/g, ''));
                }
            } catch (e) {
                pw = '';
            }
            applyEditPasswordPlain(pw);
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            document.getElementById('umFullName').focus();
        }

        function closeModal() {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            errEl.style.display = 'none';
            errEl.textContent = '';
            form.reset();
            document.getElementById('umActive').checked = true;
            document.getElementById('umEditId').value = '0';
            document.getElementById('umAddTitle').textContent = 'Add User';
            setPasswordMode(false);
        }

        if (openBtn) openBtn.addEventListener('click', openModalAdd);
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (xBtn) xBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });

        document.querySelectorAll('.um-pw-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-pw');
                var inp = document.getElementById(id);
                if (!inp) return;
                if (inp.type === 'password') {
                    inp.type = 'text';
                    btn.querySelector('i').className = 'feather icon-eye-off';
                } else {
                    inp.type = 'password';
                    btn.querySelector('i').className = 'feather icon-eye';
                }
            });
        });

        document.getElementById('umGenPw').addEventListener('click', function () {
            var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
            var out = '';
            for (var i = 0; i < 12; i++) {
                out += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('umPassword').value = out;
            document.getElementById('umPassword2').value = out;
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            errEl.style.display = 'none';
            errEl.textContent = '';

            var branch_ids = [];
            document.querySelectorAll('.um-branch-cb:checked').forEach(function (cb) {
                var vid = parseInt(cb.value, 10);
                if (vid > 0) {
                    branch_ids.push(vid);
                }
            });
            var editId = parseInt(document.getElementById('umEditId').value, 10) || 0;
            var payload = {
                id: editId,
                full_name: document.getElementById('umFullName').value.trim(),
                mail_id: document.getElementById('umMail').value.trim(),
                phone_cc: document.getElementById('umPhoneCc').value,
                phone: document.getElementById('umPhone').value.trim(),
                user_role: document.getElementById('umRole').value,
                active: document.getElementById('umActive').checked,
                branch_ids: branch_ids,
                username: document.getElementById('umUsername').value.trim(),
                monthly_salary: document.getElementById('umMonthlySalary').value,
                department_id: parseInt(document.getElementById('umDepartment').value, 10) || 0,
                designation_id: parseInt(document.getElementById('umDesignation').value, 10) || 0,
                password: document.getElementById('umPassword').value,
                confirm_password: document.getElementById('umPassword2').value
            };

            var missing = [];
            if (!payload.full_name) missing.push('Full name');
            if (!payload.mail_id) missing.push('Mail ID');
            if (!payload.phone) missing.push('Phone number');
            if (!branch_ids.length) missing.push('At least one branch');
            if (!payload.username) missing.push('User name');
            if (!editId) {
                if (!payload.password) missing.push('Password');
                if (!payload.confirm_password) missing.push('Confirm password');
            }
            if (missing.length) {
                errEl.textContent = 'Please complete: ' + missing.join(', ') + '.';
                errEl.style.display = 'block';
                return;
            }

            var saveBtn = document.getElementById('umModalSave');
            saveBtn.disabled = true;

            fetch('ajax/user-save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'same-origin',
                cache: 'no-store'
            })
            .then(function (r) {
                return r.text().then(function (t) {
                    try { return t ? JSON.parse(t) : null; } catch (e) { return null; }
                });
            })
            .then(function (data) {
                saveBtn.disabled = false;
                if (data && data.ok) {
                    window.location.reload();
                } else {
                    errEl.textContent = (data && data.message) ? data.message : 'Save failed (invalid response).';
                    errEl.style.display = 'block';
                }
            })
            .catch(function () {
                saveBtn.disabled = false;
                errEl.textContent = 'Network error';
                errEl.style.display = 'block';
            });
        });

        document.querySelectorAll('.um-status-cb').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var id = parseInt(cb.getAttribute('data-id'), 10);
                var active = cb.checked;
                fetch('ajax/user-toggle-status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, active: active })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        cb.checked = !active;
                        alert(data.message || 'Could not update');
                    }
                })
                .catch(function () {
                    cb.checked = !active;
                    alert('Network error');
                });
            });
        });

        document.querySelectorAll('.um-edit-user').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openModalEdit(btn);
            });
        });

        document.querySelectorAll('.um-delete-user').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.disabled) return;
                if (!confirm('Delete this user?')) return;
                var id = parseInt(btn.getAttribute('data-id'), 10);
                fetch('ajax/user-delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        window.location.reload();
                    } else {
                        alert(data.message || 'Error');
                    }
                })
                .catch(function () { alert('Network error'); });
            });
        });

        var fab = document.getElementById('umFabTaskBtn');
        if (fab) {
            fab.addEventListener('click', function () {
                window.location.href = 'crm.php';
            });
        }
    })();
    </script>
</body>
</html>
