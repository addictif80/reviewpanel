<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['auto_approve' => 0]);
    exit;
}

$siteId = intval($_GET['site_id'] ?? 0);

if (!$siteId) {
    echo json_encode(['auto_approve' => 0]);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT auto_approve FROM widgets WHERE site_id = ?');
    $stmt->execute([$siteId]);
    $widget = $stmt->fetch();
    
    echo json_encode(['auto_approve' => $widget ? $widget['auto_approve'] : 0]);
} catch (Exception $e) {
    echo json_encode(['auto_approve' => 0]);
}