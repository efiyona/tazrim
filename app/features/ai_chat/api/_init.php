<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$ctx = ai_chat_get_context();
if (!$ctx['ok']) {
    $code = (string) ($ctx['error'] ?? 'unauthorized');
    http_response_code($code === 'email_verification_required' ? 403 : 401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        $code === 'email_verification_required'
            ? ['status' => 'error', 'code' => 'email_verification_required', 'message' => 'email_verification_required']
            : ['status' => 'error', 'message' => 'unauthorized'],
        JSON_UNESCAPED_UNICODE
    );
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
