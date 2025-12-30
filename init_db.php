<?php
/**
 * ToutVaMal.fr - Initialisation Base de Données
 * Rebuild 2025-12-30
 */

require_once __DIR__ . '/config.php';

// Créer le dossier data si nécessaire
if (!is_dir(DATA_PATH)) {
    mkdir(DATA_PATH, 0755, true);
}

$pdo = db();

// Création des tables
$pdo->exec("
    CREATE TABLE IF NOT EXISTS articles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT UNIQUE NOT NULL,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        excerpt TEXT,
        category TEXT NOT NULL,
        image_path TEXT,
        journalist_id INTEGER,
        source_title TEXT,
        source_url TEXT,
        published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS journalists (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        role TEXT NOT NULL,
        style TEXT,
        bio TEXT,
        photo_path TEXT,
        badge TEXT,
        mood TEXT,
        active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS newsletter (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT UNIQUE NOT NULL,
        subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        unsubscribed_at DATETIME,
        source TEXT DEFAULT 'website',
        confirmation_token TEXT,
        confirmed_at DATETIME
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS generation_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        source_url TEXT,
        source_title TEXT,
        article_id INTEGER,
        status TEXT,
        error_message TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// Index pour performance
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_articles_category ON articles(category)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_articles_published ON articles(published_at DESC)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_articles_journalist ON articles(journalist_id)");

echo "Base de données initialisée avec succès.\n";
