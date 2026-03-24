<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('../db.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];

    // Verificar si el correo existe en la base de datos
    //$stmt = $conexion->prepare("SELECT * FROM usuarios WHERE email = :email");
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Generar un token único
        $token = bin2hex(random_bytes(50)); // Token aleatorio

        // Guardar el token en la base de datos
        $stmt = $pdo->prepare("UPDATE users SET token = :token WHERE email = :email");
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        // Obtener el esquema (http o https) y el host de la dirección actual
        $scheme = $_SERVER['REQUEST_SCHEME'];  // http o https
        $host = $_SERVER['HTTP_HOST'];          // Dominio o IP del servidor

        // Crear el enlace para restablecer la contraseña
        //$link = "http://38.65.143.27/sistemas/configuracion/password/restablecer_contrasena.php?token=$token";
        $link = "http://38.65.143.27/admin/password/restablecer_contrasena.php?token=$token";

        // Configuración de PHPMailer para enviar el correo
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // o el servidor SMTP que uses
            $mail->SMTPAuth = true;
            $mail->Username = 'ma.proyectos@grupo-cise.com'; // Tu correo
            $mail->Password = 'xyqp tfbj irvu ulcn'; // Tu contraseña de aplicación de Gmail
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->setFrom('ma.proyectos@grupo-cise.com', 'DevKabzoAna');
            $mail->addAddress($email); // Correo del usuario

            $mail->isHTML(true);
            $mail->Subject = 'Restablecimiento de contraseña';

            // Crear el cuerpo del correo con un diseño más atractivo
            $bodyContent = "
            <html>
            <head>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        background-color: #f4f4f4;
                        margin: 0;
                        padding: 0;
                    }
                    .container {
                        max-width: 600px;
                        margin: 0 auto;
                        background-color: #ffffff;
                        padding: 20px;
                        border-radius: 10px;
                        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                    }
                    .header {
                        text-align: center;
                        padding: 20px;
                        background-color: #007bff;
                        color: #ffffff;
                        border-radius: 10px 10px 0 0;
                    }
                    .header h1 {
                        margin: 0;
                    }
                    .content {
                        padding: 20px;
                        color: #333;
                    }
                    .footer {
                        text-align: center;
                        font-size: 12px;
                        color: #777;
                        margin-top: 20px;
                    }
                    a {
                        color: #007bff;
                        text-decoration: none;
                    }
                    .btn {
                        display: inline-block;
                        background-color: #007bff;
                        color: #ffffff;
                        padding: 10px 20px;
                        border-radius: 5px;
                        text-decoration: none;
                        font-weight: bold;
                    }
                    .details {
                        margin-top: 20px;
                        padding: 10px;
                        background-color: #f9f9f9;
                        border-radius: 5px;
                    }
                    .details p {
                        margin: 5px 0;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Restablecimiento de Contraseña</h1>
                        <p>Hola, {$user['name']}.</p>
                    </div>
                    <div class='content'>
                        <p>Hemos recibido una solicitud para restablecer tu contraseña. Si no solicitaste este cambio, por favor ignora este correo.</p>
                        <p>Haz clic en el siguiente enlace para restablecer tu contraseña:</p>
                        <a href='$link' class='btn bg-green-800'>Restablecer Contraseña</a>
                        <div class='details'>
                            <h3>Detalles de tu cuenta:</h3>
                            <p><strong>Correo:</strong> {$user['email']}</p>
                            <p><strong>Fecha de registro:</strong> {$user['fechaRegistro']}</p>
                            <p><strong>Estado:</strong> {$user['estatu']}</p>
                        </div>
                    </div>
                    <div class='footer'>
                        <p>Este es un correo automático, por favor no respondas.</p>
                    </div>
                </div>
            </body>
            </html>";

            // Asignar el contenido HTML
            $mail->Body = $bodyContent;

            // Enviar el correo
            $mail->send();
            echo 'Correo enviado con éxito para restablecer la contraseña.';
        } catch (Exception $e) {
            echo "Error al enviar el correo: " . $mail->ErrorInfo;
        }
    } else {
        echo "El correo no está registrado en nuestra base de datos.";
    }
}