<?php
/**
 * Actions: In-App Notifications (admin/sections/notifications.php)
 * Split out of the old phase3-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_mark_notification_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE notification_uuid=? AND school_uuid=?
        AND (recipient_uuid=? OR (recipient_uuid IS NULL AND recipient_role=?))")
        ->execute([safe_str($_POST['notification_uuid']??''),$school_uuid,$user_uuid,$active_role]);
    if (!($_POST['ajax'] ?? false)) $success_msg = 'Marked as read.';
}
if (isset($_POST['action_mark_all_notifications_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE school_uuid=?
        AND (recipient_uuid=? OR (recipient_uuid IS NULL AND recipient_role=?))")
        ->execute([$school_uuid,$user_uuid,$active_role]);
    $success_msg = 'All notifications marked as read.';
}
