/**
 * ToutVaMal.fr - API Client v2
 * Auth: cookie HttpOnly + token CSRF (plus de localStorage)
 */

class ToutVaMalAPI {
    constructor(baseUrl = '') {
        this.baseUrl = baseUrl || window.location.origin;
        this.apiPath = '/api/v2';
        this.csrfToken = null; // Chargé après login ou depuis meta tag
    }

    // ========== AUTH ==========

    /**
     * Charge le token CSRF depuis le meta tag (injecté côté serveur)
     * ou depuis l'objet courant
     */
    loadCsrfFromMeta() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) {
            this.csrfToken = meta.content;
        }
    }

    /**
     * Effectue le login : envoie le token au backend qui pose le cookie HttpOnly
     * et retourne le token CSRF
     */
    async login(token) {
        const url = `${this.baseUrl}${this.apiPath}/login.php`;
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ token })
        });

        const data = await response.json();

        if (!response.ok) {
            throw new APIError(data.error || 'Login failed', response.status, data);
        }

        // Stocker le token CSRF (le cookie est posé automatiquement par le serveur)
        if (data.csrf) {
            this.csrfToken = data.csrf;
            // Mettre à jour le meta tag si présent
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) meta.content = data.csrf;
        }

        return data;
    }

    /**
     * Vérifie la session courante (cookie)
     * Retourne { authenticated: bool, csrf: string|null }
     */
    async checkSession() {
        const url = `${this.baseUrl}${this.apiPath}/login.php`;
        try {
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (data.authenticated && data.csrf) {
                this.csrfToken = data.csrf;
            }
            return data;
        } catch (e) {
            return { authenticated: false, csrf: null };
        }
    }

    /**
     * Logout : supprime le cookie côté serveur
     */
    async logout() {
        const url = `${this.baseUrl}${this.apiPath}/login.php`;
        try {
            await fetch(url, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: this._csrfHeaders()
            });
        } catch (e) {
            // Ignorer les erreurs de logout
        }
        this.csrfToken = null;
    }

    /**
     * Vérifie si la session est active (via checkSession)
     */
    async isAuthenticated() {
        const session = await this.checkSession();
        return session.authenticated === true;
    }

    // ========== HTTP ==========

    /**
     * Headers CSRF pour les requêtes d'écriture
     */
    _csrfHeaders() {
        const headers = {};
        if (this.csrfToken) {
            headers['X-CSRF-Token'] = this.csrfToken;
        }
        return headers;
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${this.apiPath}${endpoint}`;
        const method = (options.method || 'GET').toUpperCase();

        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };

        // Ajouter CSRF pour les mutations
        if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
            Object.assign(headers, this._csrfHeaders());
        }

        try {
            const response = await fetch(url, {
                ...options,
                headers,
                credentials: 'same-origin' // Envoie le cookie automatiquement
            });

            const data = await response.json();

            if (!response.ok) {
                throw new APIError(data.error || 'Request failed', response.status, data);
            }

            return data;
        } catch (error) {
            if (error instanceof APIError) throw error;
            throw new APIError(error.message, 0, null);
        }
    }

    get(endpoint, params = {}) {
        const query = new URLSearchParams(params).toString();
        const url = query ? `${endpoint}?${query}` : endpoint;
        return this.request(url, { method: 'GET' });
    }

    post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }

    // ========== ARTICLES ==========

    async getArticles(params = {}) {
        return this.get('/articles.php', params);
    }

    async getArticle(id) {
        return this.get(`/articles.php?id=${id}`);
    }

    async createArticle(data) {
        return this.post('/articles.php', data);
    }

    async updateArticle(id, data) {
        return this.put(`/articles.php?id=${id}`, data);
    }

    async deleteArticle(id) {
        return this.delete(`/articles.php?id=${id}`);
    }

    async publishArticle(id) {
        return this.post(`/articles.php?id=${id}&action=publish`);
    }

    async unpublishArticle(id) {
        return this.post(`/articles.php?id=${id}&action=unpublish`);
    }

    async regenerateStatic(id) {
        return this.post(`/articles.php?id=${id}&action=regenerate`);
    }

    // ========== JOURNALISTS ==========

    async getJournalists(params = {}) {
        return this.get('/journalists.php', params);
    }

    async getJournalist(id) {
        return this.get(`/journalists.php?id=${id}`);
    }

    async createJournalist(data) {
        return this.post('/journalists.php', data);
    }

    async updateJournalist(id, data) {
        return this.put(`/journalists.php?id=${id}`, data);
    }

    async deleteJournalist(id) {
        return this.delete(`/journalists.php?id=${id}`);
    }

    // ========== GENERATION ==========

    async generateArticle(options = {}) {
        return this.post('/generate.php', options);
    }

    async getGenerationLogs(params = {}) {
        return this.get('/generation-logs.php', params);
    }

    async retryGeneration(logId) {
        return this.post(`/generate.php?retry=${logId}`);
    }

    // ========== NEWS ==========

    async getAvailableNews() {
        return this.get('/news.php?action=available');
    }

    async fetchNewsFromRss() {
        return this.post('/news.php?action=fetch');
    }

    // ========== MODELS ==========

    async getModels(type = 'all') {
        return this.get(`/models.php?type=${type}`);
    }

    // ========== IMAGES ==========

    async getArticleImages(articleId) {
        return this.get(`/images.php?action=list&article_id=${articleId}`);
    }

    async regenerateImage(articleId, prompt = null, model = null) {
        return this.post('/images.php?action=regenerate', {
            article_id: articleId,
            prompt: prompt,
            model: model
        });
    }

    async activateImage(imageId) {
        return this.post('/images.php?action=activate', { image_id: imageId });
    }

    async deleteImage(imageId) {
        return this.post('/images.php?action=delete', { image_id: imageId });
    }

    // ========== CONFIG ==========

    async getConfig() {
        return this.get('/settings.php');
    }

    async updateConfig(data) {
        return this.put('/settings.php', data);
    }

    async getPrompts() {
        return this.get('/settings.php?type=prompts');
    }

    async updatePrompts(data) {
        return this.put('/settings.php?type=prompts', data);
    }

    // ========== RSS SOURCES ==========

    async getRssSources() {
        return this.get('/rss-sources.php');
    }

    async createRssSource(data) {
        return this.post('/rss-sources.php', data);
    }

    async updateRssSource(id, data) {
        return this.put(`/rss-sources.php?id=${id}`, data);
    }

    async deleteRssSource(id) {
        return this.delete(`/rss-sources.php?id=${id}`);
    }

    async testRssSource(url) {
        return this.post('/rss-sources.php?action=test', { url });
    }

    async fetchRss() {
        return this.post('/rss-sources.php?action=fetch');
    }

    // ========== NEWSLETTER ==========

    async getNewsletterSubscribers(params = {}) {
        return this.get('/newsletter.php', params);
    }

    async exportNewsletterCsv() {
        return this.get('/newsletter.php?action=export');
    }

    async deleteSubscriber(id) {
        return this.delete(`/newsletter.php?id=${id}`);
    }

    // ========== SEO ==========

    async getSeoSettings() {
        return this.get('/seo.php');
    }

    async updateSeoSettings(data) {
        return this.put('/seo.php', data);
    }

    async getSeoAnalytics(params = {}) {
        return this.get('/seo.php?type=analytics', params);
    }

    async syncGoogleSearchConsole() {
        return this.post('/seo.php?action=sync-gsc');
    }

    async submitUrlToIndex(url) {
        return this.post('/seo.php?action=index', { url });
    }

    async generateSitemap() {
        return this.post('/seo.php?action=sitemap');
    }

    // ========== STATS ==========

    async getDashboardStats() {
        return this.get('/stats.php');
    }

    async getArticleStats(id) {
        return this.get(`/stats.php?article=${id}`);
    }

    async getGenerationStats(period = '30d') {
        return this.get(`/stats.php?type=generations&period=${period}`);
    }

    // ========== SYSTEM ==========

    async runQA() {
        return this.post('/system.php?action=qa');
    }

    async clearCache() {
        return this.post('/system.php?action=clear-cache');
    }

    async getLogs(type = 'app', lines = 100) {
        return this.get(`/system.php?action=logs&type=${type}&lines=${lines}`);
    }

    async triggerDeploy() {
        return this.post('/system.php?action=deploy');
    }
}

// ========== ERROR CLASS ==========

class APIError extends Error {
    constructor(message, status, data) {
        super(message);
        this.name = 'APIError';
        this.status = status;
        this.data = data;
    }

    isUnauthorized() {
        return this.status === 401;
    }

    isForbidden() {
        return this.status === 403;
    }

    isNotFound() {
        return this.status === 404;
    }

    isServerError() {
        return this.status >= 500;
    }
}

// ========== UI HELPERS ==========

class UIHelpers {
    static showToast(message, type = 'info', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <span class="toast-icon">${this.getIcon(type)}</span>
            <span class="toast-message">${message}</span>
        `;
        document.body.appendChild(toast);

        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    static getIcon(type) {
        const icons = {
            success: '&#10003;',
            error: '&#10007;',
            warning: '&#9888;',
            info: '&#8505;'
        };
        return icons[type] || icons.info;
    }

    static showLoader(container) {
        const loader = document.createElement('div');
        loader.className = 'loader';
        loader.innerHTML = '<div class="spinner"></div>';
        container.appendChild(loader);
        return loader;
    }

    static hideLoader(loader) {
        if (loader) loader.remove();
    }

    static confirm(message) {
        return new Promise(resolve => {
            const modal = document.createElement('div');
            modal.className = 'modal-confirm';
            modal.innerHTML = `
                <div class="modal-content">
                    <p>${message}</p>
                    <div class="modal-actions">
                        <button class="btn btn-secondary" data-action="cancel">Annuler</button>
                        <button class="btn btn-danger" data-action="confirm">Confirmer</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            setTimeout(() => modal.classList.add('show'), 10);

            modal.addEventListener('click', (e) => {
                const action = e.target.dataset.action;
                if (action) {
                    modal.classList.remove('show');
                    setTimeout(() => modal.remove(), 300);
                    resolve(action === 'confirm');
                }
            });
        });
    }

    static formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('fr-FR', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    static formatNumber(num) {
        if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
        if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
        return num.toString();
    }

    static truncate(str, length = 50) {
        if (!str) return '';
        return str.length > length ? str.substring(0, length) + '...' : str;
    }

    static escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    static debounce(func, wait = 300) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// ========== FORM HELPERS ==========

class FormHelpers {
    static serialize(form) {
        const data = {};
        const formData = new FormData(form);
        for (const [key, value] of formData.entries()) {
            if (key.endsWith('[]')) {
                const arrayKey = key.slice(0, -2);
                if (!data[arrayKey]) data[arrayKey] = [];
                data[arrayKey].push(value);
            } else {
                data[key] = value;
            }
        }
        return data;
    }

    static populate(form, data) {
        for (const [key, value] of Object.entries(data)) {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                if (input.type === 'checkbox') {
                    input.checked = !!value;
                } else if (input.type === 'select-multiple') {
                    Array.from(input.options).forEach(opt => {
                        opt.selected = Array.isArray(value) && value.includes(opt.value);
                    });
                } else {
                    input.value = value || '';
                }
            }
        }
    }

    static validate(form) {
        const errors = [];
        form.querySelectorAll('[required]').forEach(input => {
            if (!input.value.trim()) {
                errors.push(`${input.name} est requis`);
                input.classList.add('error');
            } else {
                input.classList.remove('error');
            }
        });
        return errors;
    }
}

// ========== GLOBAL INSTANCE ==========

const api = new ToutVaMalAPI();

// Charger le CSRF depuis le meta tag dès que le DOM est prêt
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => api.loadCsrfFromMeta());
} else {
    api.loadCsrfFromMeta();
}

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ToutVaMalAPI, APIError, UIHelpers, FormHelpers };
}
