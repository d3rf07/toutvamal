<?php
/**
 * ToutVaMal.fr - Image Generator
 * Modele principal : google/nano-banana-2 (Gemini Flash, ~25s)
 * Fallback 1      : black-forest-labs/flux-1.1-pro-ultra
 * Fallback 2      : black-forest-labs/flux-2-pro
 *
 * Format de sortie verifiee sur l'API Replicate (2026-03-20) :
 * - nano-banana-2  : output = string URI, formats = jpg|png, params = aspect_ratio + resolution (1K|2K|4K)
 * - flux-1.1-pro-ultra : output = string URI, formats = webp|jpg|png, params = aspect_ratio + output_format + output_quality + raw
 * - flux-2-pro         : output = array[string URI], formats = webp|jpg|png, params = aspect_ratio + output_format + output_quality
 */

class ImageGenerator {
    private string $apiKey;
    private string $primaryModel;

    // Ordre de priorite des modeles (fallback en cascade)
    private array $modelChain = [
        'google/nano-banana-2',
        'black-forest-labs/flux-1.1-pro-ultra',
        'black-forest-labs/flux-2-pro',
    ];

    // Pools de variation pour prompts photojournalisme
    private array $agencies  = ['Reuters', 'AFP', 'Getty Images', 'Associated Press', 'EPA'];
    private array $framings  = ['wide angle shot', 'medium close-up', 'over-the-shoulder shot', 'low angle shot', 'aerial view', 'eye-level shot', 'dutch angle'];
    private array $lightings = ['natural daylight', 'golden hour', 'overcast sky', 'dramatic side lighting', 'flash photography', 'soft studio light'];

    private string $negativePrompt = 'text, letters, words, caption, watermark, logo, signature, cartoon, illustration, anime, painting, drawing, CGI, 3D render, blurry, low quality';

    public function __construct() {
        $this->apiKey      = REPLICATE_API_KEY;
        $this->primaryModel = defined('REPLICATE_MODEL') ? REPLICATE_MODEL : 'google/nano-banana-2';

        // Reorder chain so the configured primary model is first
        if ($this->primaryModel !== $this->modelChain[0]) {
            $this->modelChain = array_merge(
                [$this->primaryModel],
                array_filter($this->modelChain, fn($m) => $m !== $this->primaryModel)
            );
        }
    }

    /**
     * Generate image from prompt — tente les modeles dans l'ordre avec fallback
     */
    public function generateImage(string $prompt, string $articleSlug): ?string {
        $enhancedPrompt = $this->buildPrompt($prompt);

        foreach ($this->modelChain as $model) {
            log_info("ImageGenerator: essai modele $model");
            $prediction = $this->startPrediction($enhancedPrompt, $model);
            if (!$prediction) {
                log_error("ImageGenerator: echec demarrage prediction pour $model, passage au suivant");
                continue;
            }

            $result = $this->waitForResult($prediction['id']);
            if (!$result) {
                log_error("ImageGenerator: prediction echouee pour $model, passage au suivant");
                continue;
            }

            // Normaliser la sortie : nano-banana-2 retourne string, flux retourne array
            $imageUrl = is_array($result) ? $result[0] : $result;
            if (empty($imageUrl)) {
                log_error("ImageGenerator: URL image vide pour $model, passage au suivant");
                continue;
            }

            log_info("ImageGenerator: image generee avec $model");
            return $this->saveImage($imageUrl, $articleSlug, $model);
        }

        log_error("ImageGenerator: tous les modeles ont echoue pour le slug $articleSlug");
        return null;
    }

    /**
     * Construit un prompt photojournalisme varie avec pools de randomisation
     */
    private function buildPrompt(string $basePrompt): string {
        $agency   = $this->agencies[array_rand($this->agencies)];
        $framing  = $this->framings[array_rand($this->framings)];
        $lighting = $this->lightings[array_rand($this->lightings)];

        return "A professional press photograph by {$agency}. {$basePrompt}. {$framing}, {$lighting}. "
             . "Photojournalism style, documentary photography. "
             . "Anonymous people only, no recognizable celebrities, no famous faces. "
             . "No text, no watermark, no overlay, no caption. "
             . "Negative: {$this->negativePrompt}.";
    }

    /**
     * Lance une prediction Replicate avec les bons parametres selon le modele
     */
    private function startPrediction(string $prompt, string $model): ?array {
        $url = 'https://api.replicate.com/v1/models/' . $model . '/predictions';

        $input = $this->buildModelInput($prompt, $model);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'Prefer: wait',
            ],
            CURLOPT_POSTFIELDS => json_encode(['input' => $input]),
            CURLOPT_TIMEOUT    => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201 && $httpCode !== 200) {
            log_error("Replicate API error ($model): HTTP $httpCode — $response");
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Construit les parametres d'input selon le modele cible
     * Format verifie le 2026-03-20 via l'API Replicate
     */
    private function buildModelInput(string $prompt, string $model): array {
        switch ($model) {
            case 'google/nano-banana-2':
                // output_format : jpg | png (pas de webp)
                // resolution    : 1K | 2K | 4K
                // aspect_ratio  : 16:9 supporte
                return [
                    'prompt'        => $prompt,
                    'aspect_ratio'  => '16:9',
                    'resolution'    => '2K',
                    'output_format' => 'jpg',
                ];

            case 'black-forest-labs/flux-1.1-pro-ultra':
                // output retourne string URI
                return [
                    'prompt'         => $prompt,
                    'aspect_ratio'   => '16:9',
                    'output_format'  => 'webp',
                    'output_quality' => 90,
                    'raw'            => false,
                ];

            case 'black-forest-labs/flux-2-pro':
            default:
                // output retourne array[string URI]
                return [
                    'prompt'         => $prompt,
                    'aspect_ratio'   => '16:9',
                    'output_format'  => 'webp',
                    'output_quality' => 90,
                ];
        }
    }

    /**
     * Poll Replicate jusqu'a succes ou echec
     */
    private function waitForResult(string $predictionId): mixed {
        $maxAttempts = 60;
        $attempt     = 0;

        while ($attempt < $maxAttempts) {
            $ch = curl_init("https://api.replicate.com/v1/predictions/$predictionId");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $this->apiKey,
                ],
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);

            if (($data['status'] ?? '') === 'succeeded') {
                return $data['output'];
            }

            if (in_array($data['status'] ?? '', ['failed', 'canceled'], true)) {
                log_error("Replicate prediction {$predictionId} echouee: " . ($data['error'] ?? 'Unknown error'));
                return null;
            }

            sleep(2);
            $attempt++;
        }

        log_error("Replicate prediction {$predictionId} timeout apres " . ($maxAttempts * 2) . "s");
        return null;
    }

    /**
     * Telecharge et sauvegarde l'image localement
     * L'extension du fichier est determinee selon le modele utilise
     */
    private function saveImage(string $imageUrl, string $articleSlug, string $model): ?string {
        $imageData = file_get_contents($imageUrl);
        if (!$imageData) {
            log_error("ImageGenerator: echec telechargement depuis $imageUrl");
            return null;
        }

        // Extension selon le format reel du modele
        $ext = ($model === 'google/nano-banana-2') ? 'jpg' : 'webp';

        $dir = IMAGES_PATH . '/articles';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $articleSlug . '-' . time() . '.' . $ext;
        $filepath = $dir . '/' . $filename;

        if (file_put_contents($filepath, $imageData)) {
            log_info("ImageGenerator: image sauvegardee $filepath (modele: $model)");
            return '/images/articles/' . $filename;
        }

        log_error("ImageGenerator: echec sauvegarde $filepath");
        return null;
    }
}
