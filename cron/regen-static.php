#!/usr/bin/env php
<?php
/**
 * ToutVaMal.fr - Regenerate static HTML for all published articles
 * Usage: php cron/regen-static.php
 *
 * Generates missing .html files and refreshes the homepage.
 * Safe to run repeatedly — skips articles that already have valid HTML.
 */

if (php_sapi_name() !== "cli") die("CLI only");

chdir(dirname(__DIR__));
require_once "config.php";

$db = new PDO("sqlite:" . DB_PATH);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$articles = $db->query("SELECT slug FROM articles WHERE status = 'published' ORDER BY published_at DESC")->fetchAll();

$generated = 0;
$skipped = 0;
$failed = 0;

foreach ($articles as $a) {
    $slug = $a["slug"];
    $filepath = ARTICLES_PATH . "/" . $slug . ".html";

    // Skip if already exists and is valid
    if (file_exists($filepath) && filesize($filepath) > 1000) {
        $skipped++;
        continue;
    }

    $html = (function($s) {
        $_GET["slug"] = $s;
        $_SERVER["REQUEST_URI"] = "/articles/" . $s . ".html";
        ob_start();
        include dirname(__DIR__) . "/article.php";
        return ob_get_clean();
    })($slug);

    if ($html && strlen($html) > 1000) {
        file_put_contents($filepath, $html);
        chmod($filepath, 0644);
        $generated++;
        echo "OK: $slug (" . strlen($html) . " bytes)\n";
    } else {
        $failed++;
        echo "FAIL: $slug\n";
    }
}

// Regenerate homepage
$_GET = [];
$_SERVER["REQUEST_URI"] = "/";
ob_start();
include dirname(__DIR__) . "/index.php";
$indexHtml = ob_get_clean();
file_put_contents(ROOT_PATH . "/index.html", $indexHtml);
chmod(ROOT_PATH . "/index.html", 0644);

echo "Generated: $generated | Skipped: $skipped | Failed: $failed | Homepage: " . strlen($indexHtml) . " bytes\n";
