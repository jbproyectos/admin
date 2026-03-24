<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require 'db.php';
require 'functions.php';

if (!isAdmin()) {
    die("Acceso denegado");
}

// Escanear carpetas para apps
$baseDir = __DIR__ . "/../";
$folders = scandir($baseDir);

foreach ($folders as $folder) {
    if ($folder === '.' || $folder === '..' || $folder === 'admin') continue;
    $fullPath = $baseDir . $folder;

    if (is_dir($fullPath)) {
        $indexFile = $fullPath . '/index.php';
        if (file_exists($indexFile)) {
            $stmt = $pdo->prepare("SELECT id FROM apps WHERE url = ?");
            $stmt->execute([$folder]);
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO apps (name, url, is_active) VALUES (?, ?, 1)");
                $stmt->execute([$folder, $folder]);
            }
        }
    }
}

// Obtener datos
$users = $pdo->query("SELECT * FROM users")->fetchAll();
$apps  = $pdo->query("SELECT * FROM apps")->fetchAll();
$logs  = $pdo->query("
    SELECT l.*, u.email 
    FROM activity_log l 
    LEFT JOIN users u ON l.user_id=u.id 
    ORDER BY l.created_at DESC LIMIT 20
")->fetchAll();

// Obtener asignaciones de apps
$user_apps = [];
$stmt = $pdo->query("SELECT ua.*, u.name as user_name, a.name as app_name 
                     FROM user_apps ua 
                     JOIN users u ON ua.user_id = u.id 
                     JOIN apps a ON ua.app_id = a.id");
if ($stmt) {
    $user_apps = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4361ee',
                        secondary: '#3f37c9',
                        success: '#4cc9f0',
                        danger: '#f72585',
                        warning: '#f8961e',
                        info: '#4895ef',
                        dark: '#212529'
                    }
                }
            }
        }
    </script>
    <style>
        .sidebar {
            width: 250px;
            transition: all 0.3s;
        }
        .main-content {
            margin-left: 250px;
            transition: all 0.3s;
        }
        .collapsed .sidebar {
            width: 80px;
        }
        .collapsed .main-content {
            margin-left: 80px;
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
        .active-tab {
            border-bottom: 3px solid #4361ee;
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-gray-100 flex">
    <!-- Sidebar -->
    <div class="sidebar fixed h-full bg-white shadow-lg z-10">
        <div class="p-4 border-b">
            <h1 class="text-xl font-bold text-primary flex items-center">
                <i class="fas fa-cogs mr-2"></i>
                <span class="sidebar-text">AdminPanel</span>
            </h1>
        </div>
        <nav class="mt-4">
            <a href="#" class="flex items-center p-3 text-gray-700 hover:bg-blue-50 hover:text-primary">
                <i class="fas fa-home mx-3"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
            <a href="#users" class="flex items-center p-3 text-gray-700 hover:bg-blue-50 hover:text-primary">
                <i class="fas fa-users mx-3"></i>
                <span class="sidebar-text">Usuarios</span>
            </a>
            <a href="#apps" class="flex items-center p-3 text-gray-700 hover:bg-blue-50 hover:text-primary">
                <i class="fas fa-th mx-3"></i>
                <span class="sidebar-text">Aplicaciones</span>
            </a>
            <a href="#logs" class="flex items-center p-3 text-gray-700 hover:bg-blue-50 hover:text-primary">
                <i class="fas fa-clipboard-list mx-3"></i>
                <span class="sidebar-text">Registros</span>
            </a>
            <a href="#assignments" class="flex items-center p-3 text-gray-700 hover:bg-blue-50 hover:text-primary">
                <i class="fas fa-link mx-3"></i>
                <span class="sidebar-text">Asignaciones</span>
            </a>
        </nav>
        <div class="absolute bottom-0 w-full p-4 border-t">
            <button onclick="toggleSidebar()" class="w-full flex items-center justify-center text-gray-500 hover:text-primary">
                <i class="fas fa-chevron-left" id="sidebar-icon"></i>
                <span class="sidebar-text ml-2">Contraer</span>
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content w-full min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm py-4 px-6 flex justify-between items-center">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Panel de Administración</h2>
                <p class="text-sm text-gray-500">Bienvenido, <?= $_SESSION['user_name'] ?? 'Administrador' ?></p>
            </div>
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <button class="text-gray-500 hover:text-primary focus:outline-none">
                        <i class="fas fa-bell text-xl"></i>
                        <span class="notification-dot"></span>
                    </button>
                </div>
                <div class="relative">
                    <button class="flex items-center focus:outline-none" onclick="toggleUserMenu()">
                        <div class="h-10 w-10 rounded-full bg-primary flex items-center justify-center text-white">
                            <?= substr($_SESSION['user_name'] ?? 'A', 0, 1) ?>
                        </div>
                    </button>
                    <div id="user-menu" class="absolute hidden right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-20">
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Perfil</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Configuración</a>
                        <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Cerrar sesión</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dashboard Content -->
        <main class="p-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-primary">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-500">Total Usuarios</h3>
                            <p class="text-2xl font-semibold text-gray-900"><?= count($users) ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-success">
                            <i class="fas fa-th text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-500">Total Aplicaciones</h3>
                            <p class="text-2xl font-semibold text-gray-900"><?= count($apps) ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-secondary">
                            <i class="fas fa-clipboard-list text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-500">Registros de Actividad</h3>
                            <p class="text-2xl font-semibold text-gray-900"><?= count($logs) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="border-b">
                    <nav class="flex -mb-px">
                        <button id="tab-users" class="mr-8 py-4 px-1 text-sm font-medium active-tab" onclick="switchTab('users')">
                            Usuarios
                        </button>
                        <button id="tab-apps" class="mr-8 py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700" onclick="switchTab('apps')">
                            Aplicaciones
                        </button>
                        <button id="tab-logs" class="mr-8 py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700" onclick="switchTab('logs')">
                            Registros
                        </button>
                        <button id="tab-assignments" class="mr-8 py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700" onclick="switchTab('assignments')">
                            Asignaciones
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Tab Contents -->
            <div id="tab-content">
                <!-- Users Tab -->
                <div id="content-users" class="tab-panel">
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900">Gestión de Usuarios</h3>
                            <button class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded flex items-center" onclick="openModal('userModal')">
                                <i class="fas fa-plus mr-2"></i> Nuevo Usuario
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rol</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= $u['id'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($u['name']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($u['email']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?= $u['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' ?>">
                                                <?= $u['role'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?= $u['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= $u['status'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <button class="text-blue-600 hover:text-blue-900 mr-3" onclick="editUser(<?= $u['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="text-red-600 hover:text-red-900" onclick="confirmDeleteUser(<?= $u['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Apps Tab -->
                <div id="content-apps" class="tab-panel hidden">
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900">Gestión de Aplicaciones</h3>
                            <button class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded flex items-center" onclick="openModal('appModal')">
                                <i class="fas fa-plus mr-2"></i> Nueva App
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">URL</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($apps as $a): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= $a['id'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($a['name']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="/<?= htmlspecialchars($a['url']) ?>" target="_blank" class="text-blue-600 hover:text-blue-900">
                                                <?= htmlspecialchars($a['url']) ?>
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?= $a['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= $a['is_active'] ? "Activa" : "Inactiva" ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <button class="text-blue-600 hover:text-blue-900 mr-3" onclick="editApp(<?= $a['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="text-red-600 hover:text-red-900" onclick="confirmDeleteApp(<?= $a['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Logs Tab -->
                <div id="content-logs" class="tab-panel hidden">
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b">
                            <h3 class="text-lg font-medium text-gray-900">Registros de Actividad</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acción</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detalles</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= $l['created_at'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($l['email'] ?? 'sistema') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?= $l['action'] === 'login' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?>">
                                                <?= $l['action'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4"><?= htmlspecialchars($l['details']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Assignments Tab -->
                <div id="content-assignments" class="tab-panel hidden">
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900">Asignación de Aplicaciones</h3>
                            <button class="bg-primary hover:bg-secondary text-white px-4 py-2 rounded flex items-center" onclick="openModal('assignModal')">
                                <i class="fas fa-link mr-2"></i> Nueva Asignación
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aplicación</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha Asignación</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($user_apps as $ua): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($ua['user_name']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($ua['app_name']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= $ua['assigned_at'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <button class="text-red-600 hover:text-red-900" onclick="confirmUnassign(<?= $ua['id'] ?>)">
                                                <i class="fas fa-unlink"></i> Desasignar
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modals -->
    <!-- User Modal -->
    <div id="userModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="border-b px-6 py-4">
                <h3 class="text-xl font-semibold">Nuevo Usuario</h3>
            </div>
            <form id="userForm" method="post" action="admin_save_user.php" class="px-6 py-4">
                <input type="hidden" name="user_id" id="user_id">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="name">Nombre</label>
                    <input type="text" id="name" name="name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="email">Email</label>
                    <input type="email" id="email" name="email" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">Contraseña</label>
                    <input type="password" id="password" name="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <p class="text-xs text-gray-500 mt-1">Dejar en blanco para no cambiar</p>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="role">Rol</label>
                    <select id="role" name="role" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="user">Usuario</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="status">Estado</label>
                    <select id="status" name="status" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="active">Activo</option>
                        <option value="inactive">Inactivo</option>
                    </select>
                </div>
                <div class="flex justify-end pt-4 border-t">
                    <button type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2" onclick="closeModal('userModal')">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-secondary text-white font-bold py-2 px-4 rounded">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- App Modal -->
    <div id="appModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="border-b px-6 py-4">
                <h3 class="text-xl font-semibold">Nueva Aplicación</h3>
            </div>
            <form id="appForm" method="post" action="admin_save_app.php" class="px-6 py-4">
                <input type="hidden" name="app_id" id="app_id">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="app_name">Nombre</label>
                    <input type="text" id="app_name" name="name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="app_url">URL</label>
                    <input type="text" id="app_url" name="url" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="app_status">Estado</label>
                    <select id="app_status" name="is_active" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="1">Activa</option>
                        <option value="0">Inactiva</option>
                    </select>
                </div>
                <div class="flex justify-end pt-4 border-t">
                    <button type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2" onclick="closeModal('appModal')">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-secondary text-white font-bold py-2 px-4 rounded">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign Modal -->
    <div id="assignModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="border-b px-6 py-4">
                <h3 class="text-xl font-semibold">Asignar Aplicación</h3>
            </div>
            <form method="post" action="admin_assign_app.php" class="px-6 py-4">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="assign_user">Usuario</label>
                    <select id="assign_user" name="user_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="">Seleccionar usuario</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="assign_app">Aplicación</label>
                    <select id="assign_app" name="app_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        <option value="">Seleccionar aplicación</option>
                        <?php foreach ($apps as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?> (<?= htmlspecialchars($a['url']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end pt-4 border-t">
                    <button type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2" onclick="closeModal('assignModal')">Cancelar</button>
                    <button type="submit" class="bg-primary hover:bg-secondary text-white font-bold py-2 px-4 rounded">Asignar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="border-b px-6 py-4">
                <h3 class="text-xl font-semibold">Confirmar acción</h3>
            </div>
            <div class="px-6 py-4">
                <p id="confirmMessage" class="text-gray-700 mb-4">¿Está seguro de que desea realizar esta acción?</p>
                <div class="flex justify-end pt-4 border-t">
                    <button type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2" onclick="closeModal('confirmModal')">Cancelar</button>
                    <button type="button" id="confirmAction" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Toast -->
    <div id="toast" class="fixed top-4 right-4 p-4 rounded-lg shadow-lg hidden z-50">
        <div class="flex items-center">
            <span id="toastIcon" class="mr-2"></span>
            <span id="toastMessage"></span>
        </div>
    </div>

    <script>
        // Toggle sidebar
        function toggleSidebar() {
            document.body.classList.toggle('collapsed');
            const icon = document.getElementById('sidebar-icon');
            if (document.body.classList.contains('collapsed')) {
                icon.classList.remove('fa-chevron-left');
                icon.classList.add('fa-chevron-right');
            } else {
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-left');
            }
        }

        // Toggle user menu
        function toggleUserMenu() {
            document.getElementById('user-menu').classList.toggle('hidden');
        }

        // Switch tabs
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-panel').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Show selected tab content
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // Update tab navigation
            document.querySelectorAll('[id^="tab-"]').forEach(tab => {
                tab.classList.remove('active-tab', 'text-primary');
                tab.classList.add('text-gray-500');
            });
            
            document.getElementById('tab-' + tabName).classList.add('active-tab', 'text-primary');
            document.getElementById('tab-' + tabName).classList.remove('text-gray-500');
        }

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('fixed')) {
                document.querySelectorAll('.fixed.hidden').forEach(modal => {
                    if (!modal.classList.contains('hidden')) {
                        modal.classList.add('hidden');
                    }
                });
            }
            
            // Close user menu when clicking elsewhere
            if (!event.target.matches('button') && !event.target.closest('button')) {
                document.getElementById('user-menu').classList.add('hidden');
            }
        }

        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toastIcon');
            const toastMessage = document.getElementById('toastMessage');
            
            toastMessage.textContent = message;
            
            switch(type) {
                case 'success':
                    toast.classList.add('bg-green-100', 'text-green-800');
                    toastIcon.innerHTML = '<i class="fas fa-check-circle text-green-500"></i>';
                    break;
                case 'error':
                    toast.classList.add('bg-red-100', 'text-red-800');
                    toastIcon.innerHTML = '<i class="fas fa-exclamation-circle text-red-500"></i>';
                    break;
                case 'warning':
                    toast.classList.add('bg-yellow-100', 'text-yellow-800');
                    toastIcon.innerHTML = '<i class="fas fa-exclamation-triangle text-yellow-500"></i>';
                    break;
                case 'info':
                    toast.classList.add('bg-blue-100', 'text-blue-800');
                    toastIcon.innerHTML = '<i class="fas fa-info-circle text-blue-500"></i>';
                    break;
            }
            
            toast.classList.remove('hidden');
            
            setTimeout(() => {
                toast.classList.add('hidden');
                toast.classList.remove('bg-green-100', 'text-green-800', 'bg-red-100', 'text-red-800', 'bg-yellow-100', 'text-yellow-800', 'bg-blue-100', 'text-blue-800');
            }, 3000);
        }

        // Confirmation modal
        function confirmAction(message, callback) {
            document.getElementById('confirmMessage').textContent = message;
            const confirmBtn = document.getElementById('confirmAction');
            
            // Remove previous event listeners
            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
            
            newConfirmBtn.addEventListener('click', function() {
                callback();
                closeModal('confirmModal');
            });
            
            openModal('confirmModal');
        }

        function confirmDeleteUser(userId) {
            confirmAction('¿Está seguro de que desea eliminar este usuario?', function() {
                window.location.href = 'admin_delete_user.php?id=' + userId;
            });
        }

        function confirmDeleteApp(appId) {
            confirmAction('¿Está seguro de que desea eliminar esta aplicación?', function() {
                window.location.href = 'admin_delete_app.php?id=' + appId;
            });
        }

        function confirmUnassign(assignmentId) {
            confirmAction('¿Está seguro de que desea desasignar esta aplicación?', function() {
                window.location.href = 'admin_unassign_app.php?id=' + assignmentId;
            });
        }

        // Edit user
        function editUser(userId) {
            // In a real application, you would fetch user data via AJAX
            // For this example, we'll just show the modal
            document.getElementById('user_id').value = userId;
            document.querySelector('#userModal h3').textContent = 'Editar Usuario';
            openModal('userModal');
        }

        // Edit app
        function editApp(appId) {
            // In a real application, you would fetch app data via AJAX
            document.getElementById('app_id').value = appId;
            document.querySelector('#appModal h3').textContent = 'Editar Aplicación';
            openModal('appModal');
        }

        // Check for success/error messages in URL parameters
        window.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                showToast(urlParams.get('success'), 'success');
            }
            if (urlParams.has('error')) {
                showToast(urlParams.get('error'), 'error');
            }
        });
    </script>
</body>
</html>