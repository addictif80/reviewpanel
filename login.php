<?php
session_start();
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND role = "admin"');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Identifiants administrateur incorrects';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - ReviewPanel</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container" style="margin-top: 5rem;">
        <div class="auth-card" style="max-width: 400px; margin: 0 auto;">
            <h2>Admin - Connexion</h2>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="email" name="email" placeholder="Email admin" required>
                <input type="password" name="password" placeholder="Mot de passe" required>
                <button type="submit">Se connecter</button>
            </form>
            <p style="text-align:center; margin-top:1rem;">
                <a href="index.php">Retour au site</a>
            </p>
        </div>
    </div>
</body>
</html>