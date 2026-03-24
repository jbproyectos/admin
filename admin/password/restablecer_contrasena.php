<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Conexión a la base de datos
include('../db.php');

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    try {
        // Verificar si el token existe en la base de datos
        //$query = "SELECT * FROM users WHERE reset_token = :token";
        $query = "SELECT * FROM users WHERE token = :token";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':token', $token, PDO::PARAM_STR);
        $stmt->execute();

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        :root {
            --primary-color: #3b82f6;
            --primary-hover: #2563eb;
            --accent-color: #f59e0b;
            --accent-hover: #d97706;
            --success-color: #10b981;
            --success-hover: #059669;
            --error-color: #ef4444;
            --error-hover: #dc2626;
        }
        
        .reset-container {
            background: linear-gradient(135deg, #fef3c7 0%, #fef7cd 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .reset-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
            position: relative;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .reset-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        
        .reset-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        
        .reset-icon svg {
            width: 40px;
            height: 40px;
            color: white;
        }
        
        .form-title {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-title h2 {
            font-size: 1.75rem;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .form-title p {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            background-color: #f9fafb;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background-color: white;
        }
        
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .password-strength.weak {
            background-color: var(--error-color);
            width: 33%;
        }
        
        .password-strength.medium {
            background-color: var(--warning-color);
            width: 66%;
        }
        
        .password-strength.strong {
            background-color: var(--success-color);
            width: 100%;
        }
        
        .password-match {
            font-size: 0.75rem;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
        }
        
        .password-match.valid {
            color: var(--success-color);
        }
        
        .password-match.invalid {
            color: var(--error-color);
        }
        
        .btn-reset {
            width: 100%;
            padding: 0.75rem 1rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4);
        }
        
        .btn-reset:active {
            transform: translateY(0);
        }
        
        .btn-loading {
            pointer-events: none;
            opacity: 0.7;
        }
        
        .btn-loading::after {
            content: '';
            width: 16px;
            height: 16px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .success-screen {
            text-align: center;
            padding: 2rem 0;
            display: none;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        
        .success-icon svg {
            width: 40px;
            height: 40px;
            color: white;
        }
        
        .success-screen h2 {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--success-color);
            margin-bottom: 0.5rem;
        }
        
        .success-screen p {
            color: #6b7280;
            margin-bottom: 2rem;
            line-height: 1.5;
        }
        
        .btn-back-login {
            width: 100%;
            padding: 0.75rem 1rem;
            background: linear-gradient(135deg, var(--accent-color) 0%, var(--accent-hover) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn-back-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 158, 11, 0.4);
        }
        
        .password-requirements {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.5rem;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 0.25rem;
        }
        
        .requirement.valid {
            color: var(--success-color);
        }
        
        .requirement.invalid {
            color: var(--error-color);
        }
        
        @media (max-width: 480px) {
            .reset-card {
                padding: 2rem 1.5rem;
            }
            
            .reset-icon {
                width: 60px;
                height: 60px;
            }
            
            .reset-icon svg {
                width: 30px;
                height: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <!-- Icono de restablecimiento -->
            <div class="reset-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0-6a2 2 0 012 2v4a2 2 0 01-2 2m0-10a2 2 0 00-2 2v4a2 2 0 002 2m0-6v6" />
                </svg>
            </div>
            
            <!-- Formulario de restablecimiento -->
            <div id="resetForm">
                <div class="form-title">
                    <h2>Restablecer Contraseña</h2>
                    <p>Crea una nueva contraseña segura para tu cuenta</p>
                </div>
                
                <form id="resetPasswordForm" action="actualizar_contrasena.php" method="post">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Nueva Contraseña</label>
                        <input 
                            type="password" 
                            name="password" 
                            id="password" 
                            required 
                            placeholder="Introduce tu nueva contraseña" 
                            class="form-input"
                            minlength="8"
                        >
                        <div id="password-strength" class="password-strength"></div>
                        <div class="password-requirements">
                            <div class="requirement" id="req-length">• Mínimo 8 caracteres</div>
                            <div class="requirement" id="req-case">• Mayúsculas y minúsculas</div>
                            <div class="requirement" id="req-number">• Al menos un número</div>
                            <div class="requirement" id="req-special">• Carácter especial (opcional)</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirmar Contraseña</label>
                        <input 
                            type="password" 
                            name="confirm_password" 
                            id="confirm_password" 
                            required 
                            placeholder="Confirma tu nueva contraseña" 
                            class="form-input"
                        >
                        <div id="password-match" class="password-match"></div>
                    </div>
                    
                    <button type="submit" class="btn-reset" id="resetBtn">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Actualizar Contraseña
                    </button>
                </form>
            </div>
            
            <!-- Pantalla de éxito después del restablecimiento -->
            <div class="success-screen" id="successScreen">
                <div class="success-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2>¡Contraseña Actualizada!</h2>
                <p>Tu contraseña ha sido restablecida correctamente. Ahora puedes iniciar sesión con tu nueva contraseña.</p>
                <button class="btn-back-login" onclick="goToLogin()">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                    </svg>
                    Ir al Inicio de Sesión
                </button>
            </div>
        </div>
    </div>

    <script>
        // Configuración de colores para SweetAlert
        const SwalTheme = {
            success: {
                color: '#10b981',
                background: '#f0fdf4',
                confirmButton: '#10b981'
            },
            error: {
                color: '#ef4444',
                background: '#fef2f2',
                confirmButton: '#ef4444'
            },
            warning: {
                color: '#f59e0b',
                background: '#fffbeb',
                confirmButton: '#f59e0b'
            },
            info: {
                color: '#3b82f6',
                background: '#eff6ff',
                confirmButton: '#3b82f6'
            }
        };
        
        // Función para ir al login
        function goToLogin() {
            window.location.href = '/index.php'; // Ajusta la ruta según tu estructura
        }
        
        // Validación de contraseña en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('confirm_password');
            const strengthBar = document.getElementById('password-strength');
            const matchIndicator = document.getElementById('password-match');
            const resetBtn = document.getElementById('resetBtn');
            
            // Validación de fortaleza de contraseña
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                
                // Remover clases anteriores
                strengthBar.className = 'password-strength';
                
                if (password.length === 0) {
                    strengthBar.style.width = '0';
                    updateRequirements([false, false, false, false]);
                    return;
                }
                
                // Calcular fortaleza
                const hasMinLength = password.length >= 8;
                const hasUpperCase = /[A-Z]/.test(password);
                const hasLowerCase = /[a-z]/.test(password);
                const hasNumber = /\d/.test(password);
                const hasSpecialChar = /[^A-Za-z0-9]/.test(password);
                
                const hasCase = hasUpperCase && hasLowerCase;
                
                // Actualizar indicadores de requisitos
                updateRequirements([hasMinLength, hasCase, hasNumber, hasSpecialChar]);
                
                // Calcular puntuación de fortaleza
                let strength = 0;
                if (hasMinLength) strength++;
                if (hasCase) strength++;
                if (hasNumber) strength++;
                if (hasSpecialChar) strength++;
                
                // Aplicar clases según la fortaleza
                if (strength <= 1) {
                    strengthBar.classList.add('weak');
                } else if (strength <= 3) {
                    strengthBar.classList.add('medium');
                } else {
                    strengthBar.classList.add('strong');
                }
            });
            
            // Validación de coincidencia de contraseñas
            confirmInput.addEventListener('input', function() {
                const password = passwordInput.value;
                const confirmPassword = this.value;
                
                if (confirmPassword.length === 0) {
                    matchIndicator.textContent = '';
                    matchIndicator.className = 'password-match';
                    return;
                }
                
                if (password === confirmPassword) {
                    matchIndicator.textContent = '✓ Las contraseñas coinciden';
                    matchIndicator.className = 'password-match valid';
                } else {
                    matchIndicator.textContent = '✗ Las contraseñas no coinciden';
                    matchIndicator.className = 'password-match invalid';
                }
            });
            
            // Función para actualizar los indicadores de requisitos
            function updateRequirements(requirements) {
                const reqIds = ['req-length', 'req-case', 'req-number', 'req-special'];
                
                reqIds.forEach((id, index) => {
                    const element = document.getElementById(id);
                    if (requirements[index]) {
                        element.classList.add('valid');
                        element.classList.remove('invalid');
                    } else {
                        element.classList.add('invalid');
                        element.classList.remove('valid');
                    }
                });
            }
            
            // Manejo del envío del formulario
            document.getElementById('resetPasswordForm').addEventListener('submit', function(event) {
                event.preventDefault();
                
                const password = passwordInput.value;
                const confirmPassword = confirmInput.value;
                
                // Validar que las contraseñas coincidan
                if (password !== confirmPassword) {
                    Swal.fire({
                        title: 'Contraseñas no coinciden',
                        text: 'Por favor, asegúrate de que ambas contraseñas sean iguales.',
                        icon: 'warning',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: SwalTheme.warning.confirmButton,
                        background: SwalTheme.warning.background,
                        iconColor: SwalTheme.warning.color
                    });
                    return;
                }
                
                // Validar fortaleza de la contraseña
                if (password.length < 8) {
                    Swal.fire({
                        title: 'Contraseña muy corta',
                        text: 'La contraseña debe tener al menos 8 caracteres.',
                        icon: 'warning',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: SwalTheme.warning.confirmButton,
                        background: SwalTheme.warning.background,
                        iconColor: SwalTheme.warning.color
                    });
                    return;
                }
                
                // Mostrar estado de carga
                resetBtn.classList.add('btn-loading');
                resetBtn.disabled = true;
                
                // Mostrar SweetAlert de carga
                Swal.fire({
                    title: 'Actualizando contraseña...',
                    text: 'Estamos procesando tu solicitud.',
                    didOpen: () => {
                        Swal.showLoading();
                    },
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false
                });
                
                // Enviar el formulario con fetch
                const formData = new FormData(this);
                fetch(this.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Cerrar SweetAlert de carga
                    Swal.close();
                    
                    if (data.success) {
                        // Mostrar pantalla de éxito
                        document.getElementById('resetForm').style.display = 'none';
                        document.getElementById('successScreen').style.display = 'block';
                        
                        // También mostrar SweetAlert de confirmación
                        Swal.fire({
                            title: '¡Éxito!',
                            text: data.message || 'Tu contraseña se ha actualizado correctamente.',
                            icon: 'success',
                            confirmButtonText: 'Entendido',
                            confirmButtonColor: SwalTheme.success.confirmButton,
                            background: SwalTheme.success.background,
                            iconColor: SwalTheme.success.color
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'Ocurrió un problema al actualizar tu contraseña.',
                            icon: 'error',
                            confirmButtonText: 'Entendido',
                            confirmButtonColor: SwalTheme.error.confirmButton,
                            background: SwalTheme.error.background,
                            iconColor: SwalTheme.error.color
                        });
                    }
                })
                .catch(error => {
                    // Cerrar SweetAlert de carga
                    Swal.close();
                    
                    Swal.fire({
                        title: 'Error de Conexión',
                        text: 'Ocurrió un error inesperado. Por favor, intenta nuevamente.',
                        icon: 'error',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: SwalTheme.error.confirmButton,
                        background: SwalTheme.error.background,
                        iconColor: SwalTheme.error.color
                    });
                    console.error(error);
                })
                .finally(() => {
                    // Restaurar estado del botón
                    resetBtn.classList.remove('btn-loading');
                    resetBtn.disabled = false;
                });
            });
        });
    </script>
</body>
</html>
<?php
        } else {
            echo "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            <script>
                Swal.fire({
                    title: 'Enlace Inválido',
                    text: 'El enlace de restablecimiento no es válido o ha expirado.',
                    icon: 'error',
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#ef4444',
                    background: '#fef2f2',
                    iconColor: '#ef4444'
                }).then(() => {
                    window.location.href = '/index.php';
                });
            </script>";
        }
    } catch (PDOException $e) {
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
            Swal.fire({
                title: 'Error del Sistema',
                text: 'Error al verificar el token: " . addslashes($e->getMessage()) . "',
                icon: 'error',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#ef4444',
                background: '#fef2f2',
                iconColor: '#ef4444'
            });
        </script>";
    }
} else {
    echo "
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
        Swal.fire({
            title: 'Token no proporcionado',
            text: 'No se proporcionó un token válido.',
            icon: 'error',
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#ef4444',
            background: '#fef2f2',
            iconColor: '#ef4444'
        });
    </script>";
}
?>