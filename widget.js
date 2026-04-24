<?php
require_once 'config.php';

header('Content-Type: application/javascript');

$siteId = $_GET['site'] ?? 0;
?>
(function() {
    var siteId = '<?= intval($siteId) ?>';
    var container = document.getElementById('review-widget-' + siteId);
    if (!container) return;

    var style = document.createElement('style');
    style.textContent = '.rw-container{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;max-width:600px;margin:0 auto;padding:16px;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1)}.rw-header{display:flex;align-items:center;gap:12px;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #eee}.rw-rating{font-size:32px;font-weight:700;color:#00b67a}.rw-stars{display:flex;gap:2px}.rw-star{color:#ddd;font-size:18px}.rw-star.filled{color:#ffc107}.rw-count{color:#666;font-size:14px}.rw-list{display:flex;flex-direction:column;gap:16px}.rw-review{padding:12px;background:#f9f9f9;border-radius:8px}.rw-review-header{margin-bottom:8px}.rw-review-title{font-weight:600;margin-bottom:4px}.rw-review-content{color:#333;line-height:1.5;margin-bottom:8px}.rw-review-author{color:#666;font-size:13px}.rw-loading,.rw-empty,.rw-error{text-align:center;padding:24px;color:#666}.rw-error{color:#e74c3c}';
    document.head.appendChild(style);

    var widget = document.createElement('div');
    widget.className = 'rw-container';
    widget.innerHTML = '<div class="rw-loading">Chargement...</div>';
    container.appendChild(widget);

    var apiUrl = window.location.origin + '/api/reviews.php?site=' + siteId;
    
    fetch(apiUrl)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.length > 0) {
                var avg = (data.reduce(function(s, r) { return s + parseInt(r.rating); }, 0) / data.length).toFixed(1);
                var html = '<div class="rw-header"><div class="rw-rating">' + avg + '</div><div class="rw-stars">';
                for (var i = 1; i <= 5; i++) {
                    html += '<span class="rw-star' + (i <= Math.round(avg) ? ' filled' : '') + '">★</span>';
                }
                html += '</div><div class="rw-count">' + data.length + ' avis</div></div>';
                html += '<div class="rw-list">';
                data.slice(0, 10).forEach(function(r) {
                    html += '<div class="rw-review"><div class="rw-review-header">';
                    for (var j = 1; j <= 5; j++) {
                        html += '<span class="rw-star' + (j <= parseInt(r.rating) ? ' filled' : '') + '">★</span>';
                    }
                    html += '</div>';
                    if (r.title) html += '<div class="rw-review-title">' + r.title + '</div>';
                    html += '<div class="rw-review-content">' + r.content + '</div>';
                    html += '<div class="rw-review-author">' + r.reviewer_name + '</div></div>';
                });
                html += '</div>';
                widget.innerHTML = html;
            } else {
                widget.innerHTML = '<div class="rw-empty">Aucun avis</div>';
            }
        })
        .catch(function() {
            widget.innerHTML = '<div class="rw-error">Erreur</div>';
        });
})();