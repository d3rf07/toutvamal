<?php
// Création d'une image apocalyptique par défaut
header('Content-Type: image/jpeg');

$width = 1024;
$height = 768;

// Créer l'image
$image = imagecreatetruecolor($width, $height);

// Couleurs
$black = imagecolorallocate($image, 20, 20, 20);
$red = imagecolorallocate($image, 220, 53, 69);
$darkRed = imagecolorallocate($image, 150, 20, 30);
$white = imagecolorallocate($image, 255, 255, 255);

// Fond noir
imagefill($image, 0, 0, $black);

// Gradient rouge
for ($i = 0; $i < $height; $i++) {
    $alpha = $i / $height;
    $color = imagecolorallocate($image, 
        20 + (200 * $alpha), 
        20, 
        20 + (40 * $alpha)
    );
    imageline($image, 0, $i, $width, $i, $color);
}

// Texte dramatique
$font = 5; // Police système
$text = "TOUT VA MAL";
$textWidth = imagefontwidth($font) * strlen($text);
$textHeight = imagefontheight($font);
$x = ($width - $textWidth) / 2;
$y = ($height - $textHeight) / 2;

// Ombre du texte
imagestring($image, $font, $x + 2, $y + 2, $text, $darkRed);
// Texte principal
imagestring($image, $font, $x, $y, $text, $red);

// Sous-titre
$subtitle = "Image temporaire";
$subtitleWidth = imagefontwidth(3) * strlen($subtitle);
$subtitleX = ($width - $subtitleWidth) / 2;
imagestring($image, 3, $subtitleX, $y + 30, $subtitle, $white);

// Sauvegarder
imagejpeg($image, 'default-apocalypse.jpg', 85);
imagedestroy($image);

echo "Image créée avec succès!";
?>
