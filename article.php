<?php
/**
 * ToutVaMal.fr - Article Page
 * Rebuild 2025-12-30
 */

require_once __DIR__ . '/config.php';

// Récupérer le slug depuis l'URL
if (isset($_GET['slug'])) {
    $slug = $_GET['slug'];
} else {
    // Format /articles/slug.html
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $slug = basename($uri, '.html');
}
$slug = preg_replace('/[^a-z0-9\-]/', '', $slug);

if (empty($slug) || $slug === 'article') {
    header('Location: /');
    exit;
}

// Récupérer l'article
$stmt = db()->prepare("
    SELECT a.*, j.name as journalist_name, j.slug as journalist_slug,
           j.role as journalist_role, j.bio as journalist_bio, j.photo_path as journalist_photo
    FROM articles a
    LEFT JOIN journalists j ON a.journalist_id = j.id
    WHERE a.slug = :slug
    LIMIT 1
");
$stmt->execute([':slug' => $slug]);
$article = $stmt->fetch();

if (!$article) {
    http_response_code(404);
    $pageTitle = 'Article non trouvé - ' . SITE_NAME;
    include __DIR__ . '/templates/header.php';
    echo '<main class="container" style="padding: 4rem 0; text-align: center;">
        <h1 style="font-family: var(--font-display); font-size: 2rem;">Article non trouvé</h1>
        <p style="margin-top: 1rem;">Cet article a peut-être été supprimé ou n\'a jamais existé.</p>
        <a href="/" style="display: inline-block; margin-top: 2rem; color: var(--rouge);">Retour à l\'accueil</a>
    </main>';
    include __DIR__ . '/templates/footer.php';
    exit;
}

$pageTitle = $article['title'] . ' - ' . SITE_NAME;
$pageDescription = $article['excerpt'] ?? substr(strip_tags($article['content']), 0, 160);
$ogImage = SITE_URL . $article['image_path'];
$currentCategory = $article['category'];

include __DIR__ . '/templates/header.php';
?>

<!-- Article Hero -->
<section class="article-hero">
    <img src="<?= htmlspecialchars($article['image_path'] ?: '/images/placeholder.jpg') ?>"
         alt="<?= htmlspecialchars($article['title']) ?>">
    <div class="article-hero-content">
        <span class="badge"><?= htmlspecialchars(CATEGORIES[$article['category']] ?? $article['category']) ?></span>
        <h1><?= htmlspecialchars($article['title']) ?></h1>
        <div class="meta">
            <span>Par <?= htmlspecialchars($article['journalist_name'] ?? 'La Rédaction') ?></span>
            <span><?= format_date($article['published_at']) ?></span>
            <span><?= reading_time($article['content']) ?> min de lecture</span>
        </div>
    </div>
</section>

<main class="container main-layout">
    <div class="content">
        <!-- Article Content -->
        <article class="article-content">
            <?= $article['content'] ?>
        </article>

        <!-- Share Buttons -->
        <div class="share-buttons">
            <a href="https://twitter.com/intent/tweet?text=<?= urlencode($article['title']) ?>&url=<?= urlencode(SITE_URL . '/articles/' . $article['slug'] . '.html') ?>"
               target="_blank" rel="noopener" class="share-btn twitter">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                Partager sur X
            </a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(SITE_URL . '/articles/' . $article['slug'] . '.html') ?>"
               target="_blank" rel="noopener" class="share-btn facebook">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                Partager
            </a>
            <button class="share-btn copy" onclick="navigator.clipboard.writeText(window.location.href); this.textContent='Copié !'">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
                Copier le lien
            </button>
        </div>

        <!-- Author Card -->
        <?php if ($article['journalist_name']): ?>
        <div class="author-card">
            <img src="<?= htmlspecialchars($article['journalist_photo'] ?: '/images/equipe/default.jpg') ?>"
                 alt="<?= htmlspecialchars($article['journalist_name']) ?>">
            <div class="author-card-info">
                <h4><?= htmlspecialchars($article['journalist_name']) ?></h4>
                <p class="role"><?= htmlspecialchars($article['journalist_role']) ?></p>
                <p class="bio"><?= htmlspecialchars($article['journalist_bio'] ?? '') ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Source -->
        <?php if ($article['source_url']): ?>
        <p style="font-size: 0.875rem; color: var(--gris-500); margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--gris-300);">
            Source : <a href="<?= htmlspecialchars($article['source_url']) ?>" target="_blank" rel="noopener" style="color: var(--rouge);">
                <?= htmlspecialchars($article['source_title'] ?? 'Article original') ?>
            </a>
        </p>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/templates/sidebar.php'; ?>
</main>

<?php include __DIR__ . '/templates/footer.php'; ?>
