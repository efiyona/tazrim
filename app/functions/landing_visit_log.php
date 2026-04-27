<?php
/**
 * כניסה לדפי landing — רישום שורה בטבלה (מינימלי, בצד שרת).
 */
function tazrim_ensure_landing_page_events_table() {
    global $conn;
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!$conn) {
        return;
    }

    $t = @mysqli_query($conn, "SHOW TABLES LIKE 'landing_page_events'");
    if ($t && mysqli_num_rows($t) > 0) {
        $c = @mysqli_query($conn, "SHOW COLUMNS FROM `landing_page_events` LIKE 'visitor_id'");
        if ($c && mysqli_num_rows($c) > 0) {
            @mysqli_query($conn, 'DROP TABLE `landing_page_events`');
        }
    }

    @mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS `landing_page_events` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `page_path` varchar(512) NOT NULL,
            `referer` varchar(1024) NOT NULL DEFAULT '',
            `user_agent` varchar(512) NOT NULL DEFAULT '',
            `query_string` varchar(2048) NOT NULL DEFAULT '',
            PRIMARY KEY (`id`),
            KEY `idx_lpe_created` (`created_at`),
            KEY `idx_lpe_path_created` (`page_path`(191),`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

/**
 * מוסיף שורה לכל בקשת HTTP רגילה לדפי ה-landing.
 */
function tazrim_log_landing_page_visit() {
    if (php_sapi_name() === 'cli') {
        return;
    }
    if (!defined('ROOT_PATH')) {
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }

    if (!isset($GLOBALS['conn']) || !$GLOBALS['conn']) {
        if (!defined('DB_HOST') && is_readable(ROOT_PATH . '/secrets.php')) {
            require_once ROOT_PATH . '/secrets.php';
        }
        require_once ROOT_PATH . '/app/database/connect.php';
        // connect.php נטען בתוך פונקציה — $conn מוגדר ב־local scope, לא ב־global
        if (isset($conn) && $conn instanceof mysqli) {
            $GLOBALS['conn'] = $conn;
        }
    }
    global $conn;
    tazrim_ensure_landing_page_events_table();
    if (!$conn) {
        return;
    }

    $rawUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $path = (string) (parse_url($rawUri, PHP_URL_PATH) ?: '');
    if ($path === '') {
        $path = '/';
    }
    if (mb_strlen($path) > 512) {
        $path = mb_substr($path, 0, 512);
    }

    $referer = '';
    if (isset($_SERVER['HTTP_REFERER'])) {
        $r = trim((string) $_SERVER['HTTP_REFERER']);
        $referer = $r === '' ? '' : (mb_strlen($r) > 1024 ? mb_substr($r, 0, 1024) : $r);
    }

    $ua = '';
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $u = trim((string) $_SERVER['HTTP_USER_AGENT']);
        $ua = $u === '' ? '' : (mb_strlen($u) > 512 ? mb_substr($u, 0, 512) : $u);
    }

    $qstr = '';
    if (isset($_SERVER['QUERY_STRING'])) {
        $q = (string) $_SERVER['QUERY_STRING'];
        if ($q !== '') {
            $qstr = mb_strlen($q) > 2048 ? mb_substr($q, 0, 2048) : $q;
        }
    }

    $stmt = $conn->prepare(
        'INSERT INTO `landing_page_events` (`page_path`, `referer`, `user_agent`, `query_string`) VALUES (?,?,?,?)'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ssss', $path, $referer, $ua, $qstr);
    $stmt->execute();
    $stmt->close();
}
