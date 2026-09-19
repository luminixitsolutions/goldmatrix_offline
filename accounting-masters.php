<?php
session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();

$accTablesOk = false;
$chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_accounting_financial_years'");
if ($chk && mysqli_num_rows($chk) > 0) {
    $accTablesOk = true;
    mysqli_free_result($chk);
}

$modes = [];
$calc = null;
$years = [];

if ($accTablesOk) {
    $modes = getList("SELECT id, name FROM tbl_accounting_master_modes WHERE status = 1 ORDER BY sort_order ASC, id ASC");
    $calc = getRecord("SELECT * FROM tbl_accounting_calculation_settings ORDER BY id ASC LIMIT 1");
    $years = getList("SELECT id, start_date, end_date, is_active FROM tbl_accounting_financial_years WHERE status = 1 ORDER BY start_date ASC, id ASC");
}

if (!$calc) {
    $calc = [
        'mode_id' => 0,
        'amount_decimal' => 2,
        'amount_round' => 1,
        'weight_decimal' => 3,
        'weight_round' => 1,
        'percent_decimal' => 3,
        'percent_round' => 1,
    ];
} else {
    $calc['mode_id'] = (int) ($calc['mode_id'] ?? 0);
    $calc['amount_decimal'] = (int) ($calc['amount_decimal'] ?? 2);
    $calc['amount_round'] = (int) ($calc['amount_round'] ?? 1);
    $calc['weight_decimal'] = (int) ($calc['weight_decimal'] ?? 3);
    $calc['weight_round'] = (int) ($calc['weight_round'] ?? 1);
    $calc['percent_decimal'] = (int) ($calc['percent_decimal'] ?? 3);
    $calc['percent_round'] = (int) ($calc['percent_round'] ?? 1);
}

if ($accTablesOk && count($years) === 0) {
    $years = [
        ['id' => 0, 'start_date' => '2025-04-01', 'end_date' => '2026-03-31', 'is_active' => 1],
    ];
} elseif (!$accTablesOk) {
    $years = [
        ['id' => 0, 'start_date' => '2025-04-01', 'end_date' => '2026-03-31', 'is_active' => 1],
    ];
} else {
    foreach ($years as &$y) {
        $y['id'] = (int) $y['id'];
        $y['is_active'] = (int) ($y['is_active'] ?? 0);
    }
    unset($y);
}

$modeIdSelected = (int) ($calc['mode_id'] ?? 0);
$modeIdsValid = [];
foreach ($modes as $m) {
    $modeIdsValid[(int) $m['id']] = true;
}
if ($modeIdSelected > 0 && empty($modeIdsValid[$modeIdSelected])) {
    $modeIdSelected = 0;
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Accounting Masters - Set Software - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="set-software-sidebar.css">
    <style>
        :root { --acc-navy: #11294b; --acc-navy-dark: #0d1f38; }
        .acc-master-page { padding: 20px 24px; width: 100%; box-sizing: border-box; }
        .acc-master-page h1 { font-size: 1.45rem; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
        .acc-banner {
            background: #fef3c7; border: 1px solid #fcd34d; color: #92400e;
            padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px;
        }
        .acc-grid {
            display: grid;
            grid-template-columns: minmax(280px, 340px) 1fr;
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 991px) {
            .acc-grid { grid-template-columns: 1fr; }
        }
        .acc-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .acc-card-head {
            padding: 12px 16px;
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--acc-navy);
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-bottom: 2px solid rgba(17, 41, 75, 0.2);
        }
        .acc-card-body { padding: 16px; }
        .acc-field { margin-bottom: 14px; }
        .acc-field:last-child { margin-bottom: 0; }
        .acc-field label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .acc-field-row {
            display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
        }
        .acc-field-row input[type="number"] { width: 88px; }
        .acc-field-row .form-check { margin: 0; padding-left: 1.5rem; }
        .acc-icon-save {
            border: none; background: transparent; color: var(--acc-navy);
            padding: 4px 6px; border-radius: 6px; cursor: pointer;
        }
        .acc-icon-save:hover { background: rgba(17, 41, 75, 0.08); }
        .acc-btn-primary {
            background: linear-gradient(135deg, var(--acc-navy) 0%, var(--acc-navy-dark) 100%);
            color: #fff; border: none; padding: 8px 18px; border-radius: 8px;
            font-size: 13px; font-weight: 600; cursor: pointer;
        }
        .acc-btn-primary:hover { opacity: 0.95; }
        .acc-btn-primary:disabled { opacity: 0.55; cursor: not-allowed; }
        .acc-btn-outline {
            background: #fff; color: var(--acc-navy); border: 2px solid var(--acc-navy);
            padding: 8px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;
        }
        .acc-btn-outline:hover { background: rgba(17, 41, 75, 0.06); }
        .acc-fy-toolbar { display: flex; justify-content: flex-end; align-items: center; margin-bottom: 12px; gap: 10px; }
        .acc-table-wrap { border: 1px solid #e2e8f0; border-radius: 10px; overflow: auto; }
        .acc-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .acc-table th, .acc-table td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; vertical-align: middle; }
        .acc-table th { background: #f8fafc; font-weight: 600; color: #374151; text-align: left; white-space: nowrap; }
        .acc-table input[type="date"] { min-width: 140px; font-size: 13px; }
        .acc-msg { font-size: 13px; margin-top: 8px; }
        .acc-msg.ok { color: #059669; }
        .acc-msg.err { color: #dc2626; }
        .acc-add-head { cursor: pointer; color: var(--acc-navy); padding: 0 4px; }
        .acc-add-head:hover { color: var(--acc-navy-dark); }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="layout-content">
        <div class="container-fluid w-100" style="padding-top: 0; padding-bottom: 0; max-width: none;">
            <div class="set-software-wrapper">
                <?php include 'set-software-sidebar.php'; ?>
                <div class="set-software-main">
                    <div class="acc-master-page">
                        <h1>Accounting Masters</h1>
                        <?php if (!$accTablesOk): ?>
                        <div class="acc-banner">
                            Database tables for this screen are not installed yet. Run the script
                            <code>admin/sql/create_tbl_accounting_masters.sql</code> on your branch database, then reload this page.
                        </div>
                        <?php endif; ?>

                        <div class="acc-grid">
                            <!-- Calculation Mode -->
                            <div class="acc-card">
                                <div class="acc-card-head">Calculation Mode</div>
                                <div class="acc-card-body">
                                    <div class="acc-field">
                                        <label for="accModeId">Modes</label>
                                        <select id="accModeId" class="form-control" <?php echo !$accTablesOk ? 'disabled' : ''; ?>>
                                            <option value=""<?php echo ($modeIdSelected === 0) ? ' selected' : ''; ?>>Select…</option>
                                            <?php foreach ($modes as $m): ?>
                                                <option value="<?php echo (int) $m['id']; ?>"<?php echo ((int) $m['id'] === $modeIdSelected) ? ' selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($m['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                            <?php if (empty($modes)): ?>
                                                <option value="1">Last Purchase Rate</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="acc-field">
                                        <label>Amount Decimal</label>
                                        <div class="acc-field-row">
                                            <input type="number" id="accAmountDec" class="form-control" min="0" max="8" step="1"
                                                value="<?php echo (int) $calc['amount_decimal']; ?>" <?php echo !$accTablesOk ? 'disabled' : ''; ?>>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="accAmountRound" <?php echo !empty($calc['amount_round']) ? 'checked' : ''; ?> <?php echo !$accTablesOk ? 'disabled' : ''; ?>>
                                                <label class="form-check-label" for="accAmountRound">Round</label>
                                            </div>
                                            <button type="button" class="acc-icon-save acc-save-calc" title="Save calculation settings" <?php echo !$accTablesOk ? 'disabled' : ''; ?>><i class="feather icon-save"></i></button>
                                        </div>
                                    </div>
                                    <div class="acc-field">
                                        <label>Weight Decimal</label>
                                        <div class="acc-field-row">
                                            <input type="number" id="accWeightDec" class="form-control" min="0" max="8" step="1"
                                                value="<?php echo (int) $calc['weight_decimal']; ?>" <?php echo !$accTablesOk ? 'disabled' : ''; ?>>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="accWeightRound" <?php echo !empty($calc['weight_round']) ? 'checked' : ''; ?> <?php echo !$accTablesOk ? 'disabled' : ''; ?>>
                                                <label class="form-check-label" for="accWeightRound">Round</label>
                                            </div>
                                            <button type="button" class="acc-icon-save acc-save-calc" title="Save calculation settings" <?php echo !$accTablesOk ? 'disabled' : ''; ?>><i class="feather icon-save"></i></button>
                                        </div>
                                    </div>
                                    <div class="acc-field">
                                        <label>Percent Decimal</label>
                                        <div class="acc-field-row">
                                            <input type="number" id="accPercentDec" class="form-control" min="0" max="8" step="1"
                                                value="<?php echo (int) $calc['percent_decimal']; ?>" <?php echo !$accTablesOk ? 'disabled' : ''; ?>>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="accPercentRound" <?php echo !empty($calc['percent_round']) ? 'checked' : ''; ?> <?php echo !$accTablesOk ? 'disabled' : ''; ?>>
                                                <label class="form-check-label" for="accPercentRound">Round</label>
                                            </div>
                                            <button type="button" class="acc-icon-save acc-save-calc" title="Save calculation settings" <?php echo !$accTablesOk ? 'disabled' : ''; ?>><i class="feather icon-save"></i></button>
                                        </div>
                                    </div>
                                    <p class="text-muted small mb-0">Use the save icon (or button below) to store amount, weight, and percent rules together.</p>
                                    <button type="button" class="acc-btn-outline mt-3 acc-save-calc" <?php echo !$accTablesOk ? 'disabled' : ''; ?>>Save calculation settings</button>
                                    <div id="accCalcMsg" class="acc-msg"></div>
                                </div>
                            </div>

                            <!-- Financial Year -->
                            <div class="acc-card">
                                <div class="acc-card-head d-flex justify-content-between align-items-center">
                                    <span>Financial Year</span>
                                    <button type="button" class="acc-btn-primary" id="accFySave" <?php echo !$accTablesOk ? 'disabled' : ''; ?>>Save</button>
                                </div>
                                <div class="acc-card-body">
                                    <div class="acc-table-wrap">
                                        <table class="acc-table" id="accFyTable">
                                            <thead>
                                                <tr>
                                                    <th style="width:48px;"></th>
                                                    <th>Starting Year</th>
                                                    <th>
                                                        Ending Year
                                                        <span class="acc-add-head" id="accFyAdd" title="Add row" role="button" tabindex="0"><i class="feather icon-plus"></i></span>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody id="accFyBody">
                                                <?php foreach ($years as $y): ?>
                                                <tr data-id="<?php echo (int) $y['id']; ?>">
                                                    <td class="text-center">
                                                        <input type="radio" name="fy_active" class="fy-radio" value="<?php echo (int) $y['id']; ?>"
                                                            <?php echo !empty($y['is_active']) ? 'checked' : ''; ?>>
                                                    </td>
                                                    <td><input type="date" class="form-control form-control-sm fy-start" value="<?php echo htmlspecialchars($y['start_date'] ?? ''); ?>"></td>
                                                    <td><input type="date" class="form-control form-control-sm fy-end" value="<?php echo htmlspecialchars($y['end_date'] ?? ''); ?>"></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div id="accFyMsg" class="acc-msg"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer-script.php'; ?>
    <script>
    (function () {
        var tablesOk = <?php echo $accTablesOk ? 'true' : 'false'; ?>;

        function postForm(data) {
            return fetch('ajax/accounting-masters.php', {
                method: 'POST',
                body: data,
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); });
        }

        function saveCalculation() {
            var msg = document.getElementById('accCalcMsg');
            msg.textContent = '';
            msg.className = 'acc-msg';
            var modeVal = document.getElementById('accModeId').value;
            if (!modeVal || String(modeVal).trim() === '') {
                msg.textContent = 'Please select a mode from the list.';
                msg.className = 'acc-msg err';
                return;
            }
            var fd = new FormData();
            fd.append('action', 'save_calculation');
            fd.append('mode_id', modeVal);
            fd.append('amount_decimal', document.getElementById('accAmountDec').value);
            fd.append('amount_round', document.getElementById('accAmountRound').checked ? '1' : '');
            fd.append('weight_decimal', document.getElementById('accWeightDec').value);
            fd.append('weight_round', document.getElementById('accWeightRound').checked ? '1' : '');
            fd.append('percent_decimal', document.getElementById('accPercentDec').value);
            fd.append('percent_round', document.getElementById('accPercentRound').checked ? '1' : '');
            postForm(fd).then(function (data) {
                if (data.status === 'success') {
                    msg.textContent = 'Calculation settings saved.';
                    msg.className = 'acc-msg ok';
                } else {
                    msg.textContent = data.message || 'Save failed.';
                    msg.className = 'acc-msg err';
                }
            }).catch(function () {
                msg.textContent = 'Request failed.';
                msg.className = 'acc-msg err';
            });
        }

        document.querySelectorAll('.acc-save-calc').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!tablesOk) return;
                saveCalculation();
            });
        });

        function collectFyRows() {
            var rows = [];
            var tbody = document.getElementById('accFyBody');
            var trs = tbody.querySelectorAll('tr');
            trs.forEach(function (tr) {
                var id = parseInt(tr.getAttribute('data-id'), 10) || 0;
                var start = tr.querySelector('.fy-start').value;
                var end = tr.querySelector('.fy-end').value;
                var isActive = tr.querySelector('.fy-radio') && tr.querySelector('.fy-radio').checked ? 1 : 0;
                rows.push({ id: id, start_date: start, end_date: end, is_active: isActive });
            });
            return rows;
        }

        function syncRadioValues() {
            document.querySelectorAll('#accFyBody tr').forEach(function (tr) {
                var id = parseInt(tr.getAttribute('data-id'), 10) || 0;
                var r = tr.querySelector('.fy-radio');
                if (r) r.value = String(id);
            });
        }

        /** Financial year end = one day before the same calendar day one year after start (e.g. 01-04-2026 → 31-03-2027). */
        function formatYmd(d) {
            var y = d.getFullYear();
            var m = String(d.getMonth() + 1);
            if (m.length === 1) m = '0' + m;
            var day = String(d.getDate());
            if (day.length === 1) day = '0' + day;
            return y + '-' + m + '-' + day;
        }

        function fyEndDateFromStart(ymd) {
            if (!ymd || !/^\d{4}-\d{2}-\d{2}$/.test(ymd)) {
                return '';
            }
            var parts = ymd.split('-');
            var y = parseInt(parts[0], 10);
            var mo = parseInt(parts[1], 10);
            var da = parseInt(parts[2], 10);
            var start = new Date(y, mo - 1, da);
            if (isNaN(start.getTime())) {
                return '';
            }
            var end = new Date(y + 1, mo - 1, da);
            end.setDate(end.getDate() - 1);
            return formatYmd(end);
        }

        function applyFyEndFromStart(startInput) {
            if (!startInput || !startInput.classList || !startInput.classList.contains('fy-start')) {
                return;
            }
            var tr = startInput.closest('tr');
            if (!tr) return;
            var endIn = tr.querySelector('.fy-end');
            if (!endIn) return;
            if (!startInput.value) {
                endIn.value = '';
                return;
            }
            var end = fyEndDateFromStart(startInput.value);
            if (end) {
                endIn.value = end;
            }
        }

        document.getElementById('accFyAdd').addEventListener('click', function () {
            var tbody = document.getElementById('accFyBody');
            var tr = document.createElement('tr');
            tr.setAttribute('data-id', '0');
            tr.innerHTML = '<td class="text-center"><input type="radio" name="fy_active" class="fy-radio" value="0"></td>' +
                '<td><input type="date" class="form-control form-control-sm fy-start" value=""></td>' +
                '<td><input type="date" class="form-control form-control-sm fy-end" value=""></td>';
            tbody.appendChild(tr);
            syncRadioValues();
        });

        document.getElementById('accFyBody').addEventListener('change', function (e) {
            if (e.target.classList.contains('fy-start')) {
                applyFyEndFromStart(e.target);
                return;
            }
            if (e.target.classList.contains('fy-radio') && e.target.checked) {
                syncRadioValues();
            }
        });

        document.getElementById('accFyBody').addEventListener('input', function (e) {
            if (e.target.classList.contains('fy-start')) {
                applyFyEndFromStart(e.target);
            }
        });

        document.getElementById('accFySave').addEventListener('click', function () {
            if (!tablesOk) return;
            var msg = document.getElementById('accFyMsg');
            msg.textContent = '';
            msg.className = 'acc-msg';
            var rows = collectFyRows();
            var fd = new FormData();
            fd.append('action', 'save_financial_years');
            fd.append('years_json', JSON.stringify(rows));
            var btn = document.getElementById('accFySave');
            btn.disabled = true;
            postForm(fd).then(function (data) {
                btn.disabled = false;
                if (data.status === 'success') {
                    msg.textContent = 'Financial years saved. Reloading…';
                    msg.className = 'acc-msg ok';
                    window.location.reload();
                } else {
                    msg.textContent = data.message || 'Save failed.';
                    msg.className = 'acc-msg err';
                }
            }).catch(function () {
                btn.disabled = false;
                msg.textContent = 'Request failed.';
                msg.className = 'acc-msg err';
            });
        });

        syncRadioValues();
    })();
    </script>
</body>
</html>
