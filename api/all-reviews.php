<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$siteId = intval($_GET['site_id'] ?? 0);

if (!$siteId) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT * FROM reviews WHERE site_id = ? AND site_id IN (SELECT id FROM sites WHERE user_id = ?) ORDER BY created_at DESC');
    $stmt->execute([$siteId, $_SESSION['user_id']]);
    $reviews = $stmt->fetchAll();
    echo json_encode($reviews);
} catch (Exception $e) {
    echo json_encode([]);
}