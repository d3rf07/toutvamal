<?php
/**
 * ToutVaMal.fr - News API v2
 * Récupération d'actualités depuis les sources RSS
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/v1/RSSFetcher.php';

class NewsAPI extends APIEndpoint {

    protected function get(): void {
        $action = $this->param('action', 'available');

        if ($action === 'available') {
            $this->getAvailableNews();
        } else {
            $this->error('Unknown action', 400);
        }
    }

    protected function post(): void {
        $action = $this->param('action', 'fetch');

        if ($action === 'fetch') {
            $this->fetchFromRss();
        } else {
            $this->error('Unknown action', 400);
        }
    }

    private function getAvailableNews(): void {
        $sources = Database::getRssSources(true);
        $fetcher = new RSSFetcher();
        $db = Database::getInstance();
        $items = [];

        foreach ($sources as $source) {
            try {
                $feedItems = $fetcher->fetch($source['url']);
                foreach ($feedItems as $item) {
                    if (empty($item['link'])) continue;

                    // Check if already processed
                    $stmt = $db->prepare("SELECT COUNT(*) FROM generation_logs WHERE source_url = ?");
                    $stmt->execute([$item['link']]);
                    $inLogs = $stmt->fetchColumn() > 0;

                    $stmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE source_url = ?");
                    $stmt->execute([$item['link']]);
                    $inArticles = $stmt->fetchColumn() > 0;

                    $isSimilar = $fetcher->isSimilarTopicCovered($item['title']);

                    $items[] = [
                        'title' => $item['title'],
                        'description' => $item['description'] ?? '',
                        'link' => $item['link'],
                        'source' => $item['source'] ?? $source['name'],
                        'pubDate' => $item['pubDate'] ?? null,
                        'already_processed' => $inLogs || $inArticles,
                        'similar_topic' => $isSimilar,
                        'available' => !$inLogs && !$inArticles && !$isSimilar
                    ];
                }
            } catch (Exception $e) {
                log_error("News fetch failed for {$source['name']}: " . $e->getMessage());
            }
        }

        // Sort: available first, then by date
        usort($items, function($a, $b) {
            if ($a['available'] !== $b['available']) {
                return $b['available'] <=> $a['available'];
            }
            return strtotime($b['pubDate'] ?? '0') - strtotime($a['pubDate'] ?? '0');
        });

        $this->success([
            'items' => $items,
            'total' => count($items),
            'available' => count(array_filter($items, fn($i) => $i['available']))
        ]);
    }

    private function fetchFromRss(): void {
        $sources = Database::getRssSources(true);
        $fetcher = new RSSFetcher();
        $results = [];

        foreach ($sources as $source) {
            try {
                $items = $fetcher->fetch($source['url']);
                $results[] = [
                    'source' => $source['name'],
                    'url' => $source['url'],
                    'items_found' => count($items),
                    'status' => 'ok'
                ];
                Database::updateRssSource($source['id'], ['last_fetch' => date('Y-m-d H:i:s')]);
            } catch (Exception $e) {
                $results[] = [
                    'source' => $source['name'],
                    'url' => $source['url'],
                    'items_found' => 0,
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        $this->success([
            'results' => $results,
            'sources_checked' => count($results),
            'sources_ok' => count(array_filter($results, fn($r) => $r['status'] === 'ok'))
        ]);
    }
}

// Exécution
$api = new NewsAPI();
$api->handle();
