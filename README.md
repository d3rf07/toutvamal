# ToutVaMal.fr - Rebuild v2.0 (30/12/2025)

Site satirique français automatisé - "C'ÉTAIT MIEUX AVANT"

## Architecture

```
public_html/
├── index.php              # Homepage dynamique
├── index.html             # Homepage statique (générée)
├── article.php            # Template article dynamique
├── config.php             # Configuration centrale
├── init_db.php            # Initialisation DB
├── sitemap.xml            # Sitemap SEO (généré)
├── robots.txt             # Robots SEO (généré)
├── 404.html               # Page erreur
├── .htaccess              # Rewriting + sécurité
│
├── articles/              # Articles HTML statiques
│   └── *.html
│
├── css/
│   └── style.css          # Styles complets
│
├── templates/
│   ├── header.php         # Header partagé
│   ├── sidebar.php        # Sidebar newsletter + nav
│   └── footer.php         # Footer partagé
│
├── api/
│   ├── v1/                # Générateurs (legacy)
│   │   ├── ContentGenerator.php  # GPT-5.2 via OpenRouter
│   │   ├── ImageGenerator.php    # Replicate
│   │   └── RSSFetcher.php        # Lecture flux RSS
│   │
│   └── v2/                # API REST complète (NEW)
│       ├── db.php              # Database helper singleton
│       ├── auth.php            # Auth Bearer + rate limiting
│       ├── articles.php        # CRUD articles + publish/unpublish
│       ├── journalists.php     # CRUD journalistes
│       ├── config.php          # Configuration + prompts IA
│       ├── rss-sources.php     # Sources RSS + test/fetch
│       ├── newsletter.php      # Abonnés + export CSV
│       ├── seo.php             # SEO settings + sitemap + GSC
│       ├── stats.php           # Dashboard statistiques
│       ├── generate.php        # Génération articles
│       ├── generation-logs.php # Historique générations
│       ├── system.php          # QA, deploy, logs
│       └── migrate.php         # Migrations DB
│
├── admin/                 # Interface admin v2 (NEW)
│   ├── index.html         # SPA admin complète
│   ├── css/admin.css      # Dark mode design
│   └── js/
│       ├── api.js         # Client API wrapper
│       └── app.js         # Application admin
│
├── cron/
│   ├── auto-generate.php  # CRON génération auto
│   ├── qa-check.php       # Vérification QA
│   └── deploy.php         # Script déploiement
│
├── data/
│   └── toutvamal.db       # SQLite database
│
├── images/articles/       # Images générées
├── equipe/                # Photos journalistes
└── logs/                  # Logs application
```

## Base de données

### Tables principales
- `articles` - Articles avec colonnes SEO (meta_title, meta_description, robots, etc.)
- `journalists` - Journalistes fictifs avec style d'écriture
- `newsletter` - Abonnés newsletter
- `config` - Configuration dynamique
- `rss_sources` - Sources RSS actives
- `generation_logs` - Historique générations avec coûts
- `seo_analytics` - Données Google Search Console
- `seo_settings` - Paramètres SEO globaux

## APIs

### OpenRouter (Texte)
- **Modèle**: `openai/gpt-5.2`
- **Usage**: Génération articles satiriques

### Replicate (Images)
- **Modèle**: `google/gemini-3-pro-image`
- **Usage**: Illustrations articles

## URLs

| URL | Description |
|-----|-------------|
| https://toutvamal.fr/ | Homepage |
| https://toutvamal.fr/articles/{slug}.html | Articles |
| https://toutvamal.fr/admin/ | Interface admin |
| https://toutvamal.fr/api/v2/ | API REST |

## Admin Features

- **Dashboard** - Stats temps réel, articles récents, coûts
- **Articles** - CRUD complet, éditeur, publish/unpublish, SEO
- **Générer** - Génération manuelle avec choix journaliste/catégorie
- **Journalistes** - CRUD personnages fictifs
- **Sources RSS** - Gestion flux + test + fetch
- **Newsletter** - Liste abonnés + export CSV
- **SEO** - Paramètres, sitemap, analytics GSC
- **Configuration** - Modèles IA, prompts, système
- **Logs** - Historique générations avec retry

## Configuration

### CRON (via hPanel)
```
0 */3 * * * /usr/bin/php /home/u443792660/domains/toutvamal.fr/public_html/cron/auto-generate.php
```

### Token API
```
d28b07781fabd95c106ce059b9b30b72aaa13bf7ff3ee294b94e2d799685367e
```

## Commandes utiles

```bash
# Test génération manuelle
ssh toutvamal "cd /home/u443792660/domains/toutvamal.fr/public_html && php cron/auto-generate.php"

# Lancer QA
ssh toutvamal "cd /home/u443792660/domains/toutvamal.fr/public_html && php cron/qa-check.php"

# Migration DB
ssh toutvamal "cd /home/u443792660/domains/toutvamal.fr/public_html && php api/v2/migrate.php"

# Fix permissions
ssh toutvamal "cd /home/u443792660/domains/toutvamal.fr/public_html && find . -type f -name '*.php' -exec chmod 644 {} \;"
```

## Changelog

### v2.0.0 (30/12/2025)
- Nouvelle API REST v2 complète
- Interface admin SPA dark mode
- Module SEO avec sitemap et préparation GSC
- Gestion sources RSS dynamique
- Configuration prompts IA depuis admin
- Système de logs avec retry
- QA automatisé

### v1.0.0 (30/12/2025)
- Rebuild initial depuis zero
- Migration données existantes
- Générateurs GPT-5.2 + Replicate
