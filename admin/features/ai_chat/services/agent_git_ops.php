<?php
declare(strict_types=1);

if (!function_exists('admin_ai_agent_git_run_command')) {
    /**
     * @return array{ok:bool,exit_code:int,stdout:string,stderr:string}
     */
    function admin_ai_agent_git_run_command(array $cmdParts, string $cwd, int $timeoutSec = 12): array
    {
        $cmd = implode(' ', array_map(static fn($p) => escapeshellarg((string) $p), $cmdParts));
        $spec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = @proc_open($cmd, $spec, $pipes, $cwd);
        if (!is_resource($proc)) {
            return ['ok' => false, 'exit_code' => 1, 'stdout' => '', 'stderr' => 'proc_open_failed'];
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $start = microtime(true);
        $exit = null;
        while (true) {
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
            $st = proc_get_status($proc);
            if (!$st['running']) {
                $exit = (int) $st['exitcode'];
                break;
            }
            if ((microtime(true) - $start) > $timeoutSec) {
                @proc_terminate($proc);
                $stderr .= ' command_timeout';
                $exit = 124;
                break;
            }
            usleep(50000);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        @proc_close($proc);
        return ['ok' => $exit === 0, 'exit_code' => (int) $exit, 'stdout' => trim($stdout), 'stderr' => trim($stderr)];
    }
}

if (!function_exists('admin_ai_agent_git_after_file_change')) {
    /**
     * @return array<string,mixed>
     */
    function admin_ai_agent_git_after_file_change(string $projectRoot, string $relativePath, int $chatId, string $action): array
    {
        $lockFp = @fopen(sys_get_temp_dir() . '/admin_ai_agent_git.lock', 'c+');
        if ($lockFp && !@flock($lockFp, LOCK_EX | LOCK_NB)) {
            return ['ok' => false, 'git_status' => 'lock_busy', 'message' => 'git_lock_busy'];
        }

        try {
            $branchRes = admin_ai_agent_git_run_command(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $projectRoot);
            if (!$branchRes['ok']) {
                return ['ok' => false, 'git_status' => 'git_unavailable', 'message' => 'git_branch_failed'];
            }
            $branch = trim($branchRes['stdout']);

            $addRes = admin_ai_agent_git_run_command(['git', 'add', '--', $relativePath], $projectRoot);
            if (!$addRes['ok']) {
                return ['ok' => false, 'git_status' => 'add_failed', 'message' => 'git_add_failed', 'detail' => $addRes['stderr']];
            }

            $msg = "AI {$action}: {$relativePath} (chat {$chatId})";
            $commitRes = admin_ai_agent_git_run_command(['git', 'commit', '-m', $msg], $projectRoot);
            if (!$commitRes['ok']) {
                $combined = trim($commitRes['stdout'] . "\n" . $commitRes['stderr']);
                if (stripos($combined, 'nothing to commit') !== false) {
                    return ['ok' => true, 'git_status' => 'no_changes', 'branch' => $branch, 'message' => 'git_no_changes'];
                }
                return ['ok' => false, 'git_status' => 'commit_failed', 'message' => 'git_commit_failed', 'detail' => $commitRes['stderr']];
            }

            $pat = trim((string) getenv('GIT_PAT_TOKEN'));
            if ($pat === '') {
                return ['ok' => true, 'git_status' => 'push_skipped_no_credentials', 'branch' => $branch, 'message' => 'commit_ok_push_skipped'];
            }

            $remoteRes = admin_ai_agent_git_run_command(['git', 'remote', 'get-url', 'origin'], $projectRoot);
            if (!$remoteRes['ok']) {
                return ['ok' => true, 'git_status' => 'push_skipped_no_remote', 'branch' => $branch, 'message' => 'commit_ok_push_skipped'];
            }
            $remote = trim($remoteRes['stdout']);
            if (!preg_match('#^https://#i', $remote)) {
                return ['ok' => true, 'git_status' => 'push_skipped_non_https_remote', 'branch' => $branch, 'message' => 'commit_ok_push_skipped'];
            }
            $authRemote = preg_replace('#^https://#i', 'https://x-access-token:' . rawurlencode($pat) . '@', $remote);
            $pushRes = admin_ai_agent_git_run_command(['git', 'push', $authRemote, $branch], $projectRoot, 20);
            if (!$pushRes['ok']) {
                return [
                    'ok' => true,
                    'git_status' => 'push_failed',
                    'branch' => $branch,
                    'message' => 'commit_ok_push_failed',
                    'detail' => $pushRes['stderr'] !== '' ? $pushRes['stderr'] : $pushRes['stdout'],
                ];
            }
            return ['ok' => true, 'git_status' => 'pushed', 'branch' => $branch, 'message' => 'commit_and_push_ok'];
        } finally {
            if (is_resource($lockFp)) {
                @flock($lockFp, LOCK_UN);
                @fclose($lockFp);
            }
        }
    }
}
