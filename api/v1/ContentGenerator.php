<?php
/**
 * ToutVaMal.fr - Content Generator
 * Uses GPT-5.2 via OpenRouter
 */

class ContentGenerator {
    private string $apiKey;
    private string $model;

    public function __construct() {
        $this->apiKey = OPENROUTER_API_KEY;
        $this->model = OPENROUTER_MODEL;
    }

    /**
     * Generate article from RSS item
     */
    public function generateArticle(array $rssItem, array $journalist): ?array {
        $prompt = $this->buildPrompt($rssItem, $journalist);

        $response = $this->callOpenRouter($prompt);
        if (!$response) {
            return null;
        }

        return $this->parseResponse($response, $rssItem, $journalist);
    }

    private function buildPrompt(array $rssItem, array $journalist): string {
        $categories = implode(', ', array_values(CATEGORIES));

        return <<<PROMPT
Tu es {$journalist['name']}, {$journalist['role']} pour ToutVaMal.fr, un site satirique français.

Style d'écriture : {$journalist['style']}

Ta mission : transformer cette actualité en article satirique pessimiste.

ACTUALITÉ SOURCE :
Titre : {$rssItem['title']}
Description : {$rssItem['description']}

CONSIGNES :
1. Réécris un titre accrocheur et pessimiste (max 80 caractères)
2. Choisis la catégorie la plus appropriée parmi : {$categories}
3. Rédige un article de 300-500 mots avec ton style personnel
4. Inclus une citation mise en avant (pull-quote) entre les paragraphes
5. Le ton doit être satirique mais intelligent, jamais vulgaire

FORMAT DE RÉPONSE (JSON strict) :
{
  "title": "Le titre pessimiste",
  "category": "slug-de-categorie",
  "excerpt": "Résumé de 2 phrases maximum",
  "content": "<p>Premier paragraphe avec drop cap.</p><blockquote class=\"pull-quote\">Citation mise en avant</blockquote><p>Suite de l'article...</p>",
  "image_prompt": "Description en anglais pour générer une image illustrant l'article, style photojournalisme dramatique"
}
PROMPT;
    }

    private function callOpenRouter(string $prompt): ?string {
        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.8,
            'max_tokens' => 2000
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
            CURLOPT_TIMEOUT => 60
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

        return [
            'title' => $data['title'],
            'slug' => slugify($data['title']),
            'category' => $data['category'],
            'excerpt' => $data['excerpt'] ?? substr(strip_tags($data['content']), 0, 200),
            'content' => $data['content'],
            'image_prompt' => $data['image_prompt'] ?? '',
            'journalist_id' => $journalist['id'],
            'source_title' => $rssItem['title'],
            'source_url' => $rssItem['link'] ?? ''
        ];
    }
}
