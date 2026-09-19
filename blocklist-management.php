<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session_login_type.php';
require_once __DIR__ . '/includes/blocklist_schema.php';

if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}
if (!auragold_session_is_admin_login_type()) {
    header('Location: dashboard.php');
    exit;
}

auragold_ensure_blocklist_table($conn_master);

$rows = getListMaster('SELECT * FROM tbl_login_blocklist ORDER BY id DESC');
$n    = is_array($rows) ? count($rows) : 0;

function bl_format_dt($v)
{
    if ($v === null || $v === '') {
        return '—';
    }
    $t = strtotime((string) $v);
    return $t ? date('d M Y, h:i A', $t) : '—';
}

$page_title         = 'Blocklist — Administration — ' . auragold_app_name();
$auragold_admin_tab = 'blocklist';
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
            --bl-purple: #11294b;
            --bl-purple-soft: #e9ecef;
            --bl-border: #e2e8f0;
            --bl-text: #334155;
            --bl-muted: #64748b;
        }
        .bl-page { padding: 20px 24px 100px; max-width: 1280px; margin: 0 auto; }
        .bl-top-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 16px; }
        .um-tabs { display: flex; flex-wrap: wrap; gap: 4px; flex: 1; min-width: 0; }
        .um-tab {
            border: 1px solid var(--bl-border); background: #fff; color: var(--bl-text);
            font-size: 13px; font-weight: 600; padding: 10px 16px; border-radius: 8px;
            cursor: pointer; transition: background 0.15s, color 0.15s;
        }
        .um-tabs a.um-tab {
            text-decoration: none; color: var(--bl-text); display: inline-block; box-sizing: border-box;
        }
        .um-tabs a.um-tab.active { color: #fff; }
        .um-tab:hover:not(:disabled) { background: #f8fafc; }
        .um-tab.active { background: var(--bl-purple); color: #fff; border-color: var(--bl-purple); }
        .um-tab:disabled { opacity: 0.55; cursor: not-allowed; }
        .bl-head-row {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
            gap: 12px; margin-bottom: 14px;
        }
        .bl-head-row h1 { margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--bl-text); }
        .bl-head-right { display: flex; align-items: center; gap: 10px; }
        .bl-badge {
            border: 2px solid var(--bl-purple); background: var(--bl-purple-soft); color: var(--bl-purple);
            font-weight: 700; font-size: 12px; padding: 6px 14px; border-radius: 8px;
        }
        .bl-icon-btn {
            width: 40px; height: 40px; border-radius: 8px; border: 1px solid var(--bl-border);
            background: #fff; color: var(--bl-text); display: inline-flex; align-items: center;
            justify-content: center; cursor: pointer; transition: background 0.15s;
        }
        .bl-icon-btn:hover { background: #f8fafc; border-color: #cbd5e1; }
        .bl-card {
            background: #fff; border: 1px solid var(--bl-border); border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04); overflow: hidden;
        }
        .bl-table-wrap { overflow-x: auto; }
        .bl-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .bl-table thead th {
            text-align: left; padding: 12px 10px; font-weight: 600; color: var(--bl-text);
            background: #fafafa; border-bottom: 1px solid var(--bl-border); white-space: nowrap;
        }
        .bl-table thead th .bl-sort {
            display: inline-flex; flex-direction: column; margin-left: 4px; vertical-align: middle;
            line-height: 0.65; color: var(--bl-muted); font-size: 10px;
        }
        .bl-table thead th.bl-th-gear { width: 40px; text-align: right; }
        .bl-table tbody td {
            padding: 11px 10px; border-bottom: 1px solid var(--bl-border); vertical-align: middle;
            color: var(--bl-text);
        }
        .bl-table tbody tr:nth-child(even) td { background: #fafafa; }
        .bl-table tbody tr:nth-child(odd) td { background: #fff; }
        .bl-empty { padding: 40px !important; text-align: center; color: var(--bl-muted); }
        .bl-actions { text-align: center; white-space: nowrap; }
        .bl-act-btn {
            border: none; background: transparent; cursor: pointer; padding: 6px 8px; border-radius: 6px;
            color: var(--bl-muted); line-height: 1;
        }
        .bl-act-btn:hover { background: #f1f5f9; color: var(--bl-purple); }
        .bl-act-btn.danger:hover { color: #dc2626; background: #fef2f2; }
        .bl-footer-bar {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
            gap: 12px; padding: 12px 14px; border-top: 1px solid var(--bl-border);
            background: #fafafa; font-size: 13px; color: var(--bl-muted);
        }
        .bl-fab {
            position: fixed; right: 24px; bottom: 24px; z-index: 100;
            border: 2px solid var(--bl-purple); background: #fff; color: var(--bl-purple);
            font-weight: 600; font-size: 13px; padding: 10px 18px; border-radius: 10px;
            box-shadow: 0 4px 14px rgba(107, 70, 193, 0.2); cursor: pointer;
        }
        .bl-fab:hover { background: var(--bl-purple-soft); }
    </style>
</head>
<body>
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="layout-content">
        <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
            <div class="bl-page">
                <div class="bl-top-bar">
                    <?php include __DIR__ . '/includes/administration_tabs.php'; ?>
                </div>

                <div class="bl-head-row">
                    <h1>Blocklist</h1>
                    <div class="bl-head-right">
                        <span class="bl-badge">Blocklist</span>
                        <button type="button" class="bl-icon-btn" id="blRefresh" title="Refresh" onclick="location.reload()">
                            <i class="feather icon-refresh-cw"></i>
                        </button>
                    </div>
                </div>

                <div class="bl-card">
                    <div class="bl-table-wrap">
                        <table class="bl-table">
                            <thead>
                                <tr>
                                    <th>IP Address <span class="bl-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                                    <th>Username <span class="bl-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                                    <th>User Id <span class="bl-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                                    <th>Attempt Count <span class="bl-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                                    <th>Last Attempt Time <span class="bl-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                                    <th>Blocked Until <span class="bl-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                                    <th class="bl-actions" style="width:44px;" title="Unblock"></th>
                                    <th class="bl-actions" style="width:44px;" title="Delete"></th>
                                    <th class="bl-th-gear"><i class="feather icon-settings" style="color:#11294b;" title="Column settings"></i></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($n === 0): ?>
                                <tr>
                                    <td colspan="9" class="bl-empty">No Rows To Show</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $r): ?>
                                        <?php
                                        $rid   = (int) ($r['id'] ?? 0);
                                        $ip    = (string) ($r['ip_address'] ?? '');
                                        $un    = trim((string) ($r['username'] ?? ''));
                                        $uid   = isset($r['user_id']) && $r['user_id'] !== null && $r['user_id'] !== '' ? (int) $r['user_id'] : null;
                                        $ac    = (int) ($r['attempt_count'] ?? 0);
                                        $lat   = $r['last_attempt_at'] ?? null;
                                        $bu    = $r['blocked_until'] ?? null;
                                        ?>
                                        <tr data-id="<?php echo $rid; ?>">
                                            <td><?php echo htmlspecialchars($ip, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo $un !== '' ? htmlspecialchars($un, ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                                            <td><?php echo $uid !== null ? (string) (int) $uid : '—'; ?></td>
                                            <td><?php echo (int) $ac; ?></td>
                                            <td><?php echo htmlspecialchars(bl_format_dt($lat), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo $bu === null || $bu === '' ? 'Permanent' : htmlspecialchars(bl_format_dt($bu), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="bl-actions">
                                                <button type="button" class="bl-act-btn bl-unblock" data-id="<?php echo $rid; ?>" title="Unblock" aria-label="Unblock"><i class="feather icon-unlock"></i></button>
                                            </td>
                                            <td class="bl-actions">
                                                <button type="button" class="bl-act-btn danger bl-delete" data-id="<?php echo $rid; ?>" title="Delete" aria-label="Delete"><i class="feather icon-trash-2"></i></button>
                                            </td>
                                            <td></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="bl-footer-bar">
                        <?php if ($n === 0): ?>
                        <span>Showing 0 to 0 of 0 entries</span>
                        <?php else: ?>
                        <span>Showing 1 to <?php echo (int) $n; ?> of <?php echo (int) $n; ?> entries</span>
                        <?php endif; ?>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <select aria-label="Page size" disabled style="border:1px solid var(--bl-border);border-radius:6px;padding:6px 8px;font-size:12px;">
                                <option>Show All Items</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="button" class="bl-fab" id="blFabTask">New Task / Event</button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        function removeRow(id) {
            fetch('ajax/blocklist-delete.php', {
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
        }

        document.querySelectorAll('.bl-unblock, .bl-delete').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = parseInt(btn.getAttribute('data-id'), 10);
                var msg = btn.classList.contains('bl-delete')
                    ? 'Delete this blocklist entry?'
                    : 'Remove this entry from the blocklist (unblock)?';
                if (!confirm(msg)) return;
                removeRow(id);
            });
        });

        var fab = document.getElementById('blFabTask');
        if (fab) fab.addEventListener('click', function () { window.location.href = 'crm.php'; });
    })();
    </script>
</body>
</html>
