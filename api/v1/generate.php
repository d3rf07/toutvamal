<?php
/**
 * ToutVaMal.fr - Generate Article API
 */

require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/RSSFetcher.php';
require_once __DIR__ . '/ContentGenerator.php';
require_once __DIR__ . '/ImageGenerator.php';

require_api_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$journalistId = $input['journalist_id'] ?? null;

try {
    // 1. Get journalist
    if ($journalistId) {
        $stmt = db()->prepare("SELECT * FROM journalists WHERE id = :id AND active = 1");
        $stmt->execute([':id' => $journalistId]);
    } else {
        $stmt = db()->query("SELECT * FROM journalists WHERE active = 1 ORDER BY RANDOM() LIMIT 1");
    }
    $journalist = $stmt->fetch();

    if (!$journalist) {
        throw new Exception("No journalist found");
    }

    // 2. Get RSS item
    $rssFetcher = new RSSFetcher();
    $rssItem = $rssFetcher->getRandomNewItem();

    if (!$rssItem) {
        throw new Exception("No new RSS items available");
    }

    // 3. Generate content
    $contentGen = new ContentGenerator();
    $articleData = $contentGen->generateArticle($rssItem, $journalist);

    if (!$articleData) {
        throw new Exception("Content generation failed");
    }

    // 4. Generate image
    $imagePath = null;
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

    json_response([
        'success' => true,
        'article' => [
            'id' => $articleId,
            'title' => $articleData['title'],
            'slug' => $articleData['slug'],
            'category' => $articleData['category'],
            'image_path' => $articleData['image_path'] ?? null
        ]
    ]);

} catch (Exception $e) {
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

    log_error("Generate API error: " . $e->getMessage());
    json_response(['success' => false, 'error' => $e->getMessage()], 500);
}
