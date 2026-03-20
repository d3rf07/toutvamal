<?php
/**
 * ToutVaMal.fr - Header Template (SEO Optimized)
 */

// Inclure les fonctions SEO si pas encore chargées
if (!function_exists('schema_organization')) {
    require_once dirname(__DIR__) . '/seo-functions.php';
}

$currentCategory = $currentCategory ?? '';
$pageTitle = $pageTitle ?? SITE_NAME . ' - ' . TAGLINE;
$pageDescription = $pageDescription ?? 'Les pires nouvelles du jour, tous les jours. Parce que tout va mal.';
$ogImage = $ogImage ?? SITE_URL . '/images/og-default.jpg';
// Canonical sans query string : strip les query params
$_requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$canonicalUrl = $canonicalUrl ?? (SITE_URL . $_requestPath);

// Articles pour le ticker
$tickerArticles = $tickerArticles ?? [];
if (empty($tickerArticles)) {
    $stmt = db()->query("SELECT title, slug FROM articles WHERE status = 'published' OR status IS NULL ORDER BY published_at DESC LIMIT 10");
    $tickerArticles = $stmt->fetchAll();
}

$categories = CATEGORIES;
$today = format_date_full();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO Primaire -->
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="author" content="<?= SITE_NAME ?>">
    <meta name="language" content="fr">

    <!-- Canonical -->
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">

    <!-- Fonts : preconnect + preload non-bloquant (remplacement @import) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Merriweather:ital,wght@0,400;0,700;1,400&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Merriweather:ital,wght@0,400;0,700;1,400&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap"></noscript>

    <!-- CSS -->
    <link rel="stylesheet" href="/css/style.css">

    <!-- Favicons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#DC2626">

    <!-- RSS -->
    <link rel="alternate" type="application/rss+xml" title="<?= SITE_NAME ?> RSS" href="<?= SITE_URL ?>/rss.xml">

    <!-- OpenGraph -->
    <meta property="og:type" content="<?= isset($article) ? 'article' : 'website' ?>">
    <meta property="og:site_name" content="<?= SITE_NAME ?>">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
<?php if (isset($article)): ?>
    <meta property="article:published_time" content="<?= date('c', strtotime($article['published_at'])) ?>">
    <meta property="article:modified_time" content="<?= date('c', strtotime($article['updated_at'] ?? $article['published_at'])) ?>">
    <meta property="article:author" content="<?= SITE_URL ?>/equipe.html">
    <meta property="article:section" content="<?= htmlspecialchars(CATEGORIES[$article['category']] ?? $article['category']) ?>">
    <meta property="article:tag" content="satire">
    <meta property="article:tag" content="humour">
    <meta property="article:tag" content="actualité">
<?php endif; ?>

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@toutvamal">
    <meta name="twitter:creator" content="@toutvamal">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars(substr($pageDescription, 0, 200)) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>">
    <meta name="twitter:image:alt" content="<?= htmlspecialchars($pageTitle) ?>">


<?php
// Default global schema for pages without explicit schema
if (!isset($extraSchema) || trim((string)$extraSchema) === '') {
    $defaultSchemaData = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                'name' => SITE_NAME,
                'url' => SITE_URL,
                'logo' => SITE_URL . '/logo-toutvamal.png',
                'sameAs' => []
            ],
            [
                '@type' => 'WebSite',
                'name' => SITE_NAME,
                'url' => SITE_URL,
                'inLanguage' => 'fr-FR',
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => SITE_URL . '/?s={search_term_string}',
                    'query-input' => 'required name=search_term_string'
                ]
            ]
        ]
    ];
    $extraSchema = '<script type="application/ld+json">' . json_encode($defaultSchemaData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
}
?>

<?php if (isset($extraSchema)): ?>
    <?= $extraSchema ?>
<?php endif; ?>

    <!-- Plausible Analytics (cookie-free, RGPD-compliant) -->
    <script defer data-domain="toutvamal.fr" src="https://plausible.d3rf.com/js/script.js"></script>
    <!-- Consentement cookies (Clarity) -->
    <script defer src="/js/consent.js"></script>

</head>
<body>

<!-- Breaking News Ticker -->
<div class="ticker">
    <div class="ticker-label">ALERTE</div>
    <div class="ticker-content">
        <?php foreach ($tickerArticles as $item): ?>
            <a href="/articles/<?= htmlspecialchars($item['slug']) ?>.html" class="ticker-item">
                <?= htmlspecialchars($item['title']) ?>
            </a>
        <?php endforeach; ?>
        <?php foreach ($tickerArticles as $item): ?>
            <a href="/articles/<?= htmlspecialchars($item['slug']) ?>.html" class="ticker-item">
                <?= htmlspecialchars($item['title']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Header -->
<header class="header">
    <div class="header-inner">
        <div class="header-date"><?= $today ?></div>
        <div class="header-logo">
            <a href="/" class="logo">TOUT<span>VA</span>MAL</a>
            <div class="tagline"><?= TAGLINE ?></div>
        </div>
        <div class="header-newsletter">
            <a href="#newsletter">S'abonner</a>
        </div>
    </div>
</header>

<!-- Navigation -->
<nav class="nav" aria-label="Navigation principale">
    <div class="nav-inner">
        <ul class="nav-list">
            <li><a href="/" class="nav-item <?= empty($currentCategory) ? 'active' : '' ?>">Tout</a></li>
            <?php foreach ($categories as $slug => $name): ?>
                <li><a href="/?cat=<?= $slug ?>" class="nav-item <?= $currentCategory === $slug ? 'active' : '' ?>"><?= $name ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>
