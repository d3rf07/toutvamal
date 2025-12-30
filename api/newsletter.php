<?php
/**
 * ToutVaMal.fr - Newsletter API
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$email) {
    json_response(['error' => 'Email invalide'], 400);
}

try {
    // Vérifier si déjà inscrit
    $stmt = db()->prepare("SELECT id, unsubscribed_at FROM newsletter WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['unsubscribed_at']) {
            // Réinscrire
            $stmt = db()->prepare("UPDATE newsletter SET unsubscribed_at = NULL, subscribed_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute([':id' => $existing['id']]);
            json_response(['success' => true, 'message' => 'Réinscription réussie']);
        } else {
            json_response(['success' => true, 'message' => 'Vous êtes déjà inscrit']);
        }
    } else {
        // Nouvelle inscription
        $stmt = db()->prepare("INSERT INTO newsletter (email, source) VALUES (:email, 'website')");
        $stmt->execute([':email' => $email]);
        log_info("Nouvelle inscription newsletter: $email");
        json_response(['success' => true, 'message' => 'Inscription réussie']);
    }
} catch (PDOException $e) {
    log_error("Newsletter error: " . $e->getMessage());
    json_response(['error' => 'Erreur serveur'], 500);
}
