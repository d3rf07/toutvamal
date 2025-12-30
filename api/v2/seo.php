<?php
/**
 * ToutVaMal.fr - SEO API v2
 * Gestion SEO et intégration Google Search Console
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

class SeoAPI extends APIEndpoint {

    protected function get(): void {
        $type = $this->param('type');

        if ($type === 'analytics') {
            $this->getAnalytics();
        }

        if ($type === 'articles') {
            $this->getArticlesSeo();
        }

        // SEO settings par défaut
        $this->getSettings();
    }

    protected function put(): void {
        $data = $this->getJsonBody();

        $db = Database::getInstance();

        // Update SEO settings
        $stmt = $db->prepare("
            UPDATE seo_settings SET
                site_title = :site_title,
                site_description = :site_description,
                default_og_image = :default_og_image,
                twitter_handle = :twitter_handle,
                google_site_verification = :google_site_verification,
                robots_txt = :robots_txt,
                sitemap_enabled = :sitemap_enabled,
                updated_at = datetime('now')
            WHERE id = 1
        ");

        $stmt->execute([
            ':site_title' => $data['site_title'] ?? 'ToutVaMal.fr',
            ':site_description' => $data['site_description'] ?? '',
            ':default_og_image' => $data['default_og_image'] ?? '',
            ':twitter_handle' => $data['twitter_handle'] ?? '',
            ':google_site_verification' => $data['google_site_verification'] ?? '',
            ':robots_txt' => $data['robots_txt'] ?? '',
            ':sitemap_enabled' => $data['sitemap_enabled'] ?? 1
        ]);

        log_info("SEO settings updated");

        $this->success(['message' => 'SEO settings updated']);
    }

    protected function post(): void {
        $action = $this->param('action');

        switch ($action) {
            case 'sync-gsc':
                $this->syncGoogleSearchConsole();
                break;
            case 'index':
                $this->submitToIndex();
                break;
            case 'sitemap':
                $this->generateSitemap();
                break;
            case 'robots':
                $this->generateRobotsTxt();
                break;
            default:
                $this->error('Unknown action', 400);
        }
    }

    private function getSettings(): void {
        $db = Database::getInstance();

        $stmt = $db->query("SELECT * FROM seo_settings WHERE id = 1");
        $settings = $stmt->fetch();

        if (!$settings) {
            // Créer settings par défaut
            $db->exec("INSERT INTO seo_settings (id, site_title, site_description) VALUES (1, 'ToutVaMal.fr', 'C''était mieux avant')");
            $settings = $db->query("SELECT * FROM seo_settings WHERE id = 1")->fetch();
        }

        // Ajouter stats SEO récentes
        $stats = Database::getSeoStats(7);

        $this->success([
            'settings' => $settings,
            'stats_7d' => $stats
        ]);
    }

    private function getAnalytics(): void {
        $days = $this->paramInt('days', 30);
        $db = Database::getInstance();

        // Stats globales
        $stats = Database::getSeoStats($days);

        // Par page
        $stmt = $db->prepare("
            SELECT
                page_url,
                SUM(impressions) as impressions,
                SUM(clicks) as clicks,
                AVG(ctr) as ctr,
                AVG(position) as position
            FROM seo_analytics
            WHERE date_recorded >= date('now', '-' || ? || ' days')
            GROUP BY page_url
            ORDER BY impressions DESC
            LIMIT 50
        ");
        $stmt->execute([$days]);
        $byPage = $stmt->fetchAll();

        // Par query (requête de recherche)
        $stmt = $db->prepare("
            SELECT
                query,
                SUM(impressions) as impressions,
                SUM(clicks) as clicks,
                AVG(ctr) as ctr,
                AVG(position) as position
            FROM seo_analytics
            WHERE date_recorded >= date('now', '-' || ? || ' days')
            AND query IS NOT NULL
            GROUP BY query
            ORDER BY impressions DESC
            LIMIT 50
        ");
        $stmt->execute([$days]);
        $byQuery = $stmt->fetchAll();

        // Timeline
        $stmt = $db->prepare("
            SELECT
                date_recorded,
                SUM(impressions) as impressions,
                SUM(clicks) as clicks,
                AVG(ctr) as ctr,
                AVG(position) as position
            FROM seo_analytics
            WHERE date_recorded >= date('now', '-' || ? || ' days')
            GROUP BY date_recorded
            ORDER BY date_recorded
        ");
        $stmt->execute([$days]);
        $timeline = $stmt->fetchAll();

        $this->success([
            'period_days' => $days,
            'totals' => $stats,
            'by_page' => $byPage,
            'by_query' => $byQuery,
            'timeline' => $timeline
        ]);
    }

    private function getArticlesSeo(): void {
        $db = Database::getInstance();

        $stmt = $db->query("
            SELECT
                a.id, a.slug, a.title, a.meta_title, a.meta_description,
                a.robots, a.canonical_url, a.published_at,
                COALESCE(seo.impressions, 0) as impressions,
                COALESCE(seo.clicks, 0) as clicks
            FROM articles a
            LEFT JOIN (
                SELECT article_id, SUM(impressions) as impressions, SUM(clicks) as clicks
                FROM seo_analytics
                GROUP BY article_id
            ) seo ON a.id = seo.article_id
            WHERE a.status = 'published'
            ORDER BY a.published_at DESC
        ");

        $articles = $stmt->fetchAll();

        // Ajouter score SEO simple
        foreach ($articles as &$article) {
            $score = 0;
            if (!empty($article['meta_title'])) $score += 25;
            if (!empty($article['meta_description'])) $score += 25;
            if (strlen($article['meta_description'] ?? '') >= 120) $score += 25;
            if (strlen($article['meta_title'] ?? '') >= 30 && strlen($article['meta_title'] ?? '') <= 60) $score += 25;
            $article['seo_score'] = $score;
        }

        $this->success(['articles' => $articles]);
    }

    private function syncGoogleSearchConsole(): void {
        // Cette fonction utiliserait normalement l'API GSC via MCP
        // Pour l'instant, on simule ou on log pour traitement manuel
        log_info("GSC sync requested - requires manual MCP call");

        $this->success([
            'message' => 'GSC sync queued',
            'note' => 'Use MCP google-search-console tools to fetch data'
        ]);
    }

    private function submitToIndex(): void {
        $data = $this->getJsonBody();

        if (empty($data['url'])) {
            $this->error('URL required', 400);
        }

        // Valider que l'URL appartient au site
        if (strpos($data['url'], SITE_URL) !== 0) {
            $this->error('URL must belong to ' . SITE_URL, 400);
        }

        log_info("Index submission requested for: {$data['url']}");

        $this->success([
            'message' => 'Indexing request queued',
            'url' => $data['url'],
            'note' => 'Use MCP google-search-console submit_url_for_indexing'
        ]);
    }

    private function generateSitemap(): void {
        $db = Database::getInstance();

        // Articles publiés
        $stmt = $db->query("
            SELECT slug, published_at, updated_at
            FROM articles
            WHERE status = 'published'
            ORDER BY published_at DESC
        ");
        $articles = $stmt->fetchAll();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Homepage
        $xml .= "  <url>\n";
        $xml .= "    <loc>" . SITE_URL . "/</loc>\n";
        $xml .= "    <changefreq>daily</changefreq>\n";
        $xml .= "    <priority>1.0</priority>\n";
        $xml .= "  </url>\n";

        // Articles
        foreach ($articles as $article) {
            $lastmod = $article['updated_at'] ?? $article['published_at'];
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . SITE_URL . "/articles/{$article['slug']}.html</loc>\n";
            $xml .= "    <lastmod>" . date('Y-m-d', strtotime($lastmod)) . "</lastmod>\n";
            $xml .= "    <changefreq>monthly</changefreq>\n";
            $xml .= "    <priority>0.8</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>\n";

        // Sauvegarder
        $filepath = ROOT_PATH . '/sitemap.xml';
        file_put_contents($filepath, $xml);
        chmod($filepath, 0644);

        log_info("Sitemap generated with " . count($articles) . " articles");

        $this->success([
            'message' => 'Sitemap generated',
            'url' => SITE_URL . '/sitemap.xml',
            'articles_count' => count($articles)
        ]);
    }

    private function generateRobotsTxt(): void {
        $db = Database::getInstance();

        $stmt = $db->query("SELECT robots_txt FROM seo_settings WHERE id = 1");
        $custom = $stmt->fetchColumn();

        if (empty($custom)) {
            $robots = "User-agent: *\n";
            $robots .= "Allow: /\n";
            $robots .= "Disallow: /admin/\n";
            $robots .= "Disallow: /api/\n";
            $robots .= "Disallow: /cron/\n";
            $robots .= "Disallow: /data/\n";
            $robots .= "Disallow: /logs/\n";
            $robots .= "\n";
            $robots .= "Sitemap: " . SITE_URL . "/sitemap.xml\n";
        } else {
            $robots = $custom;
        }

        $filepath = ROOT_PATH . '/robots.txt';
        file_put_contents($filepath, $robots);
        chmod($filepath, 0644);

        log_info("robots.txt generated");

        $this->success([
            'message' => 'robots.txt generated',
            'content' => $robots
        ]);
    }
}

// Exécution
$api = new SeoAPI();
$api->handle();
