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
    header("Location: admin.php?error=" . urlencode("ID de aplicación no válido."));
    exit();
}

$app_id = intval($_GET['id']);

try {
    // Obtener información de la aplicación antes de eliminarla para el registro
    $stmt = $pdo->prepare("SELECT name, url FROM apps WHERE id = ?");
    $stmt->execute([$app_id]);
    $app = $stmt->fetch();
    
    if (!$app) {
        header("Location: admin.php?error=" . urlencode("La aplicación no existe."));
        exit();
    }
    
    $app_name = $app['name'];
    $app_url = $app['url'];
    
    // Iniciar transacción para asegurar la integridad de los datos
    $pdo->beginTransaction();
    
    // 1. Eliminar todas las asignaciones de esta app a usuarios
    $stmt = $pdo->prepare("DELETE FROM user_apps WHERE app_id = ?");
    $stmt->execute([$app_id]);
    
    // 2. Eliminar la aplicación
    $stmt = $pdo->prepare("DELETE FROM apps WHERE id = ?");
    $stmt->execute([$app_id]);
    
    // Confirmar la transacción
    $pdo->commit();
    
    // Registrar la acción en el log
    if (function_exists('addLog')) {
        addLog($pdo, $_SESSION['user_id'], 'app_delete', 
            "Aplicación eliminada: $app_name (URL: $app_url, ID: $app_id)");
    }
    
    header("Location: admin.php?success=" . urlencode("Aplicación eliminada correctamente."));
    exit();
    
} catch (Exception $e) {
    // Revertir la transacción en caso de error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Registrar error en el log
    if (function_exists('addLog')) {
        addLog($pdo, $_SESSION['user_id'], 'error', 
            "Error al eliminar aplicación ID $app_id: " . $e->getMessage());
    }
    
    header("Location: admin.php?error=" . urlencode("Error al eliminar la aplicación: " . $e->getMessage()));
    exit();
}