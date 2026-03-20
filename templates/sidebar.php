<!-- Sidebar -->
<aside class="sidebar">
    <!-- Le Chiffre du Jour -->
    <?php
    $chiffres = [
        ['chiffre' => '847', 'label' => 'réunions inutiles se tiennent en ce moment en France', 'source' => 'Institut du Temps Perdu'],
        ['chiffre' => '3,7', 'label' => 'secondes d\'espoir moyen avant la prochaine mauvaise nouvelle', 'source' => 'Baromètre du Désespoir'],
        ['chiffre' => '12', 'label' => 'personnes ont encore confiance dans l\'avenir (marge d\'erreur : 12)', 'source' => 'Sondage ToutVaMal'],
        ['chiffre' => '99,8%', 'label' => 'des lundi matins confirment que le week-end était trop court', 'source' => 'Observatoire du Spleen'],
        ['chiffre' => '∞', 'label' => 'raisons de ne pas sortir du lit demain matin', 'source' => 'La Rédaction'],
        ['chiffre' => '42', 'label' => 'fois que vous avez vérifié votre téléphone pour rien aujourd\'hui', 'source' => 'Étude CNRS du Vide'],
        ['chiffre' => '0', 'label' => 'problèmes résolus en se disant "ça ira mieux demain"', 'source' => 'Archives du Déni'],
    ];
    $chiffre = $chiffres[date('z') % count($chiffres)];
    ?>
    <section class="sidebar-section sidebar-chiffre">
        <h4>Le Chiffre du Jour</h4>
        <div class="chiffre-display"><?= $chiffre['chiffre'] ?></div>
        <p class="chiffre-label"><?= htmlspecialchars($chiffre['label']) ?></p>
        <small class="chiffre-source">Source : <?= htmlspecialchars($chiffre['source']) ?></small>
    </section>

    <!-- Amazon Affiliate 1 : Casque -->
    <section class="sidebar-section sidebar-amazon">
        <a href="https://www.amazon.fr/dp/B09Y2MYL5C?tag=d3rf21-21" target="_blank" rel="nofollow noopener sponsored" class="amazon-box">
            <span class="amazon-label">SUGGESTION</span>
            <img src="/images/amazon-casque.webp" alt="Casque anti-bruit" class="amazon-img" loading="lazy" width="320" height="140">
            <div class="amazon-text">
                <h5>Besoin de vous isoler du chaos ambiant ?</h5>
                <p>&laquo; Le silence, c'est le nouveau luxe. &raquo;</p>
                <span class="amazon-cta">Découvrir &rarr;</span>
            </div>
            <small class="amazon-disclaimer">Lien affilié Amazon</small>
        </a>
    </section>

    <!-- Newsletter -->
    <section class="sidebar-section" id="newsletter">
        <h4>Newsletter</h4>
        <p style="font-size: 0.875rem; color: var(--gris-500); margin-bottom: 1rem;">
            Recevez le pire de l'actualité directement dans votre boîte mail.
        </p>
        <form class="newsletter-form" method="post" action="/api/v2/newsletter.php" aria-label="Inscription newsletter">
            <label for="newsletter-email" class="sr-only">Adresse email</label>
            <input id="newsletter-email" name="email" type="email" placeholder="Votre email" autocomplete="email" inputmode="email" required aria-required="true">
            <button type="submit">S'abonner au désespoir</button>
        </form>
    </section>

    <!-- La Citation du Jour -->
    <?php
    $citations = [
        ['texte' => 'Le futur, c\'est comme le passé, mais en pire.', 'auteur' => 'Jean-Michel Deparve'],
        ['texte' => 'Optimiste : quelqu\'un qui n\'a pas encore lu les nouvelles du matin.', 'auteur' => 'Martine Nostalvielle'],
        ['texte' => 'Le PIB augmente, mais personne ne sait où il va.', 'auteur' => 'Sylvie Sitriste'],
        ['texte' => 'La seule chose qui ne s\'effondre pas, c\'est le nombre de choses qui s\'effondrent.', 'auteur' => 'Pierre Castastroche'],
        ['texte' => 'J\'ai essayé le positivisme. Mon médecin me l\'a déconseillé.', 'auteur' => 'Bernard Bourose'],
        ['texte' => 'Les réseaux sociaux, c\'est comme les voisins : on voit tout, on comprend rien.', 'auteur' => 'Géraldine Glokysta'],
        ['texte' => 'La retraite, c\'est comme l\'horizon : plus on avance, plus ça recule.', 'auteur' => 'René Mégot'],
    ];
    $citation = $citations[date('z') % count($citations)];
    ?>
    <section class="sidebar-section sidebar-citation">
        <h4>La Citation du Jour</h4>
        <blockquote class="citation-texte">&laquo; <?= htmlspecialchars($citation['texte']) ?> &raquo;</blockquote>
        <cite class="citation-auteur">— <?= htmlspecialchars($citation['auteur']) ?></cite>
    </section>

    <!-- Amazon Affiliate 2 : Kit Survie -->
    <section class="sidebar-section sidebar-amazon">
        <a href="https://www.amazon.fr/dp/B0D798Q29V?tag=d3rf21-21" target="_blank" rel="nofollow noopener sponsored" class="amazon-box">
            <span class="amazon-label">SUGGESTION</span>
            <img src="/images/amazon-survie.webp" alt="Kit de survie" class="amazon-img" loading="lazy" width="320" height="140">
            <div class="amazon-text">
                <h5>Prêt pour quand LinkedIn ne suffira plus ?</h5>
                <p>&laquo; Plan B : la vraie compétence de demain. &raquo;</p>
                <span class="amazon-cta">Découvrir &rarr;</span>
            </div>
            <small class="amazon-disclaimer">Lien affilié Amazon</small>
        </a>
    </section>

    <!-- Baromètre du Malheur -->
    <?php
    $niveaux = [
        ['niveau' => 85, 'label' => 'CRITIQUE', 'couleur' => '#DC2626', 'desc' => 'Restez chez vous. Ou pas, dehors c\'est pareil.'],
        ['niveau' => 72, 'label' => 'ÉLEVÉ', 'couleur' => '#EA580C', 'desc' => 'Le moral des ménages est au sous-sol.'],
        ['niveau' => 91, 'label' => 'ALARMANT', 'couleur' => '#991B1B', 'desc' => 'Même les pessimistes sont surpris.'],
        ['niveau' => 67, 'label' => 'PRÉOCCUPANT', 'couleur' => '#D97706', 'desc' => 'Situation normale pour la France.'],
        ['niveau' => 95, 'label' => 'MAXIMAL', 'couleur' => '#7F1D1D', 'desc' => 'On pensait pas que c\'était possible.'],
    ];
    $barometre = $niveaux[date('z') % count($niveaux)];
    ?>
    <section class="sidebar-section sidebar-barometre">
        <h4>Baromètre du Malheur</h4>
        <div class="barometre-gauge">
            <div class="barometre-fill" style="width: <?= $barometre['niveau'] ?>%; background: <?= $barometre['couleur'] ?>;"></div>
        </div>
        <div class="barometre-niveau" style="color: <?= $barometre['couleur'] ?>;"><?= $barometre['niveau'] ?>% — <?= $barometre['label'] ?></div>
        <p class="barometre-desc"><?= htmlspecialchars($barometre['desc']) ?></p>
    </section>

    <!-- L'Équipe -->
    <section class="sidebar-section">
        <h4>L'Équipe</h4>
        <p style="font-size: 0.875rem; color: var(--gris-500); margin-bottom: 1rem;">
            Nos journalistes du malheur vous informent quotidiennement.
        </p>
        <a href="/equipe.html" style="display: inline-block; color: var(--rouge); font-weight: 600; font-size: 0.875rem;">
            Découvrir la rédaction &rarr;
        </a>
    </section>

    <!-- Amazon Affiliate 3 : Livre Effondrement -->
    <section class="sidebar-section sidebar-amazon">
        <a href="https://www.amazon.fr/dp/2021223310?tag=d3rf21-21" target="_blank" rel="nofollow noopener sponsored" class="amazon-box">
            <span class="amazon-label">LECTURE</span>
            <img src="/images/amazon-effondrement.webp" alt="Comment tout peut s'effondrer" class="amazon-img" loading="lazy" width="320" height="140">
            <div class="amazon-text">
                <h5>Envie de comprendre pourquoi tout s'effondre ?</h5>
                <p>&laquo; Spoiler : c'est pas que de votre faute. &raquo;</p>
                <span class="amazon-cta">Découvrir &rarr;</span>
            </div>
            <small class="amazon-disclaimer">Lien affilié Amazon</small>
        </a>
    </section>

    <!-- Petites Annonces du Désespoir -->
    <?php
    $annonces = [
        ['type' => 'RECHERCHE', 'texte' => 'Optimiste cherche raison de l\'être. Références exigées.'],
        ['type' => 'VENDS', 'texte' => 'Boule de cristal. Jamais servie (j\'avais trop peur de regarder).'],
        ['type' => 'OFFRE', 'texte' => 'Épaule pour pleurer. Disponible 24h/24. Apportez vos mouchoirs.'],
        ['type' => 'PERDU', 'texte' => 'Ma motivation. Dernière fois vue un lundi. Récompense symbolique.'],
        ['type' => 'CÈDE', 'texte' => 'Collection complète de promesses électorales. État neuf (jamais tenues).'],
        ['type' => 'ÉCHANGE', 'texte' => 'Angoisse existentielle contre recette de gâteau au chocolat.'],
        ['type' => 'RECRUTE', 'texte' => 'Entreprise cherche stagiaire motivé. Le stage est non rémunéré, mais la dépression est gratuite.'],
    ];
    // Afficher 2 annonces par jour
    $idx1 = date('z') % count($annonces);
    $idx2 = (date('z') + 3) % count($annonces);
    ?>
    <section class="sidebar-section sidebar-annonces">
        <h4>Petites Annonces</h4>
        <div class="annonce">
            <span class="annonce-type"><?= $annonces[$idx1]['type'] ?></span>
            <p><?= htmlspecialchars($annonces[$idx1]['texte']) ?></p>
        </div>
        <div class="annonce">
            <span class="annonce-type"><?= $annonces[$idx2]['type'] ?></span>
            <p><?= htmlspecialchars($annonces[$idx2]['texte']) ?></p>
        </div>
    </section>

    <!-- À Propos -->
    <section class="sidebar-section">
        <h4>À Propos</h4>
        <p style="font-size: 0.875rem; color: var(--gris-500); margin-bottom: 1rem;">
            ToutVaMal.fr : le site qui prouve que c'était vraiment mieux avant.
        </p>
        <a href="/a-propos.html" style="display: inline-block; color: var(--rouge); font-weight: 600; font-size: 0.875rem;">
            En savoir plus &rarr;
        </a>
    </section>

    <!-- Amazon Affiliate 4 : Sapiens -->
    <section class="sidebar-section sidebar-amazon">
        <a href="https://www.amazon.fr/dp/2226257012?tag=d3rf21-21" target="_blank" rel="nofollow noopener sponsored" class="amazon-box">
            <span class="amazon-label">LECTURE</span>
            <img src="/images/amazon-sapiens.webp" alt="Sapiens" class="amazon-img" loading="lazy" width="320" height="140">
            <div class="amazon-text">
                <h5>Comment en est-on arrivé là, au juste ?</h5>
                <p>&laquo; 300 000 ans de mauvaises décisions expliqués. &raquo;</p>
                <span class="amazon-cta">Découvrir &rarr;</span>
            </div>
            <small class="amazon-disclaimer">Lien affilié Amazon</small>
        </a>
    </section>
</aside>
