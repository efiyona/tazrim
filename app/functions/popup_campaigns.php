<?php
/**
 * לוגיקת הודעות פופאפ מנוהלות (יעדים, זמנים, קריאה, מדיניות אישור).
 */

if (!function_exists('tazrim_popup_campaigns_table_ready')) {
    function tazrim_popup_campaigns_table_ready(): bool
    {
        global $conn;
        if (!$conn) {
            return false;
        }
        $r = @mysqli_query($conn, "SHOW TABLES LIKE 'popup_campaigns'");

        return $r && mysqli_num_rows($r) > 0;
    }
}

if (!function_exists('tazrim_popup_campaign_ack_policy_column_exists')) {
    function tazrim_popup_campaign_ack_policy_column_exists(mysqli $conn): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $r = @mysqli_query($conn, "SHOW COLUMNS FROM `popup_campaigns` LIKE 'ack_policy'");
        $cache = $r && mysqli_num_rows($r) > 0;

        return $cache;
    }
}

if (!function_exists('tazrim_popup_campaign_visible_to_user_sql')) {
    /**
     * תנאי SQL (עם alias c לטבלת popup_campaigns) — הקמפיין רלוונטי למשתמש לפי יעד.
     */
    function tazrim_popup_campaign_visible_to_user_sql(int $userId, int $homeId): string
    {
        $userId = (int) $userId;
        $homeId = (int) $homeId;

        return "(c.`target_scope` = 'all'
            OR (c.`target_scope` = 'homes' AND EXISTS (
                SELECT 1 FROM `popup_campaign_homes` pch
                WHERE pch.`campaign_id` = c.`id` AND pch.`home_id` = {$homeId}
            ))
            OR (c.`target_scope` = 'users' AND EXISTS (
                SELECT 1 FROM `popup_campaign_users` pcu
                WHERE pcu.`campaign_id` = c.`id` AND pcu.`user_id` = {$userId}
            )))";
    }
}

if (!function_exists('tazrim_popup_campaign_ack_policy_visible_sql')) {
    /**
     * תנאי ack_policy: one_per_home דורש home_id; primary_only רק לאב בית בבית שנכלל ביעד.
     */
    function tazrim_popup_campaign_ack_policy_visible_sql(int $userId, int $homeId): string
    {
        $userId = (int) $userId;
        $homeId = (int) $homeId;

        return "(
            c.`ack_policy` != 'one_per_home'
            OR {$homeId} > 0
        )
        AND (
            c.`ack_policy` != 'primary_only'
            OR (
                EXISTS (
                    SELECT 1 FROM `homes` h
                    WHERE h.`id` = {$homeId} AND h.`primary_user_id` = {$userId}
                )
                AND (
                    c.`target_scope` = 'all'
                    OR (c.`target_scope` = 'homes' AND EXISTS (
                        SELECT 1 FROM `popup_campaign_homes` pch
                        WHERE pch.`campaign_id` = c.`id` AND pch.`home_id` = {$homeId}
                    ))
                    OR (c.`target_scope` = 'users' AND EXISTS (
                        SELECT 1 FROM `popup_campaign_users` pcu
                        WHERE pcu.`campaign_id` = c.`id` AND pcu.`user_id` = {$userId}
                    ))
                )
            )
        )";
    }
}

if (!function_exists('tazrim_popup_campaign_pending_ack_sql')) {
    /**
     * עדיין ממתין לאישור: פר משתמש / פר בית ראשון.
     */
    function tazrim_popup_campaign_pending_ack_sql(int $userId, int $homeId): string
    {
        $userId = (int) $userId;
        $homeId = (int) $homeId;

        return "(
            (
                c.`ack_policy` IN ('each_user','primary_only')
                AND NOT EXISTS (
                    SELECT 1 FROM `popup_reads` r
                    WHERE r.`campaign_id` = c.`id` AND r.`user_id` = {$userId}
                )
            )
            OR (
                c.`ack_policy` = 'one_per_home'
                AND {$homeId} > 0
                AND NOT EXISTS (
                    SELECT 1 FROM `popup_home_reads` phr
                    WHERE phr.`campaign_id` = c.`id` AND phr.`home_id` = {$homeId}
                )
            )
        )";
    }
}

if (!function_exists('tazrim_popup_campaigns_pending_for_user')) {
    /**
     * @return array<int, array{id:int,title:string,body_html:string,sort_order:int}>
     */
    function tazrim_popup_campaigns_pending_for_user(mysqli $conn, int $userId, ?int $homeId): array
    {
        if (!tazrim_popup_campaigns_table_ready()) {
            return [];
        }
        $userId = (int) $userId;
        if ($userId <= 0) {
            return [];
        }
        $hid = $homeId !== null ? (int) $homeId : 0;

        $vis = tazrim_popup_campaign_visible_to_user_sql($userId, $hid);

        if (!tazrim_popup_campaign_ack_policy_column_exists($conn)) {
            $sql = "SELECT c.`id`, c.`title`, c.`body_html`, c.`sort_order`
                    FROM `popup_campaigns` c
                    LEFT JOIN `popup_reads` r ON r.`campaign_id` = c.`id` AND r.`user_id` = {$userId}
                    WHERE r.`id` IS NULL
                    AND c.`status` = 'published'
                    AND c.`is_active` = 1
                    AND (c.`starts_at` IS NULL OR c.`starts_at` <= NOW())
                    AND (c.`ends_at` IS NULL OR c.`ends_at` >= NOW())
                    AND {$vis}
                    ORDER BY c.`sort_order` ASC, c.`id` ASC";
        } else {
            $ackPol = tazrim_popup_campaign_ack_policy_visible_sql($userId, $hid);
            $pend = tazrim_popup_campaign_pending_ack_sql($userId, $hid);

            $sql = "SELECT c.`id`, c.`title`, c.`body_html`, c.`sort_order`
                    FROM `popup_campaigns` c
                    WHERE c.`status` = 'published'
                    AND c.`is_active` = 1
                    AND (c.`starts_at` IS NULL OR c.`starts_at` <= NOW())
                    AND (c.`ends_at` IS NULL OR c.`ends_at` >= NOW())
                    AND {$vis}
                    AND {$ackPol}
                    AND {$pend}
                    ORDER BY c.`sort_order` ASC, c.`id` ASC";
        }

        $result = mysqli_query($conn, $sql);
        if (!$result) {
            return [];
        }
        $out = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $out[] = [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'body_html' => (string) $row['body_html'],
                'sort_order' => (int) $row['sort_order'],
            ];
        }

        return $out;
    }
}

if (!function_exists('tazrim_popup_campaign_ack_allowed')) {
    /**
     * האם מותר למשתמש לאשר קריאה לקמפיין (עדיין pending + בתוקף + ביעד).
     */
    function tazrim_popup_campaign_ack_allowed(mysqli $conn, int $userId, ?int $homeId, int $campaignId): bool
    {
        if (!tazrim_popup_campaigns_table_ready() || $userId <= 0 || $campaignId <= 0) {
            return false;
        }
        $hid = $homeId !== null ? (int) $homeId : 0;
        $vis = tazrim_popup_campaign_visible_to_user_sql($userId, $hid);
        $cid = (int) $campaignId;

        if (!tazrim_popup_campaign_ack_policy_column_exists($conn)) {
            $uid = (int) $userId;
            $sql = "SELECT c.`id` FROM `popup_campaigns` c
                    LEFT JOIN `popup_reads` r ON r.`campaign_id` = c.`id` AND r.`user_id` = {$uid}
                    WHERE c.`id` = {$cid}
                    AND r.`id` IS NULL
                    AND c.`status` = 'published'
                    AND c.`is_active` = 1
                    AND (c.`starts_at` IS NULL OR c.`starts_at` <= NOW())
                    AND (c.`ends_at` IS NULL OR c.`ends_at` >= NOW())
                    AND {$vis}
                    LIMIT 1";
        } else {
            $ackPol = tazrim_popup_campaign_ack_policy_visible_sql($userId, $hid);
            $pend = tazrim_popup_campaign_pending_ack_sql($userId, $hid);

            $sql = "SELECT c.`id` FROM `popup_campaigns` c
                    WHERE c.`id` = {$cid}
                    AND c.`status` = 'published'
                    AND c.`is_active` = 1
                    AND (c.`starts_at` IS NULL OR c.`starts_at` <= NOW())
                    AND (c.`ends_at` IS NULL OR c.`ends_at` >= NOW())
                    AND {$vis}
                    AND {$ackPol}
                    AND {$pend}
                    LIMIT 1";
        }

        $result = mysqli_query($conn, $sql);

        return $result && mysqli_num_rows($result) > 0;
    }
}

if (!function_exists('tazrim_popup_campaign_insert_ack')) {
    /**
     * רישום אישור: popup_reads; ל-one_per_home גם popup_home_reads.
     */
    function tazrim_popup_campaign_insert_ack(mysqli $conn, int $userId, ?int $homeId, int $campaignId): bool
    {
        $row = selectOne('popup_campaigns', ['id' => $campaignId]);
        if (!$row) {
            return false;
        }
        $policy = 'each_user';
        if (tazrim_popup_campaign_ack_policy_column_exists($conn) && isset($row['ack_policy'])) {
            $policy = (string) $row['ack_policy'];
        }
        if (!in_array($policy, ['each_user', 'one_per_home', 'primary_only'], true)) {
            $policy = 'each_user';
        }

        return tazrim_popup_campaign_insert_ack_with_policy($conn, $userId, $homeId, $campaignId, $policy);
    }
}

if (!function_exists('tazrim_popup_campaign_insert_ack_with_policy')) {
    function tazrim_popup_campaign_insert_ack_with_policy(
        mysqli $conn,
        int $userId,
        ?int $homeId,
        int $campaignId,
        string $policy
    ): bool {
        if (!in_array($policy, ['each_user', 'one_per_home', 'primary_only'], true)) {
            $policy = 'each_user';
        }
        $uid = (int) $userId;
        $cid = (int) $campaignId;
        $hid = $homeId !== null ? (int) $homeId : 0;

        if ($policy === 'one_per_home' && $hid <= 0) {
            return false;
        }

        if ($policy === 'one_per_home') {
            if ($hid <= 0) {
                return false;
            }
            $rt = @mysqli_query($conn, "SHOW TABLES LIKE 'popup_home_reads'");
            if (!$rt || mysqli_num_rows($rt) === 0) {
                return false;
            }
            $sqlH = "INSERT IGNORE INTO `popup_home_reads` (`campaign_id`, `home_id`, `read_by_user_id`) VALUES ({$cid}, {$hid}, {$uid})";

            return (bool) mysqli_query($conn, $sqlH);
        }

        $sqlRead = "INSERT IGNORE INTO `popup_reads` (`user_id`, `campaign_id`) VALUES ({$uid}, {$cid})";

        return (bool) mysqli_query($conn, $sqlRead);
    }
}
