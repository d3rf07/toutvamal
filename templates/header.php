<?php
/**
 * ToutVaMal.fr - Header Template
 */

$currentCategory = $currentCategory ?? '';
$pageTitle = $pageTitle ?? SITE_NAME . ' - ' . TAGLINE;

// Articles pour le ticker
$tickerArticles = $tickerArticles ?? [];
if (empty($tickerArticles)) {
    $stmt = db()->query("SELECT title, slug FROM articles ORDER BY published_at DESC LIMIT 10");
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
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription ?? 'Les pires nouvelles du jour, tous les jours. Parce que tout va mal.') ?>">

    <link rel="canonical" href="<?= SITE_URL ?><?= $_SERVER['REQUEST_URI'] ?? '' ?>">
    <link rel="stylesheet" href="/css/style.css">

    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.json">

    <meta property="og:type" content="<?= isset($article) ? 'article' : 'website' ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription ?? TAGLINE) ?>">
    <meta property="og:image" content="<?= $ogImage ?? SITE_URL . '/images/og-default.jpg' ?>">
    <meta property="og:url" content="<?= SITE_URL ?><?= $_SERVER['REQUEST_URI'] ?? '' ?>">

    <meta name="twitter:card" content="summary_large_image">
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
<nav class="nav">
    <div class="nav-inner">
        <ul class="nav-list">
            <li><a href="/" class="nav-item <?= empty($currentCategory) ? 'active' : '' ?>">Tout</a></li>
            <?php foreach ($categories as $slug => $name): ?>
                <li><a href="/?cat=<?= $slug ?>" class="nav-item <?= $currentCategory === $slug ? 'active' : '' ?>"><?= $name ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>
