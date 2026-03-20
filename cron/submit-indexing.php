#!/usr/bin/env php
<?php
/**
 * ToutVaMal.fr - Google Indexing API Submitter
 * Soumet automatiquement les URLs récentes à Google pour indexation.
 *
 * Usage:
 *   php cron/submit-indexing.php                  # Soumet les articles des 24 dernières heures
 *   php cron/submit-indexing.php --url URL        # Soumet une URL spécifique
 *   php cron/submit-indexing.php --all-pending    # Soumet tous les articles jamais soumis
 */

if (php_sapi_name() !== 'cli') die('CLI only');

chdir(dirname(__DIR__));
require_once 'config.php';

// --- Configuration ---
define('GOOGLE_KEY_PATH', DATA_PATH . '/google-indexing-key.json');
define('INDEXING_API_ENDPOINT', 'https://indexing.googleapis.com/v3/urlNotifications:publish');
define('INDEXING_API_SCOPE', 'https://www.googleapis.com/auth/indexing');

echo "=== Google Indexing API Submitter ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// --- Vérifier la clé ---
if (!file_exists(GOOGLE_KEY_PATH)) {
    die("ERROR: Google service account key not found at " . GOOGLE_KEY_PATH . "\n");
}

// --- Déterminer les URLs à soumettre ---
$urls = [];

if (in_array('--url', $argv)) {
    $idx = array_search('--url', $argv);
    $url = $argv[$idx + 1] ?? null;
    if ($url) $urls[] = $url;
} elseif (in_array('--all-pending', $argv)) {
    // Tous les articles publiés qui n'ont pas encore été soumis
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Créer la table de suivi si elle n'existe pas
    $db->exec("CREATE TABLE IF NOT EXISTS indexing_submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        url TEXT NOT NULL UNIQUE,
        submitted_at TEXT NOT NULL,
        response_code INTEGER,
        response_body TEXT
    )");

    $stmt = $db->query("
        SELECT slug FROM articles
        WHERE status = 'published'
        AND slug NOT IN (SELECT REPLACE(REPLACE(url, '" . SITE_URL . "/articles/', ''), '.html', '') FROM indexing_submissions WHERE response_code = 200)
        ORDER BY published_at DESC
    ");
    foreach ($stmt->fetchAll() as $row) {
        $urls[] = SITE_URL . '/articles/' . $row['slug'] . '.html';
    }
} else {
    // Par défaut : articles des dernières 24h
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $db->exec("CREATE TABLE IF NOT EXISTS indexing_submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        url TEXT NOT NULL UNIQUE,
        submitted_at TEXT NOT NULL,
        response_code INTEGER,
        response_body TEXT
    )");

    $stmt = $db->query("
        SELECT slug FROM articles
        WHERE status = 'published'
        AND published_at >= datetime('now', '-24 hours')
        ORDER BY published_at DESC
    ");
    foreach ($stmt->fetchAll() as $row) {
        $urls[] = SITE_URL . '/articles/' . $row['slug'] . '.html';
    }

    // Ajouter la homepage aussi
    $urls[] = SITE_URL . '/';
}

if (empty($urls)) {
    echo "No URLs to submit.\n";
    exit(0);
}

echo "URLs to submit: " . count($urls) . "\n\n";

// --- Obtenir le token OAuth2 ---
$accessToken = getAccessToken();
if (!$accessToken) {
    die("ERROR: Failed to obtain access token\n");
}

echo "OAuth2 token obtained OK\n\n";

// --- Soumettre chaque URL ---
$success = 0;
$failed = 0;
$db = new PDO('sqlite:' . DB_PATH);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

foreach ($urls as $url) {
    $result = submitUrl($url, $accessToken);

    echo ($result['success'] ? 'OK' : 'FAIL') . ": $url (HTTP {$result['code']})\n";

    // Logger la soumission
    try {
        $stmt = $db->prepare("
            INSERT OR REPLACE INTO indexing_submissions (url, submitted_at, response_code, response_body)
            VALUES (:url, :submitted_at, :code, :body)
        ");
        $stmt->execute([
            ':url' => $url,
            ':submitted_at' => date('Y-m-d H:i:s'),
            ':code' => $result['code'],
            ':body' => $result['body']
        ]);
    } catch (Exception $e) {
        // Ignorer les erreurs de log
    }

    if ($result['success']) {
        $success++;
    } else {
        $failed++;
        // Afficher l'erreur pour debug
        if ($result['body']) {
            $data = json_decode($result['body'], true);
            $errorMsg = $data['error']['message'] ?? $result['body'];
            echo "  Error: $errorMsg\n";
        }
    }

    // Rate limiting : max 200 requêtes/jour, on espace un peu
    usleep(500000); // 0.5s entre chaque requête
}

echo "\n=== SUMMARY ===\n";
echo "Submitted: $success\n";
echo "Failed: $failed\n";
echo "================\n";

exit($failed > 0 ? 1 : 0);

// ============================================================
// Fonctions
// ============================================================

/**
 * Obtient un access token OAuth2 via le service account (JWT)
 */
function getAccessToken(): ?string {
    $key = json_decode(file_get_contents(GOOGLE_KEY_PATH), true);

    if (!$key || empty($key['private_key']) || empty($key['client_email'])) {
        echo "ERROR: Invalid service account key\n";
        return null;
    }

    // Construire le JWT
    $header = base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));

    $now = time();
    $claims = base64url_encode(json_encode([
        'iss' => $key['client_email'],
        'scope' => INDEXING_API_SCOPE,
        'aud' => $key['token_uri'],
        'iat' => $now,
        'exp' => $now + 3600,
    ]));

    $signingInput = "$header.$claims";

    // Signer avec la clé privée RSA
    $privateKey = openssl_pkey_get_private($key['private_key']);
    if (!$privateKey) {
        echo "ERROR: Failed to parse private key\n";
        return null;
    }

    openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    $jwt = "$signingInput." . base64url_encode($signature);

    // Échanger le JWT contre un access token
    $ch = curl_init($key['token_uri']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo "ERROR: Token exchange failed (HTTP $httpCode): $response\n";
        return null;
    }

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

/**
 * Soumet une URL à l'Indexing API
 */
function submitUrl(string $url, string $accessToken): array {
    $ch = curl_init(INDEXING_API_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'url' => $url,
            'type' => 'URL_UPDATED',
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'success' => $httpCode === 200,
        'code' => $httpCode,
        'body' => $response,
    ];
}

/**
 * Base64url encode (sans padding, URL-safe)
 */
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
