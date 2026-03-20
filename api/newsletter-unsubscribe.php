<?php
/**
 * ToutVaMal.fr - Désinscription newsletter
 *
 * Accepte en GET :
 *   ?token=<confirmation_token>  — désinscription par token (recommandé)
 *   ?email=<email>               — désinscription par email (fallback)
 *
 * Répond avec une page HTML de confirmation dans le ton du site.
 * Ne renvoie jamais d'erreur visible côté utilisateur (privacy-by-default).
 */

define('ROOT_PATH', dirname(__DIR__));
define('DATA_PATH', ROOT_PATH . '/data');
define('LOGS_PATH', ROOT_PATH . '/logs');
define('DB_PATH',   DATA_PATH . '/toutvamal.db');
define('SITE_URL',  'https://toutvamal.fr');

// ---- Fonctions utilitaires ----

function unsub_log(string $level, string $message): void {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = LOGS_PATH . '/newsletter.log';
    if (is_writable(dirname($logFile)) || is_writable($logFile)) {
        file_put_contents($logFile, "[$timestamp] [$level] $message\n", FILE_APPEND | LOCK_EX);
    }
}

/**
 * Valide une donnée entrante depuis $_GET — defensive programming obligatoire
 */
function unsub_safe_get(string $key): string {
    $value = $_GET[$key] ?? '';
    if (!is_string($value)) {
        unsub_log('WARN', "GET[$key] inattendu : " . gettype($value));
        return '';
    }
    return trim($value);
}

/**
 * Connexion PDO SQLite
 */
function unsub_db(): ?PDO {
    if (!file_exists(DB_PATH)) {
        unsub_log('ERROR', 'Base de données introuvable : ' . DB_PATH);
        return null;
    }
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        unsub_log('ERROR', 'Connexion DB échouée : ' . $e->getMessage());
        return null;
    }
}

/**
 * Désinscrit un abonné par token
 * Retourne true si trouvé et désinscrit, false sinon
 */
function unsub_by_token(PDO $db, string $token): bool {
    // Validation : token = 64 chars hex
    if (!preg_match('/^[a-fA-F0-9]{64}$/', $token)) {
        unsub_log('WARN', 'Token format invalide : ' . substr($token, 0, 10) . '...');
        return false;
    }

    try {
        // Vérifie que le token existe et n'est pas déjà désinscrit
        $stmt = $db->prepare("SELECT id, email, unsubscribed_at FROM newsletter WHERE confirmation_token = :token");
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();

        if (!$row || !is_array($row)) {
            unsub_log('WARN', 'Token introuvable : ' . substr($token, 0, 10) . '...');
            return false;
        }

        if (!empty($row['unsubscribed_at'])) {
            // Déjà désinscrit : on retourne true (idempotent, privacy-friendly)
            unsub_log('INFO', 'Déjà désinscrit (token) : ' . $row['email']);
            return true;
        }

        $stmt = $db->prepare("UPDATE newsletter SET unsubscribed_at = datetime('now') WHERE confirmation_token = :token");
        $stmt->execute([':token' => $token]);

        unsub_log('INFO', 'Désinscrit via token : ' . $row['email']);
        return true;
    } catch (PDOException $e) {
        unsub_log('ERROR', 'Erreur DB désinscription token : ' . $e->getMessage());
        return false;
    }
}

/**
 * Désinscrit un abonné par email
 * Retourne true même si non trouvé (privacy-by-default)
 */
function unsub_by_email(PDO $db, string $email): bool {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        unsub_log('WARN', 'Email invalide pour désinscription : ' . $email);
        return false;
    }

    try {
        $stmt = $db->prepare("SELECT id, unsubscribed_at FROM newsletter WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();

        if (!$row || !is_array($row)) {
            // Email non trouvé : retourne true quand même (privacy)
            unsub_log('INFO', 'Désinscription email non trouvé (privacy) : ' . $email);
            return true;
        }

        if (!empty($row['unsubscribed_at'])) {
            unsub_log('INFO', 'Déjà désinscrit (email) : ' . $email);
            return true;
        }

        $stmt = $db->prepare("UPDATE newsletter SET unsubscribed_at = datetime('now') WHERE email = :email");
        $stmt->execute([':email' => $email]);

        unsub_log('INFO', 'Désinscrit via email : ' . $email);
        return true;
    } catch (PDOException $e) {
        unsub_log('ERROR', 'Erreur DB désinscription email : ' . $e->getMessage());
        return false;
    }
}

// ---- Traitement de la requête ----

$token = unsub_safe_get('token');
$email = unsub_safe_get('email');

// Méthode de désinscription et résultat
$unsubscribed = false;
$method = 'aucune';

if (!empty($token) || !empty($email)) {
    $db = unsub_db();
    if ($db !== null) {
        if (!empty($token)) {
            $unsubscribed = unsub_by_token($db, $token);
            $method = 'token';
        } elseif (!empty($email)) {
            $unsubscribed = unsub_by_email($db, $email);
            $method = 'email';
        }
    }
}

// Privacy-by-default : on affiche toujours la page de confirmation
// même si le token/email n'existe pas, pour ne pas permettre l'énumération
$showSuccess = true;

// ---- Rendu HTML ----
header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>D&eacute;sinscription &mdash; ToutVaMal.fr</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      background-color: #0a0a0a;
      color: #e0e0e0;
      font-family: 'Arial', Helvetica, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
    }

    .container {
      max-width: 560px;
      width: 100%;
      text-align: center;
    }

    .logo {
      font-family: Georgia, 'Times New Roman', Times, serif;
      font-size: 22px;
      font-weight: 700;
      color: #ffffff;
      text-decoration: none;
      letter-spacing: 1px;
      display: block;
      margin-bottom: 40px;
    }

    .logo span {
      color: #C41E3A;
    }

    .card {
      background-color: #111111;
      border: 1px solid #2a2a2a;
      border-top: 3px solid #C41E3A;
      padding: 40px 32px;
    }

    .icon {
      width: 56px;
      height: 56px;
      background-color: #1a1a1a;
      border: 1px solid #2a2a2a;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 24px;
      font-size: 24px;
    }

    h1 {
      font-family: Georgia, 'Times New Roman', Times, serif;
      font-size: 24px;
      font-weight: 700;
      color: #ffffff;
      margin-bottom: 16px;
      line-height: 1.3;
    }

    .message {
      font-size: 15px;
      color: #c0c0c0;
      line-height: 1.6;
      margin-bottom: 20px;
    }

    .quote {
      font-family: Georgia, 'Times New Roman', Times, serif;
      font-size: 14px;
      font-style: italic;
      color: #666666;
      border-left: 3px solid #C41E3A;
      padding-left: 16px;
      text-align: left;
      margin: 24px 0;
    }

    .btn-back {
      display: inline-block;
      padding: 12px 24px;
      background-color: #C41E3A;
      color: #ffffff;
      text-decoration: none;
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.5px;
      border: none;
      cursor: pointer;
      margin-top: 8px;
    }

    .btn-back:hover {
      background-color: #a01830;
    }

    .footnote {
      margin-top: 32px;
      font-size: 12px;
      color: #444444;
      line-height: 1.5;
    }

    .footnote a {
      color: #666666;
      text-decoration: underline;
    }

    @media (max-width: 480px) {
      .card { padding: 28px 20px; }
      h1 { font-size: 20px; }
    }

    @media (prefers-reduced-motion: no-preference) {
      .card {
        animation: fadeIn 0.4s ease-out;
      }
      @keyframes fadeIn {
        from { opacity: 0; transform: translateY(12px); }
        to   { opacity: 1; transform: translateY(0); }
      }
    }
  </style>
</head>
<body>

  <div class="container">

    <a href="<?php echo SITE_URL; ?>" class="logo">
      TOUTVAMAL<span>.FR</span>
    </a>

    <div class="card">

      <div class="icon" aria-hidden="true">&#x2713;</div>

      <?php if ($showSuccess): ?>

        <h1>Vous &ecirc;tes d&eacute;sinscrit(e).</h1>

        <p class="message">
          Vous quittez le malheur. Nous comprenons.<br />
          Tout va mal de toute fa&ccedil;on.
        </p>

        <blockquote class="quote">
          &ldquo;Le bonheur, c&rsquo;est de ne pas savoir ce qu&rsquo;on rate.
          Vous avez fait le bon choix. Probablement.&rdquo;
        </blockquote>

        <p class="message" style="font-size:13px;color:#888888;">
          Votre adresse email a &eacute;t&eacute; retir&eacute;e de notre liste de distribution.
          Vous ne recevrez plus le Bulletin du Malheur.
        </p>

        <a href="<?php echo SITE_URL; ?>" class="btn-back">
          Retourner contempler le d&eacute;sastre &rarr;
        </a>

      <?php else: ?>

        <h1>Param&egrave;tre manquant.</h1>

        <p class="message">
          Nous ne savons pas qui vous &ecirc;tes. C&rsquo;est troublant.<br />
          Utilisez le lien de d&eacute;sinscription contenu dans votre email.
        </p>

        <a href="<?php echo SITE_URL; ?>" class="btn-back">
          Retourner sur le site &rarr;
        </a>

      <?php endif; ?>

    </div>

    <p class="footnote">
      Une erreur ? Contactez-nous :
      <a href="mailto:contact@toutvamal.fr">contact@toutvamal.fr</a><br />
      ToutVaMal.fr &mdash; Toute la d&eacute;prime du monde, r&eacute;sum&eacute;e pour vous.
    </p>

  </div>

</body>
</html>
