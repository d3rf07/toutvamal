/**
 * ToutVaMal.fr - Admin Application v2
 */

class AdminApp {
    constructor() {
        this.api = new ToutVaMalAPI();
        this.currentSection = 'dashboard';
        this.categories = {};
        this.journalists = [];

        this.init();
    }

    // ========== INITIALIZATION ==========

    init() {
        // Check for stored token
        const token = localStorage.getItem('tvm_api_token');
        if (token) {
            this.api.setToken(token);
            this.validateToken();
        } else {
            this.showLogin();
        }

        this.bindEvents();
    }

    bindEvents() {
        // Login form
        document.getElementById('login-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.login();
        });

        // Logout
        document.getElementById('logout-btn')?.addEventListener('click', () => this.logout());

        // Navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const section = item.dataset.section;
                this.navigateTo(section);
            });
        });

        // Tabs
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const tabId = tab.dataset.tab;
                this.switchTab(tabId, tab);
            });
        });

        // Filters
        document.getElementById('filter-category')?.addEventListener('change', () => this.loadArticles());
        document.getElementById('filter-status')?.addEventListener('change', () => this.loadArticles());
        document.getElementById('logs-filter')?.addEventListener('change', () => this.loadLogs());

        // Generate form
        document.getElementById('generate-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.generateArticle();
        });

        // Config forms
        document.getElementById('config-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveConfig();
        });

        document.getElementById('prompts-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.savePrompts();
        });

        document.getElementById('seo-settings-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveSeoSettings();
        });

        // Modal close on backdrop click
        document.getElementById('modal')?.addEventListener('click', (e) => {
            if (e.target.id === 'modal') this.closeModal();
        });

        // Hash navigation
        window.addEventListener('hashchange', () => {
            const section = window.location.hash.slice(1) || 'dashboard';
            this.navigateTo(section);
        });
    }

    // ========== AUTH ==========

    async login() {
        const token = document.getElementById('api-token').value;
        const errorEl = document.getElementById('login-error');

        try {
            this.api.setToken(token);
            const response = await this.api.getDashboardStats();
            this.showAdmin();
            this.loadDashboard();
        } catch (error) {
            errorEl.textContent = 'Token invalide';
            this.api.clearToken();
        }
    }

    async validateToken() {
        try {
            await this.api.getDashboardStats();
            this.showAdmin();
            this.loadInitialData();
        } catch (error) {
            this.api.clearToken();
            this.showLogin();
        }
    }

    logout() {
        this.api.clearToken();
        this.showLogin();
    }

    showLogin() {
        document.getElementById('login-screen').classList.remove('hidden');
        document.getElementById('admin-app').classList.add('hidden');
    }

    showAdmin() {
        document.getElementById('login-screen').classList.add('hidden');
        document.getElementById('admin-app').classList.remove('hidden');
        const section = window.location.hash.slice(1) || 'dashboard';
        this.navigateTo(section);
    }

    // ========== NAVIGATION ==========

    navigateTo(section) {
        this.currentSection = section;

        // Update nav
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.toggle('active', item.dataset.section === section);
        });

        // Update sections
        document.querySelectorAll('.section').forEach(sec => {
            sec.classList.toggle('active', sec.id === `section-${section}`);
        });

        // Load section data
        this.loadSectionData(section);

        // Update hash
        window.location.hash = section;
    }

    loadSectionData(section) {
        switch (section) {
            case 'dashboard':
                this.loadDashboard();
                break;
            case 'articles':
                this.loadArticles();
                break;
            case 'journalists':
                this.loadJournalists();
                break;
            case 'rss':
                this.loadRssSources();
                break;
            case 'newsletter':
                this.loadNewsletter();
                break;
            case 'seo':
                this.loadSeo();
                break;
            case 'config':
                this.loadConfig();
                break;
            case 'logs':
                this.loadLogs();
                break;
            case 'generate':
                this.loadGenerateForm();
                break;
        }
    }

    loadInitialData() {
        this.loadCategories();
        this.loadJournalistsList();
        this.loadDashboard();
    }

    // ========== DASHBOARD ==========

    async loadDashboard() {
        try {
            const data = await this.api.getDashboardStats();
            this.renderDashboardStats(data);
            this.renderRecentArticles(data.recent_articles || []);
            this.renderRecentGenerations(data.recent_generations || []);
        } catch (error) {
            this.toast('Erreur chargement dashboard', 'error');
        }
    }

    refreshDashboard() {
        this.loadDashboard();
        this.toast('Dashboard rafraîchi', 'success');
    }

    renderDashboardStats(data) {
        const grid = document.getElementById('stats-grid');
        if (!grid) return;

        const articles = data.articles || {};
        const newsletter = data.newsletter || {};
        const generations = data.generations || {};
        const costs = data.costs_30d || {};

        grid.innerHTML = `
            <div class="stat-card">
                <div class="stat-label">Articles publiés</div>
                <div class="stat-value">${articles.published || 0}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Brouillons</div>
                <div class="stat-value">${articles.draft || 0}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Newsletter</div>
                <div class="stat-value">${newsletter.confirmed || 0}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Générations aujourd'hui</div>
                <div class="stat-value">${generations.today || 0}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Coût 30j</div>
                <div class="stat-value">$${(costs.total_cost || 0).toFixed(2)}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Tokens 30j</div>
                <div class="stat-value">${UIHelpers.formatNumber(costs.total_tokens || 0)}</div>
            </div>
        `;
    }

    renderRecentArticles(articles) {
        const container = document.getElementById('recent-articles');
        if (!container) return;

        if (articles.length === 0) {
            container.innerHTML = '<p class="empty-state">Aucun article récent</p>';
            return;
        }

        container.innerHTML = articles.map(a => `
            <div style="padding: 0.75rem 0; border-bottom: 1px solid var(--border-color);">
                <div style="font-weight: 500;">${UIHelpers.truncate(a.title, 40)}</div>
                <div style="font-size: 0.75rem; color: var(--text-secondary);">
                    ${a.journalist_name || 'N/A'} · ${UIHelpers.formatDate(a.published_at)}
                </div>
            </div>
        `).join('');
    }

    renderRecentGenerations(generations) {
        const container = document.getElementById('recent-generations');
        if (!container) return;

        if (generations.length === 0) {
            container.innerHTML = '<p class="empty-state">Aucune génération récente</p>';
            return;
        }

        container.innerHTML = generations.map(g => `
            <div style="padding: 0.75rem 0; border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span>${UIHelpers.truncate(g.source_title || g.article_title || 'N/A', 30)}</span>
                    <span class="status-badge ${g.status}">${g.status}</span>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-secondary);">
                    ${UIHelpers.formatDate(g.created_at)}
                </div>
            </div>
        `).join('');
    }

    // ========== ARTICLES ==========

    async loadArticles() {
        const category = document.getElementById('filter-category')?.value || '';
        const status = document.getElementById('filter-status')?.value || '';

        try {
            const data = await this.api.getArticles({ category, status, limit: 100 });
            this.renderArticlesTable(data.articles || []);
        } catch (error) {
            this.toast('Erreur chargement articles', 'error');
        }
    }

    renderArticlesTable(articles) {
        const tbody = document.querySelector('#articles-table tbody');
        if (!tbody) return;

        if (articles.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="empty-state">Aucun article</td></tr>';
            return;
        }

        tbody.innerHTML = articles.map(a => `
            <tr>
                <td>
                    <a href="${window.location.origin}/articles/${a.slug}.html" target="_blank" style="color: var(--text-primary);">
                        ${UIHelpers.truncate(a.title, 50)}
                    </a>
                </td>
                <td>${this.categories[a.category] || a.category}</td>
                <td>${a.journalist_name || '-'}</td>
                <td><span class="status-badge ${a.status}">${a.status}</span></td>
                <td>${UIHelpers.formatDate(a.published_at)}</td>
                <td class="action-buttons">
                    <button class="btn btn-small btn-secondary" onclick="app.editArticle(${a.id})">Éditer</button>
                    ${a.status === 'draft' ?
                        `<button class="btn btn-small btn-primary" onclick="app.publishArticle(${a.id})">Publier</button>` :
                        `<button class="btn btn-small btn-secondary" onclick="app.unpublishArticle(${a.id})">Dépublier</button>`
                    }
                    <button class="btn btn-small btn-danger" onclick="app.deleteArticle(${a.id})">&#128465;</button>
                </td>
            </tr>
        `).join('');
    }

    async publishArticle(id) {
        try {
            await this.api.publishArticle(id);
            this.toast('Article publié', 'success');
            this.loadArticles();
        } catch (error) {
            this.toast('Erreur publication', 'error');
        }
    }

    async unpublishArticle(id) {
        try {
            await this.api.unpublishArticle(id);
            this.toast('Article dépublié', 'success');
            this.loadArticles();
        } catch (error) {
            this.toast('Erreur dépublication', 'error');
        }
    }

    async deleteArticle(id) {
        if (!confirm('Supprimer cet article ?')) return;

        try {
            await this.api.deleteArticle(id);
            this.toast('Article supprimé', 'success');
            this.loadArticles();
        } catch (error) {
            this.toast('Erreur suppression', 'error');
        }
    }

    showArticleModal(article = null) {
        const title = article ? 'Modifier article' : 'Nouvel article';
        const body = `
            <form id="article-form">
                <div class="form-group">
                    <label>Titre</label>
                    <input type="text" name="title" value="${article?.title || ''}" required>
                </div>
                <div class="form-group">
                    <label>Slug</label>
                    <input type="text" name="slug" value="${article?.slug || ''}" placeholder="auto-généré">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Catégorie</label>
                        <select name="category">
                            ${Object.entries(this.categories).map(([k, v]) =>
                                `<option value="${k}" ${article?.category === k ? 'selected' : ''}>${v}</option>`
                            ).join('')}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Journaliste</label>
                        <select name="journalist_id">
                            <option value="">Aucun</option>
                            ${this.journalists.map(j =>
                                `<option value="${j.id}" ${article?.journalist_id == j.id ? 'selected' : ''}>${j.name}</option>`
                            ).join('')}
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Extrait</label>
                    <textarea name="excerpt" rows="3">${article?.excerpt || ''}</textarea>
                </div>
                <div class="form-group">
                    <label>Contenu HTML</label>
                    <textarea name="content" rows="10" class="code-textarea">${article?.content || ''}</textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Meta Title</label>
                        <input type="text" name="meta_title" value="${article?.meta_title || ''}" maxlength="60">
                    </div>
                    <div class="form-group">
                        <label>Meta Description</label>
                        <input type="text" name="meta_description" value="${article?.meta_description || ''}" maxlength="160">
                    </div>
                </div>
                <input type="hidden" name="id" value="${article?.id || ''}">
                <button type="submit" class="btn btn-primary">Sauvegarder</button>
            </form>
        `;

        this.showModal(title, body);

        document.getElementById('article-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.saveArticle(FormHelpers.serialize(e.target));
        });
    }

    async editArticle(id) {
        try {
            const article = await this.api.getArticle(id);
            this.showArticleModal(article);
        } catch (error) {
            this.toast('Erreur chargement article', 'error');
        }
    }

    async saveArticle(data) {
        try {
            if (data.id) {
                await this.api.updateArticle(data.id, data);
                this.toast('Article mis à jour', 'success');
            } else {
                await this.api.createArticle(data);
                this.toast('Article créé', 'success');
            }
            this.closeModal();
            this.loadArticles();
        } catch (error) {
            this.toast('Erreur sauvegarde', 'error');
        }
    }

    // ========== GENERATE ==========

    async loadGenerateForm() {
        await this.loadJournalistsList();
        this.populateJournalistSelect('generate-journalist');
        this.populateCategorySelect('generate-category');
    }

    async generateArticle() {
        const form = document.getElementById('generate-form');
        const resultDiv = document.getElementById('generate-result');
        const data = FormHelpers.serialize(form);

        // Convert checkboxes
        data.auto_publish = !!data.auto_publish;
        data.generate_image = !!data.generate_image;

        resultDiv.classList.remove('hidden', 'success', 'error');
        resultDiv.innerHTML = '<div class="loader"><div class="spinner"></div></div><p>Génération en cours...</p>';

        try {
            const result = await this.api.generateArticle(data);
            resultDiv.classList.add('success');
            resultDiv.innerHTML = `
                <h4>&#10004; Article généré avec succès !</h4>
                <p><strong>${result.article.title}</strong></p>
                <p>Journaliste: ${result.generation.journalist} | Temps: ${result.generation.time}s</p>
                <div style="margin-top: 1rem;">
                    <a href="/articles/${result.article.slug}.html" target="_blank" class="btn btn-secondary">Voir l'article</a>
                    <button class="btn btn-primary" onclick="app.editArticle(${result.article.id})">Modifier</button>
                </div>
            `;
            form.reset();
        } catch (error) {
            resultDiv.classList.add('error');
            resultDiv.innerHTML = `
                <h4>&#10007; Erreur de génération</h4>
                <p>${error.message}</p>
            `;
        }
    }

    // ========== JOURNALISTS ==========

    async loadJournalists() {
        try {
            const data = await this.api.getJournalists();
            this.journalists = data.journalists || [];
            this.renderJournalistsGrid(this.journalists);
        } catch (error) {
            this.toast('Erreur chargement journalistes', 'error');
        }
    }

    async loadJournalistsList() {
        if (this.journalists.length === 0) {
            try {
                const data = await this.api.getJournalists();
                this.journalists = data.journalists || [];
            } catch (error) {
                console.error('Failed to load journalists');
            }
        }
    }

    renderJournalistsGrid(journalists) {
        const grid = document.getElementById('journalists-grid');
        if (!grid) return;

        if (journalists.length === 0) {
            grid.innerHTML = '<p class="empty-state">Aucun journaliste</p>';
            return;
        }

        grid.innerHTML = journalists.map(j => `
            <div class="journalist-card">
                <img src="/equipe/${j.photo_path || 'default.webp'}" alt="${j.name}" onerror="this.src='/equipe/default.webp'">
                <div class="info">
                    <div class="name">${j.name}</div>
                    <div class="role">${j.role}</div>
                    <div class="stats">${j.articles_count || 0} articles · ${j.active ? 'Actif' : 'Inactif'}</div>
                    <div class="actions">
                        <button class="btn btn-small btn-secondary" onclick="app.editJournalist(${j.id})">Éditer</button>
                        <button class="btn btn-small btn-danger" onclick="app.deleteJournalist(${j.id})">&#128465;</button>
                    </div>
                </div>
            </div>
        `).join('');
    }

    showJournalistModal(journalist = null) {
        const title = journalist ? 'Modifier journaliste' : 'Nouveau journaliste';
        const body = `
            <form id="journalist-form">
                <div class="form-group">
                    <label>Nom</label>
                    <input type="text" name="name" value="${journalist?.name || ''}" required>
                </div>
                <div class="form-group">
                    <label>Rôle</label>
                    <input type="text" name="role" value="${journalist?.role || 'Journaliste'}">
                </div>
                <div class="form-group">
                    <label>Style d'écriture</label>
                    <textarea name="style" rows="3">${journalist?.style || ''}</textarea>
                </div>
                <div class="form-group">
                    <label>Bio</label>
                    <textarea name="bio" rows="3">${journalist?.bio || ''}</textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Photo (nom fichier)</label>
                        <input type="text" name="photo_path" value="${journalist?.photo_path || ''}" placeholder="nom.webp">
                    </div>
                    <div class="form-group">
                        <label>Badge</label>
                        <input type="text" name="badge" value="${journalist?.badge || ''}">
                    </div>
                </div>
                <div class="form-group checkbox-group">
                    <label><input type="checkbox" name="active" ${journalist?.active !== 0 ? 'checked' : ''}> Actif</label>
                </div>
                <input type="hidden" name="id" value="${journalist?.id || ''}">
                <button type="submit" class="btn btn-primary">Sauvegarder</button>
            </form>
        `;

        this.showModal(title, body);

        document.getElementById('journalist-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = FormHelpers.serialize(e.target);
            data.active = data.active ? 1 : 0;
            await this.saveJournalist(data);
        });
    }

    async editJournalist(id) {
        const journalist = this.journalists.find(j => j.id === id);
        if (journalist) {
            this.showJournalistModal(journalist);
        }
    }

    async saveJournalist(data) {
        try {
            if (data.id) {
                await this.api.updateJournalist(data.id, data);
                this.toast('Journaliste mis à jour', 'success');
            } else {
                await this.api.createJournalist(data);
                this.toast('Journaliste créé', 'success');
            }
            this.closeModal();
            this.loadJournalists();
        } catch (error) {
            this.toast('Erreur sauvegarde', 'error');
        }
    }

    async deleteJournalist(id) {
        if (!confirm('Supprimer ce journaliste ?')) return;

        try {
            await this.api.deleteJournalist(id);
            this.toast('Journaliste supprimé', 'success');
            this.loadJournalists();
        } catch (error) {
            this.toast(error.message || 'Erreur suppression', 'error');
        }
    }

    // ========== RSS SOURCES ==========

    async loadRssSources() {
        try {
            const data = await this.api.getRssSources();
            this.renderRssTable(data.sources || []);
        } catch (error) {
            this.toast('Erreur chargement RSS', 'error');
        }
    }

    renderRssTable(sources) {
        const tbody = document.querySelector('#rss-table tbody');
        if (!tbody) return;

        if (sources.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="empty-state">Aucune source RSS</td></tr>';
            return;
        }

        tbody.innerHTML = sources.map(s => `
            <tr>
                <td>${s.name}</td>
                <td><a href="${s.url}" target="_blank" style="color: var(--text-secondary);">${UIHelpers.truncate(s.url, 40)}</a></td>
                <td>${this.categories[s.category] || s.category || '-'}</td>
                <td>${s.last_fetch ? UIHelpers.formatDate(s.last_fetch) : 'Jamais'}</td>
                <td><span class="status-badge ${s.active ? 'success' : 'error'}">${s.active ? 'Actif' : 'Inactif'}</span></td>
                <td class="action-buttons">
                    <button class="btn btn-small btn-secondary" onclick="app.testRss('${s.url}')">Test</button>
                    <button class="btn btn-small btn-secondary" onclick="app.editRss(${s.id})">Éditer</button>
                    <button class="btn btn-small btn-danger" onclick="app.deleteRss(${s.id})">&#128465;</button>
                </td>
            </tr>
        `).join('');
    }

    showRssModal(source = null) {
        const title = source ? 'Modifier source RSS' : 'Nouvelle source RSS';
        const body = `
            <form id="rss-form">
                <div class="form-group">
                    <label>Nom</label>
                    <input type="text" name="name" value="${source?.name || ''}" required>
                </div>
                <div class="form-group">
                    <label>URL du flux</label>
                    <input type="url" name="url" value="${source?.url || ''}" required>
                </div>
                <div class="form-group">
                    <label>Catégorie par défaut</label>
                    <select name="category">
                        <option value="">Auto-détection</option>
                        ${Object.entries(this.categories).map(([k, v]) =>
                            `<option value="${k}" ${source?.category === k ? 'selected' : ''}>${v}</option>`
                        ).join('')}
                    </select>
                </div>
                <div class="form-group checkbox-group">
                    <label><input type="checkbox" name="active" ${source?.active !== 0 ? 'checked' : ''}> Actif</label>
                </div>
                <input type="hidden" name="id" value="${source?.id || ''}">
                <button type="submit" class="btn btn-primary">Sauvegarder</button>
            </form>
        `;

        this.showModal(title, body);

        document.getElementById('rss-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = FormHelpers.serialize(e.target);
            data.active = data.active ? 1 : 0;
            await this.saveRss(data);
        });
    }

    async editRss(id) {
        try {
            const source = await this.api.get(`/rss-sources.php?id=${id}`);
            this.showRssModal(source);
        } catch (error) {
            this.toast('Erreur', 'error');
        }
    }

    async saveRss(data) {
        try {
            if (data.id) {
                await this.api.updateRssSource(data.id, data);
                this.toast('Source mise à jour', 'success');
            } else {
                await this.api.createRssSource(data);
                this.toast('Source créée', 'success');
            }
            this.closeModal();
            this.loadRssSources();
        } catch (error) {
            this.toast(error.message || 'Erreur', 'error');
        }
    }

    async deleteRss(id) {
        if (!confirm('Supprimer cette source ?')) return;

        try {
            await this.api.deleteRssSource(id);
            this.toast('Source supprimée', 'success');
            this.loadRssSources();
        } catch (error) {
            this.toast('Erreur suppression', 'error');
        }
    }

    async testRss(url) {
        try {
            const result = await this.api.testRssSource(url);
            this.toast(`RSS valide: ${result.items_count} items trouvés`, 'success');
        } catch (error) {
            this.toast(error.message || 'RSS invalide', 'error');
        }
    }

    async fetchAllRss() {
        try {
            const result = await this.api.fetchRss();
            this.toast('Fetch RSS terminé', 'success');
            this.loadRssSources();
        } catch (error) {
            this.toast('Erreur fetch', 'error');
        }
    }

    // ========== NEWSLETTER ==========

    async loadNewsletter() {
        try {
            const data = await this.api.getNewsletterSubscribers();
            this.renderNewsletterStats(data.stats);
            this.renderNewsletterTable(data.subscribers || []);
        } catch (error) {
            this.toast('Erreur chargement newsletter', 'error');
        }
    }

    renderNewsletterStats(stats) {
        const container = document.getElementById('newsletter-stats');
        if (!container || !stats) return;

        container.innerHTML = `
            <div class="stat"><div class="stat-value">${stats.total}</div><div class="stat-label">Total</div></div>
            <div class="stat"><div class="stat-value">${stats.confirmed}</div><div class="stat-label">Confirmés</div></div>
            <div class="stat"><div class="stat-value">${stats.unsubscribed}</div><div class="stat-label">Désinscrits</div></div>
        `;
    }

    renderNewsletterTable(subscribers) {
        const tbody = document.querySelector('#newsletter-table tbody');
        if (!tbody) return;

        if (subscribers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Aucun abonné</td></tr>';
            return;
        }

        tbody.innerHTML = subscribers.map(s => `
            <tr>
                <td>${s.email}</td>
                <td>${s.source || 'website'}</td>
                <td>${UIHelpers.formatDate(s.subscribed_at)}</td>
                <td><span class="status-badge ${s.confirmed_at ? 'success' : 'pending'}">${s.confirmed_at ? 'Oui' : 'Non'}</span></td>
                <td class="action-buttons">
                    <button class="btn btn-small btn-danger" onclick="app.deleteSubscriber(${s.id})">&#128465;</button>
                </td>
            </tr>
        `).join('');
    }

    async deleteSubscriber(id) {
        if (!confirm('Supprimer cet abonné ?')) return;

        try {
            await this.api.deleteSubscriber(id);
            this.toast('Abonné supprimé', 'success');
            this.loadNewsletter();
        } catch (error) {
            this.toast('Erreur suppression', 'error');
        }
    }

    async exportNewsletter() {
        window.location.href = `${this.api.baseUrl}${this.api.apiPath}/newsletter.php?action=export`;
    }

    // ========== SEO ==========

    async loadSeo() {
        try {
            const data = await this.api.getSeoSettings();
            if (data.settings) {
                FormHelpers.populate(document.getElementById('seo-settings-form'), data.settings);
            }
            this.renderSeoAnalytics(data.stats_7d);
        } catch (error) {
            this.toast('Erreur chargement SEO', 'error');
        }
    }

    renderSeoAnalytics(stats) {
        const container = document.getElementById('seo-analytics-content');
        if (!container) return;

        if (!stats || !stats.total_impressions) {
            container.innerHTML = '<p class="empty-state">Aucune donnée SEO. Synchronisez avec Google Search Console.</p>';
            return;
        }

        container.innerHTML = `
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-label">Impressions</div><div class="stat-value">${UIHelpers.formatNumber(stats.total_impressions)}</div></div>
                <div class="stat-card"><div class="stat-label">Clics</div><div class="stat-value">${UIHelpers.formatNumber(stats.total_clicks)}</div></div>
                <div class="stat-card"><div class="stat-label">CTR moyen</div><div class="stat-value">${stats.avg_ctr?.toFixed(2) || 0}%</div></div>
                <div class="stat-card"><div class="stat-label">Position moyenne</div><div class="stat-value">${stats.avg_position?.toFixed(1) || '-'}</div></div>
            </div>
        `;
    }

    async saveSeoSettings() {
        const form = document.getElementById('seo-settings-form');
        const data = FormHelpers.serialize(form);

        try {
            await this.api.updateSeoSettings(data);
            this.toast('Paramètres SEO sauvegardés', 'success');
        } catch (error) {
            this.toast('Erreur sauvegarde', 'error');
        }
    }

    async generateSitemap() {
        try {
            const result = await this.api.generateSitemap();
            this.toast(`Sitemap généré: ${result.articles_count} articles`, 'success');
        } catch (error) {
            this.toast('Erreur génération sitemap', 'error');
        }
    }

    async syncGSC() {
        this.toast('Sync GSC demandée - utilisez les outils MCP', 'info');
    }

    // ========== CONFIG ==========

    async loadConfig() {
        try {
            const config = await this.api.getConfig();
            const prompts = await this.api.getPrompts();

            FormHelpers.populate(document.getElementById('config-form'), config);
            FormHelpers.populate(document.getElementById('prompts-form'), prompts);

            this.renderSystemInfo();
        } catch (error) {
            this.toast('Erreur chargement config', 'error');
        }
    }

    async saveConfig() {
        const form = document.getElementById('config-form');
        const data = FormHelpers.serialize(form);
        data.auto_publish = data.auto_publish ? true : false;

        try {
            await this.api.updateConfig(data);
            this.toast('Configuration sauvegardée', 'success');
        } catch (error) {
            this.toast('Erreur sauvegarde', 'error');
        }
    }

    async savePrompts() {
        const form = document.getElementById('prompts-form');
        const data = FormHelpers.serialize(form);

        try {
            await this.api.updatePrompts(data);
            this.toast('Prompts sauvegardés', 'success');
        } catch (error) {
            this.toast('Erreur sauvegarde', 'error');
        }
    }

    async renderSystemInfo() {
        const container = document.getElementById('system-info');
        if (!container) return;

        try {
            const info = await this.api.request('/system.php?action=health', { method: 'GET' });
            container.innerHTML = `
                <h4>État du système</h4>
                <ul style="list-style: none;">
                    ${Object.entries(info.checks).map(([k, v]) => `
                        <li style="padding: 0.5rem 0;">
                            <span class="status-badge ${v.status}">${v.status}</span> ${k}
                            ${v.message ? `<span style="color: var(--text-secondary);"> - ${v.message}</span>` : ''}
                        </li>
                    `).join('')}
                </ul>
            `;
        } catch (error) {
            container.innerHTML = '<p class="error">Impossible de charger l\'état système</p>';
        }
    }

    async runQA() {
        try {
            const result = await this.api.runQA();
            const status = result.status === 'PASS' ? 'success' : (result.status === 'FAIL' ? 'error' : 'warning');
            this.toast(`QA: ${result.status}`, status);

            if (result.errors?.length > 0) {
                alert('Erreurs QA:\n' + result.errors.join('\n'));
            }
        } catch (error) {
            this.toast('Erreur QA', 'error');
        }
    }

    async triggerDeploy() {
        try {
            await this.api.triggerDeploy();
            this.toast('Déploiement terminé', 'success');
        } catch (error) {
            this.toast('Erreur déploiement', 'error');
        }
    }

    async regenerateAll() {
        if (!confirm('Régénérer tous les fichiers statiques ?')) return;

        try {
            const result = await this.api.request('/system.php?action=regenerate-all', { method: 'POST' });
            this.toast(`Régénération: ${result.success} succès, ${result.failed} échecs`, 'success');
        } catch (error) {
            this.toast('Erreur régénération', 'error');
        }
    }

    // ========== LOGS ==========

    async loadLogs() {
        const status = document.getElementById('logs-filter')?.value || '';

        try {
            const data = await this.api.getGenerationLogs({ status, limit: 100 });
            this.renderLogsTable(data.logs || []);
        } catch (error) {
            this.toast('Erreur chargement logs', 'error');
        }
    }

    renderLogsTable(logs) {
        const tbody = document.querySelector('#logs-table tbody');
        if (!tbody) return;

        if (logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="empty-state">Aucun log</td></tr>';
            return;
        }

        tbody.innerHTML = logs.map(l => `
            <tr>
                <td>${UIHelpers.formatDate(l.created_at)}</td>
                <td>${UIHelpers.truncate(l.source_title || '-', 30)}</td>
                <td>${l.article_title ? UIHelpers.truncate(l.article_title, 25) : '-'}</td>
                <td><span class="status-badge ${l.status}">${l.status}</span></td>
                <td>${l.generation_time ? l.generation_time + 's' : '-'}</td>
                <td>${l.cost_estimate ? '$' + l.cost_estimate.toFixed(4) : '-'}</td>
                <td class="action-buttons">
                    ${l.status === 'error' ? `<button class="btn btn-small btn-secondary" onclick="app.retryGeneration(${l.id})">Retry</button>` : ''}
                    ${l.error_message ? `<button class="btn btn-small btn-secondary" onclick="alert('${l.error_message.replace(/'/g, "\\'")}')">Détail</button>` : ''}
                </td>
            </tr>
        `).join('');
    }

    async retryGeneration(logId) {
        try {
            await this.api.retryGeneration(logId);
            this.toast('Génération relancée', 'success');
            this.loadLogs();
        } catch (error) {
            this.toast('Erreur retry', 'error');
        }
    }

    // ========== HELPERS ==========

    loadCategories() {
        this.categories = {
            'chaos-politique': 'Chaos Politique',
            'effondrement-economique': 'Effondrement Économique',
            'declin-societal': 'Déclin Sociétal',
            'desastre-ecologique': 'Désastre Écologique',
            'fiasco-technologique': 'Fiasco Technologique',
            'crise-sanitaire': 'Crise Sanitaire'
        };

        this.populateCategorySelect('filter-category');
    }

    populateCategorySelect(selectId) {
        const select = document.getElementById(selectId);
        if (!select) return;

        const currentValue = select.value;
        const firstOption = select.querySelector('option');
        select.innerHTML = firstOption ? firstOption.outerHTML : '';

        Object.entries(this.categories).forEach(([k, v]) => {
            const opt = document.createElement('option');
            opt.value = k;
            opt.textContent = v;
            select.appendChild(opt);
        });

        if (currentValue) select.value = currentValue;
    }

    populateJournalistSelect(selectId) {
        const select = document.getElementById(selectId);
        if (!select) return;

        const currentValue = select.value;
        const firstOption = select.querySelector('option');
        select.innerHTML = firstOption ? firstOption.outerHTML : '';

        this.journalists.forEach(j => {
            if (j.active) {
                const opt = document.createElement('option');
                opt.value = j.id;
                opt.textContent = j.name;
                select.appendChild(opt);
            }
        });

        if (currentValue) select.value = currentValue;
    }

    showModal(title, body) {
        document.getElementById('modal-title').textContent = title;
        document.getElementById('modal-body').innerHTML = body;
        document.getElementById('modal').classList.remove('hidden');
    }

    closeModal() {
        document.getElementById('modal').classList.add('hidden');
    }

    switchTab(tabId, tabButton) {
        const parent = tabButton.closest('.section');

        parent.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        tabButton.classList.add('active');

        parent.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById(`tab-${tabId}`)?.classList.add('active');
    }

    toast(message, type = 'info') {
        UIHelpers.showToast(message, type);
    }
}

// Initialize app
const app = new AdminApp();
