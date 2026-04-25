<?php
require_once 'config.php';

header('Content-Type: application/json');

$siteId = $_GET['site_id'] ?? 0;

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