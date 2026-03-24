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
        $app_id = isset($_POST['app_id']) ? intval($_POST['app_id']) : 0;
        $name = trim($_POST['name']);
        $url = trim($_POST['url']);
        $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 0;
        
        // Validaciones básicas
        if (empty($name) || empty($url)) {
            throw new Exception("Todos los campos obligatorios deben ser completados.");
        }
        
        // Validar formato de URL (solo caracteres permitidos para rutas)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $url)) {
            throw new Exception("La URL solo puede contener letras, números, guiones y guiones bajos.");
        }
        
        // Verificar si la URL ya existe (excepto para la app actual)
        $stmt = $pdo->prepare("SELECT id FROM apps WHERE url = ? AND id != ?");
        $stmt->execute([$url, $app_id]);
        if ($stmt->fetch()) {
            throw new Exception("La URL ya está siendo utilizada por otra aplicación.");
        }
        
        if ($app_id > 0) {
            // Modo edición
            $stmt = $pdo->prepare("UPDATE apps SET name = ?, url = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $url, $is_active, $app_id]);
            
            // Registrar en log
            if (function_exists('addLog')) {
                addLog($pdo, $_SESSION['user_id'], 'app_update', 
                    "Aplicación actualizada: $name (URL: $url, ID: $app_id)");
            }
            
            $response['success'] = true;
            $response['message'] = "Aplicación actualizada correctamente.";
        } else {
            // Modo creación
            $stmt = $pdo->prepare("INSERT INTO apps (name, url, is_active) VALUES (?, ?, ?)");
            $stmt->execute([$name, $url, $is_active]);
            
            $new_app_id = $pdo->lastInsertId();
            
            // Registrar en log
            if (function_exists('addLog')) {
                addLog($pdo, $_SESSION['user_id'], 'app_create', 
                    "Aplicación creada: $name (URL: $url, ID: $new_app_id)");
            }
            
            $response['success'] = true;
            $response['message'] = "Aplicación creada correctamente.";
        }
    } else {
        throw new Exception("Método de solicitud no válido.");
    }
} catch (Exception $e) {
    // Registrar error en log
    if (function_exists('addLog')) {
        addLog($pdo, $_SESSION['user_id'] ?? null, 'error', 
            "Error en admin_save_app: " . $e->getMessage());
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