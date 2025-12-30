<?php
/**
 * ToutVaMal.fr - Deploy Script
 * Run after any code changes to regenerate static files and verify
 */

echo "=== DEPLOY ToutVaMal.fr ===\n\n";

// Step 1: Fix permissions
echo "1. Fixing permissions...\n";
$publicHtml = '/home/u443792660/domains/toutvamal.fr/public_html';
exec("find $publicHtml -type f -name '*.php' -exec chmod 644 {} \\;");
exec("find $publicHtml -type f -name '*.html' -exec chmod 644 {} \\;");
exec("find $publicHtml -type f -name '*.css' -exec chmod 644 {} \\;");
exec("find $publicHtml -type f -name '*.js' -exec chmod 644 {} \\;");
echo "   Done.\n\n";

// Step 2: Generate static files
echo "2. Generating static files...\n";
require_once dirname(__DIR__) . '/config.php';

$baseUrl = SITE_URL;
$stmt = db()->query("SELECT id, slug, title FROM articles ORDER BY id");
$articles = $stmt->fetchAll();

foreach ($articles as $article) {
    $url = $baseUrl . '/article.php?slug=' . $article['slug'];
    $html = @file_get_contents($url);
    if ($html) {
        $filepath = ARTICLES_PATH . '/' . $article['slug'] . '.html';
        file_put_contents($filepath, $html);
        chmod($filepath, 0644);
        echo "   - {$article['title']}\n";
    }
}

// Homepage
$html = @file_get_contents($baseUrl . '/index.php');
if ($html) {
    file_put_contents(ROOT_PATH . '/index.html', $html);
    chmod(ROOT_PATH . '/index.html', 0644);
    echo "   - Homepage\n";
}
echo "\n";

// Step 3: Run QA
echo "3. Running QA checks...\n\n";
include __DIR__ . '/qa-check.php';
