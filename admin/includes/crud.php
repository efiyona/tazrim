<?php

function tazrim_admin_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'גוף בקשה לא תקין.'], 400);
    }
    return $body;
}

function tazrim_admin_validate_csrf_or_fail(string $csrf): void
{
    if (!tazrim_admin_csrf_validate($csrf)) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'פג תוקף אבטחה. רעננו את הדף.'], 419);
    }
}

function tazrim_admin_resolve_table_config_from_body(array $body): array
{
    $tableKey = isset($body['t']) ? preg_replace('/[^a-z0-9_]/', '', $body['t']) : '';
    if ($tableKey === '' || $tableKey !== ($body['t'] ?? '')) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'טבלה לא תקינה.'], 400);
    }

    $config = tazrim_admin_get_table_config($tableKey);
    if (!$config) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'טבלה לא קיימת.'], 404);
    }

    return [$tableKey, $config];
}

function tazrim_admin_crud_guard_table_mutation(array $config): void
{
    if (!empty($config['list_only'])) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'טבלה זו לצפייה בלבד.'], 403);
    }
}

function tazrim_admin_crud_guard_delete_allowed(array $config): void
{
    tazrim_admin_crud_guard_table_mutation($config);
    if (isset($config['allow_delete']) && $config['allow_delete'] === false) {
        tazrim_admin_json_response(['status' => 'error', 'message' => 'מחיקה לא מופעלת לטבלה זו.'], 403);
    }
}
