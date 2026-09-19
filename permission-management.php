<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session_login_type.php';
require_once __DIR__ . '/includes/permissions_schema.php';
require_once __DIR__ . '/includes/permission_definitions.php';
require_once __DIR__ . '/includes/permission_helpers.php';
require_once __DIR__ . '/includes/user_management_schema.php';

if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}
if (!auragold_session_is_admin_login_type()) {
    header('Location: dashboard.php');
    exit;
}

auragold_ensure_user_permissions_table($conn);
auragold_ensure_user_management_columns($conn);

$users = getList(
    'SELECT id, Fname, Lname, Username FROM tbl_users WHERE 1=1'
    . auragold_um_sql_users_scope_and($conn_master)
    . auragold_um_sql_users_permission_page_and($conn_master)
    . ' ORDER BY id ASC'
);

$selId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if ($selId > 0) {
    $ue = getRecord('SELECT * FROM tbl_users WHERE id = ' . $selId . ' LIMIT 1');
    if (!$ue || !auragold_um_user_row_allowed_for_permission_page($conn_master, $ue)) {
        $selId = 0;
    }
}
if ($selId <= 0 && !empty($users)) {
    $selId = (int) ($users[0]['id'] ?? 0);
}

$branch_list_pm = getListMaster(
    "SELECT id, name FROM tbl_branches WHERE (status = 1 OR status = '1') ORDER BY IFNULL(main_branch_id, 0) ASC, id ASC"
);

$pm_locked_branch_id = auragold_um_permission_locked_branch_id($conn_master);
$pm_effective_branch_id = (int) auragold_effective_branch_id();
$pm_login_branch_label = '';
if ($pm_effective_branch_id > 0) {
    $pm_br_row = getRecordMaster('SELECT id, name FROM tbl_branches WHERE id = ' . $pm_effective_branch_id . ' LIMIT 1');
    if ($pm_br_row) {
        $pm_login_branch_label = trim((string) ($pm_br_row['name'] ?? ''));
    }
    if ($pm_login_branch_label === '') {
        $pm_login_branch_label = 'Branch #' . $pm_effective_branch_id;
    }
} else {
    $pm_login_branch_label = 'All locations (registry / main)';
}

$branch_options_pm = is_array($branch_list_pm) ? $branch_list_pm : [];
if ($pm_locked_branch_id > 0) {
    $lb = $pm_locked_branch_id;
    $branch_options_pm = array_values(array_filter($branch_options_pm, function ($br) use ($lb) {
        return (int) ($br['id'] ?? 0) === $lb;
    }));
    if (empty($branch_options_pm) && $lb > 0) {
        $brRow = getRecordMaster('SELECT id, name FROM tbl_branches WHERE id = ' . $lb . ' LIMIT 1');
        $bn  = $brRow ? trim((string) ($brRow['name'] ?? '')) : '';
        $branch_options_pm = [['id' => $lb, 'name' => ($bn !== '' ? $bn : ('Branch #' . $lb))]];
    }
}

if ($pm_locked_branch_id > 0) {
    $pmBranchId = $pm_locked_branch_id;
} elseif (isset($_GET['branch_id'])) {
    $pmBranchId = (int) $_GET['branch_id'];
    if ($pmBranchId < 0) {
        $pmBranchId = 0;
    }
} else {
    $pmBranchId = 0;
}

if ($pmBranchId < 0) {
    $pmBranchId = 0;
}
if ($pmBranchId > 0) {
    $brOk = getRecordMaster('SELECT id FROM tbl_branches WHERE id = ' . $pmBranchId . ' LIMIT 1');
    if (!$brOk) {
        $pmBranchId = 0;
    }
}

$defaults = auragold_permission_all_keys_flat();
if ($selId > 0) {
    // Read from $conn (branch operational DB): the save/get AJAX endpoints write and
    // read grants there, so $conn_master (registry) may not have these rows at all.
    $pm_grants_conn = (isset($conn) && $conn instanceof mysqli) ? $conn : $conn_master;
    $stored = auragold_permission_grants_map_for_user_branch($pm_grants_conn, $selId, $pmBranchId);
    foreach ($defaults as $k => $_) {
        $defaults[$k] = array_key_exists($k, $stored) ? (int) $stored[$k] : 0;
    }
}

$tree = auragold_permission_tree();
$page_title = 'Permissions — Administration — ' . auragold_app_name();
$auragold_admin_tab = 'permissions';
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
            --pm-purple: #11294b;
            --pm-purple-soft: #e9ecef;
            --pm-border: #e2e8f0;
            --pm-text: #334155;
            --pm-muted: #64748b;
        }
        .pm-page { padding: 20px 24px 100px; max-width: 1480px; margin: 0 auto; }
        .pm-top-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 12px; }
        .um-tabs { display: flex; flex-wrap: wrap; gap: 4px; flex: 1; min-width: 0; }
        .um-tab {
            border: 1px solid var(--pm-border); background: #fff; color: var(--pm-text);
            font-size: 13px; font-weight: 600; padding: 10px 16px; border-radius: 8px;
            cursor: pointer; transition: background 0.15s, color 0.15s;
        }
        .um-tabs a.um-tab {
            text-decoration: none; color: var(--pm-text); display: inline-block; box-sizing: border-box;
        }
        .um-tabs a.um-tab.active { color: #fff; }
        .um-tab:hover:not(:disabled) { background: #f8fafc; }
        .um-tab.active { background: var(--pm-purple); color: #fff; border-color: var(--pm-purple); }
        .um-tab:disabled { opacity: 0.55; cursor: not-allowed; }
        .pm-toolbar {
            display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 14px;
            justify-content: space-between;
        }
        .pm-toolbar-left { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .pm-user-wrap { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: var(--pm-text); }
        .pm-user-wrap select {
            min-width: 220px; border: 1px solid var(--pm-border); border-radius: 8px;
            padding: 8px 10px; font-size: 13px;
        }
        .pm-branch-wrap { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: var(--pm-text); }
        .pm-branch-wrap select {
            min-width: 240px; border: 1px solid var(--pm-border); border-radius: 8px;
            padding: 8px 10px; font-size: 13px;
        }
        .pm-branch-hint { font-size: 12px; color: var(--pm-muted); font-weight: 500; max-width: 420px; line-height: 1.35; }
        .pm-search-wrap { position: relative; }
        .pm-search-wrap i {
            position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
            color: var(--pm-muted); font-size: 16px; pointer-events: none;
        }
        .pm-search-wrap input {
            padding: 8px 12px 8px 36px; border: 1px solid var(--pm-border); border-radius: 8px;
            width: 260px; font-size: 13px;
        }
        .pm-toolbar-right { display: flex; align-items: center; gap: 10px; }
        .pm-btn {
            border: 2px solid var(--pm-purple); background: #fff; color: var(--pm-purple);
            font-weight: 600; font-size: 13px; padding: 8px 16px; border-radius: 8px; cursor: pointer;
        }
        .pm-btn:hover { background: var(--pm-purple-soft); }
        .pm-btn-primary { background: var(--pm-purple); color: #fff; border-color: var(--pm-purple); }
        .pm-btn-primary:hover { filter: brightness(0.95); }
        .pm-btn:disabled { opacity: 0.55; cursor: not-allowed; }
        .pm-card {
            background: #fff; border: 1px solid var(--pm-border); border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04); overflow: hidden;
        }
        .pm-table-wrap { overflow-x: auto; max-height: calc(100vh - 280px); overflow-y: auto; }
        .pm-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .pm-table thead th {
            position: sticky; top: 0; z-index: 2;
            text-align: center; padding: 12px 10px; font-weight: 700; color: #fff;
            background: var(--pm-purple); border-bottom: 1px solid var(--pm-border);
        }
        .pm-table thead th.pm-th-name { text-align: left; min-width: 280px; }
        .pm-table tbody td {
            padding: 10px; border-bottom: 1px solid var(--pm-border); vertical-align: middle;
            color: var(--pm-text);
        }
        .pm-table tbody tr:nth-child(even) td { background: #fafafa; }
        .pm-table tbody tr:nth-child(odd) td { background: #fff; }
        .pm-table tbody tr.pm-hidden td { display: none; }
        .pm-table tbody tr.pm-filter-hide { display: none; }
        .pm-name { text-align: left; }
        .pm-name-mod { font-weight: 700; color: var(--pm-purple); }
        .pm-name-page { padding-left: 22px; font-weight: 500; }
        .pm-page-group { font-size: 11px; font-weight: 600; color: var(--pm-muted); margin-bottom: 2px; }
        .pm-toggle {
            border: none; background: transparent; cursor: pointer; color: var(--pm-purple);
            padding: 0 6px 0 0; font-size: 12px; vertical-align: middle;
        }
        .pm-num { width: 44px; text-align: center; color: var(--pm-muted); }
        .pm-na { color: #cbd5e1; text-align: center; }
        .pm-cb { text-align: center; }
        .pm-cb input { width: 17px; height: 17px; accent-color: var(--pm-purple); cursor: pointer; }
        .pm-msg { font-size: 13px; margin-top: 10px; color: var(--pm-muted); }
        .pm-msg.ok { color: #15803d; }
        .pm-msg.err { color: #b91c1c; }
        .pm-branch-context-banner {
            display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px;
            padding: 10px 14px; margin-bottom: 14px;
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            border: 1px solid var(--pm-border); border-radius: 10px; font-size: 13px; color: var(--pm-text);
        }
        .pm-branch-context-banner strong { color: var(--pm-purple); font-weight: 700; }
        .pm-branch-readonly {
            display: inline-block; min-width: 200px; padding: 8px 10px; border: 1px solid var(--pm-border);
            border-radius: 8px; background: #f8fafc; font-weight: 600; color: var(--pm-text);
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="layout-content">
        <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
            <div class="pm-page">
                <div class="pm-top-bar">
                    <?php include __DIR__ . '/includes/administration_tabs.php'; ?>
                </div>

                <div class="pm-branch-context-banner" role="region" aria-label="Login branch context">
                    <span><strong>Login / working branch:</strong> <?php echo htmlspecialchars($pm_login_branch_label, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if ($pm_locked_branch_id > 0): ?>
                        <span style="color:var(--pm-muted);">You can only set permissions for this branch and for users assigned to this branch.</span>
                    <?php endif; ?>
                </div>

                <div class="pm-toolbar">
                    <div class="pm-toolbar-left">
                        <div class="pm-user-wrap">
                            <label for="pmUserSelect">User</label>
                            <select id="pmUserSelect" aria-label="Select user">
                                <?php foreach ($users as $u): ?>
                                    <?php
                                    $uid = (int) ($u['id'] ?? 0);
                                    $fn  = trim((string) ($u['Fname'] ?? ''));
                                    $ln  = trim((string) ($u['Lname'] ?? ''));
                                    $nm  = trim($fn . ' ' . $ln);
                                    if ($nm === '') {
                                        $nm = trim((string) ($u['Username'] ?? ''));
                                    }
                                    ?>
                                    <option value="<?php echo $uid; ?>"<?php echo $uid === $selId ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($nm . ' (' . ($u['Username'] ?? '') . ')', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="pm-branch-wrap">
                            <label for="pmBranchSelect">Permissions for branch</label>
                            <?php if ($pm_locked_branch_id > 0): ?>
                                <?php
                                $pm_sub_br_name = '';
                                foreach ($branch_options_pm as $br) {
                                    if ((int) ($br['id'] ?? 0) === (int) $pmBranchId) {
                                        $pm_sub_br_name = trim((string) ($br['name'] ?? ''));
                                        break;
                                    }
                                }
                                if ($pm_sub_br_name === '' && $pmBranchId > 0) {
                                    $pm_sub_br_name = 'Branch #' . (int) $pmBranchId;
                                }
                                ?>
                                <select id="pmBranchSelect" aria-label="Permission branch scope" disabled title="Branch login: permissions can only be edited for your current branch">
                                    <option value="<?php echo (int) $pmBranchId; ?>" selected><?php echo htmlspecialchars($pm_sub_br_name, ENT_QUOTES, 'UTF-8'); ?></option>
                                </select>
                            <?php else: ?>
                                <select id="pmBranchSelect" aria-label="Permission branch scope">
                                    <option value="0"<?php echo $pmBranchId === 0 ? ' selected' : ''; ?>>Default (all branches)</option>
                                    <?php foreach ($branch_options_pm as $br): ?>
                                        <?php
                                        $bid = (int) ($br['id'] ?? 0);
                                        $bnm = trim((string) ($br['name'] ?? ''));
                                        if ($bid <= 0 || $bnm === '') {
                                            continue;
                                        }
                                        ?>
                                        <option value="<?php echo $bid; ?>"<?php echo $bid === $pmBranchId ? ' selected' : ''; ?>>
                                            <?php echo htmlspecialchars($bnm, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <p class="pm-branch-hint"><?php echo $pm_locked_branch_id > 0
                            ? 'Permissions saved here apply when this user works under this branch.'
                            : 'Default applies when no branch-specific sheet exists for the user’s current branch. Pick a branch to set overrides for that location.'; ?></p>
                        <div class="pm-search-wrap">
                            <i class="feather icon-search"></i>
                            <input type="search" id="pmSearch" placeholder="Search permissions…" autocomplete="off" aria-label="Search">
                        </div>
                    </div>
                    <div class="pm-toolbar-right">
                        <button type="button" class="pm-btn" id="pmSelectAll" title="Check every menu and action for this user/branch">Select all</button>
                        <button type="button" class="pm-btn" id="pmClearAll" title="Uncheck every permission">Clear all</button>
                        <button type="button" class="pm-btn" id="pmRefresh">Refresh Permission</button>
                        <button type="button" class="pm-btn pm-btn-primary" id="pmSave">Save Permissions</button>
                    </div>
                </div>

                <div class="pm-card">
                    <div class="pm-table-wrap">
                        <table class="pm-table" id="pmTable">
                            <thead>
                                <tr>
                                    <th class="pm-num">#</th>
                                    <th class="pm-th-name">Main menu / Sub-menu</th>
                                    <th>Menu access</th>
                                    <th>View</th>
                                    <th>Add</th>
                                    <th>Update</th>
                                    <th>Delete</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $rowNum = 0;
                                foreach ($tree as $mod):
                                    $modKey = $mod['key'];
                                    $menuKey = $modKey . '.menu';
                                    $nPages  = count($mod['pages']);
                                    $rowNum++;
                                ?>
                                <?php
                                    $modSearch = strtolower($mod['label']);
                                    foreach ($mod['pages'] as $__p) {
                                        $modSearch .= ' ' . strtolower($__p['label']);
                                        if (!empty($__p['group'])) {
                                            $modSearch .= ' ' . strtolower((string) $__p['group']);
                                        }
                                    }
                                ?>
                                <tr class="perm-mod-row" data-search="<?php echo htmlspecialchars($modSearch, ENT_QUOTES, 'UTF-8'); ?>" data-mod="<?php echo htmlspecialchars($modKey, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td class="pm-num"><?php echo (int) $rowNum; ?></td>
                                    <td class="pm-name pm-name-mod">
                                        <button type="button" class="pm-toggle" aria-expanded="true" data-mod="<?php echo htmlspecialchars($modKey, ENT_QUOTES, 'UTF-8'); ?>">▼</button>
                                        <?php echo htmlspecialchars($mod['label'], ENT_QUOTES, 'UTF-8'); ?>
                                        <span style="color:var(--pm-muted);font-weight:600;">(<?php echo (int) $nPages; ?>)</span>
                                    </td>
                                    <td class="pm-cb">
                                        <input type="checkbox" class="pm-grant pm-menu-grant" data-key="<?php echo htmlspecialchars($menuKey, ENT_QUOTES, 'UTF-8'); ?>" data-mod="<?php echo htmlspecialchars($modKey, ENT_QUOTES, 'UTF-8'); ?>"<?php echo !empty($defaults[$menuKey]) ? ' checked' : ''; ?> title="Check to grant this menu and all its sub-menu actions">
                                    </td>
                                    <td class="pm-na" colspan="4" title="Use sub-menu rows below for View / Add / Update / Delete">—</td>
                                </tr>
                                    <?php foreach ($mod['pages'] as $page):
                                        $rowNum++;
                                        $grantNs = auragold_permission_grant_namespace_for_page($mod, $page);
                                        $acts = $page['actions'];
                                        $has = function ($a) use ($acts) {
                                            return in_array($a, $acts, true);
                                        };
                                        $searchText = strtolower($mod['label'] . ' ' . $page['label']);
                                        if (!empty($page['group'])) {
                                            $searchText .= ' ' . strtolower((string) $page['group']);
                                        }
                                        ?>
                                <tr class="perm-page-row perm-child" data-parent="<?php echo htmlspecialchars($modKey, ENT_QUOTES, 'UTF-8'); ?>" data-search="<?php echo htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td class="pm-num"><?php echo (int) $rowNum; ?></td>
                                    <td class="pm-name pm-name-page">
                                        <?php if (!empty($page['group'])): ?>
                                            <div class="pm-page-group"><?php echo htmlspecialchars((string) $page['group'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($page['label'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td class="pm-na">—</td>
                                    <?php foreach (['view', 'add', 'update', 'delete'] as $act): ?>
                                        <td class="pm-cb">
                                            <?php if ($has($act)): ?>
                                                <?php $gkey = $grantNs . '.' . $page['key'] . '.' . $act; ?>
                                                <input type="checkbox" class="pm-grant" data-key="<?php echo htmlspecialchars($gkey, ENT_QUOTES, 'UTF-8'); ?>"<?php echo !empty($defaults[$gkey]) ? ' checked' : ''; ?>>
                                            <?php else: ?>
                                                <span class="pm-na">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <p class="pm-msg" id="pmMsg" style="display:none;"></p>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var sel = document.getElementById('pmUserSelect');
        var branchSel = document.getElementById('pmBranchSelect');
        var grants = <?php echo json_encode($defaults, JSON_UNESCAPED_UNICODE); ?>;

        function applyGrantsToUi(g) {
            grants = g || {};
            document.querySelectorAll('.pm-grant').forEach(function (cb) {
                var k = cb.getAttribute('data-key');
                if (!k) return;
                cb.checked = !!(grants[k] === 1 || grants[k] === true);
            });
        }

        function collectGrants() {
            var out = {};
            document.querySelectorAll('.pm-grant').forEach(function (cb) {
                var k = cb.getAttribute('data-key');
                if (k) out[k] = cb.checked ? 1 : 0;
            });
            return out;
        }

        function setAllPermissionCheckboxes(on) {
            document.querySelectorAll('.pm-grant').forEach(function (cb) {
                cb.checked = !!on;
            });
        }

        /** When main-menu access is toggled, sync all sub-menu View/Add/Update/Delete under that module. */
        function syncSubmenuGrantsFromMenu(modKey, on) {
            if (!modKey) return;
            document.querySelectorAll('.perm-child[data-parent="' + modKey + '"] .pm-grant').forEach(function (cb) {
                cb.checked = !!on;
            });
        }

        document.getElementById('pmTable').addEventListener('change', function (e) {
            var t = e.target;
            if (!t || !t.classList || !t.classList.contains('pm-grant')) {
                return;
            }
            if (t.classList.contains('pm-menu-grant')) {
                var modKey = t.getAttribute('data-mod') || '';
                if (!modKey) {
                    var key = t.getAttribute('data-key') || '';
                    if (key.slice(-5) === '.menu') {
                        modKey = key.slice(0, -5);
                    }
                }
                syncSubmenuGrantsFromMenu(modKey, t.checked);
                return;
            }
            // Sub-menu action granted: the sidebar hides the whole module without
            // the parent ".menu" grant, so auto-check Menu access as well.
            if (t.checked) {
                var childRow = t.closest('.perm-child');
                var parentMod = childRow ? (childRow.getAttribute('data-parent') || '') : '';
                if (parentMod) {
                    var menuCb = document.querySelector('.pm-menu-grant[data-mod="' + parentMod + '"]');
                    if (menuCb && !menuCb.checked) {
                        menuCb.checked = true;
                    }
                }
            }
        });

        function showMsg(text, ok) {
            var el = document.getElementById('pmMsg');
            el.style.display = text ? 'block' : 'none';
            el.textContent = text || '';
            el.className = 'pm-msg ' + (ok ? 'ok' : 'err');
        }

        function pmNotify(title, text, type) {
            var msg = text || title || '';
            if (typeof swal === 'function') {
                var opts = {
                    title: title || (type === 'success' ? 'Success' : 'Notice'),
                    text: msg,
                    type: type || 'success',
                    confirmButtonText: 'OK',
                    allowOutsideClick: true
                };
                if (type === 'success') {
                    opts.timer = 2200;
                }
                swal(opts);
                return;
            }
            showMsg(msg, type === 'success');
            if (type !== 'success') {
                window.alert(msg);
            }
        }

        function syncPmUrl() {
            var uid = parseInt(sel.value, 10);
            if (!uid) return;
            var bid = branchSel ? (parseInt(branchSel.value, 10) || 0) : 0;
            try {
                var u = new URL(window.location.href);
                u.searchParams.set('user_id', String(uid));
                u.searchParams.set('branch_id', String(bid));
                history.replaceState({}, '', u);
            } catch (e) {}
        }

        function loadGrants() {
            var uid = parseInt(sel.value, 10);
            if (!uid) return;
            var bid = branchSel ? (parseInt(branchSel.value, 10) || 0) : 0;
            syncPmUrl();
            showMsg('Loading…', true);
            fetch('ajax/permission-grants-get.php?user_id=' + uid + '&branch_id=' + bid)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    showMsg('', true);
                    if (data.ok && data.grants) {
                        applyGrantsToUi(data.grants);
                    } else {
                        showMsg(data.message || 'Could not load', false);
                    }
                })
                .catch(function () { showMsg('Network error', false); });
        }

        document.getElementById('pmSelectAll').addEventListener('click', function () {
            setAllPermissionCheckboxes(true);
            showMsg('All permissions selected — click Save Permissions to store.', true);
        });
        document.getElementById('pmClearAll').addEventListener('click', function () {
            setAllPermissionCheckboxes(false);
            showMsg('All permissions cleared — click Save Permissions to store.', true);
        });

        document.getElementById('pmRefresh').addEventListener('click', loadGrants);

        document.getElementById('pmSave').addEventListener('click', function () {
            var uid = parseInt(sel.value, 10);
            if (!uid) return;
            var bid = branchSel ? (parseInt(branchSel.value, 10) || 0) : 0;
            var btn = document.getElementById('pmSave');
            btn.disabled = true;
            showMsg('Saving…', true);
            fetch('ajax/permission-grants-save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: uid, branch_id: bid, grants: collectGrants() }),
                credentials: 'same-origin',
                cache: 'no-store'
            })
            .then(function (r) {
                return r.text().then(function (t) {
                    var data = null;
                    try { data = t ? JSON.parse(t) : null; } catch (e) { data = null; }
                    return { status: r.status, okHttp: r.ok, data: data, raw: t };
                });
            })
            .then(function (res) {
                btn.disabled = false;
                if (!res.data || typeof res.data !== 'object') {
                    showMsg('Save failed (invalid response). Check server logs.', false);
                    pmNotify('Save failed', 'Invalid response from server. Check server logs.', 'error');
                    return;
                }
                if (res.data.ok) {
                    var okMsg = res.data.message || 'Permission saved successfully';
                    showMsg(okMsg, true);
                    pmNotify('Permission saved successfully', okMsg, 'success');
                } else {
                    var errMsg = res.data.message || 'Could not save permissions.';
                    showMsg(errMsg, false);
                    pmNotify('Save failed', errMsg, 'error');
                }
            })
            .catch(function () {
                btn.disabled = false;
                showMsg('Network error — request did not complete.', false);
                pmNotify('Save failed', 'Network error — request did not complete.', 'error');
            });
        });

        sel.addEventListener('change', loadGrants);
        if (branchSel && !branchSel.disabled) {
            branchSel.addEventListener('change', loadGrants);
        }
        syncPmUrl();

        document.getElementById('pmSearch').addEventListener('input', function () {
            var q = this.value.trim().toLowerCase();
            document.querySelectorAll('.perm-mod-row, .perm-page-row').forEach(function (tr) {
                var s = (tr.getAttribute('data-search') || '');
                if (!q || s.indexOf(q) !== -1) {
                    tr.classList.remove('pm-filter-hide');
                } else {
                    tr.classList.add('pm-filter-hide');
                }
            });
        });

        document.querySelectorAll('.pm-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var mod = btn.getAttribute('data-mod');
                var open = btn.getAttribute('aria-expanded') !== 'false';
                var next = !open;
                btn.setAttribute('aria-expanded', next ? 'true' : 'false');
                btn.textContent = next ? '▼' : '▶';
                document.querySelectorAll('.perm-child[data-parent="' + mod + '"]').forEach(function (row) {
                    row.style.display = next ? '' : 'none';
                });
            });
        });
    })();
    </script>
    <?php include __DIR__ . '/footer-script.php'; ?>
</body>
</html>
