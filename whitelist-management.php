<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session_login_type.php';
require_once __DIR__ . '/includes/whitelist_schema.php';

if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}
if (!auragold_session_is_admin_login_type()) {
    header('Location: dashboard.php');
    exit;
}

auragold_ensure_whitelist_tables($conn_master);

$ipControlOn = auragold_ip_access_control_enabled($conn_master);
$rows          = getListMaster('SELECT * FROM tbl_ip_whitelist ORDER BY id DESC');
$n             = is_array($rows) ? count($rows) : 0;

$page_title           = 'Whitelist — Administration — ' . auragold_app_name();
$auragold_admin_tab   = 'whitelist';
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
            --wl-purple: #11294b;
            --wl-purple-soft: #e9ecef;
            --wl-border: #e2e8f0;
            --wl-text: #334155;
            --wl-muted: #64748b;
        }
        .wl-page { padding: 20px 24px 100px; max-width: 1200px; margin: 0 auto; }
        .wl-top-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 16px; }
        .um-tabs { display: flex; flex-wrap: wrap; gap: 4px; flex: 1; min-width: 0; }
        .um-tab {
            border: 1px solid var(--wl-border); background: #fff; color: var(--wl-text);
            font-size: 13px; font-weight: 600; padding: 10px 16px; border-radius: 8px;
            cursor: pointer; transition: background 0.15s, color 0.15s;
        }
        .um-tabs a.um-tab {
            text-decoration: none; color: var(--wl-text); display: inline-block; box-sizing: border-box;
        }
        .um-tabs a.um-tab.active { color: #fff; }
        .um-tab:hover:not(:disabled) { background: #f8fafc; }
        .um-tab.active { background: var(--wl-purple); color: #fff; border-color: var(--wl-purple); }
        .um-tab:disabled { opacity: 0.55; cursor: not-allowed; }
        .wl-head-row {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
            gap: 12px; margin-bottom: 14px;
        }
        .wl-head-row h1 { margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--wl-text); }
        .wl-btn-add {
            border: 2px solid var(--wl-purple); background: #fff; color: var(--wl-purple);
            font-weight: 600; font-size: 13px; padding: 8px 18px; border-radius: 8px; cursor: pointer;
        }
        .wl-btn-add:hover { background: var(--wl-purple-soft); }
        .wl-status-bar {
            background: #f1f5f9; border: 1px solid var(--wl-border); border-radius: 10px;
            padding: 14px 18px; margin-bottom: 16px; display: flex; flex-wrap: wrap;
            align-items: flex-start; justify-content: space-between; gap: 14px;
        }
        .wl-status-bar p { margin: 0; font-size: 13px; color: var(--wl-text); line-height: 1.5; max-width: 720px; }
        .wl-status-bar .wl-status-title { font-weight: 700; margin-bottom: 6px; }
        .wl-status-bar .wl-status-title .wl-on { color: #15803d; }
        .wl-status-bar .wl-status-title .wl-off { color: var(--wl-muted); }
        .wl-toggle-wrap {
            display: flex; align-items: center; gap: 10px; flex-shrink: 0;
            font-size: 13px; font-weight: 600; color: var(--wl-text);
        }
        .wl-switch {
            position: relative; width: 48px; height: 26px; flex-shrink: 0;
        }
        .wl-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
        .wl-switch .wl-slider {
            position: absolute; cursor: pointer; inset: 0; background: #cbd5e1; border-radius: 26px;
            transition: 0.2s;
        }
        .wl-switch .wl-slider:before {
            position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px;
            background: #fff; border-radius: 50%; transition: 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .wl-switch input:checked + .wl-slider { background: var(--wl-purple); }
        .wl-switch input:checked + .wl-slider:before { transform: translateX(22px); }
        .wl-card {
            background: #fff; border: 1px solid var(--wl-border); border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04); overflow: hidden;
        }
        .wl-table-wrap { overflow-x: auto; }
        .wl-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .wl-table thead th {
            text-align: left; padding: 12px 14px; font-weight: 600; color: var(--wl-text);
            background: #fafafa; border-bottom: 1px solid var(--wl-border); white-space: nowrap;
        }
        .wl-table thead th .wl-sort {
            display: inline-flex; flex-direction: column; margin-left: 4px; vertical-align: middle;
            line-height: 0.65; color: var(--wl-muted); font-size: 10px;
        }
        .wl-table thead th.wl-th-actions { width: 44px; text-align: right; }
        .wl-table tbody td {
            padding: 12px 14px; border-bottom: 1px solid var(--wl-border); vertical-align: middle;
            color: var(--wl-text);
        }
        .wl-table tbody tr:nth-child(even) td { background: #fafafa; }
        .wl-table tbody tr:nth-child(odd) td { background: #fff; }
        .wl-empty { padding: 40px !important; text-align: center; color: var(--wl-muted); }
        .wl-cb-cell { text-align: center; }
        .wl-cb-cell input { width: 18px; height: 18px; accent-color: var(--wl-purple); cursor: pointer; }
        .wl-btn-del {
            border: none; background: transparent; color: #dc2626; cursor: pointer; padding: 6px;
            border-radius: 6px;
        }
        .wl-btn-del:hover { background: #fef2f2; }
        .wl-notes { max-width: 280px; color: var(--wl-muted); font-size: 12px; }
        .wl-modal-backdrop {
            display: none; position: fixed; inset: 0; z-index: 2000;
            background: rgba(15, 23, 42, 0.45); align-items: center; justify-content: center; padding: 20px;
        }
        .wl-modal-backdrop.open { display: flex; }
        .wl-modal {
            background: #fff; border-radius: 14px; box-shadow: 0 20px 50px rgba(0,0,0,0.18);
            width: 100%; max-width: 480px; max-height: 92vh; overflow: auto;
        }
        .wl-modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 18px; border-bottom: 1px solid var(--wl-border);
        }
        .wl-modal-header h2 { margin: 0; font-size: 1.05rem; font-weight: 700; color: #1e293b; }
        .wl-modal-close {
            border: none; background: transparent; padding: 6px; cursor: pointer; color: var(--wl-muted);
        }
        .wl-modal-body { padding: 18px; }
        .wl-form-group { margin-bottom: 14px; }
        .wl-form-group label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .wl-req { color: #dc2626; }
        .wl-form-group input[type="text"], .wl-form-group select, .wl-form-group textarea {
            width: 100%; border: 1px solid var(--wl-border); border-radius: 8px; padding: 10px 12px;
            font-size: 14px; box-sizing: border-box;
        }
        .wl-form-group textarea { min-height: 88px; resize: vertical; }
        .wl-form-group input:focus, .wl-form-group select:focus, .wl-form-group textarea:focus {
            outline: none; border-color: var(--wl-purple); box-shadow: 0 0 0 3px var(--wl-purple-soft);
        }
        .wl-form-row { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
        .wl-form-row select { flex: 1; min-width: 120px; }
        .wl-check-inline { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; }
        .wl-modal-footer {
            display: flex; justify-content: flex-end; gap: 10px; padding: 14px 18px;
            border-top: 1px solid var(--wl-border); background: #fafafa;
        }
        .wl-btn-close {
            border: 2px solid var(--wl-purple); background: #fff; color: var(--wl-purple);
            font-weight: 600; font-size: 13px; padding: 8px 18px; border-radius: 8px; cursor: pointer;
        }
        .wl-btn-save {
            border: none; background: var(--wl-purple); color: #fff; font-weight: 600; font-size: 13px;
            padding: 8px 18px; border-radius: 8px; cursor: pointer;
        }
        .wl-form-error { color: #dc2626; font-size: 13px; margin-top: 8px; display: none; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="layout-content">
        <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
            <div class="wl-page">
                <div class="wl-top-bar">
                    <?php include __DIR__ . '/includes/administration_tabs.php'; ?>
                </div>

                <div class="wl-head-row">
                    <h1>Whitelist</h1>
                    <button type="button" class="wl-btn-add" id="wlAddBtn">+ Add entry</button>
                </div>

                <div class="wl-status-bar">
                    <div>
                        <div class="wl-status-title">
                            IP Access Control (Whitelist &amp; Blocklist)
                            <?php if ($ipControlOn): ?>
                                <span class="wl-on">Enabled</span>
                            <?php else: ?>
                                <span class="wl-off">Disabled</span>
                            <?php endif; ?>
                        </div>
                        <p>When enabled, only IP addresses in the Whitelist can sign in. Blocklisted IPs are always denied.</p>
                    </div>
                    <div class="wl-toggle-wrap">
                        <span>IP Access Control</span>
                        <label class="wl-switch" title="Enable IP access control">
                            <input type="checkbox" id="wlIpControl"<?php echo $ipControlOn ? ' checked' : ''; ?>>
                            <span class="wl-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="wl-card">
                    <div class="wl-table-wrap">
                        <table class="wl-table">
                            <thead>
                                <tr>
                                    <th>Entity Value <span class="wl-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                                    <th>Type <span class="wl-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                                    <th>Notes <span class="wl-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                                    <th class="wl-cb-cell">Active</th>
                                    <th class="wl-th-actions"><i class="feather icon-settings" style="color:#11294b;" title="Column settings"></i></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($n === 0): ?>
                                <tr>
                                    <td colspan="5" class="wl-empty">No Rows To Show</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $r): ?>
                                        <?php
                                        $rid   = (int) ($r['id'] ?? 0);
                                        $ev    = (string) ($r['entity_value'] ?? '');
                                        $tp    = (string) ($r['entry_type'] ?? 'IP');
                                        $notes = (string) ($r['notes'] ?? '');
                                        $ok    = !empty($r['is_active']);
                                        $notesAttr = htmlspecialchars(
                                            str_replace(["\r\n", "\n", "\r"], [' ', ' ', ' '], $notes),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                        <tr data-id="<?php echo $rid; ?>">
                                            <td>
                                                <button type="button" class="wl-entity-link" style="border:none;background:none;padding:0;color:#2563eb;font-weight:600;cursor:pointer;text-align:left;font:inherit;"
                                                    data-id="<?php echo $rid; ?>"
                                                    data-entity="<?php echo htmlspecialchars($ev, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-type="<?php echo htmlspecialchars($tp, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-notes="<?php echo $notesAttr; ?>"
                                                    data-active="<?php echo $ok ? '1' : '0'; ?>">
                                                    <?php echo htmlspecialchars($ev, ENT_QUOTES, 'UTF-8'); ?>
                                                </button>
                                            </td>
                                            <td><?php echo htmlspecialchars($tp, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="wl-notes"><?php echo $notes !== '' ? htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                                            <td class="wl-cb-cell">
                                                <input type="checkbox" class="wl-row-active" data-id="<?php echo $rid; ?>"<?php echo $ok ? ' checked' : ''; ?>>
                                            </td>
                                            <td class="wl-cb-cell">
                                                <button type="button" class="wl-btn-del wl-delete" data-id="<?php echo $rid; ?>" aria-label="Delete"><i class="feather icon-trash-2"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="wlModal" class="wl-modal-backdrop" aria-hidden="true">
                    <div class="wl-modal" role="dialog" aria-labelledby="wlModalTitle">
                        <div class="wl-modal-header">
                            <h2 id="wlModalTitle">Add Whitelist Entry</h2>
                            <button type="button" class="wl-modal-close" id="wlModalX" aria-label="Close"><i class="feather icon-x"></i></button>
                        </div>
                        <form id="wlForm" novalidate>
                            <div class="wl-modal-body">
                                <input type="hidden" id="wlId" name="id" value="0">
                                <div class="wl-form-group">
                                    <label for="wlEntity">Entity Value <span class="wl-req">*</span></label>
                                    <input type="text" id="wlEntity" name="entity_value" required maxlength="255"
                                        placeholder="e.g. 192.168.1.1 or 192.168.*.*" autocomplete="off">
                                </div>
                                <div class="wl-form-row">
                                    <div class="wl-form-group" style="flex:1;min-width:140px;margin-bottom:0;">
                                        <label for="wlType">Type</label>
                                        <select id="wlType" name="entry_type">
                                            <option value="IP">IP</option>
                                        </select>
                                    </div>
                                    <label class="wl-check-inline" style="margin-top:22px;">
                                        <input type="checkbox" id="wlActive" name="is_active" checked>
                                        Active
                                    </label>
                                </div>
                                <div class="wl-form-group">
                                    <label for="wlNotes">Notes</label>
                                    <textarea id="wlNotes" name="notes" maxlength="2000" placeholder="e.g. Purpose or description (optional)"></textarea>
                                </div>
                                <div class="wl-form-error" id="wlFormError"></div>
                            </div>
                            <div class="wl-modal-footer">
                                <button type="button" class="wl-btn-close" id="wlModalClose">Close</button>
                                <button type="submit" class="wl-btn-save" id="wlModalSave">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var modal = document.getElementById('wlModal');
        var titleEl = document.getElementById('wlModalTitle');
        var form = document.getElementById('wlForm');
        var errEl = document.getElementById('wlFormError');

        function openAdd() {
            titleEl.textContent = 'Add Whitelist Entry';
            document.getElementById('wlId').value = '0';
            document.getElementById('wlEntity').value = '';
            document.getElementById('wlType').value = 'IP';
            document.getElementById('wlActive').checked = true;
            document.getElementById('wlNotes').value = '';
            errEl.style.display = 'none';
            errEl.textContent = '';
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            document.getElementById('wlEntity').focus();
        }

        function openEdit(btn) {
            titleEl.textContent = 'Edit Whitelist Entry';
            document.getElementById('wlId').value = btn.getAttribute('data-id') || '0';
            document.getElementById('wlEntity').value = btn.getAttribute('data-entity') || '';
            document.getElementById('wlType').value = btn.getAttribute('data-type') || 'IP';
            document.getElementById('wlActive').checked = btn.getAttribute('data-active') === '1';
            document.getElementById('wlNotes').value = btn.getAttribute('data-notes') || '';
            errEl.style.display = 'none';
            errEl.textContent = '';
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeModal() {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }

        document.getElementById('wlAddBtn').addEventListener('click', openAdd);
        document.getElementById('wlModalClose').addEventListener('click', closeModal);
        document.getElementById('wlModalX').addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });

        document.querySelectorAll('.wl-entity-link').forEach(function (btn) {
            btn.addEventListener('click', function () { openEdit(btn); });
        });

        document.getElementById('wlIpControl').addEventListener('change', function () {
            var on = this.checked;
            fetch('ajax/ip-access-control.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enabled: on })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    alert(data.message || 'Could not update');
                    document.getElementById('wlIpControl').checked = !on;
                    return;
                }
                window.location.reload();
            })
            .catch(function () {
                alert('Network error');
                document.getElementById('wlIpControl').checked = !on;
            });
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            errEl.style.display = 'none';
            errEl.textContent = '';
            var payload = {
                id: parseInt(document.getElementById('wlId').value, 10) || 0,
                entity_value: document.getElementById('wlEntity').value.trim(),
                entry_type: document.getElementById('wlType').value,
                notes: document.getElementById('wlNotes').value.trim(),
                is_active: document.getElementById('wlActive').checked
            };
            var saveBtn = document.getElementById('wlModalSave');
            saveBtn.disabled = true;
            fetch('ajax/whitelist-entry-save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function (r) { return r.json(); })
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

        document.querySelectorAll('.wl-row-active').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var id = parseInt(cb.getAttribute('data-id'), 10);
                var on = cb.checked;
                fetch('ajax/whitelist-entry-active.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, is_active: on })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        cb.checked = !on;
                        alert(data.message || 'Could not update');
                    }
                })
                .catch(function () {
                    cb.checked = !on;
                    alert('Network error');
                });
            });
        });

        document.querySelectorAll('.wl-delete').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Delete this whitelist entry?')) return;
                var id = parseInt(btn.getAttribute('data-id'), 10);
                fetch('ajax/whitelist-entry-delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) window.location.reload();
                    else alert(data.message || 'Error');
                })
                .catch(function () { alert('Network error'); });
            });
        });
    })();
    </script>
</body>
</html>
