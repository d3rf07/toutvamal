<?php
/**
 * ToutVaMal.fr - Authentication Helper v2
 * Gestion authentification API et sessions admin
 */

require_once dirname(dirname(__DIR__)) . '/config.php';

class Auth {
    /**
     * Vérifie le token API Bearer
     */
    public static function requireApiToken(): void {
        $token = self::getBearerToken();

        if (!$token || $token !== API_TOKEN) {
            self::unauthorized('Invalid or missing API token');
        }
    }

    /**
     * Vérifie le token sans bloquer (retourne bool)
     */
    public static function checkApiToken(): bool {
        $token = self::getBearerToken();
        return $token && $token === API_TOKEN;
    }

    /**
     * Extrait le Bearer token des headers
     */
    private static function getBearerToken(): ?string {
        $headers = self::getAuthorizationHeader();

        if ($headers && preg_match('/Bearer\s+(.+)/i', $headers, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Récupère le header Authorization (compatible différents serveurs)
     */
    private static function getAuthorizationHeader(): ?string {
        // Apache/nginx standard
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }

        // Apache avec mod_rewrite
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        // getallheaders() fallback
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Réponse 401 Unauthorized
     */
    public static function unauthorized(string $message = 'Unauthorized'): void {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        header('WWW-Authenticate: Bearer realm="ToutVaMal API"');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Réponse 403 Forbidden
     */
    public static function forbidden(string $message = 'Forbidden'): void {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Vérifie le rate limiting simple (basé sur IP)
     */
    public static function checkRateLimit(int $maxRequests = 60, int $windowSeconds = 60): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'rate_' . md5($ip);
        $file = LOGS_PATH . '/rate_limits.json';

        $limits = [];
        if (file_exists($file)) {
            $limits = json_decode(file_get_contents($file), true) ?: [];
        }

        $now = time();

        // Nettoyer les anciennes entrées
        foreach ($limits as $k => $data) {
            if ($data['window_start'] < $now - $windowSeconds) {
                unset($limits[$k]);
            }
        }

        if (!isset($limits[$key])) {
            $limits[$key] = ['count' => 0, 'window_start' => $now];
        }

        // Réinitialiser si la fenêtre est passée
        if ($limits[$key]['window_start'] < $now - $windowSeconds) {
            $limits[$key] = ['count' => 0, 'window_start' => $now];
        }

        $limits[$key]['count']++;

        file_put_contents($file, json_encode($limits));

        if ($limits[$key]['count'] > $maxRequests) {
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            header('Retry-After: ' . ($windowSeconds - ($now - $limits[$key]['window_start'])));
            echo json_encode(['error' => 'Too many requests'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        return true;
    }

    /**
     * Log d'accès API
     */
    public static function logAccess(string $endpoint, string $method): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $timestamp = date('Y-m-d H:i:s');

        $logLine = "[$timestamp] $method $endpoint | IP: $ip | UA: " . substr($userAgent, 0, 50) . "\n";
        file_put_contents(LOGS_PATH . '/api_access.log', $logLine, FILE_APPEND);
    }
}

/**
 * Classe de base pour les endpoints API
 */
class APIEndpoint {
    protected bool $requireAuth = true;
    protected bool $logAccess = true;

    public function __construct() {
        // Headers CORS
        $allowedOrigins = [SITE_URL, SITE_URL . ':443', 'https://toutvamal.fr', 'https://www.toutvamal.fr'];
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (in_array($origin, $allowedOrigins)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } else {
            header('Access-Control-Allow-Origin: https://toutvamal.fr');
        }
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        // Preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        // Content-Type JSON par défaut
        header('Content-Type: application/json; charset=utf-8');

        // Auth si requis
        if ($this->requireAuth) {
            Auth::requireApiToken();
        }

        // Log
        if ($this->logAccess) {
            Auth::logAccess($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
        }
    }

    /**
     * Route la requête vers la bonne méthode
     */
    public function handle(): void {
        $method = $_SERVER['REQUEST_METHOD'];

        switch ($method) {
            case 'GET':
                $this->get();
                break;
            case 'POST':
                $this->post();
                break;
            case 'PUT':
                $this->put();
                break;
            case 'DELETE':
                $this->delete();
                break;
            default:
                $this->methodNotAllowed();
        }
    }

    protected function get(): void {
        $this->methodNotAllowed();
    }

    protected function post(): void {
        $this->methodNotAllowed();
    }

    protected function put(): void {
        $this->methodNotAllowed();
    }

    protected function delete(): void {
        $this->methodNotAllowed();
    }

    protected function methodNotAllowed(): void {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    /**
     * Récupère le body JSON de la requête
     */
    protected function getJsonBody(): array {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?: [];
    }

    /**
     * Réponse JSON success
     */
    protected function success($data, int $status = 200): void {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Réponse JSON error
     */
    protected function error(string $message, int $status = 400, array $extra = []): void {
        http_response_code($status);
        echo json_encode(array_merge(['error' => $message], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Valide les champs requis
     */
    protected function validateRequired(array $data, array $required): array {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            $this->error('Missing required fields: ' . implode(', ', $missing), 422);
        }

        return $data;
    }

    /**
     * Récupère un paramètre GET avec valeur par défaut
     */
    protected function param(string $name, $default = null) {
        return $_GET[$name] ?? $default;
    }

    /**
     * Récupère un paramètre GET comme entier
     */
    protected function paramInt(string $name, int $default = 0): int {
        return (int)($this->param($name, $default));
    }
}
