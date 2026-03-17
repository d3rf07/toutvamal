#!/usr/bin/env php
<?php
/**
 * ToutVaMal.fr - Regenerate static HTML, sitemap & RSS
 * Usage: php cron/regen-static.php [--force]
 *
 * Generates missing .html files, refreshes the homepage,
 * regenerates sitemap.xml and rss.xml.
 * Safe to run repeatedly — skips articles that already have valid HTML unless --force.
 */

if (php_sapi_name() !== "cli") die("CLI only");

chdir(dirname(__DIR__));
require_once "config.php";

$forceRegen = in_array('--force', $argv ?? []);

$db = new PDO("sqlite:" . DB_PATH);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$articles = $db->query("SELECT slug, title, excerpt, content, published_at, updated_at, category, image_path FROM articles WHERE status = 'published' ORDER BY published_at DESC")->fetchAll();

echo "=== Regen Static ===\n";

// --- 1. Generate article HTML pages ---
$generated = 0;
$skipped = 0;
$failed = 0;

foreach ($articles as $a) {
    $slug = $a["slug"];
    $filepath = ARTICLES_PATH . "/" . $slug . ".html";

    // Skip if already exists and is valid (unless --force)
    if (!$forceRegen && file_exists($filepath) && filesize($filepath) > 1000) {
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

// --- 2. Regenerate homepage ---
$_GET = [];
$_SERVER["REQUEST_URI"] = "/";
ob_start();
include dirname(__DIR__) . "/index.php";
$indexHtml = ob_get_clean();
file_put_contents(ROOT_PATH . "/index.html", $indexHtml);
chmod(ROOT_PATH . "/index.html", 0644);

echo "Articles: Generated=$generated | Skipped=$skipped | Failed=$failed | Homepage=" . strlen($indexHtml) . " bytes\n";

// --- 2b. Regenerate static pages (À propos, Équipe) ---
$staticPhpPages = [
    'a-propos' => '/a-propos.html',
    'equipe'   => '/equipe.html',
];
foreach ($staticPhpPages as $page => $output) {
    $_GET = [];
    $_SERVER["REQUEST_URI"] = "/$page.html";
    ob_start();
    include dirname(__DIR__) . "/$page.php";
    $pageHtml = ob_get_clean();
    if ($pageHtml && strlen($pageHtml) > 500) {
        file_put_contents(ROOT_PATH . $output, $pageHtml);
        chmod(ROOT_PATH . $output, 0644);
        echo "Page $page: " . strlen($pageHtml) . " bytes\n";
    } else {
        echo "Page $page: FAILED\n";
    }
}

// --- 3. Generate sitemap.xml ---
$sitemapXml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$sitemapXml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
$sitemapXml .= '        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"' . "\n";
$sitemapXml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

// Homepage
$sitemapXml .= "  <url>\n";
$sitemapXml .= "    <loc>" . SITE_URL . "/</loc>\n";
$sitemapXml .= "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
$sitemapXml .= "    <changefreq>daily</changefreq>\n";
$sitemapXml .= "    <priority>1.0</priority>\n";
$sitemapXml .= "  </url>\n";

// Static pages
$staticPages = [
    ['loc' => '/a-propos.html', 'priority' => '0.6', 'changefreq' => 'monthly'],
    ['loc' => '/equipe.html', 'priority' => '0.6', 'changefreq' => 'monthly'],
    ['loc' => '/contact.html', 'priority' => '0.4', 'changefreq' => 'yearly'],
    ['loc' => '/mentions-legales.html', 'priority' => '0.2', 'changefreq' => 'yearly'],
    ['loc' => '/cgu.html', 'priority' => '0.2', 'changefreq' => 'yearly'],
    ['loc' => '/confidentialite.html', 'priority' => '0.2', 'changefreq' => 'yearly'],
];

foreach ($staticPages as $page) {
    $sitemapXml .= "  <url>\n";
    $sitemapXml .= "    <loc>" . SITE_URL . $page['loc'] . "</loc>\n";
    $sitemapXml .= "    <changefreq>" . $page['changefreq'] . "</changefreq>\n";
    $sitemapXml .= "    <priority>" . $page['priority'] . "</priority>\n";
    $sitemapXml .= "  </url>\n";
}

// Category pages
foreach (CATEGORIES as $catSlug => $catName) {
    $sitemapXml .= "  <url>\n";
    $sitemapXml .= "    <loc>" . SITE_URL . "/?cat=" . $catSlug . "</loc>\n";
    $sitemapXml .= "    <changefreq>daily</changefreq>\n";
    $sitemapXml .= "    <priority>0.7</priority>\n";
    $sitemapXml .= "  </url>\n";
}

// Articles with news tags for recent ones
$twoWeeksAgo = date('Y-m-d', strtotime('-14 days'));
foreach ($articles as $article) {
    $lastmod = $article['updated_at'] ?? $article['published_at'];
    $sitemapXml .= "  <url>\n";
    $sitemapXml .= "    <loc>" . SITE_URL . "/articles/" . htmlspecialchars($article['slug']) . ".html</loc>\n";
    $sitemapXml .= "    <lastmod>" . date('Y-m-d', strtotime($lastmod)) . "</lastmod>\n";
    $sitemapXml .= "    <changefreq>monthly</changefreq>\n";
    $sitemapXml .= "    <priority>0.8</priority>\n";

    // Image tag for SEO
    if (!empty($article['image_path'])) {
        $sitemapXml .= "    <image:image>\n";
        $sitemapXml .= "      <image:loc>" . SITE_URL . htmlspecialchars($article['image_path']) . "</image:loc>\n";
        $sitemapXml .= "      <image:title>" . htmlspecialchars($article['title']) . "</image:title>\n";
        $sitemapXml .= "    </image:image>\n";
    }

    // Google News tag for articles < 2 weeks old
    if ($article['published_at'] >= $twoWeeksAgo) {
        $sitemapXml .= "    <news:news>\n";
        $sitemapXml .= "      <news:publication>\n";
        $sitemapXml .= "        <news:name>ToutVaMal.fr</news:name>\n";
        $sitemapXml .= "        <news:language>fr</news:language>\n";
        $sitemapXml .= "      </news:publication>\n";
        $sitemapXml .= "      <news:publication_date>" . date('c', strtotime($article['published_at'])) . "</news:publication_date>\n";
        $sitemapXml .= "      <news:title>" . htmlspecialchars($article['title']) . "</news:title>\n";
        $sitemapXml .= "    </news:news>\n";
    }

    $sitemapXml .= "  </url>\n";
}

$sitemapXml .= "</urlset>\n";

file_put_contents(ROOT_PATH . "/sitemap.xml", $sitemapXml);
chmod(ROOT_PATH . "/sitemap.xml", 0644);
echo "Sitemap: " . count($articles) . " articles + " . count($staticPages) . " pages + " . count(CATEGORIES) . " categories\n";

// --- 3b. Ping search engines to notify of sitemap update ---
$sitemapUrl = SITE_URL . '/sitemap.xml';
$pingUrls = [
    'https://www.google.com/webmasters/tools/ping?sitemap=' . urlencode($sitemapUrl),
    'https://www.bing.com/ping?sitemap=' . urlencode($sitemapUrl),
];
foreach ($pingUrls as $pingUrl) {
    $ch = curl_init($pingUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $pingResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $engine = strpos($pingUrl, 'google') !== false ? 'Google' : 'Bing';
    echo "Ping $engine: HTTP $httpCode\n";
}

// --- 4. Generate RSS feed ---
$rssXml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$rssXml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/">' . "\n";
$rssXml .= "<channel>\n";
$rssXml .= "  <title>ToutVaMal.fr</title>\n";
$rssXml .= "  <link>" . SITE_URL . "</link>\n";
$rssXml .= "  <description>C'ÉTAIT MIEUX AVANT, MAIS CE SERA PIRE DEMAIN - Site satirique français</description>\n";
$rssXml .= "  <language>fr-FR</language>\n";
$rssXml .= "  <lastBuildDate>" . date('r') . "</lastBuildDate>\n";
$rssXml .= '  <atom:link href="' . SITE_URL . '/rss.xml" rel="self" type="application/rss+xml"/>' . "\n";
$rssXml .= "  <image>\n";
$rssXml .= "    <url>" . SITE_URL . "/logo-toutvamal.png</url>\n";
$rssXml .= "    <title>ToutVaMal.fr</title>\n";
$rssXml .= "    <link>" . SITE_URL . "</link>\n";
$rssXml .= "  </image>\n";

// Last 20 articles
$rssArticles = array_slice($articles, 0, 20);
foreach ($rssArticles as $article) {
    $articleUrl = SITE_URL . '/articles/' . htmlspecialchars($article['slug']) . '.html';
    $excerpt = htmlspecialchars($article['excerpt'] ?? substr(strip_tags($article['content']), 0, 300));
    $pubDate = date('r', strtotime($article['published_at']));
    $category = htmlspecialchars(CATEGORIES[$article['category']] ?? $article['category']);

    $rssXml .= "  <item>\n";
    $rssXml .= "    <title>" . htmlspecialchars($article['title']) . "</title>\n";
    $rssXml .= "    <link>" . $articleUrl . "</link>\n";
    $rssXml .= "    <guid isPermaLink=\"true\">" . $articleUrl . "</guid>\n";
    $rssXml .= "    <pubDate>" . $pubDate . "</pubDate>\n";
    $rssXml .= "    <category>" . $category . "</category>\n";
    $rssXml .= "    <description><![CDATA[" . $excerpt . "]]></description>\n";

    if (!empty($article['image_path'])) {
        $rssXml .= '    <media:content url="' . SITE_URL . htmlspecialchars($article['image_path']) . '" medium="image"/>' . "\n";
    }

    $rssXml .= "  </item>\n";
}

$rssXml .= "</channel>\n";
$rssXml .= "</rss>\n";

file_put_contents(ROOT_PATH . "/rss.xml", $rssXml);
chmod(ROOT_PATH . "/rss.xml", 0644);
echo "RSS: " . count($rssArticles) . " articles\n";

echo "=== Done ===\n";
