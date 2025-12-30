<?php
/**
 * ToutVaMal.fr - Import Backup Data
 * Run once after deployment
 */

require_once dirname(__DIR__) . '/config.php';

echo "=== Importing Backup Data ===\n\n";

$backupPath = dirname(ROOT_PATH) . '/backup';

// 1. Import Journalists
echo "Importing journalists...\n";
$journalists = json_decode(file_get_contents($backupPath . '/journalists.json'), true);

$stmt = db()->prepare("
    INSERT OR REPLACE INTO journalists (id, slug, name, role, style, bio, photo_path, badge, mood, active)
    VALUES (:id, :slug, :name, :role, :style, :bio, :photo_path, :badge, :mood, :active)
");

foreach ($journalists as $j) {
    $stmt->execute([
        ':id' => $j['id'],
        ':slug' => $j['slug'],
        ':name' => $j['name'],
        ':role' => $j['role'],
        ':style' => $j['style'],
        ':bio' => $j['bio'],
        ':photo_path' => $j['photo_path'],
        ':badge' => $j['badge'],
        ':mood' => $j['mood'],
        ':active' => $j['active']
    ]);
    echo "  - {$j['name']}\n";
}
echo "Imported " . count($journalists) . " journalists.\n\n";

// 2. Import Newsletter subscribers
echo "Importing newsletter subscribers...\n";
$newsletter = json_decode(file_get_contents($backupPath . '/newsletter.json'), true);

$stmt = db()->prepare("
    INSERT OR IGNORE INTO newsletter (id, email, subscribed_at, unsubscribed_at, source, confirmation_token, confirmed_at)
    VALUES (:id, :email, :subscribed_at, :unsubscribed_at, :source, :token, :confirmed_at)
");

foreach ($newsletter as $n) {
    $stmt->execute([
        ':id' => $n['id'],
        ':email' => $n['email'],
        ':subscribed_at' => $n['subscribed_at'],
        ':unsubscribed_at' => $n['unsubscribed_at'],
        ':source' => $n['source'],
        ':token' => $n['confirmation_token'],
        ':confirmed_at' => $n['confirmed_at']
    ]);
    echo "  - {$n['email']}\n";
}
echo "Imported " . count($newsletter) . " subscribers.\n\n";

// 3. Import June articles (IDs 9, 10, 11)
echo "Importing June articles...\n";
$articles = json_decode(file_get_contents($backupPath . '/articles_june.json'), true);

$stmt = db()->prepare("
    INSERT OR REPLACE INTO articles (id, slug, title, content, excerpt, category, image_path, journalist_id, source_title, source_url, published_at)
    VALUES (:id, :slug, :title, :content, :excerpt, :category, :image_path, :journalist_id, :source_title, :source_url, :published_at)
");

foreach ($articles as $a) {
    $stmt->execute([
        ':id' => $a['id'],
        ':slug' => $a['slug'],
        ':title' => $a['title'],
        ':content' => $a['content'],
        ':excerpt' => $a['excerpt'] ?? null,
        ':category' => $a['category'],
        ':image_path' => $a['image_path'],
        ':journalist_id' => $a['journalist_id'],
        ':source_title' => $a['source_title'] ?? null,
        ':source_url' => $a['source_url'] ?? null,
        ':published_at' => $a['published_at']
    ]);
    echo "  - {$a['title']}\n";
}
echo "Imported " . count($articles) . " articles.\n\n";

echo "=== Import Complete ===\n";
