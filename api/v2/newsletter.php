<?php
/**
 * ToutVaMal.fr - Newsletter API v2
 * Gestion abonnés newsletter
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

class NewsletterAPI extends APIEndpoint {
    protected bool $requireAuth = false; // Subscribe public

    public function __construct() {
        parent::__construct();

        // Requiert auth sauf pour POST (subscribe)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Auth::requireApiToken();
        }
    }

    protected function get(): void {
        $action = $this->param('action');

        if ($action === 'export') {
            $this->exportCsv();
        }

        // Liste abonnés (protégé par auth dans constructor)
        $confirmedOnly = $this->param('confirmed') !== '0';
        $subscribers = Database::getNewsletterSubscribers($confirmedOnly);

        $db = Database::getInstance();
        $stats = [
            'total' => (int)$db->query("SELECT COUNT(*) FROM newsletter")->fetchColumn(),
            'confirmed' => (int)$db->query("SELECT COUNT(*) FROM newsletter WHERE confirmed_at IS NOT NULL")->fetchColumn(),
            'unsubscribed' => (int)$db->query("SELECT COUNT(*) FROM newsletter WHERE unsubscribed_at IS NOT NULL")->fetchColumn()
        ];

        $this->success([
            'subscribers' => $subscribers,
            'stats' => $stats
        ]);
    }

    protected function post(): void {
        $action = $this->param('action');

        if ($action === 'confirm') {
            $this->confirmSubscription();
        }

        if ($action === 'unsubscribe') {
            $this->unsubscribe();
        }

        // Inscription publique
        $data = $this->getJsonBody();

        if (empty($data['email'])) {
            $this->error('Email required', 400);
        }

        $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $this->error('Invalid email format', 400);
        }

        // Rate limiting pour éviter spam
        Auth::checkRateLimit(5, 60); // 5 inscriptions par minute max

        $source = $data['source'] ?? 'website';
        $result = Database::subscribeNewsletter($email, $source);

        log_info("Newsletter subscription: $email (status: {$result['status']})");

        $messages = [
            'subscribed' => 'Inscription enregistrée ! Vérifiez votre email.',
            'resubscribed' => 'Re-inscription réussie !',
            'already_subscribed' => 'Email déjà inscrit.'
        ];

        $this->success([
            'message' => $messages[$result['status']] ?? 'OK',
            'status' => $result['status']
        ]);
    }

    protected function delete(): void {
        Auth::requireApiToken();

        $id = $this->paramInt('id');
        if (!$id) {
            $this->error('Subscriber ID required', 400);
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM newsletter WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            $this->error('Subscriber not found', 404);
        }

        $this->success(['message' => 'Subscriber deleted']);
    }

    private function confirmSubscription(): void {
        $data = $this->getJsonBody();

        if (empty($data['token'])) {
            $this->error('Token required', 400);
        }

        $confirmed = Database::confirmNewsletter($data['token']);

        if (!$confirmed) {
            $this->error('Invalid or expired token', 400);
        }

        log_info("Newsletter confirmed with token: " . substr($data['token'], 0, 10) . "...");

        $this->success(['message' => 'Email confirmé avec succès !']);
    }

    private function unsubscribe(): void {
        $data = $this->getJsonBody();

        if (empty($data['email'])) {
            $this->error('Email required', 400);
        }

        $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $this->error('Invalid email format', 400);
        }

        $unsubscribed = Database::unsubscribeNewsletter($email);

        log_info("Newsletter unsubscribe: $email (found: " . ($unsubscribed ? 'yes' : 'no') . ")");

        // Always return success for privacy
        $this->success(['message' => 'Désinscription effectuée.']);
    }

    private function exportCsv(): void {
        Auth::requireApiToken();

        $subscribers = Database::getNewsletterSubscribers(true);

        $csv = "email,subscribed_at,source\n";
        foreach ($subscribers as $sub) {
            $csv .= "{$sub['email']},{$sub['subscribed_at']},{$sub['source']}\n";
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="newsletter_' . date('Y-m-d') . '.csv"');
        echo $csv;
        exit;
    }
}

// Exécution
$api = new NewsletterAPI();
$api->handle();
