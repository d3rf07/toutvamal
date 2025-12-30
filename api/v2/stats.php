<?php
/**
 * ToutVaMal.fr - Stats API v2
 * Dashboard et statistiques
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

class StatsAPI extends APIEndpoint {

    protected function get(): void {
        $type = $this->param('type');
        $articleId = $this->paramInt('article');

        if ($articleId) {
            $this->getArticleStats($articleId);
        }

        if ($type === 'generations') {
            $this->getGenerationStats();
        }

        if ($type === 'timeline') {
            $this->getTimeline();
        }

        // Dashboard stats par défaut
        $this->getDashboard();
    }

    private function getDashboard(): void {
        $stats = Database::getDashboardStats();
        $db = Database::getInstance();

        // Articles récents
        $stmt = $db->query("
            SELECT a.id, a.title, a.slug, a.published_at, a.category, j.name as journalist_name
            FROM articles a
            LEFT JOIN journalists j ON a.journalist_id = j.id
            ORDER BY a.published_at DESC
            LIMIT 5
        ");
        $stats['recent_articles'] = $stmt->fetchAll();

        // Dernières générations
        $stmt = $db->query("
            SELECT gl.*, a.title as article_title
            FROM generation_logs gl
            LEFT JOIN articles a ON gl.article_id = a.id
            ORDER BY gl.created_at DESC
            LIMIT 5
        ");
        $stats['recent_generations'] = $stmt->fetchAll();

        // Coûts estimés (30 derniers jours)
        $stmt = $db->query("
            SELECT
                SUM(cost_estimate) as total_cost,
                SUM(tokens_used) as total_tokens,
                COUNT(*) as total_generations
            FROM generation_logs
            WHERE created_at >= date('now', '-30 days')
            AND status = 'success'
        ");
        $costs = $stmt->fetch();
        $stats['costs_30d'] = [
            'total_cost' => round((float)($costs['total_cost'] ?? 0), 4),
            'total_tokens' => (int)($costs['total_tokens'] ?? 0),
            'total_generations' => (int)($costs['total_generations'] ?? 0)
        ];

        // Catégories distribution
        $stmt = $db->query("
            SELECT category, COUNT(*) as count
            FROM articles
            GROUP BY category
            ORDER BY count DESC
        ");
        $stats['categories_distribution'] = $stmt->fetchAll();

        // SEO stats si disponible
        $stmt = $db->query("
            SELECT
                SUM(impressions) as total_impressions,
                SUM(clicks) as total_clicks,
                AVG(ctr) as avg_ctr,
                AVG(position) as avg_position
            FROM seo_analytics
            WHERE date_recorded >= date('now', '-7 days')
        ");
        $seo = $stmt->fetch();
        if ($seo['total_impressions']) {
            $stats['seo_7d'] = [
                'impressions' => (int)$seo['total_impressions'],
                'clicks' => (int)$seo['total_clicks'],
                'ctr' => round((float)$seo['avg_ctr'], 2),
                'position' => round((float)$seo['avg_position'], 1)
            ];
        }

        $this->success($stats);
    }

    private function getArticleStats(int $id): void {
        $article = Database::getArticleById($id);
        if (!$article) {
            $this->error('Article not found', 404);
        }

        $db = Database::getInstance();

        // SEO data pour cet article
        $stmt = $db->prepare("
            SELECT
                SUM(impressions) as impressions,
                SUM(clicks) as clicks,
                AVG(ctr) as ctr,
                AVG(position) as position
            FROM seo_analytics
            WHERE article_id = ?
        ");
        $stmt->execute([$id]);
        $seo = $stmt->fetch();

        // Historique SEO
        $stmt = $db->prepare("
            SELECT date_recorded, impressions, clicks, ctr, position
            FROM seo_analytics
            WHERE article_id = ?
            ORDER BY date_recorded DESC
            LIMIT 30
        ");
        $stmt->execute([$id]);
        $seoHistory = $stmt->fetchAll();

        // Log de génération
        $stmt = $db->prepare("
            SELECT * FROM generation_logs
            WHERE article_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $generation = $stmt->fetch();

        $this->success([
            'article' => $article,
            'seo' => [
                'total' => [
                    'impressions' => (int)($seo['impressions'] ?? 0),
                    'clicks' => (int)($seo['clicks'] ?? 0),
                    'ctr' => round((float)($seo['ctr'] ?? 0), 2),
                    'position' => round((float)($seo['position'] ?? 0), 1)
                ],
                'history' => $seoHistory
            ],
            'generation' => $generation
        ]);
    }

    private function getGenerationStats(): void {
        $period = $this->param('period', '30d');
        $db = Database::getInstance();

        // Parser période
        $days = 30;
        if (preg_match('/(\d+)d/', $period, $matches)) {
            $days = (int)$matches[1];
        }

        // Stats globales
        $stmt = $db->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors,
                SUM(tokens_used) as tokens,
                SUM(cost_estimate) as cost,
                AVG(generation_time) as avg_time
            FROM generation_logs
            WHERE created_at >= date('now', '-' || ? || ' days')
        ");
        $stmt->execute([$days]);
        $stats = $stmt->fetch();

        // Par jour
        $stmt = $db->prepare("
            SELECT
                date(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
                SUM(tokens_used) as tokens,
                SUM(cost_estimate) as cost
            FROM generation_logs
            WHERE created_at >= date('now', '-' || ? || ' days')
            GROUP BY date(created_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$days]);
        $byDay = $stmt->fetchAll();

        // Par modèle
        $stmt = $db->prepare("
            SELECT
                model_used,
                COUNT(*) as count,
                SUM(tokens_used) as tokens,
                SUM(cost_estimate) as cost
            FROM generation_logs
            WHERE created_at >= date('now', '-' || ? || ' days')
            AND model_used IS NOT NULL
            GROUP BY model_used
        ");
        $stmt->execute([$days]);
        $byModel = $stmt->fetchAll();

        // Erreurs récentes
        $stmt = $db->query("
            SELECT source_title, error_message, created_at
            FROM generation_logs
            WHERE status = 'error'
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $recentErrors = $stmt->fetchAll();

        $this->success([
            'period' => $period,
            'totals' => [
                'generations' => (int)$stats['total'],
                'success' => (int)$stats['success'],
                'errors' => (int)$stats['errors'],
                'success_rate' => $stats['total'] > 0 ? round(($stats['success'] / $stats['total']) * 100, 1) : 0,
                'tokens' => (int)$stats['tokens'],
                'cost' => round((float)$stats['cost'], 4),
                'avg_time' => round((float)$stats['avg_time'], 2)
            ],
            'by_day' => $byDay,
            'by_model' => $byModel,
            'recent_errors' => $recentErrors
        ]);
    }

    private function getTimeline(): void {
        $db = Database::getInstance();
        $days = $this->paramInt('days', 30);

        // Articles publiés par jour
        $stmt = $db->prepare("
            SELECT date(published_at) as date, COUNT(*) as count
            FROM articles
            WHERE published_at >= date('now', '-' || ? || ' days')
            GROUP BY date(published_at)
            ORDER BY date
        ");
        $stmt->execute([$days]);
        $articles = $stmt->fetchAll();

        // Générations par jour
        $stmt = $db->prepare("
            SELECT date(created_at) as date, COUNT(*) as count
            FROM generation_logs
            WHERE created_at >= date('now', '-' || ? || ' days')
            GROUP BY date(created_at)
            ORDER BY date
        ");
        $stmt->execute([$days]);
        $generations = $stmt->fetchAll();

        // Newsletter inscriptions par jour
        $stmt = $db->prepare("
            SELECT date(subscribed_at) as date, COUNT(*) as count
            FROM newsletter
            WHERE subscribed_at >= date('now', '-' || ? || ' days')
            GROUP BY date(subscribed_at)
            ORDER BY date
        ");
        $stmt->execute([$days]);
        $newsletter = $stmt->fetchAll();

        $this->success([
            'period_days' => $days,
            'articles' => $articles,
            'generations' => $generations,
            'newsletter' => $newsletter
        ]);
    }
}

// Exécution
$api = new StatsAPI();
$api->handle();
