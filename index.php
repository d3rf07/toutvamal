<?php
/**
 * ToutVaMal.fr - Homepage
 * Rebuild 2025-12-30
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/seo-functions.php';

// Filtrage par catégorie
$currentCategory = $_GET['cat'] ?? '';
$categoryFilter = '';
$params = [];

if (!empty($currentCategory) && isset(CATEGORIES[$currentCategory])) {
    $categoryFilter = "WHERE a.status = 'published' AND category = :category";
    $params[':category'] = $currentCategory;
} else {
    $categoryFilter = "WHERE a.status = 'published'";
}

// Récupérer les articles (tous pour hero + comptage total)
$sql = "SELECT a.*, j.name as journalist_name, j.photo_path as journalist_photo
        FROM articles a
        LEFT JOIN journalists j ON a.journalist_id = j.id
        $categoryFilter
        ORDER BY a.published_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$allArticles = $stmt->fetchAll();
$totalArticles = count($allArticles);

// Séparer hero et grille
$heroMain = array_shift($allArticles) ?: null;
$heroSide = array_splice($allArticles, 0, 2);
// On passe tous les gridArticles au template (la pagination JS masque les suivants)
$gridArticles = $allArticles;

// Récupérer l'équipe pour la preview
$stmt = db()->query("SELECT * FROM journalists WHERE active = 1 ORDER BY id LIMIT 5");
$team = $stmt->fetchAll();

$pageTitle = SITE_NAME . ' - ' . TAGLINE;
$canonicalUrl = SITE_URL . '/'; // Homepage canonical: toujours / sans query string
$pageDescription = 'Les pires nouvelles du jour, tous les jours. Parce que tout va mal.';

include __DIR__ . '/templates/header.php';
?>

<main class="container main-layout">
    <!-- H1 semantique pour SEO - le logo graphique fait office de titre visuel -->
    <h1 style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;">ToutVaMal.fr - Les pires nouvelles du jour, tous les jours</h1>
    <div class="content">
        <?php if ($heroMain): ?>
        <!-- Hero Section -->
        <section class="hero">
            <a href="/articles/<?= htmlspecialchars($heroMain['slug']) ?>.html" class="hero-main">
                <img src="<?= htmlspecialchars($heroMain['image_path'] ?: '/images/placeholder.jpg') ?>"
                     alt="<?= htmlspecialchars($heroMain['title']) ?>" fetchpriority="high" decoding="async">
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
                         alt="<?= htmlspecialchars($article['title']) ?>" loading="lazy" decoding="async">
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
                <?php $_cardIdx = 0; foreach ($gridArticles as $article): ?>
                <?php $_cardIdx++; ?>
                <a href="/articles/<?= htmlspecialchars($article['slug']) ?>.html" class="article-card" data-card-index="<?= $_cardIdx ?>">
                    <div class="article-card-image">
                        <img src="<?= htmlspecialchars($article['image_path'] ?: '/images/placeholder.jpg') ?>"
                             alt="<?= htmlspecialchars($article['title']) ?>"
                             loading="lazy" width="400" height="225">
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

            <!-- Pagination Load More -->
            <?php if (count($gridArticles) > 20): ?>
            <div id="load-more-container" style="text-align:center; margin: 2rem 0; display:none;">
                <button id="load-more-btn" onclick="tvmLoadMore()" style="font-family:var(--font-display,Impact,sans-serif);font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;background:var(--noir,#1a1a1a);color:var(--blanc,#fff);border:none;padding:.875rem 2.5rem;cursor:pointer;" onmouseover="this.style.background='#c0392b'" onmouseout="this.style.background='var(--noir,#1a1a1a)'">
                    Charger plus de catastrophes
                    <span id="load-more-remaining" style="font-size:.75em;opacity:.7;"></span>
                </button>
            </div>
            <?php endif; ?>
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


<script>
// ========== PAGINATION LOAD MORE ==========
(function() {
    var INITIAL_VISIBLE = 20;
    var LOAD_MORE_COUNT = 20;

    var cards = document.querySelectorAll('.articles-grid .article-card[data-card-index]');
    var total = cards.length;

    if (total <= INITIAL_VISIBLE) return;

    cards.forEach(function(card) {
        var idx = parseInt(card.getAttribute('data-card-index'), 10);
        if (idx > INITIAL_VISIBLE) {
            card.style.display = 'none';
        }
    });

    var visible = INITIAL_VISIBLE;

    var container = document.getElementById('load-more-container');
    var remaining = document.getElementById('load-more-remaining');

    function updateRemaining() {
        var left = total - visible;
        if (remaining) {
            remaining.textContent = left > 0 ? ' (' + left + ' restantes)' : '';
        }
    }

    if (container) {
        container.style.display = 'block';
        updateRemaining();
    }

    window.tvmLoadMore = function() {
        var next = visible + LOAD_MORE_COUNT;
        cards.forEach(function(card) {
            var idx = parseInt(card.getAttribute('data-card-index'), 10);
            if (idx > visible && idx <= next) {
                card.style.display = '';
            }
        });
        visible = Math.min(next, total);
        updateRemaining();
        if (visible >= total && container) {
            container.style.display = 'none';
        }
    };
})();
</script>
<?php include __DIR__ . '/templates/footer.php'; ?>
