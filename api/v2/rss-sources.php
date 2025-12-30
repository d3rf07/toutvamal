<?php
/**
 * ToutVaMal.fr - RSS Sources API v2
 * Gestion des sources RSS
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

class RssSourcesAPI extends APIEndpoint {

    protected function get(): void {
        $id = $this->paramInt('id');

        if ($id) {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT * FROM rss_sources WHERE id = ?");
            $stmt->execute([$id]);
            $source = $stmt->fetch();

            if (!$source) {
                $this->error('RSS source not found', 404);
            }
            $this->success($source);
        }

        $activeOnly = $this->param('active') === '1';
        $sources = Database::getRssSources($activeOnly);

        $this->success([
            'sources' => $sources,
            'total' => count($sources)
        ]);
    }

    protected function post(): void {
        $action = $this->param('action');

        if ($action === 'test') {
            $this->testSource();
        }

        if ($action === 'fetch') {
            $this->fetchAllSources();
        }

        // Création nouvelle source
        $data = $this->getJsonBody();
        $this->validateRequired($data, ['name', 'url']);

        // Valider l'URL
        if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
            $this->error('Invalid URL format', 400);
        }

        try {
            $id = Database::createRssSource($data);

            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT * FROM rss_sources WHERE id = ?");
            $stmt->execute([$id]);
            $source = $stmt->fetch();

            log_info("RSS source created: {$source['name']} (ID: $id)");

            $this->success([
                'message' => 'RSS source created',
                'source' => $source
            ], 201);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                $this->error('URL already exists', 409);
            }
            log_error("Failed to create RSS source: " . $e->getMessage());
            $this->error('Failed to create RSS source', 500);
        }
    }

    protected function put(): void {
        $id = $this->paramInt('id');
        if (!$id) {
            $this->error('Source ID required', 400);
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM rss_sources WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            $this->error('RSS source not found', 404);
        }

        $data = $this->getJsonBody();

        if (isset($data['url']) && !filter_var($data['url'], FILTER_VALIDATE_URL)) {
            $this->error('Invalid URL format', 400);
        }

        try {
            Database::updateRssSource($id, $data);

            $stmt = $db->prepare("SELECT * FROM rss_sources WHERE id = ?");
            $stmt->execute([$id]);
            $source = $stmt->fetch();

            log_info("RSS source updated: {$source['name']} (ID: $id)");

            $this->success([
                'message' => 'RSS source updated',
                'source' => $source
            ]);
        } catch (Exception $e) {
            log_error("Failed to update RSS source $id: " . $e->getMessage());
            $this->error('Failed to update RSS source', 500);
        }
    }

    protected function delete(): void {
        $id = $this->paramInt('id');
        if (!$id) {
            $this->error('Source ID required', 400);
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM rss_sources WHERE id = ?");
        $stmt->execute([$id]);
        $source = $stmt->fetch();

        if (!$source) {
            $this->error('RSS source not found', 404);
        }

        try {
            Database::deleteRssSource($id);
            log_info("RSS source deleted: {$source['name']} (ID: $id)");
            $this->success(['message' => 'RSS source deleted']);
        } catch (Exception $e) {
            log_error("Failed to delete RSS source $id: " . $e->getMessage());
            $this->error('Failed to delete RSS source', 500);
        }
    }

    private function testSource(): void {
        $data = $this->getJsonBody();
        if (empty($data['url'])) {
            $this->error('URL required', 400);
        }

        $url = $data['url'];

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'ToutVaMal RSS Reader/2.0'
                ]
            ]);

            $content = @file_get_contents($url, false, $context);

            if ($content === false) {
                $this->error('Cannot fetch URL', 400);
            }

            $xml = @simplexml_load_string($content);
            if ($xml === false) {
                $this->error('Invalid RSS/XML format', 400);
            }

            // Détecter le type (RSS 2.0, Atom, etc.)
            $items = [];
            $feedTitle = '';

            if (isset($xml->channel)) {
                // RSS 2.0
                $feedTitle = (string)$xml->channel->title;
                foreach ($xml->channel->item as $item) {
                    $items[] = [
                        'title' => (string)$item->title,
                        'link' => (string)$item->link,
                        'pubDate' => (string)$item->pubDate
                    ];
                    if (count($items) >= 5) break;
                }
            } elseif (isset($xml->entry)) {
                // Atom
                $feedTitle = (string)$xml->title;
                foreach ($xml->entry as $entry) {
                    $items[] = [
                        'title' => (string)$entry->title,
                        'link' => (string)($entry->link['href'] ?? $entry->link),
                        'pubDate' => (string)$entry->published
                    ];
                    if (count($items) >= 5) break;
                }
            } else {
                $this->error('Unknown feed format', 400);
            }

            $this->success([
                'valid' => true,
                'feed_title' => $feedTitle,
                'items_count' => count($items),
                'sample_items' => $items
            ]);
        } catch (Exception $e) {
            $this->error('Test failed: ' . $e->getMessage(), 400);
        }
    }

    private function fetchAllSources(): void {
        $sources = Database::getRssSources(true);
        $results = [];

        foreach ($sources as $source) {
            try {
                $items = $this->fetchSourceItems($source['url']);
                Database::updateRssSource($source['id'], ['last_fetch' => date('Y-m-d H:i:s')]);

                $results[] = [
                    'source' => $source['name'],
                    'status' => 'success',
                    'items_count' => count($items)
                ];
            } catch (Exception $e) {
                $results[] = [
                    'source' => $source['name'],
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        $this->success([
            'message' => 'Fetch complete',
            'results' => $results
        ]);
    }

    private function fetchSourceItems(string $url): array {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'ToutVaMal RSS Reader/2.0'
            ]
        ]);

        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            throw new Exception('Cannot fetch URL');
        }

        $xml = @simplexml_load_string($content);
        if ($xml === false) {
            throw new Exception('Invalid XML');
        }

        $items = [];

        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $items[] = [
                    'title' => (string)$item->title,
                    'link' => (string)$item->link,
                    'description' => strip_tags((string)$item->description),
                    'pubDate' => (string)$item->pubDate
                ];
            }
        } elseif (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $items[] = [
                    'title' => (string)$entry->title,
                    'link' => (string)($entry->link['href'] ?? $entry->link),
                    'description' => strip_tags((string)$entry->summary),
                    'pubDate' => (string)$entry->published
                ];
            }
        }

        return $items;
    }
}

// Exécution
$api = new RssSourcesAPI();
$api->handle();
