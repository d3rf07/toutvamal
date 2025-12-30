<?php
/**
 * ToutVaMal.fr - System API v2
 * Actions système (QA, cache, logs, deploy)
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

class SystemAPI extends APIEndpoint {

    protected function get(): void {
        $action = $this->param('action');

        if ($action === 'logs') {
            $this->getLogs();
        }

        if ($action === 'health') {
            $this->healthCheck();
        }

        // Info système par défaut
        $this->getSystemInfo();
    }

    protected function post(): void {
        $action = $this->param('action');

        switch ($action) {
            case 'qa':
                $this->runQA();
                break;
            case 'clear-cache':
                $this->clearCache();
                break;
            case 'deploy':
                $this->triggerDeploy();
                break;
            case 'regenerate-all':
                $this->regenerateAllStatic();
                break;
            default:
                $this->error('Unknown action', 400);
        }
    }

    private function getSystemInfo(): void {
        $db = Database::getInstance();

        // Tailles des dossiers
        $sizes = [
            'articles' => $this->getFolderSize(ARTICLES_PATH),
            'images' => $this->getFolderSize(IMAGES_PATH),
            'logs' => $this->getFolderSize(LOGS_PATH),
            'database' => file_exists(DB_PATH) ? filesize(DB_PATH) : 0
        ];

        // Dernières activités
        $stmt = $db->query("SELECT created_at FROM generation_logs ORDER BY created_at DESC LIMIT 1");
        $lastGeneration = $stmt->fetchColumn();

        $stmt = $db->query("SELECT published_at FROM articles ORDER BY published_at DESC LIMIT 1");
        $lastArticle = $stmt->fetchColumn();

        $this->success([
            'version' => '2.0.0',
            'php_version' => PHP_VERSION,
            'site_url' => SITE_URL,
            'sizes' => $sizes,
            'last_generation' => $lastGeneration,
            'last_article' => $lastArticle,
            'cron_interval' => CRON_INTERVAL_HOURS . 'h'
        ]);
    }

    private function healthCheck(): void {
        $checks = [];

        // Database
        try {
            $db = Database::getInstance();
            $db->query("SELECT 1");
            $checks['database'] = ['status' => 'ok'];
        } catch (Exception $e) {
            $checks['database'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Directories writable
        $dirs = [
            'articles' => ARTICLES_PATH,
            'images' => IMAGES_PATH . '/articles',
            'logs' => LOGS_PATH
        ];

        foreach ($dirs as $name => $path) {
            $checks[$name] = is_writable($path)
                ? ['status' => 'ok']
                : ['status' => 'error', 'message' => 'Not writable'];
        }

        // API keys configured
        $checks['openrouter'] = !empty(OPENROUTER_API_KEY) && OPENROUTER_API_KEY !== 'your-key-here'
            ? ['status' => 'ok']
            : ['status' => 'warning', 'message' => 'Not configured'];

        $checks['replicate'] = !empty(REPLICATE_API_KEY) && REPLICATE_API_KEY !== 'your-key-here'
            ? ['status' => 'ok']
            : ['status' => 'warning', 'message' => 'Not configured'];

        $allOk = !array_filter($checks, fn($c) => $c['status'] === 'error');

        $this->success([
            'healthy' => $allOk,
            'checks' => $checks
        ]);
    }

    private function getLogs(): void {
        $type = $this->param('type', 'app');
        $lines = $this->paramInt('lines', 100);

        $logFiles = [
            'app' => LOGS_PATH . '/app.log',
            'error' => LOGS_PATH . '/error.log',
            'api' => LOGS_PATH . '/api_access.log'
        ];

        if (!isset($logFiles[$type])) {
            $this->error('Unknown log type', 400);
        }

        $logFile = $logFiles[$type];

        if (!file_exists($logFile)) {
            $this->success(['logs' => [], 'message' => 'Log file empty']);
        }

        // Lire les dernières lignes
        $content = file_get_contents($logFile);
        $allLines = explode("\n", trim($content));
        $lastLines = array_slice($allLines, -$lines);

        $this->success([
            'type' => $type,
            'file' => basename($logFile),
            'total_lines' => count($allLines),
            'returned_lines' => count($lastLines),
            'logs' => $lastLines
        ]);
    }

    private function runQA(): void {
        $results = [];
        $errors = [];
        $warnings = [];

        // 1. Homepage
        $homepage = @file_get_contents(SITE_URL . '/');
        if ($homepage === false) {
            $errors[] = 'Homepage not accessible';
        } else {
            if (strpos($homepage, 'TOUT') !== false) {
                $results[] = 'Homepage: Logo present';
            } else {
                $errors[] = 'Homepage: Logo missing';
            }
            if (strpos($homepage, 'class="nav"') !== false || strpos($homepage, 'class=\'nav\'') !== false) {
                $results[] = 'Homepage: Navigation present';
            } else {
                $warnings[] = 'Homepage: Navigation class not found';
            }
        }

        // 2. CSS
        $headers = @get_headers(SITE_URL . '/css/style.css');
        if ($headers && strpos($headers[0], '200') !== false) {
            $results[] = 'CSS: Accessible';
        } else {
            $errors[] = 'CSS: 403/404 error';
        }

        // 3. Sample article
        $db = Database::getInstance();
        $stmt = $db->query("SELECT slug FROM articles WHERE status = 'published' LIMIT 1");
        $article = $stmt->fetch();

        if ($article) {
            $articleHtml = @file_get_contents(SITE_URL . '/articles/' . $article['slug'] . '.html');
            if ($articleHtml !== false) {
                $results[] = 'Article: Static file accessible';
                if (strpos($articleHtml, '</html>') !== false) {
                    $results[] = 'Article: HTML complete';
                } else {
                    $errors[] = 'Article: HTML truncated';
                }
            } else {
                $warnings[] = 'Article: Static file not found';
            }
        }

        // 4. API
        $apiHeaders = @get_headers(SITE_URL . '/api/v2/stats.php');
        if ($apiHeaders && (strpos($apiHeaders[0], '200') !== false || strpos($apiHeaders[0], '401') !== false)) {
            $results[] = 'API: Endpoint responding';
        } else {
            $errors[] = 'API: Not accessible';
        }

        // 5. Admin
        $admin = @file_get_contents(SITE_URL . '/admin/');
        if ($admin !== false) {
            $results[] = 'Admin: Accessible';
        } else {
            $warnings[] = 'Admin: Not accessible';
        }

        $status = empty($errors) ? (empty($warnings) ? 'PASS' : 'PASS_WITH_WARNINGS') : 'FAIL';

        $this->success([
            'status' => $status,
            'errors' => $errors,
            'warnings' => $warnings,
            'success' => $results
        ]);
    }

    private function clearCache(): void {
        // Effacer les fichiers de cache si présents
        $cacheFiles = glob(ROOT_PATH . '/cache/*');
        $deleted = 0;

        foreach ($cacheFiles as $file) {
            if (is_file($file)) {
                unlink($file);
                $deleted++;
            }
        }

        log_info("Cache cleared: $deleted files");

        $this->success([
            'message' => "Cache cleared ($deleted files)",
            'note' => 'Hostinger cache must be cleared via hPanel'
        ]);
    }

    private function triggerDeploy(): void {
        // Permissions
        $publicHtml = ROOT_PATH;
        exec("find $publicHtml -type f -name '*.php' -exec chmod 644 {} \\;");
        exec("find $publicHtml -type f -name '*.html' -exec chmod 644 {} \\;");
        exec("find $publicHtml -type f -name '*.css' -exec chmod 644 {} \\;");
        exec("find $publicHtml -type f -name '*.js' -exec chmod 644 {} \\;");

        // Régénérer homepage statique
        $homepage = @file_get_contents(SITE_URL . '/index.php');
        if ($homepage) {
            file_put_contents(ROOT_PATH . '/index.html', $homepage);
            chmod(ROOT_PATH . '/index.html', 0644);
        }

        log_info("Deploy triggered");

        $this->success(['message' => 'Deploy completed']);
    }

    private function regenerateAllStatic(): void {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT id, slug, title FROM articles WHERE status = 'published'");
        $articles = $stmt->fetchAll();

        $success = 0;
        $failed = 0;

        foreach ($articles as $article) {
            $url = SITE_URL . '/article.php?slug=' . $article['slug'];
            $html = @file_get_contents($url);

            if ($html) {
                $filepath = ARTICLES_PATH . '/' . $article['slug'] . '.html';
                file_put_contents($filepath, $html);
                chmod($filepath, 0644);
                $success++;
            } else {
                $failed++;
            }
        }

        // Homepage
        $homepage = @file_get_contents(SITE_URL . '/index.php');
        if ($homepage) {
            file_put_contents(ROOT_PATH . '/index.html', $homepage);
            chmod(ROOT_PATH . '/index.html', 0644);
        }

        log_info("Regenerated all static: $success success, $failed failed");

        $this->success([
            'message' => 'Static files regenerated',
            'success' => $success,
            'failed' => $failed
        ]);
    }

    private function getFolderSize(string $path): int {
        if (!is_dir($path)) return 0;

        $size = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }
}

// Exécution
$api = new SystemAPI();
$api->handle();
