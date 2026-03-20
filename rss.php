<?php
/**
 * ToutVaMal.fr - Flux RSS
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/rss+xml; charset=utf-8');
header('X-Robots-Tag: noindex');

$stmt = db()->query("
    SELECT a.*, j.name as journalist_name
    FROM articles a
    LEFT JOIN journalists j ON a.journalist_id = j.id
    WHERE a.status = 'published' OR a.status IS NULL
    ORDER BY a.published_at DESC
    LIMIT 50
");
$articles = $stmt->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0"
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:media="http://search.yahoo.com/mrss/">
    <channel>
        <title><?= htmlspecialchars(SITE_NAME) ?></title>
        <link><?= SITE_URL ?></link>
        <description><?= htmlspecialchars(TAGLINE) ?> - Site satirique français</description>
        <language>fr-FR</language>
        <lastBuildDate><?= date('r') ?></lastBuildDate>
        <atom:link href="<?= SITE_URL ?>/rss.xml" rel="self" type="application/rss+xml"/>
        <image>
            <url><?= SITE_URL ?>/logo-toutvamal.png</url>
            <title><?= htmlspecialchars(SITE_NAME) ?></title>
            <link><?= SITE_URL ?></link>
        </image>
        <copyright>Copyright <?= date('Y') ?> <?= SITE_NAME ?></copyright>
        <webMaster>contact@toutvamal.fr (ToutVaMal.fr)</webMaster>
        <ttl>60</ttl>

<?php foreach ($articles as $article): ?>
        <item>
            <title><?= htmlspecialchars($article['title']) ?></title>
            <link><?= SITE_URL ?>/articles/<?= htmlspecialchars($article['slug']) ?>.html</link>
            <guid isPermaLink="true"><?= SITE_URL ?>/articles/<?= htmlspecialchars($article['slug']) ?>.html</guid>
            <pubDate><?= date('r', strtotime($article['published_at'])) ?></pubDate>
            <dc:creator><?= htmlspecialchars($article['journalist_name'] ?? 'La Rédaction') ?></dc:creator>
            <category><?= htmlspecialchars(CATEGORIES[$article['category']] ?? $article['category']) ?></category>
            <description><![CDATA[<?= htmlspecialchars($article['excerpt'] ?? substr(strip_tags($article['content']), 0, 300) . '...') ?>]]></description>
<?php if ($article['image_path']): ?>
            <media:content url="<?= SITE_URL . htmlspecialchars($article['image_path']) ?>" medium="image"/>
            <enclosure url="<?= SITE_URL . htmlspecialchars($article['image_path']) ?>" type="image/jpeg"/>
<?php endif; ?>
        </item>
<?php endforeach; ?>

    </channel>
</rss>
