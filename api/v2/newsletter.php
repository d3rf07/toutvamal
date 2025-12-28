<?php
/**
 * Newsletter API - ToutVaMal v4
 * Double opt-in - Style conforme au guide
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Email invalide']);
    exit;
}

$dbPath = '/home/u443792660/domains/toutvamal.fr/private_data/toutvamal.db';
$baseUrl = 'https://toutvamal.fr';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE TABLE IF NOT EXISTS newsletter (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT UNIQUE NOT NULL,
        confirmation_token TEXT,
        subscribed_at TEXT DEFAULT CURRENT_TIMESTAMP,
        confirmed_at TEXT,
        unsubscribed_at TEXT,
        source TEXT DEFAULT 'website'
    )");

    $stmt = $pdo->prepare("SELECT * FROM newsletter WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($existing['confirmed_at'] && !$existing['unsubscribed_at']) {
            echo json_encode([
                'success' => true,
                'message' => 'Vous faites déjà partie des déprimés. Pas de double dose.',
                'status' => 'already_confirmed'
            ]);
            exit;
        }
        if ($existing['unsubscribed_at']) {
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("UPDATE newsletter SET confirmation_token = ?, unsubscribed_at = NULL, confirmed_at = NULL, subscribed_at = datetime('now') WHERE email = ?");
            $stmt->execute([$token, $email]);
        } else {
            $token = $existing['confirmation_token'] ?: bin2hex(random_bytes(32));
            if (!$existing['confirmation_token']) {
                $stmt = $pdo->prepare("UPDATE newsletter SET confirmation_token = ? WHERE email = ?");
                $stmt->execute([$token, $email]);
            }
        }
    } else {
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("INSERT INTO newsletter (email, confirmation_token, source) VALUES (?, ?, ?)");
        $stmt->execute([$email, $token, 'website']);
    }

    $confirmUrl = $baseUrl . '/confirm.php?token=' . $token . '&email=' . urlencode($email);

    $subject = "=?UTF-8?B?" . base64_encode("Confirmez votre inscription - TOUTVAMAL") . "?=";

    // Email stylé selon STYLE_GUIDE.md - CTA en haut, pas de dégradé
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background-color:#0a0a0a;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#0a0a0a;">
        <tr>
            <td align="center" style="padding:30px 20px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

                    <!-- Header simple -->
                    <tr>
                        <td style="background-color:#0a0a0a;padding:30px;text-align:center;">
                            <h1 style="margin:0;font-family:Georgia,serif;font-size:32px;font-weight:900;color:#ffffff;">
                                TOUT<span style="color:#C41E3A;">VA</span>MAL
                            </h1>
                            <p style="margin:10px 0 0;color:#737373;font-size:13px;">
                                C'était mieux avant, et ce sera pire demain
                            </p>
                        </td>
                    </tr>

                    <!-- Intro + CTA immédiat -->
                    <tr>
                        <td style="background-color:#111111;padding:30px;text-align:center;">
                            <h2 style="margin:0 0 15px;color:#ffffff;font-size:20px;font-weight:600;">
                                Alors comme ça, vous voulez souffrir ?
                            </h2>
                            <p style="color:#9ca3af;font-size:15px;line-height:1.6;margin:0 0 25px;">
                                Quelqu'un utilisant cette adresse email (probablement vous, dans un moment de faiblesse existentielle) a demandé à rejoindre notre communauté de pessimistes éclairés.
                            </p>

                            <!-- CTA Button - visible immédiatement -->
                            <a href="{$confirmUrl}" style="display:inline-block;background-color:#C41E3A;color:#ffffff;padding:16px 32px;border-radius:8px;text-decoration:none;font-weight:600;font-size:15px;">
                                Oui, je veux déprimer chaque dimanche
                            </a>
                        </td>
                    </tr>

                    <!-- Détails -->
                    <tr>
                        <td style="background-color:#1a1a1a;padding:25px 30px;">
                            <h3 style="margin:0 0 15px;color:#ffffff;font-size:14px;text-transform:uppercase;letter-spacing:1px;">
                                Ce qui vous attend
                            </h3>
                            <p style="color:#9ca3af;font-size:14px;line-height:1.6;margin:0 0 10px;">
                                ✓ Chaque <strong style="color:#ffffff;">dimanche matin</strong>, votre dose de mauvaises nouvelles
                            </p>
                            <p style="color:#9ca3af;font-size:14px;line-height:1.6;margin:0 0 10px;">
                                ✓ Les 5 pires nouvelles de la semaine, décryptées avec cynisme
                            </p>
                            <p style="color:#9ca3af;font-size:14px;line-height:1.6;margin:0;">
                                ✓ Zéro spam, 100% désespoir
                            </p>
                        </td>
                    </tr>

                    <!-- Avertissement -->
                    <tr>
                        <td style="background-color:#111111;padding:20px 30px;border-left:3px solid #C41E3A;">
                            <p style="margin:0;color:#9ca3af;font-size:13px;line-height:1.5;">
                                <strong style="color:#C41E3A;">Effets secondaires possibles :</strong> cynisme aigu, regards désabusés, et une capacité troublante à dire "je vous l'avais dit".
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#0a0a0a;padding:25px 30px;text-align:center;">
                            <p style="margin:0 0 10px;color:#737373;font-size:12px;">
                                Si vous n'avez pas demandé cet email, ignorez-le.<br>
                                Vous ne recevrez rien d'autre. Comme le bonheur.
                            </p>
                            <p style="margin:0;color:#525252;font-size:11px;">
                                © 2025 TOUTVAMAL.FR
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: =?UTF-8?B?' . base64_encode('TOUTVAMAL') . '?= <noreply@toutvamal.fr>',
        'Reply-To: contact@toutvamal.fr',
        'X-Mailer: ToutVaMal/1.0'
    ];

    $sent = mail($email, $subject, $htmlBody, implode("\r\n", $headers));

    if ($sent) {
        echo json_encode([
            'success' => true,
            'message' => 'Email de confirmation envoyé. Vérifiez vos spams, on sait jamais.',
            'status' => 'confirmation_sent'
        ]);
    } else {
        throw new Exception('Échec envoi email');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur technique: ' . $e->getMessage()]);
}
