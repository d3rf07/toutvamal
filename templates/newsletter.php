<?php
/**
 * ToutVaMal.fr - Template Newsletter "Le Bulletin du Malheur"
 * Email HTML compatible tous clients (tables, CSS inline, pas de flexbox/grid)
 * Largeur max 600px, responsive
 *
 * Usage : require ce fichier après avoir défini $articles (array) et $unsubscribeUrl (string)
 * Variables attendues :
 *   $articles       : array de 3 articles (chacun : title, excerpt, slug, image_path, journalist_name, published_at)
 *   $unsubscribeUrl : string — URL de désinscription personnalisée
 *   $sendDate       : string (optionnel) — date d'envoi formatée
 *   $barometre      : int (optionnel) — valeur du baromètre 0-100, défaut 67
 */

if (!defined('NEWSLETTER_TEMPLATE_LOADED')) {
    define('NEWSLETTER_TEMPLATE_LOADED', true);
}

// ---- Données par défaut si appelé directement ----
if (!isset($articles)) {
    $articles = [];
}
if (!isset($unsubscribeUrl)) {
    $unsubscribeUrl = 'https://toutvamal.fr/api/newsletter-unsubscribe.php?token=DEMO';
}
if (!isset($sendDate)) {
    $months = ['janvier','février','mars','avril','mai','juin',
               'juillet','août','septembre','octobre','novembre','décembre'];
    $day = date('j');
    $month = $months[(int)date('n') - 1];
    $year = date('Y');
    $sendDate = "$day $month $year";
}
if (!isset($barometre)) {
    $barometre = 67;
}

// ---- Validation défensive des articles ----
if (!is_array($articles)) {
    error_log('[newsletter] $articles attendu array, reçu : ' . gettype($articles));
    $articles = [];
}

/**
 * Extrait une string propre depuis une valeur potentiellement mixte
 * Protégée par function_exists pour permettre require multiple (boucle d'envoi)
 */
if (!function_exists('nl_safe_string')) {
    function nl_safe_string($value, string $fallback = ''): string {
        if (is_string($value)) return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        if (is_int($value) || is_float($value)) return (string)$value;
        if (is_array($value)) {
            error_log('[newsletter] valeur inattendue (array) : ' . json_encode($value));
            return $fallback;
        }
        if ($value === null) return $fallback;
        error_log('[newsletter] type inattendu : ' . gettype($value));
        return $fallback;
    }
}

/**
 * Construit l'URL absolue d'une image article
 */
if (!function_exists('nl_image_url')) {
    function nl_image_url($image_path): string {
        if (!is_string($image_path) || trim($image_path) === '') {
            return 'https://toutvamal.fr/images/og-default.jpg';
        }
        // Déjà une URL absolue
        if (strpos($image_path, 'http') === 0) {
            return $image_path;
        }
        // Chemin relatif commençant par /
        return 'https://toutvamal.fr' . ltrim($image_path, '/');
    }
}

/**
 * Construit le bloc HTML d'un article pour la newsletter
 */
if (!function_exists('nl_article_block')) {
function nl_article_block(array $article, int $index): string {
    // Validation de chaque champ
    foreach (['title', 'excerpt', 'slug'] as $field) {
        if (!isset($article[$field]) || !is_string($article[$field])) {
            error_log("[newsletter] article[$index].$field manquant ou non-string");
        }
    }

    $title     = nl_safe_string($article['title'] ?? '', 'Article sans titre');
    $excerpt   = nl_safe_string($article['excerpt'] ?? '', 'Aucun extrait disponible.');
    $slug      = is_string($article['slug'] ?? null) ? $article['slug'] : '';
    $journalist = nl_safe_string($article['journalist_name'] ?? '', 'La Rédaction');
    $imageUrl  = nl_image_url($article['image_path'] ?? '');
    $articleUrl = $slug ? 'https://toutvamal.fr/articles/' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '.html' : 'https://toutvamal.fr';

    // Tronquer l'extrait à 160 caractères max
    $excerptRaw = html_entity_decode($excerpt, ENT_QUOTES, 'UTF-8');
    if (mb_strlen($excerptRaw) > 160) {
        $excerptRaw = mb_substr($excerptRaw, 0, 157) . '...';
    }
    $excerptDisplay = htmlspecialchars($excerptRaw, ENT_QUOTES, 'UTF-8');

    // Séparateur entre articles (sauf le premier)
    $separator = $index > 0
        ? '<tr><td style="padding:0;"><table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"><tr><td style="padding:0 24px;"><div style="border-top:1px solid #2a2a2a;font-size:0;line-height:0;">&nbsp;</div></td></tr></table></td></tr>'
        : '';

    return $separator . '
    <tr>
      <td style="padding:0;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
          <!-- Image article -->
          <tr>
            <td style="padding:24px 24px 0 24px;">
              <a href="' . $articleUrl . '" style="display:block;text-decoration:none;" target="_blank">
                <img src="' . $imageUrl . '"
                     alt="' . $title . '"
                     width="552"
                     style="display:block;width:100%;max-width:552px;height:auto;border-radius:4px;border:0;"
                     loading="eager" />
              </a>
            </td>
          </tr>
          <!-- Titre -->
          <tr>
            <td style="padding:16px 24px 6px 24px;">
              <a href="' . $articleUrl . '" style="text-decoration:none;" target="_blank">
                <h2 style="margin:0;padding:0;font-family:Georgia,\'Times New Roman\',Times,serif;font-size:20px;font-weight:700;line-height:1.3;color:#e0e0e0;">' . $title . '</h2>
              </a>
            </td>
          </tr>
          <!-- Journaliste -->
          <tr>
            <td style="padding:0 24px 10px 24px;">
              <p style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#888888;line-height:1.4;">Par ' . $journalist . '</p>
            </td>
          </tr>
          <!-- Extrait -->
          <tr>
            <td style="padding:0 24px 16px 24px;">
              <p style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#c0c0c0;">' . $excerptDisplay . '</p>
            </td>
          </tr>
          <!-- CTA -->
          <tr>
            <td style="padding:0 24px 24px 24px;">
              <table cellpadding="0" cellspacing="0" border="0" role="presentation">
                <tr>
                  <td style="background-color:#C41E3A;border-radius:3px;">
                    <a href="' . $articleUrl . '"
                       target="_blank"
                       style="display:inline-block;padding:10px 20px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#ffffff;text-decoration:none;letter-spacing:0.5px;white-space:nowrap;">
                      Lire la catastrophe &rarr;
                    </a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </td>
    </tr>';
}
} // end function_exists('nl_article_block')

// ---- Calcul du baromètre ----
$barometreVal   = max(0, min(100, (int)$barometre));
$barometreBlocs = round($barometreVal / 6.25); // 16 blocs au total
$barometreVide  = 16 - $barometreBlocs;

if ($barometreVal < 30) {
    $barometreLabel = 'CALME SUSPECT';
    $barometreColor = '#888888';
} elseif ($barometreVal < 50) {
    $barometreLabel = 'TENDU';
    $barometreColor = '#cc8800';
} elseif ($barometreVal < 75) {
    $barometreLabel = 'PREOCCUPANT';
    $barometreColor = '#C41E3A';
} else {
    $barometreLabel = 'CATASTROPHIQUE';
    $barometreColor = '#8b0000';
}

$barometreBarre = str_repeat('&#9608;', (int)$barometreBlocs) . str_repeat('&#9617;', (int)$barometreVide);

// ---- Citations du malheur ----
$citations = [
    ['texte' => 'Le pessimiste voit la difficulté dans chaque opportunité. L\'optimiste est un imbécile heureux.', 'auteur' => 'Oscar Wilde (version révisée)'],
    ['texte' => 'L\'avenir, c\'est du passé en préparation.', 'auteur' => 'Pierre Dac'],
    ['texte' => 'Tout ce qui peut mal tourner tournera mal.', 'auteur' => 'Murphy'],
    ['texte' => 'Il faut toujours viser la lune, car même en cas d\'échec, on atterrit dans le vide intersidéral.', 'auteur' => 'Rédaction ToutVaMal'],
    ['texte' => 'La catastrophe est la mère de l\'invention, mais elle est aussi stérile.', 'auteur' => 'Rédaction ToutVaMal'],
    ['texte' => 'Chaque matin qui se lève est une nouvelle occasion pour que les choses empirent.', 'auteur' => 'Rédaction ToutVaMal'],
];
$citation = $citations[array_rand($citations)];

?><!DOCTYPE html>
<html lang="fr" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="x-apple-disable-message-reformatting" />
  <meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no" />
  <!--[if mso]>
  <noscript>
    <xml>
      <o:OfficeDocumentSettings>
        <o:PixelsPerInch>96</o:PixelsPerInch>
      </o:OfficeDocumentSettings>
    </xml>
  </noscript>
  <![endif]-->
  <title>Le Bulletin du Malheur — ToutVaMal.fr</title>
  <style type="text/css">
    /* Resets clients email */
    body { margin:0 !important; padding:0 !important; width:100% !important; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
    table { border-collapse:collapse !important; }
    img { border:0; height:auto; line-height:100%; outline:none; text-decoration:none; -ms-interpolation-mode:bicubic; }
    a { text-decoration:none; }
    /* Outlook */
    .ReadMsgBody { width:100%; }
    .ExternalClass { width:100%; }
    .ExternalClass, .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td, .ExternalClass div { line-height:100%; }
    /* Responsive */
    @media only screen and (max-width: 620px) {
      .email-wrapper { width:100% !important; }
      .email-content { padding:0 !important; }
      .article-image { width:100% !important; }
    }
  </style>
</head>
<body style="margin:0;padding:0;background-color:#1a1a1a;font-family:Arial,Helvetica,sans-serif;">

  <!-- Prévisualisation cachée (snippet email) -->
  <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">
    Tout va mal. Comme d'habitude. Voici votre dose hebdomadaire de réalité.
    &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
    &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
  </div>

  <!-- Wrapper principal -->
  <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"
         style="background-color:#1a1a1a;margin:0;padding:0;">
    <tr>
      <td align="center" style="padding:20px 10px;">

        <!-- Conteneur email 600px -->
        <table class="email-wrapper" width="600" cellpadding="0" cellspacing="0" border="0" role="presentation"
               style="max-width:600px;width:100%;background-color:#0a0a0a;border:1px solid #2a2a2a;">

          <!-- ===== EN-TÊTE ===== -->
          <tr>
            <td style="padding:0;background-color:#0d0d0d;border-bottom:3px solid #C41E3A;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
                <tr>
                  <td style="padding:28px 24px 20px 24px;">
                    <!-- Logo / Titre -->
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
                      <tr>
                        <td>
                          <a href="https://toutvamal.fr" target="_blank" style="text-decoration:none;">
                            <p style="margin:0;padding:0;font-family:Georgia,'Times New Roman',Times,serif;font-size:28px;font-weight:700;color:#ffffff;letter-spacing:1px;line-height:1.1;">
                              TOUTVAMAL<span style="color:#C41E3A;">.FR</span>
                            </p>
                          </a>
                        </td>
                        <td align="right" valign="middle">
                          <a href="https://toutvamal.fr" target="_blank" style="text-decoration:none;">
                            <img src="https://toutvamal.fr/images/toutvamal-logo-small.png"
                                 alt="ToutVaMal.fr"
                                 width="48"
                                 height="48"
                                 style="display:block;border:0;width:48px;height:48px;" />
                          </a>
                        </td>
                      </tr>
                    </table>
                    <!-- Sous-titre -->
                    <p style="margin:6px 0 0 0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#888888;letter-spacing:2px;text-transform:uppercase;">
                      Le Bulletin du Malheur
                    </p>
                    <!-- Date -->
                    <p style="margin:4px 0 0 0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#666666;">
                      <?php echo htmlspecialchars($sendDate, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- ===== CITATION DU JOUR ===== -->
          <tr>
            <td style="padding:0;background-color:#111111;border-bottom:1px solid #2a2a2a;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
                <tr>
                  <td style="padding:20px 24px;">
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
                      <tr>
                        <td width="4" style="background-color:#C41E3A;font-size:0;line-height:0;">&nbsp;</td>
                        <td style="padding-left:14px;">
                          <p style="margin:0;padding:0;font-family:Georgia,'Times New Roman',Times,serif;font-size:15px;font-style:italic;color:#c0c0c0;line-height:1.5;">
                            &ldquo;<?php echo nl_safe_string($citation['texte']); ?>&rdquo;
                          </p>
                          <p style="margin:6px 0 0 0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#888888;">
                            &mdash; <?php echo nl_safe_string($citation['auteur']); ?>
                          </p>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- ===== ARTICLES ===== -->
          <?php if (!empty($articles) && is_array($articles)): ?>
            <?php
            $validArticles = array_filter($articles, function($a) { return is_array($a); });
            $validArticles = array_values(array_slice($validArticles, 0, 3));
            $articleCount = count($validArticles);
            if ($articleCount === 0) {
                error_log('[newsletter] aucun article valide à afficher');
            }
            ?>
            <?php foreach ($validArticles as $i => $article): ?>
              <?php echo nl_article_block($article, $i); ?>
            <?php endforeach; ?>
          <?php else: ?>
          <tr>
            <td style="padding:32px 24px;text-align:center;">
              <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#666666;">
                Aucune catastrophe disponible. Ce calme est suspect.
              </p>
            </td>
          </tr>
          <?php endif; ?>

          <!-- ===== BAROMETRE DU MALHEUR ===== -->
          <tr>
            <td style="padding:0;border-top:1px solid #2a2a2a;border-bottom:1px solid #2a2a2a;background-color:#0d0d0d;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
                <tr>
                  <td style="padding:20px 24px;">
                    <p style="margin:0 0 8px 0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#888888;letter-spacing:2px;text-transform:uppercase;">
                      Barometre du Malheur
                    </p>
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
                      <tr>
                        <td style="width:auto;padding:0 12px 0 0;">
                          <p style="margin:0;padding:0;font-family:'Courier New',Courier,monospace;font-size:16px;line-height:1;color:<?php echo $barometreColor; ?>;">
                            <?php echo $barometreBarre; ?>
                          </p>
                        </td>
                        <td style="width:1%;white-space:nowrap;vertical-align:middle;">
                          <p style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:<?php echo $barometreColor; ?>;letter-spacing:1px;">
                            <?php echo $barometreVal; ?>% &mdash; <?php echo htmlspecialchars($barometreLabel, ENT_QUOTES, 'UTF-8'); ?>
                          </p>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- ===== FOOTER ===== -->
          <tr>
            <td style="padding:0;background-color:#0d0d0d;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
                <tr>
                  <td style="padding:24px 24px 8px 24px;text-align:center;">
                    <p style="margin:0;padding:0;font-family:Georgia,'Times New Roman',Times,serif;font-size:13px;font-style:italic;color:#666666;line-height:1.5;">
                      &ldquo;C&rsquo;&eacute;tait mieux avant. Ce sera pire demain.&rdquo;
                    </p>
                  </td>
                </tr>
                <tr>
                  <td style="padding:8px 24px;text-align:center;">
                    <!-- Séparateur -->
                    <div style="border-top:1px solid #2a2a2a;font-size:0;line-height:0;">&nbsp;</div>
                  </td>
                </tr>
                <tr>
                  <td style="padding:8px 24px;text-align:center;">
                    <table align="center" cellpadding="0" cellspacing="0" border="0" role="presentation">
                      <tr>
                        <td style="padding:0 8px;">
                          <a href="https://toutvamal.fr" target="_blank"
                             style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#888888;text-decoration:none;">
                            toutvamal.fr
                          </a>
                        </td>
                        <td style="padding:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#444444;">|</td>
                        <td style="padding:0 8px;">
                          <a href="<?php echo htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8'); ?>"
                             target="_blank"
                             style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#888888;text-decoration:underline;">
                            Se d&eacute;sabonner
                          </a>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td style="padding:8px 24px 24px 24px;text-align:center;">
                    <p style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#444444;line-height:1.4;">
                      Vous recevez cet email car vous &ecirc;tes abonn&eacute;(e) &agrave; la newsletter de ToutVaMal.fr.<br />
                      ToutVaMal.fr &mdash; Toute la d&eacute;prime du monde, r&eacute;sum&eacute;e pour vous.
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

        </table>
        <!-- Fin conteneur email -->

      </td>
    </tr>
  </table>
  <!-- Fin wrapper principal -->

</body>
</html>
