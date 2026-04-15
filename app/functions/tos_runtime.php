<?php
/**
 * תקנון: גרסה נוכחית ותוכן מטבלת tos_terms (ניהול בפאל האדמין).
 * נפילה: קבועים ישנים + קובץ assets/includes/tos_content.php
 */

if (!function_exists('tazrim_tos_terms_table_ready')) {
    function tazrim_tos_terms_table_ready(): bool
    {
        global $conn;
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        $ok = false;
        if (!$conn) {
            return $ok;
        }
        $r = @mysqli_query($conn, "SHOW TABLES LIKE 'tos_terms'");
        $ok = $r && mysqli_num_rows($r) > 0;
        return $ok;
    }
}

if (!function_exists('tazrim_ensure_tos_terms_table')) {
    function tazrim_ensure_tos_terms_table(): void
    {
        global $conn;
        if (!$conn) {
            return;
        }
        @mysqli_query(
            $conn,
            "CREATE TABLE IF NOT EXISTS `tos_terms` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `version` varchar(50) NOT NULL,
                `last_updated_label` varchar(120) NOT NULL DEFAULT '',
                `content_html` mediumtext NOT NULL,
                `is_current` tinyint(1) NOT NULL DEFAULT 0,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `version` (`version`),
                KEY `idx_current` (`is_current`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        if (!tazrim_tos_terms_table_ready()) {
            return;
        }
        $c = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM `tos_terms`');
        if (!$c) {
            return;
        }
        $n = (int) mysqli_fetch_assoc($c)['c'];
        if ($n > 0) {
            return;
        }

        $path = ROOT_PATH . '/assets/includes/tos_content.php';
        $html = is_readable($path) ? (string) file_get_contents($path) : '<p>תוכן התקנון יוגדר בניהול המערכת.</p>';
        $ver = '2.0';
        $label = 'אפריל 2026';

        $stmt = $conn->prepare(
            'INSERT INTO `tos_terms` (`version`, `last_updated_label`, `content_html`, `is_current`) VALUES (?, ?, ?, 1)'
        );
        if ($stmt) {
            $stmt->bind_param('sss', $ver, $label, $html);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('tazrim_current_tos_row')) {
    /**
     * @return array<string, mixed>|null
     */
    function tazrim_current_tos_row(): ?array
    {
        static $cached = null;
        static $resolved = false;
        if ($resolved) {
            return $cached;
        }
        $resolved = true;
        global $conn;

        if (tazrim_tos_terms_table_ready() && $conn) {
            $r = mysqli_query($conn, 'SELECT * FROM `tos_terms` WHERE `is_current` = 1 LIMIT 1');
            if ($r && mysqli_num_rows($r) > 0) {
                $cached = mysqli_fetch_assoc($r);
                return $cached;
            }
            $r2 = mysqli_query($conn, 'SELECT * FROM `tos_terms` ORDER BY `id` DESC LIMIT 1');
            if ($r2 && mysqli_num_rows($r2) > 0) {
                $cached = mysqli_fetch_assoc($r2);
                return $cached;
            }
        }

        return null;
    }
}

if (!function_exists('tazrim_tos_version')) {
    function tazrim_tos_version(): string
    {
        $row = tazrim_current_tos_row();
        if ($row && !empty($row['version'])) {
            return (string) $row['version'];
        }
        return '2.0';
    }
}

if (!function_exists('tazrim_tos_last_updated')) {
    function tazrim_tos_last_updated(): string
    {
        $row = tazrim_current_tos_row();
        if ($row && isset($row['last_updated_label'])) {
            return (string) $row['last_updated_label'];
        }
        return 'אפריל 2026';
    }
}

if (!function_exists('tazrim_tos_content_html')) {
    function tazrim_tos_content_html(): string
    {
        $row = tazrim_current_tos_row();
        if ($row && isset($row['content_html']) && $row['content_html'] !== '') {
            return (string) $row['content_html'];
        }
        $path = ROOT_PATH . '/assets/includes/tos_content.php';
        if (is_readable($path)) {
            return (string) file_get_contents($path);
        }
        return '';
    }
}
