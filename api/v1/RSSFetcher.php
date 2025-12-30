<?php
/**
 * ToutVaMal.fr - RSS Fetcher
 * Fetches and parses RSS feeds
 */

class RSSFetcher {
    private array $feeds;
    private int $maxItemsPerFeed = 5;

    public function __construct() {
        $this->feeds = RSS_FEEDS;
    }

    /**
     * Fetch latest items from all feeds
     */
    public function fetchAll(): array {
        $items = [];

        foreach ($this->feeds as $feedUrl) {
            $feedItems = $this->fetchFeed($feedUrl);
            $items = array_merge($items, $feedItems);
        }

        // Shuffle to get variety
        shuffle($items);

        return $items;
    }

    /**
     * Fetch items from a single feed
     */
    private function fetchFeed(string $url): array {
        $items = [];

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'ToutVaMal RSS Reader/1.0'
                ]
            ]);

            $content = @file_get_contents($url, false, $context);
            if (!$content) {
                log_error("Failed to fetch RSS feed: $url");
                return [];
            }

            // Disable libxml errors
            $prevUseErrors = libxml_use_internal_errors(true);

            $xml = simplexml_load_string($content);
            if (!$xml) {
                log_error("Failed to parse RSS feed: $url");
                libxml_use_internal_errors($prevUseErrors);
                return [];
            }

            libxml_use_internal_errors($prevUseErrors);

            // Parse RSS 2.0 or Atom
            if (isset($xml->channel->item)) {
                // RSS 2.0
                $count = 0;
                foreach ($xml->channel->item as $item) {
                    if ($count >= $this->maxItemsPerFeed) break;

                    $items[] = [
                        'title' => (string) $item->title,
                        'description' => strip_tags((string) $item->description),
                        'link' => (string) $item->link,
                        'pubDate' => (string) $item->pubDate,
                        'source' => parse_url($url, PHP_URL_HOST)
                    ];
                    $count++;
                }
            } elseif (isset($xml->entry)) {
                // Atom
                $count = 0;
                foreach ($xml->entry as $entry) {
                    if ($count >= $this->maxItemsPerFeed) break;

                    $link = '';
                    foreach ($entry->link as $l) {
                        if ((string) $l['rel'] === 'alternate' || empty($link)) {
                            $link = (string) $l['href'];
                        }
                    }

                    $items[] = [
                        'title' => (string) $entry->title,
                        'description' => strip_tags((string) ($entry->summary ?? $entry->content)),
                        'link' => $link,
                        'pubDate' => (string) ($entry->published ?? $entry->updated),
                        'source' => parse_url($url, PHP_URL_HOST)
                    ];
                    $count++;
                }
            }

        } catch (Exception $e) {
            log_error("RSS fetch error for $url: " . $e->getMessage());
        }

        return $items;
    }

    /**
     * Check if an item has already been processed
     */
    public function isAlreadyProcessed(string $sourceUrl): bool {
        $stmt = db()->prepare("SELECT id FROM articles WHERE source_url = :url LIMIT 1");
        $stmt->execute([':url' => $sourceUrl]);
        return $stmt->fetch() !== false;
    }

    /**
     * Get random unprocessed item
     */
    public function getRandomNewItem(): ?array {
        $items = $this->fetchAll();

        foreach ($items as $item) {
            if (!empty($item['link']) && !$this->isAlreadyProcessed($item['link'])) {
                return $item;
            }
        }

        return null;
    }
}
