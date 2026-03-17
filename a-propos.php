<?php
/**
 * ToutVaMal.fr - Page À Propos
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'À Propos - ' . SITE_NAME;
$pageDescription = 'ToutVaMal.fr est un site satirique français qui couvre l\'actualité sous l\'angle du pire. Parce que tout va mal, mais au moins on en rit.';
$currentCategory = '';

include __DIR__ . '/templates/header.php';
?>

<main class="container main-layout">
    <div class="content">
        <article class="page-editorial">
            <!-- En-tête -->
            <header class="page-editorial-header">
                <p class="page-kicker">Qui sommes-nous</p>
                <h1>À Propos de ToutVaMal.fr</h1>
                <p class="page-subtitle">Dans un monde où tout va bien (paraît-il), nous nous sommes donné pour mission sacrée de rétablir la vérité.</p>
            </header>

            <!-- Corps éditorial -->
            <div class="page-editorial-body">
                <p class="drop-cap">Fondé en 2024 par une bande de journalistes fatigués de devoir mettre des émojis positifs dans leurs articles, <strong>ToutVaMal.fr</strong> transforme l'actualité quotidienne en ce qu'elle devrait être : un rappel constant que l'apocalypse est non seulement imminente, mais probablement déjà en cours.</p>

                <p>Chaque matin, notre rédaction se réunit autour d'un café tiède et d'un journal qu'elle refuse d'ouvrir. Puis, armée de son pessimisme légendaire, elle produit les articles que vous méritez : des analyses faussement catastrophistes, des brèves authentiquement déprimantes, et des éditoriaux qui feraient pleurer un coach en développement personnel.</p>

                <h2>Nos Principes</h2>

                <p><strong>Pessimisme éclairé.</strong> Nous croyons que voir le verre à moitié vide, c'est déjà être trop optimiste. Le verre est probablement fissuré.</p>

                <p><strong>Rigueur satirique.</strong> Nos fake news sont plus vraies que les vraies. Chaque article est méticuleusement déprimant, sourcé avec la plus grande mauvaise foi.</p>

                <p><strong>Anticipation du pire.</strong> Pourquoi attendre la catastrophe quand on peut la prédire ? Nous avons toujours une longueur d'avance sur le désastre.</p>

                <p><strong>Sincérité brutale.</strong> Pas de langue de bois ici. Si ça ne fait pas mal, ce n'est pas de l'information, c'est de la propagande positive.</p>

                <!-- Manifeste -->
                <blockquote class="editorial-quote">
                    <p>Nous croyons fermement qu'un article n'est bon que s'il provoque au minimum trois soupirs et une envie irrépressible de retourner se coucher. Si vous souriez en nous lisant, c'est que vous n'avez pas compris la gravité de la situation.</p>
                    <cite>— La Rédaction de ToutVaMal.fr</cite>
                </blockquote>

                <h2>Notre Engagement</h2>

                <p><strong>ToutVaMal.fr</strong> est un site satirique. Nos articles sont des parodies de l'actualité. Nous ne prétendons pas à l'objectivité — seulement au pessimisme le plus raffiné.</p>

                <p>Si vous cherchez de vraies informations, vous êtes au mauvais endroit. Mais si vous voulez rire de l'absurdité du monde avec nous, bienvenue au club. Rédigés avec amour (et beaucoup de caféine) pour vous faire sourire jaune.</p>
            </div>

            <!-- Stats en bas de page -->
            <div class="page-stats-bar">
                <div class="page-stat">
                    <span class="page-stat-number">&infin;</span>
                    <span class="page-stat-label">Raisons de désespérer</span>
                </div>
                <div class="page-stat">
                    <span class="page-stat-number">0</span>
                    <span class="page-stat-label">Raisons d'espérer</span>
                </div>
                <div class="page-stat">
                    <span class="page-stat-number">9</span>
                    <span class="page-stat-label">Journalistes las</span>
                </div>
                <div class="page-stat">
                    <span class="page-stat-number">2024</span>
                    <span class="page-stat-label">Fondation</span>
                </div>
            </div>

            <!-- CTA -->
            <footer class="page-editorial-footer">
                <a href="/equipe.html" class="btn btn-primary">Rencontrer l'Équipe</a>
                <a href="/" class="btn btn-secondary">Lire les Articles</a>
            </footer>
        </article>
    </div>

    <?php include __DIR__ . '/templates/sidebar.php'; ?>
</main>

<?php include __DIR__ . '/templates/footer.php'; ?>
