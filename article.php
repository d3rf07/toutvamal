<?php
/**
 * ToutVaMal.fr - Article Page (SEO Optimized)
 * Rebuild 2025-12-30, SEO update 2026-02-26
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/seo-functions.php';

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
$canonicalUrl = SITE_URL . '/articles/' . $article['slug'] . '.html';
$currentCategory = $article['category'];

// Schema NewsArticle JSON-LD
$articleSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'NewsArticle',
    'headline' => $article['title'],
    'description' => $pageDescription,
    'image' => $ogImage,
    'datePublished' => date('c', strtotime($article['published_at'])),
    'dateModified' => date('c', strtotime($article['updated_at'] ?? $article['published_at'])),
    'author' => [
        '@type' => 'Person',
        'name' => $article['journalist_name'] ?? 'La Rédaction',
        'url' => SITE_URL . '/equipe/' . ($article['journalist_slug'] ?? '') . '.html'
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name' => SITE_NAME,
        'url' => SITE_URL,
        'logo' => [
            '@type' => 'ImageObject',
            'url' => SITE_URL . '/logo-toutvamal.png',
            'width' => 600,
            'height' => 60
        ]
    ],
    'mainEntityOfPage' => [
        '@type' => 'WebPage',
        '@id' => $canonicalUrl
    ],
    'articleSection' => CATEGORIES[$article['category']] ?? $article['category'],
    'inLanguage' => 'fr-FR',
    'isAccessibleForFree' => true,
    'url' => $canonicalUrl,
    'wordCount' => str_word_count(strip_tags($article['content'])),
    'genre' => 'Satire',
    'keywords' => 'satire,humour,actualité,France'
];
$articleSchemaJson = json_encode($articleSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// Breadcrumbs
// BreadcrumbList: garantir que name n'est jamais vide (GSC signale "Unnamed" sinon)
$_catName = !empty(CATEGORIES[$article['category']]) ? CATEGORIES[$article['category']] : ucfirst($article['category']);
$breadcrumbs = [
    ['name' => 'Accueil', 'url' => SITE_URL . '/'],
    ['name' => $_catName, 'url' => SITE_URL . '/categorie/' . $article['category']],
    ['name' => !empty($article['title']) ? $article['title'] : SITE_NAME, 'url' => $canonicalUrl]
];
$breadcrumbSchema = schema_breadcrumbs($breadcrumbs);

// Related articles
$relatedArticles = get_related_articles($article["category"], $article["id"], 4);

// Extra schema for header
$extraSchema = $breadcrumbSchema;

include __DIR__ . '/templates/header.php';
?>

<!-- NewsArticle Schema -->
<script type="application/ld+json"><?= $articleSchemaJson ?></script>

<!-- Breadcrumbs -->
<div class="container">
    <?= render_breadcrumbs($breadcrumbs) ?>
</div>

<!-- Article Hero -->
<section class="article-hero">
    <img src="<?= htmlspecialchars($article['image_path'] ?: '/images/placeholder.jpg') ?>"
         alt="<?= htmlspecialchars($article['title']) ?>"
         loading="eager" decoding="async">
    <div class="article-hero-content">
        <span class="badge"><?= htmlspecialchars(CATEGORIES[$article['category']] ?? $article['category']) ?></span>
        <h1><?= htmlspecialchars($article['title']) ?></h1>
        <div class="meta">
            <span>Par <a href="/equipe/<?= htmlspecialchars($article['journalist_slug'] ?? '') ?>.html" style="color: inherit; text-decoration: underline;"><?= htmlspecialchars($article['journalist_name'] ?? 'La Rédaction') ?></a></span>
            <span><time datetime="<?= date('c', strtotime($article['published_at'])) ?>"><?= format_date($article['published_at']) ?></time></span>
            <span><?= reading_time($article['content']) ?> min de lecture</span>
        </div>
    </div>
</section>

<main class="container main-layout">
    <div class="content">
        <!-- Article Content -->
        <article class="article-content">
            <?php
            // Injecter un bloc affilié Amazon aléatoirement (1 article sur 4-5)
            $amazonProducts = [
                [
                    'url' => 'https://www.amazon.fr/dp/B09Y2MYL5C?tag=d3rf21-21',
                    'img' => '/images/amazon-casque.webp',
                    'alt' => 'Casque anti-bruit',
                    'title' => 'Besoin de vous isoler du chaos ambiant ?',
                    'quote' => 'Le silence, c\'est le nouveau luxe.',
                    'label' => 'SUGGESTION'
                ],
                [
                    'url' => 'https://www.amazon.fr/dp/B0D798Q29V?tag=d3rf21-21',
                    'img' => '/images/amazon-survie.webp',
                    'alt' => 'Kit de survie',
                    'title' => 'Prêt pour quand LinkedIn ne suffira plus ?',
                    'quote' => 'Plan B : la vraie compétence de demain.',
                    'label' => 'SUGGESTION'
                ],
                [
                    'url' => 'https://www.amazon.fr/dp/2021223310?tag=d3rf21-21',
                    'img' => '/images/amazon-effondrement.webp',
                    'alt' => 'Comment tout peut s\'effondrer',
                    'title' => 'Envie de comprendre pourquoi tout s\'effondre ?',
                    'quote' => 'Spoiler : c\'est pas que de votre faute.',
                    'label' => 'LECTURE'
                ],
                [
                    'url' => 'https://www.amazon.fr/dp/2226257012?tag=d3rf21-21',
                    'img' => '/images/amazon-sapiens.webp',
                    'alt' => 'Sapiens',
                    'title' => 'Comment en est-on arrivé là, au juste ?',
                    'quote' => '300 000 ans de mauvaises décisions expliqués.',
                    'label' => 'LECTURE'
                ],
            ];

            $content = strip_tags($article['content'], '<p><blockquote><em><strong>'); // Sanitize: whitelist safe HTML tags only
            // Insérer un bloc affilié environ 1 fois sur 4-5 articles (basé sur l'ID)
            $showAmazon = ($article['id'] % 5 <= 1); // ~40% des articles

            if ($showAmazon) {
                $product = $amazonProducts[$article['id'] % count($amazonProducts)];
                $amazonBlock = '<div class="amazon-inline"><a href="' . htmlspecialchars($product['url']) . '" target="_blank" rel="nofollow noopener sponsored" class="amazon-box">'
                    . '<img src="' . $product['img'] . '" alt="' . htmlspecialchars($product['alt']) . '" class="amazon-img" loading="lazy">'
                    . '<div class="amazon-text"><span class="amazon-label">' . $product['label'] . '</span>'
                    . '<h5>' . htmlspecialchars($product['title']) . '</h5>'
                    . '<p>&laquo; ' . htmlspecialchars($product['quote']) . ' &raquo;</p>'
                    . '<span class="amazon-cta">Découvrir &rarr;</span></div>'
                    . '</a><small class="amazon-disclaimer">Lien affilié Amazon</small></div>';

                // Insérer après le 2e ou 3e </p>
                $pos = 0;
                $insertAfter = 2 + ($article['id'] % 2); // 2e ou 3e paragraphe
                for ($i = 0; $i < $insertAfter; $i++) {
                    $pos = strpos($content, '</p>', $pos);
                    if ($pos === false) break;
                    $pos += 4;
                }
                if ($pos !== false && $pos > 0) {
                    $content = substr($content, 0, $pos) . $amazonBlock . substr($content, $pos);
                }
            }

            echo $content;
            ?>
        </article>

        <!-- Share Bar -->
        <style>
        .share-bar {
            background: #111;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 2rem 0;
        }
        .share-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #888;
            margin-bottom: 1rem;
        }
        .share-buttons-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
        }
        .share-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: opacity 0.15s ease;
            white-space: nowrap;
            font-family: inherit;
        }
        .share-btn:hover { opacity: 0.85; }
        .share-twitter { background: #1DA1F2; color: #fff; }
        .share-facebook { background: #1877F2; color: #fff; }
        .share-whatsapp { background: #25D366; color: #fff; }
        .share-copy {
            background: transparent;
            color: #e0e0e0;
            border: 1px solid #666;
        }
        .share-copy:hover { border-color: #999; }
        @media (max-width: 480px) {
            .share-buttons-row { gap: 0.5rem; }
            .share-btn { padding: 7px 12px; font-size: 0.8125rem; }
        }
        @media (prefers-reduced-motion: reduce) {
            .share-btn { transition: none; }
        }
        </style>
        <div class="share-bar">
            <span class="share-label">Partager cette catastrophe</span>
            <div class="share-buttons-row">
                <a href="https://twitter.com/intent/tweet?text=<?= urlencode($article['title']) ?>&url=<?= urlencode($canonicalUrl) ?>"
                   target="_blank" rel="noopener"
                   class="share-btn share-twitter"
                   aria-label="Partager sur Twitter">&#x1D54F;</a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($canonicalUrl) ?>"
                   target="_blank" rel="noopener"
                   class="share-btn share-facebook"
                   aria-label="Partager sur Facebook">f</a>
                <a href="https://wa.me/?text=<?= urlencode($article['title'] . ' ' . $canonicalUrl) ?>"
                   target="_blank" rel="noopener"
                   class="share-btn share-whatsapp"
                   aria-label="Partager sur WhatsApp">WhatsApp</a>
                <button class="share-btn share-copy"
                        aria-label="Copier le lien de l'article"
                        onclick="
                            var btn = this;
                            var original = btn.textContent;
                            navigator.clipboard.writeText(window.location.href).then(function() {
                                btn.textContent = 'Lien copie !';
                                btn.setAttribute('aria-live', 'polite');
                                setTimeout(function() { btn.textContent = original; }, 2000);
                            }).catch(function() {
                                btn.textContent = 'Erreur';
                                setTimeout(function() { btn.textContent = original; }, 2000);
                            });
                        ">Copier</button>
            </div>
        </div>

        <!-- Author Card -->
        <?php if ($article['journalist_name']): ?>
        <div class="author-card">
            <a href="/equipe/<?= htmlspecialchars($article['journalist_slug'] ?? '') ?>.html">
                <img src="<?= htmlspecialchars($article['journalist_photo'] ?: '/images/equipe/default.jpg') ?>"
                     alt="<?= htmlspecialchars($article['journalist_name']) ?>">
            </a>
            <div class="author-card-info">
                <h4><a href="/equipe/<?= htmlspecialchars($article['journalist_slug'] ?? '') ?>.html" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($article['journalist_name']) ?></a></h4>
                <p class="role"><?= htmlspecialchars($article['journalist_role']) ?></p>
                <p class="bio"><?= htmlspecialchars($article['journalist_bio'] ?? '') ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Source -->
        <?php if ($article['source_url']): ?>
        <p style="font-size: 0.875rem; color: var(--gris-500); margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--gris-300);">
            Source : <a href="<?= htmlspecialchars($article['source_url']) ?>" target="_blank" rel="noopener nofollow" style="color: var(--rouge);">
                <?= htmlspecialchars($article['source_title'] ?? 'Article original') ?>
            </a>
        </p>
        <?php endif; ?>

        <!-- Related Articles -->
        <?= render_related_articles($relatedArticles) ?>
    </div>

    <?php include __DIR__ . '/templates/sidebar.php'; ?>
</main>

<?php include __DIR__ . '/templates/footer.php'; ?>
