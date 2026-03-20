<?php
/**
 * ToutVaMal.fr - Endpoint Login/Logout
 * POST   → Login (retourne cookie HttpOnly + token CSRF)
 * GET    → Vérifier session
 * DELETE → Logout
 */

require_once __DIR__ . '/auth.php';

handleLoginEndpoint();
