<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurar cabecera para devolver JSON
header('Content-Type: application/json');

// Conexión a la base de datos
include('../db.php'); // Asegúrate de que `$conexion` sea una instancia de PDO

$response = []; // Array para almacenar la respuesta

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password === $confirm_password) {
        try {
            // Hashear la nueva contraseña
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Actualizar la contraseña en la base de datos
            //$query = "UPDATE users SET password_hash = :password, reset_token = NULL WHERE reset_token = :token";
            $query = "UPDATE users SET password_hash = :password, token = NULL WHERE token = :token";
            /*$query = "UPDATE usuarios SET contrasena = :password, token = NULL WHERE token = :token";*/
            $stmt = $pdo->prepare($query);  
            $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
            $stmt->bindParam(':token', $token, PDO::PARAM_STR);

            if ($stmt->execute() && $stmt->rowCount() > 0) {
                // Respuesta exitosa
                $response = [
                    'success' => true,
                    'message' => 'Tu contraseña ha sido actualizada con éxito.'
                ];
            } else {
                // Si no se actualizó ninguna fila, el token es inválido o ya fue usado
                $response = [
                    'success' => false,
                    'message' => 'El enlace de restablecimiento no es válido o ya fue usado.'
                ];
            }
        } catch (PDOException $e) {
            $response = [
                'success' => false,
                'message' => 'Error al actualizar la contraseña: ' . $e->getMessage()
            ];
        }
    } else {
        // Las contraseñas no coinciden
        $response = [
            'success' => false,
            'message' => 'Las contraseñas no coinciden.'
        ];
    }
} else {
    // Método HTTP inválido
    $response = [
        'success' => false,
        'message' => 'Método HTTP no permitido.'
    ];
}

// Enviar la respuesta como JSON
echo json_encode($response);
?>