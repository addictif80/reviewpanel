<?php
require_once 'config.php';

function runMigrationsCLI() {
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
                echo "Migration '$name' appliquée\n";
            } catch (PDOException $e) {
                if ($e->getCode() == '42S01') {
                    echo "Table widgets n'existe pas encore - Skip\n";
                } elseif ($e->getCode() == '1060') {
                    echo "Colonne existe déjà - Skip\n";
                    $pdo->prepare('INSERT INTO migrations (name) VALUES (?)')->execute([$name]);
                } else {
                    echo "Erreur migration '$name': " . $e->getMessage() . "\n";
                }
            }
        }
    }

    echo "Migrations terminées!\n";
}

runMigrationsCLI();
