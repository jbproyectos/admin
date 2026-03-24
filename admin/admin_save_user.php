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
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $role = $_POST['role'];
        $status = $_POST['status'];
        
        // Validaciones básicas
        if (empty($name) || empty($email) || empty($role) || empty($status)) {
            throw new Exception("Todos los campos obligatorios deben ser completados.");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El formato del email no es válido.");
        }
        
        // Verificar si el email ya existe (excepto para el usuario actual)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            throw new Exception("El email ya está registrado por otro usuario.");
        }
        
        if ($user_id > 0) {
            // Modo edición
            if (!empty($password)) {
                // Actualizar con nueva contraseña
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $email, $hashed_password, $role, $status, $user_id]);
            } else {
                // Actualizar sin cambiar contraseña
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $email, $role, $status, $user_id]);
            }
            
            // Registrar en log usando tu función existente
            if (function_exists('addLog')) {
                addLog($pdo, $_SESSION['user_id'], 'user_update', "Usuario actualizado: $email (ID: $user_id)");
            }
            
            $response['success'] = true;
            $response['message'] = "Usuario actualizado correctamente.";
        } else {
            // Modo creación - la contraseña es obligatoria
            if (empty($password)) {
                throw new Exception("La contraseña es obligatoria para nuevos usuarios.");
            }
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hashed_password, $role, $status]);
            
            $new_user_id = $pdo->lastInsertId();
            
            // Registrar en log usando tu función existente
            if (function_exists('addLog')) {
                addLog($pdo, $_SESSION['user_id'], 'user_create', "Usuario creado: $email (ID: $new_user_id)");
            }
            
            $response['success'] = true;
            $response['message'] = "Usuario creado correctamente.";
        }
    } else {
        throw new Exception("Método de solicitud no válido.");
    }
} catch (Exception $e) {
    // Registrar error en log
    if (function_exists('addLog')) {
        addLog($pdo, $_SESSION['user_id'] ?? null, 'error', "Error en admin_save_user: " . $e->getMessage());
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