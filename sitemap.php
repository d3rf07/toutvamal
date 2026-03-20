<?php
/**
 * ToutVaMal.fr - Sitemap XML Dynamique
 * Génère un sitemap conforme aux standards Google
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">

    <!-- Page d'accueil -->
    <url>
        <loc><?= SITE_URL ?>/</loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    <!-- Pages statiques -->
    <url>
        <loc><?= SITE_URL ?>/archives.html</loc>
        <lastmod><?= date("Y-m-d") ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.7</priority>
    </url>
    <url>
        <loc><?= SITE_URL ?>/equipe.html</loc>
        <lastmod><?= date('Y-m-d', filemtime(__DIR__ . '/equipe.html')) ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    <url>
        <loc><?= SITE_URL ?>/a-propos.html</loc>
        <lastmod><?= date('Y-m-d', filemtime(__DIR__ . '/a-propos.html')) ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>

    <!-- Catégories -->
<?php foreach (CATEGORIES as $slug => $name): ?>
    <url>
        <loc><?= SITE_URL ?>/categorie/<?= $slug ?></loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.7</priority>
    </url>
<?php endforeach; ?>

    <!-- Articles -->
<?php
$stmt = db()->query("
    SELECT slug, title, image_path, published_at, updated_at
    FROM articles
    WHERE status = 'published' OR status IS NULL
    ORDER BY published_at DESC
");
$articles = $stmt->fetchAll();

foreach ($articles as $article):
    $lastmod = $article['updated_at'] ?? $article['published_at'];
    $lastmod = date('Y-m-d', strtotime($lastmod));
    $pubDate = date('Y-m-d', strtotime($article['published_at']));
    $isRecent = (time() - strtotime($article['published_at'])) < 172800; // 48h
?>
    <url>
        <loc><?= SITE_URL ?>/articles/<?= htmlspecialchars($article['slug']) ?>.html</loc>
        <lastmod><?= $lastmod ?></lastmod>
        <changefreq><?= $isRecent ? 'hourly' : 'weekly' ?></changefreq>
        <priority><?= $isRecent ? '0.9' : '0.8' ?></priority>
<?php if ($isRecent): ?>
        <news:news>
            <news:publication>
                <news:name>ToutVaMal.fr</news:name>
                <news:language>fr</news:language>
            </news:publication>
            <news:publication_date><?= date('c', strtotime($article['published_at'])) ?></news:publication_date>
            <news:title><?= htmlspecialchars($article['title']) ?></news:title>
        </news:news>
<?php endif; ?>
<?php if ($article['image_path']): ?>
        <image:image>
            <image:loc><?= SITE_URL . htmlspecialchars($article['image_path']) ?></image:loc>
            <image:title><?= htmlspecialchars($article['title']) ?></image:title>
        </image:image>
<?php endif; ?>
    </url>
<?php endforeach; ?>

    <!-- Profils journalistes -->
<?php
$stmt = db()->query("SELECT slug, name, updated_at FROM journalists WHERE active = 1");
$journalists = $stmt->fetchAll();

foreach ($journalists as $journalist):
    $lastmod = $journalist['updated_at'] ? date('Y-m-d', strtotime($journalist['updated_at'])) : date('Y-m-d');
?>
    <url>
        <loc><?= SITE_URL ?>/equipe/<?= htmlspecialchars($journalist['slug']) ?>.html</loc>
        <lastmod><?= $lastmod ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
<?php endforeach; ?>

</urlset>
