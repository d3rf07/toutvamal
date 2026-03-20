<?php
/**
 * ToutVaMal.fr - Script d'envoi de la newsletter "Le Bulletin du Malheur"
 *
 * Exécution : php cron/send-newsletter.php
 * Ou via cron : 0 9 * * 1 php /home/u443792660/domains/toutvamal.fr/public_html/cron/send-newsletter.php
 *
 * Ce script :
 *  1. Vérifie qu'un envoi n'a pas déjà eu lieu aujourd'hui (anti-doublon)
 *  2. Récupère les 3 derniers articles publiés depuis la DB SQLite
 *  3. Récupère tous les abonnés actifs (non-désinscrits)
 *  4. Génère le HTML avec le template newsletter.php
 *  5. Envoie à chaque abonné via mail() PHP natif
 *  6. Log l'envoi dans logs/newsletter.log
 *
 * NOTE : La table newsletter ne possède aucun confirmed_at rempli (confirmation
 * email non implémentée côté utilisateur). Le script envoie donc à tous les
 * abonnés dont unsubscribed_at IS NULL. Adapter la constante
 * NEWSLETTER_CONFIRMED_ONLY si la confirmation est activée ultérieurement.
 */

// ---- Environnement CLI uniquement ----
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Accès interdit. Ce script s\'exécute uniquement en ligne de commande.');
}

define('ROOT_PATH', dirname(__DIR__));
define('DATA_PATH',      ROOT_PATH . '/data');
define('LOGS_PATH',      ROOT_PATH . '/logs');
define('TEMPLATES_PATH', ROOT_PATH . '/templates');
define('DB_PATH',        DATA_PATH . '/toutvamal.db');

// ---- Constantes de configuration ----
define('SITE_URL',               'https://toutvamal.fr');
define('NEWSLETTER_FROM',        'ToutVaMal.fr <noreply@toutvamal.fr>');
define('NEWSLETTER_REPLY_TO',    'contact@toutvamal.fr');
define('NEWSLETTER_SUBJECT',     'Le Bulletin du Malheur — ' . date('d/m/Y'));
define('NEWSLETTER_LOG_FILE',    LOGS_PATH . '/newsletter.log');
define('NEWSLETTER_LOCK_FILE',   LOGS_PATH . '/newsletter-today.lock');
define('NEWSLETTER_CONFIRMED_ONLY', false); // Passer à true si confirmation email activée

// ---- Mode test : passer --dry-run pour simuler sans envoyer ----
$dryRun = in_array('--dry-run', $argv ?? [], true);

// ---- Fonctions utilitaires ----

function nl_log(string $level, string $message): void {
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] [$level] $message\n";
    echo $line;
    file_put_contents(NEWSLETTER_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

function nl_exit(string $message, int $code = 1): void {
    nl_log('ERROR', $message);
    exit($code);
}

/**
 * Valide et retourne une string propre depuis une valeur backend potentiellement mixte.
 * Applique le pattern defensive programming obligatoire.
 */
function nl_safe_field($value, string $fieldName, string $fallback = ''): string {
    if (is_string($value)) return $value;
    if (is_int($value) || is_float($value)) return (string)$value;
    if ($value === null) return $fallback;
    if (is_array($value)) {
        nl_log('WARN', "Champ $fieldName inattendu (array) : " . json_encode($value));
        // Essaye d'extraire une valeur utile
        return $value['name'] ?? $value['label'] ?? $value['value'] ?? $fallback;
    }
    nl_log('WARN', "Champ $fieldName type inattendu : " . gettype($value));
    return $fallback;
}

/**
 * Ouvre la connexion PDO à la DB SQLite
 */
function nl_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (!file_exists(DB_PATH)) {
            nl_exit('Base de données introuvable : ' . DB_PATH);
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

/**
 * Vérifie si un envoi a déjà été effectué aujourd'hui
 */
function nl_already_sent_today(): bool {
    if (!file_exists(NEWSLETTER_LOCK_FILE)) {
        return false;
    }
    $lockDate = trim(file_get_contents(NEWSLETTER_LOCK_FILE));
    return $lockDate === date('Y-m-d');
}

/**
 * Marque l'envoi comme effectué aujourd'hui
 */
function nl_mark_sent_today(): void {
    file_put_contents(NEWSLETTER_LOCK_FILE, date('Y-m-d'), LOCK_EX);
}

/**
 * Récupère les 3 derniers articles publiés
 */
function nl_get_articles(): array {
    $db = nl_db();
    $stmt = $db->prepare("
        SELECT
            a.id,
            a.slug,
            a.title,
            a.excerpt,
            a.image_path,
            a.published_at,
            a.category,
            j.name AS journalist_name
        FROM articles a
        LEFT JOIN journalists j ON a.journalist_id = j.id
        WHERE a.status = 'published'
        ORDER BY a.published_at DESC
        LIMIT 3
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();

    if (!is_array($rows)) {
        nl_log('WARN', 'nl_get_articles() : résultat non-array, retourne []');
        return [];
    }

    // Validation défensive de chaque article
    $validated = [];
    foreach ($rows as $i => $row) {
        if (!is_array($row)) {
            nl_log('WARN', "article[$i] non-array, ignoré");
            continue;
        }
        $validated[] = [
            'id'              => (int)($row['id'] ?? 0),
            'slug'            => nl_safe_field($row['slug']            ?? '', "slug[$i]"),
            'title'           => nl_safe_field($row['title']           ?? '', "title[$i]",     'Article sans titre'),
            'excerpt'         => nl_safe_field($row['excerpt']         ?? '', "excerpt[$i]",   ''),
            'image_path'      => nl_safe_field($row['image_path']      ?? '', "image_path[$i]",''),
            'published_at'    => nl_safe_field($row['published_at']    ?? '', "published_at[$i]",''),
            'category'        => nl_safe_field($row['category']        ?? '', "category[$i]",  ''),
            'journalist_name' => nl_safe_field($row['journalist_name'] ?? '', "journalist_name[$i]", 'La Rédaction'),
        ];
    }

    return $validated;
}

/**
 * Récupère tous les abonnés actifs (non-désinscrits)
 * Si NEWSLETTER_CONFIRMED_ONLY = true, filtre sur confirmed_at IS NOT NULL
 */
function nl_get_subscribers(): array {
    $db = nl_db();

    if (NEWSLETTER_CONFIRMED_ONLY) {
        $stmt = $db->query("
            SELECT id, email, confirmation_token
            FROM newsletter
            WHERE unsubscribed_at IS NULL
              AND confirmed_at IS NOT NULL
            ORDER BY subscribed_at ASC
        ");
    } else {
        $stmt = $db->query("
            SELECT id, email, confirmation_token
            FROM newsletter
            WHERE unsubscribed_at IS NULL
            ORDER BY subscribed_at ASC
        ");
    }

    $rows = $stmt->fetchAll();

    if (!is_array($rows)) {
        nl_log('WARN', 'nl_get_subscribers() : résultat non-array');
        return [];
    }

    $validated = [];
    foreach ($rows as $i => $row) {
        if (!is_array($row)) {
            nl_log('WARN', "subscriber[$i] non-array, ignoré");
            continue;
        }
        $email = nl_safe_field($row['email'] ?? '', "email[$i]");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            nl_log('WARN', "email[$i] invalide : \"$email\", ignoré");
            continue;
        }
        $validated[] = [
            'id'    => (int)($row['id'] ?? 0),
            'email' => $email,
            'token' => nl_safe_field($row['confirmation_token'] ?? '', "token[$i]"),
        ];
    }

    return $validated;
}

/**
 * Génère le HTML de la newsletter pour un abonné donné
 */
function nl_generate_html(array $articles, string $unsubscribeUrl, int $barometre): string {
    // Calcule la date d'envoi
    $months = ['janvier','février','mars','avril','mai','juin',
               'juillet','août','septembre','octobre','novembre','décembre'];
    $sendDate = date('j') . ' ' . $months[(int)date('n') - 1] . ' ' . date('Y');

    // Rend les variables disponibles pour le template
    ob_start();
    require TEMPLATES_PATH . '/newsletter.php';
    $html = ob_get_clean();

    if (!is_string($html) || trim($html) === '') {
        nl_log('WARN', 'Template newsletter.php a produit un output vide');
        return '';
    }

    return $html;
}

/**
 * Envoie un email HTML à un destinataire
 * Retourne true en succès, false en échec
 */
function nl_send_email(string $to, string $subject, string $htmlBody): bool {
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . NEWSLETTER_FROM,
        'Reply-To: ' . NEWSLETTER_REPLY_TO,
        'X-Mailer: ToutVaMal Newsletter',
        'X-Priority: 3',
        'Precedence: bulk',
        'List-Unsubscribe: <' . SITE_URL . '/api/newsletter-unsubscribe.php>',
    ]);

    // Nettoyage du sujet pour éviter l'injection d'en-têtes
    $subject = str_replace(["\r", "\n"], '', $subject);

    return mail($to, $subject, $htmlBody, $headers);
}

/**
 * Calcule une valeur de baromètre pseudo-aléatoire mais stable pour la journée
 */
function nl_compute_barometre(): int {
    // Basé sur la date du jour pour que le baromètre soit le même toute la journée
    $seed = (int)date('Ymd');
    srand($seed);
    return rand(45, 89); // Toujours "préoccupant" à "catastrophique"
}

// ========== SCRIPT PRINCIPAL ==========

nl_log('INFO', '=== Démarrage envoi newsletter ===');
nl_log('INFO', 'Mode : ' . ($dryRun ? 'DRY-RUN (simulation)' : 'PRODUCTION'));

// Vérification anti-doublon
if (!$dryRun && nl_already_sent_today()) {
    nl_log('INFO', 'Newsletter déjà envoyée aujourd\'hui (' . date('Y-m-d') . '). Abandon.');
    exit(0);
}

// Récupération des articles
$articles = nl_get_articles();
if (empty($articles)) {
    nl_exit('Aucun article publié trouvé. Envoi annulé.');
}
nl_log('INFO', count($articles) . ' article(s) récupéré(s) : ' . implode(', ', array_column($articles, 'title')));

// Récupération des abonnés
$subscribers = nl_get_subscribers();
if (empty($subscribers)) {
    nl_log('INFO', 'Aucun abonné actif. Envoi annulé.');
    exit(0);
}
nl_log('INFO', count($subscribers) . ' abonné(s) actif(s) trouvé(s).');

// Valeur du baromètre du jour
$barometre = nl_compute_barometre();
nl_log('INFO', "Barometre du jour : $barometre%");

// Compteurs
$sent    = 0;
$errors  = 0;
$skipped = 0;

// Envoi à chaque abonné
foreach ($subscribers as $subscriber) {
    $email = $subscriber['email'];
    $token = $subscriber['token'];
    $id    = $subscriber['id'];

    // Construit l'URL de désinscription personnalisée
    if (!empty($token)) {
        $unsubscribeUrl = SITE_URL . '/api/newsletter-unsubscribe.php?token=' . urlencode($token);
    } else {
        // Fallback : désinscription par email
        $unsubscribeUrl = SITE_URL . '/api/newsletter-unsubscribe.php?email=' . urlencode($email);
    }

    // Génère le HTML personnalisé
    $html = nl_generate_html($articles, $unsubscribeUrl, $barometre);
    if (empty($html)) {
        nl_log('WARN', "HTML vide pour $email, ignoré");
        $skipped++;
        continue;
    }

    if ($dryRun) {
        nl_log('INFO', "[DRY-RUN] Simulerait envoi à : $email (token: " . substr($token, 0, 8) . "...)");
        $sent++;
        continue;
    }

    // Envoi réel
    $ok = nl_send_email($email, NEWSLETTER_SUBJECT, $html);
    if ($ok) {
        nl_log('INFO', "Envoyé à : $email");
        $sent++;
    } else {
        nl_log('ERROR', "Échec envoi à : $email");
        $errors++;
    }

    // Pause légère pour éviter la surcharge SMTP (100ms)
    usleep(100000);
}

// ---- Résumé ----
nl_log('INFO', "=== Résumé : $sent envoyé(s), $errors erreur(s), $skipped ignoré(s) ===");

// Marque l'envoi comme fait (sauf en dry-run)
if (!$dryRun && $sent > 0) {
    nl_mark_sent_today();
    nl_log('INFO', 'Fichier lock créé pour aujourd\'hui : ' . NEWSLETTER_LOCK_FILE);
}

nl_log('INFO', '=== Fin du script newsletter ===');
exit(0);
