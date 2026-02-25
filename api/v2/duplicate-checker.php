<?php
/**
 * ToutVaMal.fr - Duplicate Theme Checker
 * Vérifie que le thème d'un article n'a pas déjà été traité récemment.
 * Utilise la similarité Jaccard sur les mots-clés significatifs.
 */

class DuplicateChecker {

    private static array $stopWords = [
        'le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'au', 'aux',
        'et', 'ou', 'mais', 'donc', 'car', 'ni', 'que', 'qui', 'quoi',
        'ce', 'cette', 'ces', 'son', 'sa', 'ses', 'leur', 'leurs',
        'pour', 'par', 'sur', 'sous', 'dans', 'avec', 'sans', 'entre',
        'est', 'sont', 'a', 'ont', 'été', 'être', 'avoir', 'fait',
        'plus', 'moins', 'très', 'tout', 'tous', 'toute', 'toutes',
        'en', 'se', 'ne', 'pas', 'si', 'comme', 'même', 'aussi',
        'après', 'avant', 'alors', 'encore', 'déjà', 'bien', 'mal',
        'france', 'français', 'française', 'pays', 'monde', 'national',
        'nouveau', 'nouvelle', 'nouveaux', 'nouvelles', 'grand', 'petit',
    ];

    /**
     * Extrait les mots-clés significatifs d'un texte
     */
    public static function extractKeywords(string $text): array {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^a-zàâäéèêëïîôùûüç\s]/u', ' ', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $keywords = array_filter($words, function($word) {
            return mb_strlen($word) > 3 && !in_array($word, self::$stopWords);
        });

        return array_unique(array_values($keywords));
    }

    /**
     * Similarité Jaccard entre deux ensembles de mots-clés
     */
    public static function calculateSimilarity(array $keywords1, array $keywords2): float {
        if (empty($keywords1) || empty($keywords2)) {
            return 0.0;
        }

        $intersection = array_intersect($keywords1, $keywords2);
        $union = array_unique(array_merge($keywords1, $keywords2));

        return count($intersection) / count($union);
    }

    /**
     * Vérifie si un titre/thème est trop similaire aux articles existants
     * Compare avec les 100 derniers articles ET les derniers logs de génération
     */
    public static function checkDuplicate(string $title, float $threshold = 0.35): ?array {
        $db = Database::getInstance();
        $newKeywords = self::extractKeywords($title);

        if (empty($newKeywords)) {
            return null;
        }

        // Vérifier contre les articles existants
        $stmt = $db->query("SELECT id, title, source_title FROM articles ORDER BY id DESC LIMIT 100");
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($articles as $article) {
            $articleKeywords = self::extractKeywords($article['title'] . ' ' . ($article['source_title'] ?? ''));
            $similarity = self::calculateSimilarity($newKeywords, $articleKeywords);

            if ($similarity >= $threshold) {
                return [
                    'article' => $article,
                    'similarity' => round($similarity * 100, 1)
                ];
            }
        }

        // Vérifier aussi dans les logs récents (7 jours) — même les échecs
        $stmt = $db->query("SELECT id, source_title FROM generation_logs WHERE created_at > datetime('now', '-7 days')");
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($logs as $log) {
            if (empty($log['source_title'])) continue;

            $logKeywords = self::extractKeywords($log['source_title']);
            $similarity = self::calculateSimilarity($newKeywords, $logKeywords);

            if ($similarity >= $threshold) {
                return [
                    'log_id' => $log['id'],
                    'source_title' => $log['source_title'],
                    'similarity' => round($similarity * 100, 1)
                ];
            }
        }

        return null;
    }
}
