<?php
session_start();
require 'db.php';
require 'functions.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM apps WHERE id=? AND is_active=1");
$stmt->execute([$id]);
$app = $stmt->fetch();

if (!$app) { die("App no encontrada"); }

$pdo->prepare("INSERT INTO app_usage (app_id, user_id) VALUES (?, ?)")
    ->execute([$app['id'], $_SESSION['user_id']]);
addLog($pdo, $_SESSION['user_id'], "access_app", $app['name']);

header("Location: " . $app['url']);
exit;
