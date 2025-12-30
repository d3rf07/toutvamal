<?php
/**
 * ToutVaMal.fr - Generation Log API
 */

require_once dirname(__DIR__) . '/config.php';
require_api_token();

$limit = min((int) ($_GET['limit'] ?? 20), 100);

$stmt = db()->prepare("
    SELECT * FROM generation_log
    ORDER BY created_at DESC
    LIMIT :limit
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

json_response($stmt->fetchAll());
