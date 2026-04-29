<?php
declare(strict_types=1);

/**
 * CSRF לדפי האפליקציה (לא פאנל אדמין).
 */
function tazrim_app_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['tazrim_app_csrf'])) {
        $_SESSION['tazrim_app_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['tazrim_app_csrf'];
}

function tazrim_app_csrf_validate(?string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if ($token === null || $token === '' || empty($_SESSION['tazrim_app_csrf'])) {
        return false;
    }

    return hash_equals($_SESSION['tazrim_app_csrf'], $token);
}
