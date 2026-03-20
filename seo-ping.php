<?php
/**
 * ToutVaMal.fr - SEO Ping System
 * Notifie les moteurs de recherche lors de la publication d'articles
 */

require_once __DIR__ . '/config.php';

/**
 * Ping les moteurs de recherche avec le sitemap mis à jour
 */
function ping_search_engines(): array {
    $sitemapUrl = urlencode(SITE_URL . '/sitemap.xml');
    $results = [];

    $services = [
        'Google' => "https://www.google.com/ping?sitemap={$sitemapUrl}",
        'Bing' => "https://www.bing.com/ping?sitemap={$sitemapUrl}",
        'IndexNow (Bing)' => null, // Handled separately
    ];

    foreach ($services as $name => $url) {
        if ($url === null) continue;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'ToutVaMal-SEO-Bot/1.0'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $results[$name] = [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode
        ];
    }

    return $results;
}

/**
 * Ping IndexNow (Bing, Yandex, etc.) pour une URL spécifique
 */
function ping_indexnow(string $url): array {
    // IndexNow nécessite une clé API (fichier texte à la racine)
    $keyFile = ROOT_PATH . '/indexnow-key.txt';

    if (!file_exists($keyFile)) {
        // Générer une clé si elle n'existe pas
        $key = bin2hex(random_bytes(16));
        file_put_contents($keyFile, $key);
        file_put_contents(ROOT_PATH . '/' . $key . '.txt', $key);
    }

    $key = trim(file_get_contents($keyFile));

    $data = [
        'host' => parse_url(SITE_URL, PHP_URL_HOST),
        'key' => $key,
        'urlList' => [$url]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.indexnow.org/indexnow',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'response' => $response
    ];
}

/**
 * Fonction à appeler après publication d'un article
 */
function notify_new_article(string $articleUrl, string $title = ''): void {
    $logFile = LOGS_PATH . '/seo-ping.log';
    $timestamp = date('Y-m-d H:i:s');

    // Ping IndexNow pour l'article spécifique
    $indexNowResult = ping_indexnow($articleUrl);

    // Ping général du sitemap
    $sitemapResults = ping_search_engines();

    // Log
    $log = "[$timestamp] New article: $articleUrl\n";
    $log .= "  IndexNow: " . ($indexNowResult['success'] ? 'OK' : 'FAIL') . " (HTTP {$indexNowResult['http_code']})\n";
    foreach ($sitemapResults as $service => $result) {
        $log .= "  $service: " . ($result['success'] ? 'OK' : 'FAIL') . " (HTTP {$result['http_code']})\n";
    }
    $log .= "\n";

    file_put_contents($logFile, $log, FILE_APPEND);

    if (defined('DEBUG') && DEBUG) {
        echo $log;
    }
}

/**
 * Ping WebSub/PubSubHubbub pour le flux RSS
 */
function ping_websub(): bool {
    $hub = 'https://pubsubhubbub.appspot.com/';
    $feedUrl = SITE_URL . '/rss.xml';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $hub,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'hub.mode' => 'publish',
            'hub.url' => $feedUrl
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}

// Si exécuté directement, ping le sitemap
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($argv[0])) {
    echo "=== SEO Ping System ===\n\n";

    if (isset($argv[1])) {
        echo "Ping pour: {$argv[1]}\n";
        notify_new_article($argv[1]);
    } else {
        echo "Ping du sitemap...\n";
        $results = ping_search_engines();
        foreach ($results as $service => $result) {
            $status = $result['success'] ? '✓' : '✗';
            echo "  $status $service (HTTP {$result['http_code']})\n";
        }

        echo "\nPing WebSub...\n";
        $websubOk = ping_websub();
        echo "  " . ($websubOk ? '✓' : '✗') . " PubSubHubbub\n";
    }

    echo "\nTerminé.\n";
}
