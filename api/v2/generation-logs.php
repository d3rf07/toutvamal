<?php
/**
 * ToutVaMal.fr - Generation Logs API v2
 * Historique des générations d'articles
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

class GenerationLogsAPI extends APIEndpoint {

    protected function get(): void {
        $id = $this->paramInt('id');

        if ($id) {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT gl.*, a.title as article_title, a.slug as article_slug,
                       j.name as journalist_name
                FROM generation_logs gl
                LEFT JOIN articles a ON gl.article_id = a.id
                LEFT JOIN journalists j ON gl.journalist_id = j.id
                WHERE gl.id = ?
            ");
            $stmt->execute([$id]);
            $log = $stmt->fetch();

            if (!$log) {
                $this->error('Log not found', 404);
            }
            $this->success($log);
        }

        // Liste avec filtres
        $status = $this->param('status');
        $limit = $this->paramInt('limit', 100);
        $offset = $this->paramInt('offset', 0);

        $logs = Database::getGenerationLogs($limit, $offset, $status);

        // Total count
        $db = Database::getInstance();
        if ($status) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM generation_logs WHERE status = ?");
            $stmt->execute([$status]);
            $total = (int)$stmt->fetchColumn();
        } else {
            $total = (int)$db->query("SELECT COUNT(*) FROM generation_logs")->fetchColumn();
        }

        $this->success([
            'logs' => $logs,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + count($logs)) < $total
            ]
        ]);
    }

    protected function delete(): void {
        $id = $this->paramInt('id');
        $action = $this->param('action');

        if ($action === 'clear-old') {
            $this->clearOldLogs();
        }

        if (!$id) {
            $this->error('Log ID required', 400);
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM generation_logs WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            $this->error('Log not found', 404);
        }

        $this->success(['message' => 'Log deleted']);
    }

    private function clearOldLogs(): void {
        $days = $this->paramInt('days', 30);

        $db = Database::getInstance();
        $stmt = $db->prepare("
            DELETE FROM generation_logs
            WHERE created_at < date('now', '-' || ? || ' days')
        ");
        $stmt->execute([$days]);
        $deleted = $stmt->rowCount();

        log_info("Cleared $deleted old generation logs (older than $days days)");

        $this->success([
            'message' => "Deleted $deleted logs older than $days days"
        ]);
    }
}

// Exécution
$api = new GenerationLogsAPI();
$api->handle();
