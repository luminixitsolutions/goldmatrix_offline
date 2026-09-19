<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/crm_whatsapp_schema.php';
require_once __DIR__ . '/includes/crm_contact_groups_schema.php';

if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}

$page_title = 'CRM — ' . auragold_app_name();

auragold_ensure_crm_whatsapp_tables($conn);
auragold_ensure_crm_contact_groups_tables($conn);

$crm_whatsapp_campaigns = getList(
    "SELECT c.id, c.caption, c.customer_name, c.contact_no, c.created_at, c.status, c.branch_id,
            IFNULL(NULLIF(TRIM(b.name), ''), '') AS branch_name,
            (SELECT COUNT(*) FROM tbl_crm_whatsapp_campaign_images i WHERE i.campaign_id = c.id) AS image_count
     FROM tbl_crm_whatsapp_campaigns c
     LEFT JOIN tbl_branches b ON b.id = c.branch_id
     ORDER BY c.id DESC
     LIMIT 200"
);

$crm_customers = getList(
    "SELECT id, name, IFNULL(mobile_no,'') AS mobile_no, IFNULL(phone_no,'') AS phone_no,
            IFNULL(mobile_country_code,'') AS mobile_country_code, IFNULL(status,1) AS status
     FROM tbl_customers
     ORDER BY name ASC
     LIMIT 5000"
);
$crm_contact_groups = getList(
    "SELECT g.id, g.name, g.status, g.created_at,
            (SELECT COUNT(*) FROM tbl_crm_contact_group_members m WHERE m.group_id = g.id) AS user_count
     FROM tbl_crm_contact_groups g
     ORDER BY g.id DESC
     LIMIT 500"
);

/**
 * WhatsApp tab: list campaigns from DB.
 */
function crm_render_whatsapp_campaigns($campaigns)
{
    $rows = is_array($campaigns) ? $campaigns : [];
    ?>
    <div class="crm-card">
        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Caption <span class="crm-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                        <th>Customer Name <span class="crm-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                        <th>Contact No <span class="crm-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                        <th>Date &amp; Time <span class="crm-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                        <th>Branch Name <span class="crm-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                        <th style="text-align:right;">Status</th>
                        <th class="crm-th-actions"><i class="feather icon-settings" style="color:#6b46c1;" title="Column settings"></i></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="7" class="crm-empty-cell">No Rows To Show</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $cap_plain = htmlspecialchars((string) ($r['caption'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $dt = !empty($r['created_at']) ? date('d M Y, h:i A', strtotime($r['created_at'])) : '—';
                            $br = htmlspecialchars((string) ($r['branch_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                            if ($br === '') {
                                $br = '—';
                            }
                            $st = ((int) ($r['status'] ?? 0) === 1) ? 'Saved' : '—';
                            $imgc = (int) ($r['image_count'] ?? 0);
                            $cap_html = $cap_plain;
                            if ($imgc > 0) {
                                $cap_html .= ' <span class="crm-badge-soft">' . (int) $imgc . ' img</span>';
                            }
                            $cust = htmlspecialchars(trim((string) ($r['customer_name'] ?? '')), ENT_QUOTES, 'UTF-8');
                            if ($cust === '') {
                                $cust = '—';
                            }
                            $contact = htmlspecialchars(trim((string) ($r['contact_no'] ?? '')), ENT_QUOTES, 'UTF-8');
                            if ($contact === '') {
                                $contact = '—';
                            }
                            ?>
                            <tr>
                                <td><?php echo $cap_html; ?></td>
                                <td><?php echo $cust; ?></td>
                                <td><?php echo $contact; ?></td>
                                <td><?php echo htmlspecialchars($dt, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo $br; ?></td>
                                <td style="text-align:right;"><?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="crm-footer-bar">
            <?php $n = count($rows); ?>
            <span><?php echo $n > 0 ? 'Showing 1 to ' . $n . ' of ' . $n . ' entries' : 'Showing 0 to 0 of 0 entries'; ?></span>
            <div class="crm-footer-right">
                <select aria-label="Page size" disabled>
                    <option>Show All Items</option>
                </select>
                <div class="crm-pager">
                    <button type="button" disabled title="First" aria-label="First page"><i class="feather icon-chevrons-left"></i></button>
                    <button type="button" disabled title="Previous" aria-label="Previous page"><i class="feather icon-chevron-left"></i></button>
                    <button type="button" disabled title="Next" aria-label="Next page"><i class="feather icon-chevron-right"></i></button>
                    <button type="button" disabled title="Last" aria-label="Last page"><i class="feather icon-chevrons-right"></i></button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Empty grid shell: Caption, Date & Time, Branch Name, Status (matches CRM list UI).
 */
function crm_render_empty_grid($panel_id)
{
    unset($panel_id);
    ?>
    <div class="crm-card">
        <div class="crm-table-wrap">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Caption <span class="crm-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                        <th>Date &amp; Time <span class="crm-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                        <th>Branch Name <span class="crm-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>
                        <th style="text-align:right;">Status</th>
                        <th class="crm-th-actions"><i class="feather icon-settings" style="color:#6b46c1;" title="Column settings"></i></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5" class="crm-empty-cell">No Rows To Show</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="crm-footer-bar">
            <span>Showing 0 to 0 of 0 entries</span>
            <div class="crm-footer-right">
                <select aria-label="Page size">
                    <option>Show All Items</option>
                    <option>10</option>
                    <option>25</option>
                    <option>50</option>
                </select>
                <div class="crm-pager">
                    <button type="button" disabled title="First" aria-label="First page"><i class="feather icon-chevrons-left"></i></button>
                    <button type="button" disabled title="Previous" aria-label="Previous page"><i class="feather icon-chevron-left"></i></button>
                    <button type="button" disabled title="Next" aria-label="Next page"><i class="feather icon-chevron-right"></i></button>
                    <button type="button" disabled title="Last" aria-label="Last page"><i class="feather icon-chevrons-right"></i></button>
                </div>
            </div>
        </div>
    </div>
    <?php
}
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
            --crm-purple: #6b46c1;
            --crm-purple-dark: #553c9a;
            --crm-purple-soft: #ede9fe;
            --crm-border: #e2e8f0;
            --crm-text: #334155;
            --crm-muted: #64748b;
        }
        .crm-page {
            padding: 20px 24px 100px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .crm-top-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .crm-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            flex: 1;
            min-width: 0;
        }
        .crm-tab {
            border: none;
            background: #fff;
            color: var(--crm-text);
            font-size: 13px;
            font-weight: 600;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
        }
        .crm-tab:hover {
            background: #f8fafc;
        }
        .crm-tab.active {
            background: var(--crm-purple);
            color: #fff;
        }
        .crm-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        .crm-icon-btn {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 1px solid var(--crm-border);
            background: #fff;
            color: var(--crm-text);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
        }
        .crm-icon-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
        .crm-icon-btn .crm-badge {
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
        .crm-btn-create {
            border: 2px solid var(--crm-purple);
            background: #fff;
            color: var(--crm-purple);
            font-weight: 600;
            font-size: 13px;
            padding: 8px 18px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
        }
        .crm-btn-create:hover {
            background: var(--crm-purple-soft);
        }
        .crm-panel {
            display: none;
        }
        .crm-panel.active {
            display: block;
        }
        .crm-card {
            background: #fff;
            border: 1px solid var(--crm-border);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }
        .crm-table-wrap {
            overflow-x: auto;
        }
        .crm-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .crm-table thead th {
            text-align: left;
            padding: 14px 16px;
            font-weight: 600;
            color: var(--crm-text);
            background: #fafafa;
            border-bottom: 1px solid var(--crm-border);
            white-space: nowrap;
        }
        .crm-table thead th .crm-sort {
            display: inline-flex;
            flex-direction: column;
            margin-left: 4px;
            vertical-align: middle;
            line-height: 0.65;
            color: var(--crm-muted);
            font-size: 10px;
        }
        .crm-table thead th .crm-sort i { font-size: 11px; }
        .crm-table thead th.crm-th-actions {
            width: 48px;
            text-align: right;
        }
        .crm-empty-cell {
            padding: 48px 24px !important;
            text-align: center;
            color: var(--crm-muted);
            font-size: 14px;
            border-bottom: none !important;
        }
        .crm-footer-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            border-top: 1px solid var(--crm-border);
            background: #fafafa;
            font-size: 13px;
            color: var(--crm-muted);
        }
        .crm-footer-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .crm-footer-bar select {
            border: 1px solid var(--crm-border);
            border-radius: 6px;
            padding: 6px 10px;
            font-size: 12px;
            background: #fff;
            color: var(--crm-text);
        }
        .crm-pager {
            display: flex;
            gap: 4px;
        }
        .crm-pager button {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid var(--crm-border);
            background: #fff;
            color: var(--crm-text);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        .crm-pager button:hover:not(:disabled) {
            background: #f1f5f9;
        }
        .crm-pager button:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .crm-fab {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 100;
            border: 2px solid var(--crm-purple);
            background: #fff;
            color: var(--crm-purple);
            font-weight: 600;
            font-size: 13px;
            padding: 10px 18px;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(107, 70, 193, 0.2);
            cursor: pointer;
        }
        .crm-fab:hover {
            background: var(--crm-purple-soft);
        }
        .crm-badge-soft {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 999px;
            background: var(--crm-purple-soft);
            color: var(--crm-purple-dark);
            vertical-align: middle;
        }
        .crm-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
            background: rgba(15, 23, 42, 0.45);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .crm-modal-backdrop.open {
            display: flex;
        }
        .crm-modal {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.18);
            width: 100%;
            max-width: 620px;
            max-height: 92vh;
            overflow: auto;
        }
        .crm-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 18px;
            border-bottom: 1px solid var(--crm-border);
        }
        .crm-modal-header h2 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: #1e293b;
        }
        .crm-modal-close {
            border: none;
            background: transparent;
            padding: 6px;
            cursor: pointer;
            color: var(--crm-muted);
            line-height: 1;
        }
        .crm-modal-close:hover {
            color: #0f172a;
        }
        .crm-modal-body {
            padding: 18px;
        }
        .crm-form-group {
            margin-bottom: 14px;
        }
        .crm-form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
        }
        .crm-form-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .crm-form-row .crm-form-group {
            flex: 1;
            min-width: 0;
            margin-bottom: 14px;
        }
        .crm-form-group input[type="text"],
        .crm-form-group textarea {
            width: 100%;
            border: 1px solid var(--crm-border);
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 14px;
        }
        .crm-form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        .crm-attach-label {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
        }
        .crm-dropzone {
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            padding: 28px 16px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.15s, background 0.15s;
            background: #fafafa;
        }
        .crm-dropzone:hover,
        .crm-dropzone.crm-drag {
            border-color: var(--crm-purple);
            background: var(--crm-purple-soft);
        }
        .crm-dropzone .crm-drop-icon {
            font-size: 28px;
            color: var(--crm-muted);
            margin-bottom: 8px;
        }
        .crm-dropzone p {
            margin: 0;
            font-size: 13px;
            color: var(--crm-muted);
        }
        .crm-file-list {
            margin-top: 10px;
            font-size: 12px;
            color: #475569;
            text-align: left;
        }
        .crm-file-list li {
            margin-bottom: 4px;
        }
        .crm-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 14px 18px;
            border-top: 1px solid var(--crm-border);
            background: #fafafa;
        }
        .crm-btn-secondary {
            border: 1px solid var(--crm-border);
            background: #fff;
            color: var(--crm-text);
            font-weight: 600;
            font-size: 13px;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
        }
        .crm-btn-primary {
            border: none;
            background: var(--crm-purple);
            color: #fff;
            font-weight: 600;
            font-size: 13px;
            padding: 8px 18px;
            border-radius: 8px;
            cursor: pointer;
        }
        .crm-btn-primary:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }
        .crm-form-error {
            font-size: 12px;
            color: #b91c1c;
            margin-top: 8px;
        }
        /* Contact & Groups */
        .crm-cg-wrap {
            margin-top: 4px;
        }
        .crm-cg-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
            padding: 12px 14px;
            background: #fff;
            border: 1px solid var(--crm-border);
            border-radius: 12px;
        }
        .crm-cg-toolbar-left {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 14px;
        }
        .crm-cg-input-name {
            min-width: 220px;
            max-width: 100%;
            border: 1px solid var(--crm-border);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 14px;
        }
        .crm-cg-active-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--crm-text);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
        }
        .crm-cg-toolbar-right {
            display: flex;
            gap: 10px;
        }
        .crm-btn-outline {
            border: 2px solid var(--crm-purple);
            background: #fff;
            color: var(--crm-purple);
            font-weight: 600;
            font-size: 13px;
            padding: 7px 16px;
            border-radius: 8px;
            cursor: pointer;
        }
        .crm-btn-outline:hover {
            background: var(--crm-purple-soft);
        }
        .crm-cg-columns {
            display: grid;
            grid-template-columns: minmax(280px, 38%) minmax(320px, 1fr);
            gap: 14px;
            align-items: start;
        }
        @media (max-width: 991px) {
            .crm-cg-columns {
                grid-template-columns: 1fr;
            }
        }
        .crm-cg-card {
            margin-bottom: 0;
        }
        .crm-cg-scroll {
            max-height: min(62vh, 640px);
            overflow: auto;
        }
        .crm-cg-table thead th {
            vertical-align: top;
        }
        .crm-cg-th-search {
            min-width: 140px;
        }
        .crm-cg-th-title {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--crm-text);
        }
        .crm-cg-search {
            width: 100%;
            max-width: 100%;
            border: 1px solid var(--crm-border);
            border-radius: 6px;
            padding: 5px 8px;
            font-size: 12px;
        }
        .crm-cg-status-cell {
            text-align: center;
        }
        .crm-cg-st-active {
            color: #16a34a;
            font-size: 18px;
        }
        .crm-cg-st-blocked {
            color: #dc2626;
            font-size: 18px;
        }
        #crmPanelContacts .crm-empty-cell {
            padding: 36px 16px !important;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="layout-content">
        <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
            <div class="crm-page">
                <div class="crm-top-bar">
                    <div class="crm-tabs" role="tablist" id="crmTabList">
                        <button type="button" class="crm-tab active" data-crm-tab="whatsapp" role="tab" aria-selected="true">Whatsapp</button>
                        <button type="button" class="crm-tab" data-crm-tab="email" role="tab" aria-selected="false">Email</button>
                        <button type="button" class="crm-tab" data-crm-tab="contacts" role="tab" aria-selected="false">Contact &amp; Groups</button>
                        <button type="button" class="crm-tab" data-crm-tab="templates" role="tab" aria-selected="false">Marketing Template</button>
                        <button type="button" class="crm-tab" data-crm-tab="tasks" role="tab" aria-selected="false">Task/Events</button>
                        <button type="button" class="crm-tab" data-crm-tab="notifications" role="tab" aria-selected="false">Notification Log</button>
                    </div>
                    <div class="crm-actions">
                        <button type="button" class="crm-icon-btn" id="crmFilterBtn" title="Filters">
                            <i class="feather icon-filter"></i>
                            <span class="crm-badge" id="crmFilterBadge">2</span>
                        </button>
                        <button type="button" class="crm-icon-btn" id="crmRefreshBtn" title="Refresh">
                            <i class="feather icon-refresh-cw"></i>
                        </button>
                        <button type="button" class="crm-btn-create" id="crmCreateBtn">Create</button>
                    </div>
                </div>

                <div id="crmPanelWhatsapp" class="crm-panel active" role="tabpanel" data-panel="whatsapp">
                    <?php crm_render_whatsapp_campaigns($crm_whatsapp_campaigns); ?>
                </div>
                <div id="crmPanelEmail" class="crm-panel" role="tabpanel" data-panel="email">
                    <?php crm_render_empty_grid('email'); ?>
                </div>
                <div id="crmPanelContacts" class="crm-panel" role="tabpanel" data-panel="contacts">
                    <?php include __DIR__ . '/includes/crm_contact_groups_panel.php'; ?>
                </div>
                <div id="crmPanelTemplates" class="crm-panel" role="tabpanel" data-panel="templates">
                    <?php crm_render_empty_grid('templates'); ?>
                </div>
                <div id="crmPanelTasks" class="crm-panel" role="tabpanel" data-panel="tasks">
                    <?php crm_render_empty_grid('tasks'); ?>
                </div>
                <div id="crmPanelNotifications" class="crm-panel" role="tabpanel" data-panel="notifications">
                    <?php crm_render_empty_grid('notifications'); ?>
                </div>

                <button type="button" class="crm-fab" id="crmNewTaskBtn">New Task / Event</button>

                <div id="crmCreateModal" class="crm-modal-backdrop" aria-hidden="true">
                    <div class="crm-modal" role="dialog" aria-modal="true" aria-labelledby="crmCreateTitle">
                        <div class="crm-modal-header">
                            <h2 id="crmCreateTitle">New WhatsApp campaign</h2>
                            <button type="button" class="crm-modal-close" id="crmModalClose" aria-label="Close"><i class="feather icon-x"></i></button>
                        </div>
                        <form id="crmCreateForm" novalidate>
                            <div class="crm-modal-body">
                                <div class="crm-form-row">
                                    <div class="crm-form-group">
                                        <label for="crmCustomerName">Customer Name</label>
                                        <input type="text" id="crmCustomerName" name="customer_name" maxlength="255" placeholder="Customer / contact name" autocomplete="name">
                                    </div>
                                    <div class="crm-form-group">
                                        <label for="crmContactNo">Contact No</label>
                                        <input type="text" id="crmContactNo" name="contact_no" maxlength="64" placeholder="Phone / WhatsApp number" inputmode="tel" autocomplete="tel">
                                    </div>
                                </div>
                                <div class="crm-form-group">
                                    <label for="crmCaption">Caption</label>
                                    <input type="text" id="crmCaption" name="caption" required maxlength="500" placeholder="Title shown in the list" autocomplete="off">
                                </div>
                                <div class="crm-form-group">
                                    <label for="crmMessage">Message</label>
                                    <textarea id="crmMessage" name="message" placeholder="Optional message body"></textarea>
                                </div>
                                <div class="crm-attach-label">Attachment</div>
                                <div class="crm-dropzone" id="crmDropzone" tabindex="0">
                                    <div class="crm-drop-icon"><i class="feather icon-paperclip"></i></div>
                                    <p>Drop files here or click to upload.</p>
                                    <input type="file" id="crmFileInput" accept="image/jpeg,image/jpg,image/png,image/webp,image/gif" multiple style="display:none">
                                </div>
                                <ul class="crm-file-list" id="crmFileList" style="list-style:none;padding-left:0;margin-bottom:0;"></ul>
                                <div class="crm-form-error" id="crmFormError" style="display:none;"></div>
                            </div>
                            <div class="crm-modal-footer">
                                <button type="button" class="crm-btn-secondary" id="crmModalCancel">Cancel</button>
                                <button type="submit" class="crm-btn-primary" id="crmModalSave">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var tabs = document.querySelectorAll('.crm-tab');
        var panels = {
            whatsapp: document.getElementById('crmPanelWhatsapp'),
            email: document.getElementById('crmPanelEmail'),
            contacts: document.getElementById('crmPanelContacts'),
            templates: document.getElementById('crmPanelTemplates'),
            tasks: document.getElementById('crmPanelTasks'),
            notifications: document.getElementById('crmPanelNotifications')
        };

        function showTab(key) {
            Object.keys(panels).forEach(function (k) {
                var p = panels[k];
                if (!p) return;
                p.classList.toggle('active', k === key);
            });
            tabs.forEach(function (btn) {
                var isActive = btn.getAttribute('data-crm-tab') === key;
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '#' + key);
            }
        }

        tabs.forEach(function (btn) {
            btn.addEventListener('click', function () {
                showTab(btn.getAttribute('data-crm-tab'));
            });
        });

        document.getElementById('crmRefreshBtn').addEventListener('click', function () {
            location.reload();
        });

        var hash = (window.location.hash || '').replace(/^#/, '');
        var valid = ['whatsapp', 'email', 'contacts', 'templates', 'tasks', 'notifications'];
        if (hash && valid.indexOf(hash) !== -1) {
            showTab(hash);
        }

        var modal = document.getElementById('crmCreateModal');
        var createBtn = document.getElementById('crmCreateBtn');
        var form = document.getElementById('crmCreateForm');
        var dropzone = document.getElementById('crmDropzone');
        var fileInput = document.getElementById('crmFileInput');
        var fileList = document.getElementById('crmFileList');
        var formError = document.getElementById('crmFormError');
        var crmFiles = [];

        function showError(msg) {
            if (!formError) return;
            if (msg) {
                formError.textContent = msg;
                formError.style.display = 'block';
            } else {
                formError.textContent = '';
                formError.style.display = 'none';
            }
        }

        function renderFileList() {
            if (!fileList) return;
            fileList.innerHTML = '';
            crmFiles.forEach(function (f, idx) {
                var li = document.createElement('li');
                li.textContent = f.name + ' (' + Math.round(f.size / 1024) + ' KB)';
                fileList.appendChild(li);
            });
        }

        function addFilesFromList(fileListObj) {
            if (!fileListObj || !fileListObj.length) return;
            var allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
            for (var i = 0; i < fileListObj.length; i++) {
                var f = fileListObj[i];
                var t = (f.type || '').toLowerCase();
                if (allowed.indexOf(t) === -1) continue;
                if (f.size > 5 * 1024 * 1024) continue;
                crmFiles.push(f);
            }
            renderFileList();
        }

        function openModal() {
            if (!modal) return;
            crmFiles = [];
            renderFileList();
            showError('');
            if (form) form.reset();
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeModal() {
            if (!modal) return;
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            crmFiles = [];
            renderFileList();
            showError('');
            if (form) form.reset();
        }

        if (createBtn) {
            createBtn.addEventListener('click', function () {
                openModal();
            });
        }
        document.getElementById('crmModalClose').addEventListener('click', closeModal);
        document.getElementById('crmModalCancel').addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });

        if (dropzone && fileInput) {
            dropzone.addEventListener('click', function (e) {
                if (e.target !== fileInput) fileInput.click();
            });
            fileInput.addEventListener('change', function () {
                addFilesFromList(fileInput.files);
                fileInput.value = '';
            });
            dropzone.addEventListener('dragover', function (e) {
                e.preventDefault();
                dropzone.classList.add('crm-drag');
            });
            dropzone.addEventListener('dragleave', function () {
                dropzone.classList.remove('crm-drag');
            });
            dropzone.addEventListener('drop', function (e) {
                e.preventDefault();
                dropzone.classList.remove('crm-drag');
                addFilesFromList(e.dataTransfer.files);
            });
        }

        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                showError('');
                var cap = (document.getElementById('crmCaption') || {}).value;
                cap = (cap || '').trim();
                if (!cap) {
                    showError('Caption is required.');
                    return;
                }
                var msg = (document.getElementById('crmMessage') || {}).value || '';
                var btn = document.getElementById('crmModalSave');
                if (btn) btn.disabled = true;
                var fd = new FormData();
                fd.append('caption', cap);
                fd.append('message', msg);
                fd.append('customer_name', ((document.getElementById('crmCustomerName') || {}).value || '').trim());
                fd.append('contact_no', ((document.getElementById('crmContactNo') || {}).value || '').trim());
                crmFiles.forEach(function (f) {
                    fd.append('images[]', f);
                });
                fetch('ajax/save-crm-whatsapp-campaign.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (btn) btn.disabled = false;
                        if (d && d.status === 'ok') {
                            closeModal();
                            window.location.reload();
                            return;
                        }
                        showError((d && d.message) ? d.message : 'Could not save.');
                    })
                    .catch(function () {
                        if (btn) btn.disabled = false;
                        showError('Network error.');
                    });
            });
        }
    })();

    (function () {
        var sn = document.getElementById('crmUserSearchName');
        var sc = document.getElementById('crmUserSearchContact');
        var body = document.getElementById('crmUserListBody');
        var selAll = document.getElementById('crmUserSelectAll');

        function applyUserFilter() {
            if (!body) return;
            var qn = (sn && sn.value ? String(sn.value) : '').toLowerCase().trim();
            var qc = (sc && sc.value ? String(sc.value) : '').toLowerCase().trim();
            var rows = body.querySelectorAll('tr.crm-user-row');
            rows.forEach(function (tr) {
                var ok = true;
                var dn = tr.getAttribute('data-name') || '';
                var dc = tr.getAttribute('data-contact') || '';
                if (qn && dn.indexOf(qn) === -1) ok = false;
                if (qc && dc.indexOf(qc) === -1) ok = false;
                tr.style.display = ok ? '' : 'none';
            });
        }

        if (sn) sn.addEventListener('input', applyUserFilter);
        if (sc) sc.addEventListener('input', applyUserFilter);

        if (selAll && body) {
            selAll.addEventListener('change', function () {
                var on = selAll.checked;
                body.querySelectorAll('tr.crm-user-row').forEach(function (tr) {
                    if (tr.style.display === 'none') return;
                    var cb = tr.querySelector('input.crm-user-cb');
                    if (cb) cb.checked = on;
                });
            });
        }

        var saveGroupBtn = document.getElementById('crmSaveGroupBtn');
        var newGroupBtn = document.getElementById('crmNewGroupBtn');
        var gName = document.getElementById('crmGroupName');
        var gAct = document.getElementById('crmGroupActive');

        if (newGroupBtn && gName && gAct) {
            newGroupBtn.addEventListener('click', function () {
                gName.value = '';
                gAct.checked = true;
                gName.focus();
            });
        }

        if (saveGroupBtn && gName && gAct) {
            saveGroupBtn.addEventListener('click', function () {
                var nm = (gName.value || '').trim();
                if (!nm) {
                    alert('Enter a group name.');
                    gName.focus();
                    return;
                }
                var fd = new FormData();
                fd.append('name', nm);
                fd.append('active', gAct.checked ? '1' : '0');
                fetch('ajax/save-crm-contact-group.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d && d.status === 'ok') {
                            window.location.reload();
                            return;
                        }
                        alert((d && d.message) ? d.message : 'Could not save group');
                    })
                    .catch(function () {
                        alert('Network error');
                    });
            });
        }
    })();
    </script>
</body>
</html>
