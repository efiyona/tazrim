<?php
declare(strict_types=1);

require_once __DIR__ . '/_init.php';
require_once dirname(__DIR__) . '/services/user_agent_transport.php';
require_once dirname(__DIR__) . '/services/user_agent_dispatch.php';
require_once dirname(__DIR__) . '/services/chat_repository.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);
if (!is_array($payload)) {
    echo json_encode(['status' => 'error', 'message' => 'invalid_json'], JSON_UNESCAPED_UNICODE);
    exit;
}

$proposalId = trim((string) ($payload['proposal_id'] ?? ''));
$signature = trim((string) ($payload['signature'] ?? ''));
$chatId = (int) ($payload['chat_id'] ?? 0);
$proposedAt = (int) ($payload['proposed_at'] ?? 0);
$accept = !empty($payload['accept']);

if ($proposalId === '' || $signature === '' || $chatId <= 0 || $proposedAt <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'missing_fields'], JSON_UNESCAPED_UNICODE);
    exit;
}

$key = 'ai_chat_proposals';
if (empty($_SESSION[$key]) || !is_array($_SESSION[$key])) {
    echo json_encode(['status' => 'error', 'message' => 'proposal_expired'], JSON_UNESCAPED_UNICODE);
    exit;
}

$store = $_SESSION[$key];
if (empty($store[$proposalId]) || !is_array($store[$proposalId])) {
    echo json_encode(['status' => 'error', 'message' => 'proposal_not_found'], JSON_UNESCAPED_UNICODE);
    exit;
}

$entry = $store[$proposalId];
if ((time() - (int) ($entry['t'] ?? 0)) > 900) {
    unset($_SESSION[$key][$proposalId]);
    echo json_encode(['status' => 'error', 'message' => 'proposal_expired'], JSON_UNESCAPED_UNICODE);
    exit;
}

$canonical = (string) ($entry['canonical'] ?? '');
if ($canonical === '' || (int) ($entry['chat_id'] ?? 0) !== $chatId || (int) ($entry['proposed_at'] ?? 0) !== $proposedAt) {
    echo json_encode(['status' => 'error', 'message' => 'proposal_mismatch'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!ai_chat_verify_proposal_signature($canonical, $chatId, $proposedAt, $signature)) {
    echo json_encode(['status' => 'error', 'message' => 'bad_signature'], JSON_UNESCAPED_UNICODE);
    exit;
}

$chat = ai_chat_repo_get($conn, $chatId, $userId);
if (!$chat) {
    echo json_encode(['status' => 'error', 'message' => 'chat_not_found'], JSON_UNESCAPED_UNICODE);
    exit;
}

$proposalType = (string) ($entry['proposal_type'] ?? 'unknown');

if (!$accept) {
    ai_user_agent_log_hitl($conn, $userId, $homeId, $chatId, $proposalType, 'REJECTED');
    unset($_SESSION[$key][$proposalId]);
    echo json_encode(['status' => 'success', 'outcome' => 'rejected'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = json_decode($canonical, true);
if (!is_array($action)) {
    echo json_encode(['status' => 'error', 'message' => 'bad_action'], JSON_UNESCAPED_UNICODE);
    exit;
}

$res = ai_user_agent_dispatch($conn, $homeId, $userId, $action);
if (!$res['ok']) {
    ai_user_agent_log_hitl($conn, $userId, $homeId, $chatId, $proposalType, 'REJECTED');
    echo json_encode(['status' => 'error', 'message' => $res['message'] ?? 'dispatch_failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

ai_user_agent_log_hitl($conn, $userId, $homeId, $chatId, $proposalType, 'ACCEPTED');
unset($_SESSION[$key][$proposalId]);

$note = 'בוצע: ' . ($res['message'] ?? 'הסתיים');
ai_chat_repo_add_message($conn, $chatId, 'assistant', $note, 'agent_execute');

echo json_encode(['status' => 'success', 'outcome' => 'accepted', 'message' => $res['message'] ?? ''], JSON_UNESCAPED_UNICODE);
