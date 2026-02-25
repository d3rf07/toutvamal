<?php
/**
 * ToutVaMal.fr - Generate API v2
 * Génération d'articles via GPT-5.2 et Replicate
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/v1/ContentGenerator.php';
require_once dirname(__DIR__) . '/v1/ImageGenerator.php';
require_once dirname(__DIR__) . '/v1/RSSFetcher.php';

class GenerateAPI extends APIEndpoint {

    protected function post(): void {
        $retry = $this->paramInt('retry');

        if ($retry) {
            $this->retryGeneration($retry);
        }

        $data = $this->getJsonBody();

        // Options
        $options = [
            'source_url' => $data['source_url'] ?? null,
            'journalist_id' => $data['journalist_id'] ?? null,
            'category' => $data['category'] ?? null,
            'auto_publish' => $data['auto_publish'] ?? true,
            'generate_image' => $data['generate_image'] ?? true
        ];

        // Si pas d'URL source, récupérer depuis RSS
        if (empty($options['source_url'])) {
            $sourceItem = $this->getRandomRssItem();
            if (!$sourceItem) {
                $this->error('No RSS items available', 400);
            }
            $options['source_url'] = $sourceItem['link'];
            $options['source_title'] = $sourceItem['title'];
            $options['source_description'] = $sourceItem['description'] ?? '';
        } else {
            $options['source_title'] = $data['source_title'] ?? 'Source externe';
            $options['source_description'] = $data['source_description'] ?? '';
        }

        // Sélection journaliste
        if (empty($options['journalist_id'])) {
            $journalist = Database::getRandomJournalist();
        } else {
            $journalist = Database::getJournalistById($options['journalist_id']);
        }

        if (!$journalist) {
            $this->error('No journalist available', 400);
        }

        // Créer log de génération
        $logId = Database::createGenerationLog([
            'source_url' => $options['source_url'],
            'source_title' => $options['source_title'],
            'journalist_id' => $journalist['id'],
            'status' => 'pending'
        ]);

        $startTime = microtime(true);

        try {
            // 1. Générer le contenu
            $generator = new ContentGenerator();
            $content = $generator->generate([
                'title' => $options['source_title'],
                'description' => $options['source_description'],
                'url' => $options['source_url']
            ], $journalist);

            if (!$content || empty($content['title'])) {
                throw new Exception('Content generation failed');
            }

            // 2. Générer l'image si demandé
            $imagePath = null;
            if ($options['generate_image'] && !empty($content['image_prompt'])) {
                try {
                    $imageGenerator = new ImageGenerator();
                    $imagePath = $imageGenerator->generate($content['image_prompt'], slugify($content['title']));
                } catch (Exception $e) {
                    log_error("Image generation failed: " . $e->getMessage());
                    // Continue sans image
                }
            }

            // 3. Créer l'article
            $articleData = [
                'title' => $content['title'],
                'slug' => slugify($content['title']),
                'content' => $content['content'],
                'excerpt' => $content['excerpt'] ?? '',
                'category' => $content['category'] ?? $options['category'] ?? 'chaos-politique',
                'image_path' => $imagePath,
                'journalist_id' => $journalist['id'],
                'source_title' => $options['source_title'],
                'source_url' => $options['source_url'],
                'status' => $options['auto_publish'] ? 'published' : 'draft',
                'published_at' => $options['auto_publish'] ? date('Y-m-d H:i:s') : null,
                // SEO auto-généré
                'meta_title' => substr($content['title'], 0, 60),
                'meta_description' => substr(strip_tags($content['excerpt'] ?? $content['content']), 0, 160)
            ];

            $articleId = Database::createArticle($articleData);
            $article = Database::getArticleById($articleId);

            $generationTime = microtime(true) - $startTime;

            // 4. Mettre à jour le log
            Database::updateGenerationLog($logId, [
                'article_id' => $articleId,
                'status' => 'success',
                'model_used' => OPENROUTER_MODEL,
                'tokens_used' => $content['tokens_used'] ?? null,
                'cost_estimate' => $content['cost_estimate'] ?? null,
                'generation_time' => round($generationTime, 2)
            ]);

            // 5. Générer fichier statique si publié
            if ($options['auto_publish']) {
                $this->generateStaticFile($article);
            }

            log_info("Article generated: {$article['title']} (ID: $articleId) in {$generationTime}s");

            $this->success([
                'message' => 'Article generated successfully',
                'article' => $article,
                'generation' => [
                    'log_id' => $logId,
                    'time' => round($generationTime, 2),
                    'journalist' => $journalist['name'],
                    'has_image' => !empty($imagePath)
                ]
            ], 201);

        } catch (Exception $e) {
            $generationTime = microtime(true) - $startTime;

            Database::updateGenerationLog($logId, [
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'generation_time' => round($generationTime, 2)
            ]);

            log_error("Generation failed: " . $e->getMessage());

            $this->error('Generation failed: ' . $e->getMessage(), 500, [
                'log_id' => $logId
            ]);
        }
    }

    private function retryGeneration(int $logId): void {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM generation_logs WHERE id = ?");
        $stmt->execute([$logId]);
        $log = $stmt->fetch();

        if (!$log) {
            $this->error('Log not found', 404);
        }

        if ($log['status'] !== 'error') {
            $this->error('Can only retry failed generations', 400);
        }

        // Relancer avec les mêmes paramètres
        $_POST = json_encode([
            'source_url' => $log['source_url'],
            'source_title' => $log['source_title'],
            'journalist_id' => $log['journalist_id']
        ]);

        // Marquer l'ancien comme "retried"
        $db->prepare("UPDATE generation_logs SET error_message = CONCAT(error_message, ' [RETRIED]') WHERE id = ?")
           ->execute([$logId]);

        // Rappeler post() normalement
        $this->post();
    }

    private function getRandomRssItem(): ?array {
        $sources = Database::getRssSources(true);

        if (empty($sources)) {
            return null;
        }

        // Mélanger les sources
        shuffle($sources);

        $fetcher = new RSSFetcher();

        foreach ($sources as $source) {
            try {
                $items = $fetcher->fetch($source['url']);
                if (!empty($items)) {
                    // Vérifier que l'item n'a pas déjà été utilisé
                    $db = Database::getInstance();
                    foreach ($items as $item) {
                        $stmt = $db->prepare("SELECT COUNT(*) FROM generation_logs WHERE source_url = ?");
                        $stmt->execute([$item['link']]);
                        if ($stmt->fetchColumn() == 0) {
                            // Item non utilisé
                            Database::updateRssSource($source['id'], ['last_fetch' => date('Y-m-d H:i:s')]);
                            return $item;
                        }
                    }
                }
            } catch (Exception $e) {
                log_error("RSS fetch failed for {$source['name']}: " . $e->getMessage());
            }
        }

        return null;
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
            log_error("Static file generation failed for {$article['id']}: " . $e->getMessage());
        }
        return false;
    }
}

// Exécution
$api = new GenerateAPI();
$api->handle();
