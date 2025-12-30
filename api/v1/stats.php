<?php
/**
 * ToutVaMal.fr - Stats API
 */

require_once dirname(__DIR__) . '/config.php';
require_api_token();

$stats = [
    'articles' => db()->query("SELECT COUNT(*) FROM articles")->fetchColumn(),
    'journalists' => db()->query("SELECT COUNT(*) FROM journalists WHERE active = 1")->fetchColumn(),
    'newsletter' => db()->query("SELECT COUNT(*) FROM newsletter WHERE unsubscribed_at IS NULL")->fetchColumn()
];

json_response($stats);
