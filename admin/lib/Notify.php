<?php
/**
 * Notify — thin wrapper around the `notifications` table.
 * Use Notify::user() to target one person, Notify::role() to broadcast to
 * everyone with a given role in a school (recipient_uuid stays NULL).
 */
class Notify
{
    public static function user(PDO $pdo, string $school_uuid, string $recipient_uuid, string $title, string $message, string $type = 'info', ?string $link = null): void
    {
        try {
            $pdo->prepare("INSERT INTO notifications (notification_uuid, school_uuid, recipient_uuid, recipient_role, title, message, type, link)
                VALUES (?,?,?,NULL,?,?,?,?)")
                ->execute([uid('ntf'), $school_uuid, $recipient_uuid, $title, $message, $type, $link]);
        } catch (Exception $e) { /* never block the calling action */ }
    }

    public static function role(PDO $pdo, string $school_uuid, string $role, string $title, string $message, string $type = 'info', ?string $link = null): void
    {
        try {
            $pdo->prepare("INSERT INTO notifications (notification_uuid, school_uuid, recipient_uuid, recipient_role, title, message, type, link)
                VALUES (?,?,NULL,?,?,?,?,?)")
                ->execute([uid('ntf'), $school_uuid, $role, $title, $message, $type, $link]);
        } catch (Exception $e) {}
    }

    /** Unread count for the currently logged-in user (their own uuid + their role's broadcasts) */
    public static function unreadCount(PDO $pdo, string $school_uuid, string $user_uuid, string $role): int
    {
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM notifications
                WHERE school_uuid = ? AND is_read = 0
                AND (recipient_uuid = ? OR (recipient_uuid IS NULL AND recipient_role = ?))");
            $st->execute([$school_uuid, $user_uuid, $role]);
            return (int)$st->fetchColumn();
        } catch (Exception $e) { return 0; }
    }

    public static function listFor(PDO $pdo, string $school_uuid, string $user_uuid, string $role, int $limit = 20): array
    {
        try {
            $st = $pdo->prepare("SELECT * FROM notifications
                WHERE school_uuid = ?
                AND (recipient_uuid = ? OR (recipient_uuid IS NULL AND recipient_role = ?))
                ORDER BY created_at DESC LIMIT " . (int)$limit);
            $st->execute([$school_uuid, $user_uuid, $role]);
            return $st->fetchAll();
        } catch (Exception $e) { return []; }
    }
}
