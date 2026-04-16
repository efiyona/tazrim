<?php
/**
 * לוגיקת הודעות פופאפ מנוהלות (יעדים, זמנים, קריאה).
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

        $result = mysqli_query($conn, $sql);
        return $result && mysqli_num_rows($result) > 0;
    }
}
