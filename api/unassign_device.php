<?php
require_once '../db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$deviceId = $data['device_id'] ?? null;

if (!$deviceId) {
    echo json_encode(['error' => 'Нет ID устройства']);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE devices 
    SET user_id = NULL, status = 'warehouse'
    WHERE id = ? AND status = 'issued'
");

$stmt->execute([(int)$deviceId]);

echo json_encode(['success' => true]);
?>