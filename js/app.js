let currentSiteId = null;

function showNotification(message, type = 'success') {
    const notif = document.getElementById('notification');
    notif.className = 'modal-notification ' + type;
    notif.textContent = message;
    notif.classList.add('show');
    setTimeout(() => notif.classList.remove('show'), 3000);
}

function showConfirm(title, message, callback) {
    const modal = document.getElementById('modal-confirm');
    const confirmBtn = document.getElementById('confirmYes');
    const cancelBtn = document.querySelector('#modal-confirm .btn-cancel');
    
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
    
    confirmBtn.onclick = null;
    cancelBtn.onclick = null;
    
    confirmBtn.onclick = function() {
        modal.classList.remove('active');
        callback();
    };
    
    cancelBtn.onclick = function() {
        modal.classList.remove('active');
    };
    
    modal.classList.add('active');
}

function showTab(tab) {
    document.getElementById('loginCard').style.display = tab === 'login' ? 'block' : 'none';
    document.getElementById('registerCard').style.display = tab === 'register' ? 'block' : 'none';
    document.getElementById('forgotCard').style.display = tab === 'forgot' ? 'block' : 'none';
}

function showModal(id) {
    document.getElementById('modal-' + id).classList.add('active');
}

function hideModal(id) {
    document.getElementById('modal-' + id).classList.remove('active');
}

function showReviews(siteId) {
    currentSiteId = siteId;
    document.getElementById('reviewSiteId').value = siteId;
    
    fetch('api/all-reviews.php?site_id=' + siteId)
        .then(r => r.json())
        .then(data => {
            console.log('Reviews loaded:', data);
            const list = document.getElementById('reviewsList');
            if (data.length === 0) {
                list.innerHTML = '<div class="empty-state"><h3>Aucun avis</h3></div>';
            } else {
                list.innerHTML = data.map(review => `
                    <div class="review-item ${review.status === 'pending' ? 'pending' : ''}">
                        <div class="review-header">
                            <strong>${escapeHtml(review.reviewer_name)}</strong>
                            <span class="review-stars">${getStars(review.rating)}</span>
                        </div>
                        ${review.title ? '<div class="review-title">' + escapeHtml(review.title) + '</div>' : ''}
                        <div class="review-content">${escapeHtml(review.content)}</div>
                        <div class="review-meta">
                            <span class="review-status ${review.status}">${review.status === 'pending' ? 'En attente' : 'Approuvé'}</span>
                        </div>
                        <div class="review-actions">
                            ${review.status === 'pending' ? '<button class="approve-btn" onclick="event.stopPropagation();approveReview(' + review.id + ')">Approuver</button>' : ''}
                            <button class="delete-btn" onclick="event.stopPropagation();deleteReview(' + review.id + ')">Supprimer</button>
                        </div>
                    </div>
                `).join('');
            }
        });
    
    document.getElementById('reviewsList').querySelectorAll('.delete-btn').forEach(btn => {
        btn.onclick = function() { deleteReview(this.dataset.id); };
    });
    document.getElementById('reviewsList').querySelectorAll('.approve-btn').forEach(btn => {
        btn.onclick = function() { approveReview(this.dataset.id); };
    });
    
    showModal('reviews');
}

function getStars(rating) {
    let html = '';
    for (let i = 1; i <= 5; i++) {
        html += '<span style="color:' + (i <= rating ? 'var(--star)' : '#ddd') + '">★</span>';
    }
    return html;
}

function deleteReview(id) {
    if (!id) {
        alert('ID invalide');
        return;
    }
    if (!confirm('Supprimer cet avis ?')) return;
    
    console.log('Deleting review:', id);
    const formData = new FormData();
    formData.append('action', 'delete_review');
    formData.append('review_id', id);
    
    fetch('index.php', { method: 'POST', body: formData })
        .then(response => {
            console.log('Delete response:', response.status);
            showNotification('Avis supprimé');
            showReviews(currentSiteId);
        })
        .catch(error => {
            console.error('Delete error:', error);
            showNotification('Erreur', 'error');
        });
}

function approveReview(id) {
    const formData = new FormData();
    formData.append('action', 'approve_review');
    formData.append('review_id', parseInt(id));
    
    fetch('index.php', { method: 'POST', body: formData })
        .then(() => {
            showNotification('Avis approuvé');
            showReviews(currentSiteId);
        });
}

function deleteSite(id) {
    if (!confirm('Supprimer ce site et tous ses avis ?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_site');
    formData.append('site_id', id);
    
    fetch('index.php', { method: 'POST', body: formData })
        .then(() => {
            showNotification('Site supprimé');
            window.location.reload();
        });
}

function showWidget(siteId, siteName, reviewsData) {
    document.getElementById('widgetSiteName').textContent = siteName;
    document.getElementById('widgetSiteId').value = siteId;
    
    fetch('api/widget-settings.php?site_id=' + siteId)
        .then(r => r.json())
        .then(settings => {
            document.getElementById('autoApprove').checked = settings.auto_approve == 1;
            updateWidgetEmbed(siteId, reviewsData);
            showModal('widget');
        })
        .catch(() => {
            updateWidgetEmbed(siteId, reviewsData);
            showModal('widget');
        });
}

function updateWidget() {
    const form = document.getElementById('widgetForm');
    const formData = new FormData(form);
    
    fetch('index.php', { method: 'POST', body: formData })
        .then(r => r.text())
        .then(() => {
            showNotification('Paramètres enregistrés');
        });
}

function updateWidgetEmbed(siteId, reviewsData) {
    const origin = window.location.origin;
    const embedCode = '<div id="review-widget-' + siteId + '"></div>\n<script src="' + origin + '/widget.js?site=' + siteId + '" async></script>';
    document.getElementById('embedCode').value = embedCode;
    
    renderWidgetPreview(siteId, reviewsData);
}

function renderWidgetPreview(siteId, reviews) {
    const preview = document.getElementById('widgetPreviewContent');
    
    if (!reviews || reviews.length === 0) {
        preview.innerHTML = '<div style="text-align:center;color:#666;padding:2rem;">Aucun avis à afficher</div>';
        return;
    }
    
    const avg = (reviews.reduce((s, r) => s + parseInt(r.rating), 0) / reviews.length).toFixed(1);
    
    let html = '<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #eee;">';
    html += '<div style="font-size:28px;font-weight:bold;color:#00b67a;">' + avg + '</div>';
    html += '<div style="display:flex;">';
    for (let i = 1; i <= 5; i++) {
        html += '<span style="color:' + (i <= Math.round(avg) ? '#ffc107' : '#ddd') + '">★</span>';
    }
    html += '</div>';
    html += '<div style="color:#666;font-size:14px;">' + reviews.length + ' avis</div>';
    html += '</div>';
    
    html += '<div style="display:flex;flex-direction:column;gap:12px;">';
    reviews.slice(0, 3).forEach(function(r) {
        html += '<div style="padding:10px;background:#f9f9f9;border-radius:6px;">';
        html += '<div style="margin-bottom:6px;">';
        for (let j = 1; j <= 5; j++) {
            html += '<span style="color:' + (j <= parseInt(r.rating) ? '#ffc107' : '#ddd') + '">★</span>';
        }
        html += '</div>';
        if (r.title) html += '<div style="font-weight:600;margin-bottom:4px;">' + escapeHtml(r.title) + '</div>';
        html += '<div style="color:#333;line-height:1.4;font-size:14px;">' + escapeHtml(r.content) + '</div>';
        html += '<div style="color:#666;font-size:13px;margin-top:6px;">' + escapeHtml(r.reviewer_name) + '</div>';
        html += '</div>';
    });
    html += '</div>';
    
    preview.innerHTML = html;
}

function copyEmbedCode() {
    const textarea = document.getElementById('embedCode');
    textarea.select();
    document.execCommand('copy');
    showNotification('Code copié dans le presse-papiers');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.querySelectorAll('.modal, .modal-confirm').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

document.getElementById('notification').addEventListener('click', function() {
    this.classList.remove('show');
});