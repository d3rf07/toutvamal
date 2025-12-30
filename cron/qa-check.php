<?php
/**
 * ToutVaMal.fr - QA Verification Script
 * Run after each deployment to verify everything works
 */

$baseUrl = 'https://toutvamal.fr';
$errors = [];
$warnings = [];
$success = [];

echo "=== QA Check ToutVaMal.fr ===\n\n";

// 1. Check homepage
echo "1. Homepage...\n";
$homepage = @file_get_contents($baseUrl . '/');
if ($homepage === false) {
    $errors[] = "Homepage not accessible";
} else {
    if (strpos($homepage, 'TOUT') !== false && strpos($homepage, 'VA') !== false && strpos($homepage, 'MAL') !== false) {
        $success[] = "Homepage: Logo present";
    } else {
        $errors[] = "Homepage: Logo missing";
    }
    if (strpos($homepage, 'class="nav"') !== false) {
        $success[] = "Homepage: Navigation present";
    } else {
        $errors[] = "Homepage: Navigation missing";
    }
    if (strpos($homepage, 'class="footer"') !== false) {
        $success[] = "Homepage: Footer present";
    } else {
        $errors[] = "Homepage: Footer missing";
    }
    if (strpos($homepage, 'team-section') !== false) {
        $success[] = "Homepage: Team section present";
    } else {
        $warnings[] = "Homepage: Team section missing";
    }
}

// 2. Check CSS
echo "2. CSS...\n";
$headers = @get_headers($baseUrl . '/css/style.css');
if ($headers && strpos($headers[0], '200') !== false) {
    $css = @file_get_contents($baseUrl . '/css/style.css');
    if (strlen($css) > 1000) {
        $success[] = "CSS: Loaded (" . strlen($css) . " bytes)";
    } else {
        $warnings[] = "CSS: File seems too small";
    }
} else {
    $errors[] = "CSS: Not accessible (403 or 404)";
}

// 3. Check an article
echo "3. Article pages...\n";
$articleUrl = $baseUrl . '/articles/les-chats-desertent-instagram-panique-generale.html';
$article = @file_get_contents($articleUrl);
if ($article === false) {
    $errors[] = "Article: Page not accessible";
} else {
    if (strpos($article, 'article-hero') !== false) {
        $success[] = "Article: Hero section present";
    } else {
        $errors[] = "Article: Hero section missing";
    }
    if (strpos($article, 'article-content') !== false) {
        $success[] = "Article: Content section present";
    } else {
        $errors[] = "Article: Content section missing";
    }
    if (strpos($article, 'share-btn') !== false) {
        $success[] = "Article: Share buttons present";
    } else {
        $warnings[] = "Article: Share buttons missing";
    }
    if (strpos($article, '</html>') !== false) {
        $success[] = "Article: HTML complete";
    } else {
        $errors[] = "Article: HTML truncated";
    }
}

// 4. Check images
echo "4. Images...\n";
$images = [
    '/images/articles/les-chats-desertent-instagram-panique-generale.webp' => 'Article image',
    '/equipe/jean-michel-deparve.webp' => 'Team photo',
    '/favicon.ico' => 'Favicon'
];
foreach ($images as $path => $name) {
    $headers = @get_headers($baseUrl . $path);
    if ($headers && strpos($headers[0], '200') !== false) {
        $success[] = "Image: $name accessible";
    } else {
        $warnings[] = "Image: $name not accessible ($path)";
    }
}

// 5. Check API
echo "5. API...\n";
$apiUrl = $baseUrl . '/api/newsletter.php';
$headers = @get_headers($apiUrl);
if ($headers && (strpos($headers[0], '200') !== false || strpos($headers[0], '405') !== false)) {
    $success[] = "API: Newsletter endpoint accessible";
} else {
    $errors[] = "API: Newsletter endpoint not working";
}

// 6. Check admin
echo "6. Admin...\n";
$admin = @file_get_contents($baseUrl . '/admin/');
if ($admin !== false && strpos($admin, 'Admin') !== false) {
    $success[] = "Admin: Interface accessible";
} else {
    $warnings[] = "Admin: Interface may not be accessible";
}

// Summary
echo "\n=== RESULTS ===\n\n";

if (!empty($errors)) {
    echo "❌ ERRORS (" . count($errors) . "):\n";
    foreach ($errors as $e) {
        echo "   - $e\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  WARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $w) {
        echo "   - $w\n";
    }
    echo "\n";
}

if (!empty($success)) {
    echo "✅ SUCCESS (" . count($success) . "):\n";
    foreach ($success as $s) {
        echo "   - $s\n";
    }
    echo "\n";
}

// Final status
$status = empty($errors) ? (empty($warnings) ? 'PASS' : 'PASS WITH WARNINGS') : 'FAIL';
echo "=== STATUS: $status ===\n";

exit(empty($errors) ? 0 : 1);
