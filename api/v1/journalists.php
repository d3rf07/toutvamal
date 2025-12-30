<?php
/**
 * ToutVaMal.fr - Journalists API
 */

require_once dirname(__DIR__) . '/config.php';
require_api_token();

$stmt = db()->query("SELECT id, name, role, badge FROM journalists WHERE active = 1 ORDER BY name");
json_response($stmt->fetchAll());
