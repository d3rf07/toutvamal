<?php
/**
 * ToutVaMal.fr - Page L'Équipe
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'L\'Équipe du Malheur - ' . SITE_NAME;
$pageDescription = 'Découvrez les journalistes désabusés qui font de ToutVaMal.fr le site satirique le plus déprimant de France. Une équipe soudée par le désespoir.';
$currentCategory = '';

// Récupérer l'équipe depuis la DB
$stmt = db()->query("SELECT * FROM journalists WHERE active = 1 ORDER BY id");
$team = $stmt->fetchAll();

include __DIR__ . '/templates/header.php';
?>

<main class="container main-layout">
    <div class="content">
        <article class="page-editorial">
            <header class="page-editorial-header">
                <p class="page-kicker">La Rédaction</p>
                <h1>L'Équipe du Malheur</h1>
                <p class="page-subtitle">Neuf professionnels unis par une même passion : annoncer les pires nouvelles avec le sourire (enfin, un sourire crispé, mais quand même).</p>
            </header>

            <div class="equipe-list">
                <?php foreach ($team as $i => $member): ?>
                <a href="/equipe/<?= htmlspecialchars($member['slug']) ?>.html" class="equipe-member <?= $i === 0 ? 'equipe-member--featured' : '' ?>">
                    <div class="equipe-member-photo">
                        <img src="<?= htmlspecialchars($member['photo_path'] ?: '/equipe/default.webp') ?>"
                             alt="<?= htmlspecialchars($member['name']) ?>"
                             loading="<?= $i < 3 ? 'eager' : 'lazy' ?>" width="300" height="300">
                    </div>
                    <div class="equipe-member-info">
                        <h3><?= htmlspecialchars($member['name']) ?></h3>
                        <p class="equipe-member-role"><?= htmlspecialchars($member['role']) ?></p>
                        <?php if (!empty($member['bio'])): ?>
                        <p class="equipe-member-bio"><?= htmlspecialchars(mb_substr($member['bio'], 0, 200)) ?><?= mb_strlen($member['bio']) > 200 ? '...' : '' ?></p>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <footer class="page-editorial-footer">
                <a href="/a-propos.html" class="btn btn-primary">À propos du site</a>
                <a href="/" class="btn btn-secondary">Lire les articles</a>
            </footer>
        </article>
    </div>

    <?php include __DIR__ . '/templates/sidebar.php'; ?>
</main>

<?php include __DIR__ . '/templates/footer.php'; ?>
