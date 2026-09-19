<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['date']) || !isset($input['data'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Date and data are required'
    ]);
    exit;
}

$date = esc($input['date']);
$data = $input['data'];

try {
    // First check if table exists, if not create it
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_day_reports'");
    if (mysqli_num_rows($check_table) == 0) {
        // Create table if it doesn't exist
        $create_table = "
            CREATE TABLE IF NOT EXISTS `tbl_day_reports` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `report_date` date NOT NULL,
                `opening_amount` decimal(15,2) DEFAULT 0.00,
                `expected_amount` decimal(15,2) DEFAULT 0.00,
                `online_cheque_payment` decimal(15,2) DEFAULT 0.00,
                `closing_cash` decimal(15,2) DEFAULT 0.00,
                `cash_denomination` decimal(15,3) DEFAULT 0.000,
                `difference` decimal(15,2) DEFAULT 0.00,
                `report_data` text DEFAULT NULL,
                `created_by` int(11) DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `report_date` (`report_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        if (!mysqli_query($conn, $create_table)) {
            throw new Exception("Failed to create table: " . mysqli_error($conn));
        }
    }
    
    // Check if day report already exists
    $existing = getRecord("SELECT id FROM tbl_day_reports WHERE report_date = '$date'");
    
    $summary = $data['summary'] ?? [];
    
    if ($existing) {
        // Update existing report
        $sql = "
            UPDATE tbl_day_reports SET
                opening_amount = " . (float)($summary['opening_amount'] ?? 0) . ",
                expected_amount = " . (float)($summary['expected_amount'] ?? 0) . ",
                online_cheque_payment = " . (float)($summary['online_cheque_payment'] ?? 0) . ",
                closing_cash = " . (float)($summary['closing_cash'] ?? 0) . ",
                cash_denomination = " . (float)($summary['cash_denomination'] ?? 0) . ",
                difference = " . (float)($summary['difference'] ?? 0) . ",
                report_data = '" . mysqli_real_escape_string($conn, json_encode($data)) . "',
                updated_at = NOW()
            WHERE report_date = '$date'
        ";
    } else {
        // Insert new report
        $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : NULL;
        $sql = "
            INSERT INTO tbl_day_reports (
                report_date, opening_amount, expected_amount, online_cheque_payment,
                closing_cash, cash_denomination, difference, report_data, created_by, created_at
            ) VALUES (
                '$date',
                " . (float)($summary['opening_amount'] ?? 0) . ",
                " . (float)($summary['expected_amount'] ?? 0) . ",
                " . (float)($summary['online_cheque_payment'] ?? 0) . ",
                " . (float)($summary['closing_cash'] ?? 0) . ",
                " . (float)($summary['cash_denomination'] ?? 0) . ",
                " . (float)($summary['difference'] ?? 0) . ",
                '" . mysqli_real_escape_string($conn, json_encode($data)) . "',
                " . ($user_id ? $user_id : "NULL") . ",
                NOW()
            )
        ";
    }
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Day report saved successfully'
        ]);
    } else {
        throw new Exception("Failed to save day report: " . mysqli_error($conn));
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
