<?php
require_once 'config.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    
    if ($password !== $confirm) {
        $error = 'Les mots de passe ne correspondent pas';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW()');
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?');
            $stmt->execute([$hash, $user['id']]);
            $success = 'Mot de passe réinitialisé ! <a href="index.php">Se connecter</a>';
        } else {
            $error = 'Token invalide ou expiré';
        }
    }
} elseif (!$token) {
    $error = 'Token requis';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialiser le mot de passe</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container" style="margin-top: 5rem;">
        <div class="auth-card" style="max-width: 400px; margin: 0 auto;">
            <h2>Nouveau mot de passe</h2>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php else: ?>
                <form method="POST">
                    <input type="password" name="password" placeholder="Nouveau mot de passe" required minlength="6">
                    <input type="password" name="confirm" placeholder="Confirmer" required minlength="6">
                    <button type="submit">Réinitialiser</button>
                </form>
            <?php endif; ?>
            <p style="text-align:center; margin-top:1rem;">
                <a href="index.php">Retour</a>
            </p>
        </div>
    </div>
</body>
</html>