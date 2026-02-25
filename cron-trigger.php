<?php
/**
 * ToutVaMal.fr - Webhook de déclenchement cron
 * Sécurisé par token secret
 *
 * Usage: https://toutvamal.fr/cron-trigger.php?token=SECRET&action=generate
 */

// Token secret généré une seule fois
define('CRON_TOKEN', 'tvm_cron_8f3k2m9x7p4q1w6n');

// Headers
header('Content-Type: application/json');

// Vérification token
$token = $_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
if (!hash_equals(CRON_TOKEN, $token)) {
    http_response_code(403);
    die(json_encode(['error' => 'Invalid token', 'provided' => substr($token, 0, 5) . '...']));
}

// Action demandée
$action = $_GET['action'] ?? 'generate';
$count = min((int)($_GET['count'] ?? 1), 3); // Max 3 articles par appel

chdir(__DIR__);
require_once 'config.php';

$logFile = dirname(__DIR__) . '/logs/cron-articles.log';
$timestamp = date('Y-m-d H:i:s');

switch ($action) {
    case 'generate':
        // Anti-spam : vérifier qu'on n'a pas généré dans les 30 dernières minutes
        $lockFile = sys_get_temp_dir() . '/tvm_cron_lock';
        if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 1800) {
            echo json_encode([
                'status' => 'skipped',
                'reason' => 'Rate limited - dernier run il y a moins de 30 min',
                'next_allowed' => date('Y-m-d H:i:s', filemtime($lockFile) + 1800)
            ]);
            exit;
        }
        touch($lockFile);

        // Exécuter la génération d'articles
        $cmd = "/usr/bin/php " . escapeshellarg(__DIR__ . "/cron/generate-articles.php") . " $count --publish 2>&1";
        $output = shell_exec($cmd);

        // Logger le résultat
        file_put_contents($logFile, "[$timestamp] Triggered via webhook\n$output\n\n", FILE_APPEND);

        // Extraire les stats
        preg_match('/Generated: (\d+)/', $output, $generated);
        preg_match('/Failed: (\d+)/', $output, $failed);
        preg_match('/Skipped.*: (\d+)/', $output, $skipped);

        echo json_encode([
            'status' => 'completed',
            'timestamp' => $timestamp,
            'articles_generated' => (int)($generated[1] ?? 0),
            'articles_failed' => (int)($failed[1] ?? 0),
            'articles_skipped' => (int)($skipped[1] ?? 0)
        ], JSON_PRETTY_PRINT);
        break;

    case 'status':
        // Retourner le statut sans exécuter
        $lastLog = '';
        if (file_exists($logFile)) {
            $lines = file($logFile);
            $lastLog = implode('', array_slice($lines, -15));
        }

        echo json_encode([
            'status' => 'ok',
            'server_time' => $timestamp,
            'last_logs' => $lastLog ?: 'Aucun log'
        ], JSON_PRETTY_PRINT);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Action inconnue. Utilisez: generate ou status']);
}
