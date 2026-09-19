<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/account_ledger_list_data.php';

header('Content-Type: application/json');

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$limit = isset($_GET['limit']) ? min(200, max(10, (int) $_GET['limit'])) : 100;

$data = auragold_account_ledger_fetch_rows($conn, ['search' => $search]);
$rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];

$result = [];
foreach ($rows as $row) {
    $name = trim((string) ($row['ledger_name'] ?? $row['name'] ?? ''));
    if ($name === '') {
        continue;
    }
    $group = trim((string) ($row['group_name'] ?? ''));
    $display = $name;
    if ($group !== '') {
        $display = $group . ' — ' . $name;
    }
    $result[] = [
        'name' => $name,
        'type' => $group,
        'display_text' => $display,
    ];
    if (count($result) >= $limit) {
        break;
    }
}

echo json_encode(['status' => 'success', 'categories' => $result]);
