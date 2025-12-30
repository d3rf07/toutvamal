<?php
/**
 * ToutVaMal.fr - Generate Static Files
 * Generates static HTML for all articles and homepage
 */

require_once dirname(__DIR__) . '/config.php';

echo "=== Generating Static Files ===\n\n";

$baseUrl = SITE_URL;

// 1. Generate static articles
echo "Generating articles...\n";
$stmt = db()->query("SELECT id, slug, title FROM articles ORDER BY id");
$articles = $stmt->fetchAll();

foreach ($articles as $article) {
    $url = $baseUrl . '/article.php?slug=' . $article['slug'];
    $html = file_get_contents($url);

    if ($html) {
        $filepath = ARTICLES_PATH . '/' . $article['slug'] . '.html';
        file_put_contents($filepath, $html);
        echo "  - {$article['title']}\n";
    } else {
        echo "  ! ERREUR: {$article['title']}\n";
    }
}

echo "Generated " . count($articles) . " article files.\n\n";

// 2. Generate homepage
echo "Generating homepage...\n";
$html = file_get_contents($baseUrl . '/index.php');

if ($html) {
    file_put_contents(ROOT_PATH . '/index.html', $html);
    echo "Homepage generated.\n\n";
} else {
    echo "Homepage generation failed!\n\n";
}

echo "=== Static Generation Complete ===\n";
