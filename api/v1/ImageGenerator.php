<?php
/**
 * ToutVaMal.fr - Image Generator
 * Uses Replicate API with google/gemini-3-pro-image
 */

class ImageGenerator {
    private string $apiKey;
    private string $model = 'google/gemini-3-pro-image';

    public function __construct() {
        $this->apiKey = REPLICATE_API_KEY;
    }

    /**
     * Generate image from prompt
     */
    public function generateImage(string $prompt, string $articleSlug): ?string {
        // Style adjustments for news photography
        $enhancedPrompt = "Professional photojournalism style, dramatic lighting, realistic, high quality news photograph: " . $prompt;

        // Start prediction
        $prediction = $this->startPrediction($enhancedPrompt);
        if (!$prediction) {
            return null;
        }

        // Poll for completion
        $result = $this->waitForResult($prediction['id']);
        if (!$result) {
            return null;
        }

        // Download and save image
        $imageUrl = is_array($result) ? $result[0] : $result;
        return $this->saveImage($imageUrl, $articleSlug);
    }

    private function startPrediction(string $prompt): ?array {
        $ch = curl_init('https://api.replicate.com/v1/predictions');

        $payload = [
            'version' => 'latest',
            'model' => $this->model,
            'input' => [
                'prompt' => $prompt,
                'width' => 1280,
                'height' => 720,
                'output_format' => 'webp'
            ]
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'Prefer: wait'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 120
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201 && $httpCode !== 200) {
            log_error("Replicate API error: HTTP $httpCode - $response");
            return null;
        }

        return json_decode($response, true);
    }

    private function waitForResult(string $predictionId): ?string {
        $maxAttempts = 60;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $ch = curl_init("https://api.replicate.com/v1/predictions/$predictionId");

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->apiKey
                ]
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);

            if ($data['status'] === 'succeeded') {
                return $data['output'];
            }

            if ($data['status'] === 'failed' || $data['status'] === 'canceled') {
                log_error("Replicate prediction failed: " . ($data['error'] ?? 'Unknown error'));
                return null;
            }

            sleep(2);
            $attempt++;
        }

        log_error("Replicate prediction timeout");
        return null;
    }

    private function saveImage(string $imageUrl, string $articleSlug): ?string {
        $imageData = file_get_contents($imageUrl);
        if (!$imageData) {
            log_error("Failed to download image from $imageUrl");
            return null;
        }

        // Create directory if needed
        $dir = IMAGES_PATH . '/articles';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Save file
        $filename = $articleSlug . '-' . time() . '.webp';
        $filepath = $dir . '/' . $filename;

        if (file_put_contents($filepath, $imageData)) {
            log_info("Image saved: $filepath");
            return '/images/articles/' . $filename;
        }

        log_error("Failed to save image to $filepath");
        return null;
    }
}
