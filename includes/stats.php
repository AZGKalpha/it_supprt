<?php
function getTicketStats($pdo, $userId = null) {
    $stats = ['new' => 0, 'in_progress' => 0, 'resolved' => 0];
    
    $baseSql = "SELECT COUNT(*) FROM tickets WHERE 1=1";
    if ($userId !== null) {
        $baseSql .= " AND user_id = ?";
    }
    $baseSql .= " AND status = ?";
    
    $stmt = $pdo->prepare($baseSql);
    
    foreach (['new', 'in_progress', 'resolved'] as $status) {
        $params = $userId !== null ? [$userId, $status] : [$status];
        $stmt->execute($params);
        $stats[$status] = $stmt->fetchColumn();
    }
    
    return $stats;
}
?>