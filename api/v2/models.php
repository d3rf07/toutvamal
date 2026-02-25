<?php
/**
 * ToutVaMal.fr - Models API v2
 * Informations sur les modèles IA utilisés
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

class ModelsAPI extends APIEndpoint {

    protected function get(): void {
        $type = $this->param('type', 'all');

        $models = [
            'text' => [
                'id' => OPENROUTER_MODEL,
                'name' => 'GPT-5.2',
                'provider' => 'OpenRouter',
                'type' => 'text',
                'description' => 'Génération de contenu satirique',
                'config' => [
                    'temperature' => 0.92,
                    'max_tokens' => 2500,
                    'frequency_penalty' => 0.3,
                    'top_p' => 0.95
                ]
            ],
            'image' => [
                'id' => REPLICATE_MODEL,
                'name' => 'Gemini 3 Pro Image',
                'provider' => 'Replicate',
                'type' => 'image',
                'description' => 'Génération d\'images photojournalistiques',
                'config' => [
                    'width' => 1280,
                    'height' => 720,
                    'output_format' => 'webp',
                    'style' => 'photojournalism'
                ]
            ]
        ];

        if ($type === 'text') {
            $this->success(['models' => [$models['text']]]);
        } elseif ($type === 'image') {
            $this->success(['models' => [$models['image']]]);
        } else {
            $this->success(['models' => array_values($models)]);
        }
    }
}

// Exécution
$api = new ModelsAPI();
$api->handle();
