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

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validar y sanitizar datos
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $app_id = isset($_POST['app_id']) ? intval($_POST['app_id']) : 0;
        
        // Validaciones básicas
        if (empty($user_id) || empty($app_id)) {
            throw new Exception("Debe seleccionar un usuario y una aplicación.");
        }
        
        // Verificar si el usuario existe
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception("El usuario seleccionado no existe.");
        }
        
        // Verificar si la aplicación existe
        $stmt = $pdo->prepare("SELECT id, name, url FROM apps WHERE id = ? AND is_active = 1");
        $stmt->execute([$app_id]);
        $app = $stmt->fetch();
        
        if (!$app) {
            throw new Exception("La aplicación seleccionada no existe o está inactiva.");
        }
        
        // Verificar si la asignación ya existe
        $stmt = $pdo->prepare("SELECT id FROM user_apps WHERE user_id = ? AND app_id = ?");
        $stmt->execute([$user_id, $app_id]);
        
        if ($stmt->fetch()) {
            throw new Exception("Esta aplicación ya está asignada al usuario.");
        }
        
        // Asignar la aplicación al usuario
        $stmt = $pdo->prepare("INSERT INTO user_apps (user_id, app_id, assigned_at) VALUES (?, ?, NOW())");
        $stmt->execute([$user_id, $app_id]);
        
        // Registrar la acción en el log
        if (function_exists('addLog')) {
            addLog($pdo, $_SESSION['user_id'], 'app_assignment', 
                "Aplicación '{$app['name']}' (ID: $app_id) asignada al usuario '{$user['email']}' (ID: $user_id)");
        }
        
        $response['success'] = true;
        $response['message'] = "Aplicación asignada correctamente al usuario.";
    } else {
        throw new Exception("Método de solicitud no válido.");
    }
} catch (Exception $e) {
    // Registrar error en el log
    if (function_exists('addLog')) {
        addLog($pdo, $_SESSION['user_id'] ?? null, 'error', 
            "Error en admin_assign_app: " . $e->getMessage());
    }
    
    $response['message'] = $e->getMessage();
}

// Redireccionar con mensaje
if ($response['success']) {
    header("Location: admin.php?success=" . urlencode($response['message']));
} else {
    header("Location: admin.php?error=" . urlencode($response['message']));
}
exit();