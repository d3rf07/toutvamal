<?php
/**
 * ToutVaMal.fr - Régénération des pages statiques avec SEO complet
 * Usage: php regenerate-static.php [all|equipe|journalists|articles|apropos]
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/seo-functions.php';

$action = $argv[1] ?? 'all';

echo "=== Régénération des pages statiques ===\n";
echo "Action: $action\n\n";

/**
 * Génère le header HTML complet avec SEO
 */
function generate_header($pageTitle, $pageDescription, $canonicalUrl, $ogImage = null, $pageType = 'website', $breadcrumbs = [], $extraSchema = '', $article = null) {
    $ogImage = $ogImage ?? SITE_URL . '/images/og-default.jpg';

    $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO Primaire -->
    <title>' . htmlspecialchars($pageTitle) . '</title>
    <meta name="description" content="' . htmlspecialchars($pageDescription) . '">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="author" content="' . SITE_NAME . '">
    <meta name="language" content="fr">

    <!-- Canonical -->
    <link rel="canonical" href="' . htmlspecialchars($canonicalUrl) . '">

    <!-- OpenGraph -->
    <meta property="og:type" content="' . $pageType . '">
    <meta property="og:site_name" content="' . SITE_NAME . '">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:title" content="' . htmlspecialchars($pageTitle) . '">
    <meta property="og:description" content="' . htmlspecialchars($pageDescription) . '">
    <meta property="og:image" content="' . htmlspecialchars($ogImage) . '">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="' . htmlspecialchars($canonicalUrl) . '">';

    // Article-specific OG tags
    if ($pageType === 'article' && $article) {
        $html .= '
    <meta property="article:published_time" content="' . date('c', strtotime($article['published_at'])) . '">
    <meta property="article:modified_time" content="' . date('c', strtotime($article['updated_at'] ?? $article['published_at'])) . '">
    <meta property="article:author" content="' . SITE_URL . '/equipe.html">
    <meta property="article:section" content="' . htmlspecialchars(CATEGORIES[$article['category']] ?? $article['category']) . '">
    <meta property="article:tag" content="satire">
    <meta property="article:tag" content="humour">
    <meta property="article:tag" content="actualité">';
    }

    $html .= '

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@toutvamal">
    <meta name="twitter:creator" content="@toutvamal">
    <meta name="twitter:title" content="' . htmlspecialchars($pageTitle) . '">
    <meta name="twitter:description" content="' . htmlspecialchars(substr($pageDescription, 0, 200)) . '">
    <meta name="twitter:image" content="' . htmlspecialchars($ogImage) . '">
    <meta name="twitter:image:alt" content="' . htmlspecialchars($pageTitle) . '">

    <!-- Favicons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#DC2626">

    <!-- CSS -->
    <link rel="stylesheet" href="/css/style.css">

    <!-- RSS -->
    <link rel="alternate" type="application/rss+xml" title="' . SITE_NAME . ' RSS" href="' . SITE_URL . '/rss.xml">

    <!-- Schema.org -->
    ' . schema_organization() . '
    ' . schema_website() . '
    ' . $extraSchema;

    if (count($breadcrumbs) > 1) {
        $html .= '
    ' . schema_breadcrumbs($breadcrumbs);
    }

    $html .= '
</head>
<body>';

    return $html;
}

/**
 * Génère le ticker avec les derniers articles
 */
function generate_ticker() {
    $stmt = db()->query("SELECT title, slug FROM articles WHERE status = 'published' OR status IS NULL ORDER BY published_at DESC LIMIT 10");
    $articles = $stmt->fetchAll();

    $html = '
<!-- Breaking News Ticker -->
<div class="ticker">
    <div class="ticker-label">ALERTE</div>
    <div class="ticker-content">';

    foreach ($articles as $item) {
        $html .= '
            <a href="/articles/' . htmlspecialchars($item['slug']) . '.html" class="ticker-item">
                ' . htmlspecialchars($item['title']) . '
            </a>';
    }
    // Duplicate for seamless scrolling
    foreach ($articles as $item) {
        $html .= '
            <a href="/articles/' . htmlspecialchars($item['slug']) . '.html" class="ticker-item">
                ' . htmlspecialchars($item['title']) . '
            </a>';
    }

    $html .= '
    </div>
</div>';

    return $html;
}

/**
 * Génère la navigation
 */
function generate_nav($activePage = '') {
    $today = format_date_full();

    return generate_ticker() . '
    <!-- Header -->
    <header class="header">
        <div class="header-inner">
            <div class="header-date">' . $today . '</div>
            <div class="header-logo">
                <a href="/" class="logo" title="' . SITE_NAME . ' - Accueil">TOUT<span>VA</span>MAL</a>
                <div class="tagline">' . TAGLINE . '</div>
            </div>
            <div class="header-newsletter"><a href="/#newsletter">S\'abonner</a></div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="nav" aria-label="Navigation principale">
        <div class="nav-inner">
            <ul class="nav-list">
                <li><a href="/" class="nav-item' . ($activePage === 'home' ? ' active' : '') . '">Accueil</a></li>
                <li><a href="/equipe.html" class="nav-item' . ($activePage === 'equipe' ? ' active' : '') . '">L\'Équipe</a></li>
                <li><a href="/a-propos.html" class="nav-item' . ($activePage === 'apropos' ? ' active' : '') . '">À Propos</a></li>
            </ul>
        </div>
    </nav>';
}

/**
 * Génère les breadcrumbs HTML
 */
function generate_breadcrumbs_html($breadcrumbs) {
    if (count($breadcrumbs) <= 1) return '';
    return '
    <div class="container">
        ' . render_breadcrumbs($breadcrumbs) . '
    </div>';
}

/**
 * Génère le footer
 */
function generate_footer() {
    $year = date('Y');
    $categories = CATEGORIES;

    $catLinks = '';
    foreach ($categories as $slug => $name) {
        $catLinks .= '                <li><a href="/?cat=' . $slug . '">' . $name . '</a></li>' . "\n";
    }

    return '
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="logo">TOUT<span>VA</span>MAL</div>
                <div class="tagline">' . TAGLINE . '</div>
                <div class="footer-social">
                    <a href="https://twitter.com/toutvamal" target="_blank" rel="noopener" aria-label="Twitter">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                    <a href="https://facebook.com/toutvamal" target="_blank" rel="noopener" aria-label="Facebook">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                </div>
            </div>

            <div class="footer-column">
                <h5>Navigation</h5>
                <ul>
                    <li><a href="/">Accueil</a></li>
                    <li><a href="/equipe.html">L\'Équipe</a></li>
                    <li><a href="/a-propos.html">À Propos</a></li>
                    <li><a href="/contact.html">Contact</a></li>
                    <li><a href="/archives.html">Archives</a></li>
                </ul>
            </div>

            <div class="footer-column">
                <h5>Rubriques</h5>
                <ul>
' . $catLinks . '
                </ul>
            </div>

            <div class="footer-column">
                <h5>Légal</h5>
                <ul>
                    <li><a href="/mentions-legales.html">Mentions légales</a></li>
                    <li><a href="/cgu.html">CGU</a></li>
                    <li><a href="/confidentialite.html">Confidentialité</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; ' . $year . ' ToutVaMal.fr - Site satirique. Toute ressemblance avec la réalité serait purement fortuite (ou pas).</p>
        </div>
    </footer>

    <script>
    document.querySelectorAll(\'.newsletter-form\').forEach(form => {
        form.addEventListener(\'submit\', async (e) => {
            e.preventDefault();
            const email = form.querySelector(\'input[type="email"]\').value;
            const btn = form.querySelector(\'button\');
            const originalText = btn.textContent;
            btn.textContent = \'Envoi...\';
            btn.disabled = true;
            try {
                const res = await fetch(\'/api/newsletter.php\', {
                    method: \'POST\',
                    headers: { \'Content-Type\': \'application/json\' },
                    body: JSON.stringify({ email })
                });
                const data = await res.json();
                if (data.success) {
                    btn.textContent = \'Inscrit !\';
                    form.querySelector(\'input\').value = \'\';
                } else {
                    btn.textContent = data.error || \'Erreur\';
                }
            } catch (err) {
                btn.textContent = \'Erreur réseau\';
            }
            setTimeout(() => {
                btn.textContent = originalText;
                btn.disabled = false;
            }, 3000);
        });
    });
    </script>
</body>
</html>';
}

/**
 * Régénère la page équipe
 */
function regenerate_equipe() {
    echo "Régénération de equipe.html...\n";

    $stmt = db()->query("SELECT * FROM journalists WHERE active = 1 ORDER BY id");
    $journalists = $stmt->fetchAll();

    $pageTitle = "L'Équipe du Malheur - " . SITE_NAME;
    $pageDescription = "Découvrez les journalistes désabusés qui font de ToutVaMal.fr le site satirique le plus déprimant de France. Une équipe soudée par le désespoir.";
    $canonicalUrl = SITE_URL . '/equipe.html';
    $breadcrumbs = [
        ['name' => 'Accueil', 'url' => SITE_URL],
        ['name' => 'L\'Équipe', 'url' => $canonicalUrl]
    ];

    $html = generate_header($pageTitle, $pageDescription, $canonicalUrl, null, 'website', $breadcrumbs);
    $html .= generate_nav('equipe');
    $html .= generate_breadcrumbs_html($breadcrumbs);

    $html .= '
    <main class="container" style="padding: 3rem 0;">
        <header style="text-align: center; margin-bottom: 3rem;">
            <h1 style="font-family: var(--font-display); font-size: 2.5rem; margin-bottom: 1rem;">La Rédaction du Malheur</h1>
            <p style="color: var(--gris-600); max-width: 600px; margin: 0 auto;">
                Une équipe de professionnels unis par une même passion : annoncer les pires nouvelles avec le sourire
                (enfin, un sourire crispé, mais quand même).
            </p>
        </header>

        <div class="team-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 2rem;">';

    foreach ($journalists as $j) {
        $photo = $j['photo_path'] ?: '/images/equipe/default.webp';
        $html .= '
            <a href="/equipe/' . htmlspecialchars($j['slug']) . '.html" class="team-card" style="text-decoration: none; color: inherit; display: block; background: #111; border-radius: 8px; overflow: hidden;">
                <img src="' . htmlspecialchars($photo) . '" alt="Photo de ' . htmlspecialchars($j['name']) . '" loading="lazy" width="300" height="300" style="width: 100%; aspect-ratio: 1; object-fit: cover;">
                <div style="padding: 1.25rem;">
                    <h3 style="font-family: var(--font-display); margin: 0 0 0.25rem; font-size: 1.2rem;">' . htmlspecialchars($j['name']) . '</h3>
                    <p style="color: var(--rouge); font-size: 0.85rem; font-weight: 600; margin: 0 0 0.75rem;">' . htmlspecialchars($j['role']) . '</p>'
            . (!empty($j['bio']) ? '<p style="color: var(--gris-500); font-size: 0.875rem; line-height: 1.6; margin: 0;">' . htmlspecialchars($j['bio']) . '</p>' : '') . '
                </div>
            </a>';
    }

    $html .= '
        </div>
    </main>';

    $html .= generate_footer();

    file_put_contents(ARTICLES_PATH . '/../equipe.html', $html);
    echo "  ✓ equipe.html généré\n";
}

/**
 * Régénère les profils journalistes
 */
function regenerate_journalists() {
    echo "Régénération des profils journalistes...\n";

    $stmt = db()->query("SELECT * FROM journalists WHERE active = 1");
    $journalists = $stmt->fetchAll();

    foreach ($journalists as $j) {
        // Récupérer les articles du journaliste
        $stmtArticles = db()->prepare("
            SELECT slug, title, category, image_path, published_at
            FROM articles
            WHERE journalist_id = :id
            ORDER BY published_at DESC
            LIMIT 6
        ");
        $stmtArticles->execute([':id' => $j['id']]);
        $articles = $stmtArticles->fetchAll();

        $pageTitle = $j['name'] . ' - ' . SITE_NAME;
        $pageDescription = $j['role'] . ' chez ' . SITE_NAME . '. ' . ($j['bio'] ? substr($j['bio'], 0, 120) . '...' : 'Découvrez son profil et ses articles.');
        $canonicalUrl = SITE_URL . '/equipe/' . $j['slug'] . '.html';
        $photo = $j['photo_path'] ? SITE_URL . $j['photo_path'] : SITE_URL . '/images/equipe/default.webp';

        $breadcrumbs = [
            ['name' => 'Accueil', 'url' => SITE_URL],
            ['name' => 'L\'Équipe', 'url' => SITE_URL . '/equipe.html'],
            ['name' => $j['name'], 'url' => $canonicalUrl]
        ];

        $personSchema = schema_person($j, $articles);

        $html = generate_header($pageTitle, $pageDescription, $canonicalUrl, $photo, 'profile', $breadcrumbs, $personSchema);
        $html .= generate_nav('equipe');
        $html .= generate_breadcrumbs_html($breadcrumbs);

        $html .= '
    <main class="container" style="padding: 3rem 0;">
        <div style="display: grid; grid-template-columns: 300px 1fr; gap: 3rem; align-items: start;" class="journalist-profile">
            <aside>
                <img src="' . htmlspecialchars($j['photo_path'] ?: '/images/equipe/default.webp') . '"
                     alt="Photo de ' . htmlspecialchars($j['name']) . '"
                     style="width: 100%; border-radius: 0.5rem; aspect-ratio: 1; object-fit: cover;"
                     width="300" height="300">
                ' . ($j['badge'] ? '<span class="badge" style="margin-top: 1rem; display: inline-block;">' . htmlspecialchars($j['badge']) . '</span>' : '') . '
            </aside>

            <article itemscope itemtype="https://schema.org/Person">
                <h1 style="font-family: var(--font-display); font-size: 2.5rem; margin-bottom: 0.5rem;" itemprop="name">' . htmlspecialchars($j['name']) . '</h1>
                <p style="color: var(--rouge); font-weight: 600; font-size: 1.1rem; margin-bottom: 1.5rem;" itemprop="jobTitle">' . htmlspecialchars($j['role']) . '</p>

                ' . ($j['bio'] ? '<div style="line-height: 1.8; color: var(--gris-700);" itemprop="description">' . nl2br(htmlspecialchars($j['bio'])) . '</div>' : '') . '

                ' . ($j['style'] ? '<p style="margin-top: 1.5rem; padding: 1rem; background: var(--gris-100); border-radius: 0.5rem; font-style: italic;">Style : ' . htmlspecialchars($j['style']) . '</p>' : '') . '

                ' . ($j['mood'] ? '<p style="margin-top: 1rem; color: var(--gris-500);">Humeur actuelle : ' . htmlspecialchars($j['mood']) . '</p>' : '') . '
            </article>
        </div>';

        if (!empty($articles)) {
            $html .= '
        <section style="margin-top: 4rem;">
            <h2 style="font-family: var(--font-display); font-size: 1.5rem; margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 2px solid var(--noir);">
                Ses dernières catastrophes
            </h2>
            <div class="articles-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">';

            foreach ($articles as $a) {
                $catName = CATEGORIES[$a['category']] ?? $a['category'];
                $html .= '
                <a href="/articles/' . htmlspecialchars($a['slug']) . '.html" class="article-card" style="text-decoration: none; color: inherit; border: 1px solid var(--gris-200); border-radius: 0.5rem; overflow: hidden;">
                    <img src="' . htmlspecialchars($a['image_path'] ?: '/images/placeholder.jpg') . '" alt="' . htmlspecialchars($a['title']) . '" loading="lazy" style="width: 100%; height: 180px; object-fit: cover;">
                    <div style="padding: 1rem;">
                        <span class="badge badge-outline">' . htmlspecialchars($catName) . '</span>
                        <h3 style="font-size: 1rem; margin-top: 0.5rem; line-height: 1.4;">' . htmlspecialchars($a['title']) . '</h3>
                        <p style="font-size: 0.8rem; color: var(--gris-500); margin-top: 0.5rem;">' . format_date($a['published_at']) . '</p>
                    </div>
                </a>';
            }

            $html .= '
            </div>
        </section>';
        }

        $html .= '
    </main>';

        $html .= generate_footer();

        // CSS responsive
        $html = str_replace('</head>', '
    <style>
        @media (max-width: 768px) {
            .journalist-profile {
                grid-template-columns: 1fr !important;
            }
            .journalist-profile aside {
                max-width: 250px;
                margin: 0 auto;
            }
        }
    </style>
</head>', $html);

        file_put_contents(ROOT_PATH . '/equipe/' . $j['slug'] . '.html', $html);
        echo "  ✓ " . $j['slug'] . ".html généré\n";
    }
}

/**
 * Régénère la page À propos
 */
function regenerate_apropos() {
    echo "Régénération de a-propos.html...\n";

    $pageTitle = "À Propos - " . SITE_NAME;
    $pageDescription = "ToutVaMal.fr est un site satirique français qui couvre l'actualité sous l'angle du pire. Parce que tout va mal, mais au moins on en rit.";
    $canonicalUrl = SITE_URL . '/a-propos.html';
    $breadcrumbs = [
        ['name' => 'Accueil', 'url' => SITE_URL],
        ['name' => 'À Propos', 'url' => $canonicalUrl]
    ];

    // Lire le contenu existant pour le préserver
    $existingContent = '';
    $existingFile = ROOT_PATH . '/a-propos.html';
    if (file_exists($existingFile)) {
        $existing = file_get_contents($existingFile);
        if (preg_match('/<main[^>]*>(.*?)<\/main>/s', $existing, $matches)) {
            $existingContent = $matches[1];
        }
    }

    // Si pas de contenu existant, créer un contenu par défaut
    if (empty(trim(strip_tags($existingContent)))) {
        $existingContent = '
        <div style="max-width: 800px; margin: 0 auto; padding: 3rem 1rem;">
            <h1 style="font-family: var(--font-display); font-size: 2.5rem; margin-bottom: 2rem; text-align: center;">À Propos de ToutVaMal.fr</h1>

            <section style="margin-bottom: 3rem;">
                <h2 style="font-family: var(--font-display); font-size: 1.5rem; margin-bottom: 1rem; color: var(--rouge);">Notre Mission</h2>
                <p style="line-height: 1.8; color: var(--gris-700);">
                    ToutVaMal.fr est né d\'un constat simple : le monde va mal, alors autant en rire.
                    Nous sommes un site satirique français qui réinvente l\'actualité sous l\'angle du pire,
                    parce que parfois, l\'absurde est la seule réponse raisonnable à l\'absurdité du monde.
                </p>
            </section>

            <section style="margin-bottom: 3rem;">
                <h2 style="font-family: var(--font-display); font-size: 1.5rem; margin-bottom: 1rem; color: var(--rouge);">Avertissement</h2>
                <p style="line-height: 1.8; color: var(--gris-700); padding: 1.5rem; background: var(--gris-100); border-left: 4px solid var(--rouge); border-radius: 0.25rem;">
                    <strong>CECI EST UN SITE SATIRIQUE.</strong><br><br>
                    Tous les articles publiés sur ToutVaMal.fr sont fictifs et à vocation humoristique.
                    Toute ressemblance avec des personnes ou des événements réels serait purement fortuite...
                    ou délibérément exagérée pour l\'effet comique.
                </p>
            </section>

            <section style="margin-bottom: 3rem;">
                <h2 style="font-family: var(--font-display); font-size: 1.5rem; margin-bottom: 1rem; color: var(--rouge);">Notre Équipe</h2>
                <p style="line-height: 1.8; color: var(--gris-700);">
                    Notre rédaction est composée de journalistes fictifs, tous unis par une même passion :
                    transformer les pires nouvelles en moments de légèreté. Chacun apporte sa touche personnelle
                    de désespoir élégant.
                </p>
                <p style="margin-top: 1rem;">
                    <a href="/equipe.html" style="color: var(--rouge); font-weight: 600;">Découvrir l\'équipe →</a>
                </p>
            </section>

            <section>
                <h2 style="font-family: var(--font-display); font-size: 1.5rem; margin-bottom: 1rem; color: var(--rouge);">Contact</h2>
                <p style="line-height: 1.8; color: var(--gris-700);">
                    Pour nous contacter :<br>
                    <strong>contact@toutvamal.fr</strong>
                </p>
            </section>
        </div>';
    }

    $html = generate_header($pageTitle, $pageDescription, $canonicalUrl, null, 'website', $breadcrumbs);
    $html .= generate_nav('apropos');
    $html .= generate_breadcrumbs_html($breadcrumbs);
    $html .= '
    <main>' . $existingContent . '</main>';
    $html .= generate_footer();

    file_put_contents(ROOT_PATH . '/a-propos.html', $html);
    echo "  ✓ a-propos.html généré\n";
}

/**
 * Régénère toutes les pages articles statiques
 */
function regenerate_articles() {
    echo "Régénération des articles statiques...\n";

    $stmt = db()->query("
        SELECT a.*, j.name as journalist_name, j.slug as journalist_slug,
               j.role as journalist_role, j.bio as journalist_bio, j.photo_path as journalist_photo
        FROM articles a
        LEFT JOIN journalists j ON a.journalist_id = j.id
        WHERE a.status = 'published' OR a.status IS NULL
        ORDER BY a.published_at DESC
    ");
    $articles = $stmt->fetchAll();

    $count = 0;
    foreach ($articles as $article) {
        $pageTitle = $article['title'] . ' - ' . SITE_NAME;
        $pageDescription = $article['excerpt'] ?? substr(strip_tags($article['content']), 0, 160);
        $ogImage = $article['image_path'] ? SITE_URL . $article['image_path'] : SITE_URL . '/images/og-default.jpg';
        $canonicalUrl = SITE_URL . '/articles/' . $article['slug'] . '.html';

        $breadcrumbs = [
            ['name' => 'Accueil', 'url' => SITE_URL],
            ['name' => CATEGORIES[$article['category']] ?? $article['category'], 'url' => SITE_URL . '/categorie/' . $article['category']],
            ['name' => $article['title'], 'url' => $canonicalUrl]
        ];

        // NewsArticle schema
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
            'wordCount' => str_word_count(strip_tags($article['content']))
        ];
        $articleSchemaJson = '<script type="application/ld+json">' . json_encode($articleSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

        // Related articles
        $relatedArticles = get_related_articles($article['category'], $article['id'], 3);

        $html = generate_header($pageTitle, $pageDescription, $canonicalUrl, $ogImage, 'article', $breadcrumbs, $articleSchemaJson, $article);
        $html .= generate_nav();
        $html .= generate_breadcrumbs_html($breadcrumbs);

        // Article hero
        $html .= '

<!-- Article Hero -->
<section class="article-hero">
    <img src="' . htmlspecialchars($article['image_path'] ?: '/images/placeholder.jpg') . '"
         alt="' . htmlspecialchars($article['title']) . '"
         loading="eager">
    <div class="article-hero-content">
        <span class="badge">' . htmlspecialchars(CATEGORIES[$article['category']] ?? $article['category']) . '</span>
        <h1>' . htmlspecialchars($article['title']) . '</h1>
        <div class="meta">
            <span>Par <a href="/equipe/' . htmlspecialchars($article['journalist_slug'] ?? '') . '.html" style="color: inherit; text-decoration: underline;">' . htmlspecialchars($article['journalist_name'] ?? 'La Rédaction') . '</a></span>
            <span><time datetime="' . date('c', strtotime($article['published_at'])) . '">' . format_date($article['published_at']) . '</time></span>
            <span>' . reading_time($article['content']) . ' min de lecture</span>
        </div>
    </div>
</section>

<main class="container main-layout">
    <div class="content">
        <!-- Article Content -->
        <article class="article-content">
            ' . $article['content'] . '
        </article>

        <!-- Share Buttons -->
        <div class="share-buttons">
            <a href="https://twitter.com/intent/tweet?text=' . urlencode($article['title']) . '&url=' . urlencode($canonicalUrl) . '"
               target="_blank" rel="noopener" class="share-btn twitter">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                Partager sur X
            </a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=' . urlencode($canonicalUrl) . '"
               target="_blank" rel="noopener" class="share-btn facebook">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                Partager
            </a>
            <a href="https://www.linkedin.com/shareArticle?mini=true&url=' . urlencode($canonicalUrl) . '&title=' . urlencode($article['title']) . '"
               target="_blank" rel="noopener" class="share-btn" style="background: #0077B5;">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                LinkedIn
            </a>
            <button class="share-btn copy" onclick="navigator.clipboard.writeText(window.location.href); this.textContent=\'Copié !\'">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
                Copier le lien
            </button>
        </div>';

        // Author Card
        if ($article['journalist_name']) {
            $html .= '

        <!-- Author Card -->
        <div class="author-card">
            <a href="/equipe/' . htmlspecialchars($article['journalist_slug'] ?? '') . '.html">
                <img src="' . htmlspecialchars($article['journalist_photo'] ?: '/images/equipe/default.jpg') . '"
                     alt="' . htmlspecialchars($article['journalist_name']) . '">
            </a>
            <div class="author-card-info">
                <h4><a href="/equipe/' . htmlspecialchars($article['journalist_slug'] ?? '') . '.html" style="color: inherit; text-decoration: none;">' . htmlspecialchars($article['journalist_name']) . '</a></h4>
                <p class="role">' . htmlspecialchars($article['journalist_role']) . '</p>
                <p class="bio">' . htmlspecialchars($article['journalist_bio'] ?? '') . '</p>
            </div>
        </div>';
        }

        // Source
        if ($article['source_url']) {
            $html .= '
        <p style="font-size: 0.875rem; color: var(--gris-500); margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--gris-300);">
            Source : <a href="' . htmlspecialchars($article['source_url']) . '" target="_blank" rel="noopener nofollow" style="color: var(--rouge);">'
                . htmlspecialchars($article['source_title'] ?? 'Article original') . '</a>
        </p>';
        }

        // Related articles
        $html .= render_related_articles($relatedArticles);

        $html .= '
    </div>
</main>';

        $html .= generate_footer();

        file_put_contents(ROOT_PATH . '/articles/' . $article['slug'] . '.html', $html);
        $count++;
    }

    echo "  ✓ $count articles régénérés\n";
}

// Exécution
switch ($action) {
    case 'equipe':
        regenerate_equipe();
        break;
    case 'journalists':
        regenerate_journalists();
        break;
    case 'apropos':
        regenerate_apropos();
        break;
    case 'articles':
        regenerate_articles();
        break;
    case 'all':
    default:
        regenerate_equipe();
        regenerate_journalists();
        regenerate_apropos();
        regenerate_articles();
        break;
}

echo "\n=== Régénération terminée ===\n";
