<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/jewelry_catalogue_create_include.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = [];
if ($raw !== false && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}
if ($data === [] && !empty($_POST)) {
    $data = $_POST;
}

$result = auragold_jewelry_catalogue_save($conn, $data);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
