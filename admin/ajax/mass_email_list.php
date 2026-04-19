<?php
declare(strict_types=1);

/**
 * רשימת שליחות מייל המוניות + לוגים (אופציונלי לפי broadcast_id).
 */
require_once dirname(__DIR__) . '/includes/init_ajax.php';
require_once dirname(__DIR__) . '/includes/mass_email_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    tazrim_admin_json_response(['status' => 'error', 'message' => 'שיטה לא מורשית.'], 405);
}

global $conn;

if (!tazrim_admin_mass_email_tables_ok($conn)) {
    tazrim_admin_json_response(['status' => 'ok', 'items' => [], 'total' => 0, 'tables_missing' => true]);
}

$bid = isset($_GET['broadcast_id']) ? (int) $_GET['broadcast_id'] : 0;
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = min(50, max(5, (int) ($_GET['per_page'] ?? 25)));
$offset = ($page - 1) * $per;

if ($bid > 0) {
    $cntRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM admin_email_broadcast_logs WHERE broadcast_id={$bid}");
    $total = 0;
    if ($cntRes && $row = mysqli_fetch_assoc($cntRes)) {
        $total = (int) ($row['c'] ?? 0);
    }
    if ($cntRes) {
        mysqli_free_result($cntRes);
    }
    $sql = "SELECT l.id, l.recipient_email, l.user_id, l.home_id, l.status, l.error_message, l.created_at
            FROM admin_email_broadcast_logs l
            WHERE l.broadcast_id={$bid}
            ORDER BY l.id ASC
            LIMIT {$per} OFFSET {$offset}";
    $items = [];
    $r = mysqli_query($conn, $sql);
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'recipient_email' => (string) ($row['recipient_email'] ?? ''),
                'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
                'home_id' => isset($row['home_id']) ? (int) $row['home_id'] : null,
                'status' => (string) ($row['status'] ?? ''),
                'error_message' => (string) ($row['error_message'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }
        mysqli_free_result($r);
    }
    tazrim_admin_json_response(['status' => 'ok', 'items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $per]);
}

$cntRes = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM admin_email_broadcasts');
$total = 0;
if ($cntRes && $row = mysqli_fetch_assoc($cntRes)) {
    $total = (int) ($row['c'] ?? 0);
}
if ($cntRes) {
    mysqli_free_result($cntRes);
}

$sql = "SELECT b.id, b.admin_user_id, b.target_type, b.subject, b.status, b.recipient_total, b.sent_ok, b.sent_fail,
        b.error_summary, b.created_at, b.completed_at,
        CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS admin_name
        FROM admin_email_broadcasts b
        LEFT JOIN users u ON u.id = b.admin_user_id
        ORDER BY b.id DESC
        LIMIT {$per} OFFSET {$offset}";
$items = [];
$r = mysqli_query($conn, $sql);
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        $items[] = [
            'id' => (int) ($row['id'] ?? 0),
            'admin_user_id' => (int) ($row['admin_user_id'] ?? 0),
            'admin_name' => trim((string) ($row['admin_name'] ?? '')),
            'target_type' => (string) ($row['target_type'] ?? ''),
            'subject' => (string) ($row['subject'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'recipient_total' => (int) ($row['recipient_total'] ?? 0),
            'sent_ok' => (int) ($row['sent_ok'] ?? 0),
            'sent_fail' => (int) ($row['sent_fail'] ?? 0),
            'error_summary' => (string) ($row['error_summary'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'completed_at' => (string) ($row['completed_at'] ?? ''),
        ];
    }
    mysqli_free_result($r);
}

tazrim_admin_json_response(['status' => 'ok', 'items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $per]);
