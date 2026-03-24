<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require 'db.php';
require 'functions.php';

if (!isAdmin()) {
    header("Location: login.php");
    exit();
}

// Verificar que se haya proporcionado un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin.php?error=" . urlencode("ID de asignación no válido."));
    exit();
}

$assignment_id = intval($_GET['id']);

try {
    // Obtener información de la asignación antes de eliminarla para el registro
    $stmt = $pdo->prepare("
        SELECT ua.id, ua.user_id, u.name as user_name, u.email, 
               a.id as app_id, a.name as app_name, a.url
        FROM user_apps ua
        JOIN users u ON ua.user_id = u.id
        JOIN apps a ON ua.app_id = a.id
        WHERE ua.id = ?
    ");
    $stmt->execute([$assignment_id]);
    $assignment = $stmt->fetch();
    
    if (!$assignment) {
        header("Location: admin.php?error=" . urlencode("La asignación no existe."));
        exit();
    }
    
    // Eliminar la asignación
    $stmt = $pdo->prepare("DELETE FROM user_apps WHERE id = ?");
    $stmt->execute([$assignment_id]);
    
    // Registrar la acción en el log
    if (function_exists('addLog')) {
        addLog($pdo, $_SESSION['user_id'], 'app_unassignment', 
            "Aplicación '{$assignment['app_name']}' (ID: {$assignment['app_id']}) desasignada del usuario '{$assignment['email']}' (ID: {$assignment['user_id']})");
    }
    
    header("Location: admin.php?success=" . urlencode("Aplicación desasignada correctamente."));
    exit();
    
} catch (Exception $e) {
    // Registrar error en el log
    if (function_exists('addLog')) {
        addLog($pdo, $_SESSION['user_id'], 'error', 
            "Error al desasignar aplicación (ID: $assignment_id): " . $e->getMessage());
    }
    
    header("Location: admin.php?error=" . urlencode("Error al desasignar la aplicación: " . $e->getMessage()));
    exit();
}