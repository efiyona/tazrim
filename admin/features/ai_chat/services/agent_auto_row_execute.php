<?php
declare(strict_types=1);

/**
 * אוטו-ביצוע CRUD על שורות (לא sql / לא DDL) — אחרי אישור וולידטור ב-stream_message.
 */

require_once __DIR__ . '/agent_execute_dispatch.php';

if (!function_exists('admin_ai_chat_action_is_auto_row_crud_only')) {
    /** רק create/update/delete או sequence שכל שלביו כאלה (בלי sql). */
    function admin_ai_chat_action_is_auto_row_crud_only(array $action): bool
    {
        $a = strtolower((string) ($action['action'] ?? ''));
        if ($a === 'sql') {
            return false;
        }
        if (in_array($a, ['create', 'update', 'delete'], true)) {
            return true;
        }
        if ($a !== 'sequence' || empty($action['steps']) || !is_array($action['steps'])) {
            return false;
        }
        foreach ($action['steps'] as $step) {
            if (!is_array($step)) {
                return false;
            }
            $sa = strtolower((string) ($step['action'] ?? ''));
            if (!in_array($sa, ['create', 'update', 'delete'], true)) {
                return false;
            }
        }

        return count($action['steps']) > 0;
    }
}

if (!function_exists('admin_ai_chat_resolve_sequence_value')) {
    /** @param array<int, int|string|null> $stepIdByIndex */
    function admin_ai_chat_resolve_sequence_value(mixed $value, array $stepIdByIndex): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value) && preg_match('/^\{\{step:(\d+)\}\}$/', $value, $m)) {
            $ix = (int) $m[1];
            if (!array_key_exists($ix, $stepIdByIndex)) {
                return $value;
            }
            $idVal = $stepIdByIndex[$ix];
            if ($idVal === null || $idVal === '') {
                return $value;
            }

            return is_numeric($idVal) ? (int) $idVal : $idVal;
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = admin_ai_chat_resolve_sequence_value($v, $stepIdByIndex);
            }

            return $out;
        }

        return $value;
    }
}

if (!function_exists('admin_ai_chat_resolve_sequence_step_for_execute')) {
    /**
     * @param array<int, int|string|null> $stepIdByIndex
     * @return array<string, mixed>
     */
    function admin_ai_chat_resolve_sequence_step_for_execute(array $step, array $stepIdByIndex): array
    {
        $out = $step;
        if (isset($out['data']) && is_array($out['data'])) {
            foreach ($out['data'] as $k => $v) {
                $out['data'][$k] = admin_ai_chat_resolve_sequence_value($v, $stepIdByIndex);
            }
        }
        if (isset($out['id'])) {
            $out['id'] = admin_ai_chat_resolve_sequence_value($out['id'], $stepIdByIndex);
        }

        return $out;
    }
}

if (!function_exists('admin_ai_chat_run_auto_row_executions')) {
    /**
     * מריץ את אותו מסלול dispatch כמו כפתור «בצע» — ושומר EXECUTION_RESULT לכל שלב.
     *
     * @return list<array{step:?int, http:int, payload:array<string, mixed>}>
     */
    function admin_ai_chat_run_auto_row_executions(
        mysqli $conn,
        int $homeId,
        int $userId,
        int $chatId,
        array $finalAction,
        int $proposedAtMs
    ): array {
        $outcomes = [];
        $root = strtolower((string) ($finalAction['action'] ?? ''));

        if (in_array($root, ['create', 'update', 'delete'], true)) {
            $pl = [
                'action' => $root,
                'table' => (string) ($finalAction['table'] ?? ''),
                'chat_id' => $chatId,
                'proposed_at' => $proposedAtMs,
            ];
            if (isset($finalAction['id'])) {
                $pl['id'] = (int) $finalAction['id'];
            }
            if (isset($finalAction['data']) && is_array($finalAction['data'])) {
                $pl['data'] = $finalAction['data'];
            }
            $ctx = [
                'chat_id' => $chatId,
                'action' => $root,
                'table' => $pl['table'],
                'id' => $pl['id'] ?? 0,
                'sql' => '',
            ];
            $dispatch = admin_ai_agent_dispatch_execute_payload($conn, $homeId, $userId, $chatId, $pl);
            admin_ai_agent_exec_persist_chat_execution($conn, $chatId, $ctx, $dispatch['payload'], true);
            $outcomes[] = ['step' => null, 'http' => $dispatch['http'], 'payload' => $dispatch['payload']];

            return $outcomes;
        }

        if ($root !== 'sequence') {
            return [];
        }

        $stepIdByIndex = [];
        $si = 0;
        foreach ($finalAction['steps'] as $step) {
            if (!is_array($step)) {
                break;
            }
            $resolved = admin_ai_chat_resolve_sequence_step_for_execute($step, $stepIdByIndex);
            $sa = strtolower((string) ($resolved['action'] ?? ''));
            $pl = [
                'action' => $sa,
                'table' => (string) ($resolved['table'] ?? ''),
                'chat_id' => $chatId,
                'proposed_at' => $proposedAtMs,
            ];
            if (isset($resolved['id'])) {
                $pl['id'] = is_int($resolved['id']) ? $resolved['id'] : (int) $resolved['id'];
            }
            if (isset($resolved['data']) && is_array($resolved['data'])) {
                $pl['data'] = $resolved['data'];
            }
            $ctx = [
                'chat_id' => $chatId,
                'action' => $sa,
                'table' => $pl['table'],
                'id' => $pl['id'] ?? 0,
                'sql' => '',
            ];
            $dispatch = admin_ai_agent_dispatch_execute_payload($conn, $homeId, $userId, $chatId, $pl);
            admin_ai_agent_exec_persist_chat_execution($conn, $chatId, $ctx, $dispatch['payload'], true);
            $outcomes[] = ['step' => $si, 'http' => $dispatch['http'], 'payload' => $dispatch['payload']];

            if (($dispatch['payload']['status'] ?? '') !== 'success') {
                break;
            }
            $newId = (int) ($dispatch['payload']['id'] ?? $dispatch['payload']['insert_id'] ?? 0);
            if ($newId > 0) {
                $stepIdByIndex[$si] = $newId;
            }
            $si++;
        }

        return $outcomes;
    }
}
