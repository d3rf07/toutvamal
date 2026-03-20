<?php
/**
 * ToutVaMal.fr - Authentication Helper v2
 * Gestion authentification API - Cookie HttpOnly + CSRF
 *
 * Structure cookie : tvm_admin_session (HttpOnly, Secure, SameSite=Strict)
 * Protection CSRF  : token en session PHP, envoyé en meta tag, vérifié en header X-CSRF-Token
 */

require_once dirname(dirname(__DIR__)) . '/config.php';

class Auth {
    private static string $cookieName   = 'tvm_admin_session';
    private static string $csrfSession  = 'tvm_csrf_token';
    private static int    $cookieTtl    = 86400; // 24h

    // -------------------------------------------------------------------------
    // SESSION
    // -------------------------------------------------------------------------

    /**
     * Démarre la session PHP si pas déjà active
     */
    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('tvm_session');
            session_set_cookie_params([
                'lifetime' => self::$cookieTtl,
                'path'     => '/',
                'domain'   => '',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    // -------------------------------------------------------------------------
    // AUTH PRINCIPALE : Cookie HttpOnly
    // -------------------------------------------------------------------------

    /**
     * Vérifie l'authentification (cookie HttpOnly) et bloque si absent/invalide.
     * Accepte aussi Bearer token en fallback pour rétrocompatibilité.
     */
    public static function requireApiToken(): void {
        // 1. Vérifier cookie HttpOnly (méthode principale)
        if (isset($_COOKIE[self::$cookieName])) {
            $token = $_COOKIE[self::$cookieName];
            if ($token === API_TOKEN) {
                return; // OK
            }
        }

        // 2. Fallback Bearer token (transitoire, à retirer après migration complète)
        $bearerToken = self::getBearerToken();
        if ($bearerToken && $bearerToken === API_TOKEN) {
            return; // OK - compatible ancien client
        }

        self::unauthorized('Invalid or missing authentication');
    }

    /**
     * Vérifie l'authentification sans bloquer (retourne bool)
     */
    public static function checkApiToken(): bool {
        if (isset($_COOKIE[self::$cookieName]) && $_COOKIE[self::$cookieName] === API_TOKEN) {
            return true;
        }
        $bearerToken = self::getBearerToken();
        return $bearerToken && $bearerToken === API_TOKEN;
    }

    /**
     * Crée la session admin : pose le cookie HttpOnly + génère le token CSRF
     * À appeler après validation du token au login
     */
    public static function createSession(): string {
        // Cookie d'authentification HttpOnly
        setcookie(
            self::$cookieName,
            API_TOKEN,
            [
                'expires'  => time() + self::$cookieTtl,
                'path'     => '/',
                'domain'   => '',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Strict',
            ]
        );

        // Générer et stocker le token CSRF en session
        self::startSession();
        $csrfToken = bin2hex(random_bytes(32));
        $_SESSION[self::$csrfSession] = $csrfToken;

        return $csrfToken;
    }

    /**
     * Détruit la session admin (logout)
     */
    public static function destroySession(): void {
        // Supprimer le cookie
        setcookie(
            self::$cookieName,
            '',
            [
                'expires'  => time() - 3600,
                'path'     => '/',
                'domain'   => '',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Strict',
            ]
        );

        // Vider la session PHP
        self::startSession();
        if (isset($_SESSION[self::$csrfSession])) {
            unset($_SESSION[self::$csrfSession]);
        }
        session_destroy();
    }

    // -------------------------------------------------------------------------
    // CSRF
    // -------------------------------------------------------------------------

    /**
     * Vérifie le token CSRF pour les méthodes POST/PUT/DELETE
     * Autorise les requêtes GET (lecture seule) sans CSRF
     */
    public static function verifyCsrf(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // GET ne nécessite pas de CSRF
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        self::startSession();

        $sessionCsrf = $_SESSION[self::$csrfSession] ?? null;
        if (!$sessionCsrf) {
            // Pas de session CSRF — bloquer si pas de fallback Bearer
            // (le fallback Bearer ne vérifie pas CSRF pour rétrocompatibilité)
            if (self::getBearerToken() === API_TOKEN) {
                return; // Ancien client, on tolère
            }
            self::forbidden('CSRF session missing');
        }

        // Lire le token depuis le header X-CSRF-Token
        $headerCsrf = self::getCsrfHeader();
        if (!$headerCsrf || !hash_equals($sessionCsrf, $headerCsrf)) {
            // Fallback Bearer : tolère l'absence de CSRF
            if (self::getBearerToken() === API_TOKEN) {
                return;
            }
            self::forbidden('CSRF token invalid');
        }
    }

    /**
     * Retourne le token CSRF actuel pour injection dans les meta tags
     */
    public static function getCsrfToken(): string {
        self::startSession();
        if (empty($_SESSION[self::$csrfSession])) {
            $_SESSION[self::$csrfSession] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::$csrfSession];
    }

    // -------------------------------------------------------------------------
    // HELPERS PRIVÉS
    // -------------------------------------------------------------------------

    private static function getBearerToken(): ?string {
        $header = self::getAuthorizationHeader();
        if ($header && preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private static function getAuthorizationHeader(): ?string {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                if (strtolower($k) === 'authorization') {
                    return $v;
                }
            }
        }
        return null;
    }

    private static function getCsrfHeader(): ?string {
        // HTTP_X_CSRF_TOKEN (format serveur)
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        // Via getallheaders()
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                if (strtolower($k) === 'x-csrf-token') {
                    return $v;
                }
            }
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // RÉPONSES HTTP
    // -------------------------------------------------------------------------

    public static function unauthorized(string $message = 'Unauthorized'): void {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        header('WWW-Authenticate: Bearer realm="ToutVaMal API"');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function forbidden(string $message = 'Forbidden'): void {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------------------
    // RATE LIMITING
    // -------------------------------------------------------------------------

    public static function checkRateLimit(int $maxRequests = 60, int $windowSeconds = 60): bool {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
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

    // -------------------------------------------------------------------------
    // LOG D'ACCÈS
    // -------------------------------------------------------------------------

    public static function logAccess(string $endpoint, string $method): void {
        $ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $timestamp = date('Y-m-d H:i:s');
        $logLine   = "[$timestamp] $method $endpoint | IP: $ip | UA: " . substr($userAgent, 0, 50) . "\n";
        file_put_contents(LOGS_PATH . '/api_access.log', $logLine, FILE_APPEND);
    }
}

// =============================================================================
// ENDPOINT LOGIN : /api/v2/login.php
// =============================================================================

/**
 * Endpoint dédié au login admin
 * POST /api/v2/login.php  { "token": "..." }
 * → Pose le cookie HttpOnly, retourne { "ok": true, "csrf": "..." }
 *
 * GET  /api/v2/login.php
 * → Vérifie la session, retourne { "authenticated": true/false, "csrf": "..." }
 *
 * DELETE /api/v2/login.php
 * → Logout, supprime cookie et session
 */
function handleLoginEndpoint(): void {
    require_once dirname(dirname(__DIR__)) . '/config.php';

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Headers CORS
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: https://toutvamal.fr');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Allow-Credentials: true');

    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if ($method === 'POST') {
        // Login : valider le token, créer la session
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $token = trim($input['token'] ?? '');

        if ($token !== API_TOKEN) {
            http_response_code(401);
            echo json_encode(['error' => 'Token invalide'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $csrfToken = Auth::createSession();
        echo json_encode(['ok' => true, 'csrf' => $csrfToken], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'GET') {
        // Vérifier session courante
        $authenticated = Auth::checkApiToken();
        $csrf = $authenticated ? Auth::getCsrfToken() : null;
        echo json_encode(['authenticated' => $authenticated, 'csrf' => $csrf], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method === 'DELETE') {
        // Logout
        Auth::destroySession();
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================================================
// CLASSE APIEndpoint (inchangée, avec ajout vérif CSRF)
// =============================================================================

class APIEndpoint {
    protected bool $requireAuth = true;
    protected bool $logAccess   = true;
    protected bool $requireCsrf = true;

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
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
        header('Access-Control-Allow-Credentials: true');

        // Preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');

        // Auth
        if ($this->requireAuth) {
            Auth::requireApiToken();
        }

        // CSRF (POST/PUT/DELETE uniquement)
        if ($this->requireCsrf) {
            Auth::verifyCsrf();
        }

        // Log
        if ($this->logAccess) {
            Auth::logAccess($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
        }
    }

    public function handle(): void {
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':    $this->get();    break;
            case 'POST':   $this->post();   break;
            case 'PUT':    $this->put();    break;
            case 'DELETE': $this->delete(); break;
            default:       $this->methodNotAllowed();
        }
    }

    protected function get(): void    { $this->methodNotAllowed(); }
    protected function post(): void   { $this->methodNotAllowed(); }
    protected function put(): void    { $this->methodNotAllowed(); }
    protected function delete(): void { $this->methodNotAllowed(); }

    protected function methodNotAllowed(): void {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    protected function getJsonBody(): array {
        return json_decode(file_get_contents('php://input'), true) ?: [];
    }

    protected function success($data, int $status = 200): void {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function error(string $message, int $status = 400, array $extra = []): void {
        http_response_code($status);
        echo json_encode(array_merge(['error' => $message], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    }

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

    protected function param(string $name, $default = null) {
        return $_GET[$name] ?? $default;
    }

    protected function paramInt(string $name, int $default = 0): int {
        return (int)($this->param($name, $default));
    }
}
