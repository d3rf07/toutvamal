#!/usr/bin/env php
<?php
/**
 * ToutVaMal.fr - Cron: Auto-generate articles
 * Usage: php generate-articles.php [count] [--publish]
 * 
 * Exemples:
 *   php generate-articles.php 3 --publish  # 3 articles publiés auto
 *   php generate-articles.php 1            # 1 article en draft
 */

// Configuration
define('CRON_MODE', true);
chdir(dirname(__DIR__));
require_once 'config.php';
require_once 'api/v2/db.php';
require_once 'api/v2/duplicate-checker.php';
require_once 'api/v1/ContentGenerator.php';
require_once 'api/v1/ImageGenerator.php';
require_once 'api/v1/RSSFetcher.php';

// Arguments
$count = (int)($argv[1] ?? 1);
$autoPublish = in_array('--publish', $argv);

echo "=== ToutVaMal.fr Article Generator ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Target: $count article(s), " . ($autoPublish ? 'auto-publish' : 'draft') . "\n\n";

$generated = 0;
$failed = 0;
$skipped = 0;

// Récupérer sources RSS
$sources = Database::getRssSources(true);
if (empty($sources)) {
    die("ERROR: No active RSS sources\n");
}

$fetcher = new RSSFetcher();
$db = Database::getInstance();

// Collecter tous les items RSS disponibles
$allItems = [];
foreach ($sources as $source) {
    try {
        $items = $fetcher->fetch($source['url']);
        foreach ($items as $item) {
            $item['source_name'] = $source['name'];
            $allItems[] = $item;
        }
    } catch (Exception $e) {
        echo "WARN: Failed to fetch {$source['name']}: " . $e->getMessage() . "\n";
    }
}

shuffle($allItems); // Randomiser
echo "Found " . count($allItems) . " RSS items to evaluate\n\n";

foreach ($allItems as $item) {
    if ($generated >= $count) break;
    
    echo "Checking: " . substr($item['title'], 0, 60) . "...\n";
    
    // 1. Vérifier URL unique
    $stmt = $db->prepare("SELECT COUNT(*) FROM generation_logs WHERE source_url = ?");
    $stmt->execute([$item['link']]);
    if ($stmt->fetchColumn() > 0) {
        echo "  → SKIP: URL already processed\n";
        $skipped++;
        continue;
    }
    
    // 2. Vérifier doublon thème
    $duplicate = DuplicateChecker::checkDuplicate($item['title']);
    if ($duplicate) {
        echo "  → SKIP: Theme similar to existing article (" . ($duplicate['similarity'] ?? '?') . "%)\n";
        $skipped++;
        continue;
    }
    
    // 3. Générer l'article
    echo "  → GENERATING...\n";
    
    $journalist = Database::getRandomJournalist();
    if (!$journalist) {
        echo "  → ERROR: No journalist available\n";
        $failed++;
        continue;
    }
    
    // Log de génération
    $logId = Database::createGenerationLog([
        'source_url' => $item['link'],
        'source_title' => $item['title'],
        'journalist_id' => $journalist['id'],
        'status' => 'pending'
    ]);
    
    $startTime = microtime(true);
    
    try {
        // Générer contenu
        $generator = new ContentGenerator();
        $content = $generator->generateArticle([
            'title' => $item['title'],
            'description' => $item['description'] ?? '',
            'link' => $item['link']
        ], $journalist);
        
        if (!$content || empty($content['title'])) {
            throw new Exception('Content generation returned empty');
        }
        
        // Générer image
        $imagePath = null;
        if (!empty($content['image_prompt'])) {
            try {
                $imageGenerator = new ImageGenerator();
                $imagePath = $imageGenerator->generateImage($content['image_prompt'], slugify($content['title']));
                echo "  → Image generated\n";
            } catch (Exception $e) {
                echo "  → WARN: Image failed: " . $e->getMessage() . "\n";
            }
        }
        
        // Créer article
        $articleData = [
            'title' => $content['title'],
            'slug' => slugify($content['title']),
            'content' => $content['content'],
            'excerpt' => $content['excerpt'] ?? '',
            'category' => $content['category'] ?? 'chaos-politique',
            'image_path' => $imagePath,
            'journalist_id' => $journalist['id'],
            'source_title' => $item['title'],
            'source_url' => $item['link'],
            'status' => $autoPublish ? 'published' : 'draft',
            'published_at' => $autoPublish ? date('Y-m-d H:i:s') : null,
            'meta_title' => substr($content['title'], 0, 60),
            'meta_description' => substr(strip_tags($content['excerpt'] ?? $content['content']), 0, 160)
        ];
        
        $articleId = Database::createArticle($articleData);
        $generationTime = microtime(true) - $startTime;
        
        // Mettre à jour log
        Database::updateGenerationLog($logId, [
            'article_id' => $articleId,
            'status' => 'success',
            'model_used' => OPENROUTER_MODEL,
            'generation_time' => round($generationTime, 2)
        ]);
        
        // Static HTML + homepage are generated by regen-static.php (chained after this script in cron)

        $generated++;
        echo "  → SUCCESS: Article #$articleId created (" . round($generationTime, 1) . "s)\n";
        echo "    Title: {$content['title']}\n";
        echo "    By: {$journalist['name']}\n\n";
        
        // Pause entre les générations pour éviter le rate limiting
        if ($generated < $count) {
            sleep(5);
        }
        
    } catch (Exception $e) {
        $generationTime = microtime(true) - $startTime;
        
        Database::updateGenerationLog($logId, [
            'status' => 'error',
            'error_message' => $e->getMessage(),
            'generation_time' => round($generationTime, 2)
        ]);
        
        echo "  → ERROR: " . $e->getMessage() . "\n\n";
        $failed++;
    }
}

// Homepage + static HTML are handled by regen-static.php (runs after this script in cron)

echo "\n=== SUMMARY ===\n";
echo "Generated: $generated\n";
echo "Failed: $failed\n";
echo "Skipped (duplicates): $skipped\n";
echo "================\n";

exit($failed > 0 ? 1 : 0);
