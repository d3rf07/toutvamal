<?php
/**
 * ToutVaMal.fr - Fonctions SEO
 * Schema.org JSON-LD, Breadcrumbs, Meta tags
 */

/**
 * Génère le Schema.org JSON-LD pour l'organisation
 */
function schema_organization(): string {
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'NewsMediaOrganization',
        '@id' => SITE_URL . '/#organization',
        'name' => SITE_NAME,
        'alternateName' => 'Tout Va Mal',
        'url' => SITE_URL,
        'logo' => [
            '@type' => 'ImageObject',
            'url' => SITE_URL . '/logo-toutvamal.png',
            'width' => 600,
            'height' => 60
        ],
        'image' => SITE_URL . '/images/og-default.jpg',
        'description' => 'Site satirique français - Les pires nouvelles du jour, tous les jours.',
        'slogan' => TAGLINE,
        'foundingDate' => '2025',
        'sameAs' => [
            'https://twitter.com/toutvamal',
            'https://facebook.com/toutvamal'
        ],
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'contactType' => 'customer service',
            'email' => 'contact@toutvamal.fr',
            'availableLanguage' => 'French'
        ]
    ];
    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
}

/**
 * Génère le Schema.org JSON-LD pour un article
 */
function schema_article(array $article, ?array $journalist = null): string {
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'SatiricalArticle',
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => SITE_URL . '/articles/' . $article['slug'] . '.html'
        ],
        'headline' => $article['title'],
        'description' => $article['excerpt'] ?? substr(strip_tags($article['content']), 0, 160),
        'image' => $article['image_path'] ? SITE_URL . $article['image_path'] : SITE_URL . '/images/og-default.jpg',
        'datePublished' => date('c', strtotime($article['published_at'])),
        'dateModified' => date('c', strtotime($article['updated_at'] ?? $article['published_at'])),
        'articleSection' => CATEGORIES[$article['category']] ?? $article['category'],
        'inLanguage' => 'fr-FR',
        'isAccessibleForFree' => true,
        'publisher' => [
            '@type' => 'NewsMediaOrganization',
            '@id' => SITE_URL . '/#organization',
            'name' => SITE_NAME,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => SITE_URL . '/logo-toutvamal.png'
            ]
        ],
        'author' => [
            '@type' => 'Person',
            'name' => $journalist['name'] ?? 'La Rédaction',
            'url' => $journalist ? SITE_URL . '/equipe/' . $journalist['slug'] . '.html' : SITE_URL . '/equipe.html'
        ],
        'about' => [
            '@type' => 'Thing',
            'name' => 'Satire',
            'description' => 'Contenu satirique et humoristique'
        ]
    ];

    // Ajouter le temps de lecture
    $wordCount = str_word_count(strip_tags($article['content']));
    $schema['wordCount'] = $wordCount;

    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
}

/**
 * Génère le Schema.org JSON-LD pour un journaliste
 */
function schema_person(array $journalist, array $articles = []): string {
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Person',
        '@id' => SITE_URL . '/equipe/' . $journalist['slug'] . '.html#person',
        'name' => $journalist['name'],
        'jobTitle' => $journalist['role'],
        'description' => $journalist['bio'] ?? '',
        'image' => $journalist['photo_path'] ? SITE_URL . $journalist['photo_path'] : SITE_URL . '/images/equipe/default.webp',
        'url' => SITE_URL . '/equipe/' . $journalist['slug'] . '.html',
        'worksFor' => [
            '@type' => 'NewsMediaOrganization',
            '@id' => SITE_URL . '/#organization',
            'name' => SITE_NAME
        ]
    ];

    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
}

/**
 * Génère le Schema.org JSON-LD pour le WebSite (recherche)
 */
function schema_website(): string {
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        '@id' => SITE_URL . '/#website',
        'url' => SITE_URL,
        'name' => SITE_NAME,
        'description' => 'Site satirique français - ' . TAGLINE,
        'publisher' => [
            '@id' => SITE_URL . '/#organization'
        ],
        'inLanguage' => 'fr-FR'
    ];
    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
}

/**
 * Génère le Schema.org JSON-LD pour les breadcrumbs
 */
function schema_breadcrumbs(array $items): string {
    $itemList = [];
    foreach ($items as $i => $item) {
        $itemList[] = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $item['name'],
            'item' => $item['url']
        ];
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $itemList
    ];

    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
}

/**
 * Génère le HTML du fil d'Ariane
 */
function render_breadcrumbs(array $items): string {
    $html = '<nav class="breadcrumbs" aria-label="Fil d\'Ariane">';
    $html .= '<ol itemscope itemtype="https://schema.org/BreadcrumbList">';

    foreach ($items as $i => $item) {
        $isLast = ($i === count($items) - 1);
        $position = $i + 1;

        $html .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';

        if ($isLast) {
            $html .= '<span itemprop="name">' . htmlspecialchars($item['name']) . '</span>';
        } else {
            $html .= '<a itemprop="item" href="' . htmlspecialchars($item['url']) . '">';
            $html .= '<span itemprop="name">' . htmlspecialchars($item['name']) . '</span>';
            $html .= '</a>';
            $html .= '<span class="breadcrumb-separator" aria-hidden="true">/</span>';
        }

        $html .= '<meta itemprop="position" content="' . $position . '">';
        $html .= '</li>';
    }

    $html .= '</ol></nav>';
    return $html;
}

/**
 * Génère les meta tags Twitter Cards complets
 */
function twitter_cards(string $title, string $description, string $image, string $url, string $type = 'summary_large_image'): string {
    $html = '<meta name="twitter:card" content="' . $type . '">' . "\n";
    $html .= '    <meta name="twitter:site" content="@toutvamal">' . "\n";
    $html .= '    <meta name="twitter:creator" content="@toutvamal">' . "\n";
    $html .= '    <meta name="twitter:title" content="' . htmlspecialchars($title) . '">' . "\n";
    $html .= '    <meta name="twitter:description" content="' . htmlspecialchars(substr($description, 0, 200)) . '">' . "\n";
    $html .= '    <meta name="twitter:image" content="' . htmlspecialchars($image) . '">' . "\n";
    $html .= '    <meta name="twitter:image:alt" content="' . htmlspecialchars($title) . '">';
    return $html;
}

/**
 * Génère les meta tags OpenGraph complets
 */
function opengraph_tags(string $type, string $title, string $description, string $image, string $url, ?string $publishedTime = null, ?string $section = null): string {
    $html = '<meta property="og:type" content="' . $type . '">' . "\n";
    $html .= '    <meta property="og:site_name" content="' . SITE_NAME . '">' . "\n";
    $html .= '    <meta property="og:locale" content="fr_FR">' . "\n";
    $html .= '    <meta property="og:title" content="' . htmlspecialchars($title) . '">' . "\n";
    $html .= '    <meta property="og:description" content="' . htmlspecialchars(substr($description, 0, 300)) . '">' . "\n";
    $html .= '    <meta property="og:image" content="' . htmlspecialchars($image) . '">' . "\n";
    $html .= '    <meta property="og:image:width" content="1200">' . "\n";
    $html .= '    <meta property="og:image:height" content="630">' . "\n";
    $html .= '    <meta property="og:url" content="' . htmlspecialchars($url) . '">';

    if ($type === 'article' && $publishedTime) {
        $html .= "\n" . '    <meta property="article:published_time" content="' . date('c', strtotime($publishedTime)) . '">';
        $html .= "\n" . '    <meta property="article:author" content="' . SITE_URL . '/equipe.html">';
        $html .= "\n" . '    <meta property="article:publisher" content="https://facebook.com/toutvamal">';
        if ($section) {
            $html .= "\n" . '    <meta property="article:section" content="' . htmlspecialchars($section) . '">';
        }
        $html .= "\n" . '    <meta property="article:tag" content="satire">';
        $html .= "\n" . '    <meta property="article:tag" content="humour">';
        $html .= "\n" . '    <meta property="article:tag" content="actualité">';
    }

    return $html;
}

/**
 * Génère les articles connexes pour internal linking
 */
function get_related_articles(string $category, int $excludeId, int $limit = 3): array {
    $stmt = db()->prepare("
        SELECT a.slug, a.title, a.image_path, a.category, j.name as journalist_name
        FROM articles a
        LEFT JOIN journalists j ON a.journalist_id = j.id
        WHERE a.category = :category AND a.id != :excludeId
        AND (a.status = 'published' OR a.status IS NULL)
        ORDER BY a.published_at DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':category', $category);
    $stmt->bindValue(':excludeId', $excludeId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $related = $stmt->fetchAll();

    // Si pas assez d'articles dans la même catégorie, compléter avec des récents
    if (count($related) < $limit) {
        $needed = $limit - count($related);
        $excludeIds = array_column($related, 'slug');
        $excludeIds[] = $excludeId;

        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
        $stmt = db()->prepare("
            SELECT a.slug, a.title, a.image_path, a.category, j.name as journalist_name
            FROM articles a
            LEFT JOIN journalists j ON a.journalist_id = j.id
            WHERE a.id NOT IN ($placeholders)
            AND (a.status = 'published' OR a.status IS NULL)
            ORDER BY a.published_at DESC
            LIMIT ?
        ");
        $params = $excludeIds;
        $params[] = $needed;
        $stmt->execute($params);
        $related = array_merge($related, $stmt->fetchAll());
    }

    return $related;
}

/**
 * Rend la section articles connexes
 */
function render_related_articles(array $articles): string {
    if (empty($articles)) return '';

    $html = '<section class="related-articles">';
    $html .= '<h3>À lire aussi</h3>';
    $html .= '<div class="related-grid">';

    foreach ($articles as $article) {
        $html .= '<a href="/articles/' . htmlspecialchars($article['slug']) . '.html" class="related-card">';
        $html .= '<img src="' . htmlspecialchars($article['image_path'] ?: '/images/placeholder.jpg') . '" ';
        $html .= 'alt="' . htmlspecialchars($article['title']) . '" loading="lazy">';
        $html .= '<div class="related-card-content">';
        $html .= '<span class="badge badge-small">' . htmlspecialchars(CATEGORIES[$article['category']] ?? $article['category']) . '</span>';
        $html .= '<h4>' . htmlspecialchars($article['title']) . '</h4>';
        $html .= '</div></a>';
    }

    $html .= '</div></section>';
    return $html;
}
