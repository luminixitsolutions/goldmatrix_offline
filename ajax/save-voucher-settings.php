<?php
session_start();
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

try {
    require_once dirname(__DIR__) . '/includes/auragold_voucher_settings_schema.php';

    $settings_bid = auragold_resolve_voucher_settings_branch_id(
        isset($_POST['settings_branch_id']) ? (int) $_POST['settings_branch_id'] : null
    );

    auragold_ensure_tbl_voucher_settings($conn);

    $posted_raw = isset($_POST['settings_by_metal_json']) ? (string) $_POST['settings_by_metal_json'] : '';
    $save_all = !empty($_POST['save_all']);
    $metals_to_save = [];

    if ($save_all && $posted_raw !== '') {
        $decoded = json_decode($posted_raw, true);
        if (!is_array($decoded)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid settings data (JSON parse failed).']);
            exit;
        }
        $allowed = getVoucherSettingMetals();
        foreach ($allowed as $metal) {
            if (isset($decoded[$metal]) && is_array($decoded[$metal])) {
                $metals_to_save[$metal] = auragold_normalize_voucher_metal_row($decoded[$metal]);
            }
        }
    } else {
        $metal_wise = isset($_POST['metal_wise']) ? trim((string) $_POST['metal_wise']) : 'Gold';
        $metals_to_save[$metal_wise] = auragold_normalize_voucher_metal_row([
            'minimum_amount_column' => $_POST['minimum_amount_column'] ?? 'Amount',
            'reverse_calculation_result_column' => $_POST['reverse_calculation_result_column'] ?? 'MakingRate',
            'default_discount_type' => $_POST['default_discount_type'] ?? 'Fix',
            'default_calculation_type' => $_POST['default_calculation_type'] ?? 'Fix',
            'stock_availability_check_by' => $_POST['stock_availability_check_by'] ?? 'Carat',
            'wastage_wt_calculation' => $_POST['wastage_wt_calculation'] ?? 'GoldWt',
        ]);
    }

    if ($metals_to_save === []) {
        echo json_encode(['status' => 'error', 'message' => 'No voucher settings to save.']);
        exit;
    }

    if ($settings_bid <= 0 && auragold_tbl_has_column($conn, 'tbl_voucher_settings', 'branch_id')) {
        echo json_encode(['status' => 'error', 'message' => 'Branch not resolved. Refresh the page and try again.']);
        exit;
    }

    $saved = [];
    $errors = [];
    foreach ($metals_to_save as $metal => $row) {
        $result = auragold_save_voucher_settings_for_metal($conn, $settings_bid, (string) $metal, $row);
        if ($result['ok']) {
            $saved[] = $metal;
        } else {
            $errors[] = $metal . ': ' . ($result['message'] ?? 'failed');
        }
    }

    $reloaded = getVoucherSettings($settings_bid);

    if ($errors !== []) {
        echo json_encode([
            'status' => count($saved) > 0 ? 'partial' : 'error',
            'message' => 'Some metals could not be saved: ' . implode('; ', $errors),
            'saved_metals' => $saved,
            'branch_id' => $settings_bid,
            'posted_json' => $metals_to_save,
            'reloaded_settings' => $reloaded,
        ]);
        exit;
    }

    $count = count($saved);
    $msg = $count > 1
        ? ('Voucher settings saved for all ' . $count . ' metals.')
        : ('Voucher settings saved for ' . $saved[0] . '.');

    echo json_encode([
        'status' => 'success',
        'message' => $msg,
        'saved_metals' => $saved,
        'branch_id' => $settings_bid,
        'posted_json' => $metals_to_save,
        'reloaded_settings' => $reloaded,
    ]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
