<?php
/**
 * ToutVaMal.fr - Journalists API v2
 * CRUD complet pour les journalistes
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

class JournalistsAPI extends APIEndpoint {

    protected function get(): void {
        $id = $this->paramInt('id');

        if ($id) {
            $journalist = Database::getJournalistById($id);
            if (!$journalist) {
                $this->error('Journalist not found', 404);
            }
            $this->success($journalist);
        }

        // Liste avec option activeOnly
        $activeOnly = $this->param('active') === '1';
        $journalists = Database::getJournalists($activeOnly);

        // Ajouter stats articles par journaliste
        $db = Database::getInstance();
        foreach ($journalists as &$j) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE journalist_id = ?");
            $stmt->execute([$j['id']]);
            $j['articles_count'] = (int)$stmt->fetchColumn();
        }

        $this->success([
            'journalists' => $journalists,
            'total' => count($journalists)
        ]);
    }

    protected function post(): void {
        $data = $this->getJsonBody();
        $this->validateRequired($data, ['name']);

        // Génération du slug si non fourni
        if (empty($data['slug'])) {
            $data['slug'] = slugify($data['name']);
        }

        try {
            $id = Database::createJournalist($data);
            $journalist = Database::getJournalistById($id);

            log_info("Journalist created: {$journalist['name']} (ID: $id)");

            $this->success([
                'message' => 'Journalist created',
                'journalist' => $journalist
            ], 201);
        } catch (Exception $e) {
            log_error("Failed to create journalist: " . $e->getMessage());
            $this->error('Failed to create journalist: ' . $e->getMessage(), 500);
        }
    }

    protected function put(): void {
        $id = $this->paramInt('id');
        if (!$id) {
            $this->error('Journalist ID required', 400);
        }

        $journalist = Database::getJournalistById($id);
        if (!$journalist) {
            $this->error('Journalist not found', 404);
        }

        $data = $this->getJsonBody();

        try {
            Database::updateJournalist($id, $data);
            $updated = Database::getJournalistById($id);

            log_info("Journalist updated: {$updated['name']} (ID: $id)");

            $this->success([
                'message' => 'Journalist updated',
                'journalist' => $updated
            ]);
        } catch (Exception $e) {
            log_error("Failed to update journalist $id: " . $e->getMessage());
            $this->error('Failed to update journalist: ' . $e->getMessage(), 500);
        }
    }

    protected function delete(): void {
        $id = $this->paramInt('id');
        if (!$id) {
            $this->error('Journalist ID required', 400);
        }

        $journalist = Database::getJournalistById($id);
        if (!$journalist) {
            $this->error('Journalist not found', 404);
        }

        // Vérifier si des articles sont liés
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT COUNT(*) FROM articles WHERE journalist_id = ?");
        $stmt->execute([$id]);
        $articlesCount = (int)$stmt->fetchColumn();

        if ($articlesCount > 0) {
            $this->error("Cannot delete journalist with $articlesCount linked articles. Set inactive instead.", 409);
        }

        try {
            // Supprimer photo si existe
            if ($journalist['photo_path']) {
                $photoPath = ROOT_PATH . '/equipe/' . $journalist['photo_path'];
                if (file_exists($photoPath)) {
                    unlink($photoPath);
                }
            }

            Database::deleteJournalist($id);

            log_info("Journalist deleted: {$journalist['name']} (ID: $id)");

            $this->success(['message' => 'Journalist deleted']);
        } catch (Exception $e) {
            log_error("Failed to delete journalist $id: " . $e->getMessage());
            $this->error('Failed to delete journalist: ' . $e->getMessage(), 500);
        }
    }
}

// Exécution
$api = new JournalistsAPI();
$api->handle();
