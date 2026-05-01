<?php
require_once '../db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$userId = $data['user_id'] ?? null;
$deviceIds = $data['device_ids'] ?? [];

if ($userId === null || !is_array($deviceIds) || count($deviceIds) === 0) {
    echo json_encode(['error' => 'Неверные данные']);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE devices 
    SET user_id = ?, status = 'issued' 
    WHERE id = ? AND status = 'warehouse'
");

foreach ($deviceIds as $devId) {
    $stmt->execute([(int)$userId, (int)$devId]);
}

echo json_encode(['success' => true]);
?>