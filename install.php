<?php
session_start();

if (file_exists('config.php') && $step < 5) {
    header('Location: index.php');
    exit;
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

function checkRequirement($name, $test) {
    return $test ? '<span class="ok">✓</span>' : '<span class="fail">✗</span>';
}

function testMySQL($host, $user, $pass) {
    try {
        $pdo = new PDO("mysql:host=$host", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        return [true, 'Connexion réussie'];
    } catch (PDOException $e) {
        return [false, $e->getMessage()];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 2) {
        $db_host = $_POST['db_host'];
        $db_name = $_POST['db_name'];
        $db_user = $_POST['db_user'];
        $db_pass = $_POST['db_pass'];
        
        list($ok, $msg) = testMySQL($db_host, $db_user, $db_pass);
        
        if ($ok) {
            try {
                $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                $pdo->exec("USE `$db_name`");
                $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    role ENUM('user', 'admin') DEFAULT 'user',
                    reset_token VARCHAR(255),
                    reset_expires DATETIME,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
                $pdo->exec("CREATE TABLE IF NOT EXISTS sites (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    url VARCHAR(500) NOT NULL,
                    description TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )");
                $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    site_id INT NOT NULL,
                    reviewer_name VARCHAR(255) NOT NULL,
                    reviewer_email VARCHAR(255),
                    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
                    title VARCHAR(255),
                    content TEXT NOT NULL,
                    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
                )");
                $pdo->exec("CREATE TABLE IF NOT EXISTS widgets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    site_id INT UNIQUE NOT NULL,
                    style VARCHAR(50) DEFAULT 'light',
                    show_rating TINYINT(1) DEFAULT 1,
                    show_count TINYINT(1) DEFAULT 1,
                    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
                )");
                $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                    setting_value TEXT
                )");
                
                $_SESSION['install'] = [
                    'db_host' => $db_host,
                    'db_name' => $db_name,
                    'db_user' => $db_user,
                    'db_pass' => $db_pass
                ];
                header('Location: install.php?step=3');
                exit;
            } catch (PDOException $e) {
                $error = 'Erreur: ' . $e->getMessage();
            }
        } else {
            $error = 'Connexion MySQL échouée: ' . $msg;
        }
    }
    
    if ($step == 3) {
        $admin_email = $_POST['admin_email'];
        $admin_pass = $_POST['admin_pass'];
        $admin_name = $_POST['admin_name'];
        
        if (strlen($admin_pass) < 6) {
            $error = 'Le mot de passe doit contenir au moins 6 caractères';
        } else {
            $db = $_SESSION['install'];
            $pdo = new PDO("mysql:host={$db['db_host']};dbname={$db['db_name']}", $db['db_user'], $db['db_pass']);
            
            $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO users (email, password, name, role) VALUES (?, ?, ?, ?)')->execute([$admin_email, $hash, $admin_name, 'admin']);
            
            $_SESSION['install']['admin_email'] = $admin_email;
            header('Location: install.php?step=4');
            exit;
        }
    }
    
    if ($step == 4) {
        $smtp_host = $_POST['smtp_host'];
        $smtp_port = $_POST['smtp_port'];
        $smtp_user = $_POST['smtp_user'];
        $smtp_pass = $_POST['smtp_pass'];
        $smtp_from = $_POST['smtp_from'];
        $app_url = $_POST['app_url'];
        
        $config = "<?php
define('DB_HOST', '{$_SESSION['install']['db_host']}');
define('DB_NAME', '{$_SESSION['install']['db_name']}');
define('DB_USER', '{$_SESSION['install']['db_user']}');
define('DB_PASS', '{$_SESSION['install']['db_pass']}');

define('SMTP_HOST', '$smtp_host');
define('SMTP_PORT', $smtp_port);
define('SMTP_USER', '$smtp_user');
define('SMTP_PASS', '$smtp_pass');
define('SMTP_FROM', '$smtp_from');

define('APP_URL', '$app_url');
define('APP_SECRET', '" . bin2hex(random_bytes(24)) . "');

function getDB() {
    static \$pdo = null;
    if (\$pdo === null) {
        \$pdo = new PDO(\"mysql:host=\".DB_HOST.\";dbname=\".DB_NAME, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
    return \$pdo;
}

function sendEmail(\$to, \$subject, \$html) {
    \$headers = \"MIME-Version: 1.0\r\n\";
    \$headers .= \"Content-Type: text/html; charset=UTF-8\r\n\";
    \$headers .= \"From: \" . SMTP_FROM . \"\r\n\";
    \$message = \"<html><body>\$html</body></html>\";
    return mail(\$to, \$subject, \$message, \$headers);
}

function isLoggedIn() {
    return isset(\$_SESSION['user_id']);
}

function isAdmin() {
    return isset(\$_SESSION['role']) && \$_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: index.php');
        exit;
    }
}
";
        file_put_contents('config.php', $config);
        session_destroy();
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - ReviewPanel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .install-box { background: #fff; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.1); width: 100%; max-width: 500px; }
        h1 { color: #00b67a; margin-bottom: 1.5rem; }
        h2 { font-size: 1.2rem; color: #333; margin-bottom: 1rem; }
        .step { display: flex; gap: 0.5rem; margin-bottom: 2rem; }
        .step span { width: 32px; height: 32px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; }
        .step span.active { background: #00b67a; color: #fff; }
        .step span.done { background: #00b67a; color: #fff; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; }
        .form-group input:focus { outline: none; border-color: #00b67a; }
        button { width: 100%; padding: 0.75rem; background: #00b67a; color: #fff; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; }
        button:hover { background: #009963; }
        .error { background: #fee; color: #e74c3c; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; }
        .info { background: #e3f2fd; color: #1565c0; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
        .requirements { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
        .requirements div { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #eee; }
        .requirements div:last-child { border: none; }
        .ok { color: #00b67a; }
        .fail { color: #e74c3c; }
    </style>
</head>
<body>
    <div class="install-box">
        <h1>ReviewPanel</h1>
        
        <div class="step">
            <span class="<?= $step >= 1 ? 'active' : '' ?>">1</span>
            <span class="<?= $step >= 2 ? 'active' : '' ?>">2</span>
            <span class="<?= $step >= 3 ? 'active' : '' ?>">3</span>
            <span class="<?= $step >= 4 ? 'active' : '' ?>">4</span>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($step == 1): ?>
            <h2>Vérification système</h2>
            <div class="requirements">
                <div><span>PHP 7.4+</span> <?= checkRequirement('php', version_compare(PHP_VERSION, '7.4.0') >= 0) ?></div>
                <div><span>Extension PDO MySQL</span> <?= checkRequirement('pdo', extension_loaded('pdo_mysql')) ?></div>
                <div><span>Extension JSON</span> <?= checkRequirement('json', extension_loaded('json')) ?></div>
            </div>
            <button onclick="location.href='?step=2'">Continuer</button>
        <?php endif; ?>
        
        <?php if ($step == 2): ?>
            <h2>Configuration base de données</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Serveur MySQL</label>
                    <input type="text" name="db_host" value="localhost" required>
                </div>
                <div class="form-group">
                    <label>Nom de la base</label>
                    <input type="text" name="db_name" value="review_panel" required>
                </div>
                <div class="form-group">
                    <label>Utilisateur</label>
                    <input type="text" name="db_user" value="root" required>
                </div>
                <div class="form-group">
                    <label>Mot de passe</label>
                    <input type="password" name="db_pass" placeholder="Laissez vide si aucun">
                </div>
                <button type="submit">Tester et créer les tables</button>
            </form>
        <?php endif; ?>
        
        <?php if ($step == 3): ?>
            <h2>Compte administrateur</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Nom</label>
                    <input type="text" name="admin_name" value="Admin" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="admin_email" required>
                </div>
                <div class="form-group">
                    <label>Mot de passe</label>
                    <input type="password" name="admin_pass" minlength="6" placeholder="Minimum 6 caractères" required>
                </div>
                <button type="submit">Créer le compte</button>
            </form>
        <?php endif; ?>
        
        <?php if ($step == 4): ?>
            <h2>Configuration SMTP (optionnel)</h2>
            <div class="info">Laissez vide pour configurer plus tard depuis le panel admin</div>
            <form method="POST">
                <div class="form-group">
                    <label>URL du site</label>
                    <input type="url" name="app_url" value="<?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ?>" required>
                </div>
                <div class="form-group">
                    <label>Serveur SMTP</label>
                    <input type="text" name="smtp_host" placeholder="smtp.gmail.com">
                </div>
                <div class="form-group">
                    <label>Port SMTP</label>
                    <input type="number" name="smtp_port" value="587">
                </div>
                <div class="form-group">
                    <label>Utilisateur SMTP</label>
                    <input type="email" name="smtp_user" placeholder="email@gmail.com">
                </div>
                <div class="form-group">
                    <label>Mot de passe SMTP</label>
                    <input type="password" name="smtp_pass" placeholder="Mot de passe application">
                </div>
                <div class="form-group">
                    <label>Email expéditeur</label>
                    <input type="email" name="smtp_from" placeholder="noreply@votresite.com">
                </div>
                <button type="submit">Terminer l'installation</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>