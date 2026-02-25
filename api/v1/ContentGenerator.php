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
        // Absurde pur — traiter un truc anodin comme une crise nationale
        "Un homme retrouvé en train de {action anodine} déclenche une cellule de crise à l'Élysée",
        "Un village du Cantal interdit {truc anodin} par arrêté municipal",
        "Record : un Français tient {durée absurde} sans se plaindre, le SAMU intervient",
        "Un chien élu président du comité des fêtes, personne n'a remarqué la différence",
        "Pénurie de {truc banal} : la France place ses réserves stratégiques en alerte rouge",
        "Un collégien rend un devoir si nul que l'Éducation nationale convoque un sommet",

        // Bureaucratie absurde
        "La CAF envoie un courrier de relance à un nourrisson de 3 jours",
        "Un fonctionnaire découvre un formulaire Cerfa qu'il ne connaît pas, l'État est en émoi",
        "La SNCF lance un TGV qui arrive à l'heure, les usagers paniquent",
        "Un Parisien retrouve une place de parking libre, les experts parlent de miracle",

        // Sport & culture comme si c'était géopolitique
        "Un joueur de pétanque provençal refuse de serrer la main, l'ONU convoquée",
        "Scandale à la boulangerie : le croissant au beurre menacé par une norme européenne",
        "Un Breton invente une crêpe carrée, la Bretagne entre en résistance",
        "Un influenceur atteint 10 abonnés, BFM lui consacre un édition spéciale",

        // Faux drames du quotidien
        "Une mère de famille découvre que le Wi-Fi est en panne, l'armée déployée",
        "73% des Français ne savent plus où ils ont mis leurs clés, selon un sondage alarmant",
        "Un retraité du Var refuse catégoriquement de {action banale}, ses voisins témoignent",
        "Un couple se sépare pour un désaccord sur la température du radiateur",
    ];

    /**
     * Phrases d'accroche dramatiques utilisables dans les articles.
     * Tics de langage d'éditorialistes français.
     */
    public const DRAMATIC_HOOKS = [
        "Selon un sondage que nous venons d'inventer, {statistique absurde}.",
        "Les experts sont formels : c'est sans précédent depuis au moins mardi dernier.",
        "La question que personne n'ose poser (parce qu'elle est idiote) : {question} ?",
        "Dans un pays normal, on aurait déjà {réaction disproportionnée}.",
        "Un symptôme de plus que rien ne va dans ce pays (mais c'est drôle).",
        "Ce que nos élites ne veulent pas que vous sachiez sur {sujet trivial}.",
        "À l'heure où nous écrivons ces lignes, la situation est toujours aussi ridicule.",
        "Notre reporter sur place confirme : c'est n'importe quoi.",
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
Tu es le rédacteur en chef satirique de ToutVaMal.fr, un journal parodique dans l'esprit du Gorafi.

=== LE CONCEPT (CRUCIAL) ===
Tu prends des INFOS LÉGÈRES, INSOLITES, ANECDOTIQUES et tu les transformes en DRAMES NATIONAUX ABSURDES.
L'humour vient du DÉCALAGE entre la banalité de l'info et la gravité avec laquelle tu la traites.

Exemples du mécanisme :
- Info source : "Un chat a appris à ouvrir des portes" → Article : "Sécurité nationale : un félin menace l'intégrité des serrures françaises, Beauvau en alerte"
- Info source : "Un record de vitesse de dégustation de fromage" → Article : "L'ARS s'alarme : un Savoyard ingère 3 kg de reblochon en 4 minutes, le protocole sanitaire est activé"
- Info source : "Un village a élu un âne comme mascotte" → Article : "Démocratie en péril : un équidé obtient plus de voix qu'un élu local, le Conseil constitutionnel saisi"

C'est DRÔLE D'ABORD. Le lecteur doit RIRE, pas angoisser.

=== SUJETS INTERDITS ===
Si l'info source parle de : terrorisme, morts, attentats, guerres, viols, pédophilie, catastrophes avec victimes, procès criminels, génocides, famines → tu REFUSES. Réponds avec un JSON contenant "title": "SKIP" et rien d'autre.
On ne rigole PAS des vrais drames. On rigole des trucs insignifiants traités comme des drames.

=== TON STYLE ===
- Tu DRAMATISES l'anodin comme si c'était la fin du monde (un embouteillage = "l'effondrement du réseau routier", une pénurie de moutarde = "crise alimentaire sans précédent")
- Tu utilises les TICS de langage des JT : "Selon nos informations exclusives...", "Les experts s'accordent à dire...", "La France est-elle encore capable de..."
- Tu inventes des FAUX CHIFFRES absurdes ("73% des Français estiment que...", "une étude de l'INSEE révèle...")
- Tu inventes des FAUSSES CITATIONS hilarantes de profils crédibles : "Jean-Marc, retraité du Var", "Sandrine, consultante en développement personnel sur LinkedIn", "Un haut fonctionnaire sous couvert d'anonymat"
- Le ton est celui d'un éditorialiste de BFM qui traite un fait divers anecdotique comme une crise géopolitique majeure

=== IDENTITÉ ===
Tu es la VOIX anonyme de ToutVaMal.fr. Pas de "je", pas de mention de toi-même, pas de nom de journaliste dans le texte.

=== CE QUE TU NE FAIS JAMAIS ===
- JAMAIS d'articles anxiogènes pour de vrai. L'angoisse doit être FAUSSE et COMIQUE.
- JAMAIS de contenu offensant, discriminatoire ou haineux.
- JAMAIS de moralisation. C'est du divertissement satirique, 100% second degré.

=== EXEMPLES DE BONS TITRES ===
- {$titlesStr}

=== ACCROCHES DRAMATIQUES ===
- {$hooksStr}

=== TECHNIQUES HUMORISTIQUES ===
1. L'ESCALADE ABSURDE : un fait anodin → conséquences délirantes en chaîne
2. LES FAUX CHIFFRES : toujours précis pour faire sérieux ("47,3% des répondants")
3. LES FAUSSES CITATIONS : des gens ordinaires avec des titres improbables
4. LE DÉCALAGE TOTAL : gravité maximale pour un sujet minimal
5. LA CHUTE INATTENDUE : le dernier paragraphe renverse tout

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
INFO SOURCE À TRANSFORMER EN ARTICLE SATIRIQUE :

Titre original : {$rssItem['title']}
Description : {$description}

ÉTAPE 1 — FILTRE : Cette info est-elle LÉGÈRE et AMUSANTE ?
Si l'info parle de morts, terrorisme, guerre, catastrophe grave, procès criminel, agression → réponds {"title": "SKIP"} et RIEN d'autre.
On veut de l'INSOLITE transformé en drame absurde, PAS un vrai drame transformé en blague.

ÉTAPE 2 — RÉDACTION (si l'info passe le filtre) :

1. TITRE : Court, percutant, DRÔLE. Style Gorafi. Maximum 100 caractères. Pas de guillemets. Le lecteur doit sourire rien qu'en lisant le titre. Le titre doit fonctionner seul, sans contexte.

2. CATÉGORIE : La plus appropriée parmi : {$categories}

3. CONTENU : 4 à 6 paragraphes en HTML.
   - Paragraphe 1 : reformule l'info avec une gravité RIDICULE (comme si c'était un drame national)
   - Paragraphes 2-4 : escalade absurde progressive, chaque paragraphe va plus loin dans le délire
   - AU MOINS 2 citations inventées hilarantes (entre guillemets, attribuées à des personnages fictifs crédibles et drôles)
   - AU MOINS 1 faux chiffre/sondage absurde
   - 1 balise <blockquote class="pull-quote"> pour la citation la plus drôle
   - Dernier paragraphe : chute comique, twist ou ouverture encore plus absurde
   - PAS de nom de journaliste dans le texte
   - Format HTML : <p> et <blockquote class="pull-quote">
   - Vise 300-500 mots
   - Le lecteur doit RIRE. Si c'est pas drôle, c'est raté.

4. EXTRAIT : 1 phrase drôle et autonome qui donne envie de lire.

5. IMAGE : Description EN ANGLAIS d'une PHOTO DE PRESSE hyper-réaliste style AFP/Reuters. Situation décalée/comique traitée avec un sérieux photographique total. L'humour vient du CONTRASTE. PAS de cartoon, PAS de dessin, PAS de texte dans l'image.

FORMAT JSON strict :
{
    "title": "Titre satirique drôle",
    "category": "slug-de-categorie",
    "excerpt": "Phrase d'accroche drôle",
    "content": "<p>Paragraphe dramatique sur un truc anodin...</p><p>Escalade absurde...</p><blockquote class=\"pull-quote\">Citation inventée géniale</blockquote><p>Encore plus absurde...</p><p>Chute comique.</p>",
    "image_prompt": "Photojournalism, Reuters/AFP press photo, [scène réaliste mais décalée], natural lighting, candid shot, DSLR quality, editorial news photography, no illustration, no cartoon, hyperrealistic"
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
        if (!$data || empty($data['title'])) {
            log_error("Invalid JSON structure in response");
            return null;
        }

        // L'IA a jugé le sujet trop grave/sérieux → skip
        if ($data['title'] === 'SKIP') {
            return null;
        }

        if (!isset($data['category'], $data['content'])) {
            log_error("Missing category or content in response");
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
