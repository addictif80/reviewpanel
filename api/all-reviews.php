<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$siteId = $_GET['site_id'] ?? 0;

if (!$siteId) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT * FROM reviews WHERE site_id = ? ORDER BY created_at DESC');
    $stmt->execute([intval($siteId)]);
    $reviews = $stmt->fetchAll();
    echo json_encode($reviews);
} catch (Exception $e) {
    echo json_encode([]);
}