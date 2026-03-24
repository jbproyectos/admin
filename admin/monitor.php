<?php
require 'db.php';

$stmt = $pdo->query("SELECT * FROM apps WHERE is_active = 1");
$apps = $stmt->fetchAll();

foreach ($apps as $app) {
    $status = 'down';

    $ch = curl_init($app['url']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http >= 200 && $http < 400) {
        $status = 'up';
    }

    $pdo->prepare("INSERT INTO app_monitor (app_id, last_status, last_checked, total_checks, failed_checks)
                   VALUES (?, ?, NOW(), 1, IF(?='down',1,0))
                   ON DUPLICATE KEY UPDATE
                   last_status=VALUES(last_status),
                   last_checked=NOW(),
                   total_checks=total_checks+1,
                   failed_checks=failed_checks+IF(?='down',1,0),
                   uptime_percent=ROUND(((total_checks-failed_checks)/total_checks)*100,2)")
        ->execute([$app['id'], $status, $status, $status]);
}
