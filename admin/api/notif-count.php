<?php
/**
 * admin/api/notif-count.php — polled every ~20s from the dashboard header
 * to give a near-real-time unread notification count without a full page
 * reload. Also returns the newest notification (if any) so the header can
 * pop a toast for it, similar to a push notification.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../lib/Helpers.php';
require_once __DIR__ . '/../lib/Notify.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_uuid'], $_SESSION['school_uuid'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$school_uuid = $_SESSION['school_uuid'];
$user_uuid   = $_SESSION['user_uuid'];
$role        = $_SESSION['role'] ?? '';

$count = Notify::unreadCount($pdo, $school_uuid, $user_uuid, $role);

$latest = null;
try {
    $q = $pdo->prepare("SELECT title, message, notification_uuid, created_at FROM notifications
        WHERE school_uuid=? AND is_read=0 AND (recipient_uuid=? OR (recipient_uuid IS NULL AND recipient_role=?))
        ORDER BY created_at DESC LIMIT 1");
    $q->execute([$school_uuid, $user_uuid, $role]);
    $latest = $q->fetch() ?: null;
} catch (Exception $e) {}

echo json_encode(['count' => $count, 'latest' => $latest]);
