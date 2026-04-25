<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$siteId = $_POST['site_id'] ?? 0;
$reviewerName = trim($_POST['reviewer_name'] ?? '');
$reviewerEmail = trim($_POST['reviewer_email'] ?? '');
$rating = intval($_POST['rating'] ?? 0);
$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');

if (!$siteId || !$reviewerName || !$rating || !$content) {
    echo json_encode(['success' => false, 'error' => 'Champs requis manquants']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'error' => 'Note invalide']);
    exit;
}

try {
    $pdo = getDB();
    
    $stmt = $pdo->prepare('SELECT id FROM sites WHERE id = ?');
    $stmt->execute([$siteId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Site non trouvé']);
        exit;
    }
    
    $stmt = $pdo->prepare('INSERT INTO reviews (site_id, reviewer_name, reviewer_email, rating, title, content, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$siteId, $reviewerName, $reviewerEmail, $rating, $title, $content, 'pending']);
    
    echo json_encode(['success' => true, 'message' => 'Avis soumis']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}