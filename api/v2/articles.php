<?php
/**
 * ToutVaMal.fr - Articles API v2
 * CRUD complet pour les articles
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

class ArticlesAPI extends APIEndpoint {

    protected function get(): void {
        $id = $this->paramInt('id');

        if ($id) {
            $article = Database::getArticleById($id);
            if (!$article) {
                $this->error('Article not found', 404);
            }
            $this->success($article);
        }

        // Liste avec pagination et filtres
        $params = [
            'limit' => $this->paramInt('limit', 50),
            'offset' => $this->paramInt('offset', 0),
            'category' => $this->param('category'),
            'status' => $this->param('status')
        ];

        $articles = Database::getArticles(
            $params['limit'],
            $params['offset'],
            $params['category'],
            $params['status']
        );

        $total = Database::countArticles($params['category'], $params['status']);

        $this->success([
            'articles' => $articles,
            'pagination' => [
                'total' => $total,
                'limit' => $params['limit'],
                'offset' => $params['offset'],
                'has_more' => ($params['offset'] + count($articles)) < $total
            ]
        ]);
    }

    protected function post(): void {
        $action = $this->param('action');
        $id = $this->paramInt('id');

        // Actions spéciales
        if ($action && $id) {
            switch ($action) {
                case 'publish':
                    $this->publishArticle($id);
                    break;
                case 'unpublish':
                    $this->unpublishArticle($id);
                    break;
                case 'regenerate':
                    $this->regenerateStatic($id);
                    break;
                default:
                    $this->error('Unknown action', 400);
            }
        }

        // Création nouvel article
        $data = $this->getJsonBody();
        $this->validateRequired($data, ['title']);

        // Génération du slug si non fourni
        if (empty($data['slug'])) {
            $data['slug'] = slugify($data['title']);
        }

        // Vérifier unicité du slug
        $existing = Database::getArticleBySlug($data['slug']);
        if ($existing) {
            $data['slug'] .= '-' . time();
        }

        try {
            $id = Database::createArticle($data);
            $article = Database::getArticleById($id);

            log_info("Article created: {$article['title']} (ID: $id)");

            $this->success([
                'message' => 'Article created',
                'article' => $article
            ], 201);
        } catch (Exception $e) {
            log_error("Failed to create article: " . $e->getMessage());
            $this->error('Failed to create article: ' . $e->getMessage(), 500);
        }
    }

    protected function put(): void {
        $id = $this->paramInt('id');
        if (!$id) {
            $this->error('Article ID required', 400);
        }

        $article = Database::getArticleById($id);
        if (!$article) {
            $this->error('Article not found', 404);
        }

        $data = $this->getJsonBody();

        // Si le slug change, vérifier unicité
        if (!empty($data['slug']) && $data['slug'] !== $article['slug']) {
            $existing = Database::getArticleBySlug($data['slug']);
            if ($existing && $existing['id'] !== $id) {
                $this->error('Slug already exists', 409);
            }
        }

        try {
            Database::updateArticle($id, $data);
            $updated = Database::getArticleById($id);

            log_info("Article updated: {$updated['title']} (ID: $id)");

            // Régénérer le fichier statique si publié
            if ($updated['status'] === 'published') {
                $this->generateStaticFile($updated);
            }

            $this->success([
                'message' => 'Article updated',
                'article' => $updated
            ]);
        } catch (Exception $e) {
            log_error("Failed to update article $id: " . $e->getMessage());
            $this->error('Failed to update article: ' . $e->getMessage(), 500);
        }
    }

    protected function delete(): void {
        $id = $this->paramInt('id');
        if (!$id) {
            $this->error('Article ID required', 400);
        }

        $article = Database::getArticleById($id);
        if (!$article) {
            $this->error('Article not found', 404);
        }

        try {
            // Supprimer fichier statique
            $staticFile = ARTICLES_PATH . '/' . $article['slug'] . '.html';
            if (file_exists($staticFile)) {
                unlink($staticFile);
            }

            // Supprimer image si existe
            if ($article['image_path']) {
                $imagePath = ROOT_PATH . $article['image_path'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            Database::deleteArticle($id);

            log_info("Article deleted: {$article['title']} (ID: $id)");

            $this->success(['message' => 'Article deleted']);
        } catch (Exception $e) {
            log_error("Failed to delete article $id: " . $e->getMessage());
            $this->error('Failed to delete article: ' . $e->getMessage(), 500);
        }
    }

    // ========== Actions spéciales ==========

    private function publishArticle(int $id): void {
        $article = Database::getArticleById($id);
        if (!$article) {
            $this->error('Article not found', 404);
        }

        Database::updateArticle($id, [
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s')
        ]);

        $updated = Database::getArticleById($id);
        $this->generateStaticFile($updated);

        log_info("Article published: {$article['title']} (ID: $id)");

        $this->success([
            'message' => 'Article published',
            'article' => $updated
        ]);
    }

    private function unpublishArticle(int $id): void {
        $article = Database::getArticleById($id);
        if (!$article) {
            $this->error('Article not found', 404);
        }

        Database::updateArticle($id, ['status' => 'draft']);

        // Supprimer fichier statique
        $staticFile = ARTICLES_PATH . '/' . $article['slug'] . '.html';
        if (file_exists($staticFile)) {
            unlink($staticFile);
        }

        log_info("Article unpublished: {$article['title']} (ID: $id)");

        $this->success([
            'message' => 'Article unpublished',
            'article' => Database::getArticleById($id)
        ]);
    }

    private function regenerateStatic(int $id): void {
        $article = Database::getArticleById($id);
        if (!$article) {
            $this->error('Article not found', 404);
        }

        if ($article['status'] !== 'published') {
            $this->error('Article must be published to generate static file', 400);
        }

        $this->generateStaticFile($article);

        $this->success(['message' => 'Static file regenerated']);
    }

    private function generateStaticFile(array $article): bool {
        try {
            $url = SITE_URL . '/article.php?slug=' . $article['slug'];
            $html = @file_get_contents($url);

            if ($html) {
                $filepath = ARTICLES_PATH . '/' . $article['slug'] . '.html';
                file_put_contents($filepath, $html);
                chmod($filepath, 0644);
                return true;
            }
        } catch (Exception $e) {
            log_error("Failed to generate static file for article {$article['id']}: " . $e->getMessage());
        }
        return false;
    }
}

// Exécution
$api = new ArticlesAPI();
$api->handle();
