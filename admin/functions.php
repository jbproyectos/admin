<?php
function addLog($pdo, $userId, $action, $details = null) {
    $stmt = $pdo->prepare("INSERT INTO activity_log 
        (user_id, action, details, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        $action,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
