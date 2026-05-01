<?php
require_once '../db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$userId = $data['user_id'] ?? null;

if (!$userId) {
    echo json_encode(['error' => 'Нет ID пользователя']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        UPDATE devices 
        SET user_id = NULL, status = 'warehouse'
        WHERE user_id = ? AND status = 'issued'
    ");
    $stmt->execute([$userId]);
    
    $stmt = $pdo->prepare("DELETE FROM tickets WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
?>