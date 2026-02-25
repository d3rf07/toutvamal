<?php
/**
 * ToutVaMal.fr - Images API v2
 * Gestion des images d'articles
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/v1/ImageGenerator.php';

class ImagesAPI extends APIEndpoint {

    protected function get(): void {
        $action = $this->param('action', 'list');

        if ($action === 'list') {
            $this->listImages();
        } else {
            $this->error('Unknown action', 400);
        }
    }

    protected function post(): void {
        $action = $this->param('action');

        switch ($action) {
            case 'regenerate':
                $this->regenerateImage();
                break;
            case 'activate':
                $this->activateImage();
                break;
            case 'delete':
                $this->deleteImage();
                break;
            default:
                $this->error('Unknown action', 400);
        }
    }

    private function listImages(): void {
        $articleId = $this->paramInt('article_id');
        if (!$articleId) {
            $this->error('article_id required', 400);
        }

        $article = Database::getArticleById($articleId);
        if (!$article) {
            $this->error('Article not found', 404);
        }

        $images = [];
        if (!empty($article['image_path'])) {
            $images[] = [
                'id' => 'current',
                'path' => $article['image_path'],
                'url' => SITE_URL . $article['image_path'],
                'active' => true,
                'created_at' => $article['updated_at'] ?? $article['created_at']
            ];
        }

        // Check for alternative images in the directory
        $slug = $article['slug'];
        $dir = IMAGES_PATH . '/articles';
        if (is_dir($dir)) {
            $files = glob($dir . '/' . $slug . '-*.webp');
            foreach ($files as $file) {
                $relativePath = '/images/articles/' . basename($file);
                if ($relativePath !== ($article['image_path'] ?? '')) {
                    $images[] = [
                        'id' => md5(basename($file)),
                        'path' => $relativePath,
                        'url' => SITE_URL . $relativePath,
                        'active' => false,
                        'created_at' => date('Y-m-d H:i:s', filemtime($file))
                    ];
                }
            }
        }

        $this->success([
            'article_id' => $articleId,
            'images' => $images,
            'total' => count($images)
        ]);
    }

    private function regenerateImage(): void {
        $data = $this->getJsonBody();
        $articleId = (int)($data['article_id'] ?? 0);

        if (!$articleId) {
            $this->error('article_id required', 400);
        }

        $article = Database::getArticleById($articleId);
        if (!$article) {
            $this->error('Article not found', 404);
        }

        $prompt = $data['prompt'] ?? null;

        // If no custom prompt, try to regenerate from the generation log
        if (!$prompt) {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT gl.* FROM generation_logs gl
                WHERE gl.article_id = ?
                ORDER BY gl.created_at DESC LIMIT 1
            ");
            $stmt->execute([$articleId]);
            $log = $stmt->fetch();

            // Default prompt from article title
            $prompt = "Photojournalism, Reuters/AFP press photo, dramatic scene related to: " . $article['title'] . ", natural lighting, candid shot, DSLR quality, editorial news photography, hyperrealistic";
        }

        try {
            $imageGen = new ImageGenerator();
            $imagePath = $imageGen->generateImage($prompt, $article['slug']);

            if (!$imagePath) {
                $this->error('Image generation failed', 500);
            }

            // Update article with new image
            Database::updateArticle($articleId, ['image_path' => $imagePath]);

            $this->success([
                'message' => 'Image regenerated successfully',
                'image_path' => $imagePath,
                'url' => SITE_URL . $imagePath,
                'article_id' => $articleId
            ]);
        } catch (Exception $e) {
            log_error("Image regeneration failed for article $articleId: " . $e->getMessage());
            $this->error('Image generation failed: ' . $e->getMessage(), 500);
        }
    }

    private function activateImage(): void {
        $data = $this->getJsonBody();
        $imageId = $data['image_id'] ?? '';

        if (!$imageId) {
            $this->error('image_id required', 400);
        }

        // Find the image file by its ID (md5 of filename)
        $dir = IMAGES_PATH . '/articles';
        $found = null;
        if (is_dir($dir)) {
            $files = glob($dir . '/*.webp');
            foreach ($files as $file) {
                if (md5(basename($file)) === $imageId) {
                    $found = '/images/articles/' . basename($file);
                    break;
                }
            }
        }

        if (!$found) {
            $this->error('Image not found', 404);
        }

        // Find article by slug from filename
        $basename = basename($found);
        $slug = preg_replace('/-\d+\.webp$/', '', $basename);

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM articles WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $article = $stmt->fetch();

        if (!$article) {
            $this->error('Associated article not found', 404);
        }

        Database::updateArticle($article['id'], ['image_path' => $found]);

        $this->success([
            'message' => 'Image activated',
            'image_path' => $found,
            'article_id' => $article['id']
        ]);
    }

    private function deleteImage(): void {
        $data = $this->getJsonBody();
        $imageId = $data['image_id'] ?? '';

        if (!$imageId) {
            $this->error('image_id required', 400);
        }

        $dir = IMAGES_PATH . '/articles';
        $found = null;
        if (is_dir($dir)) {
            $files = glob($dir . '/*.webp');
            foreach ($files as $file) {
                if (md5(basename($file)) === $imageId) {
                    $found = $file;
                    break;
                }
            }
        }

        if (!$found) {
            $this->error('Image not found', 404);
        }

        // Don't delete if it's the active image
        $relativePath = '/images/articles/' . basename($found);
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM articles WHERE image_path = ? LIMIT 1");
        $stmt->execute([$relativePath]);
        if ($stmt->fetch()) {
            $this->error('Cannot delete active image. Activate another image first.', 400);
        }

        unlink($found);

        $this->success(['message' => 'Image deleted']);
    }
}

// Exécution
$api = new ImagesAPI();
$api->handle();
