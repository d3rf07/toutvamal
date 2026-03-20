<?php
/**
 * ToutVaMal.fr - Page Archives
 */

require_once __DIR__ . "/config.php";

// Pagination
$page = max(1, intval($_GET["page"] ?? 1));
$perPage = 24;
$offset = ($page - 1) * $perPage;

// Filtre par mois optionnel
$month = $_GET["mois"] ?? "";

// Compter le total
$countSql = "SELECT COUNT(*) FROM articles WHERE status = :status";
$countParams = [":status" => "published"];
if ($month && preg_match("/^\d{4}-\d{2}$/", $month)) {
    $countSql .= " AND strftime(\"%Y-%m\", published_at) = :month";
    $countParams[":month"] = $month;
}
$countStmt = db()->prepare($countSql);
$countStmt->execute($countParams);
$total = $countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

// Récupérer les articles
$sql = "SELECT a.*, j.name as journalist_name, j.photo_path as journalist_photo
        FROM articles a
        LEFT JOIN journalists j ON a.journalist_id = j.id
        WHERE a.status = :status";
$params = [":status" => "published"];
if ($month && preg_match("/^\d{4}-\d{2}$/", $month)) {
    $sql .= " AND strftime(\"%Y-%m\", a.published_at) = :month";
    $params[":month"] = $month;
}
$sql .= " ORDER BY a.published_at DESC LIMIT :limit OFFSET :offset";

$stmt = db()->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(":limit", $perPage, PDO::PARAM_INT);
$stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
$stmt->execute();
$articles = $stmt->fetchAll();

// Récupérer les mois disponibles pour le filtre
$monthsStmt = db()->query("
    SELECT DISTINCT strftime(\"%Y-%m\", published_at) as ym,
           strftime(\"%Y\", published_at) as y,
           strftime(\"%m\", published_at) as m
    FROM articles
    WHERE status = \"published\"
    ORDER BY ym DESC
");
$availableMonths = $monthsStmt->fetchAll();

$monthNames = ["01"=>"Janvier","02"=>"Février","03"=>"Mars","04"=>"Avril",
               "05"=>"Mai","06"=>"Juin","07"=>"Juillet","08"=>"Août",
               "09"=>"Septembre","10"=>"Octobre","11"=>"Novembre","12"=>"Décembre"];

// SEO
$pageTitle = "Archives" . ($month ? " - " . ($monthNames[substr($month,5)] ?? "") . " " . substr($month,0,4) : "") . " | " . SITE_NAME;
$pageDescription = "Toutes les archives satiriques de ToutVaMal.fr. $total articles de mauvaises nouvelles à parcourir.";
$currentCategory = "";

include __DIR__ . "/templates/header.php";
?>

<main class="container main-layout">
    <div class="content">
        <header style="margin-bottom: 2rem;">
            <h1 style="font-family: var(--font-display); font-size: 2.5rem; margin-bottom: 0.5rem;">
                Archives du Malheur
            </h1>
            <p style="color: var(--gris-600);">
                <?= $total ?> article<?= $total > 1 ? "s" : "" ?> au catalogue de la catastrophe
            </p>
        </header>

        <!-- Filtre par mois -->
        <?php if (!empty($availableMonths)): ?>
        <div style="margin-bottom: 2rem; display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
            <a href="/archives.html" style="padding: 0.375rem 0.75rem; border-radius: 4px; font-size: 0.875rem; text-decoration: none; <?= empty($month) ? "background: var(--noir); color: white;" : "background: var(--gris-100); color: var(--gris-700);" ?>">
                Tous
            </a>
            <?php foreach ($availableMonths as $m): ?>
            <a href="/archives.html?mois=<?= $m["ym"] ?>"
               style="padding: 0.375rem 0.75rem; border-radius: 4px; font-size: 0.875rem; text-decoration: none; <?= $month === $m["ym"] ? "background: var(--noir); color: white;" : "background: var(--gris-100); color: var(--gris-700);" ?>">
                <?= ($monthNames[$m["m"]] ?? $m["m"]) . " " . $m["y"] ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($articles)): ?>
        <div style="text-align: center; padding: 4rem 0; color: var(--gris-500);">
            <p style="font-size: 1.25rem;">Aucun article pour cette période.</p>
            <a href="/archives.html" style="display: inline-block; margin-top: 1rem; color: var(--rouge);">Voir toutes les archives</a>
        </div>
        <?php else: ?>

        <!-- Articles Grid -->
        <div class="articles-grid">
            <?php foreach ($articles as $article): ?>
            <a href="/articles/<?= htmlspecialchars($article["slug"]) ?>.html" class="article-card">
                <div class="article-card-image">
                    <img src="<?= htmlspecialchars($article["image_path"] ?: "/images/placeholder.jpg") ?>"
                         alt="<?= htmlspecialchars($article["title"]) ?>"
                         width="400" height="225" loading="lazy">
                </div>
                <div class="article-card-content">
                    <span class="badge badge-outline"><?= htmlspecialchars(CATEGORIES[$article["category"]] ?? $article["category"]) ?></span>
                    <h3><?= htmlspecialchars($article["title"]) ?></h3>
                    <div class="meta">
                        <span><?= htmlspecialchars($article["journalist_name"] ?? "La Rédaction") ?></span>
                        <span><?= format_date($article["published_at"]) ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 2rem; padding: 1rem 0;">
            <?php if ($page > 1): ?>
            <a href="/archives.html?page=<?= $page - 1 ?><?= $month ? "&mois=$month" : "" ?>"
               style="padding: 0.5rem 1rem; background: var(--gris-100); border-radius: 4px; text-decoration: none; color: var(--noir);">
                &larr; Précédent
            </a>
            <?php endif; ?>

            <span style="padding: 0.5rem 1rem; color: var(--gris-600);">
                Page <?= $page ?> / <?= $totalPages ?>
            </span>

            <?php if ($page < $totalPages): ?>
            <a href="/archives.html?page=<?= $page + 1 ?><?= $month ? "&mois=$month" : "" ?>"
               style="padding: 0.5rem 1rem; background: var(--gris-100); border-radius: 4px; text-decoration: none; color: var(--noir);">
                Suivant &rarr;
            </a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <?php include __DIR__ . "/templates/sidebar.php"; ?>
</main>

<?php include __DIR__ . "/templates/footer.php"; ?>
