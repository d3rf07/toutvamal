<?php
/**
 * ToutVaMal.fr - Page Catégorie avec SEO
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/seo-functions.php';

// Récupérer la catégorie depuis l'URL
$categorySlug = $_GET['cat'] ?? '';

// Support des URLs propres /categorie/xxx
if (empty($categorySlug)) {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (preg_match('#^/categorie/([a-z0-9-]+)/?$#', $uri, $matches)) {
        $categorySlug = $matches[1];
    }
}

// Vérifier que la catégorie existe
if (empty($categorySlug) || !isset(CATEGORIES[$categorySlug])) {
    header('Location: /');
    exit;
}

$categoryName = CATEGORIES[$categorySlug];

// Récupérer les articles de cette catégorie
$stmt = db()->prepare("
    SELECT a.*, j.name as journalist_name, j.slug as journalist_slug, j.photo_path as journalist_photo
    FROM articles a
    LEFT JOIN journalists j ON a.journalist_id = j.id
    WHERE a.category = :category
    AND (a.status = 'published' OR a.status IS NULL)
    ORDER BY a.published_at DESC
");
$stmt->execute([':category' => $categorySlug]);
$articles = $stmt->fetchAll();

// SEO
$pageTitle = $categoryName . ' - Actualités satiriques | ' . SITE_NAME;
$pageDescription = "Toutes les actualités satiriques dans la catégorie $categoryName sur ToutVaMal.fr. Les pires nouvelles du jour, avec humour et dérision.";
$canonicalUrl = SITE_URL . '/categorie/' . $categorySlug;
$pageType = 'website';
$ogImage = SITE_URL . '/images/og-default.jpg';

// Breadcrumbs
$breadcrumbs = [
    ['name' => 'Accueil', 'url' => SITE_URL],
    ['name' => $categoryName, 'url' => $canonicalUrl]
];

// Schema.org CollectionPage
$collectionSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $categoryName,
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'isPartOf' => [
        '@type' => 'WebSite',
        '@id' => SITE_URL . '/#website'
    ],
    'about' => [
        '@type' => 'Thing',
        'name' => $categoryName
    ],
    'numberOfItems' => count($articles)
];
$extraSchema = '<script type="application/ld+json">' . json_encode($collectionSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

include __DIR__ . '/templates/header.php';
?>

<main class="container main-layout">
    <div class="content">
        <!-- Category Header -->
        <header style="margin-bottom: 2rem;">
            <h1 style="font-family: var(--font-display); font-size: 2.5rem; margin-bottom: 0.5rem;">
                <?= htmlspecialchars($categoryName) ?>
            </h1>
            <p style="color: var(--gris-600);">
                <?= count($articles) ?> article<?= count($articles) > 1 ? 's' : '' ?> dans cette catégorie
            </p>
        </header>

        <?php if (empty($articles)): ?>
        <div style="text-align: center; padding: 4rem 0; color: var(--gris-500);">
            <p style="font-size: 1.25rem;">Aucun article dans cette catégorie pour l'instant.</p>
            <p>Mais ne vous inquiétez pas, les mauvaises nouvelles arrivent toujours.</p>
            <a href="/" style="display: inline-block; margin-top: 2rem; color: var(--rouge);">← Retour à l'accueil</a>
        </div>
        <?php else: ?>

        <!-- Articles Grid -->
        <div class="articles-grid">
            <?php foreach ($articles as $article): ?>
            <a href="/articles/<?= htmlspecialchars($article['slug']) ?>.html" class="article-card">
                <div class="article-card-image">
                    <img src="<?= htmlspecialchars($article['image_path'] ?: '/images/placeholder.jpg') ?>"
                         alt="<?= htmlspecialchars($article['title']) ?>"
                         width="400" height="225" loading="lazy">
                </div>
                <div class="article-card-content">
                    <h2 style="font-size: 1.1rem; line-height: 1.4; margin-bottom: 0.5rem;">
                        <?= htmlspecialchars($article['title']) ?>
                    </h2>
                    <p style="font-size: 0.9rem; color: var(--gris-600); line-height: 1.5;">
                        <?= htmlspecialchars($article['excerpt'] ?? substr(strip_tags($article['content']), 0, 150) . '...') ?>
                    </p>
                    <div class="meta" style="margin-top: 1rem;">
                        <?php if ($article['journalist_name']): ?>
                        <span>
                            <img src="<?= htmlspecialchars($article['journalist_photo'] ?: '/images/equipe/default.webp') ?>"
                                 alt="" style="width: 24px; height: 24px; border-radius: 50%; vertical-align: middle; margin-right: 0.25rem;">
                            <?= htmlspecialchars($article['journalist_name']) ?>
                        </span>
                        <?php endif; ?>
                        <span><time datetime="<?= date('c', strtotime($article['published_at'])) ?>"><?= format_date($article['published_at']) ?></time></span>
                        <span><?= reading_time($article['content']) ?> min</span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/templates/sidebar.php'; ?>
</main>

<?php include __DIR__ . '/templates/footer.php'; ?>
