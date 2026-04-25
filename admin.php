<?php
session_start();
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_settings') {
            $pdo = getDB();
            
            $fields = [
                'smtp_host', 'smtp_port', 'smtp_secure', 'smtp_user', 'smtp_password', 'smtp_from'
            ];
            
            foreach ($fields as $field) {
                $value = $_POST[$field] ?? '';
                $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
                $stmt->execute([$field, $value, $value]);
            }
            
            $success = 'Paramètres enregistrés !';
        }
        
        if ($_POST['action'] === 'test_email') {
            $to = $_POST['test_email'] ?? '';
            if ($to && sendEmail($to, 'Test ReviewPanel', '<h1>Email de test</h1><p>Configuration SMTP OK !</p>')) {
                $success = 'Email de test envoyé !';
            } else {
                $error = 'Erreur envoi email';
            }
        }
    }
}

$pdo = getDB();
$stmt = $pdo->prepare('SELECT * FROM settings');
$stmt->execute();
$settingsArr = $stmt->fetchAll();
$settings = [];
foreach ($settingsArr as $s) {
    $settings[$s['setting_key']] = $s['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - ReviewPanel</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">ReviewPanel <span class="badge">Admin</span></div>
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="?section=settings">Paramètres</a>
            <a href="?section=users">Utilisateurs</a>
            <a href="index.php?logout=1">Déconnexion</a>
        </div>
    </nav>

    <div class="container">
        <h1>Administration</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <?php
            $pdo = getDB();
            echo '<div class="stat-card"><div class="stat-value">' . $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() . '</div><div class="stat-label">Utilisateurs</div></div>';
            echo '<div class="stat-card"><div class="stat-value">' . $pdo->query('SELECT COUNT(*) FROM sites')->fetchColumn() . '</div><div class="stat-label">Sites</div></div>';
            echo '<div class="stat-card"><div class="stat-value">' . $pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn() . '</div><div class="stat-label">Avis</div></div>';
            ?>
        </div>

        <h2>Paramètres SMTP</h2>
        <form method="POST" class="settings-form">
            <input type="hidden" name="action" value="update_settings">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Serveur SMTP</label>
                    <input type="text" name="smtp_host" value="<?= htmlspecialchars($settings['smtp_host'] ?? SMTP_HOST) ?>" required>
                </div>
                <div class="form-group">
                    <label>Port</label>
                    <input type="number" name="smtp_port" value="<?= htmlspecialchars($settings['smtp_port'] ?? SMTP_PORT) ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Utilisateur SMTP</label>
                    <input type="email" name="smtp_user" value="<?= htmlspecialchars($settings['smtp_user'] ?? SMTP_USER) ?>">
                </div>
                <div class="form-group">
                    <label>Mot de passe SMTP</label>
                    <input type="password" name="smtp_password" value="<?= htmlspecialchars($settings['smtp_password'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Email expéditeur</label>
                    <input type="email" name="smtp_from" value="<?= htmlspecialchars($settings['smtp_from'] ?? SMTP_FROM) ?>">
                </div>
                <div class="form-group">
                    <label>Sécurisé (SSL)</label>
                    <select name="smtp_secure">
                        <option value="0" <?= (($settings['smtp_secure'] ?? '') == '0') ? 'selected' : '' ?>>Non (TLS)</option>
                        <option value="1" <?= (($settings['smtp_secure'] ?? '') == '1') ? 'selected' : '' ?>>Oui (SSL)</option>
                    </select>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary">Enregistrer</button>
                <button type="button" onclick="testEmail()" class="btn-secondary">Tester email</button>
            </div>
        </form>

        <h2>Utilisateurs</h2>
        <table class="users-table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Inscrit le</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->query('SELECT name, email, role, created_at FROM users ORDER BY created_at DESC');
                while ($user = $stmt->fetch()):
                ?>
                <tr>
                    <td><?= htmlspecialchars($user['name']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><span class="user-role <?= $user['role'] ?>"><?= $user['role'] ?></span></td>
                    <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal: Test Email -->
    <div id="modal-testEmail" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('modal-testEmail').classList.remove('active')">&times;</span>
            <h2>Tester l'email</h2>
            <form onsubmit="submitTestEmail(event)">
                <div class="form-group">
                    <label>Adresse email de test</label>
                    <input type="email" name="test_email" required>
                </div>
                <button type="submit" class="btn-primary">Envoyer</button>
            </form>
        </div>
    </div>

    <script>
    function testEmail() {
        document.getElementById('modal-testEmail').classList.add('active');
    }

    function submitTestEmail(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        formData.append('action', 'test_email');
        
        fetch('admin.php', { method: 'POST', body: formData })
            .then(r => r.text())
            .then(() => {
                document.getElementById('modal-testEmail').classList.remove('active');
                e.target.reset();
                showNotification('Email de test envoyé !', 'success');
            })
            .catch(() => showNotification('Erreur lors de l\'envoi', 'error'));
    }

    function showNotification(message, type) {
        const notif = document.getElementById('notification');
        notif.className = 'notification ' + type;
        notif.textContent = message;
        notif.style.display = 'block';
        setTimeout(() => notif.style.display = 'none', 3000);
    }
    </script>
    
    <div id="notification" class="notification"></div>
</body>
</html>