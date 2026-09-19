<?php
/**
 * Shared Administration master page (Roles-style UI).
 * Expects $employee_master_type: departments | designations.
 */
$employee_master_type = ($employee_master_type ?? '') === 'designations' ? 'designations' : 'departments';
$isDesignation = $employee_master_type === 'designations';
$table = $isDesignation ? 'tbl_employee_designations' : 'tbl_employee_departments';
$singular = $isDesignation ? 'Designation' : 'Department';
$plural = $isDesignation ? 'Designations' : 'Departments';
$branchId = auragold_em_resolve_branch_id();

require_once __DIR__ . '/user_management_schema.php';
auragold_ensure_user_management_columns($conn);
auragold_em_ensure_tables($conn);
auragold_em_seed_defaults($conn, $branchId);

$redirectUrl = $isDesignation ? 'designation-management.php' : 'department-management.php';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = trim((string) ($_POST['action'] ?? 'save'));
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'toggle') {
        header('Content-Type: application/json; charset=utf-8');
        $value = !empty($_POST['value']) ? 1 : 0;
        $ok = $id > 0 && @mysqli_query(
            $conn,
            "UPDATE `$table` SET status = $value WHERE id = $id AND branch_id = " . (int) $branchId . ' LIMIT 1'
        );
        echo json_encode(['ok' => (bool) $ok, 'message' => $ok ? 'Updated.' : 'Could not update.']);
        exit;
    }

    if ($action === 'delete') {
        header('Content-Type: application/json; charset=utf-8');
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'message' => 'Invalid ' . strtolower($singular) . '.']);
            exit;
        }
        if ($isDesignation) {
            $usedEmployees = (int) (getRecord(
                "SELECT COUNT(*) AS c FROM tbl_employees
                 WHERE designation_id = $id AND branch_id = " . (int) $branchId . ' AND record_status = 1'
            )['c'] ?? 0);
            $usedUsers = (int) (getRecord(
                "SELECT COUNT(*) AS c FROM tbl_users WHERE designation_id = $id"
            )['c'] ?? 0);
            $used = $usedEmployees + $usedUsers;
        } else {
            $usedDesignations = (int) (getRecord(
                "SELECT COUNT(*) AS c FROM tbl_employee_designations
                 WHERE department_id = $id AND branch_id = " . (int) $branchId
            )['c'] ?? 0);
            $usedEmployees = (int) (getRecord(
                "SELECT COUNT(*) AS c FROM tbl_employees
                 WHERE department_id = $id AND branch_id = " . (int) $branchId . ' AND record_status = 1'
            )['c'] ?? 0);
            $usedUsers = (int) (getRecord(
                "SELECT COUNT(*) AS c FROM tbl_users WHERE department_id = $id"
            )['c'] ?? 0);
            $used = $usedDesignations + $usedEmployees + $usedUsers;
        }
        if ($used > 0) {
            echo json_encode(['ok' => false, 'message' => $singular . ' is in use and cannot be deleted.']);
            exit;
        }
        $ok = @mysqli_query(
            $conn,
            "DELETE FROM `$table` WHERE id = $id AND branch_id = " . (int) $branchId . ' LIMIT 1'
        );
        echo json_encode(['ok' => (bool) $ok, 'message' => $ok ? 'Deleted.' : 'Could not delete ' . strtolower($singular) . '.']);
        exit;
    }

    // action=save (AJAX)
    header('Content-Type: application/json; charset=utf-8');
    $name = trim((string) ($_POST['name'] ?? ''));
    $status = !empty($_POST['status']) ? 1 : 0;
    $departmentId = $isDesignation ? (int) ($_POST['department_id'] ?? 0) : 0;
    if ($name === '') {
        echo json_encode(['ok' => false, 'message' => $singular . ' name is required.']);
        exit;
    }
    if ($isDesignation && $departmentId <= 0) {
        echo json_encode(['ok' => false, 'message' => 'Department is required.']);
        exit;
    }
    $nameEsc = auragold_em_esc($conn, $name);
    $scopeSql = $isDesignation ? ' AND department_id = ' . $departmentId : '';
    $duplicate = getRecord(
        "SELECT id FROM `$table`
         WHERE name = '$nameEsc'
           AND branch_id = " . (int) $branchId . "
           $scopeSql
           AND id <> $id
         LIMIT 1"
    );
    if ($duplicate) {
        echo json_encode(['ok' => false, 'message' => $singular . ' already exists.']);
        exit;
    }
    if ($id > 0) {
        $departmentSet = $isDesignation ? 'department_id = ' . $departmentId . ', ' : '';
        $ok = @mysqli_query(
            $conn,
            "UPDATE `$table`
             SET $departmentSet name = '$nameEsc', status = $status
             WHERE id = $id AND branch_id = " . (int) $branchId . ' LIMIT 1'
        );
    } elseif ($isDesignation) {
        $ok = @mysqli_query(
            $conn,
            "INSERT INTO `$table` (branch_id, department_id, name, status)
             VALUES (" . (int) $branchId . ", $departmentId, '$nameEsc', $status)"
        );
    } else {
        $ok = @mysqli_query(
            $conn,
            "INSERT INTO `$table` (branch_id, name, status)
             VALUES (" . (int) $branchId . ", '$nameEsc', $status)"
        );
    }
    echo json_encode(['ok' => (bool) $ok, 'message' => $ok ? $singular . ' saved.' : 'Could not save ' . strtolower($singular) . '.']);
    exit;
}

$departments = getList(
    'SELECT * FROM tbl_employee_departments
     WHERE branch_id = ' . (int) $branchId . '
     ORDER BY name ASC'
);
if ($isDesignation) {
    $rows = getList(
        'SELECT g.*, d.name AS department_name
         FROM tbl_employee_designations g
         LEFT JOIN tbl_employee_departments d ON d.id = g.department_id
         WHERE g.branch_id = ' . (int) $branchId . '
         ORDER BY d.name ASC, g.name ASC'
    );
} else {
    $rows = $departments;
}
$rows = is_array($rows) ? $rows : [];
$departments = is_array($departments) ? $departments : [];
$auragold_admin_tab = $isDesignation ? 'designations' : 'departments';
$page_title = $plural . ' — Administration — ' . auragold_app_name();
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include __DIR__ . '/../header-script.php'; ?>
    <style>
        :root {
            --um-purple: #11294b;
            --um-purple-dark: #0d1f38;
            --um-purple-soft: #e9ecef;
            --um-border: #e2e8f0;
            --um-text: #334155;
            --um-muted: #64748b;
        }
        .um-page { padding: 20px 24px 100px; max-width: 1480px; margin: 0 auto; }
        .um-top-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 16px; }
        .um-tabs { display: flex; flex-wrap: wrap; gap: 4px; flex: 1; min-width: 0; }
        .um-tab {
            border: none; background: #fff; color: var(--um-text); font-size: 13px; font-weight: 600;
            padding: 10px 16px; border-radius: 8px; cursor: pointer;
            transition: background 0.15s, color 0.15s; border: 1px solid var(--um-border);
        }
        .um-tabs a.um-tab { text-decoration: none; color: var(--um-text); display: inline-block; box-sizing: border-box; }
        .um-tabs a.um-tab.active { color: #fff; }
        .um-tab:hover:not(:disabled) { background: #f8fafc; }
        .um-tab.active { background: var(--um-purple); color: #fff; border-color: var(--um-purple); }
        .um-actions { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .um-icon-btn {
            position: relative; width: 40px; height: 40px; border-radius: 8px; border: 1px solid var(--um-border);
            background: #fff; color: var(--um-text); display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; transition: background 0.15s, border-color 0.15s;
        }
        .um-icon-btn:hover { background: #f8fafc; border-color: #cbd5e1; }
        .um-btn-user {
            border: 2px solid var(--um-purple); background: #fff; color: var(--um-purple); font-weight: 600;
            font-size: 13px; padding: 8px 18px; border-radius: 8px; cursor: pointer;
            transition: background 0.15s, color 0.15s;
        }
        .um-btn-user:hover { background: var(--um-purple-soft); }
        .um-card {
            background: #fff; border: 1px solid var(--um-border); border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04); overflow: hidden;
        }
        .um-table-wrap { overflow-x: auto; }
        .um-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .um-table thead th {
            text-align: left; padding: 14px 16px; font-weight: 600; color: var(--um-text);
            background: #fafafa; border-bottom: 1px solid var(--um-border); white-space: nowrap;
        }
        .um-table thead th .um-sort {
            display: inline-flex; flex-direction: column; margin-left: 4px; vertical-align: middle;
            line-height: 0.65; color: var(--um-muted); font-size: 10px;
        }
        .um-table thead th .um-sort i { font-size: 11px; }
        .um-table thead th.um-th-actions { width: 48px; text-align: right; }
        .um-table tbody td {
            padding: 12px 16px; border-bottom: 1px solid var(--um-border);
            color: var(--um-text); vertical-align: middle; white-space: nowrap;
        }
        .um-table.um-table-roles tbody tr:nth-child(even) td { background: #eff6ff; }
        .um-table.um-table-roles tbody tr:nth-child(odd) td { background: #fff; }
        .um-role-link {
            background: none; border: none; padding: 0; color: #2563eb; font-weight: 600;
            font-size: inherit; cursor: pointer; text-align: left; font-family: inherit;
        }
        .um-role-link:hover { text-decoration: underline; }
        .um-empty-cell {
            padding: 48px 24px !important; text-align: center; color: var(--um-muted);
            font-size: 14px; border-bottom: none !important; background: #fff !important;
        }
        .um-status-cell { text-align: center; }
        .um-status-cell input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--um-purple); cursor: pointer; }
        .um-actions-cell { text-align: center; }
        .um-btn-delete {
            border: none; background: transparent; color: #dc2626; cursor: pointer;
            padding: 6px; border-radius: 6px; line-height: 1;
        }
        .um-btn-delete:hover { background: #fef2f2; }
        .um-footer-bar {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
            gap: 12px; padding: 12px 16px; border-top: 1px solid var(--um-border);
            background: #fafafa; font-size: 13px; color: var(--um-muted);
        }
        .um-footer-right { display: flex; align-items: center; gap: 12px; }
        .um-footer-bar select {
            border: 1px solid var(--um-border); border-radius: 6px; padding: 6px 10px;
            font-size: 12px; background: #fff; color: var(--um-text);
        }
        .um-pager { display: flex; gap: 4px; align-items: center; }
        .um-pager button {
            width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--um-border);
            background: #fff; color: var(--um-text); cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; font-size: 12px;
        }
        .um-pager button:hover:not(:disabled) { background: #f1f5f9; }
        .um-pager button:disabled { opacity: 0.45; cursor: not-allowed; }
        .um-pager .um-page-num {
            min-width: 32px; height: 32px; border-radius: 999px; border: none;
            background: var(--um-purple); color: #fff; font-weight: 600; font-size: 12px; cursor: default;
        }
        .um-fab {
            position: fixed; right: 24px; bottom: 24px; z-index: 100;
            border: 2px solid var(--um-purple); background: #fff; color: var(--um-purple);
            font-weight: 600; font-size: 13px; padding: 10px 18px; border-radius: 10px;
            box-shadow: 0 4px 14px rgba(107, 70, 193, 0.2); cursor: pointer;
        }
        .um-fab:hover { background: var(--um-purple-soft); }
        .um-modal-backdrop {
            display: none; position: fixed; inset: 0; z-index: 2000;
            background: rgba(15, 23, 42, 0.45); align-items: center; justify-content: center; padding: 20px;
        }
        .um-modal-backdrop.open { display: flex; }
        .um-modal {
            background: #fff; border-radius: 14px; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.18);
            width: 100%; max-width: 440px; max-height: 92vh; overflow: auto;
        }
        .um-modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 18px; border-bottom: 1px solid var(--um-border);
        }
        .um-modal-header h2 { margin: 0; font-size: 1.05rem; font-weight: 700; color: #1e293b; }
        .um-modal-close { border: none; background: transparent; padding: 6px; cursor: pointer; color: var(--um-muted); line-height: 1; }
        .um-modal-close:hover { color: #0f172a; }
        .um-modal-body { padding: 18px; }
        .um-form-group { margin-bottom: 14px; }
        .um-form-group label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .um-form-group input[type="text"], .um-form-group select {
            width: 100%; border: 1px solid var(--um-border); border-radius: 8px;
            padding: 10px 12px; font-size: 14px; box-sizing: border-box; background: #fff;
        }
        .um-form-group input:focus, .um-form-group select:focus {
            outline: none; border-color: var(--um-purple); box-shadow: 0 0 0 3px var(--um-purple-soft);
        }
        .um-check-inline { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: var(--um-text); }
        .um-check-inline input { width: 18px; height: 18px; accent-color: var(--um-purple); }
        .um-modal-footer {
            display: flex; justify-content: flex-end; gap: 10px; padding: 14px 18px;
            border-top: 1px solid var(--um-border); background: #fafafa;
        }
        .um-btn-close {
            border: 2px solid var(--um-purple); background: #fff; color: var(--um-purple);
            font-weight: 600; font-size: 13px; padding: 8px 18px; border-radius: 8px; cursor: pointer;
        }
        .um-btn-close:hover { background: var(--um-purple-soft); }
        .um-btn-save {
            border: none; background: var(--um-purple); color: #fff; font-weight: 600;
            font-size: 13px; padding: 8px 18px; border-radius: 8px; cursor: pointer;
        }
        .um-btn-save:disabled { opacity: 0.55; cursor: not-allowed; }
        .um-form-error { color: #dc2626; font-size: 13px; margin-top: 8px; display: none; }
        .um-form-error.show { display: block; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../sidebar.php'; ?>
    <div class="layout-content">
        <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
            <div class="um-page">
                <div class="um-top-bar">
                    <?php include __DIR__ . '/administration_tabs.php'; ?>
                    <div class="um-actions">
                        <button type="button" class="um-icon-btn" title="Refresh" onclick="location.reload()">
                            <i class="feather icon-refresh-cw"></i>
                        </button>
                        <button type="button" class="um-btn-user" id="dmAddBtn">+ <?php echo htmlspecialchars($singular, ENT_QUOTES, 'UTF-8'); ?></button>
                    </div>
                </div>

                <div class="um-card">
                    <div class="um-table-wrap">
                        <table class="um-table um-table-roles">
                            <thead>
                                <tr>
                                    <?php if ($isDesignation): ?>
                                    <th>Department <span class="um-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                                    <?php endif; ?>
                                    <th><?php echo htmlspecialchars($singular, ENT_QUOTES, 'UTF-8'); ?> <span class="um-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                                    <th class="um-status-cell">Active <span class="um-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                                    <th class="um-actions-cell">Actions</th>
                                    <th class="um-th-actions"><i class="feather icon-settings" style="color:#11294b;" title="Column settings"></i></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $n = count($rows);
                                $colspan = $isDesignation ? 5 : 4;
                                if ($n === 0):
                                ?>
                                <tr>
                                    <td colspan="<?php echo $colspan; ?>" class="um-empty-cell">No Rows To Show</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $row): ?>
                                        <?php
                                        $rid = (int) ($row['id'] ?? 0);
                                        $rname = trim((string) ($row['name'] ?? ''));
                                        $act = !empty($row['status']);
                                        $deptId = (int) ($row['department_id'] ?? 0);
                                        $deptName = trim((string) ($row['department_name'] ?? ''));
                                        ?>
                                        <tr data-row-id="<?php echo $rid; ?>">
                                            <?php if ($isDesignation): ?>
                                            <td><?php echo htmlspecialchars($deptName !== '' ? $deptName : '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <?php endif; ?>
                                            <td>
                                                <button type="button" class="um-role-link dm-edit"
                                                    data-id="<?php echo $rid; ?>"
                                                    data-name="<?php echo htmlspecialchars($rname, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-active="<?php echo $act ? '1' : '0'; ?>"
                                                    data-department-id="<?php echo $deptId; ?>">
                                                    <?php echo htmlspecialchars($rname, ENT_QUOTES, 'UTF-8'); ?>
                                                </button>
                                            </td>
                                            <td class="um-status-cell">
                                                <input type="checkbox" class="dm-flag" data-id="<?php echo $rid; ?>" <?php echo $act ? 'checked' : ''; ?>>
                                            </td>
                                            <td class="um-actions-cell">
                                                <button type="button" class="um-btn-delete dm-delete" data-id="<?php echo $rid; ?>" aria-label="Delete"><i class="feather icon-trash-2"></i></button>
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

                <button type="button" class="um-fab" id="dmFabTaskBtn">New Task / Event</button>

                <div id="dmModal" class="um-modal-backdrop" aria-hidden="true">
                    <div class="um-modal" role="dialog" aria-modal="true" aria-labelledby="dmModalTitle">
                        <div class="um-modal-header">
                            <h2 id="dmModalTitle">Add <?php echo htmlspecialchars($singular, ENT_QUOTES, 'UTF-8'); ?></h2>
                            <button type="button" class="um-modal-close" id="dmModalX" aria-label="Close"><i class="feather icon-x"></i></button>
                        </div>
                        <form id="dmForm" novalidate>
                            <div class="um-modal-body">
                                <input type="hidden" id="dmId" name="id" value="0">
                                <?php if ($isDesignation): ?>
                                <div class="um-form-group">
                                    <label for="dmDepartment">Department</label>
                                    <select id="dmDepartment" name="department_id" required>
                                        <option value="">— Select department —</option>
                                        <?php foreach ($departments as $department): ?>
                                        <option value="<?php echo (int) ($department['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($department['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <div class="um-form-group">
                                    <label for="dmName"><?php echo htmlspecialchars($singular, ENT_QUOTES, 'UTF-8'); ?> Name</label>
                                    <input type="text" id="dmName" name="name" required maxlength="120" autocomplete="off" placeholder="Enter <?php echo strtolower($singular); ?> name">
                                </div>
                                <div class="um-form-group" style="margin-bottom:0;">
                                    <label class="um-check-inline">
                                        <input type="checkbox" id="dmActive" name="status" checked>
                                        Active
                                    </label>
                                </div>
                                <div class="um-form-error" id="dmFormError"></div>
                            </div>
                            <div class="um-modal-footer">
                                <button type="button" class="um-btn-close" id="dmModalClose">Close</button>
                                <button type="submit" class="um-btn-save" id="dmModalSave">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var isDesignation = <?php echo $isDesignation ? 'true' : 'false'; ?>;
        var singular = <?php echo json_encode($singular); ?>;
        var pageUrl = <?php echo json_encode($redirectUrl); ?>;
        var modal = document.getElementById('dmModal');
        var titleEl = document.getElementById('dmModalTitle');
        var form = document.getElementById('dmForm');
        var errEl = document.getElementById('dmFormError');
        var idInput = document.getElementById('dmId');
        var nameInput = document.getElementById('dmName');
        var activeInput = document.getElementById('dmActive');
        var departmentSelect = document.getElementById('dmDepartment');

        function openAdd() {
            titleEl.textContent = 'Add ' + singular;
            idInput.value = '0';
            nameInput.value = '';
            activeInput.checked = true;
            if (departmentSelect) departmentSelect.value = '';
            errEl.style.display = 'none';
            errEl.textContent = '';
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            (departmentSelect || nameInput).focus();
        }

        function openEdit(btn) {
            titleEl.textContent = 'Edit ' + singular;
            idInput.value = btn.getAttribute('data-id') || '0';
            nameInput.value = btn.getAttribute('data-name') || '';
            activeInput.checked = btn.getAttribute('data-active') === '1';
            if (departmentSelect) departmentSelect.value = btn.getAttribute('data-department-id') || '';
            errEl.style.display = 'none';
            errEl.textContent = '';
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            nameInput.focus();
        }

        function closeModal() {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }

        function postForm(data) {
            return fetch(pageUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data),
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); });
        }

        document.getElementById('dmAddBtn').addEventListener('click', openAdd);
        document.getElementById('dmModalClose').addEventListener('click', closeModal);
        document.getElementById('dmModalX').addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });

        document.querySelectorAll('.dm-edit').forEach(function (btn) {
            btn.addEventListener('click', function () { openEdit(btn); });
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            errEl.style.display = 'none';
            errEl.textContent = '';
            var payload = {
                action: 'save',
                id: parseInt(idInput.value, 10) || 0,
                name: nameInput.value.trim(),
                status: activeInput.checked ? 1 : ''
            };
            if (isDesignation) {
                payload.department_id = departmentSelect ? (parseInt(departmentSelect.value, 10) || 0) : 0;
                if (!payload.department_id) {
                    errEl.textContent = 'Please select a department.';
                    errEl.style.display = 'block';
                    return;
                }
            }
            if (!payload.name) {
                errEl.textContent = singular + ' name is required.';
                errEl.style.display = 'block';
                return;
            }
            var saveBtn = document.getElementById('dmModalSave');
            saveBtn.disabled = true;
            postForm(payload)
            .then(function (data) {
                saveBtn.disabled = false;
                if (data.ok) {
                    window.location.reload();
                } else {
                    errEl.textContent = data.message || 'Error';
                    errEl.style.display = 'block';
                }
            })
            .catch(function () {
                saveBtn.disabled = false;
                errEl.textContent = 'Network error';
                errEl.style.display = 'block';
            });
        });

        document.querySelectorAll('.dm-flag').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var val = cb.checked;
                postForm({ action: 'toggle', id: cb.getAttribute('data-id'), value: val ? 1 : '' })
                .then(function (data) {
                    if (!data.ok) {
                        cb.checked = !val;
                        alert(data.message || 'Could not update');
                    }
                })
                .catch(function () {
                    cb.checked = !val;
                    alert('Network error');
                });
            });
        });

        document.querySelectorAll('.dm-delete').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Delete this ' + singular.toLowerCase() + '?')) return;
                postForm({ action: 'delete', id: btn.getAttribute('data-id') })
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

        var fab = document.getElementById('dmFabTaskBtn');
        if (fab) fab.addEventListener('click', function () { window.location.href = 'crm.php'; });
    })();
    </script>
</body>
</html>
