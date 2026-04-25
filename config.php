<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'review_panel');
define('DB_USER', 'root');
define('DB_PASS', 'password');

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_FROM', 'noreply@reviewpanel.com');

define('APP_URL', 'http://localhost');
define('APP_SECRET', bin2hex(random_bytes(24)));

function runMigrations() {
    static $run = false;
    if ($run) return;
    $run = true;
    
    try {
        $pdo = getDB();
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $migrations = [
            '001_add_auto_approve' => "ALTER TABLE widgets ADD COLUMN auto_approve TINYINT(1) DEFAULT 0"
        ];
        
        foreach ($migrations as $name => $sql) {
            $stmt = $pdo->prepare('SELECT id FROM migrations WHERE name = ?');
            $stmt->execute([$name]);
            
            if (!$stmt->fetch()) {
                try {
                    $pdo->exec($sql);
                    $pdo->prepare('INSERT INTO migrations (name) VALUES (?)')->execute([$name]);
                } catch (PDOException $e) {
                    if ($e->getCode() != '1060') throw $e;
                    $pdo->prepare('INSERT INTO migrations (name) VALUES (?)')->execute([$name]);
                }
            }
        }
    } catch (Exception $e) {
        error_log('Migration error: ' . $e->getMessage());
    }
}

runMigrations();

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
    return $pdo;
}

function sendEmail($to, $subject, $html) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM . "\r\n";
    
    $text = strip_tags($html);
    $message = "<html><body>$html</body></html>";
    
    return mail($to, $subject, $message, $headers);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
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