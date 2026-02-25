<?php
/**
 * ToutVaMal.fr - Content Generator
 * Uses GPT-5.2 via OpenRouter
 *
 * Le journal qui transforme les petites nouvelles en catastrophes nationales.
 * Style Gorafi / CNews sous anxiolytiques.
 */

class ContentGenerator {
    private string $apiKey;
    private string $model;

    /**
     * Templates de titres absurdes utilisables en fallback ou en inspiration.
     * Le modèle peut s'en inspirer pour générer des titres percutants.
     */
    public const TITLE_TEMPLATES = [
        // Structure: [Sujet] : la France [catastrophe inattendue]
        "{sujet} : la France au bord de l'effondrement",
        "{sujet} : la France ferme ses frontières en urgence",
        "{sujet} : la France se classe dernière au classement mondial",
        "{sujet} : la France envisage de se déclarer en faillite",
        "{sujet} : la France menace de quitter l'Europe (encore)",
        "{sujet} : la France suspend la Constitution par précaution",

        // Structure: Un [personne] [action absurde] pour [raison décalée]
        "Un retraité fait annuler {événement} pour nuisance sonore",
        "Un élu local propose d'interdire {sujet} pour sauver l'identité française",
        "Un maire interdit {sujet} après un sondage réalisé auprès de sa belle-mère",
        "Un Français moyen provoque une crise diplomatique en {action}",
        "Un fonctionnaire en burn-out bloque accidentellement {institution}",
        "Un expert autoproclamé sur BFM déclenche une panique nationale",

        // Structure: [Institution] lance [mesure absurde] face à [faux problème]
        "Le gouvernement lance un plan Marshall contre {sujet}",
        "L'Assemblée nationale vote en urgence une loi sur {sujet}",
        "Bercy annonce un impôt spécial pour financer {absurdité}",
        "L'ONU convoque une session extraordinaire après {événement}",
        "L'Élysée crée une commission d'enquête sur {sujet}",
        "La Cour des comptes révèle que {sujet} coûte 3 milliards par an",

        // Structure dramatique CNews
        "Insécurité : {sujet}, le symptôme d'un pays en déroute",
        "Immigration : {sujet} relance le débat sur l'identité nationale",
        "Écologie : {sujet}, ou comment la France rate encore le coche",
        "Pouvoir d'achat : {sujet}, la goutte d'eau qui fait déborder le caddie",
        "Éducation : {sujet} confirme la faillite du système",
        "Sondage exclusif : 73% des Français estiment que {sujet} est la fin de tout",
        "C'était mieux avant : {sujet} donne raison aux nostalgiques",
        "{sujet} : les experts s'accordent à dire que c'est foutu",

        // Absurde pur style Gorafi
        "La France ferme ses frontières après une pénurie de croissants",
        "Un Français sur deux envisage de déménager à cause de {sujet}",
        "Le dernier Français optimiste a été retrouvé mort ce matin",
        "Un village entier se déclare en sécession après {événement}",
        "{sujet} : le mot « espoir » officiellement retiré du dictionnaire",
        "Météo France annonce un risque d'effondrement civilisationnel pour jeudi",
    ];

    /**
     * Phrases d'accroche dramatiques utilisables dans les articles.
     * Tics de langage d'éditorialistes français.
     */
    public const DRAMATIC_HOOKS = [
        "Dans le monde d'aujourd'hui, est-il encore possible de {action} ?",
        "La France est-elle encore capable de {action} ?",
        "Dans un pays qui se respecte, jamais on n'aurait toléré {sujet}.",
        "Nos ancêtres se retournent dans leur tombe.",
        "Les experts s'accordent à dire que c'est sans précédent.",
        "Selon un sondage que nous venons d'inventer, {statistique}.",
        "Ce que nos élites ne veulent pas que vous sachiez sur {sujet}.",
        "Et si c'était le signe que tout s'effondre ?",
        "Un symptôme de plus du déclin français.",
        "La question que personne n'ose poser : {question} ?",
    ];

    public function __construct() {
        $this->apiKey = OPENROUTER_API_KEY;
        $this->model = OPENROUTER_MODEL;
    }

    /**
     * Generate article from RSS item
     */
    public function generateArticle(array $rssItem, array $journalist): ?array {
        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($rssItem, $journalist);

        $response = $this->callOpenRouter($systemPrompt, $userPrompt);
        if (!$response) {
            return null;
        }

        return $this->parseResponse($response, $rssItem, $journalist);
    }

    /**
     * Alias pour compatibilité v2
     */
    public function generate(array $rssItem, array $journalist): ?array {
        return $this->generateArticle($rssItem, $journalist);
    }

    /**
     * System prompt : définit la personnalité et le style du générateur.
     * C'est le coeur du ton ToutVaMal.fr.
     */
    private function buildSystemPrompt(): string {
        // Sélectionner quelques templates de titres au hasard pour inspirer le modèle
        $shuffledTitles = self::TITLE_TEMPLATES;
        shuffle($shuffledTitles);
        $exampleTitles = array_slice($shuffledTitles, 0, 5);
        $titlesStr = implode("\n- ", $exampleTitles);

        // Sélectionner quelques accroches dramatiques
        $shuffledHooks = self::DRAMATIC_HOOKS;
        shuffle($shuffledHooks);
        $exampleHooks = array_slice($shuffledHooks, 0, 4);
        $hooksStr = implode("\n- ", $exampleHooks);

        return <<<SYSTEM
Tu es le rédacteur en chef satirique de ToutVaMal.fr, le journal qui transforme les petites nouvelles en catastrophes nationales. Tu es le Gorafi sous stéroïdes, le CNews des univers parallèles.

=== TON IDENTITÉ ===
Tu n'es PAS un journaliste avec un nom et un style personnel. Tu es la VOIX de ToutVaMal.fr : un éditorialiste omniscient, dramatique, et profondément convaincu que tout va mal, que tout a toujours été mieux avant, et que demain sera inévitablement pire.

=== TON STYLE ===
- Tu DRAMATISES l'insignifiant comme si c'était la fin du monde. Un embouteillage devient "l'effondrement du réseau routier français". Une rupture de stock de moutarde devient "une crise alimentaire sans précédent".
- Tu utilises les TICS DE LANGAGE des JT et éditorialistes français :
  "La France est-elle encore capable de...", "Dans un pays qui se respecte...", "Les experts s'accordent à dire que...", "Selon nos informations exclusives...", "Ce que le gouvernement ne vous dit pas..."
- Chaque fait divers doit devenir un SYMPTÔME DU DÉCLIN FRANÇAIS
- Le ton est celui d'un éditorialiste de CNews qui a pris trop d'anxiolytiques et pas assez de recul
- Tu SAIS que tout va mal. Tu en es CERTAIN. Les chiffres le prouvent (tu les inventes).
- Références fréquentes à : l'effondrement de la France, la crise permanente, "les valeurs qui se perdent", "nos ancêtres", "la France d'avant", le déclin de l'Occident
- Tu adores les THÈMES RÉCURRENTS : immigration, crise économique, écologie catastrophiste, nostalgie du passé, insécurité, perte des valeurs, le "bon sens populaire"

=== CE QUE TU NE FAIS JAMAIS ===
- JAMAIS de moralisation. JAMAIS sérieux. 100% second degré.
- JAMAIS de mention du journaliste/auteur dans le texte de l'article. Le texte est anonyme, c'est la voix du journal.
- JAMAIS de "en tant que journaliste de ToutVaMal" ou de référence à toi-même
- JAMAIS de contenu réellement offensant ou discriminatoire. C'est de la satire intelligente, pas de la haine.

=== EXEMPLES DE TITRES QUI MARCHENT ===
- {$titlesStr}

=== ACCROCHES DRAMATIQUES UTILISABLES ===
- {$hooksStr}

=== TECHNIQUES HUMORISTIQUES À UTILISER ===
1. L'ESCALADE ABSURDE : partir d'un fait réel et dériver vers le n'importe quoi
2. LES FAUX CHIFFRES : "Selon un sondage, 73% des Français pensent que...", "Une étude de l'INSEE révèle que..."
3. LES FAUSSES CITATIONS : inventer des déclarations entre guillemets attribuées à des profils crédibles ("Jean-Marc, retraité du Var", "Sandrine, experte en rien du tout sur CNews", "Un haut fonctionnaire sous couvert d'anonymat")
4. LE DÉCALAGE : traiter un sujet futile avec une gravité de crise nucléaire
5. LA NOSTALGIE TOXIQUE : comparer systématiquement avec un passé fantasmé
6. LE CATASTROPHISME JOYEUX : annoncer la fin du monde avec enthousiasme

=== FORMAT JSON DE SORTIE ===
Réponds TOUJOURS en JSON valide, rien d'autre. Pas de texte avant ou après le JSON.
SYSTEM;
    }

    /**
     * User prompt : contient l'actu source et les consignes de format.
     */
    private function buildUserPrompt(array $rssItem, array $journalist): string {
        $categories = implode(', ', array_keys(CATEGORIES));
        $description = $rssItem['description'] ?? 'Pas de description disponible.';

        return <<<PROMPT
ACTUALITÉ À TRANSFORMER EN ARTICLE SATIRIQUE :

Titre original : {$rssItem['title']}
Description : {$description}

CONSIGNES DE RÉDACTION :
1. TITRE : Court, percutant, ABSURDE. Style Gorafi. Doit donner envie de cliquer. Maximum 100 caractères. Pas de guillemets dans le titre. Le titre doit fonctionner seul, sans contexte.

2. CATÉGORIE : Choisis la plus appropriée parmi : {$categories}

3. CONTENU : 4 à 6 paragraphes en HTML.
   - Le 1er paragraphe ANCRE dans le réel (reformule l'actu de façon dramatique)
   - Les paragraphes suivants DÉRIVENT vers l'absurde progressivement
   - Inclus AU MOINS 2 citations inventées entre guillemets (attribuées à des personnages fictifs crédibles et drôles)
   - Inclus AU MOINS 1 chiffre absurde ("selon un sondage...", "d'après une étude...")
   - Utilise une balise <blockquote class="pull-quote"> pour la citation la plus percutante
   - Le dernier paragraphe doit être une chute, un twist, ou une ouverture encore plus catastrophiste
   - NE MENTIONNE PAS le nom du journaliste dans l'article
   - Format HTML : <p> pour les paragraphes, <blockquote class="pull-quote"> pour les citations en exergue
   - Vise 300-500 mots

4. EXTRAIT : 1 seule phrase percutante qui donne ENVIE de lire l'article. Doit être drôle et autonome.

5. IMAGE : Description EN ANGLAIS pour générer une PHOTO DE PRESSE hyper-réaliste. Style photojournalisme AFP/Reuters. L'image doit ressembler à une VRAIE photo d'agence de presse, sérieuse et dramatique, mais montrant une situation décalée ou absurde. L'humour vient du CONTRASTE entre le sérieux photographique et le sujet comique. PAS de cartoon, PAS de dessin, PAS de style IA visible. Pas de texte dans l'image.

FORMAT DE RÉPONSE (JSON strict, rien d'autre) :
{
    "title": "Titre satirique absurde et accrocheur",
    "category": "slug-de-categorie",
    "excerpt": "Une phrase d'accroche percutante et drôle",
    "content": "<p>Premier paragraphe dramatique ancré dans le réel...</p><p>Deuxième paragraphe où ça commence à déraper...</p><blockquote class=\"pull-quote\">Citation inventée absolument géniale</blockquote><p>Suite de la dérive absurde avec faux chiffres...</p><p>Chute hilarante ou ouverture catastrophiste.</p>",
    "image_prompt": "Photojournalism, Reuters/AFP press photo, [description of a realistic scene that contrasts serious tone with absurd subject], natural lighting, candid shot, DSLR quality, editorial news photography, no illustration, no cartoon, hyperrealistic"
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
            'frequency_penalty' => 0.3
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
        // Extraire le JSON de la réponse
        preg_match('/\{[\s\S]*\}/', $response, $matches);
        if (empty($matches)) {
            log_error("Failed to parse JSON from response");
            return null;
        }

        $data = json_decode($matches[0], true);
        if (!$data || !isset($data['title'], $data['category'], $data['content'])) {
            log_error("Invalid JSON structure in response");
            return null;
        }

        // Valider la catégorie
        if (!isset(CATEGORIES[$data['category']])) {
            $data['category'] = array_key_first(CATEGORIES);
        }

        // Nettoyer le titre (retirer les guillemets si le modèle en met)
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
     * Retourne un template de titre aléatoire.
     * Utile en fallback si la génération échoue.
     */
    public static function getRandomTitleTemplate(): string {
        return self::TITLE_TEMPLATES[array_rand(self::TITLE_TEMPLATES)];
    }

    /**
     * Retourne une accroche dramatique aléatoire.
     */
    public static function getRandomDramaticHook(): string {
        return self::DRAMATIC_HOOKS[array_rand(self::DRAMATIC_HOOKS)];
    }
}
