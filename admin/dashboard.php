<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require 'db.php';
require 'functions.php';
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../"); 
    exit; 
}

// Obtener información del usuario
$user_stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch();

// Obtener aplicaciones asignadas al usuario
$stmt = $pdo->prepare("
  SELECT a.* FROM apps a
  JOIN user_apps ua ON ua.app_id = a.id
  WHERE ua.user_id = ? AND a.is_active = 1
  ORDER BY a.name
");
$stmt->execute([$_SESSION['user_id']]);
$apps = $stmt->fetchAll();

// Registrar vista del dashboard
addLog($pdo, $_SESSION['user_id'], "dashboard_view");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portal de Aplicaciones</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      min-height: 100vh;
    }
    
    .app-card {
      transition: all 0.3s ease;
      border-radius: 16px;
      overflow: hidden;
      position: relative;
    }
    
    .app-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    
    .app-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #4361ee, #3a0ca3);
    }
    
    .notification-dot {
      position: absolute;
      top: -5px;
      right: -5px;
      width: 12px;
      height: 12px;
      background-color: #f72585;
      border-radius: 50%;
    }
    
    .skeleton {
      animation: skeleton-loading 1s linear infinite alternate;
    }
    
    @keyframes skeleton-loading {
      0% {
        background-color: hsl(200, 20%, 80%);
      }
      100% {
        background-color: hsl(200, 20%, 95%);
      }
    }
    
    .app-modal {
      transition: opacity 0.3s ease, transform 0.3s ease;
      transform: scale(0.9);
      opacity: 0;
    }
    
    .app-modal.active {
      transform: scale(1);
      opacity: 1;
    }
  </style>
</head>
<body class="min-h-screen">
  <!-- Header -->
  <header class="bg-white shadow-sm py-4 px-6 flex justify-between items-center sticky top-0 z-10">
    <div class="flex items-center">
      <div class="h-10 w-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold mr-3">
        <?= strtoupper(substr($user['name'], 0, 1)) ?>
      </div>
      <div>
        <h1 class="text-xl font-bold text-gray-800">Portal de Aplicaciones</h1>
        <p class="text-sm text-gray-500">Bienvenido, <?= htmlspecialchars($user['name']) ?></p>
      </div>
    </div>
    
    <div class="flex items-center space-x-4">
      <div class="relative">
        <button class="text-gray-500 hover:text-blue-600 focus:outline-none" onclick="toggleNotifications()">
          <i class="fas fa-bell text-xl"></i>
          <span class="notification-dot"></span>
        </button>
        <div id="notifications-panel" class="absolute hidden right-0 mt-2 w-80 bg-white rounded-md shadow-lg py-1 z-20">
          <div class="px-4 py-2 border-b">
            <h3 class="text-sm font-medium text-gray-900">Notificaciones</h3>
          </div>
          <div class="max-h-60 overflow-y-auto">
            <div class="px-4 py-3 hover:bg-gray-50">
              <p class="text-sm text-gray-800">Bienvenido al nuevo portal de aplicaciones</p>
              <p class="text-xs text-gray-500">Hace unos momentos</p>
            </div>
          </div>
        </div>
      </div>
      
      <div class="relative">
        <button class="flex items-center focus:outline-none" onclick="toggleUserMenu()">
          <div class="h-10 w-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold">
            <?= strtoupper(substr($user['name'], 0, 1)) ?>
          </div>
        </button>
        <div id="user-menu" class="absolute hidden right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-20">
          <div class="px-4 py-2 border-b">
            <p class="text-sm text-gray-900"><?= htmlspecialchars($user['name']) ?></p>
            <p class="text-xs text-gray-500"><?= htmlspecialchars($user['email']) ?></p>
          </div>
          <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
            <i class="fas fa-user mr-2"></i> Mi Perfil
          </a>
          <?php if (isAdmin()): ?>
          <a href="admin.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
            <i class="fas fa-cog mr-2"></i> Panel Admin
          </a>
          <?php endif; ?>
          <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
            <i class="fas fa-sign-out-alt mr-2"></i> Cerrar sesión
          </a>
        </div>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="container mx-auto px-4 py-8">
    <!-- Welcome Section -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-700 rounded-2xl p-6 text-white shadow-lg mb-8">
      <div class="flex flex-col md:flex-row items-center justify-between">
        <div>
          <h2 class="text-2xl font-bold mb-2">¡Hola, <?= htmlspecialchars($user['name']) ?>!</h2>
          <p class="opacity-90">Tienes <?= count($apps) ?> aplicación<?= count($apps) !== 1 ? 'es' : '' ?> disponible<?= count($apps) !== 1 ? 's' : '' ?></p>
        </div>
        <div class="mt-4 md:mt-0">
          <button class="bg-white text-blue-700 px-4 py-2 rounded-lg font-medium hover:bg-blue-50 transition" onclick="showHelp()">
            <i class="fas fa-question-circle mr-2"></i> ¿Necesitas ayuda?
          </button>
        </div>
      </div>
    </div>

    <!-- Apps Grid -->
    <div class="mb-6 flex justify-between items-center">
      <h3 class="text-lg font-semibold text-gray-800">Mis Aplicaciones</h3>
      <div class="text-sm text-gray-500">
        <?= count($apps) ?> aplicación<?= count($apps) !== 1 ? 'es' : '' ?>
      </div>
    </div>

<?php if (count($apps) > 0): ?>
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
<?php foreach ($apps as $app): ?>
  <a href="http://38.65.143.27/<?= htmlspecialchars($app['url']) ?>?email=<?= urlencode($user['email']) ?>" 
     target="_blank" 
     class="app-card bg-white shadow rounded-lg overflow-hidden block">
    <div class="p-5">
        <div class="flex items-center justify-center h-16 w-16 rounded-xl bg-blue-100 text-blue-600 text-2xl mx-auto mb-4">
            <?php if ($app['icon']): ?>
                <img src="<?= htmlspecialchars($app['icon']) ?>" class="w-10 h-10" alt="<?= htmlspecialchars($app['name']) ?>">
            <?php else: ?>
                <i class="fas fa-cube"></i>
            <?php endif; ?>
        </div>
        <h4 class="font-semibold text-center text-gray-800 mb-1"><?= htmlspecialchars($app['name']) ?></h4>
        <p class="text-xs text-gray-500 text-center mb-4"><?= htmlspecialchars($app['url']) ?></p>
        <div class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg font-medium text-center transition">
            Abrir aplicación
        </div>
    </div>
  </a>
<?php endforeach; ?>

</div>
<?php else: ?>
<div class="bg-white rounded-2xl shadow-sm p-8 text-center">
    <h3 class="text-lg font-medium text-gray-900 mb-2">No tienes aplicaciones asignadas</h3>
    <p class="text-gray-500">Contacta con el administrador para que te asigne aplicaciones.</p>
</div>
<?php endif; ?>

  </main>

  <!-- App Modal -->
  <div id="appModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 hidden">
    <div class="app-modal bg-white rounded-2xl shadow-xl w-full max-w-md">
      <div class="p-6 border-b">
        <h3 class="text-xl font-semibold text-gray-800" id="modalAppName">Aplicación</h3>
      </div>
      <div class="p-6">
        <div class="flex justify-center mb-6">
          <div class="h-20 w-20 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600 text-4xl">
            <i class="fas fa-cube" id="modalAppIcon"></i>
          </div>
        </div>
        <p class="text-gray-600 text-center mb-2">Estás a punto de abrir la aplicación:</p>
        <p class="font-medium text-center text-gray-800 mb-2" id="modalAppTitle">Nombre de la app</p>
        <p class="text-sm text-gray-500 text-center mb-6" id="modalAppUrl">URL de la app</p>
        <div class="flex space-x-3">
          <button onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-800 py-3 rounded-lg font-medium hover:bg-gray-300 transition">
            Cancelar
          </button>
          <a id="modalAppLink" href="#" target="_blank" class="flex-1 bg-blue-600 text-white py-3 rounded-lg font-medium hover:bg-blue-700 transition text-center">
            <i class="fas fa-external-link-alt mr-2"></i> Abrir
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Help Modal -->
  <div id="helpModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
      <div class="p-6 border-b flex justify-between items-center">
        <h3 class="text-xl font-semibold text-gray-800">Centro de Ayuda</h3>
        <button onclick="closeHelp()" class="text-gray-400 hover:text-gray-600">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="p-6">
        <div class="mb-6">
          <h4 class="font-medium text-gray-800 mb-2">¿Cómo usar las aplicaciones?</h4>
          <p class="text-gray-600 text-sm">Haz clic en cualquier aplicación para abrirla en una nueva ventana. Necesitarás iniciar sesión en cada aplicación por separado.</p>
        </div>
        <div class="mb-6">
          <h4 class="font-medium text-gray-800 mb-2">Problemas comunes</h4>
          <ul class="text-gray-600 text-sm list-disc pl-5 space-y-1">
            <li>Si una aplicación no carga, verifica tu conexión a internet</li>
            <li>Si no puedes acceder, contacta con el administrador</li>
            <li>Cierra sesión correctamente para proteger tu cuenta</li>
          </ul>
        </div>
        <div class="bg-blue-50 rounded-lg p-4">
          <p class="text-blue-800 text-sm">¿Necesitas más ayuda? Contacta al soporte técnico: <span class="font-medium">desarrollo@kabzo.org</span></p>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Toggle user menu
    function toggleUserMenu() {
      document.getElementById('user-menu').classList.toggle('hidden');
    }
    
    // Toggle notifications panel
    function toggleNotifications() {
      document.getElementById('notifications-panel').classList.toggle('hidden');
    }
    
    // Open app modal
    function openApp(appId, appName, appUrl) {
      document.getElementById('modalAppName').textContent = 'Abrir aplicación';
      document.getElementById('modalAppTitle').textContent = appName;
      document.getElementById('modalAppUrl').textContent = appUrl;
      
      const link = document.getElementById('modalAppLink');
      link.href = '../' + appUrl + '/';
      
      const modal = document.getElementById('appModal');
      modal.classList.remove('hidden');
      setTimeout(() => {
        modal.querySelector('.app-modal').classList.add('active');
      }, 10);
    }
    
    // Close modal
    function closeModal() {
      const modal = document.getElementById('appModal');
      modal.querySelector('.app-modal').classList.remove('active');
      setTimeout(() => {
        modal.classList.add('hidden');
      }, 300);
    }
    
    // Show help modal
    function showHelp() {
      document.getElementById('helpModal').classList.remove('hidden');
    }
    
    // Close help modal
    function closeHelp() {
      document.getElementById('helpModal').classList.add('hidden');
    }
    
    // Close menus when clicking outside
    window.addEventListener('click', function(e) {
      if (!e.target.closest('#user-menu') && !e.target.closest('.relative > button')) {
        document.getElementById('user-menu').classList.add('hidden');
      }
      if (!e.target.closest('#notifications-panel') && !e.target.closest('.relative > button')) {
        document.getElementById('notifications-panel').classList.add('hidden');
      }
    });
  </script>
</body>
</html>