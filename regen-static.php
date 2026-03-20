<?php
require __DIR__ . "/config.php";
$db = db();

$stmt = $db->query("SELECT id, slug FROM articles WHERE status = \"published\" ORDER BY id DESC");
$count = 0;
$errors = [];

while ($row = $stmt->fetch()) {
    $url = SITE_URL . "/article.php?slug=" . $row["slug"];
    
    $ctx = stream_context_create(["http" => ["timeout" => 10]]);
    $html = @file_get_contents($url, false, $ctx);
    
    if ($html && strlen($html) > 5000) {
        $filepath = ARTICLES_PATH . "/" . $row["slug"] . ".html";
        file_put_contents($filepath, $html);
        chmod($filepath, 0644);
        $count++;
    } else {
        $errors[] = $row["id"];
    }
}

echo "Regenerated: $count\n";
if (!empty($errors)) echo "Errors: " . implode(", ", $errors) . "\n";
