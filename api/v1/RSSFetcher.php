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
     * Public alias for fetchFeed (used by v2 API)
     */
    public function fetch(string $url): array {
        return $this->fetchFeed($url);
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
     * Check if an item has already been processed (by source URL)
     */
    public function isAlreadyProcessed(string $sourceUrl): bool {
        // Check articles table
        $stmt = db()->prepare("SELECT id FROM articles WHERE source_url = :url LIMIT 1");
        $stmt->execute([':url' => $sourceUrl]);
        if ($stmt->fetch() !== false) return true;

        // Check generation_logs table (covers failed attempts too)
        $stmt = db()->prepare("SELECT id FROM generation_logs WHERE source_url = :url LIMIT 1");
        $stmt->execute([':url' => $sourceUrl]);
        return $stmt->fetch() !== false;
    }

    /**
     * Check if a similar topic has been covered recently.
     * Extracts key words from the title and checks for overlap with recent articles.
     */
    public function isSimilarTopicCovered(string $title, int $daysBack = 14): bool {
        // Normalize: lowercase, remove accents, strip common French words
        $normalized = $this->normalizeTitle($title);
        $words = array_filter(explode(' ', $normalized), fn($w) => mb_strlen($w) > 3);

        if (count($words) < 2) return false;

        // Get recent article titles
        $stmt = db()->prepare("
            SELECT title, source_title FROM articles
            WHERE created_at > datetime('now', :days_ago)
        ");
        $stmt->execute([':days_ago' => "-{$daysBack} days"]);
        $recentArticles = $stmt->fetchAll();

        foreach ($recentArticles as $article) {
            $existingWords = array_filter(
                explode(' ', $this->normalizeTitle($article['title'])),
                fn($w) => mb_strlen($w) > 3
            );

            // Check source_title similarity too
            $sourceWords = array_filter(
                explode(' ', $this->normalizeTitle($article['source_title'] ?? '')),
                fn($w) => mb_strlen($w) > 3
            );

            $allExistingWords = array_unique(array_merge($existingWords, $sourceWords));

            // Calculate overlap
            $overlap = array_intersect($words, $allExistingWords);
            $overlapRatio = count($overlap) / max(count($words), 1);

            // If >50% of significant words overlap, consider it a duplicate topic
            if ($overlapRatio > 0.5 && count($overlap) >= 2) {
                log_info("Duplicate topic detected: '{$title}' overlaps with '{$article['title']}' (overlap: " . round($overlapRatio * 100) . "%)");
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize a title for comparison: lowercase, no accents, no stop words
     */
    private function normalizeTitle(string $title): string {
        $title = mb_strtolower(trim($title));

        // Remove accents
        $title = strtr($title, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e',
            'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o',
            'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
            'œ' => 'oe', 'æ' => 'ae'
        ]);

        // Remove punctuation
        $title = preg_replace('/[^\w\s]/', ' ', $title);

        // Remove French stop words
        $stopWords = ['les', 'des', 'une', 'dans', 'pour', 'par', 'sur',
            'avec', 'son', 'ses', 'aux', 'est', 'sont', 'ont', 'pas',
            'plus', 'que', 'qui', 'mais', 'cette', 'tout', 'tous',
            'elle', 'lui', 'leur', 'nous', 'vous', 'etre', 'avoir',
            'fait', 'ete', 'dit', 'apres', 'avant', 'entre', 'aussi',
            'comme', 'encore', 'peut', 'bien', 'sans', 'tres', 'meme'];

        $words = explode(' ', $title);
        $words = array_diff($words, $stopWords);

        return implode(' ', array_filter($words));
    }

    /**
     * Get random unprocessed item, with anti-duplicate checks
     */
    public function getRandomNewItem(): ?array {
        $items = $this->fetchAll();

        foreach ($items as $item) {
            if (empty($item['link'])) continue;

            // Check 1: exact URL match
            if ($this->isAlreadyProcessed($item['link'])) continue;

            // Check 2: similar topic already covered
            if ($this->isSimilarTopicCovered($item['title'])) continue;

            return $item;
        }

        return null;
    }
}
