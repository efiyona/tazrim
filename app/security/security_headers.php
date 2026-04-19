<?php
declare(strict_types=1);

if (!function_exists('tazrim_is_https_request')) {
    function tazrim_is_https_request(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }

        return isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https';
    }
}

if (!function_exists('tazrim_apply_security_headers')) {
    function tazrim_apply_security_headers(): void
    {
        if (headers_sent()) {
            return;
        }

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https:",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'self'",
            "img-src 'self' data: https:",
            "style-src 'self' 'unsafe-inline' https:",
            "font-src 'self' data: https:",
            "connect-src 'self' https: wss:",
        ]);

        header('Content-Security-Policy: ' . $csp);
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        if (tazrim_is_https_request()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
