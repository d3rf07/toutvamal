<?php
/**
 * ToutVaMal.fr - Homepage
 * Rebuild 2025-12-30
 */

require_once __DIR__ . '/config.php';

// Filtrage par catégorie
$currentCategory = $_GET['cat'] ?? '';
$categoryFilter = '';
$params = [];

if (!empty($currentCategory) && isset(CATEGORIES[$currentCategory])) {
    $categoryFilter = 'WHERE category = :category';
    $params[':category'] = $currentCategory;
}

// Récupérer les articles
$sql = "SELECT a.*, j.name as journalist_name, j.photo_path as journalist_photo
        FROM articles a
        LEFT JOIN journalists j ON a.journalist_id = j.id
        $categoryFilter
        ORDER BY a.published_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

// Séparer hero et grille
$heroMain = array_shift($articles) ?: null;
$heroSide = array_splice($articles, 0, 2);
$gridArticles = $articles;

// Récupérer l'équipe pour la preview
$stmt = db()->query("SELECT * FROM journalists WHERE active = 1 ORDER BY id LIMIT 5");
$team = $stmt->fetchAll();

$pageTitle = SITE_NAME . ' - ' . TAGLINE;
$pageDescription = 'Les pires nouvelles du jour, tous les jours. Parce que tout va mal.';

include __DIR__ . '/templates/header.php';
?>

<main class="container main-layout">
    <div class="content">
        <?php if ($heroMain): ?>
        <!-- Hero Section -->
        <section class="hero">
            <a href="/articles/<?= htmlspecialchars($heroMain['slug']) ?>.html" class="hero-main">
                <img src="<?= htmlspecialchars($heroMain['image_path'] ?: '/images/placeholder.jpg') ?>"
                     alt="<?= htmlspecialchars($heroMain['title']) ?>">
                <div class="hero-main-overlay">
                    <span class="badge"><?= htmlspecialchars(CATEGORIES[$heroMain['category']] ?? $heroMain['category']) ?></span>
                    <h2><?= htmlspecialchars($heroMain['title']) ?></h2>
                    <div class="meta">
                        <span><?= htmlspecialchars($heroMain['journalist_name'] ?? 'La Rédaction') ?></span>
                        <span><?= format_date($heroMain['published_at']) ?></span>
                        <span><?= reading_time($heroMain['content']) ?> min de lecture</span>
                    </div>
                </div>
            </a>

            <div class="hero-sidebar">
                <?php foreach ($heroSide as $article): ?>
                <a href="/articles/<?= htmlspecialchars($article['slug']) ?>.html" class="hero-card">
                    <img src="<?= htmlspecialchars($article['image_path'] ?: '/images/placeholder.jpg') ?>"
                         alt="<?= htmlspecialchars($article['title']) ?>">
                    <div class="hero-card-overlay">
                        <span class="badge" style="font-size: 0.625rem; padding: 0.125rem 0.5rem;">
                            <?= htmlspecialchars(CATEGORIES[$article['category']] ?? $article['category']) ?>
                        </span>
                        <h3><?= htmlspecialchars($article['title']) ?></h3>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Articles Grid -->
        <?php if (!empty($gridArticles)): ?>
        <section class="articles-section">
            <h2 style="font-family: var(--font-display); font-size: 1.5rem; margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 2px solid var(--noir);">
                Dernières Catastrophes
            </h2>
            <div class="articles-grid">
                <?php foreach ($gridArticles as $article): ?>
                <a href="/articles/<?= htmlspecialchars($article['slug']) ?>.html" class="article-card">
                    <div class="article-card-image">
                        <img src="<?= htmlspecialchars($article['image_path'] ?: '/images/placeholder.jpg') ?>"
                             alt="<?= htmlspecialchars($article['title']) ?>">
                    </div>
                    <div class="article-card-content">
                        <span class="badge badge-outline"><?= htmlspecialchars(CATEGORIES[$article['category']] ?? $article['category']) ?></span>
                        <h3><?= htmlspecialchars($article['title']) ?></h3>
                        <p><?= htmlspecialchars($article['excerpt'] ?? substr(strip_tags($article['content']), 0, 150) . '...') ?></p>
                        <div class="meta">
                            <span><?= htmlspecialchars($article['journalist_name'] ?? 'La Rédaction') ?></span>
                            <span><?= reading_time($article['content']) ?> min</span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/templates/sidebar.php'; ?>
</main>

<!-- Team Section -->
<?php if (!empty($team)): ?>
<section class="team-section">
    <h2>La Rédaction du Malheur</h2>
    <div class="team-grid">
        <?php foreach ($team as $member): ?>
        <a href="/equipe/<?= htmlspecialchars($member['slug']) ?>.html" class="team-card">
            <img src="<?= htmlspecialchars($member['photo_path'] ?: '/images/equipe/default.jpg') ?>"
                 alt="<?= htmlspecialchars($member['name']) ?>">
            <h4><?= htmlspecialchars($member['name']) ?></h4>
            <p><?= htmlspecialchars($member['role']) ?></p>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/templates/footer.php'; ?>
