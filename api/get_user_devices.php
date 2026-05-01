<?php
require_once '../db.php';
header('Content-Type: application/json');

$userId = $_GET['user_id'] ?? null;
if (!$userId) { echo json_encode([]); exit; }

$stmt = $pdo->prepare("SELECT id, inventory_code, device_type FROM devices WHERE user_id = ? AND status = 'issued'");
$stmt->execute([$userId]);
echo json_encode($stmt->fetchAll());
?>