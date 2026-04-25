<?php
header('Content-Type: application/javascript');

$siteId = $_GET['site'] ?? 0;
$action = $_GET['action'] ?? 'display';
$baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
?>
(function() {
    var siteId = '<?= intval($siteId) ?>';
    var baseUrl = '<?= $baseUrl ?>';
    var container = document.getElementById('review-widget-' + siteId);
    if (!container) return;

    var style = document.createElement('style');
    style.textContent = '.rw-container{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;max-width:600px;margin:0 auto;padding:16px;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1)}.rw-header{display:flex;align-items:center;gap:12px;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #eee}.rw-rating{font-size:32px;font-weight:700;color:#00b67a}.rw-stars{display:flex;gap:2px;cursor:pointer}.rw-star{color:#ddd;font-size:20px;transition:color .2s}.rw-star.active,.rw-star:hover{color:#ffc107}.rw-count{color:#666;font-size:14px}.rw-list{display:flex;flex-direction:column;gap:16px}.rw-review{padding:12px;background:#f9f9f9;border-radius:8px}.rw-review-header{margin-bottom:8px}.rw-review-title{font-weight:600;margin-bottom:4px}.rw-review-content{color:#333;line-height:1.5;margin-bottom:8px}.rw-review-author{color:#666;font-size:13px}.rw-loading,.rw-empty,.rw-error,.rw-success{text-align:center;padding:24px;color:#666}.rw-error{color:#e74c3c}.rw-success{color:#00b67a}.rw-form{display:flex;flex-direction:column;gap:12px;margin-bottom:16px;padding:16px;background:#f5f7fa;border-radius:8px}.rw-form input,.rw-form textarea{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px}.rw-form textarea{min-height:80px;resize:vertical}.rw-form button,.rw-add-review{background:#00b67a;color:#fff;border:none;padding:12px 24px;border-radius:8px;cursor:pointer;font-size:14px}.rw-form button:hover,.rw-add-review:hover{background:#009963}.rw-add-review{margin-bottom:16px;display:block;text-align:center;text-decoration:none}';
    document.head.appendChild(style);

    var widget = document.createElement('div');
    widget.className = 'rw-container';
    widget.innerHTML = '<div class="rw-loading">Chargement...</div>';
    container.appendChild(widget);

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getStars(rating, interactive) {
        var html = '';
        for (var i = 1; i <= 5; i++) {
            var cls = 'rw-star' + (i <= rating ? ' active' : '');
            if (interactive) {
                cls += '" onclick="setRating(' + i + ')" onmouseover="hoverRating(' + i + ')" onmouseout="resetRating()"';
            }
            html += '<span class="' + cls + '">★</span>';
        }
        return html;
    }

    window.hoverRating = function(rating) {
        var stars = widget.querySelectorAll('.rw-star');
        stars.forEach(function(s, i) {
            s.classList.toggle('active', i < rating);
        });
    };

    window.resetRating = function() {
        var current = widget.dataset.selectedRating || 0;
        var stars = widget.querySelectorAll('.rw-star');
        stars.forEach(function(s, i) {
            s.classList.toggle('active', i < current);
        });
    };

    window.setRating = function(rating) {
        widget.dataset.selectedRating = rating;
        window.hoverRating(rating);
    };

    function showAddForm() {
        widget.innerHTML = '<a href="#" class="rw-add-review" onclick="showReviewsList();return false;">← Retour aux avis</a>' +
            '<form class="rw-form" onsubmit="submitReview(event)">' +
            '<input type="text" name="reviewer_name" placeholder="Votre nom" required>' +
            '<input type="email" name="reviewer_email" placeholder="Votre email (optionnel)">' +
            '<div class="rw-stars" id="interactiveStars">' + getStars(0, true) + '</div>' +
            '<input type="hidden" name="rating" id="ratingValue" value="5">' +
            '<input type="text" name="title" placeholder="Titre (optionnel)">' +
            '<textarea name="content" placeholder="Votre avis" required></textarea>' +
            '<button type="submit">Soumettre mon avis</button>' +
            '</form>';
    }

    window.submitReview = function(e) {
        e.preventDefault();
        var form = e.target;
        var rating = widget.dataset.selectedRating || 5;
        
        var formData = new FormData();
        formData.append('action', 'public_review');
        formData.append('site_id', siteId);
        formData.append('reviewer_name', form.reviewer_name.value);
        formData.append('reviewer_email', form.reviewer_email.value);
        formData.append('rating', rating);
        formData.append('title', form.title.value);
        formData.append('content', form.content.value);

        fetch(baseUrl + '/api/public-review.php', {
            method: 'POST',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                widget.innerHTML = '<div class="rw-success">Merci ! Votre avis a été soumis et sera publié après modération.</div>';
            } else {
                widget.innerHTML = '<div class="rw-error">Erreur: ' + (data.error || 'Impossible de soumettre l\'avis') + '</div>';
            }
        })
        .catch(function() {
            widget.innerHTML = '<div class="rw-error">Erreur de connexion</div>';
        });
    };

    window.showReviewsList = function() {
        loadReviews();
    };

    function loadReviews() {
        fetch(baseUrl + '/api/reviews.php?site=' + siteId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.length > 0) {
                    var avg = (data.reduce(function(s, r) { return s + parseInt(r.rating); }, 0) / data.length).toFixed(1);
                    var html = '<a href="#" class="rw-add-review" onclick="showAddForm();return false;">+ Ajouter un avis</a>';
                    html += '<div class="rw-header"><div class="rw-rating">' + avg + '</div><div class="rw-stars">';
                    for (var i = 1; i <= 5; i++) {
                        html += '<span class="rw-star' + (i <= Math.round(avg) ? ' active' : '') + '">★</span>';
                    }
                    html += '</div><div class="rw-count">' + data.length + ' avis</div></div>';
                    html += '<div class="rw-list">';
                    data.slice(0, 10).forEach(function(r) {
                        html += '<div class="rw-review"><div class="rw-review-header">';
                        for (var j = 1; j <= 5; j++) {
                            html += '<span class="rw-star' + (j <= parseInt(r.rating) ? ' active' : '') + '">★</span>';
                        }
                        html += '</div>';
                        if (r.title) html += '<div class="rw-review-title">' + escapeHtml(r.title) + '</div>';
                        html += '<div class="rw-review-content">' + escapeHtml(r.content) + '</div>';
                        html += '<div class="rw-review-author">' + escapeHtml(r.reviewer_name) + '</div></div>';
                    });
                    html += '</div>';
                    widget.innerHTML = html;
                } else {
                    var html = '<a href="#" class="rw-add-review" onclick="showAddForm();return false;">+ Ajouter le premier avis</a>';
                    html += '<div class="rw-empty">Aucun avis pour le moment</div>';
                    widget.innerHTML = html;
                }
            })
            .catch(function() {
                widget.innerHTML = '<div class="rw-error">Erreur de chargement</div>';
            });
    }

    loadReviews();
})();