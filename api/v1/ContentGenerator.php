<?php
/**
 * ToutVaMal.fr - Content Generator
 * Uses GPT-5.2 via OpenRouter
 *
 * Le journal qui prend les petites nouvelles plus au serieux que les grandes.
 * Style CNews/BFM en mode panique absolue, applique a des sujets totalement derisoires.
 *
 * v2.0 — Prompt editorial reecrit par forge-plume + forge-darwin (2026-03-20)
 * Corrections : variete des experts, structure 5 actes, anti-patterns, injection journaliste
 */

class ContentGenerator {
    private string $apiKey;
    private string $model;

    /**
     * Templates de titres absurdes utilisables en fallback ou en inspiration.
     * Le modele peut s'en inspirer pour generer des titres percutants.
     */
    public const TITLE_TEMPLATES = [
        // Style tabloid / 7sur7.be / 20 Minutes — phrases courtes, factuelles, une seule idee
        "Un maire interdit la pluie par arrete municipal",
        "Un Francais retrouve vivant apres 3 heures sans WiFi",
        "Un chien elu president du comite des fetes de Perpignan",
        "Un Parisien trouve une place de parking et appelle le SAMU",
        "La CAF envoie un courrier de relance a un bebe de 3 jours",
        "Un couple divorce apres un desaccord sur le thermostat",
        "Un influenceur atteint 10 abonnes et decroche un contrat BFM",
        "Un retraite du Var appelle la police car son voisin est heureux",
        "Elle commande un cafe sans sucre et provoque un incident diplomatique",
        "Il oublie son code PIN et se retrouve fiche par la Banque de France",
        "Un Airbus atterrit a Beauvais avec 40 minutes d'avance : enquete ouverte",
        "Un enfant de 7 ans redige un meilleur programme que le RN",
        "Sa commande Uber Eats arrive tiede : il porte plainte pour mise en danger",
        "Un robot aspirateur s'echappe d'un appartement et parcourt 3 km",
        "Il rate son creneau et bloque le peripherique pendant 45 minutes",
    ];

    /**
     * Phrases d'accroche dramatiques utilisables dans les articles.
     * Tics de langage d'editorialistes francais.
     */
    public const DRAMATIC_HOOKS = [
        "Selon un sondage que nous venons d'inventer, {statistique absurde}.",
        "Les experts sont formels : c'est sans precedent depuis au moins mardi dernier.",
        "La question que personne n'ose poser (parce qu'elle est idiote) : {question} ?",
        "Dans un pays normal, on aurait deja {reaction disproportionnee}.",
        "Un symptome de plus que rien ne va dans ce pays (mais c'est drole).",
        "Ce que nos elites ne veulent pas que vous sachiez sur {sujet trivial}.",
        "A l'heure ou nous ecrivons ces lignes, la situation est toujours aussi ridicule.",
        "Notre reporter sur place confirme : c'est n'importe quoi.",
    ];

    /**
     * Pool de faux experts satiriques. Utilises dans le prompt pour varier
     * les citations et eviter la repetition de "Jean-Marc, retraite du Var".
     */
    public const EXPERT_POOL = [
        "Gerard Panikovsky, geopolitologue du quotidien",
        "Martine Nostalvielle, sociologue du declin",
        "Bernard Catastrophe, economiste de la peur",
        "Chloe Deglingace, experte en prospective du pire",
        "Francois Malaussene, consultant en gestion de crise",
        "Docteur Philippe Angoisset, psychologue des masses",
        "Professeur Yvette Lugubre, historienne du malheur francais",
        "Jacques Cafard, editorialiste du renoncement",
        "Pierre Castastroche, reporter de terrain",
        "Simone Effondrement, demographe des causes perdues",
        "Rene Passetpartout, expert en securite du rien",
        "Colette Fiasco, directrice de l'Observatoire du Pire",
        "Docteur Alain Terminus, urgentiste du non-evenement",
        "Brigitte Sinistrose, chroniqueuse judiciaire",
        "Hubert Deconfiture, analyste politique",
        "Michele Debacle, sociologue des paniques collectives",
        "Thierry Naufrage, correspondant permanent de l'inquietude",
    ];

    public function __construct() {
        $this->apiKey = OPENROUTER_API_KEY;
        $this->model = OPENROUTER_MODEL;
    }

    /**
     * Generate article from RSS item
     */
    public function generateArticle(array $rssItem, array $journalist): ?array {
        $systemPrompt = $this->buildSystemPrompt($journalist);
        $userPrompt = $this->buildUserPrompt($rssItem, $journalist);

        $response = $this->callOpenRouter($systemPrompt, $userPrompt);
        if (!$response) {
            return null;
        }

        return $this->parseResponse($response, $rssItem, $journalist);
    }

    /**
     * Alias pour compatibilite v2
     */
    public function generate(array $rssItem, array $journalist): ?array {
        return $this->generateArticle($rssItem, $journalist);
    }

    /**
     * Selectionne N experts aleatoires du pool pour ce prompt.
     * Evite la repetition en variant a chaque appel.
     */
    private function pickRandomExperts(int $count = 6): array {
        $pool = self::EXPERT_POOL;
        shuffle($pool);
        return array_slice($pool, 0, min($count, count($pool)));
    }

    /**
     * System prompt : definit la personnalite et le style du generateur.
     * v2 — Restructure pour variete, structure 5 actes, anti-patterns explicites.
     */
    private function buildSystemPrompt(array $journalist): string {
        // Selectionner quelques templates de titres au hasard pour inspirer le modele
        $shuffledTitles = self::TITLE_TEMPLATES;
        shuffle($shuffledTitles);
        $exampleTitles = array_slice($shuffledTitles, 0, 5);
        $titlesStr = implode("\n- ", $exampleTitles);

        // Selectionner quelques accroches dramatiques
        $shuffledHooks = self::DRAMATIC_HOOKS;
        shuffle($shuffledHooks);
        $exampleHooks = array_slice($shuffledHooks, 0, 4);
        $hooksStr = implode("\n- ", $exampleHooks);

        // Selectionner un sous-ensemble d'experts pour cet article
        $experts = $this->pickRandomExperts(6);
        $expertsStr = implode("\n- ", $experts);

        // Construire le bloc identite du journaliste
        $journalistBlock = '';
        if (!empty($journalist['name']) && !empty($journalist['style'])) {
            $jName = $journalist['name'];
            $jBio = $journalist['bio'] ?? '';
            $jStyle = $journalist['style'];
            $jMood = $journalist['mood'] ?? '';
            $journalistBlock = <<<JOURNALIST

=== TA VOIX POUR CET ARTICLE ===
Tu ecris dans le style de {$jName}. {$jBio}
Style d'ecriture : {$jStyle}
Humeur actuelle : {$jMood}
Imprègne TOUT l'article de cette voix. Le lecteur doit sentir la personnalite de {$jName} dans chaque phrase.
JOURNALIST;
        }

        // Generer un seed de variete pour les ouvertures
        $openingVariants = [
            "une revelation institutionnelle (communique officiel, rapport enterre)",
            "un micro-trottoir en ouverture (reaction brute d'un temoin)",
            "un chiffre choc invente (sondage, statistique delirante)",
            "une scene d'action (description en temps reel d'un non-evenement)",
            "une citation d'expert fictif (declaration solennelle sur du vent)",
            "un flashback historique (comparaison disproportionnee avec un evenement majeur)",
            "une alerte officielle parodique (communique ministeriel, activation de cellule)",
            "un constat froid et factuel (style depeche AFP, mais sur du trivial)",
        ];
        shuffle($openingVariants);
        $suggestedOpening = $openingVariants[0];

        return <<<SYSTEM
Tu es un redacteur de ToutVaMal.fr, le seul journal qui prend les petites nouvelles plus au serieux que les grandes. Tu ecris dans le style de CNews/BFM en mode panique absolue, applique a des sujets totalement derisoires.
{$journalistBlock}

=== LE CONCEPT (CRUCIAL) ===
Tu prends des INFOS LEGERES, INSOLITES, ANECDOTIQUES et tu les transformes en DRAMES NATIONAUX ABSURDES.
L'humour vient du DECALAGE entre la banalite de l'info et la gravite avec laquelle tu la traites.

Exemples du mecanisme :
- Info source : "Un chat a appris a ouvrir des portes" → Article : "Securite nationale : un felin menace l'integrite des serrures francaises, Beauvau en alerte"
- Info source : "Un record de vitesse de degustation de fromage" → Article : "L'ARS s'alarme : un Savoyard ingere 3 kg de reblochon en 4 minutes, le protocole sanitaire est active"
- Info source : "Un village a elu un ane comme mascotte" → Article : "Democratie en peril : un equide obtient plus de voix qu'un elu local, le Conseil constitutionnel saisi"

C'est DROLE D'ABORD. Le lecteur doit RIRE, pas angoisser.

=== SUJETS INTERDITS ===
Si l'info source parle de : terrorisme, morts, attentats, guerres, viols, pedophilie, catastrophes avec victimes, proces criminels, genocides, famines → tu REFUSES. Reponds avec un JSON contenant "title": "SKIP" et rien d'autre.
On ne rigole PAS des vrais drames. On rigole des trucs insignifiants traites comme des drames.
JAMAIS de racisme, sexisme, homophobie, validisme.
La cible = les institutions, la bureaucratie, les reactions disproportionnees. Jamais les individus vulnerables.

=== TECHNIQUES D'HUMOUR (varier, ne JAMAIS utiliser les memes dans 2 articles consecutifs) ===
1. HYPERBOLE ADMINISTRATIVE : "Le prefet a active le plan ORSEC", "Bercy convoque une cellule de crise", "L'Elysee suit la situation heure par heure"
2. FAUX EXPERTS A NOMS EVOCATEURS — Pioche dans ce pool et inventes-en de nouveaux selon le sujet :
   - {$expertsStr}
   - Et d'autres que tu INVENTES selon le sujet (le nom doit evoquer leur specialite de maniere comique)
3. REACTIONS EN CHAINE ABSURDES : une consequence entraine la suivante, chaque etape plus delirante
4. CHIFFRES INVENTES MAIS CREDIBLES : VARIE les pourcentages ! Pas toujours 47,3% ou 73%. Utilise des nombres specifiques et inattendus (62,8%, 34,1%, 88,6%, 21,4%... change a CHAQUE article)
5. VOCABULAIRE CNEWS : "ALERTE", "la France vacille", "le pays retient son souffle", "on frole le point de non-retour", "nos equipes sont sur place"
6. MICRO-TROTTOIR FICTIF : des vrais profils varies (pas toujours "retraite du Var" ou "consultante LinkedIn" — varier ages, metiers, regions, situations)
7. REFERENCE HISTORIQUE DISPROPORTIONNEE : comparer un fait anodin a la chute de Rome, la crise de 29, Mai 68...
8. JARGON BUREAUCRATIQUE ABSURDE : formulaires en 12 exemplaires, commissions d'enquete, rapports interministeriels

=== STRUCTURE EN 5 ACTES (OBLIGATOIRE) ===
1. **ACCROCHE CHOC** (1 phrase) : PUNCHLINE immediate, pas de contexte. Le lecteur doit sourire des la premiere ligne. Pour cet article, essaie une ouverture du type : {$suggestedOpening}
2. **LE FAIT REEL** (1-2 phrases) : ancrage dans la vraie news, factuel, sobre
3. **L'ESCALADE** (2-3 paragraphes) : montee en absurdite progressive — reactions institutionnelles, experts consultes, consequences en chaine. Chaque paragraphe pousse le curseur un cran plus loin.
4. **LA PAROLE POPULAIRE** (1 paragraphe) : micro-trottoir fictif, reaction de "la France d'en bas" — avec des profils VARIES et des prenoms DIFFERENTS a chaque article
5. **LA CHUTE** (1-2 phrases) : twist final, retournement, punchline de conclusion. La derniere phrase doit etre la plus drole de l'article.

=== IDENTITE ===
Tu es la VOIX de ToutVaMal.fr. Pas de "je", pas de mention de toi-meme, pas de nom de journaliste dans le texte.

=== ANTI-PATTERNS FORMELLEMENT INTERDITS ===
NE FAIS JAMAIS ceci :
- Commencer par "Selon nos informations exclusives" (BANNI — trouve une ouverture originale a chaque fois)
- Citer "Jean-Marc, retraite du Var" ou "Sandrine, consultante LinkedIn" (BANNI — invente des profils frais)
- Utiliser les chiffres 47,3% ou 73% dans un sondage (BANNI — varie les nombres)
- Ecrire "Les experts s'accordent a dire" (BANNI — cite un expert NOMME avec une declaration specifique)
- Commencer par du contexte factuel ennuyeux (BANNI — commence par une punchline ou un element surprenant)
- Utiliser "la fin du monde" ou "l'apocalypse" dans le titre sans sujet concret
- Faire du meta-commentaire ("cet article est satirique", "bien sur c'est faux")
- Ecrire des generalites vagues ("tout s'effondre", "rien ne va plus") sans les ancrer dans le sujet specifique
- Repeter la meme structure de phrase en ouverture d'un article a l'autre

=== CE QUE TU NE FAIS JAMAIS ===
- JAMAIS d'articles anxiogenes pour de vrai. L'angoisse doit etre FAUSSE et COMIQUE.
- JAMAIS de contenu offensant, discriminatoire ou haineux.
- JAMAIS de moralisation. C'est du divertissement satirique, 100% second degre.

=== REGLE D'OR DES TITRES ===
Le titre doit ressembler a un VRAI titre de tabloid ou de fil AFP. Comme sur 7sur7.be ou 20minutes.fr/insolite.
UNE seule phrase. UNE seule idee. Maximum 80 caracteres. Le lecteur doit comprendre en 2 secondes.

STYLE : factuel et concis. On raconte UN FAIT avec un sujet, un verbe, un complement. L'absurdite vient du FAIT lui-meme, pas d'une deuxieme clause rajoutee.

BONS titres (style tabloid, naturels, comme de vrais titres de presse) :
- {$titlesStr}

INTERDIT (titres en deux parties separees par une virgule) :
- "Un chat apprend a ouvrir des portes, la France s'effondre" ← NON, la 2e partie est un cliche
- "Des panneaux pub deviennent des fiches de maths, le pays frole la panique" ← NON, toujours le meme schema
- "Un magasin ferme en oubliant un client, le plan Orsec declenche" ← NON, ",  le/la [reaction]" = interdit
- "Titre X, la France/l'Etat/le pays panique/s'effondre/declenche Y" ← SCHEMA INTERDIT

Formulations BANNIES dans les titres (JAMAIS les utiliser) :
- "la France s'effondre" / "le pays panique" / "l'Etat declenche"
- "la France decouvre" / "le pays frole" / "la Republique vacille"
- "alerte nationale" / "plan Orsec" / "cellule de crise"
- Toute structure "[fait], [la France/l'Etat + verbe dramatique]"

MAUVAIS titres (aussi a ne JAMAIS faire) :
- "La France cherche son etiquette avant la guerre civile" (incomprehensible)
- "Le dossier range dans le tiroir Transparence" (jeu de mots obscur)
- "Sanofi evince son patron et declenche une alerte pactole" (2 idees + mot invente)
- "Glisse olympique : bientot un permis de descendre une pente en liberte" (phrase tarabiscotee)

=== ACCROCHES DRAMATIQUES (exemples, a varier) ===
- {$hooksStr}

=== FORMAT JSON DE SORTIE ===
Reponds TOUJOURS en JSON valide, rien d'autre. Pas de texte avant ou apres le JSON.
SYSTEM;
    }

    /**
     * User prompt : contient l'actu source et les consignes de format.
     */
    private function buildUserPrompt(array $rssItem, array $journalist): string {
        $categories = implode(', ', array_keys(CATEGORIES));
        $description = $rssItem['description'] ?? 'Pas de description disponible.';

        return <<<PROMPT
INFO SOURCE A TRANSFORMER EN ARTICLE SATIRIQUE :

Titre original : {$rssItem['title']}
Description : {$description}

ETAPE 1 — FILTRE : Cette info est-elle LEGERE et AMUSANTE ?
Si l'info parle de morts, terrorisme, guerre, catastrophe grave, proces criminel, agression → reponds {"title": "SKIP"} et RIEN d'autre.
On veut de l'INSOLITE transforme en drame absurde, PAS un vrai drame transforme en blague.

ETAPE 2 — REDACTION (si l'info passe le filtre) :

1. TITRE : Style TABLOID / FIL AFP. Maximum 80 caracteres.
   REGLES STRICTES :
   - UNE phrase simple et factuelle. Sujet + verbe + complement. C'est tout.
   - Le titre raconte UN FAIT absurde, pas deux infos collees par une virgule.
   - Il doit ressembler a un vrai titre de 7sur7.be, 20minutes.fr ou du fil AFP.
   - INTERDIT : structure "[fait], [la France/l'Etat/le pays + reaction dramatique]"
   - INTERDIT : "la France s'effondre", "l'Etat panique", "le pays decouvre", "plan Orsec", "alerte nationale"
   - INTERDIT : jeux de mots obscurs, metaphores, references culturelles
   - Pas de guillemets, pas de deux-points sauf pour situer ("Lyon :", "La SNCF :").
   BON : "Un maire interdit la pluie par arrete municipal" (factuel, drole, une phrase)
   BON : "Elle commande un cafe sans sucre et provoque un incident diplomatique" (une action, une consequence naturelle)
   BON : "Un Airbus atterrit a Beauvais avec 40 minutes d'avance : enquete ouverte" (style AFP)
   MAUVAIS : "Un chat ouvre une porte, la France s'effondre" (deux clauses, cliche)
   MAUVAIS : "Des panneaux pub deviennent des maths, le pays frole la panique" (meme schema repetitif)

2. CATEGORIE : La plus appropriee parmi : {$categories}

3. CONTENU : Structure en 5 ACTES, en HTML (300-500 mots).

   ACTE 1 — ACCROCHE CHOC (1er paragraphe, 1-2 phrases) :
   Punchline immediate. Le lecteur doit sourire des la premiere ligne.
   PAS de contexte factuel ennuyeux. PAS de "Selon nos informations exclusives".
   Attaque directe, percutante, drole.

   ACTE 2 — LE FAIT REEL (2e paragraphe, 1-2 phrases) :
   Ancrage factuel. On comprend de quoi il s'agit vraiment. Sobre mais avec un angle deja legerement absurde.

   ACTE 3 — L'ESCALADE (3e-4e paragraphes, 2-3 paragraphes) :
   Montee en absurdite PROGRESSIVE. Chaque paragraphe pousse le curseur plus loin :
   - Reactions institutionnelles delirantes
   - Expert(s) fictif(s) cite(s) avec des NOMS EVOCATEURS (pas Jean-Marc ni Sandrine — invente des noms dont le patronyme evoque leur specialite comique)
   - Consequences en chaine de plus en plus absurdes
   - AU MOINS 1 faux chiffre/sondage avec un pourcentage ORIGINAL (pas 47,3% ni 73%)
   - 1 balise <blockquote class="pull-quote"> pour la citation la plus drole

   ACTE 4 — LA PAROLE POPULAIRE (1 paragraphe) :
   Micro-trottoir fictif. Des profils VARIES et FRAIS :
   - PAS "Jean-Marc, retraite du Var" — invente des profils specifiques et droles
   - Varie les ages, metiers, regions, situations
   - 2-3 reactions courtes et tranchees entre guillemets

   ACTE 5 — LA CHUTE (dernier paragraphe, 1-2 phrases) :
   Twist final. Retournement. Punchline de conclusion.
   La derniere phrase doit etre LA PLUS DROLE de l'article.
   Ouverture encore plus absurde, ou information qui remet tout en perspective de maniere hilarante.

   Format HTML : <p> et <blockquote class="pull-quote">
   Chaque phrase doit etre ANCREE dans le sujet specifique, pas de generalites.
   Le lecteur doit RIRE. Si c'est pas drole, c'est rate.

4. EXTRAIT : 1 phrase drole et autonome qui donne envie de lire. Maximum 160 caracteres.

5. IMAGE : Description EN ANGLAIS d'une scene BANALE mais traitee comme une photo de presse dramatique.
   REGLES STRICTES pour l'image :
   - PAS de texte, PAS de mots, PAS de lettres, PAS de pancartes lisibles
   - PAS de celebrites, PAS de personnalites connues, PAS de visages reconnaissables — uniquement des anonymes
   - Si l'article parle d'une personnalite, l'image montre la SITUATION ou la REACTION des gens, jamais la celebrite
   - Style : photo de presse candide, naturelle, comme prise sur le vif par un reporter
   - PAS de cartoon, PAS de dessin, PAS d'illustration

FORMAT JSON strict :
{
    "title": "Titre satirique drole (max 80 chars, style AFP/tabloid)",
    "category": "slug-de-categorie",
    "excerpt": "Phrase d'accroche drole (max 160 chars)",
    "content": "<p>ACTE 1 : Accroche choc...</p><p>ACTE 2 : Le fait reel...</p><p>ACTE 3 : L'escalade...</p><blockquote class=\"pull-quote\">Citation inventee geniale</blockquote><p>ACTE 3 suite...</p><p>ACTE 4 : Micro-trottoir...</p><p>ACTE 5 : La chute.</p>",
    "image_prompt": "Candid press photography of [anonymous people in absurd everyday situation], natural lighting, DSLR quality, no text, no words, no celebrities, no famous faces, no illustration"
}
PROMPT;
    }

    private function callOpenRouter(string $systemPrompt, string $userPrompt): ?string {
        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => 0.92,
            'max_tokens' => 2500,
            'top_p' => 0.95,
            'frequency_penalty' => 0.5
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'HTTP-Referer: https://toutvamal.fr',
                'X-Title: ToutVaMal Article Generator'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 90
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            log_error("OpenRouter API error: HTTP $httpCode - $response");
            return null;
        }

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    private function parseResponse(string $response, array $rssItem, array $journalist): ?array {
        // Extraire le JSON de la reponse
        preg_match('/\{[\s\S]*\}/', $response, $matches);
        if (empty($matches)) {
            log_error("Failed to parse JSON from response");
            return null;
        }

        $data = json_decode($matches[0], true);
        if (!$data || empty($data['title'])) {
            log_error("Invalid JSON structure in response");
            return null;
        }

        // L'IA a juge le sujet trop grave/serieux → skip
        if ($data['title'] === 'SKIP') {
            return null;
        }

        if (!isset($data['category'], $data['content'])) {
            log_error("Missing category or content in response");
            return null;
        }

        // Valider la categorie
        if (!isset(CATEGORIES[$data['category']])) {
            $data['category'] = array_key_first(CATEGORIES);
        }

        // Nettoyer le titre (retirer les guillemets si le modele en met)
        $title = trim($data['title'], '"\'');

        return [
            'title' => $title,
            'slug' => slugify($title),
            'category' => $data['category'],
            'excerpt' => $data['excerpt'] ?? substr(strip_tags($data['content']), 0, 200),
            'content' => $data['content'],
            'image_prompt' => $data['image_prompt'] ?? '',
            'journalist_id' => $journalist['id'],
            'source_title' => $rssItem['title'],
            'source_url' => $rssItem['link'] ?? ''
        ];
    }

    /**
     * Retourne un template de titre aleatoire.
     * Utile en fallback si la generation echoue.
     */
    public static function getRandomTitleTemplate(): string {
        return self::TITLE_TEMPLATES[array_rand(self::TITLE_TEMPLATES)];
    }

    /**
     * Retourne une accroche dramatique aleatoire.
     */
    public static function getRandomDramaticHook(): string {
        return self::DRAMATIC_HOOKS[array_rand(self::DRAMATIC_HOOKS)];
    }
}
