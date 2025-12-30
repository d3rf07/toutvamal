<?php
/**
 * ToutVaMal.fr - Articles API
 */

require_once dirname(__DIR__) . '/config.php';
require_api_token();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $limit = min((int) ($_GET['limit'] ?? 20), 100);
        $offset = (int) ($_GET['offset'] ?? 0);

        $stmt = db()->prepare("
            SELECT a.*, j.name as journalist_name
            FROM articles a
            LEFT JOIN journalists j ON a.journalist_id = j.id
            ORDER BY a.published_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        json_response($stmt->fetchAll());
        break;

    case 'DELETE':
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            json_response(['error' => 'ID required'], 400);
        }

        // Get article to delete its image
        $stmt = db()->prepare("SELECT slug, image_path FROM articles WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $article = $stmt->fetch();

        if ($article) {
            // Delete image file
            if ($article['image_path'] && file_exists(ROOT_PATH . $article['image_path'])) {
                unlink(ROOT_PATH . $article['image_path']);
            }

            // Delete static HTML
            $htmlPath = ARTICLES_PATH . '/' . $article['slug'] . '.html';
            if (file_exists($htmlPath)) {
                unlink($htmlPath);
            }

            // Delete from DB
            $stmt = db()->prepare("DELETE FROM articles WHERE id = :id");
            $stmt->execute([':id' => $id]);

            json_response(['success' => true]);
        } else {
            json_response(['error' => 'Article not found'], 404);
        }
        break;

    default:
        json_response(['error' => 'Method not allowed'], 405);
}
