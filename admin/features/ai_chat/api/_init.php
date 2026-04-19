<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once dirname(__DIR__, 3) . '/includes/auth.php';
if (!tazrim_admin_is_program_admin()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'admin_required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ctx = admin_ai_chat_get_context();
if (!$ctx['ok']) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int) $ctx['user_id'];
$homeId = (int) ($ctx['home_id'] ?? 0);

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'db_unavailable'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once dirname(__DIR__) . '/services/chat_repository.php';
require_once dirname(__DIR__) . '/services/guardrails.php';
require_once dirname(__DIR__) . '/services/prompt_builder.php';
