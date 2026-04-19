<?php
declare(strict_types=1);

if (!function_exists('admin_ai_agent_project_policy')) {
    /**
     * @return array<string, mixed>
     */
    function admin_ai_agent_project_policy(): array
    {
        return [
            'allowed_prefixes' => ['admin/', 'app/', 'includes/'],
            'blocked_prefixes' => ['.git/', 'vendor/', 'node_modules/'],
            'blocked_files' => ['path.php'],
            'blocked_globs' => ['.env', '.env.*'],
            'allowed_extensions' => ['php', 'phtml', 'js', 'ts', 'css', 'scss', 'md', 'json', 'txt', 'html'],
            'max_read_bytes' => 512 * 1024,
            'max_write_bytes' => 512 * 1024,
        ];
    }
}

if (!function_exists('admin_ai_agent_project_last_php_error_message')) {
    function admin_ai_agent_project_last_php_error_message(): string
    {
        $e = error_get_last();
        if (!is_array($e)) {
            return '';
        }
        return trim((string) ($e['message'] ?? ''));
    }
}

if (!function_exists('admin_ai_agent_project_root_path')) {
    function admin_ai_agent_project_root_path(): string
    {
        if (defined('ROOT_PATH')) {
            return rtrim((string) ROOT_PATH, '/');
        }
        return rtrim(dirname(__DIR__, 4), '/');
    }
}

if (!function_exists('admin_ai_agent_project_match_glob')) {
    function admin_ai_agent_project_match_glob(string $relativePath, string $pattern): bool
    {
        if (function_exists('fnmatch')) {
            return fnmatch($pattern, $relativePath);
        }
        $regex = '/^' . str_replace(['\*', '\?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/u';
        return preg_match($regex, $relativePath) === 1;
    }
}

if (!function_exists('admin_ai_agent_project_resolve_path')) {
    /**
     * @return array{ok:bool,abs_path?:string,display_path?:string,error?:string}
     */
    function admin_ai_agent_project_resolve_path(string $relative): array
    {
        $raw = trim(str_replace('\\', '/', $relative));
        $raw = ltrim($raw, '/');
        if ($raw === '') {
            return ['ok' => false, 'error' => 'missing_path'];
        }
        if (strpos($raw, "\0") !== false) {
            return ['ok' => false, 'error' => 'invalid_path'];
        }
        if (strpos($raw, '..') !== false) {
            return ['ok' => false, 'error' => 'path_escape'];
        }

        $policy = admin_ai_agent_project_policy();
        foreach (($policy['blocked_prefixes'] ?? []) as $pref) {
            $p = (string) $pref;
            if ($p !== '' && str_starts_with($raw, $p)) {
                return ['ok' => false, 'error' => 'path_blocked_prefix'];
            }
        }
        foreach (($policy['blocked_files'] ?? []) as $bf) {
            if ($raw === (string) $bf) {
                return ['ok' => false, 'error' => 'path_blocked_file'];
            }
        }
        foreach (($policy['blocked_globs'] ?? []) as $g) {
            if (admin_ai_agent_project_match_glob($raw, (string) $g)) {
                return ['ok' => false, 'error' => 'path_blocked_glob'];
            }
        }

        $allowed = false;
        foreach (($policy['allowed_prefixes'] ?? []) as $pref) {
            $p = (string) $pref;
            if ($p !== '' && str_starts_with($raw, $p)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            return ['ok' => false, 'error' => 'path_not_allowed_prefix'];
        }

        $ext = strtolower((string) pathinfo($raw, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $policy['allowed_extensions'] ?? [], true)) {
            return ['ok' => false, 'error' => 'extension_not_allowed'];
        }

        $root = admin_ai_agent_project_root_path();
        $abs = $root . '/' . $raw;
        $absDir = dirname($abs);
        $realRoot = realpath($root);
        $realDir = realpath($absDir);
        if ($realRoot === false || $realDir === false || !str_starts_with($realDir, $realRoot)) {
            return ['ok' => false, 'error' => 'path_escape'];
        }

        return ['ok' => true, 'abs_path' => $abs, 'display_path' => $raw];
    }
}

if (!function_exists('admin_ai_agent_project_tmp_token')) {
    function admin_ai_agent_project_tmp_token(): string
    {
        try {
            return bin2hex(random_bytes(6));
        } catch (\Throwable $e) {
            return dechex((int) (microtime(true) * 1000000)) . '_' . (string) mt_rand(1000, 9999);
        }
    }
}

if (!function_exists('admin_ai_agent_project_create_tmp_lint_file')) {
    /**
     * @return array{ok:bool,path?:string,error?:string}
     */
    if (!function_exists('admin_ai_agent_project_create_tmp_lint_file')) {
        /**
         * @return array{ok:bool,path?:string,error?:string,detail?:string}
         */
        function admin_ai_agent_project_create_tmp_lint_file(string $targetPath): array
        {
            $candidates = [];
            
            // 1. התיקייה המקורית של הקובץ (העדפה ראשונה)
            $targetDir = rtrim(dirname($targetPath), '/');
            if ($targetDir !== '') {
                $candidates[] = $targetDir;
            }
            
            // 2. תיקיית הזמניים של המערכת
            $sysTmp = rtrim((string) sys_get_temp_dir(), '/');
            if ($sysTmp !== '' && !in_array($sysTmp, $candidates, true)) {
                $candidates[] = $sysTmp;
            }
            
            // 3. תיקון ל-Mac/Linux: תיקיית tmp הציבורית
            if (is_dir('/tmp') && !in_array('/tmp', $candidates, true)) {
                $candidates[] = '/tmp';
            }
            
            // 4. גיבוי אחרון: תיקיית הסקריפט הנוכחי (services)
            if (!in_array(__DIR__, $candidates, true)) {
                $candidates[] = __DIR__;
            }
    
            $lastErr = '';
            foreach ($candidates as $dir) {
                // בדיקת הרשאות מוקדמת כדי לדלג על תיקיות נעולות
                if ($dir === '' || !is_dir($dir) || !is_writable($dir)) {
                    continue;
                }
                
                $attempts = 3;
                for ($i = 0; $i < $attempts; $i++) {
                    $tmpPath = $dir . '/.ai_chat_lint_' . admin_ai_agent_project_tmp_token() . '.php';
                    
                    // שימוש ב-file_put_contents עדיף על fopen('x') הנוקשה בטיפול בהרשאות
                    $bytes = @file_put_contents($tmpPath, '');
                    if ($bytes !== false) {
                        return ['ok' => true, 'path' => $tmpPath];
                    } else {
                        $err = error_get_last();
                        if ($err) {
                            $lastErr = $err['message'];
                        }
                    }
                }
            }
    
            return [
                'ok' => false, 
                'error' => 'tmp_file_create_failed', 
                'detail' => $lastErr ?: 'לא נמצאה תיקייה עם הרשאות כתיבה לשרת ה-Apache.'
            ];
        }
    }
}

if (!function_exists('admin_ai_agent_project_validate_syntax')) {
    /**
     * @return array{ok:bool,error?:string,detail?:string,lint_tool?:string,skipped?:bool}
     */
    function admin_ai_agent_project_validate_syntax(string $targetPath, string $content): array
    {
        $ext = strtolower((string) pathinfo($targetPath, PATHINFO_EXTENSION));
        if ($ext !== 'php' && $ext !== 'phtml') {
            return ['ok' => true, 'skipped' => true, 'lint_tool' => 'none'];
        }

        $tmpOut = admin_ai_agent_project_create_tmp_lint_file($targetPath);
        if (empty($tmpOut['ok'])) {
            return [
                'ok' => false,
                'error' => (string) ($tmpOut['error'] ?? 'tmp_file_create_failed'),
                'detail' => 'cannot_create_tmp_for_lint target_dir=' . dirname($targetPath) . ' sys_tmp=' . sys_get_temp_dir(),
            ];
        }
        $tmp = (string) ($tmpOut['path'] ?? '');
        if ($tmp === '') {
            return ['ok' => false, 'error' => 'tmp_file_create_failed'];
        }
        try {
            $bytes = @file_put_contents($tmp, $content);
            if (!is_int($bytes) || $bytes < 0) {
                return ['ok' => false, 'error' => 'tmp_file_write_failed'];
            }
            $cmd = 'php -l ' . escapeshellarg($tmp) . ' 2>&1';
            $out = shell_exec($cmd);
            $out = is_string($out) ? trim($out) : '';
            if ($out === '' || stripos($out, 'Errors parsing') !== false || stripos($out, 'Parse error') !== false) {
                return ['ok' => false, 'error' => 'syntax_check_failed', 'detail' => $out !== '' ? $out : 'php_lint_unknown_error', 'lint_tool' => 'php -l'];
            }
            return ['ok' => true, 'lint_tool' => 'php -l'];
        } finally {
            @unlink($tmp);
        }
    }
}

if (!function_exists('admin_ai_agent_project_lint_file_path')) {
    /**
     * @return array{ok:bool,error?:string,detail?:string,lint_tool?:string}
     */
    function admin_ai_agent_project_lint_file_path(string $path): array
    {
        $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
        $out = shell_exec($cmd);
        $out = is_string($out) ? trim($out) : '';
        if ($out === '' || stripos($out, 'Errors parsing') !== false || stripos($out, 'Parse error') !== false) {
            return [
                'ok' => false,
                'error' => 'syntax_check_failed',
                'detail' => $out !== '' ? $out : 'php_lint_unknown_error',
                'lint_tool' => 'php -l',
            ];
        }
        return ['ok' => true, 'lint_tool' => 'php -l'];
    }
}

if (!function_exists('admin_ai_agent_project_inplace_php_lint_transaction')) {
    /**
     * Fallback when no tmp file can be created.
     * @return array{ok:bool,error?:string,detail?:string,lint_tool?:string}
     */
    function admin_ai_agent_project_inplace_php_lint_transaction(string $targetPath, string $newContent, string $beforeContent): array
    {
        $bytes = @file_put_contents($targetPath, $newContent);
        if (!is_int($bytes) || $bytes < 0) {
            return ['ok' => false, 'error' => 'write_failed'];
        }
        $lint = admin_ai_agent_project_lint_file_path($targetPath);
        if (!empty($lint['ok'])) {
            $lint['lint_tool'] = 'php -l(in_place_fallback)';
            return $lint;
        }
        @file_put_contents($targetPath, $beforeContent);
        return $lint;
    }
}

if (!function_exists('admin_ai_agent_project_file_read')) {
    function admin_ai_agent_project_file_read(string $path): array
    {
        $resolved = admin_ai_agent_project_resolve_path($path);
        if (empty($resolved['ok'])) {
            return ['ok' => false, 'error' => (string) ($resolved['error'] ?? 'resolve_failed')];
        }
        $abs = (string) $resolved['abs_path'];
        $display = (string) $resolved['display_path'];
        if (!is_file($abs)) {
            return ['ok' => false, 'error' => 'file_not_found', 'path' => $display];
        }
        $size = (int) (@filesize($abs) ?: 0);
        $max = (int) (admin_ai_agent_project_policy()['max_read_bytes'] ?? 524288);
        if ($size > $max) {
            return ['ok' => false, 'error' => 'file_too_large', 'path' => $display, 'bytes' => $size, 'max_bytes' => $max];
        }
        $content = @file_get_contents($abs);
        if (!is_string($content)) {
            return ['ok' => false, 'error' => 'read_failed', 'path' => $display];
        }
        return [
            'ok' => true,
            'path' => $display,
            'bytes' => strlen($content),
            'sha1' => sha1($content),
            'content' => $content,
        ];
    }
}

if (!function_exists('admin_ai_agent_project_file_write')) {
    function admin_ai_agent_project_file_write(string $path, string $content): array
    {
        $resolved = admin_ai_agent_project_resolve_path($path);
        if (empty($resolved['ok'])) {
            return ['ok' => false, 'error' => (string) ($resolved['error'] ?? 'resolve_failed')];
        }
        $abs = (string) $resolved['abs_path'];
        $display = (string) $resolved['display_path'];
        $exists = is_file($abs);
        if ($exists && !is_writable($abs)) {
            return [
                'ok' => false,
                'error' => 'write_failed',
                'detail' => 'target_not_writable path=' . $display,
            ];
        }
        if (!$exists && !is_writable(dirname($abs))) {
            return [
                'ok' => false,
                'error' => 'write_failed',
                'detail' => 'target_dir_not_writable dir=' . dirname($abs),
            ];
        }
        $max = (int) (admin_ai_agent_project_policy()['max_write_bytes'] ?? 524288);
        if (strlen($content) > $max) {
            return ['ok' => false, 'error' => 'content_too_large', 'max_bytes' => $max];
        }
        $before = is_file($abs) ? (string) @file_get_contents($abs) : null;
        $isPhpLike = in_array(strtolower((string) pathinfo($abs, PATHINFO_EXTENSION)), ['php', 'phtml'], true);
        $syntax = admin_ai_agent_project_validate_syntax($abs, $content);
        if (empty($syntax['ok'])) {
            if (($syntax['error'] ?? '') === 'tmp_file_create_failed' && $isPhpLike && $before !== null) {
                $fallbackLint = admin_ai_agent_project_inplace_php_lint_transaction($abs, $content, $before);
                if (empty($fallbackLint['ok'])) {
                    return ['ok' => false, 'error' => (string) ($fallbackLint['error'] ?? 'syntax_check_failed'), 'detail' => (string) ($fallbackLint['detail'] ?? '')];
                }
                return [
                    'ok' => true,
                    'path' => $display,
                    'bytes' => strlen($content),
                    'before_content' => $before,
                    'after_sha1' => sha1($content),
                    'syntax_check' => $fallbackLint,
                ];
            }
            return ['ok' => false, 'error' => (string) ($syntax['error'] ?? 'syntax_check_failed'), 'detail' => (string) ($syntax['detail'] ?? '')];
        }
        if (!is_dir(dirname($abs))) {
            @mkdir(dirname($abs), 0775, true);
        }
        $bytes = @file_put_contents($abs, $content);
        if (!is_int($bytes) || $bytes < 0) {
            $detail = admin_ai_agent_project_last_php_error_message();
            return [
                'ok' => false,
                'error' => 'write_failed',
                'path' => $display,
                'detail' => $detail !== '' ? $detail : 'file_put_contents_failed path=' . $display,
            ];
        }
        return [
            'ok' => true,
            'path' => $display,
            'bytes' => $bytes,
            'before_content' => $before,
            'after_sha1' => sha1($content),
            'syntax_check' => $syntax,
        ];
    }
}

if (!function_exists('admin_ai_agent_project_file_delete')) {
    function admin_ai_agent_project_file_delete(string $path): array
    {
        $resolved = admin_ai_agent_project_resolve_path($path);
        if (empty($resolved['ok'])) {
            return ['ok' => false, 'error' => (string) ($resolved['error'] ?? 'resolve_failed')];
        }
        $abs = (string) $resolved['abs_path'];
        $display = (string) $resolved['display_path'];
        if (!is_file($abs)) {
            return ['ok' => false, 'error' => 'file_not_found', 'path' => $display];
        }
        if (!is_writable($abs)) {
            return ['ok' => false, 'error' => 'delete_failed', 'path' => $display, 'detail' => 'target_not_writable'];
        }
        $before = (string) @file_get_contents($abs);
        if (!@unlink($abs)) {
            $detail = admin_ai_agent_project_last_php_error_message();
            return [
                'ok' => false,
                'error' => 'delete_failed',
                'path' => $display,
                'detail' => $detail !== '' ? $detail : 'unlink_failed path=' . $display,
            ];
        }
        return ['ok' => true, 'path' => $display, 'before_content' => $before];
    }
}

if (!function_exists('admin_ai_agent_project_file_patch')) {
    /**
     * @return array{ok:bool,error?:string,path?:string,mode?:string,matched_block?:string,occurrences?:int,new_content?:string}
     */
    function admin_ai_agent_project_try_patch_content(string $content, string $searchBlock, string $replaceBlock, bool $allowEscapedDecode = true): array
    {
        $exactCount = substr_count($content, $searchBlock);
        if ($exactCount === 1) {
            return [
                'ok' => true,
                'mode' => 'exact',
                'matched_block' => $searchBlock,
                'occurrences' => 1,
                'new_content' => str_replace($searchBlock, $replaceBlock, $content),
            ];
        }
        if ($exactCount > 1) {
            return ['ok' => false, 'error' => 'ambiguous_match_found_make_search_block_larger', 'occurrences' => $exactCount];
        }

        if ($allowEscapedDecode) {
            $decoded = str_replace(
                ['\\r\\n', '\\n', '\\r', '\\t', '\\"'],
                ["\r\n", "\n", "\r", "\t", '"'],
                $searchBlock
            );
            if ($decoded !== $searchBlock) {
                $retry = admin_ai_agent_project_try_patch_content($content, $decoded, $replaceBlock, false);
                if (!empty($retry['ok'])) {
                    $retry['mode'] = 'decoded_escapes_' . (string) ($retry['mode'] ?? 'exact');
                    return $retry;
                }
                if (($retry['error'] ?? '') === 'ambiguous_match_found_make_search_block_larger') {
                    return $retry;
                }
            }
        }

        $contentLf = str_replace("\r\n", "\n", $content);
        $searchLf = str_replace("\r\n", "\n", $searchBlock);
        $lfCount = substr_count($contentLf, $searchLf);
        if ($lfCount === 1) {
            return [
                'ok' => true,
                'mode' => 'line_endings_normalized',
                'matched_block' => $searchLf,
                'occurrences' => 1,
                'new_content' => str_replace($searchLf, $replaceBlock, $contentLf),
            ];
        }
        if ($lfCount > 1) {
            return ['ok' => false, 'error' => 'ambiguous_match_found_make_search_block_larger', 'occurrences' => $lfCount];
        }

        $quoted = preg_quote($searchLf, '/');
        $flexPattern = '/' . str_replace('\ ', '\s+', $quoted) . '/u';
        if (@preg_match_all($flexPattern, $contentLf, $m) !== false) {
            $flexCount = isset($m[0]) ? count($m[0]) : 0;
            if ($flexCount === 1) {
                $matched = (string) $m[0][0];
                return [
                    'ok' => true,
                    'mode' => 'flex_whitespace',
                    'matched_block' => $matched,
                    'occurrences' => 1,
                    'new_content' => preg_replace($flexPattern, $replaceBlock, $contentLf, 1) ?? $contentLf,
                ];
            }
            if ($flexCount > 1) {
                return ['ok' => false, 'error' => 'ambiguous_match_found_make_search_block_larger', 'occurrences' => $flexCount];
            }
        }

        if (preg_match('/<([a-zA-Z0-9]+)[^>]*>([\s\S]*?)<\/\1>/u', $searchLf, $tagM)) {
            $tag = strtolower((string) $tagM[1]);
            $inner = trim(strip_tags((string) $tagM[2]));
            if ($inner !== '') {
                $tagPat = '/<' . preg_quote($tag, '/') . '[^>]*>\s*' . preg_quote($inner, '/') . '\s*<\/' . preg_quote($tag, '/') . '>/u';
                if (@preg_match_all($tagPat, $contentLf, $tm) !== false) {
                    $tagCount = isset($tm[0]) ? count($tm[0]) : 0;
                    if ($tagCount === 1) {
                        $matched = (string) $tm[0][0];
                        return [
                            'ok' => true,
                            'mode' => 'html_tag_text_fallback',
                            'matched_block' => $matched,
                            'occurrences' => 1,
                            'new_content' => preg_replace($tagPat, $replaceBlock, $contentLf, 1) ?? $contentLf,
                        ];
                    }
                    if ($tagCount > 1) {
                        return ['ok' => false, 'error' => 'ambiguous_match_found_make_search_block_larger', 'occurrences' => $tagCount];
                    }
                }
            }
        }

        return ['ok' => false, 'error' => 'search_block_not_found'];
    }
}

if (!function_exists('admin_ai_agent_project_file_patch')) {
    function admin_ai_agent_project_file_patch(string $path, string $searchBlock, string $replaceBlock): array
    {
        if ($searchBlock === '') {
            return ['ok' => false, 'error' => 'missing_search_block'];
        }
        $read = admin_ai_agent_project_file_read($path);
        if (empty($read['ok'])) {
            return $read;
        }
        $content = (string) ($read['content'] ?? '');
        $patched = admin_ai_agent_project_try_patch_content($content, $searchBlock, $replaceBlock);
        if (empty($patched['ok'])) {
            $patched['path'] = (string) ($read['path'] ?? $path);
            return $patched;
        }
        $newContent = (string) ($patched['new_content'] ?? $content);
        $write = admin_ai_agent_project_file_write($path, $newContent);
        if (empty($write['ok'])) {
            return $write;
        }
        return [
            'ok' => true,
            'path' => (string) ($read['path'] ?? $path),
            'mode' => (string) ($patched['mode'] ?? 'exact'),
            'occurrences' => (int) ($patched['occurrences'] ?? 1),
            'matched_block' => (string) ($patched['matched_block'] ?? $searchBlock),
            'before_sha1' => (string) ($read['sha1'] ?? ''),
            'after_sha1' => (string) ($write['after_sha1'] ?? ''),
            'before_content' => $content,
            'search_block' => $searchBlock,
            'replace_block' => $replaceBlock,
            'syntax_check' => $write['syntax_check'] ?? ['ok' => true, 'skipped' => true],
        ];
    }
}
