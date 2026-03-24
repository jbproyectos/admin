<?php
$dsn = "mysql:host=localhost;dbname=db_admin;charset=utf8mb4";
$user = "adminweb"; 
$pass = "20171051DEV";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Error DB: " . $e->getMessage());
}
