<?php
/**
 * Génère l'image OpenGraph par défaut pour ToutVaMal.fr
 * Dimensions : 1200x630 (ratio 1.91:1 recommandé par Facebook/Twitter)
 */

$width = 1200;
$height = 630;

// Créer l'image
$img = imagecreatetruecolor($width, $height);

// Couleurs
$rouge = imagecolorallocate($img, 220, 38, 38);      // #DC2626
$noir = imagecolorallocate($img, 23, 23, 23);        // #171717
$blanc = imagecolorallocate($img, 255, 255, 255);
$gris = imagecolorallocate($img, 64, 64, 64);

// Fond noir
imagefill($img, 0, 0, $noir);

// Bande rouge en haut
imagefilledrectangle($img, 0, 0, $width, 8, $rouge);

// Bande rouge en bas
imagefilledrectangle($img, 0, $height - 8, $width, $height, $rouge);

// Texte "TOUT VA MAL" centré
$text1 = "TOUT";
$text2 = "VA";
$text3 = "MAL";

// Police système (ajuster selon ce qui est disponible)
$fontBold = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
$fontRegular = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';

// Si les polices n'existent pas, utiliser les polices intégrées
if (!file_exists($fontBold)) {
    // Fallback avec imagestring (moins joli mais fonctionnel)
    $centerX = $width / 2;
    $centerY = $height / 2;

    // Logo simplifié
    imagestring($img, 5, $centerX - 100, $centerY - 40, "TOUT VA MAL", $blanc);
    imagestring($img, 5, $centerX - 60, $centerY - 20, "VA", $rouge);
    imagestring($img, 3, $centerX - 180, $centerY + 20, "C'ETAIT MIEUX AVANT, MAIS CE SERA PIRE DEMAIN", $gris);
    imagestring($img, 2, $centerX - 50, $height - 50, "toutvamal.fr", $blanc);
} else {
    // Version avec TTF
    $logoSize = 72;
    $taglineSize = 18;
    $urlSize = 16;

    // Calculer la largeur totale du logo
    $bbox1 = imagettfbbox($logoSize, 0, $fontBold, $text1);
    $bbox2 = imagettfbbox($logoSize, 0, $fontBold, $text2);
    $bbox3 = imagettfbbox($logoSize, 0, $fontBold, $text3);

    $width1 = $bbox1[2] - $bbox1[0];
    $width2 = $bbox2[2] - $bbox2[0];
    $width3 = $bbox3[2] - $bbox3[0];
    $totalWidth = $width1 + $width2 + $width3 + 20; // 20px d'espacement

    $startX = ($width - $totalWidth) / 2;
    $logoY = $height / 2 - 20;

    // Dessiner le logo
    imagettftext($img, $logoSize, 0, $startX, $logoY, $blanc, $fontBold, $text1);
    imagettftext($img, $logoSize, 0, $startX + $width1 + 10, $logoY, $rouge, $fontBold, $text2);
    imagettftext($img, $logoSize, 0, $startX + $width1 + $width2 + 20, $logoY, $blanc, $fontBold, $text3);

    // Tagline
    $tagline = "C'ÉTAIT MIEUX AVANT, MAIS CE SERA PIRE DEMAIN";
    $bboxTag = imagettfbbox($taglineSize, 0, $fontRegular, $tagline);
    $tagWidth = $bboxTag[2] - $bboxTag[0];
    imagettftext($img, $taglineSize, 0, ($width - $tagWidth) / 2, $logoY + 60, $gris, $fontRegular, $tagline);

    // URL en bas
    $url = "toutvamal.fr";
    $bboxUrl = imagettfbbox($urlSize, 0, $fontBold, $url);
    $urlWidth = $bboxUrl[2] - $bboxUrl[0];
    imagettftext($img, $urlSize, 0, ($width - $urlWidth) / 2, $height - 40, $blanc, $fontBold, $url);

    // Badge "SITE SATIRIQUE"
    $badge = "SITE SATIRIQUE";
    $badgeSize = 12;
    $bboxBadge = imagettfbbox($badgeSize, 0, $fontBold, $badge);
    $badgeWidth = $bboxBadge[2] - $bboxBadge[0];
    $badgeX = ($width - $badgeWidth - 20) / 2;
    $badgeY = $logoY - 80;

    imagefilledrectangle($img, $badgeX - 10, $badgeY - 18, $badgeX + $badgeWidth + 10, $badgeY + 6, $rouge);
    imagettftext($img, $badgeSize, 0, $badgeX, $badgeY, $blanc, $fontBold, $badge);
}

// Sauvegarder
$outputPath = __DIR__ . '/images/og-default.jpg';
imagejpeg($img, $outputPath, 95);
imagedestroy($img);

echo "Image créée : $outputPath\n";
echo "Dimensions : {$width}x{$height}\n";
