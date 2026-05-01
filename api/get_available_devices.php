<?php
require_once '../db.php';
header('Content-Type: application/json');

$stmt = $pdo->query("
    SELECT id, inventory_code, device_type, description, status
    FROM devices 
    WHERE status = 'warehouse'
");

echo json_encode($stmt->fetchAll());
?>