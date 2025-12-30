<?php
/**
 * ToutVaMal.fr - CRON Auto Generate
 * Run every 3 hours: 0 */3 * * * php /path/to/auto-generate.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli' && !defined('CRON_ALLOW')) {
    die('CLI only');
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/api/v1/RSSFetcher.php';
require_once dirname(__DIR__) . '/api/v1/ContentGenerator.php';
require_once dirname(__DIR__) . '/api/v1/ImageGenerator.php';

log_info("=== CRON Auto-Generate Started ===");

try {
    // 1. Get random journalist
    $stmt = db()->query("SELECT * FROM journalists WHERE active = 1 ORDER BY RANDOM() LIMIT 1");
    $journalist = $stmt->fetch();

    if (!$journalist) {
        throw new Exception("No active journalists found");
    }

    log_info("Selected journalist: {$journalist['name']}");

    // 2. Fetch RSS and get new item
    $rssFetcher = new RSSFetcher();
    $rssItem = $rssFetcher->getRandomNewItem();

    if (!$rssItem) {
        log_info("No new RSS items to process");
        exit(0);
    }

    log_info("Processing: {$rssItem['title']}");

    // 3. Generate article content
    $contentGen = new ContentGenerator();
    $articleData = $contentGen->generateArticle($rssItem, $journalist);

    if (!$articleData) {
        throw new Exception("Failed to generate article content");
    }

    log_info("Article generated: {$articleData['title']}");

    // 4. Generate image
    if (!empty($articleData['image_prompt'])) {
        $imageGen = new ImageGenerator();
        $imagePath = $imageGen->generateImage($articleData['image_prompt'], $articleData['slug']);
        $articleData['image_path'] = $imagePath;
    }

    // 5. Save to database
    $stmt = db()->prepare("
        INSERT INTO articles (slug, title, content, excerpt, category, image_path, journalist_id, source_title, source_url)
        VALUES (:slug, :title, :content, :excerpt, :category, :image_path, :journalist_id, :source_title, :source_url)
    ");

    $stmt->execute([
        ':slug' => $articleData['slug'],
        ':title' => $articleData['title'],
        ':content' => $articleData['content'],
        ':excerpt' => $articleData['excerpt'],
        ':category' => $articleData['category'],
        ':image_path' => $articleData['image_path'] ?? null,
        ':journalist_id' => $articleData['journalist_id'],
        ':source_title' => $articleData['source_title'],
        ':source_url' => $articleData['source_url']
    ]);

    $articleId = db()->lastInsertId();

    // 6. Log success
    $stmt = db()->prepare("
        INSERT INTO generation_log (source_url, source_title, article_id, status)
        VALUES (:url, :title, :article_id, 'success')
    ");
    $stmt->execute([
        ':url' => $rssItem['link'],
        ':title' => $rssItem['title'],
        ':article_id' => $articleId
    ]);

    // 7. Generate static HTML file
    generateStaticArticle($articleId);

    // 8. Regenerate homepage
    generateHomepage();

    log_info("=== CRON Complete: Article #{$articleId} published ===");

} catch (Exception $e) {
    log_error("CRON Error: " . $e->getMessage());

    // Log failure
    if (isset($rssItem)) {
        $stmt = db()->prepare("
            INSERT INTO generation_log (source_url, source_title, status, error_message)
            VALUES (:url, :title, 'failed', :error)
        ");
        $stmt->execute([
            ':url' => $rssItem['link'] ?? '',
            ':title' => $rssItem['title'] ?? '',
            ':error' => $e->getMessage()
        ]);
    }

    exit(1);
}

/**
 * Generate static HTML for article
 */
function generateStaticArticle(int $articleId): void {
    $stmt = db()->prepare("
        SELECT a.*, j.name as journalist_name, j.slug as journalist_slug,
               j.role as journalist_role, j.bio as journalist_bio, j.photo_path as journalist_photo
        FROM articles a
        LEFT JOIN journalists j ON a.journalist_id = j.id
        WHERE a.id = :id
    ");
    $stmt->execute([':id' => $articleId]);
    $article = $stmt->fetch();

    if (!$article) return;

    // Buffer the article page
    ob_start();
    $slug = $article['slug'];
    $_SERVER['REQUEST_URI'] = "/articles/{$slug}.html";
    include dirname(__DIR__) . '/article.php';
    $html = ob_get_clean();

    // Save static file
    $filepath = ARTICLES_PATH . '/' . $article['slug'] . '.html';
    file_put_contents($filepath, $html);
    log_info("Generated static article: $filepath");
}

/**
 * Regenerate homepage
 */
function generateHomepage(): void {
    ob_start();
    $_GET = [];
    $_SERVER['REQUEST_URI'] = '/';
    include dirname(__DIR__) . '/index.php';
    $html = ob_get_clean();

    $filepath = ROOT_PATH . '/index.html';
    file_put_contents($filepath, $html);
    log_info("Regenerated homepage");
}
