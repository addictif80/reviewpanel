let currentSiteId = null;

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
    
    fetch('api/reviews.php?site_id=' + siteId)
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('reviewsList');
            if (data.length === 0) {
                list.innerHTML = '<div class="empty-state"><h3>Aucun avis</h3></div>';
            } else {
                list.innerHTML = data.map(review => `
                    <div class="review-item">
                        <div class="review-header">
                            <strong>${escapeHtml(review.reviewer_name)}</strong>
                            <span class="review-stars">${getStars(review.rating)}</span>
                        </div>
                        ${review.title ? '<div class="review-title">' + escapeHtml(review.title) + '</div>' : ''}
                        <div class="review-content">${escapeHtml(review.content)}</div>
                        <div style="margin-top:0.5rem;">
                            <button class="delete-btn" onclick="deleteReview(${review.id})">Supprimer</button>
                        </div>
                    </div>
                `).join('');
            }
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
    if (!confirm('Supprimer cet avis ?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_review');
    formData.append('review_id', id);
    
    fetch('index.php', { method: 'POST', body: formData })
        .then(() => showReviews(currentSiteId));
}

function deleteSite(id) {
    if (!confirm('Supprimer ce site et tous ses avis ?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_site');
    formData.append('site_id', id);
    
    fetch('index.php', { method: 'POST', body: formData })
        .then(() => window.location.reload());
}

function showWidget(siteId, siteName) {
    document.getElementById('widgetSiteName').textContent = siteName;
    
    const formData = new FormData();
    formData.append('action', 'create_widget');
    formData.append('site_id', siteId);
    
    fetch('index.php', { method: 'POST', body: formData })
        .then(r => r.text())
        .then(() => {
            const embedCode = '<div id="review-widget-' + siteId + '"></div>\n<script src="' + window.location.origin + '/widget.js?site=' + siteId + '" async></script>';
            document.getElementById('embedCode').value = embedCode;
            showModal('widget');
        });
}

function copyEmbedCode() {
    const textarea = document.getElementById('embedCode');
    textarea.select();
    document.execCommand('copy');
    alert('Code copié !');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});