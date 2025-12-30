<?php
/**
 * ToutVaMal.fr - Database Migration v2
 * Ajoute les nouvelles tables et colonnes pour l'admin complet
 */

require_once dirname(dirname(__DIR__)) . '/config.php';

echo "=== Migration Database v2 ===\n\n";

$db = db();

// Migrations SQL
$migrations = [
    // 1. Ajouter colonnes SEO aux articles
    "ALTER TABLE articles ADD COLUMN status TEXT DEFAULT 'draft'" => "Add status column",
    "ALTER TABLE articles ADD COLUMN meta_title TEXT" => "Add meta_title column",
    "ALTER TABLE articles ADD COLUMN meta_description TEXT" => "Add meta_description column",
    "ALTER TABLE articles ADD COLUMN og_image TEXT" => "Add og_image column",
    "ALTER TABLE articles ADD COLUMN schema_type TEXT DEFAULT 'NewsArticle'" => "Add schema_type column",
    "ALTER TABLE articles ADD COLUMN canonical_url TEXT" => "Add canonical_url column",
    "ALTER TABLE articles ADD COLUMN robots TEXT DEFAULT 'index,follow'" => "Add robots column",
    "ALTER TABLE articles ADD COLUMN updated_at DATETIME" => "Add updated_at column",
    "ALTER TABLE articles ADD COLUMN views INTEGER DEFAULT 0" => "Add views column",

    // 2. Table config
    "CREATE TABLE IF NOT EXISTS config (
        key TEXT PRIMARY KEY,
        value TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )" => "Create config table",

    // 3. Table RSS sources
    "CREATE TABLE IF NOT EXISTS rss_sources (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        url TEXT UNIQUE NOT NULL,
        category TEXT,
        active INTEGER DEFAULT 1,
        last_fetch DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )" => "Create rss_sources table",

    // 4. Table generation_logs améliorée
    "CREATE TABLE IF NOT EXISTS generation_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        source_url TEXT,
        source_title TEXT,
        article_id INTEGER,
        journalist_id INTEGER,
        status TEXT DEFAULT 'pending',
        error_message TEXT,
        model_used TEXT,
        tokens_used INTEGER,
        cost_estimate REAL,
        generation_time REAL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (article_id) REFERENCES articles(id),
        FOREIGN KEY (journalist_id) REFERENCES journalists(id)
    )" => "Create generation_logs table",

    // 5. Table SEO analytics
    "CREATE TABLE IF NOT EXISTS seo_analytics (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        article_id INTEGER,
        page_url TEXT NOT NULL,
        impressions INTEGER DEFAULT 0,
        clicks INTEGER DEFAULT 0,
        ctr REAL DEFAULT 0,
        position REAL DEFAULT 0,
        query TEXT,
        date_recorded DATE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (article_id) REFERENCES articles(id)
    )" => "Create seo_analytics table",

    // 6. Table SEO settings
    "CREATE TABLE IF NOT EXISTS seo_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        site_title TEXT,
        site_description TEXT,
        default_og_image TEXT,
        twitter_handle TEXT,
        google_site_verification TEXT,
        robots_txt TEXT,
        sitemap_enabled INTEGER DEFAULT 1,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )" => "Create seo_settings table",

    // 7. Index pour performance
    "CREATE INDEX IF NOT EXISTS idx_articles_status ON articles(status)" => "Create status index",
    "CREATE INDEX IF NOT EXISTS idx_articles_category ON articles(category)" => "Create category index",
    "CREATE INDEX IF NOT EXISTS idx_articles_published ON articles(published_at)" => "Create published_at index",
    "CREATE INDEX IF NOT EXISTS idx_seo_date ON seo_analytics(date_recorded)" => "Create seo date index",
    "CREATE INDEX IF NOT EXISTS idx_generation_status ON generation_logs(status)" => "Create generation status index"
];

$success = 0;
$skipped = 0;
$errors = 0;

foreach ($migrations as $sql => $description) {
    try {
        $db->exec($sql);
        echo "  [OK] $description\n";
        $success++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'duplicate column') !== false ||
            strpos($e->getMessage(), 'already exists') !== false) {
            echo "  [SKIP] $description (already exists)\n";
            $skipped++;
        } else {
            echo "  [ERROR] $description: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
}

// Mettre à jour les articles existants avec status = 'published'
try {
    $db->exec("UPDATE articles SET status = 'published' WHERE status IS NULL OR status = ''");
    echo "\n  [OK] Updated existing articles to published status\n";
} catch (PDOException $e) {
    // Ignore
}

// Insérer les sources RSS par défaut si table vide
$count = $db->query("SELECT COUNT(*) FROM rss_sources")->fetchColumn();
if ($count == 0) {
    echo "\n  Importing default RSS sources...\n";
    $sources = [
        ['Le Monde', 'https://www.lemonde.fr/rss/une.xml', 'chaos-politique'],
        ['Le Figaro', 'https://www.lefigaro.fr/rss/figaro_actualites.xml', 'chaos-politique'],
        ['Liberation', 'https://www.liberation.fr/arc/outboundfeeds/rss-all/collection/accueil-une/', 'declin-societal'],
        ['BFM TV', 'https://www.bfmtv.com/rss/news-24-7/', 'chaos-politique'],
        ['20 Minutes', 'https://www.20minutes.fr/feeds/rss-une.xml', 'declin-societal'],
        ['France 24', 'https://www.france24.com/fr/rss', 'chaos-politique']
    ];

    $stmt = $db->prepare("INSERT INTO rss_sources (name, url, category) VALUES (?, ?, ?)");
    foreach ($sources as $source) {
        try {
            $stmt->execute($source);
            echo "    + {$source[0]}\n";
        } catch (PDOException $e) {
            // Ignore duplicates
        }
    }
}

// Insérer config par défaut
$defaultConfig = [
    'openrouter_model' => 'openai/gpt-5.2',
    'replicate_model' => 'google/gemini-3-pro-image',
    'generation_interval' => 3,
    'articles_per_generation' => 1,
    'content_prompt' => "Tu es un journaliste satirique français de ToutVaMal.fr. Tu dois transformer cette actualité en article humoristique et cynique, dans le style du Gorafi. Ton personnage est {journalist_name}, {journalist_role}. Style: {journalist_style}",
    'image_prompt_template' => "Editorial cartoon style, satirical French newspaper illustration, {subject}, minimalist, black and white with accent color, professional press quality"
];

foreach ($defaultConfig as $key => $value) {
    try {
        $db->prepare("INSERT OR IGNORE INTO config (key, value) VALUES (?, ?)")
           ->execute([$key, is_array($value) ? json_encode($value) : $value]);
    } catch (PDOException $e) {
        // Ignore
    }
}
echo "\n  [OK] Default config inserted\n";

// Insérer SEO settings par défaut
try {
    $db->exec("INSERT OR IGNORE INTO seo_settings (id, site_title, site_description) VALUES (1, 'ToutVaMal.fr', 'C''était mieux avant - Site satirique d''information')");
    echo "  [OK] Default SEO settings inserted\n";
} catch (PDOException $e) {
    // Ignore
}

echo "\n=== Migration Complete ===\n";
echo "Success: $success | Skipped: $skipped | Errors: $errors\n";

exit($errors > 0 ? 1 : 0);
