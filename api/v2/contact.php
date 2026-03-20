<?php
/**
 * ToutVaMal.fr - Contact API
 * Reçoit les soumissions du formulaire contact et envoie un email.
 *
 * Format POST attendu (form standard ou JSON) :
 *   name    : string (requis)
 *   email   : string email (requis)
 *   subject : string (requis)
 *   message : string (requis)
 */

require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

// Support form-data et JSON
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
} else {
    $input = $_POST;
}

// Validation des champs
$name    = trim($input['name'] ?? '');
$email   = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$subject = trim($input['subject'] ?? '');
$message = trim($input['message'] ?? '');

if (!$name || strlen($name) < 2) {
    json_response(['error' => 'Nom invalide'], 400);
}
if (!$email) {
    json_response(['error' => 'Email invalide'], 400);
}
if (!$subject) {
    json_response(['error' => 'Sujet manquant'], 400);
}
if (!$message || strlen($message) < 10) {
    json_response(['error' => 'Message trop court'], 400);
}

// Rate limiting basique : max 3 envois par IP par heure via fichier lock
$rateLimitFile = sys_get_temp_dir() . '/tvm_contact_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$rateLimitData = [];
if (file_exists($rateLimitFile)) {
    $rateLimitData = json_decode(file_get_contents($rateLimitFile), true) ?? [];
}
// Nettoyer les entrées > 1 heure
$rateLimitData = array_filter($rateLimitData, fn($t) => $t > time() - 3600);
if (count($rateLimitData) >= 3) {
    json_response(['error' => 'Trop de messages envoyés. Attendez un peu.'], 429);
}
$rateLimitData[] = time();
file_put_contents($rateLimitFile, json_encode(array_values($rateLimitData)));

// Sujets lisibles
$subjectLabels = [
    'general'      => 'Question générale',
    'correction'   => 'Signalement d\'erreur',
    'partenariat'  => 'Proposition de partenariat',
    'presse'       => 'Demande presse',
    'technique'    => 'Problème technique',
    'droit-reponse'=> 'Droit de réponse',
    'autre'        => 'Autre récrimination',
];
$subjectLabel = $subjectLabels[$subject] ?? htmlspecialchars($subject);

// Construction de l'email
$to      = 'contact@toutvamal.fr';
$mailSubject = "[ToutVaMal.fr Contact] $subjectLabel";
$body  = "Nouveau message depuis le formulaire de contact.\n\n";
$body .= "Nom    : " . $name . "\n";
$body .= "Email  : " . $email . "\n";
$body .= "Sujet  : " . $subjectLabel . "\n";
$body .= "IP     : " . ($_SERVER['REMOTE_ADDR'] ?? 'inconnue') . "\n";
$body .= "Date   : " . date('d/m/Y H:i:s') . "\n\n";
$body .= "---\n\n";
$body .= $message . "\n";

$headers  = "From: noreply@toutvamal.fr\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "X-Mailer: ToutVaMal/1.0\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

try {
    $sent = mail($to, $mailSubject, $body, $headers);
    if ($sent) {
        log_info("Contact form submitted by $email (subject: $subject)");
        json_response(['success' => true, 'message' => 'Message envoyé']);
    } else {
        log_error("Contact form mail() failed for $email");
        json_response(['error' => 'Échec envoi email'], 500);
    }
} catch (Throwable $e) {
    log_error("Contact form exception: " . $e->getMessage());
    json_response(['error' => 'Erreur serveur'], 500);
}
