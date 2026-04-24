<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'register') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($name) || empty($email) || empty($password)) {
                $error = 'Tous les champs sont requis';
            } else {
                $pdo = getDB();
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email déjà utilisé';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('INSERT INTO users (email, password, name) VALUES (?, ?, ?)');
                    $stmt->execute([$email, $hash, $name]);
                    
                    sendEmail($email, 'Bienvenue sur ReviewPanel', "<h1>Bienvenue $name !</h1><p>Merci de votre inscription.</p>");
                    
                    $success = 'Inscription réussie ! Vous pouvez maintenant vous connecter.';
                }
            }
        }
        
        if ($_POST['action'] === 'login') {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            $pdo = getDB();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                header('Location: index.php');
                exit;
            } else {
                $error = 'Email ou mot de passe incorrect';
            }
        }
        
        if ($_POST['action'] === 'forgot') {
            $email = trim($_POST['email'] ?? '');
            
            if ($email) {
                $pdo = getDB();
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    $stmt = $pdo->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?');
                    $stmt->execute([$token, $expires, $email]);
                    
                    $link = APP_URL . '/reset-password.php?token=' . $token;
                    sendEmail($email, 'Réinitialisation mot de passe', "<h1>Réinitialisation</h1><p><a href='$link'>Cliquez ici</a></p>");
                }
                $success = 'Si le compte existe, un email a été envoyé';
            }
        }
        
        if ($_POST['action'] === 'add_site' && isLoggedIn()) {
            $name = trim($_POST['name'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if ($name && $url) {
                $pdo = getDB();
                $stmt = $pdo->prepare('INSERT INTO sites (user_id, name, url, description) VALUES (?, ?, ?, ?)');
                $stmt->execute([$_SESSION['user_id'], $name, $url, $description]);
                $success = 'Site ajouté !';
            }
        }
        
        if ($_POST['action'] === 'add_review' && isLoggedIn()) {
            $site_id = intval($_POST['site_id']);
            $reviewer_name = trim($_POST['reviewer_name']);
            $reviewer_email = trim($_POST['reviewer_email']);
            $rating = intval($_POST['rating']);
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            
            if ($site_id && $reviewer_name && $rating && $content) {
                $pdo = getDB();
                $stmt = $pdo->prepare('SELECT id FROM sites WHERE id = ? AND user_id = ?');
                $stmt->execute([$site_id, $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    $stmt = $pdo->prepare('INSERT INTO reviews (site_id, reviewer_name, reviewer_email, rating, title, content, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$site_id, $reviewer_name, $reviewer_email, $rating, $title, $content, 'approved']);
                    $success = 'Avis ajouté !';
                }
            }
        }
        
        if ($_POST['action'] === 'delete_site' && isLoggedIn()) {
            $site_id = intval($_POST['site_id']);
            $pdo = getDB();
            $stmt = $pdo->prepare('DELETE FROM sites WHERE id = ? AND user_id = ?');
            $stmt->execute([$site_id, $_SESSION['user_id']]);
            $success = 'Site supprimé';
        }
        
        if ($_POST['action'] === 'delete_review' && isLoggedIn()) {
            $review_id = intval($_POST['review_id']);
            $pdo = getDB();
            $stmt = $pdo->prepare('DELETE FROM reviews WHERE id = ?');
            $stmt->execute([$review_id]);
            $success = 'Avis supprimé';
        }
        
        if ($_POST['action'] === 'create_widget' && isLoggedIn()) {
            $site_id = intval($_POST['site_id']);
            $pdo = getDB();
            
            $stmt = $pdo->prepare('SELECT id FROM widgets WHERE site_id = ?');
            $stmt->execute([$site_id]);
            $widget = $stmt->fetch();
            
            if ($widget) {
                $stmt = $pdo->prepare('UPDATE widgets SET style = ? WHERE id = ?');
                $stmt->execute([$_POST['style'] ?? 'light', $widget['id']]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO widgets (site_id) VALUES (?)');
                $stmt->execute([$site_id]);
            }
            
            $success = 'Widget créé !';
        }
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReviewPanel - Gestion d'Avis Clients</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">ReviewPanel</div>
        <div class="nav-links">
            <?php if (isLoggedIn()): ?>
                <span>Bienvenue, <?= htmlspecialchars($_SESSION['name']) ?></span>
                <a href="?logout=1">Déconnexion</a>
            <?php else: ?>
                <a href="#login" onclick="showTab('login')">Connexion</a>
                <a href="#register" onclick="showTab('register')">Inscription</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!isLoggedIn()): ?>
            <div class="auth-cards">
                <div class="auth-card" id="loginCard">
                    <h2>Connexion</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="login">
                        <input type="email" name="email" placeholder="Email" required>
                        <input type="password" name="password" placeholder="Mot de passe" required>
                        <button type="submit">Se connecter</button>
                    </form>
                    <p class="switch-link">Pas de compte ? <a href="#register" onclick="showTab('register')">S'inscrire</a></p>
                    <p><a href="#forgot" onclick="showTab('forgot')">Mot de passe oublié ?</a></p>
                </div>

                <div class="auth-card" id="registerCard" style="display:none;">
                    <h2>Inscription</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="register">
                        <input type="text" name="name" placeholder="Nom complet" required>
                        <input type="email" name="email" placeholder="Email" required>
                        <input type="password" name="password" placeholder="Mot de passe" required>
                        <button type="submit">S'inscrire</button>
                    </form>
                    <p class="switch-link">Déjà un compte ? <a href="#login" onclick="showTab('login')">Se connecter</a></p>
                </div>

                <div class="auth-card" id="forgotCard" style="display:none;">
                    <h2>Mot de passe oublié</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="forgot">
                        <input type="email" name="email" placeholder="Votre email" required>
                        <button type="submit">Envoyer le lien</button>
                    </form>
                    <p><a href="#login" onclick="showTab('login')">Retour</a></p>
                </div>
            </div>
        <?php else: ?>
            <div class="dashboard">
                <div class="dashboard-header">
                    <h1>Mon Dashboard</h1>
                    <button class="btn-primary" onclick="showModal('addSite')">+ Ajouter un site</button>
                </div>

                <div class="sites-grid">
                    <?php
                    $pdo = getDB();
                    $stmt = $pdo->prepare('SELECT * FROM sites WHERE user_id = ? ORDER BY created_at DESC');
                    $stmt->execute([$_SESSION['user_id']]);
                    $sites = $stmt->fetchAll();
                    
                    if (empty($sites)):
                    ?>
                        <div class="empty-state">
                            <h3>Aucun site</h3>
                            <p>Ajoutez votre premier site pour collecter des avis.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($sites as $site): ?>
                            <div class="site-card">
                                <h3><?= htmlspecialchars($site['name']) ?></h3>
                                <div class="site-url"><?= htmlspecialchars($site['url']) ?></div>
                                <div class="site-description"><?= htmlspecialchars($site['description'] ?? '') ?></div>
                                
                                <?php
                                $stmt = $pdo->prepare('SELECT COUNT(*) as cnt, AVG(rating) as avg FROM reviews WHERE site_id = ? AND status = "approved"');
                                $stmt->execute([$site['id']]);
                                $stats = $stmt->fetch();
                                ?>
                                <div class="site-stats">
                                    <div class="stat">
                                        <div class="stat-value"><?= number_format($stats['avg'] ?? 0, 1) ?></div>
                                        <div class="stat-label">Note moyenne</div>
                                    </div>
                                    <div class="stat">
                                        <div class="stat-value"><?= $stats['cnt'] ?? 0 ?></div>
                                        <div class="stat-label">Avis</div>
                                    </div>
                                </div>
                                
                                <div class="site-actions">
                                    <button onclick="showReviews(<?= $site['id'] ?>)">Gérer les avis</button>
                                    <button onclick="showWidget(<?= $site['id'] ?>, '<?= htmlspecialchars($site['name']) ?>')">Widget</button>
                                    <button class="delete-btn" onclick="deleteSite(<?= $site['id'] ?>)">Supprimer</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal: Ajouter un site -->
    <div id="modal-addSite" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('addSite')">&times;</span>
            <h2>Ajouter un site</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_site">
                <input type="text" name="name" placeholder="Nom du site" required>
                <input type="url" name="url" placeholder="URL (https://...)" required>
                <textarea name="description" placeholder="Description (optionnel)"></textarea>
                <button type="submit" class="btn-primary">Ajouter</button>
            </form>
        </div>
    </div>

    <!-- Modal: Gérer les avis -->
    <div id="modal-reviews" class="modal">
        <div class="modal-content modal-large">
            <span class="close" onclick="hideModal('reviews')">&times;</span>
            <h2>Gérer les avis</h2>
            <button class="btn-primary" onclick="showModal('addReview')" style="margin-bottom:1rem;">+ Ajouter un avis</button>
            <div class="reviews-list" id="reviewsList"></div>
        </div>
    </div>

    <!-- Modal: Ajouter un avis -->
    <div id="modal-addReview" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('addReview')">&times;</span>
            <h2>Ajouter un avis</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_review">
                <input type="hidden" name="site_id" id="reviewSiteId">
                <input type="text" name="reviewer_name" placeholder="Nom du client" required>
                <input type="email" name="reviewer_email" placeholder="Email (optionnel)">
                <select name="rating" required>
                    <option value="5">★★★★★ - Excellent</option>
                    <option value="4">★★★★☆ - Bon</option>
                    <option value="3">★★★☆☆ - Moyen</option>
                    <option value="2">★★☆☆☆ - Médiocre</option>
                    <option value="1">★☆☆☆☆ - Mauvais</option>
                </select>
                <input type="text" name="title" placeholder="Titre de l'avis">
                <textarea name="content" placeholder="Contenu de l'avis" required></textarea>
                <button type="submit" class="btn-primary">Publier</button>
            </form>
        </div>
    </div>

    <!-- Modal: Widget -->
    <div id="modal-widget" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('widget')">&times;</span>
            <h2>Widget - <span id="widgetSiteName"></span></h2>
            <p>Copiez ce code pour afficher les avis sur votre site:</p>
            <textarea id="embedCode" readonly></textarea>
            <button class="btn-primary" onclick="copyEmbedCode()">Copier le code</button>
        </div>
    </div>

    <script src="js/app.js"></script>
</body>
</html>