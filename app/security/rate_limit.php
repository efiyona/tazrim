<?php
declare(strict_types=1);

if (!function_exists('tazrim_is_api_request')) {
    function tazrim_is_api_request(): bool
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return false;
        }

        return strpos($path, '/application/api/') === 0
            || strpos($path, '/app/api/') === 0
            || strpos($path, '/app/ajax/') === 0
            || strpos($path, '/app/features/ai_chat/api/') === 0;
    }
}

if (!function_exists('tazrim_client_ip')) {
    function tazrim_client_ip(): string
    {
        $forwarded = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwarded !== '') {
            $first = trim(explode(',', $forwarded)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }
}

if (!function_exists('tazrim_apply_api_rate_limit')) {
    function tazrim_apply_api_rate_limit(int $maxRequests = 120, int $windowSeconds = 60): void
    {
        if (headers_sent() || !tazrim_is_api_request()) {
            return;
        }

        $ip = tazrim_client_ip();
        $now = time();
        $windowStart = $now - ($now % $windowSeconds);
        $bucketKey = hash('sha256', $ip . '|' . $windowStart);
        $storeDir = sys_get_temp_dir() . '/tazrim_rate_limit';

        if (!is_dir($storeDir)) {
            @mkdir($storeDir, 0775, true);
        }

        $bucketPath = $storeDir . '/' . $bucketKey . '.json';
        $count = 0;
        if (is_file($bucketPath)) {
            $payload = json_decode((string) @file_get_contents($bucketPath), true);
            if (is_array($payload) && isset($payload['count'])) {
                $count = (int) $payload['count'];
            }
        }

        $count++;
        @file_put_contents($bucketPath, json_encode(['count' => $count], JSON_UNESCAPED_UNICODE), LOCK_EX);

        $remaining = max(0, $maxRequests - $count);
        $retryAfter = max(1, ($windowStart + $windowSeconds) - $now);
        header('X-RateLimit-Limit: ' . $maxRequests);
        header('X-RateLimit-Remaining: ' . $remaining);
        header('X-RateLimit-Reset: ' . ($windowStart + $windowSeconds));

        if ($count > $maxRequests) {
            http_response_code(429);
            header('Retry-After: ' . $retryAfter);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'error',
                'message' => 'Too many requests. Please try again soon.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
