<?php

/**
 * Render Employee Management page body by $employee_page_key.
 * Expects $em bootstrap array from auragold_em_bootstrap_page().
 */

function auragold_em_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function auragold_em_employee_options(array $employees, $selected = 0, bool $selfOnly = false): string
{
    $html = '';
    if (!$selfOnly || count($employees) !== 1) {
        $html .= '<option value="">— Select employee —</option>';
    }
    foreach ($employees as $emp) {
        $id = (int) ($emp['id'] ?? 0);
        $label = auragold_em_h(auragold_em_employee_name($emp) . ' (' . ($emp['employee_code'] ?? '') . ')');
        $sel = ((int) $selected === $id || ($selfOnly && count($employees) === 1)) ? ' selected' : '';
        $salary = number_format((float) ($emp['basic_salary'] ?? 0), 2, '.', '');
        $html .= '<option value="' . $id . '" data-basic-salary="' . $salary . '"' . $sel . '>' . $label . '</option>';
    }
    return $html;
}

function auragold_em_payroll_employee_options(array $employees, $selected = 0, bool $selfOnly = false): string
{
    $html = '';
    if (!$selfOnly || count($employees) !== 1) {
        $html .= '<option value="">Select value</option>';
    }
    foreach ($employees as $emp) {
        $id = (int) ($emp['id'] ?? 0);
        $label = auragold_em_h(auragold_em_employee_name($emp) . ' (' . ($emp['employee_code'] ?? '') . ')');
        $sel = ((int) $selected === $id || ($selfOnly && count($employees) === 1)) ? ' selected' : '';
        $salary = number_format((float) ($emp['basic_salary'] ?? 0), 2, '.', '');
        $join = !empty($emp['joining_date']) ? date('d-m-Y', strtotime((string) $emp['joining_date'])) : '';
        $html .= '<option value="' . $id . '"'
            . ' data-basic-salary="' . auragold_em_h($salary) . '"'
            . ' data-employee-code="' . auragold_em_h((string) ($emp['employee_code'] ?? '')) . '"'
            . ' data-employee-name="' . auragold_em_h(auragold_em_employee_name($emp)) . '"'
            . ' data-phone="' . auragold_em_h((string) ($emp['phone'] ?? '')) . '"'
            . ' data-department="' . auragold_em_h((string) ($emp['department_name'] ?? '')) . '"'
            . ' data-designation="' . auragold_em_h((string) ($emp['designation_name'] ?? '')) . '"'
            . ' data-joining-date="' . auragold_em_h($join) . '"'
            . ' data-monthly-salary="' . auragold_em_h($salary) . '"'
            . $sel . '>' . $label . '</option>';
    }

    return $html;
}

function auragold_em_badge(string $status): string
{
    $s = strtolower($status);
    $cls = 'em-badge-gray';
    if (in_array($s, ['active', 'present', 'approved', 'completed', 'paid'], true)) {
        $cls = 'em-badge-green';
    } elseif (in_array($s, ['pending', 'open', 'draft', 'in progress', 'half day'], true)) {
        $cls = 'em-badge-yellow';
    } elseif (in_array($s, ['rejected', 'absent', 'cancelled'], true)) {
        $cls = 'em-badge-red';
    }
    return '<span class="em-badge ' . $cls . '">' . auragold_em_h($status) . '</span>';
}

function auragold_em_render_page(string $pageKey, array $em, $conn): void
{
    $employees = $em['employees'] ?? [];
    $branch_id = (int) ($em['branch_id'] ?? 0);
    $isEmAdmin = !empty($em['is_em_admin']);
    $myEmployeeId = (int) ($em['my_employee_id'] ?? 0);
    $scopeEmployeeId = $isEmAdmin ? 0 : $myEmployeeId;
    $empOptsSelf = !$isEmAdmin;
    $empSelectAttrs = $empOptsSelf ? ' disabled' : '';

    switch ($pageKey) {
        case 'employee_dashboard':
            $stats = auragold_em_dashboard_stats($conn, $branch_id, $scopeEmployeeId);
            $recentLeave = array_slice(auragold_em_get_leave_requests($conn, $branch_id, '', $scopeEmployeeId), 0, 5);
            $recentTasks = array_slice(auragold_em_get_tasks($conn, $branch_id, '', $scopeEmployeeId), 0, 5);
            ?>
            <div id="emAlert" class="em-alert"></div>
            <?php if (!$isEmAdmin): ?>
            <p class="em-lead" style="margin-top:0;">Showing your employee records only.</p>
            <?php endif; ?>
            <div class="em-stats">
                <div class="em-stat"><div class="em-stat-label"><?php echo $isEmAdmin ? 'Active Employees' : 'My Profile'; ?></div><div class="em-stat-value"><?php echo (int) $stats['total_employees']; ?></div></div>
                <div class="em-stat"><div class="em-stat-label">Present Today</div><div class="em-stat-value"><?php echo (int) $stats['present_today']; ?></div></div>
                <div class="em-stat"><div class="em-stat-label">On Leave Today</div><div class="em-stat-value"><?php echo (int) $stats['on_leave_today']; ?></div></div>
                <div class="em-stat"><div class="em-stat-label">Pending Leave</div><div class="em-stat-value"><?php echo (int) $stats['pending_leave']; ?></div></div>
                <div class="em-stat"><div class="em-stat-label">Open Tasks</div><div class="em-stat-value"><?php echo (int) $stats['open_tasks']; ?></div></div>
                <div class="em-stat"><div class="em-stat-label">Payroll This Month</div><div class="em-stat-value"><?php echo auragold_em_h(auragold_em_format_money($stats['payroll_month_total'])); ?></div><div class="em-stat-sub"><?php echo auragold_em_h(date('F Y')); ?></div></div>
            </div>
            <div class="em-card">
                <h3 style="margin:0 0 10px;font-size:1rem;">Quick Links</h3>
                <div class="em-quick-links">
                    <a href="employee-attendance.php"><i class="feather icon-clock"></i> Attendance</a>
                    <a href="employee-advance.php"><i class="feather icon-pocket"></i> Advance</a>
                    <a href="employee-advance-request.php"><i class="feather icon-check-circle"></i> Advance Request</a>
                    <a href="employee-incentive.php"><i class="feather icon-award"></i> Incentive</a>
                    <a href="employee-salary-payroll.php"><i class="feather icon-credit-card"></i> Salary</a>
                    <a href="employee-reports.php"><i class="feather icon-bar-chart-2"></i> Reports</a>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px;">
                <div class="em-card">
                    <h3 style="margin:0 0 10px;font-size:1rem;">Recent Leave Requests</h3>
                    <div class="em-table-wrap"><table class="em-table"><thead><tr><th>Employee</th><th>Period</th><th>Status</th></tr></thead><tbody>
                    <?php if (empty($recentLeave)): ?><tr><td colspan="3" class="em-empty">No leave requests yet.</td></tr>
                    <?php else: foreach ($recentLeave as $row): ?>
                    <tr><td><?php echo auragold_em_h(auragold_em_employee_name($row)); ?></td><td><?php echo auragold_em_h(auragold_em_format_date($row['from_date'] ?? '') . ' – ' . auragold_em_format_date($row['to_date'] ?? '')); ?></td><td><?php echo auragold_em_badge((string) ($row['status'] ?? '')); ?></td></tr>
                    <?php endforeach; endif; ?>
                    </tbody></table></div>
                </div>
                <div class="em-card">
                    <h3 style="margin:0 0 10px;font-size:1rem;">Recent Tasks</h3>
                    <div class="em-table-wrap"><table class="em-table"><thead><tr><th>Task</th><th>Employee</th><th>Status</th></tr></thead><tbody>
                    <?php if (empty($recentTasks)): ?><tr><td colspan="3" class="em-empty">No tasks yet.</td></tr>
                    <?php else: foreach ($recentTasks as $row): ?>
                    <tr><td><?php echo auragold_em_h($row['title'] ?? ''); ?></td><td><?php echo auragold_em_h(auragold_em_employee_name($row)); ?></td><td><?php echo auragold_em_badge((string) ($row['status'] ?? '')); ?></td></tr>
                    <?php endforeach; endif; ?>
                    </tbody></table></div>
                </div>
            </div>
            <?php
            break;

        case 'employee_documents':
            $docs = auragold_em_get_documents($conn, $branch_id, $scopeEmployeeId);
            ?>
            <div id="emAlert" class="em-alert"></div>
            <div class="em-toolbar">
                <div class="em-toolbar-left"><strong><?php echo count($docs); ?></strong> document(s)</div>
                <div class="em-toolbar-right"><button type="button" class="em-btn em-btn-primary" onclick="EmApp.openModal('emDocModal')"><i class="feather icon-plus"></i> Add Document</button></div>
            </div>
            <div class="em-table-wrap"><table class="em-table"><thead><tr><th>Employee</th><th>Type</th><th>Title</th><th>Expiry</th><th>File</th><th>Actions</th></tr></thead><tbody>
            <?php if (empty($docs)): ?><tr><td colspan="6" class="em-empty">No documents uploaded yet.</td></tr>
            <?php else: foreach ($docs as $row): ?>
            <tr>
                <td><?php echo auragold_em_h(auragold_em_employee_name($row) . ' (' . ($row['employee_code'] ?? '') . ')'); ?></td>
                <td><?php echo auragold_em_h($row['doc_type'] ?? ''); ?></td>
                <td><?php echo auragold_em_h($row['doc_title'] ?? ''); ?></td>
                <td><?php echo auragold_em_h(auragold_em_format_date($row['expiry_date'] ?? '')); ?></td>
                <td><?php if (!empty($row['file_path'])): ?><a href="<?php echo auragold_em_h($row['file_path']); ?>" target="_blank" rel="noopener"><?php echo auragold_em_h($row['file_name'] ?: 'View'); ?></a><?php else: ?>—<?php endif; ?></td>
                <td class="em-actions"><button type="button" class="danger" data-del-doc="<?php echo (int) $row['id']; ?>">Delete</button></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody></table></div>
            <div id="emDocModal" class="em-modal-backdrop"><div class="em-modal"><div class="em-modal-head"><h3>Add Document</h3><button type="button" class="em-close">&times;</button></div>
            <form id="emDocForm" enctype="multipart/form-data"><div class="em-modal-body"><div class="em-form-grid">
                <div class="em-field"><label>Employee</label><select name="employee_id" required<?php echo $empSelectAttrs; ?>><?php echo auragold_em_employee_options($employees, $myEmployeeId, $empOptsSelf); ?></select><?php if ($empOptsSelf && $myEmployeeId > 0): ?><input type="hidden" name="employee_id" value="<?php echo (int) $myEmployeeId; ?>"><?php endif; ?></div>
                <div class="em-field"><label>Document Type</label><select name="doc_type"><option value="ID Proof">ID Proof</option><option value="Contract">Contract</option><option value="Certificate">Certificate</option><option value="Other">Other</option></select></div>
                <div class="em-field"><label>Title</label><input type="text" name="doc_title" required></div>
                <div class="em-field"><label>Expiry Date</label><input type="date" name="expiry_date"></div>
                <div class="em-field" style="grid-column:1/-1"><label>Upload File</label><input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></div>
                <div class="em-field" style="grid-column:1/-1"><label>Notes</label><textarea name="notes"></textarea></div>
            </div></div><div class="em-modal-foot"><button type="button" class="em-btn em-btn-light em-close">Cancel</button><button type="submit" class="em-btn em-btn-primary">Save Document</button></div></form></div></div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var alertEl = document.getElementById('emAlert');
                document.getElementById('emDocForm').addEventListener('submit', function (e) {
                    e.preventDefault();
                    var fd = new FormData(this);
                    EmApp.post('save_document', fd, true).then(function (r) {
                        EmApp.showAlert(alertEl, r.message, r.success);
                        if (r.success) setTimeout(function(){ EmApp.reload(); }, 700);
                    });
                });
                EmApp.qsa('[data-del-doc]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (!confirm('Delete this document?')) return;
                        EmApp.post('delete_document', { id: btn.getAttribute('data-del-doc') }).then(function (r) {
                            EmApp.showAlert(alertEl, r.message, r.success);
                            if (r.success) setTimeout(function(){ EmApp.reload(); }, 700);
                        });
                    });
                });
            });
            </script>
            <?php
            break;

        case 'employee_attendance':
            $today = date('Y-m-d');
            $viewDate = !empty($_GET['date']) ? preg_replace('/[^0-9-]/', '', (string) $_GET['date']) : $today;
            if ($viewDate === '') {
                $viewDate = $today;
            }
            $isToday = ($viewDate === $today);
            $board = auragold_em_get_attendance_board($conn, $branch_id, $viewDate, $scopeEmployeeId);
            $staleHrs = auragold_em_attendance_stale_hours();
            ?>
            <div id="emAlert" class="em-alert"></div>
            <div class="em-card" style="margin-bottom:14px;padding:12px 16px;">
                <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between;">
                    <div>
                        <div style="font-size:11px;color:#64748b;font-weight:600;text-transform:uppercase;">Current Time (24h)</div>
                        <div class="em-live-clock" id="emLiveClock"><?php echo auragold_em_h(date('d M Y H:i:s')); ?></div>
                    </div>
                    <div style="font-size:12px;color:#64748b;max-width:520px;line-height:1.5;">
                        Day / night shift supported (24-hour punch). If punch out is missing for <strong><?php echo (int) $staleHrs; ?> hours</strong>, the session is marked <strong>Absent</strong> and the employee can punch in again for today.
                    </div>
                </div>
            </div>
            <div class="em-toolbar">
                <div class="em-toolbar-left">
                    <label style="font-size:12px;font-weight:600;">Attendance Date</label>
                    <input type="date" id="emAttDate" value="<?php echo auragold_em_h($viewDate); ?>" style="margin-left:8px;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;">
                    <?php if (!$isToday): ?>
                    <span class="em-badge em-badge-yellow" style="margin-left:8px;">View only — punch on today</span>
                    <?php else: ?>
                    <span class="em-badge em-badge-green" style="margin-left:8px;">Today — punch enabled</span>
                    <?php endif; ?>
                </div>
                <div class="em-toolbar-right">
                    <button type="button" class="em-btn em-btn-light" id="emAttGoToday">Today</button>
                </div>
            </div>
            <div class="em-table-wrap">
                <table class="em-table" id="emAttTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Shift</th>
                            <th>Status</th>
                            <th>Punch In (24h)</th>
                            <th>Punch Out (24h)</th>
                            <th>Duration</th>
                            <th style="min-width:200px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($board)): ?>
                    <tr><td colspan="9" class="em-empty">No employees for this branch. Add active users in Settings → Users, or add employees in Employee Settings.</td></tr>
                    <?php else: foreach ($board as $row): ?>
                    <tr data-employee-id="<?php echo (int) $row['employee_id']; ?>">
                        <td><?php echo auragold_em_h($row['employee_code']); ?></td>
                        <td><strong><?php echo auragold_em_h($row['name']); ?></strong></td>
                        <td><?php echo auragold_em_h($row['department_name']); ?></td>
                        <td><?php echo auragold_em_h($row['shift_name']); ?></td>
                        <td><?php echo auragold_em_badge(str_replace('Present (In)', 'Present', (string) $row['status'])); ?><?php if (!empty($row['open_punch'])): ?> <span class="em-badge em-badge-blue">Open</span><?php endif; ?></td>
                        <td class="em-punch-in-cell"><?php echo auragold_em_h($row['punch_in_display']); ?></td>
                        <td class="em-punch-out-cell"><?php echo auragold_em_h($row['punch_out_display']); ?></td>
                        <td class="em-duration-cell"><?php echo auragold_em_h($row['duration']); ?></td>
                        <td class="em-punch-actions">
                            <?php if ($isToday && !empty($row['can_punch_in'])): ?>
                            <button type="button" class="em-btn em-btn-success em-punch-in-btn" data-employee-id="<?php echo (int) $row['employee_id']; ?>"><i class="feather icon-log-in"></i> Punch In</button>
                            <?php endif; ?>
                            <?php if ($isToday && !empty($row['can_punch_out'])): ?>
                            <button type="button" class="em-btn em-btn-primary em-punch-out-btn" data-employee-id="<?php echo (int) $row['employee_id']; ?>"><i class="feather icon-log-out"></i> Punch Out</button>
                            <?php endif; ?>
                            <?php if ($isToday && empty($row['can_punch_in']) && empty($row['can_punch_out']) && ($row['status'] ?? '') === '—'): ?>
                            <span style="font-size:11px;color:#94a3b8;">No punch</span>
                            <?php elseif (!$isToday): ?>
                            <span style="font-size:11px;color:#94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var alertEl = document.getElementById('emAlert');
                var clockEl = document.getElementById('emLiveClock');
                if (clockEl) {
                    setInterval(function () {
                        var d = new Date();
                        var p = function (n) { return n < 10 ? '0' + n : '' + n; };
                        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                        clockEl.textContent = p(d.getDate()) + ' ' + months[d.getMonth()] + ' ' + d.getFullYear() + ' '
                            + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
                    }, 1000);
                }
                document.getElementById('emAttDate').addEventListener('change', function () {
                    window.location.href = 'employee-attendance.php?date=' + encodeURIComponent(this.value);
                });
                document.getElementById('emAttGoToday').addEventListener('click', function () {
                    window.location.href = 'employee-attendance.php';
                });
                function doPunch(action, employeeId, btn) {
                    if (btn) btn.disabled = true;
                    EmApp.post(action, { employee_id: employeeId, attendance_date: '<?php echo auragold_em_h($today); ?>' }).then(function (r) {
                        EmApp.showAlert(alertEl, r.message, r.success);
                        if (r.success) {
                            setTimeout(function () { EmApp.reload(); }, 600);
                        } else if (btn) {
                            btn.disabled = false;
                        }
                    }).catch(function () {
                        if (btn) btn.disabled = false;
                    });
                }
                EmApp.qsa('.em-punch-in-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        doPunch('punch_in', btn.getAttribute('data-employee-id'), btn);
                    });
                });
                EmApp.qsa('.em-punch-out-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        doPunch('punch_out', btn.getAttribute('data-employee-id'), btn);
                    });
                });
            });
            </script>
            <?php
            break;

        case 'leave_management':
            $leaveRows = auragold_em_get_leave_requests($conn, $branch_id, '', $scopeEmployeeId);
            $leaveTypes = $em['leave_types'] ?? [];
            ?>
            <div id="emAlert" class="em-alert"></div>
            <div class="em-toolbar"><div class="em-toolbar-left"><strong><?php echo count($leaveRows); ?></strong> leave request(s)</div>
            <div class="em-toolbar-right"><button type="button" class="em-btn em-btn-primary" onclick="EmApp.openModal('emLeaveModal')">Apply Leave</button></div></div>
            <div class="em-table-wrap"><table class="em-table"><thead><tr><th>Employee</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            <?php if (empty($leaveRows)): ?><tr><td colspan="7" class="em-empty">No leave requests yet.</td></tr>
            <?php else: foreach ($leaveRows as $row): ?>
            <tr>
                <td><?php echo auragold_em_h(auragold_em_employee_name($row)); ?></td>
                <td><?php echo auragold_em_h($row['leave_type_name'] ?? '—'); ?></td>
                <td><?php echo auragold_em_h(auragold_em_format_date($row['from_date'] ?? '')); ?></td>
                <td><?php echo auragold_em_h(auragold_em_format_date($row['to_date'] ?? '')); ?></td>
                <td class="num"><?php echo auragold_em_h(number_format((float)($row['days'] ?? 0), 2)); ?></td>
                <td><?php echo auragold_em_badge((string)($row['status'] ?? '')); ?></td>
                <td class="em-actions">
                    <?php if ($isEmAdmin && ($row['status'] ?? '') === 'Pending'): ?>
                    <button type="button" data-leave-status="<?php echo (int)$row['id']; ?>" data-status="Approved">Approve</button>
                    <button type="button" class="danger" data-leave-status="<?php echo (int)$row['id']; ?>" data-status="Rejected">Reject</button>
                    <?php endif; ?>
                    <?php if ($isEmAdmin || (int)($row['employee_id'] ?? 0) === $myEmployeeId): ?>
                    <button type="button" class="danger" data-del-leave="<?php echo (int)$row['id']; ?>">Delete</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody></table></div>
            <div id="emLeaveModal" class="em-modal-backdrop"><div class="em-modal"><div class="em-modal-head"><h3>Apply Leave</h3><button type="button" class="em-close">&times;</button></div>
            <form id="emLeaveForm"><div class="em-modal-body"><div class="em-form-grid">
                <div class="em-field"><label>Employee</label><select name="employee_id" required<?php echo $empSelectAttrs; ?>><?php echo auragold_em_employee_options($employees, $myEmployeeId, $empOptsSelf); ?></select><?php if ($empOptsSelf && $myEmployeeId > 0): ?><input type="hidden" name="employee_id" value="<?php echo (int) $myEmployeeId; ?>"><?php endif; ?></div>
                <div class="em-field"><label>Leave Type</label><select name="leave_type_id"><option value="0">—</option><?php foreach ($leaveTypes as $lt): ?><option value="<?php echo (int)$lt['id']; ?>"><?php echo auragold_em_h($lt['name']); ?></option><?php endforeach; ?></select></div>
                <div class="em-field"><label>From Date</label><input type="date" name="from_date" required></div>
                <div class="em-field"><label>To Date</label><input type="date" name="to_date" required></div>
                <div class="em-field"><label>Days</label><input type="number" step="0.5" min="0.5" name="days" value="1"></div>
                <div class="em-field" style="grid-column:1/-1"><label>Reason</label><textarea name="reason"></textarea></div>
            </div></div><div class="em-modal-foot"><button type="button" class="em-btn em-btn-light em-close">Cancel</button><button type="submit" class="em-btn em-btn-primary">Submit</button></div></form></div></div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var alertEl = document.getElementById('emAlert');
                document.getElementById('emLeaveForm').addEventListener('submit', function (e) {
                    e.preventDefault();
                    var fd = new FormData(this); var data = {}; fd.forEach(function(v,k){ data[k]=v; });
                    EmApp.post('save_leave', data).then(function (r) { EmApp.showAlert(alertEl, r.message, r.success); if (r.success) setTimeout(function(){ EmApp.reload(); }, 700); });
                });
                EmApp.qsa('[data-leave-status]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        EmApp.post('leave_status', { id: btn.getAttribute('data-leave-status'), status: btn.getAttribute('data-status') }).then(function (r) {
                            EmApp.showAlert(alertEl, r.message, r.success); if (r.success) setTimeout(function(){ EmApp.reload(); }, 700);
                        });
                    });
                });
                EmApp.qsa('[data-del-leave]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (!confirm('Delete leave request?')) return;
                        EmApp.post('delete_leave', { id: btn.getAttribute('data-del-leave') }).then(function (r) {
                            EmApp.showAlert(alertEl, r.message, r.success); if (r.success) setTimeout(function(){ EmApp.reload(); }, 700);
                        });
                    });
                });
            });
            </script>
            <?php
            break;

        case 'salary_payroll':
        case 'employee_salary':
            $month = !empty($_GET['month']) ? preg_replace('/[^0-9-]/', '', (string) $_GET['month']) : date('Y-m');
            if ($month === '') {
                $month = date('Y-m');
            }
            $payroll = auragold_em_get_payroll($conn, $branch_id, $month, $scopeEmployeeId);
            $monthAdvances = auragold_em_approved_advances_for_month($conn, $branch_id, $month, $scopeEmployeeId);
            $advByEmp = $monthAdvances['by_employee'] ?? [];
            $payMonthParts = explode('-', $month, 2);
            $payYearDefault = $payMonthParts[0] ?? date('Y');
            $payMonDefault = isset($payMonthParts[1]) ? (int) $payMonthParts[1] : (int) date('n');
            $payMonthOptions = [
                1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
                7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
            ];
            ?>
            <div id="emAlert" class="em-alert"></div>
            <div class="em-toolbar">
                <div class="em-toolbar-left">
                    <label style="font-size:12px;font-weight:600;">Month</label>
                    <input type="month" id="emPayMonth" value="<?php echo auragold_em_h($month); ?>" style="margin-left:8px;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;">
                    <span style="margin-left:14px;font-size:13px;color:#475569;">
                        Advances approved:
                        <strong><?php echo (int) ($monthAdvances['count'] ?? 0); ?></strong>
                        <?php if ((float) ($monthAdvances['amount'] ?? 0) > 0): ?>
                        · <?php echo auragold_em_h(auragold_em_format_money($monthAdvances['amount'])); ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="em-toolbar-right">
                    <?php if ($isEmAdmin): ?>
                    <button type="button" class="em-btn em-btn-light" id="emGenPayroll">Generate from Employees</button>
                    <button type="button" class="em-btn em-btn-primary" id="emAddPayrollBtn">Add Payroll</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="em-table-wrap"><table class="em-table"><thead><tr><th>Employee</th><th>Month</th><th>Basic</th><th>Allowances</th><th>Advances Approved</th><th>Deductions</th><th>Net</th><th>Status</th><th>Actions</th></tr></thead><tbody id="emPayBody">
            <?php if (empty($payroll)): ?><tr><td colspan="9" class="em-empty">No payroll records for this month.</td></tr>
            <?php else: foreach ($payroll as $row):
                $eid = (int) ($row['employee_id'] ?? 0);
                $advInfo = $advByEmp[$eid] ?? ['count' => 0, 'amount' => 0.0];
                $advCount = (int) ($advInfo['count'] ?? 0);
                $advAmt = (float) ($advInfo['amount'] ?? 0);
            ?>
            <tr>
                <td><?php echo auragold_em_h(auragold_em_employee_name($row)); ?></td>
                <td><?php echo auragold_em_h($row['payroll_month'] ?? ''); ?></td>
                <td class="num"><?php echo auragold_em_h(auragold_em_format_money($row['basic_salary'] ?? 0)); ?></td>
                <td class="num"><?php echo auragold_em_h(auragold_em_format_money($row['allowances'] ?? 0)); ?></td>
                <td class="num"><?php if ($advCount > 0): ?><strong><?php echo $advCount; ?></strong> · <?php echo auragold_em_h(auragold_em_format_money($advAmt)); ?><?php else: ?>0<?php endif; ?></td>
                <td class="num"><?php echo auragold_em_h(auragold_em_format_money($row['deductions'] ?? 0)); ?></td>
                <td class="num"><strong><?php echo auragold_em_h(auragold_em_format_money($row['net_salary'] ?? 0)); ?></strong></td>
                <td><?php echo auragold_em_badge((string)($row['status'] ?? '')); ?></td>
                <td class="em-actions">
                    <a href="employee-salary-slip.php?id=<?php echo (int) $row['id']; ?>"
                        target="_blank"
                        title="Print salary slip"
                        aria-label="Print salary slip"
                        style="display:inline-flex;align-items:center;justify-content:center;">
                        <i class="feather icon-printer"></i>
                    </a>
                    <?php if ($isEmAdmin): ?>
                    <?php if (strcasecmp((string) ($row['status'] ?? ''), 'Draft') === 0):
                        $payDetail = auragold_em_payroll_detail_decode($row['payroll_detail_json'] ?? '');
                        $payDetailJson = htmlspecialchars(json_encode($payDetail, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                    ?>
                    <button type="button"
                        data-edit-pay="<?php echo (int) $row['id']; ?>"
                        data-employee-id="<?php echo (int) ($row['employee_id'] ?? 0); ?>"
                        data-payroll-month="<?php echo auragold_em_h((string) ($row['payroll_month'] ?? '')); ?>"
                        data-basic-salary="<?php echo auragold_em_h(number_format((float) ($row['basic_salary'] ?? 0), 2, '.', '')); ?>"
                        data-final-net="<?php echo auragold_em_h(number_format((float) ($row['net_salary'] ?? 0), 2, '.', '')); ?>"
                        data-payroll-detail="<?php echo $payDetailJson; ?>"
                        data-status="<?php echo auragold_em_h((string) ($row['status'] ?? 'Draft')); ?>">Edit</button>
                    <?php endif; ?>
                    <button type="button" class="danger" data-del-pay="<?php echo (int)$row['id']; ?>">Delete</button>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody></table></div>
            <div id="emPayModal" class="em-modal-backdrop">
                <div class="em-modal em-modal-payroll">
                    <div class="em-modal-head">
                        <h3 id="emPayModalTitle">Add Payroll</h3>
                        <button type="button" class="em-close" aria-label="Close">&times;</button>
                    </div>
                    <form id="emPayForm">
                        <div class="em-modal-body">
                            <input type="hidden" name="id" id="emPayId" value="0">
                            <input type="hidden" name="payroll_month" id="emPayMonthHidden" value="<?php echo auragold_em_h($month); ?>">
                            <input type="hidden" name="status" value="Draft">
                            <div class="em-payroll-detail-box">
                                <div class="em-payroll-detail-title">Employee Detail</div>
                                <div class="em-payroll-form-grid">
                                    <div class="em-field em-field-req em-payroll-col-4"><label>Employee</label><select name="employee_id" id="emPayEmployee" required<?php echo $empSelectAttrs; ?>><?php echo auragold_em_payroll_employee_options($employees, $myEmployeeId, $empOptsSelf); ?></select><?php if ($empOptsSelf && $myEmployeeId > 0): ?><input type="hidden" name="employee_id" value="<?php echo (int) $myEmployeeId; ?>"><?php endif; ?></div>
                                    <div class="em-field"><label>Employee ID</label><input type="text" id="emPayEmpCode" readonly tabindex="-1"></div>
                                    <div class="em-field"><label>Employee Name</label><input type="text" id="emPayEmpName" readonly tabindex="-1"></div>
                                    <div class="em-field"><label>Mobile No</label><input type="text" id="emPayMobile" readonly tabindex="-1"></div>
                                    <div class="em-field"><label>Department</label><input type="text" id="emPayDepartment" readonly tabindex="-1"></div>
                                    <div class="em-field"><label>Designation</label><input type="text" id="emPayDesignation" readonly tabindex="-1"></div>
                                    <div class="em-field"><label>Date of Joining</label><input type="text" id="emPayJoining" readonly tabindex="-1"></div>
                                    <div class="em-field"><label>Month</label><select name="payroll_month_part" id="emPayMonthPart"><?php foreach ($payMonthOptions as $num => $label): ?><option value="<?php echo sprintf('%02d', (int) $num); ?>"<?php echo (int) $num === (int) $payMonDefault ? ' selected' : ''; ?>><?php echo auragold_em_h($label); ?></option><?php endforeach; ?></select></div>
                                    <div class="em-field"><label>Year</label><select name="payroll_year" id="emPayYear"><?php for ($y = (int) date('Y') - 1; $y <= (int) date('Y') + 2; $y++): ?><option value="<?php echo $y; ?>"<?php echo (string) $y === (string) $payYearDefault ? ' selected' : ''; ?>><?php echo $y; ?></option><?php endfor; ?></select></div>
                                    <div class="em-field"><label>No. of Days</label><input type="number" step="1" min="0" name="no_of_days" id="emPayNoDays" value="0" readonly tabindex="-1"></div>
                                    <div class="em-field"><label>Present Days</label><input type="number" step="1" min="0" name="present_days" id="emPayPresent" value="0" class="em-payroll-stat-input"></div>
                                    <div class="em-field"><label>Absent Days</label><input type="number" step="1" min="0" name="absent_days" id="emPayAbsent" value="0" class="em-payroll-stat-input" readonly tabindex="-1"></div>
                                    <div class="em-field"><label>Monthly Salary</label><input type="number" step="0.01" name="monthly_salary" id="emPayMonthly" value="0"></div>
                                    <div class="em-field"><label>Gross Salary</label><input type="number" step="0.01" name="gross_salary" id="emPayGross" value="0"></div>
                                    <div class="em-field"><label>Basic Salary</label><input type="number" step="0.01" name="basic_salary" id="emPayBasic" value="0"></div>
                                    <div class="em-field"><label>HRA</label><input type="number" step="0.01" name="hra" id="emPayHra" value="0"></div>
                                    <div class="em-field"><label>DA</label><input type="number" step="0.01" name="da" id="emPayDa" value="0"></div>
                                    <div class="em-field"><label>Conveyance</label><input type="number" step="0.01" name="conveyance" id="emPayConveyance" value="0"></div>
                                    <div class="em-field"><label>Professional Tax</label><input type="number" step="0.01" name="professional_tax" id="emPayProfTax" value="0"></div>
                                    <div class="em-field"><label>PF</label><input type="number" step="0.01" name="pf" id="emPayPf" value="0"></div>
                                    <div class="em-field"><label>ESIC</label><input type="number" step="0.01" name="esic" id="emPayEsic" value="0"></div>
                                    <div class="em-field"><label>TDS</label><input type="number" step="0.01" name="tds" id="emPayTds" value="0"></div>
                                    <div class="em-field"><label>Advance Salary</label><input type="number" step="0.01" name="advance_salary" id="emPayAdvance" value="0"></div>
                                    <div class="em-field"><label>Other Deduction</label><input type="number" step="0.01" name="other_deduction" id="emPayOtherDed" value="0"></div>
                                    <div class="em-field"><label>UAN No</label><input type="text" name="uan_no" id="emPayUan" value=""></div>
                                    <div class="em-field"><label>ESIC No</label><input type="text" name="esic_no" id="emPayEsicNo" value=""></div>
                                    <div class="em-field"><label>Salary Arrears</label><input type="number" step="0.01" name="salary_arrears" id="emPayArrears" value="0"></div>
                                    <div class="em-field"><label>Final Net Salary</label><input type="number" step="0.01" name="final_net_salary" id="emPayFinalNet" value="0.00" readonly></div>
                                </div>
                            </div>
                        </div>
                        <div class="em-modal-foot em-payroll-foot">
                            <button type="submit" class="em-btn em-btn-save-payroll" id="emPaySubmitBtn">Save</button>
                        </div>
                    </form>
                </div>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var alertEl = document.getElementById('emAlert');
                var payForm = document.getElementById('emPayForm');
                var payEmployee = document.getElementById('emPayEmployee');
                var payBasic = document.getElementById('emPayBasic');
                var payMonthly = document.getElementById('emPayMonthly');
                var payGross = document.getElementById('emPayGross');
                var payFinalNet = document.getElementById('emPayFinalNet');
                var payMonthPart = document.getElementById('emPayMonthPart');
                var payYear = document.getElementById('emPayYear');
                var payMonthHidden = document.getElementById('emPayMonthHidden');
                var payNoDays = document.getElementById('emPayNoDays');
                var payPresent = document.getElementById('emPayPresent');
                var payAbsent = document.getElementById('emPayAbsent');
                var payAdvance = document.getElementById('emPayAdvance');
                var payrollSkipStats = false;
                var calcInputs = ['emPayBasic', 'emPayHra', 'emPayDa', 'emPayConveyance', 'emPayArrears', 'emPayProfTax', 'emPayPf', 'emPayEsic', 'emPayTds', 'emPayAdvance', 'emPayOtherDed'];

                function num(id) {
                    var el = document.getElementById(id);
                    return el ? (parseFloat(el.value || '0') || 0) : 0;
                }
                function setVal(id, val) {
                    var el = document.getElementById(id);
                    if (el) el.value = val;
                }
                function syncPayrollMonthHidden() {
                    if (!payMonthPart || !payYear || !payMonthHidden) return;
                    payMonthHidden.value = payYear.value + '-' + payMonthPart.value;
                }
                function daysInSelectedMonth() {
                    if (!payMonthPart || !payYear) return 0;
                    var y = parseInt(payYear.value, 10) || new Date().getFullYear();
                    var m = parseInt(payMonthPart.value, 10) || 1;
                    return new Date(y, m, 0).getDate();
                }
                function updateNoOfDaysOnly() {
                    if (payNoDays) payNoDays.value = String(daysInSelectedMonth());
                    syncPayrollMonthHidden();
                }
                function recalculateGrossFromPresent() {
                    var monthly = num('emPayMonthly');
                    var noDays = num('emPayNoDays') || daysInSelectedMonth();
                    var present = num('emPayPresent');
                    if (monthly <= 0 || noDays <= 0) return;
                    var gross = (monthly / noDays) * present;
                    setVal('emPayGross', gross.toFixed(2));
                    if (payBasic) payBasic.value = gross.toFixed(2);
                    recalculatePayNet();
                }
                function loadPayrollStats() {
                    if (payrollSkipStats) {
                        payrollSkipStats = false;
                        return;
                    }
                    if (!payEmployee || !payEmployee.value) {
                        setVal('emPayPresent', '0');
                        setVal('emPayAbsent', '0');
                        return;
                    }
                    syncPayrollMonthHidden();
                    updateNoOfDaysOnly();
                    EmApp.post('payroll_calc', {
                        employee_id: payEmployee.value,
                        payroll_month_part: payMonthPart ? payMonthPart.value : '',
                        payroll_year: payYear ? payYear.value : '',
                        payroll_month: payMonthHidden ? payMonthHidden.value : '',
                        monthly_salary: num('emPayMonthly') || num('emPayBasic')
                    }).then(function (r) {
                        if (!r.success || !r.data) return;
                        var d = r.data;
                        setVal('emPayNoDays', d.no_of_days != null ? d.no_of_days : daysInSelectedMonth());
                        setVal('emPayPresent', d.present_days != null ? d.present_days : 0);
                        setVal('emPayAbsent', d.absent_days != null ? d.absent_days : 0);
                        if (d.monthly_salary > 0) setVal('emPayMonthly', parseFloat(d.monthly_salary).toFixed(2));
                        setVal('emPayGross', parseFloat(d.gross_salary || 0).toFixed(2));
                        if (payBasic) payBasic.value = parseFloat(d.basic_salary || d.gross_salary || 0).toFixed(2);
                        setVal('emPayAdvance', parseFloat(d.advance_salary || 0).toFixed(2));
                        recalculatePayNet();
                    });
                }
                function recalculatePayNet() {
                    if (!payFinalNet) return;
                    var earnings = num('emPayBasic') + num('emPayHra') + num('emPayDa') + num('emPayConveyance') + num('emPayArrears');
                    var deductions = num('emPayProfTax') + num('emPayPf') + num('emPayEsic') + num('emPayTds') + num('emPayAdvance') + num('emPayOtherDed');
                    payFinalNet.value = (earnings - deductions).toFixed(2);
                }
                function fillEmployeeDetail() {
                    if (!payEmployee) return;
                    var option = payEmployee.options[payEmployee.selectedIndex];
                    if (!option || !option.value) {
                        ['emPayEmpCode', 'emPayEmpName', 'emPayMobile', 'emPayDepartment', 'emPayDesignation', 'emPayJoining'].forEach(function (id) { setVal(id, ''); });
                        setVal('emPayPresent', '0');
                        setVal('emPayAbsent', '0');
                        return;
                    }
                    setVal('emPayEmpCode', option.getAttribute('data-employee-code') || '');
                    setVal('emPayEmpName', option.getAttribute('data-employee-name') || '');
                    setVal('emPayMobile', option.getAttribute('data-phone') || '');
                    setVal('emPayDepartment', option.getAttribute('data-department') || '');
                    setVal('emPayDesignation', option.getAttribute('data-designation') || '');
                    setVal('emPayJoining', option.getAttribute('data-joining-date') || '');
                    var sal = option.getAttribute('data-monthly-salary') || option.getAttribute('data-basic-salary') || '0.00';
                    if (payMonthly) payMonthly.value = sal;
                    loadPayrollStats();
                }
                function applyPayrollDetail(detail) {
                    detail = detail || {};
                    setVal('emPayNoDays', detail.no_of_days || daysInSelectedMonth());
                    setVal('emPayPresent', detail.present_days != null ? detail.present_days : 0);
                    setVal('emPayAbsent', detail.absent_days != null ? detail.absent_days : 0);
                    if (detail.monthly_salary != null && parseFloat(detail.monthly_salary) > 0) {
                        setVal('emPayMonthly', detail.monthly_salary);
                    }
                    if (detail.gross_salary != null && parseFloat(detail.gross_salary) > 0) {
                        setVal('emPayGross', detail.gross_salary);
                    }
                    if (detail.hra != null) setVal('emPayHra', detail.hra);
                    if (detail.da != null) setVal('emPayDa', detail.da);
                    if (detail.conveyance != null) setVal('emPayConveyance', detail.conveyance);
                    if (detail.professional_tax != null) setVal('emPayProfTax', detail.professional_tax);
                    if (detail.pf != null) setVal('emPayPf', detail.pf);
                    if (detail.esic != null) setVal('emPayEsic', detail.esic);
                    if (detail.tds != null) setVal('emPayTds', detail.tds);
                    if (detail.advance_salary != null) setVal('emPayAdvance', detail.advance_salary);
                    if (detail.other_deduction != null) setVal('emPayOtherDed', detail.other_deduction);
                    setVal('emPayUan', detail.uan_no || '');
                    setVal('emPayEsicNo', detail.esic_no || '');
                    if (detail.salary_arrears != null) setVal('emPayArrears', detail.salary_arrears);
                    recalculatePayNet();
                }
                function resetPayrollFormDefaults() {
                    applyPayrollDetail({});
                    updateNoOfDaysOnly();
                    fillEmployeeDetail();
                }
                if (payEmployee) payEmployee.addEventListener('change', fillEmployeeDetail);
                if (payMonthPart) payMonthPart.addEventListener('change', loadPayrollStats);
                if (payYear) payYear.addEventListener('change', loadPayrollStats);
                if (payPresent) payPresent.addEventListener('input', recalculateGrossFromPresent);
                if (payMonthly) payMonthly.addEventListener('input', recalculateGrossFromPresent);
                calcInputs.forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) el.addEventListener('input', recalculatePayNet);
                });
                var addPayrollBtn = document.getElementById('emAddPayrollBtn');
                if (addPayrollBtn) {
                    addPayrollBtn.addEventListener('click', function () {
                        payForm.reset();
                        document.getElementById('emPayId').value = '0';
                        document.getElementById('emPayModalTitle').textContent = 'Add Payroll';
                        document.getElementById('emPaySubmitBtn').textContent = 'Save';
                        var toolbarMonth = document.getElementById('emPayMonth');
                        if (toolbarMonth && toolbarMonth.value && toolbarMonth.value.indexOf('-') !== -1) {
                            var tmParts = toolbarMonth.value.split('-');
                            if (payYear) payYear.value = tmParts[0];
                            if (payMonthPart) payMonthPart.value = tmParts[1];
                        }
                        resetPayrollFormDefaults();
                        EmApp.openModal('emPayModal');
                    });
                }
                EmApp.qsa('[data-edit-pay]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        payrollSkipStats = true;
                        document.getElementById('emPayId').value = btn.getAttribute('data-edit-pay') || '0';
                        if (payEmployee) payEmployee.value = btn.getAttribute('data-employee-id') || '';
                        var pm = btn.getAttribute('data-payroll-month') || '';
                        if (pm.indexOf('-') !== -1) {
                            var parts = pm.split('-');
                            if (payYear) payYear.value = parts[0];
                            if (payMonthPart) payMonthPart.value = parts[1];
                        }
                        syncPayrollMonthHidden();
                        if (payBasic) payBasic.value = btn.getAttribute('data-basic-salary') || '0.00';
                        if (payFinalNet) payFinalNet.value = btn.getAttribute('data-final-net') || '0.00';
                        fillEmployeeDetail();
                        payrollSkipStats = true;
                        var detailRaw = btn.getAttribute('data-payroll-detail') || '{}';
                        try { applyPayrollDetail(JSON.parse(detailRaw)); } catch (e) { applyPayrollDetail({}); }
                        document.getElementById('emPayModalTitle').textContent = 'Edit Payroll';
                        document.getElementById('emPaySubmitBtn').textContent = 'Update';
                        EmApp.openModal('emPayModal');
                    });
                });
                if (payForm) {
                    payForm.addEventListener('submit', function (e) {
                        e.preventDefault();
                        syncPayrollMonthHidden();
                        recalculatePayNet();
                        var fd = new FormData(this);
                        var data = {};
                        fd.forEach(function (v, k) { data[k] = v; });
                        EmApp.post('save_payroll', data).then(function (r) {
                            EmApp.showAlert(alertEl, r.message, r.success);
                            if (r.success) setTimeout(function () { EmApp.reload(); }, 700);
                        });
                    });
                }
                var genBtn = document.getElementById('emGenPayroll');
                if (genBtn) {
                    genBtn.addEventListener('click', function () {
                        EmApp.post('generate_payroll', { payroll_month: document.getElementById('emPayMonth').value }).then(function (r) {
                            EmApp.showAlert(alertEl, r.message, r.success);
                            if (r.success) setTimeout(function () { EmApp.reload(); }, 700);
                        });
                    });
                }
                document.getElementById('emPayMonth').addEventListener('change', function () {
                    window.location.href = 'employee-salary-payroll.php?month=' + encodeURIComponent(this.value);
                });
                EmApp.qsa('[data-del-pay]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (!confirm('Delete payroll row?')) return;
                        EmApp.post('delete_payroll', { id: btn.getAttribute('data-del-pay') }).then(function (r) {
                            EmApp.showAlert(alertEl, r.message, r.success);
                            if (r.success) setTimeout(function () { EmApp.reload(); }, 700);
                        });
                    });
                });
            });
            </script>
            <?php
            break;

        case 'employee_advance':
            $advanceRows = auragold_em_get_advances($conn, $branch_id, '', $scopeEmployeeId);
            $pendingCount = 0;
            foreach ($advanceRows as $_ar) {
                if (strcasecmp((string) ($_ar['status'] ?? ''), 'Pending') === 0) {
                    $pendingCount++;
                }
            }
            ?>
            <div id="emAlert" class="em-alert"></div>
            <p class="em-lead" style="margin-top:0;"><?php echo $isEmAdmin
                ? 'Raise or view advance requests here. Use <a href="employee-advance-request.php">Advance Request</a> to approve pending requests (adds to this month’s payroll).'
                : 'Submit your own advance request. After admin approval on <a href="employee-advance-request.php">Advance Request</a>, it is added to this month’s payroll deductions.'; ?></p>
            <div class="em-toolbar">
                <div class="em-toolbar-left"><strong><?php echo count($advanceRows); ?></strong> request(s)<?php if ($isEmAdmin && $pendingCount > 0): ?> · <span style="color:#b45309;"><?php echo (int) $pendingCount; ?> pending — <a href="employee-advance-request.php?status=Pending">review</a></span><?php endif; ?></div>
                <div class="em-toolbar-right">
                    <?php if ($isEmAdmin): ?>
                    <a class="em-btn em-btn-light" href="employee-advance-request.php?status=Pending">Advance Request</a>
                    <?php endif; ?>
                    <button type="button" class="em-btn em-btn-primary" id="emAdvanceOpenBtn">Request Advance</button>
                </div>
            </div>
            <div class="em-table-panel">
                <div class="em-table-tools" id="emAdvanceTableTools"></div>
                <div class="em-table-wrap">
                    <table class="em-table" id="emAdvanceTable" data-em-col-key="employee_advance">
                        <thead>
                            <tr>
                                <th data-column="employee"><span class="em-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span><span class="em-col-head-inner">Employee</span></th>
                                <th data-column="date"><span class="em-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span><span class="em-col-head-inner">Date</span></th>
                                <th data-column="amount"><span class="em-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span><span class="em-col-head-inner">Requested Amount</span></th>
                                <th data-column="approved_amount"><span class="em-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span><span class="em-col-head-inner">Approved Amount</span></th>
                                <th data-column="recovered"><span class="em-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span><span class="em-col-head-inner">Recovered</span></th>
                                <th data-column="balance"><span class="em-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span><span class="em-col-head-inner">Balance</span></th>
                                <th data-column="payroll_month"><span class="em-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span><span class="em-col-head-inner">Payroll Month</span></th>
                                <th data-column="status"><span class="em-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span><span class="em-col-head-inner">Status</span></th>
                                <th data-column="notes"><span class="em-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span><span class="em-col-head-inner">Notes</span></th>
                                <th data-column="actions" data-em-col-fixed="1"><span class="em-col-head-inner">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
            <?php if (empty($advanceRows)): ?>
                            <tr><td colspan="10" class="em-empty">No advance requests yet. Use Request Advance to submit the first request.</td></tr>
            <?php else: foreach ($advanceRows as $row):
                $amt = (float) ($row['amount'] ?? 0);
                $approvedAmt = isset($row['approved_amount']) ? (float) $row['approved_amount'] : null;
                $claimAmt = $approvedAmt ?? $amt;
                $rec = (float) ($row['recovered'] ?? 0);
                $bal = $claimAmt - $rec;
                $st = (string) ($row['status'] ?? 'Pending');
                $isPending = strcasecmp($st, 'Pending') === 0;
                $canManage = $isPending && ($isEmAdmin || ((int) ($row['employee_id'] ?? 0) === $myEmployeeId));
                ?>
                            <tr>
                                <td data-column="employee"><?php echo auragold_em_h(auragold_em_employee_name($row)); ?></td>
                                <td data-column="date"><?php echo auragold_em_h(auragold_em_format_date($row['advance_date'] ?? '')); ?></td>
                                <td data-column="amount" class="num"><?php echo auragold_em_h(auragold_em_format_money($amt)); ?></td>
                                <td data-column="approved_amount" class="num"><?php echo $approvedAmt !== null
                                    ? auragold_em_h(auragold_em_format_money($approvedAmt))
                                    : '—'; ?></td>
                                <td data-column="recovered" class="num"><?php echo auragold_em_h(auragold_em_format_money($rec)); ?></td>
                                <td data-column="balance" class="num"><?php echo auragold_em_h(auragold_em_format_money($bal)); ?></td>
                                <td data-column="payroll_month"><?php echo auragold_em_h(($row['payroll_month'] ?? '') !== '' ? $row['payroll_month'] : '—'); ?></td>
                                <td data-column="status"><?php echo auragold_em_badge($st); ?></td>
                                <td data-column="notes"><?php echo auragold_em_h(($row['notes'] ?? '') !== '' ? $row['notes'] : '—'); ?></td>
                                <td data-column="actions" class="em-actions">
                    <?php if ($canManage): ?>
                                    <button type="button"
                                        data-edit-advance="<?php echo (int) $row['id']; ?>"
                                        data-employee-id="<?php echo (int) ($row['employee_id'] ?? 0); ?>"
                                        data-advance-date="<?php echo auragold_em_h((string) ($row['advance_date'] ?? '')); ?>"
                                        data-amount="<?php echo auragold_em_h(number_format($amt, 2, '.', '')); ?>"
                                        data-notes="<?php echo auragold_em_h((string) ($row['notes'] ?? '')); ?>">Edit</button>
                                    <button type="button" class="danger" data-del-advance="<?php echo (int) $row['id']; ?>">Delete</button>
                    <?php else: ?>
                                    —
                    <?php endif; ?>
                                </td>
                            </tr>
            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="emAdvanceModal" class="em-modal-backdrop"><div class="em-modal"><div class="em-modal-head"><h3 id="emAdvanceModalTitle">Request Advance</h3><button type="button" class="em-close">&times;</button></div>
            <form id="emAdvanceForm"><div class="em-modal-body"><div class="em-form-grid">
                <input type="hidden" name="id" id="emAdvanceId" value="0">
                <div class="em-field"><label>Employee</label><select name="employee_id" id="emAdvanceEmployee" required<?php echo $empSelectAttrs; ?>><?php echo auragold_em_employee_options($employees, $myEmployeeId, $empOptsSelf); ?></select><?php if ($empOptsSelf && $myEmployeeId > 0): ?><input type="hidden" name="employee_id" value="<?php echo (int) $myEmployeeId; ?>"><?php endif; ?></div>
                <div class="em-field"><label>Advance Date</label><input type="date" name="advance_date" id="emAdvanceDate" value="<?php echo auragold_em_h(date('Y-m-d')); ?>" required></div>
                <div class="em-field"><label>Amount</label><input type="number" step="0.01" min="0.01" name="amount" id="emAdvanceAmount" value="" placeholder="0.00" required>
                <p id="emAdvanceLimitHint" class="em-advance-limit-hint" style="display:none;"></p></div>
                <div class="em-field" style="grid-column:1/-1;"><label>Notes</label><textarea name="notes" id="emAdvanceNotes" rows="2" placeholder="Reason for advance"></textarea></div>
                <p style="grid-column:1/-1;margin:0;font-size:12px;color:#64748b;">Request is saved as <strong>Pending</strong>. After admin approval it is added to this month’s payroll deductions. Approved or rejected requests cannot be edited or deleted.</p>
            </div></div><div class="em-modal-foot"><button type="button" class="em-btn em-btn-light em-close">Cancel</button><button type="submit" class="em-btn em-btn-primary" id="emAdvanceSubmitBtn">Submit Request</button></div></form></div></div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var alertEl = document.getElementById('emAlert');
                var form = document.getElementById('emAdvanceForm');
                var titleEl = document.getElementById('emAdvanceModalTitle');
                var submitBtn = document.getElementById('emAdvanceSubmitBtn');
                var empEl = document.getElementById('emAdvanceEmployee');
                var dateEl = document.getElementById('emAdvanceDate');
                var amtEl = document.getElementById('emAdvanceAmount');
                var limitHint = document.getElementById('emAdvanceLimitHint');
                var advanceMaxAllowed = 0;

                function formatMoney(n) {
                    return (parseFloat(n || 0) || 0).toFixed(2);
                }
                function loadAdvanceLimit() {
                    if (!empEl || !empEl.value) {
                        advanceMaxAllowed = 0;
                        if (limitHint) {
                            limitHint.style.display = 'none';
                            limitHint.textContent = '';
                        }
                        if (amtEl) amtEl.removeAttribute('max');
                        return;
                    }
                    var idEl = document.getElementById('emAdvanceId');
                    EmApp.post('advance_limit', {
                        employee_id: empEl.value,
                        advance_date: dateEl ? dateEl.value : '',
                        id: idEl ? idEl.value : '0'
                    }).then(function (r) {
                        if (!r.success || !r.data) return;
                        var d = r.data;
                        var maxTotal = parseFloat(d.max_advance_total || 0) || 0;
                        var usedAdvance = parseFloat(d.used_advance || 0) || 0;
                        advanceMaxAllowed = parseFloat(d.max_advance || 0) || 0;
                        var isEdit = idEl && parseInt(idEl.value, 10) > 0;
                        if (limitHint) {
                            if ((parseInt(d.present_days, 10) || 0) <= 0) {
                                limitHint.textContent = 'No present attendance this month — advance not allowed.';
                                limitHint.style.display = 'block';
                            } else {
                                var hint = 'Present: <strong>' + (d.present_days || 0) + '</strong>'
                                    + ' · Per day: <strong>' + formatMoney(d.per_day_salary) + '</strong>'
                                    + ' · Earned: <strong>' + formatMoney(d.earned_salary) + '</strong>'
                                    + ' · Max advance (40%): <strong>' + formatMoney(maxTotal) + '</strong>';
                                if (usedAdvance > 0) {
                                    hint += ' · Already requested: <strong>' + formatMoney(usedAdvance) + '</strong>'
                                        + ' · Available: <strong>' + formatMoney(advanceMaxAllowed) + '</strong>';
                                }
                                limitHint.innerHTML = hint;
                                limitHint.style.display = 'block';
                            }
                        }
                        if (amtEl) {
                            if (advanceMaxAllowed > 0) {
                                amtEl.setAttribute('max', advanceMaxAllowed.toFixed(2));
                            } else {
                                amtEl.removeAttribute('max');
                            }
                            if (!isEdit) {
                                amtEl.value = advanceMaxAllowed > 0 ? advanceMaxAllowed.toFixed(2) : '';
                            }
                        }
                    });
                }
                function resetAdvanceForm() {
                    if (!form) return;
                    form.reset();
                    var idEl = document.getElementById('emAdvanceId');
                    if (idEl) idEl.value = '0';
                    if (dateEl) dateEl.value = '<?php echo auragold_em_h(date('Y-m-d')); ?>';
                    if (titleEl) titleEl.textContent = 'Request Advance';
                    if (submitBtn) submitBtn.textContent = 'Submit Request';
                    loadAdvanceLimit();
                }

                var openBtn = document.getElementById('emAdvanceOpenBtn');
                if (openBtn) {
                    openBtn.addEventListener('click', function () {
                        resetAdvanceForm();
                        EmApp.openModal('emAdvanceModal');
                    });
                }
                if (empEl) empEl.addEventListener('change', loadAdvanceLimit);
                if (dateEl) dateEl.addEventListener('change', loadAdvanceLimit);

                if (form) {
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        var fd = new FormData(form);
                        var data = {};
                        fd.forEach(function (v, k) { data[k] = v; });
                        var amt = parseFloat(data.amount || '0');
                        if (!(amt > 0)) {
                            EmApp.showAlert(alertEl, 'Enter a valid advance amount.', false);
                            return;
                        }
                        if (advanceMaxAllowed <= 0) {
                            EmApp.showAlert(alertEl, 'No advance available for this month (40% limit already used or no attendance).', false);
                            return;
                        }
                        if (amt > advanceMaxAllowed + 0.009) {
                            EmApp.showAlert(alertEl, 'Advance amount cannot exceed ' + formatMoney(advanceMaxAllowed) + ' (available limit).', false);
                            return;
                        }
                        EmApp.post('save_advance', data).then(function (r) {
                            EmApp.showAlert(alertEl, r.message, r.success);
                            if (r.success) {
                                EmApp.closeModal('emAdvanceModal');
                                setTimeout(function () { EmApp.reload(); }, 700);
                            }
                        }).catch(function () {
                            EmApp.showAlert(alertEl, 'Could not save advance request.', false);
                        });
                    });
                }
                EmApp.qsa('[data-edit-advance]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var idEl = document.getElementById('emAdvanceId');
                        var notesEl = document.getElementById('emAdvanceNotes');
                        if (idEl) idEl.value = btn.getAttribute('data-edit-advance') || '0';
                        if (empEl) empEl.value = btn.getAttribute('data-employee-id') || '';
                        if (dateEl) dateEl.value = btn.getAttribute('data-advance-date') || '';
                        if (amtEl) amtEl.value = btn.getAttribute('data-amount') || '';
                        if (notesEl) notesEl.value = btn.getAttribute('data-notes') || '';
                        if (titleEl) titleEl.textContent = 'Edit Advance Request';
                        if (submitBtn) submitBtn.textContent = 'Update Request';
                        loadAdvanceLimit();
                        EmApp.openModal('emAdvanceModal');
                    });
                });
                EmApp.qsa('[data-del-advance]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (!confirm('Delete this pending advance request?')) return;
                        EmApp.post('delete_advance', { id: btn.getAttribute('data-del-advance') }).then(function (r) {
                            EmApp.showAlert(alertEl, r.message, r.success);
                            if (r.success) setTimeout(function () { EmApp.reload(); }, 700);
                        });
                    });
                });
            });
            </script>
            <?php
            break;

        case 'employee_advance_request':
            $statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : 'Pending';
            if (!in_array($statusFilter, ['Pending', 'Approved', 'Rejected', 'All'], true)) {
                $statusFilter = 'Pending';
            }
            // Admin sees every request; employees only their own.
            $advListStatus = $statusFilter === 'All' ? '' : $statusFilter;
            $advanceRows = auragold_em_get_advances($conn, $branch_id, $advListStatus, $scopeEmployeeId);
            $allForCounts = auragold_em_get_advances($conn, $branch_id, '', $scopeEmployeeId);
            $pendingCount = 0;
            $approvedCount = 0;
            $rejectedCount = 0;
            foreach ($allForCounts as $_ar) {
                $stc = strcasecmp((string) ($_ar['status'] ?? ''), 'Pending') === 0 ? 'Pending'
                    : (strcasecmp((string) ($_ar['status'] ?? ''), 'Approved') === 0 ? 'Approved'
                    : (strcasecmp((string) ($_ar['status'] ?? ''), 'Rejected') === 0 ? 'Rejected' : ''));
                if ($stc === 'Pending') {
                    $pendingCount++;
                } elseif ($stc === 'Approved') {
                    $approvedCount++;
                } elseif ($stc === 'Rejected') {
                    $rejectedCount++;
                }
            }
            $filterBase = 'employee-advance-request.php';
            ?>
            <div id="emAlert" class="em-alert"></div>
            <?php if (!$isEmAdmin): ?>
            <p class="em-lead" style="margin-top:0;color:#b45309;">Only Admin / HR can approve advances. You can view your own requests here.</p>
            <?php endif; ?>
            <style>
                #emAdvanceRequestTable th,
                #emAdvanceRequestTable td { white-space: nowrap; }
            </style>
            <div class="em-toolbar">
                <div class="em-toolbar-left" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                    <a class="em-btn <?php echo $statusFilter === 'Pending' ? 'em-btn-primary' : 'em-btn-light'; ?>" href="<?php echo auragold_em_h($filterBase . '?status=Pending'); ?>">Pending (<?php echo (int) $pendingCount; ?>)</a>
                    <a class="em-btn <?php echo $statusFilter === 'Approved' ? 'em-btn-primary' : 'em-btn-light'; ?>" href="<?php echo auragold_em_h($filterBase . '?status=Approved'); ?>">Approved (<?php echo (int) $approvedCount; ?>)</a>
                    <a class="em-btn <?php echo $statusFilter === 'Rejected' ? 'em-btn-primary' : 'em-btn-light'; ?>" href="<?php echo auragold_em_h($filterBase . '?status=Rejected'); ?>">Rejected (<?php echo (int) $rejectedCount; ?>)</a>
                    <a class="em-btn <?php echo $statusFilter === 'All' ? 'em-btn-primary' : 'em-btn-light'; ?>" href="<?php echo auragold_em_h($filterBase . '?status=All'); ?>">All (<?php echo count($allForCounts); ?>)</a>
                </div>
                <div class="em-toolbar-right">
                    <a class="em-btn em-btn-light" href="employee-advance.php">New request</a>
                </div>
            </div>
            <div class="em-table-panel">
                <div class="em-table-tools" id="emAdvanceRequestTableTools"></div>
                <div class="em-table-wrap">
                    <table class="em-table" id="emAdvanceRequestTable" data-em-col-key="employee_advance_request">
                        <thead>
                            <tr>
                                <th data-column="employee"><span class="em-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span><span class="em-col-head-inner">Employee</span></th>
                                <th data-column="monthly_salary"><span class="em-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span><span class="em-col-head-inner">Monthly Salary</span></th>
                                <th data-column="date"><span class="em-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span><span class="em-col-head-inner">Date</span></th>
                                <th data-column="amount"><span class="em-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span><span class="em-col-head-inner">Requested Amount</span></th>
                                <th data-column="approved_amount"><span class="em-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span><span class="em-col-head-inner">Approved Amount</span></th>
                                <th data-column="requested_by"><span class="em-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span><span class="em-col-head-inner">Requested By</span></th>
                                <th data-column="payroll_month"><span class="em-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span><span class="em-col-head-inner">Payroll Month</span></th>
                                <th data-column="status"><span class="em-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span><span class="em-col-head-inner">Status</span></th>
                                <th data-column="notes"><span class="em-col-drag" title="Drag to reorder"><i class="feather icon-move"></i></span><span class="em-col-head-inner">Notes</span></th>
                                <th data-column="actions" data-em-col-fixed="1"><span class="em-col-head-inner">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
            <?php if (empty($advanceRows)): ?>
                            <tr><td colspan="10" class="em-empty"><?php echo $statusFilter === 'Pending'
                ? 'No pending advance requests.'
                : 'No advance requests for this filter.'; ?></td></tr>
            <?php else: foreach ($advanceRows as $row):
                $amt = (float) ($row['amount'] ?? 0);
                $approvedAmt = isset($row['approved_amount']) ? (float) $row['approved_amount'] : null;
                $monthlySalary = (float) ($row['monthly_salary'] ?? $row['basic_salary'] ?? 0);
                $st = (string) ($row['status'] ?? 'Pending');
                ?>
                            <tr>
                                <td data-column="employee"><?php echo auragold_em_h(auragold_em_employee_name($row)); ?></td>
                                <td data-column="monthly_salary" class="num"><?php echo auragold_em_h(auragold_em_format_money($monthlySalary)); ?></td>
                                <td data-column="date"><?php echo auragold_em_h(auragold_em_format_date($row['advance_date'] ?? '')); ?></td>
                                <td data-column="amount" class="num"><strong><?php echo auragold_em_h(auragold_em_format_money($amt)); ?></strong></td>
                                <td data-column="approved_amount" class="num"><?php echo $approvedAmt !== null
                                    ? '<strong>' . auragold_em_h(auragold_em_format_money($approvedAmt)) . '</strong>'
                                    : '—'; ?></td>
                                <td data-column="requested_by"><?php echo auragold_em_h(($row['requested_by'] ?? '') !== '' ? $row['requested_by'] : '—'); ?></td>
                                <td data-column="payroll_month"><?php echo auragold_em_h(($row['payroll_month'] ?? '') !== '' ? $row['payroll_month'] : '—'); ?></td>
                                <td data-column="status"><?php echo auragold_em_badge($st); ?></td>
                                <td data-column="notes"><?php echo auragold_em_h(($row['notes'] ?? '') !== '' ? $row['notes'] : '—'); ?></td>
                                <td data-column="actions" class="em-actions">
                    <?php if ($isEmAdmin && strcasecmp($st, 'Pending') === 0): ?>
                                    <button type="button" class="em-btn em-btn-primary" style="padding:4px 10px;font-size:12px;"
                                        data-advance-status="<?php echo (int) $row['id']; ?>"
                                        data-requested-amount="<?php echo auragold_em_h(number_format($amt, 2, '.', '')); ?>"
                                        data-status="Approved">Approve</button>
                                    <button type="button" class="em-btn danger" style="padding:4px 10px;font-size:12px;" data-advance-status="<?php echo (int) $row['id']; ?>" data-status="Rejected">Reject</button>
                    <?php elseif (strcasecmp($st, 'Pending') === 0): ?>
                                    <span style="font-size:12px;color:#94a3b8;">Waiting for admin</span>
                    <?php else: ?>
                                    —
                    <?php endif; ?>
                                </td>
                            </tr>
            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="emAdvanceApproveModal" class="em-modal-backdrop">
                <div class="em-modal">
                    <div class="em-modal-head"><h3>Approve Advance Request</h3><button type="button" class="em-close">&times;</button></div>
                    <form id="emAdvanceApproveForm">
                        <div class="em-modal-body">
                            <input type="hidden" id="emApproveAdvanceId" value="0">
                            <div class="em-form-grid">
                                <div class="em-field"><label>Requested Amount</label><input type="text" id="emRequestedAdvanceAmount" readonly></div>
                                <div class="em-field"><label>Approved Amount</label><input type="number" id="emApprovedAdvanceAmount" step="0.01" min="0.01" required></div>
                                <p style="grid-column:1/-1;margin:0;font-size:12px;color:#64748b;">You may approve the full request or a lower amount. Only the approved amount will be claimed and added to this month’s payroll deductions.</p>
                            </div>
                        </div>
                        <div class="em-modal-foot">
                            <button type="button" class="em-btn em-btn-light em-close">Cancel</button>
                            <button type="submit" class="em-btn em-btn-primary">Approve Amount</button>
                        </div>
                    </form>
                </div>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var alertEl = document.getElementById('emAlert');
                EmApp.qsa('[data-advance-status]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var status = btn.getAttribute('data-status');
                        if (status === 'Approved') {
                            var requested = btn.getAttribute('data-requested-amount') || '0.00';
                            document.getElementById('emApproveAdvanceId').value = btn.getAttribute('data-advance-status') || '0';
                            document.getElementById('emRequestedAdvanceAmount').value = requested;
                            var approvedInput = document.getElementById('emApprovedAdvanceAmount');
                            approvedInput.value = requested;
                            approvedInput.max = requested;
                            EmApp.openModal('emAdvanceApproveModal');
                            return;
                        }
                        if (!confirm('Reject this advance request?')) return;
                        EmApp.post('advance_status', {
                            id: btn.getAttribute('data-advance-status'),
                            status: status
                        }).then(function (r) {
                            EmApp.showAlert(alertEl, r.message, r.success);
                            if (r.success) setTimeout(function () { EmApp.reload(); }, 700);
                        });
                    });
                });
                var approveForm = document.getElementById('emAdvanceApproveForm');
                if (approveForm) {
                    approveForm.addEventListener('submit', function (e) {
                        e.preventDefault();
                        var id = document.getElementById('emApproveAdvanceId').value;
                        var requested = parseFloat(document.getElementById('emRequestedAdvanceAmount').value || '0');
                        var approved = parseFloat(document.getElementById('emApprovedAdvanceAmount').value || '0');
                        if (!(approved > 0) || approved > requested) {
                            EmApp.showAlert(alertEl, 'Approved amount must be greater than zero and cannot exceed the requested amount.', false);
                            return;
                        }
                        EmApp.post('advance_status', {
                            id: id,
                            status: 'Approved',
                            approved_amount: approved.toFixed(2)
                        }).then(function (r) {
                            EmApp.showAlert(alertEl, r.message, r.success);
                            if (r.success) {
                                EmApp.closeModal('emAdvanceApproveModal');
                                setTimeout(function () { EmApp.reload(); }, 700);
                            }
                        });
                    });
                }
            });
            </script>
            <?php
            break;

        case 'employee_incentive':
            ?>
            <div id="emAlert" class="em-alert"></div>
            <div class="em-toolbar">
                <div class="em-toolbar-right">
                    <button type="button" class="em-btn em-btn-primary" onclick="EmApp.openModal('emIncentiveModal')">Add Incentive</button>
                </div>
            </div>
            <div class="em-table-wrap"><table class="em-table"><thead><tr><th>Employee</th><th>Period</th><th>Type</th><th>Amount</th><th>Status</th><th>Notes</th></tr></thead><tbody>
            <tr><td colspan="6" class="em-empty">No employee incentives recorded yet. Use Add Incentive to create the first entry.</td></tr>
            </tbody></table></div>
            <div id="emIncentiveModal" class="em-modal-backdrop"><div class="em-modal"><div class="em-modal-head"><h3>Add Incentive</h3><button type="button" class="em-close">&times;</button></div>
            <form id="emIncentiveForm"><div class="em-modal-body"><div class="em-form-grid">
                <div class="em-field"><label>Employee</label><select name="employee_id" required<?php echo $empSelectAttrs; ?>><?php echo auragold_em_employee_options($employees, $myEmployeeId, $empOptsSelf); ?></select><?php if ($empOptsSelf && $myEmployeeId > 0): ?><input type="hidden" name="employee_id" value="<?php echo (int) $myEmployeeId; ?>"><?php endif; ?></div>
                <div class="em-field"><label>Period</label><input type="month" name="incentive_month" value="<?php echo auragold_em_h(date('Y-m')); ?>" required></div>
                <div class="em-field"><label>Type</label><select name="incentive_type"><option>Bonus</option><option>Sales Incentive</option><option>Performance</option><option>Other</option></select></div>
                <div class="em-field"><label>Amount</label><input type="number" step="0.01" name="amount" value="0" required></div>
                <div class="em-field"><label>Status</label><select name="status"><option>Draft</option><option>Approved</option><option>Paid</option></select></div>
                <div class="em-field" style="grid-column:1/-1;"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
            </div></div><div class="em-modal-foot"><button type="button" class="em-btn em-btn-light em-close">Cancel</button><button type="submit" class="em-btn em-btn-primary">Save</button></div></form></div></div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var alertEl = document.getElementById('emAlert');
                var form = document.getElementById('emIncentiveForm');
                if (form) {
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        EmApp.showAlert(alertEl, 'Incentive save will be available after backend setup.', false);
                    });
                }
            });
            </script>
            <?php
            break;

        case 'employee_tracking':
            ?>
            <div id="emAlert" class="em-alert"></div>
            <div class="em-toolbar">
                <div class="em-toolbar-right">
                    <button type="button" class="em-btn em-btn-primary" onclick="EmApp.openModal('emTrackingModal')">Add Tracking Entry</button>
                </div>
            </div>
            <div class="em-table-wrap"><table class="em-table"><thead><tr><th>Employee</th><th>Date / Time</th><th>Location</th><th>Activity</th><th>Status</th><th>Notes</th></tr></thead><tbody>
            <tr><td colspan="6" class="em-empty">No tracking entries yet. Use Add Tracking Entry to log the first activity.</td></tr>
            </tbody></table></div>
            <div id="emTrackingModal" class="em-modal-backdrop"><div class="em-modal"><div class="em-modal-head"><h3>Add Tracking Entry</h3><button type="button" class="em-close">&times;</button></div>
            <form id="emTrackingForm"><div class="em-modal-body"><div class="em-form-grid">
                <div class="em-field"><label>Employee</label><select name="employee_id" required<?php echo $empSelectAttrs; ?>><?php echo auragold_em_employee_options($employees, $myEmployeeId, $empOptsSelf); ?></select><?php if ($empOptsSelf && $myEmployeeId > 0): ?><input type="hidden" name="employee_id" value="<?php echo (int) $myEmployeeId; ?>"><?php endif; ?></div>
                <div class="em-field"><label>Date / Time</label><input type="datetime-local" name="tracked_at" value="<?php echo auragold_em_h(date('Y-m-d\TH:i')); ?>" required></div>
                <div class="em-field"><label>Location</label><input type="text" name="location" placeholder="Branch / Area / Address"></div>
                <div class="em-field"><label>Activity</label><select name="activity"><option>Check-in</option><option>Field Visit</option><option>Delivery</option><option>Meeting</option><option>Other</option></select></div>
                <div class="em-field"><label>Status</label><select name="status"><option>Active</option><option>Completed</option></select></div>
                <div class="em-field" style="grid-column:1/-1;"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
            </div></div><div class="em-modal-foot"><button type="button" class="em-btn em-btn-light em-close">Cancel</button><button type="submit" class="em-btn em-btn-primary">Save</button></div></form></div></div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var alertEl = document.getElementById('emAlert');
                var form = document.getElementById('emTrackingForm');
                if (form) {
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        EmApp.showAlert(alertEl, 'Tracking save will be available after backend setup.', false);
                    });
                }
            });
            </script>
            <?php
            break;

        case 'employee_tasks':
            $tasks = auragold_em_get_tasks($conn, $branch_id, '', $scopeEmployeeId);
            ?>
            <div id="emAlert" class="em-alert"></div>
            <div class="em-toolbar"><div class="em-toolbar-right"><button type="button" class="em-btn em-btn-primary" onclick="EmApp.openModal('emTaskModal')">Add Task</button></div></div>
            <div class="em-table-wrap"><table class="em-table"><thead><tr><th>Task</th><th>Employee</th><th>Priority</th><th>Due</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            <?php if (empty($tasks)): ?><tr><td colspan="6" class="em-empty">No tasks assigned yet.</td></tr>
            <?php else: foreach ($tasks as $row): ?>
            <tr>
                <td><?php echo auragold_em_h($row['title'] ?? ''); ?><div style="font-size:11px;color:#64748b;"><?php $desc = (string)($row['description'] ?? ''); echo auragold_em_h(strlen($desc) > 60 ? substr($desc, 0, 60) . '…' : $desc); ?></div></td>
                <td><?php echo auragold_em_h(auragold_em_employee_name($row)); ?></td>
                <td><?php echo auragold_em_badge((string)($row['priority'] ?? '')); ?></td>
                <td><?php echo auragold_em_h(auragold_em_format_date($row['due_date'] ?? '')); ?></td>
                <td><?php echo auragold_em_badge((string)($row['status'] ?? '')); ?></td>
                <td class="em-actions">
                    <?php if (($row['status'] ?? '') !== 'Completed'): ?><button type="button" data-task-done="<?php echo (int)$row['id']; ?>">Complete</button><?php endif; ?>
                    <button type="button" class="danger" data-del-task="<?php echo (int)$row['id']; ?>">Delete</button>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody></table></div>
            <div id="emTaskModal" class="em-modal-backdrop"><div class="em-modal"><div class="em-modal-head"><h3>Add Task</h3><button type="button" class="em-close">&times;</button></div>
            <form id="emTaskForm"><div class="em-modal-body"><div class="em-form-grid">
                <div class="em-field"><label>Employee</label><select name="employee_id" required<?php echo $empSelectAttrs; ?>><?php echo auragold_em_employee_options($employees, $myEmployeeId, $empOptsSelf); ?></select><?php if ($empOptsSelf && $myEmployeeId > 0): ?><input type="hidden" name="employee_id" value="<?php echo (int) $myEmployeeId; ?>"><?php endif; ?></div>
                <div class="em-field"><label>Title</label><input type="text" name="title" required></div>
                <div class="em-field"><label>Priority</label><select name="priority"><option>Low</option><option selected>Medium</option><option>High</option></select></div>
                <div class="em-field"><label>Due Date</label><input type="date" name="due_date"></div>
                <div class="em-field"><label>Status</label><select name="status"><option>Open</option><option>In Progress</option><option>Completed</option></select></div>
                <div class="em-field" style="grid-column:1/-1"><label>Description</label><textarea name="description"></textarea></div>
            </div></div><div class="em-modal-foot"><button type="button" class="em-btn em-btn-light em-close">Cancel</button><button type="submit" class="em-btn em-btn-primary">Save Task</button></div></form></div></div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var alertEl = document.getElementById('emAlert');
                document.getElementById('emTaskForm').addEventListener('submit', function (e) {
                    e.preventDefault(); var fd = new FormData(this); var data = {}; fd.forEach(function(v,k){ data[k]=v; });
                    EmApp.post('save_task', data).then(function (r) { EmApp.showAlert(alertEl, r.message, r.success); if (r.success) setTimeout(function(){ EmApp.reload(); }, 700); });
                });
                EmApp.qsa('[data-task-done]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        EmApp.post('complete_task', { id: btn.getAttribute('data-task-done') }).then(function (r) {
                            EmApp.showAlert(alertEl, r.message, r.success); if (r.success) setTimeout(function(){ EmApp.reload(); }, 700);
                        });
                    });
                });
                EmApp.qsa('[data-del-task]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (!confirm('Delete task?')) return;
                        EmApp.post('delete_task', { id: btn.getAttribute('data-del-task') }).then(function (r) {
                            EmApp.showAlert(alertEl, r.message, r.success); if (r.success) setTimeout(function(){ EmApp.reload(); }, 700);
                        });
                    });
                });
            });
            </script>
            <?php
            break;

        case 'employee_performance':
            $perf = auragold_em_get_performance($conn, $branch_id, $scopeEmployeeId);
            ?>
            <div id="emAlert" class="em-alert"></div>
            <div class="em-toolbar"><div class="em-toolbar-right"><button type="button" class="em-btn em-btn-primary" onclick="EmApp.openModal('emPerfModal')">Add Review</button></div></div>
            <div class="em-table-wrap"><table class="em-table"><thead><tr><th>Employee</th><th>Period</th><th>Review Date</th><th>Rating</th><th>KPI</th><th>Reviewer</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            <?php if (empty($perf)): ?><tr><td colspan="8" class="em-empty">No performance reviews yet.</td></tr>
            <?php else: foreach ($perf as $row): ?>
            <tr>
                <td><?php echo auragold_em_h(auragold_em_employee_name($row)); ?></td>
                <td><?php echo auragold_em_h($row['review_period'] ?? ''); ?></td>
                <td><?php echo auragold_em_h(auragold_em_format_date($row['review_date'] ?? '')); ?></td>
                <td class="num"><?php echo auragold_em_h(number_format((float)($row['rating'] ?? 0), 1)); ?>/5</td>
                <td class="num"><?php echo auragold_em_h(number_format((float)($row['kpi_score'] ?? 0), 2)); ?></td>
                <td><?php echo auragold_em_h($row['reviewer_name'] ?? ''); ?></td>
                <td><?php echo auragold_em_badge((string)($row['status'] ?? '')); ?></td>
                <td class="em-actions"><button type="button" class="danger" data-del-perf="<?php echo (int)$row['id']; ?>">Delete</button></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody></table></div>
            <div id="emPerfModal" class="em-modal-backdrop"><div class="em-modal"><div class="em-modal-head"><h3>Performance Review</h3><button type="button" class="em-close">&times;</button></div>
            <form id="emPerfForm"><div class="em-modal-body"><div class="em-form-grid">
                <div class="em-field"><label>Employee</label><select name="employee_id" required<?php echo $empSelectAttrs; ?>><?php echo auragold_em_employee_options($employees, $myEmployeeId, $empOptsSelf); ?></select><?php if ($empOptsSelf && $myEmployeeId > 0): ?><input type="hidden" name="employee_id" value="<?php echo (int) $myEmployeeId; ?>"><?php endif; ?></div>
                <div class="em-field"><label>Review Period</label><input type="text" name="review_period" placeholder="Q1 2026"></div>
                <div class="em-field"><label>Review Date</label><input type="date" name="review_date"></div>
                <div class="em-field"><label>Rating (1-5)</label><input type="number" step="0.1" min="0" max="5" name="rating" value="3"></div>
                <div class="em-field"><label>KPI Score</label><input type="number" step="0.01" name="kpi_score" value="0"></div>
                <div class="em-field"><label>Reviewer</label><input type="text" name="reviewer_name"></div>
                <div class="em-field"><label>Status</label><select name="status"><option>Draft</option><option>Final</option></select></div>
                <div class="em-field" style="grid-column:1/-1"><label>Strengths</label><textarea name="strengths"></textarea></div>
                <div class="em-field" style="grid-column:1/-1"><label>Improvements</label><textarea name="improvements"></textarea></div>
            </div></div><div class="em-modal-foot"><button type="button" class="em-btn em-btn-light em-close">Cancel</button><button type="submit" class="em-btn em-btn-primary">Save Review</button></div></form></div></div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var alertEl = document.getElementById('emAlert');
                document.getElementById('emPerfForm').addEventListener('submit', function (e) {
                    e.preventDefault(); var fd = new FormData(this); var data = {}; fd.forEach(function(v,k){ data[k]=v; });
                    EmApp.post('save_performance', data).then(function (r) { EmApp.showAlert(alertEl, r.message, r.success); if (r.success) setTimeout(function(){ EmApp.reload(); }, 700); });
                });
                EmApp.qsa('[data-del-perf]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (!confirm('Delete review?')) return;
                        EmApp.post('delete_performance', { id: btn.getAttribute('data-del-perf') }).then(function (r) {
                            EmApp.showAlert(alertEl, r.message, r.success); if (r.success) setTimeout(function(){ EmApp.reload(); }, 700);
                        });
                    });
                });
            });
            </script>
            <?php
            break;

        case 'employee_reports':
            $from = date('Y-m-01');
            $to = date('Y-m-d');
            if (!empty($_GET['from'])) {
                $from = preg_replace('/[^0-9-]/', '', (string) $_GET['from']);
            }
            if (!empty($_GET['to'])) {
                $to = preg_replace('/[^0-9-]/', '', (string) $_GET['to']);
            }
            $filterEmployeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
            if (!$isEmAdmin) {
                $filterEmployeeId = $myEmployeeId;
            }
            $activeTab = trim((string) ($_GET['tab'] ?? 'attendance'));
            $allowedTabs = ['attendance', 'leave', 'payroll', 'tasks', 'performance'];
            if (!in_array($activeTab, $allowedTabs, true)) {
                $activeTab = 'attendance';
            }
            $rep = auragold_em_get_reports($conn, $branch_id, $from, $to, $filterEmployeeId);
            $reportUrl = static function (array $extra = []) use ($from, $to, $filterEmployeeId, $activeTab): string {
                $q = array_merge([
                    'from' => $from,
                    'to' => $to,
                    'employee_id' => $filterEmployeeId > 0 ? $filterEmployeeId : '',
                    'tab' => $activeTab,
                ], $extra);
                if (($q['employee_id'] ?? '') === '' || (int) $q['employee_id'] <= 0) {
                    unset($q['employee_id']);
                }
                return 'employee-reports.php?' . http_build_query($q);
            };
            ?>
            <div class="em-reports-page">
            <div class="em-toolbar em-reports-toolbar">
                <div class="em-toolbar-left em-reports-filters">
                    <div class="em-field em-filter-field">
                        <label>From</label>
                        <input type="date" id="emRepFrom" value="<?php echo auragold_em_h($from); ?>">
                    </div>
                    <div class="em-field em-filter-field">
                        <label>To</label>
                        <input type="date" id="emRepTo" value="<?php echo auragold_em_h($to); ?>">
                    </div>
                    <div class="em-field em-filter-field em-filter-employee">
                        <label>Employee</label>
                        <select id="emRepEmployee"<?php echo $isEmAdmin ? '' : ' disabled'; ?>>
                            <?php if ($isEmAdmin): ?><option value="0">All Employees</option><?php endif; ?>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo (int) ($emp['id'] ?? 0); ?>"<?php echo $filterEmployeeId === (int) ($emp['id'] ?? 0) ? ' selected' : ''; ?>>
                                <?php echo auragold_em_h(auragold_em_employee_name($emp) . ' (' . ($emp['employee_code'] ?? '') . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="em-btn em-btn-primary" id="emRepRun">Run Report</button>
                </div>
            </div>

            <div class="em-tabs-group em-reports-tabs">
                <div class="em-tabs">
                    <a class="em-tab<?php echo $activeTab === 'attendance' ? ' active' : ''; ?>" href="<?php echo auragold_em_h($reportUrl(['tab' => 'attendance'])); ?>">Attendance Summary</a>
                    <a class="em-tab<?php echo $activeTab === 'leave' ? ' active' : ''; ?>" href="<?php echo auragold_em_h($reportUrl(['tab' => 'leave'])); ?>">Leave Summary</a>
                    <a class="em-tab<?php echo $activeTab === 'payroll' ? ' active' : ''; ?>" href="<?php echo auragold_em_h($reportUrl(['tab' => 'payroll'])); ?>">Payroll</a>
                    <a class="em-tab<?php echo $activeTab === 'tasks' ? ' active' : ''; ?>" href="<?php echo auragold_em_h($reportUrl(['tab' => 'tasks'])); ?>">Tasks</a>
                    <a class="em-tab<?php echo $activeTab === 'performance' ? ' active' : ''; ?>" href="<?php echo auragold_em_h($reportUrl(['tab' => 'performance'])); ?>">Performance</a>
                </div>

                <?php if ($activeTab === 'attendance'): ?>
                <div class="em-tab-panel active">
                    <div class="em-stats">
                        <?php
                        $attMap = [];
                        foreach ($rep['attendance_summary'] as $r) {
                            $attMap[(string) ($r['status'] ?? '')] = (int) ($r['c'] ?? 0);
                        }
                        ?>
                        <div class="em-stat"><div class="em-stat-label">Present</div><div class="em-stat-value"><?php echo (int) ($attMap['Present'] ?? 0); ?></div></div>
                        <div class="em-stat"><div class="em-stat-label">Absent</div><div class="em-stat-value"><?php echo (int) ($attMap['Absent'] ?? 0); ?></div></div>
                        <div class="em-stat"><div class="em-stat-label">Half Day</div><div class="em-stat-value"><?php echo (int) ($attMap['Half Day'] ?? 0); ?></div></div>
                        <div class="em-stat"><div class="em-stat-label">Employees</div><div class="em-stat-value"><?php echo count($rep['attendance_by_employee']); ?></div></div>
                    </div>
                    <div class="em-card em-reports-card">
                        <h3>Employee-wise Attendance</h3>
                        <div class="em-table-wrap"><table class="em-table em-table-fluid"><thead><tr>
                            <th>Employee</th><th>Code</th><th class="num">Present</th><th class="num">Absent</th><th class="num">Half Day</th><th class="num">Other</th><th class="num">Total Days</th>
                        </tr></thead><tbody>
                        <?php if (empty($rep['attendance_by_employee'])): ?>
                        <tr><td colspan="7" class="em-empty">No attendance records for the selected filters.</td></tr>
                        <?php else: foreach ($rep['attendance_by_employee'] as $r): ?>
                        <tr>
                            <td><?php echo auragold_em_h(auragold_em_employee_name($r)); ?></td>
                            <td><?php echo auragold_em_h($r['employee_code'] ?? ''); ?></td>
                            <td class="num"><?php echo (int) ($r['present_days'] ?? 0); ?></td>
                            <td class="num"><?php echo (int) ($r['absent_days'] ?? 0); ?></td>
                            <td class="num"><?php echo (int) ($r['half_days'] ?? 0); ?></td>
                            <td class="num"><?php echo (int) ($r['other_days'] ?? 0); ?></td>
                            <td class="num"><strong><?php echo (int) ($r['total_days'] ?? 0); ?></strong></td>
                        </tr>
                        <?php endforeach; endif; ?>
                        </tbody></table></div>
                    </div>
                    <div class="em-card em-reports-card">
                        <h3>Attendance Detail</h3>
                        <div class="em-table-wrap"><table class="em-table em-table-fluid"><thead><tr>
                            <th>Employee</th><th>Date</th><th>Status</th><th>Check In</th><th>Check Out</th><th>Notes</th>
                        </tr></thead><tbody>
                        <?php if (empty($rep['attendance_rows'])): ?>
                        <tr><td colspan="6" class="em-empty">No attendance detail rows.</td></tr>
                        <?php else: foreach ($rep['attendance_rows'] as $r): ?>
                        <tr>
                            <td><?php echo auragold_em_h(auragold_em_employee_name($r)); ?></td>
                            <td><?php echo auragold_em_h(auragold_em_format_date($r['attendance_date'] ?? '')); ?></td>
                            <td><?php echo auragold_em_badge((string) ($r['status'] ?? '')); ?></td>
                            <td><?php echo auragold_em_h(substr((string) ($r['check_in'] ?? $r['punch_in_at'] ?? ''), 0, 16)); ?></td>
                            <td><?php echo auragold_em_h(substr((string) ($r['check_out'] ?? $r['punch_out_at'] ?? ''), 0, 16)); ?></td>
                            <td><?php echo auragold_em_h($r['notes'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                        </tbody></table></div>
                    </div>
                </div>
                <?php elseif ($activeTab === 'leave'): ?>
                <div class="em-tab-panel active">
                    <div class="em-stats">
                        <?php
                        $leaveMap = [];
                        foreach ($rep['leave_summary'] as $r) {
                            $leaveMap[(string) ($r['status'] ?? '')] = (int) ($r['c'] ?? 0);
                        }
                        $leaveTotal = array_sum($leaveMap);
                        ?>
                        <div class="em-stat"><div class="em-stat-label">Total Requests</div><div class="em-stat-value"><?php echo (int) $leaveTotal; ?></div></div>
                        <div class="em-stat"><div class="em-stat-label">Pending</div><div class="em-stat-value"><?php echo (int) ($leaveMap['Pending'] ?? 0); ?></div></div>
                        <div class="em-stat"><div class="em-stat-label">Approved</div><div class="em-stat-value"><?php echo (int) ($leaveMap['Approved'] ?? 0); ?></div></div>
                        <div class="em-stat"><div class="em-stat-label">Rejected</div><div class="em-stat-value"><?php echo (int) ($leaveMap['Rejected'] ?? 0); ?></div></div>
                    </div>
                    <div class="em-card em-reports-card">
                        <h3>Employee-wise Leave</h3>
                        <div class="em-table-wrap"><table class="em-table em-table-fluid"><thead><tr>
                            <th>Employee</th><th>Type</th><th>From</th><th>To</th><th class="num">Days</th><th>Status</th><th>Reason</th>
                        </tr></thead><tbody>
                        <?php if (empty($rep['leave_rows'])): ?>
                        <tr><td colspan="7" class="em-empty">No leave records for the selected filters.</td></tr>
                        <?php else: foreach ($rep['leave_rows'] as $r): ?>
                        <tr>
                            <td><?php echo auragold_em_h(auragold_em_employee_name($r)); ?></td>
                            <td><?php echo auragold_em_h($r['leave_type_name'] ?? '—'); ?></td>
                            <td><?php echo auragold_em_h(auragold_em_format_date($r['from_date'] ?? '')); ?></td>
                            <td><?php echo auragold_em_h(auragold_em_format_date($r['to_date'] ?? '')); ?></td>
                            <td class="num"><?php echo auragold_em_h(number_format((float) ($r['days'] ?? 0), 2)); ?></td>
                            <td><?php echo auragold_em_badge((string) ($r['status'] ?? '')); ?></td>
                            <td><?php echo auragold_em_h($r['reason'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                        </tbody></table></div>
                    </div>
                </div>
                <?php elseif ($activeTab === 'payroll'): ?>
                <div class="em-tab-panel active">
                    <div class="em-stats">
                        <div class="em-stat"><div class="em-stat-label">Records</div><div class="em-stat-value"><?php echo (int) $rep['payroll_count']; ?></div></div>
                        <div class="em-stat"><div class="em-stat-label">Basic Total</div><div class="em-stat-value" style="font-size:1.2rem;"><?php echo auragold_em_h(auragold_em_format_money($rep['payroll_basic_total'] ?? 0)); ?></div></div>
                        <div class="em-stat"><div class="em-stat-label">Deductions</div><div class="em-stat-value" style="font-size:1.2rem;"><?php echo auragold_em_h(auragold_em_format_money($rep['payroll_ded_total'] ?? 0)); ?></div></div>
                        <div class="em-stat"><div class="em-stat-label">Net Total</div><div class="em-stat-value" style="font-size:1.2rem;"><?php echo auragold_em_h(auragold_em_format_money($rep['payroll_total'])); ?></div></div>
                    </div>
                    <div class="em-card em-reports-card">
                        <h3>Employee-wise Payroll</h3>
                        <div class="em-table-wrap"><table class="em-table em-table-fluid"><thead><tr>
                            <th>Employee</th><th>Month</th><th class="num">Basic</th><th class="num">Allowances</th><th class="num">Deductions</th><th class="num">Net</th><th>Status</th><th>Notes</th>
                        </tr></thead><tbody>
                        <?php if (empty($rep['payroll_rows'])): ?>
                        <tr><td colspan="8" class="em-empty">No payroll records for the selected filters.</td></tr>
                        <?php else: foreach ($rep['payroll_rows'] as $r): ?>
                        <tr>
                            <td><?php echo auragold_em_h(auragold_em_employee_name($r)); ?></td>
                            <td><?php echo auragold_em_h($r['payroll_month'] ?? ''); ?></td>
                            <td class="num"><?php echo auragold_em_h(auragold_em_format_money($r['basic_salary'] ?? 0)); ?></td>
                            <td class="num"><?php echo auragold_em_h(auragold_em_format_money($r['allowances'] ?? 0)); ?></td>
                            <td class="num"><?php echo auragold_em_h(auragold_em_format_money($r['deductions'] ?? 0)); ?></td>
                            <td class="num"><strong><?php echo auragold_em_h(auragold_em_format_money($r['net_salary'] ?? 0)); ?></strong></td>
                            <td><?php echo auragold_em_badge((string) ($r['status'] ?? '')); ?></td>
                            <td><?php echo auragold_em_h($r['notes'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                        </tbody></table></div>
                    </div>
                </div>
                <?php elseif ($activeTab === 'tasks'): ?>
                <div class="em-tab-panel active">
                    <div class="em-stats">
                        <?php
                        $taskMap = [];
                        foreach ($rep['task_summary'] as $r) {
                            $taskMap[(string) ($r['status'] ?? '')] = (int) ($r['c'] ?? 0);
                        }
                        ?>
                        <div class="em-stat"><div class="em-stat-label">Open</div><div class="em-stat-value"><?php echo (int) ($taskMap['Open'] ?? 0); ?></div></div>
                        <div class="em-stat"><div class="em-stat-label">In Progress</div><div class="em-stat-value"><?php echo (int) ($taskMap['In Progress'] ?? 0); ?></div></div>
                        <div class="em-stat"><div class="em-stat-label">Completed</div><div class="em-stat-value"><?php echo (int) ($taskMap['Completed'] ?? 0); ?></div></div>
                        <div class="em-stat"><div class="em-stat-label">Total</div><div class="em-stat-value"><?php echo (int) array_sum($taskMap); ?></div></div>
                    </div>
                    <div class="em-card em-reports-card">
                        <h3>Employee-wise Tasks</h3>
                        <div class="em-table-wrap"><table class="em-table em-table-fluid"><thead><tr>
                            <th>Employee</th><th>Task</th><th>Priority</th><th>Due Date</th><th>Status</th>
                        </tr></thead><tbody>
                        <?php if (empty($rep['task_rows'])): ?>
                        <tr><td colspan="5" class="em-empty">No tasks for the selected filters.</td></tr>
                        <?php else: foreach ($rep['task_rows'] as $r): ?>
                        <tr>
                            <td><?php echo auragold_em_h(auragold_em_employee_name($r)); ?></td>
                            <td><?php echo auragold_em_h($r['title'] ?? ''); ?></td>
                            <td><?php echo auragold_em_h($r['priority'] ?? ''); ?></td>
                            <td><?php echo auragold_em_h(auragold_em_format_date($r['due_date'] ?? '')); ?></td>
                            <td><?php echo auragold_em_badge((string) ($r['status'] ?? '')); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                        </tbody></table></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="em-tab-panel active">
                    <div class="em-stats">
                        <div class="em-stat"><div class="em-stat-label">Reviews</div><div class="em-stat-value"><?php echo (int) $rep['performance_reviews']; ?></div></div>
                        <div class="em-stat"><div class="em-stat-label">Avg Rating</div><div class="em-stat-value"><?php echo auragold_em_h(number_format((float) $rep['avg_rating'], 2)); ?><span class="em-stat-sub"> / 5</span></div></div>
                    </div>
                    <div class="em-card em-reports-card">
                        <h3>Employee-wise Performance</h3>
                        <div class="em-table-wrap"><table class="em-table em-table-fluid"><thead><tr>
                            <th>Employee</th><th>Period</th><th>Review Date</th><th class="num">Rating</th><th class="num">KPI</th><th>Reviewer</th><th>Status</th>
                        </tr></thead><tbody>
                        <?php if (empty($rep['performance_rows'])): ?>
                        <tr><td colspan="7" class="em-empty">No performance reviews for the selected filters.</td></tr>
                        <?php else: foreach ($rep['performance_rows'] as $r): ?>
                        <tr>
                            <td><?php echo auragold_em_h(auragold_em_employee_name($r)); ?></td>
                            <td><?php echo auragold_em_h($r['review_period'] ?? ''); ?></td>
                            <td><?php echo auragold_em_h(auragold_em_format_date($r['review_date'] ?? '')); ?></td>
                            <td class="num"><?php echo auragold_em_h(number_format((float) ($r['rating'] ?? 0), 1)); ?></td>
                            <td class="num"><?php echo auragold_em_h(number_format((float) ($r['kpi_score'] ?? 0), 2)); ?></td>
                            <td><?php echo auragold_em_h($r['reviewer_name'] ?? ''); ?></td>
                            <td><?php echo auragold_em_badge((string) ($r['status'] ?? '')); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                        </tbody></table></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var runBtn = document.getElementById('emRepRun');
                if (!runBtn) return;
                function goReport() {
                    var f = document.getElementById('emRepFrom').value;
                    var t = document.getElementById('emRepTo').value;
                    var e = document.getElementById('emRepEmployee').value;
                    var isAdmin = <?php echo $isEmAdmin ? 'true' : 'false'; ?>;
                    var myId = <?php echo (int) $myEmployeeId; ?>;
                    if (!isAdmin) e = String(myId || '0');
                    var tab = <?php echo json_encode($activeTab); ?>;
                    var url = 'employee-reports.php?from=' + encodeURIComponent(f) + '&to=' + encodeURIComponent(t) + '&tab=' + encodeURIComponent(tab);
                    if (e && e !== '0') url += '&employee_id=' + encodeURIComponent(e);
                    window.location.href = url;
                }
                runBtn.addEventListener('click', goReport);
                document.getElementById('emRepEmployee').addEventListener('change', goReport);
            });
            </script>
            <?php
            break;

        case 'employee_settings':
            if (!$isEmAdmin) {
                echo '<div class="em-alert show em-alert-error">Only admin can manage employee settings.</div>';
                break;
            }
            $allEmployees = auragold_em_get_employees($conn, $branch_id);
            $departments = $em['departments'] ?? [];
            $designations = $em['designations'] ?? [];
            $shifts = $em['shifts'] ?? [];
            $leaveTypes = $em['leave_types'] ?? [];
            ?>
            <div id="emAlert" class="em-alert"></div>
            <div class="em-tabs-group">
                <div class="em-tabs">
                    <button type="button" class="em-tab active" data-panel="emTabEmployees">Employees</button>
                    <button type="button" class="em-tab" data-panel="emTabDepartments">Departments</button>
                    <button type="button" class="em-tab" data-panel="emTabDesignations">Designations</button>
                    <button type="button" class="em-tab" data-panel="emTabShifts">Shifts</button>
                    <button type="button" class="em-tab" data-panel="emTabLeaveTypes">Leave Types</button>
                </div>
                <div id="emTabEmployees" class="em-tab-panel active">
                    <div class="em-toolbar"><div class="em-toolbar-right"><button type="button" class="em-btn em-btn-primary" onclick="EmApp.openModal('emEmpModal')">Add Employee</button></div></div>
                    <div class="em-table-wrap"><table class="em-table"><thead><tr><th>Code</th><th>Name</th><th>Department</th><th>Designation</th><th>Phone</th><th>Salary</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                    <?php if (empty($allEmployees)): ?><tr><td colspan="8" class="em-empty">No employees yet. Click Add Employee.</td></tr>
                    <?php else: foreach ($allEmployees as $row): ?>
                    <tr>
                        <td><?php echo auragold_em_h($row['employee_code'] ?? ''); ?></td>
                        <td><?php echo auragold_em_h(auragold_em_employee_name($row)); ?></td>
                        <td><?php echo auragold_em_h($row['department_name'] ?? '—'); ?></td>
                        <td><?php echo auragold_em_h($row['designation_name'] ?? '—'); ?></td>
                        <td><?php echo auragold_em_h($row['phone'] ?? ''); ?></td>
                        <td class="num"><?php echo auragold_em_h(auragold_em_format_money($row['basic_salary'] ?? 0)); ?></td>
                        <td><?php echo auragold_em_badge((string)($row['status'] ?? '')); ?></td>
                        <td class="em-actions"><button type="button" class="danger" data-del-emp="<?php echo (int)$row['id']; ?>">Remove</button></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody></table></div>
                </div>
                <?php
                $masterTabs = [
                    ['emTabDepartments', 'department', 'Departments', $departments, false],
                    ['emTabDesignations', 'designation', 'Designations', $designations, false],
                    ['emTabShifts', 'shift', 'Shifts', $shifts, true],
                    ['emTabLeaveTypes', 'leave_type', 'Leave Types', $leaveTypes, false, true],
                ];
                foreach ($masterTabs as $mt):
                    $panelId = $mt[0]; $type = $mt[1]; $title = $mt[2]; $rows = $mt[3]; $isShift = !empty($mt[4]); $isLeave = !empty($mt[5]);
                ?>
                <div id="<?php echo auragold_em_h($panelId); ?>" class="em-tab-panel">
                    <div class="em-toolbar"><div class="em-toolbar-right"><button type="button" class="em-btn em-btn-primary" onclick="document.getElementById('<?php echo auragold_em_h($panelId); ?>Form').style.display='block'">Add <?php echo auragold_em_h(rtrim($title, 's')); ?></button></div></div>
                    <form id="<?php echo auragold_em_h($panelId); ?>Form" class="em-card" style="display:none;margin-bottom:14px;" data-master-type="<?php echo auragold_em_h($type); ?>">
                        <div class="em-form-grid">
                            <div class="em-field"><label>Name</label><input type="text" name="name" required></div>
                            <?php if ($isShift): ?>
                            <div class="em-field"><label>Start Time</label><input type="time" name="start_time" value="09:00"></div>
                            <div class="em-field"><label>End Time</label><input type="time" name="end_time" value="18:00"></div>
                            <?php elseif ($isLeave): ?>
                            <div class="em-field"><label>Days / Year</label><input type="number" step="0.5" name="days_per_year" value="12"></div>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top:10px;"><button type="submit" class="em-btn em-btn-primary">Save</button></div>
                    </form>
                    <div class="em-table-wrap"><table class="em-table"><thead><tr><th>Name</th><?php if ($isShift): ?><th>Start</th><th>End</th><?php elseif ($isLeave): ?><th>Days/Year</th><?php endif; ?><th>Actions</th></tr></thead><tbody>
                    <?php if (empty($rows)): ?><tr><td colspan="4" class="em-empty">No records.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo auragold_em_h($r['name'] ?? ''); ?></td>
                        <?php if ($isShift): ?><td><?php echo auragold_em_h(substr((string)($r['start_time'] ?? ''), 0, 5)); ?></td><td><?php echo auragold_em_h(substr((string)($r['end_time'] ?? ''), 0, 5)); ?></td>
                        <?php elseif ($isLeave): ?><td class="num"><?php echo auragold_em_h(number_format((float)($r['days_per_year'] ?? 0), 2)); ?></td><?php endif; ?>
                        <td class="em-actions"><button type="button" class="danger" data-del-master="<?php echo auragold_em_h($type); ?>" data-id="<?php echo (int)$r['id']; ?>">Remove</button></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody></table></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="emEmpModal" class="em-modal-backdrop"><div class="em-modal"><div class="em-modal-head"><h3>Add Employee</h3><button type="button" class="em-close">&times;</button></div>
            <form id="emEmpForm"><div class="em-modal-body"><div class="em-form-grid">
                <div class="em-field"><label>Employee Code</label><input type="text" name="employee_code" value="<?php echo auragold_em_h($em['next_employee_code'] ?? ''); ?>"></div>
                <div class="em-field"><label>First Name</label><input type="text" name="first_name" required></div>
                <div class="em-field"><label>Last Name</label><input type="text" name="last_name"></div>
                <div class="em-field"><label>Email</label><input type="email" name="email"></div>
                <div class="em-field"><label>Phone</label><input type="text" name="phone"></div>
                <div class="em-field"><label>Department</label><select name="department_id"><option value="0">—</option><?php foreach ($departments as $d): ?><option value="<?php echo (int)$d['id']; ?>"><?php echo auragold_em_h($d['name']); ?></option><?php endforeach; ?></select></div>
                <div class="em-field"><label>Designation</label><select name="designation_id"><option value="0">—</option><?php foreach ($designations as $d): ?><option value="<?php echo (int)$d['id']; ?>"><?php echo auragold_em_h($d['name']); ?></option><?php endforeach; ?></select></div>
                <div class="em-field"><label>Shift</label><select name="shift_id"><option value="0">—</option><?php foreach ($shifts as $s): ?><option value="<?php echo (int)$s['id']; ?>"><?php echo auragold_em_h($s['name']); ?></option><?php endforeach; ?></select></div>
                <div class="em-field"><label>Joining Date</label><input type="date" name="joining_date"></div>
                <div class="em-field"><label>Basic Salary</label><input type="number" step="0.01" name="basic_salary" value="0"></div>
                <div class="em-field"><label>Status</label><select name="status"><option>Active</option><option>Inactive</option></select></div>
                <div class="em-field" style="grid-column:1/-1"><label>Address</label><textarea name="address"></textarea></div>
            </div></div><div class="em-modal-foot"><button type="button" class="em-btn em-btn-light em-close">Cancel</button><button type="submit" class="em-btn em-btn-primary">Save Employee</button></div></form></div></div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var alertEl = document.getElementById('emAlert');
                document.getElementById('emEmpForm').addEventListener('submit', function (e) {
                    e.preventDefault(); var fd = new FormData(this); var data = {}; fd.forEach(function(v,k){ data[k]=v; });
                    EmApp.post('save_employee', data).then(function (r) { EmApp.showAlert(alertEl, r.message, r.success); if (r.success) setTimeout(function(){ EmApp.reload(); }, 700); });
                });
                EmApp.qsa('[data-del-emp]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (!confirm('Remove employee?')) return;
                        EmApp.post('delete_employee', { id: btn.getAttribute('data-del-emp') }).then(function (r) {
                            EmApp.showAlert(alertEl, r.message, r.success); if (r.success) setTimeout(function(){ EmApp.reload(); }, 700);
                        });
                    });
                });
                EmApp.qsa('form[data-master-type]').forEach(function (form) {
                    form.addEventListener('submit', function (e) {
                        e.preventDefault(); var fd = new FormData(form); var data = { master_type: form.getAttribute('data-master-type') };
                        fd.forEach(function(v,k){ data[k]=v; });
                        EmApp.post('save_master', data).then(function (r) { EmApp.showAlert(alertEl, r.message, r.success); if (r.success) setTimeout(function(){ EmApp.reload(); }, 700); });
                    });
                });
                EmApp.qsa('[data-del-master]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (!confirm('Remove this item?')) return;
                        EmApp.post('delete_master', { master_type: btn.getAttribute('data-del-master'), id: btn.getAttribute('data-id') }).then(function (r) {
                            EmApp.showAlert(alertEl, r.message, r.success); if (r.success) setTimeout(function(){ EmApp.reload(); }, 700);
                        });
                    });
                });
            });
            </script>
            <?php
            break;

        case 'employee_attendance_report':
            $from = date('Y-m-01');
            $to = date('Y-m-d');
            if (!empty($_GET['from'])) {
                $from = preg_replace('/[^0-9-]/', '', (string) $_GET['from']);
            }
            if (!empty($_GET['to'])) {
                $to = preg_replace('/[^0-9-]/', '', (string) $_GET['to']);
            }
            if ($from === '') {
                $from = date('Y-m-01');
            }
            if ($to === '') {
                $to = date('Y-m-d');
            }
            $filterEmployeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;
            $filterDepartmentId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
            $filterStatus = trim((string) ($_GET['status'] ?? ''));
            if (!$isEmAdmin) {
                $filterEmployeeId = $myEmployeeId;
            }
            $departments = $em['departments'] ?? [];
            $report = auragold_em_get_attendance_datewise_report($conn, $branch_id, [
                'from' => $from,
                'to' => $to,
                'employee_id' => $filterEmployeeId,
                'department_id' => $filterDepartmentId,
                'status' => $filterStatus,
            ]);
            $reportUrl = static function (array $extra = []) use ($from, $to, $filterEmployeeId, $filterDepartmentId, $filterStatus): string {
                $q = array_merge([
                    'from' => $from,
                    'to' => $to,
                    'department_id' => $filterDepartmentId > 0 ? $filterDepartmentId : '',
                    'status' => $filterStatus,
                    'employee_id' => $filterEmployeeId > 0 ? $filterEmployeeId : '',
                ], $extra);
                foreach ($q as $k => $v) {
                    if ($v === '' || $v === 0 || $v === '0') {
                        unset($q[$k]);
                    }
                }
                return 'employee-attendance-report.php?' . http_build_query($q);
            };
            $exportUrl = $reportUrl(['export' => 'csv']);
            ?>
            <div id="emAlert" class="em-alert"></div>
            <div class="em-att-report-page">
                <form class="em-att-report-filters" id="emAttReportFilters" method="get" action="employee-attendance-report.php">
                    <div class="em-att-report-filter-grid">
                        <div class="em-field em-filter-field">
                            <label>Department</label>
                            <select name="department_id" id="emAttRepDepartment">
                                <option value="0">All</option>
                                <?php foreach ($departments as $dep): ?>
                                <option value="<?php echo (int) ($dep['id'] ?? 0); ?>"<?php echo $filterDepartmentId === (int) ($dep['id'] ?? 0) ? ' selected' : ''; ?>>
                                    <?php echo auragold_em_h($dep['name'] ?? ''); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="em-field em-filter-field">
                            <label>Status</label>
                            <select name="status" id="emAttRepStatus">
                                <option value="">All</option>
                                <option value="Active"<?php echo $filterStatus === 'Active' ? ' selected' : ''; ?>>Active</option>
                                <option value="Inactive"<?php echo $filterStatus === 'Inactive' ? ' selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="em-field em-filter-field em-filter-employee">
                            <label>Employee</label>
                            <select name="employee_id" id="emAttRepEmployee"<?php echo $isEmAdmin ? '' : ' disabled'; ?>>
                                <?php if ($isEmAdmin): ?><option value="0">All</option><?php endif; ?>
                                <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo (int) ($emp['id'] ?? 0); ?>"<?php echo $filterEmployeeId === (int) ($emp['id'] ?? 0) ? ' selected' : ''; ?>>
                                    <?php echo auragold_em_h(auragold_em_employee_name($emp) . ' (' . ($emp['employee_code'] ?? '') . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$isEmAdmin && $myEmployeeId > 0): ?>
                            <input type="hidden" name="employee_id" value="<?php echo (int) $myEmployeeId; ?>">
                            <?php endif; ?>
                        </div>
                        <div class="em-field em-filter-field">
                            <label>From Date</label>
                            <input type="date" name="from" id="emAttRepFrom" value="<?php echo auragold_em_h($from); ?>" required>
                        </div>
                        <div class="em-field em-filter-field">
                            <label>To Date</label>
                            <input type="date" name="to" id="emAttRepTo" value="<?php echo auragold_em_h($to); ?>" required>
                        </div>
                        <div class="em-att-report-filter-actions">
                            <button type="submit" class="em-btn em-btn-search-att">Search</button>
                            <a class="em-btn em-btn-clear-att" href="employee-attendance-report.php" title="Clear filters">&times;</a>
                        </div>
                    </div>
                </form>

                <?php if (!empty($report['truncated'])): ?>
                <div class="em-alert show em-alert-warning" style="margin-bottom:12px;">
                    Date range exceeds 62 days — showing first 62 days only. Narrow the range for full detail.
                </div>
                <?php endif; ?>

                <div class="em-att-report-toolbar">
                    <a class="em-btn em-btn-excel-att" href="<?php echo auragold_em_h($exportUrl); ?>">Excel</a>
                    <div class="em-att-report-search-wrap">
                        <label for="emAttRepSearch">Search:</label>
                        <input type="search" id="emAttRepSearch" placeholder="Filter employees…" autocomplete="off">
                    </div>
                </div>

                <div class="em-att-report-legend">
                    <span><i class="em-att-cell em-att-p">P</i> Present</span>
                    <span><i class="em-att-cell em-att-a">A</i> Absent</span>
                </div>

                <div class="em-table-wrap em-att-report-table-wrap">
                    <table class="em-table em-att-report-table" id="emAttReportTable">
                        <thead>
                            <tr>
                                <th class="em-att-sticky-col em-att-col-emp">Employee</th>
                                <th class="em-att-sticky-col em-att-col-dept">Department</th>
                                <th class="em-att-sticky-col em-att-col-desig">Designation</th>
                                <th class="em-att-sticky-col em-att-col-sal">Monthly Salary</th>
                                <?php foreach ($report['dates'] as $day): ?>
                                <th class="em-att-day-col" title="<?php echo auragold_em_h($day['ymd'] ?? ''); ?>"><?php echo auragold_em_h($day['label'] ?? ''); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($report['rows'])): ?>
                        <tr><td colspan="<?php echo 4 + count($report['dates']); ?>" class="em-empty">No employees found for the selected filters.</td></tr>
                        <?php else: foreach ($report['rows'] as $row): ?>
                        <tr data-search="<?php echo auragold_em_h(strtolower(($row['employee_name'] ?? '') . ' ' . ($row['employee_code'] ?? '') . ' ' . ($row['department_name'] ?? '') . ' ' . ($row['manager_name'] ?? ''))); ?>">
                            <td class="em-att-sticky-col em-att-col-emp"><strong><?php echo auragold_em_h($row['employee_name'] ?? ''); ?></strong></td>
                            <td class="em-att-sticky-col em-att-col-dept"><?php echo auragold_em_h($row['department_name'] ?? '—'); ?></td>
                            <td class="em-att-sticky-col em-att-col-desig"><?php echo auragold_em_h($row['manager_name'] ?? '—'); ?></td>
                            <td class="em-att-sticky-col em-att-col-sal num"><?php echo auragold_em_h(number_format((float) ($row['monthly_salary'] ?? 0), 2, '.', '')); ?></td>
                            <?php foreach ($report['dates'] as $day):
                                $ymd = (string) ($day['ymd'] ?? '');
                                $code = (string) ($row['cells'][$ymd] ?? '');
                                $cls = $code === 'P' ? 'em-att-p' : ($code === 'A' ? 'em-att-a' : 'em-att-blank');
                            ?>
                            <td class="em-att-day-cell"><span class="em-att-cell <?php echo auragold_em_h($cls); ?>"><?php echo auragold_em_h($code); ?></span></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="em-att-report-foot">
                    Showing <?php echo count($report['rows']); ?> employee(s)
                    · <?php echo auragold_em_h(auragold_em_format_date($from)); ?> to <?php echo auragold_em_h(auragold_em_format_date($to)); ?>
                </div>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var search = document.getElementById('emAttRepSearch');
                var tbody = document.querySelector('#emAttReportTable tbody');
                if (search && tbody) {
                    search.addEventListener('input', function () {
                        var q = (search.value || '').trim().toLowerCase();
                        tbody.querySelectorAll('tr[data-search]').forEach(function (tr) {
                            tr.style.display = !q || tr.getAttribute('data-search').indexOf(q) !== -1 ? '' : 'none';
                        });
                    });
                }
            });
            </script>
            <?php
            break;

        case 'expense_category':
            $categories = auragold_em_get_expense_categories($conn, false);
            ?>
            <div id="emAlert" class="em-alert"></div>
            <div class="em-toolbar">
                <div class="em-toolbar-right">
                    <button type="button" class="em-btn em-btn-primary" id="emCatOpenBtn">Add Category</button>
                </div>
            </div>
            <div class="em-table-wrap">
                <table class="em-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Sort</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="5" class="em-empty">No expense categories yet. Use Add Category to create the first one.</td></tr>
                    <?php else: foreach ($categories as $row): ?>
                        <tr>
                            <td><?php echo auragold_em_h($row['name'] ?? ''); ?></td>
                            <td><?php echo auragold_em_h($row['type'] ?? ''); ?></td>
                            <td><?php echo (int) ($row['sort_order'] ?? 0); ?></td>
                            <td><?php echo auragold_em_badge(((int) ($row['status'] ?? 0) === 1) ? 'Active' : 'Inactive'); ?></td>
                            <td class="em-actions">
                                <button type="button"
                                    data-edit-cat="<?php echo (int) $row['id']; ?>"
                                    data-name="<?php echo auragold_em_h($row['name'] ?? ''); ?>"
                                    data-type="<?php echo auragold_em_h($row['type'] ?? ''); ?>"
                                    data-sort="<?php echo (int) ($row['sort_order'] ?? 0); ?>"
                                    data-status="<?php echo (int) ($row['status'] ?? 1); ?>">Edit</button>
                                <?php if ((int) ($row['status'] ?? 0) === 1): ?>
                                <button type="button" class="danger" data-del-cat="<?php echo (int) $row['id']; ?>">Deactivate</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div id="emExpenseCatModal" class="em-modal-backdrop">
                <div class="em-modal">
                    <div class="em-modal-head"><h3 id="emCatModalTitle">Add Category</h3><button type="button" class="em-close">&times;</button></div>
                    <form id="emExpenseCatForm">
                        <input type="hidden" name="id" id="emCatId" value="0">
                        <div class="em-modal-body">
                            <div class="em-form-grid">
                                <div class="em-field"><label>Category Name</label><input type="text" name="name" id="emCatName" required placeholder="e.g. Travel"></div>
                                <div class="em-field"><label>Type</label><input type="text" name="type" id="emCatType" placeholder="e.g. Indirect Expenses"></div>
                                <div class="em-field"><label>Sort Order</label><input type="number" name="sort_order" id="emCatSort" value="0" min="0"></div>
                                <div class="em-field"><label>Status</label>
                                    <select name="status" id="emCatStatus">
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="em-modal-foot">
                            <button type="button" class="em-btn em-btn-light em-close">Cancel</button>
                            <button type="submit" class="em-btn em-btn-primary" id="emCatSubmitBtn">Save</button>
                        </div>
                    </form>
                </div>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var alertEl = document.getElementById('emAlert');
                var form = document.getElementById('emExpenseCatForm');
                var titleEl = document.getElementById('emCatModalTitle');
                var submitBtn = document.getElementById('emCatSubmitBtn');
                function resetCatForm() {
                    if (!form) return;
                    form.reset();
                    document.getElementById('emCatId').value = '0';
                    document.getElementById('emCatStatus').value = '1';
                    if (titleEl) titleEl.textContent = 'Add Category';
                    if (submitBtn) submitBtn.textContent = 'Save';
                }
                var openBtn = document.getElementById('emCatOpenBtn');
                if (openBtn) {
                    openBtn.addEventListener('click', function () {
                        resetCatForm();
                        EmApp.openModal('emExpenseCatModal');
                    });
                }
                if (form) {
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        var fd = new FormData(form);
                        var data = {};
                        fd.forEach(function (v, k) { data[k] = v; });
                        EmApp.post('save_expense_category', data).then(function (r) {
                            EmApp.showAlert(alertEl, r.message, r.success);
                            if (r.success) {
                                EmApp.closeModal('emExpenseCatModal');
                                setTimeout(function () { EmApp.reload(); }, 600);
                            }
                        }).catch(function () {
                            EmApp.showAlert(alertEl, 'Could not save category.', false);
                        });
                    });
                }
                EmApp.qsa('[data-edit-cat]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        document.getElementById('emCatId').value = btn.getAttribute('data-edit-cat') || '0';
                        document.getElementById('emCatName').value = btn.getAttribute('data-name') || '';
                        document.getElementById('emCatType').value = btn.getAttribute('data-type') || '';
                        document.getElementById('emCatSort').value = btn.getAttribute('data-sort') || '0';
                        document.getElementById('emCatStatus').value = btn.getAttribute('data-status') || '1';
                        if (titleEl) titleEl.textContent = 'Edit Category';
                        if (submitBtn) submitBtn.textContent = 'Update';
                        EmApp.openModal('emExpenseCatModal');
                    });
                });
                EmApp.qsa('[data-del-cat]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (!confirm('Deactivate this expense category?')) return;
                        EmApp.post('delete_expense_category', { id: btn.getAttribute('data-del-cat') }).then(function (r) {
                            EmApp.showAlert(alertEl, r.message, r.success);
                            if (r.success) setTimeout(function () { EmApp.reload(); }, 600);
                        });
                    });
                });
            });
            </script>
            <?php
            break;

        case 'expenses':
            $categories = auragold_em_get_expense_categories($conn, true);
            $expenseRows = auragold_em_get_expenses($conn, $branch_id, $scopeEmployeeId > 0 ? $scopeEmployeeId : 0);
            $catOpts = '<option value="">— Select category —</option>';
            foreach ($categories as $cat) {
                $catOpts .= '<option value="' . (int) $cat['id'] . '">' . auragold_em_h($cat['name'] ?? '');
                if (!empty($cat['type'])) {
                    $catOpts .= ' (' . auragold_em_h($cat['type']) . ')';
                }
                $catOpts .= '</option>';
            }
            ?>
            <div id="emAlert" class="em-alert"></div>
            <div class="em-toolbar">
                <div class="em-toolbar-left">
                    <a class="em-btn em-btn-light" href="expense-category.php">Manage Categories</a>
                </div>
                <div class="em-toolbar-right">
                    <button type="button" class="em-btn em-btn-primary" id="emExpOpenBtn">Add Expense</button>
                </div>
            </div>
            <div class="em-table-wrap">
                <table class="em-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Employee</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($expenseRows)): ?>
                        <tr><td colspan="8" class="em-empty">No expenses recorded yet. Use Add Expense to create the first entry.</td></tr>
                    <?php else: foreach ($expenseRows as $row):
                        $hasEmp = (int) ($row['employee_id'] ?? 0) > 0;
                        $empLabel = $hasEmp
                            ? auragold_em_employee_name($row) . (!empty($row['employee_code']) ? ' (' . $row['employee_code'] . ')' : '')
                            : '—';
                    ?>
                        <tr>
                            <td><?php echo auragold_em_h(auragold_em_format_date($row['expense_date'] ?? '')); ?></td>
                            <td><?php echo auragold_em_h($row['category_name'] ?? ''); ?></td>
                            <td><?php echo auragold_em_h($empLabel); ?></td>
                            <td><?php echo auragold_em_h(auragold_em_format_money($row['amount'] ?? 0)); ?></td>
                            <td><?php echo auragold_em_h($row['payment_mode'] ?? ''); ?></td>
                            <td><?php echo auragold_em_badge((string) ($row['status'] ?? '')); ?></td>
                            <td><?php
                                $desc = (string) ($row['description'] ?? '');
                                echo auragold_em_h(strlen($desc) > 50 ? substr($desc, 0, 50) . '…' : $desc);
                            ?></td>
                            <td class="em-actions">
                                <button type="button"
                                    data-edit-exp="<?php echo (int) $row['id']; ?>"
                                    data-employee-id="<?php echo (int) ($row['employee_id'] ?? 0); ?>"
                                    data-against-employee="<?php echo $hasEmp ? '1' : '0'; ?>"
                                    data-category-id="<?php echo (int) ($row['category_id'] ?? 0); ?>"
                                    data-expense-date="<?php echo auragold_em_h($row['expense_date'] ?? ''); ?>"
                                    data-amount="<?php echo auragold_em_h(number_format((float) ($row['amount'] ?? 0), 2, '.', '')); ?>"
                                    data-payment-mode="<?php echo auragold_em_h($row['payment_mode'] ?? 'Cash'); ?>"
                                    data-status="<?php echo auragold_em_h($row['status'] ?? 'Paid'); ?>"
                                    data-description="<?php echo auragold_em_h($row['description'] ?? ''); ?>">Edit</button>
                                <button type="button" class="danger" data-del-exp="<?php echo (int) $row['id']; ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div id="emExpenseModal" class="em-modal-backdrop">
                <div class="em-modal" style="max-width:560px;">
                    <div class="em-modal-head"><h3 id="emExpModalTitle">Add Expense</h3><button type="button" class="em-close">&times;</button></div>
                    <form id="emExpenseForm">
                        <input type="hidden" name="id" id="emExpId" value="0">
                        <div class="em-modal-body">
                            <div class="em-form-grid">
                                <div class="em-field"><label>Expense Date</label><input type="date" name="expense_date" id="emExpDate" value="<?php echo auragold_em_h(date('Y-m-d')); ?>" required></div>
                                <div class="em-field"><label>Category</label><select name="category_id" id="emExpCategory" required><?php echo $catOpts; ?></select></div>
                                <div class="em-field"><label>Amount</label><input type="number" step="0.01" min="0.01" name="amount" id="emExpAmount" value="0" required></div>
                                <div class="em-field"><label>Payment Mode</label>
                                    <select name="payment_mode" id="emExpPayment">
                                        <option>Cash</option>
                                        <option>Bank</option>
                                        <option>UPI</option>
                                        <option>Card</option>
                                        <option>Cheque</option>
                                    </select>
                                </div>
                                <div class="em-field" style="grid-column:1/-1;">
                                    <label class="em-field-check" for="emExpAgainstEmp">
                                        <input type="checkbox" name="against_employee" id="emExpAgainstEmp" value="1">
                                        <span>Expense against employee</span>
                                    </label>
                                </div>
                                <div class="em-field" id="emExpEmployeeWrap" style="grid-column:1/-1;display:none;">
                                    <label>Employee Name</label>
                                    <select name="employee_id" id="emExpEmployee"<?php echo $empSelectAttrs; ?>><?php echo auragold_em_employee_options($employees, $myEmployeeId, $empOptsSelf); ?></select>
                                    <?php if ($empOptsSelf && $myEmployeeId > 0): ?>
                                    <input type="hidden" name="employee_id" id="emExpEmployeeHidden" value="<?php echo (int) $myEmployeeId; ?>">
                                    <?php endif; ?>
                                </div>
                                <div class="em-field"><label>Status</label>
                                    <select name="status" id="emExpStatus">
                                        <option>Paid</option>
                                        <option>Pending</option>
                                        <option>Draft</option>
                                    </select>
                                </div>
                                <div class="em-field" style="grid-column:1/-1;"><label>Description / Notes</label><textarea name="description" id="emExpDesc" rows="2" placeholder="Optional notes"></textarea></div>
                            </div>
                        </div>
                        <div class="em-modal-foot">
                            <button type="button" class="em-btn em-btn-light em-close">Cancel</button>
                            <button type="submit" class="em-btn em-btn-primary" id="emExpSubmitBtn">Save</button>
                        </div>
                    </form>
                </div>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var alertEl = document.getElementById('emAlert');
                var form = document.getElementById('emExpenseForm');
                var againstEl = document.getElementById('emExpAgainstEmp');
                var empWrap = document.getElementById('emExpEmployeeWrap');
                var titleEl = document.getElementById('emExpModalTitle');
                var submitBtn = document.getElementById('emExpSubmitBtn');

                function toggleEmployeeField() {
                    var on = againstEl && againstEl.checked;
                    if (empWrap) empWrap.style.display = on ? '' : 'none';
                    var empSel = document.getElementById('emExpEmployee');
                    if (empSel) {
                        if (on) empSel.setAttribute('required', 'required');
                        else empSel.removeAttribute('required');
                    }
                }
                if (againstEl) againstEl.addEventListener('change', toggleEmployeeField);

                function resetExpForm() {
                    if (!form) return;
                    form.reset();
                    document.getElementById('emExpId').value = '0';
                    document.getElementById('emExpDate').value = '<?php echo auragold_em_h(date('Y-m-d')); ?>';
                    document.getElementById('emExpAmount').value = '0';
                    if (againstEl) againstEl.checked = false;
                    toggleEmployeeField();
                    if (titleEl) titleEl.textContent = 'Add Expense';
                    if (submitBtn) submitBtn.textContent = 'Save';
                }

                var openBtn = document.getElementById('emExpOpenBtn');
                if (openBtn) {
                    openBtn.addEventListener('click', function () {
                        resetExpForm();
                        EmApp.openModal('emExpenseModal');
                    });
                }

                if (form) {
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        var fd = new FormData(form);
                        var data = {};
                        fd.forEach(function (v, k) { data[k] = v; });
                        data.against_employee = (againstEl && againstEl.checked) ? '1' : '0';
                        if (data.against_employee !== '1') {
                            data.employee_id = '0';
                        }
                        var amt = parseFloat(data.amount || '0');
                        if (!(amt > 0)) {
                            EmApp.showAlert(alertEl, 'Enter a valid expense amount.', false);
                            return;
                        }
                        if (!data.category_id) {
                            EmApp.showAlert(alertEl, 'Select an expense category.', false);
                            return;
                        }
                        if (data.against_employee === '1' && !data.employee_id) {
                            EmApp.showAlert(alertEl, 'Select employee name for this expense.', false);
                            return;
                        }
                        EmApp.post('save_expense', data).then(function (r) {
                            EmApp.showAlert(alertEl, r.message, r.success);
                            if (r.success) {
                                EmApp.closeModal('emExpenseModal');
                                setTimeout(function () { EmApp.reload(); }, 600);
                            }
                        }).catch(function () {
                            EmApp.showAlert(alertEl, 'Could not save expense.', false);
                        });
                    });
                }

                EmApp.qsa('[data-edit-exp]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        document.getElementById('emExpId').value = btn.getAttribute('data-edit-exp') || '0';
                        document.getElementById('emExpDate').value = btn.getAttribute('data-expense-date') || '';
                        document.getElementById('emExpCategory').value = btn.getAttribute('data-category-id') || '';
                        document.getElementById('emExpAmount').value = btn.getAttribute('data-amount') || '0';
                        document.getElementById('emExpPayment').value = btn.getAttribute('data-payment-mode') || 'Cash';
                        document.getElementById('emExpStatus').value = btn.getAttribute('data-status') || 'Paid';
                        document.getElementById('emExpDesc').value = btn.getAttribute('data-description') || '';
                        var against = btn.getAttribute('data-against-employee') === '1';
                        if (againstEl) againstEl.checked = against;
                        var empSel = document.getElementById('emExpEmployee');
                        if (empSel) empSel.value = btn.getAttribute('data-employee-id') || '';
                        toggleEmployeeField();
                        if (titleEl) titleEl.textContent = 'Edit Expense';
                        if (submitBtn) submitBtn.textContent = 'Update';
                        EmApp.openModal('emExpenseModal');
                    });
                });

                EmApp.qsa('[data-del-exp]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (!confirm('Delete this expense?')) return;
                        EmApp.post('delete_expense', { id: btn.getAttribute('data-del-exp') }).then(function (r) {
                            EmApp.showAlert(alertEl, r.message, r.success);
                            if (r.success) setTimeout(function () { EmApp.reload(); }, 600);
                        });
                    });
                });
            });
            </script>
            <?php
            break;

        default:
            echo '<div class="em-empty">Page not configured.</div>';
    }
}
