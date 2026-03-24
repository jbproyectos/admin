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
    header("Location: admin.php?error=" . urlencode("ID de usuario no válido."));
    exit();
}

$user_id = intval($_GET['id']);

// Evitar que un usuario se elimine a sí mismo
if ($user_id == $_SESSION['user_id']) {
    header("Location: admin.php?error=" . urlencode("No puedes eliminar tu propia cuenta."));
    exit();
}

try {
    // Obtener información del usuario antes de eliminarlo para el registro
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header("Location: admin.php?error=" . urlencode("El usuario no existe."));
        exit();
    }
    
    $user_email = $user['email'];
    
    // Iniciar transacción para asegurar la integridad de los datos
    $pdo->beginTransaction();
    
    // 1. Eliminar asignaciones de apps del usuario
    $stmt = $pdo->prepare("DELETE FROM user_apps WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // 2. Actualizar registros de actividad para mantener integridad referencial
    // Opción: mantener los logs pero marcar user_id como NULL
    $stmt = $pdo->prepare("UPDATE activity_log SET user_id = NULL WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // 3. Eliminar el usuario
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    
    // Confirmar la transacción
    $pdo->commit();
    
    // Registrar la acción en el log
    if (function_exists('addLog')) {
        addLog($pdo, $_SESSION['user_id'], 'user_delete', "Usuario eliminado: $user_email (ID: $user_id)");
    }
    
    header("Location: admin.php?success=" . urlencode("Usuario eliminado correctamente."));
    exit();
    
} catch (Exception $e) {
    // Revertir la transacción en caso de error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Registrar error en el log
    if (function_exists('addLog')) {
        addLog($pdo, $_SESSION['user_id'], 'error', "Error al eliminar usuario ID $user_id: " . $e->getMessage());
    }
    
    header("Location: admin.php?error=" . urlencode("Error al eliminar el usuario: " . $e->getMessage()));
    exit();
}