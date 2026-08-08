<?php
/**
 * AuditLog — lightweight wrapper that writes every mutating action to
 * the audit_logs table.  All parameters are sanitised before insert.
 *
 * Usage:
 *   AuditLog::write($pdo, $school_uuid, $user_uuid, 'student.create', 'std-xxx', 'Enrolled David Benson');
 */
class AuditLog {

    /**
     * @param PDO    $pdo
     * @param string $school_uuid
     * @param string $user_uuid      Actor (user who triggered the action)
     * @param string $action         Dot-notation verb  e.g. "student.create"
     * @param string $target_uuid    UUID of the record being acted on ('' if N/A)
     * @param string $description    Human-readable summary
     */
    public static function write(
        PDO    $pdo,
        string $school_uuid,
        string $user_uuid,
        string $action,
        string $target_uuid = '',
        string $description = ''
    ): void {
        try {
            // PERF-3: table creation moved to the schema_versions-gated migration
            // block in dashboard.php (runs once) instead of every write() call.
            // Fallback below (catch block) still creates it defensively for any
            // entry point that writes an audit log without loading dashboard.php.
            $stmt = $pdo->prepare(
                "INSERT INTO audit_logs
                    (log_uuid, school_uuid, user_uuid, action, target_uuid, description, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                'log-' . uniqid(),
                $school_uuid ?: null,
                $user_uuid   ?: null,
                substr($action,      0, 100),
                substr($target_uuid, 0, 50) ?: null,
                substr($description, 0, 1000) ?: null,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Exception $e) {
            // Table likely doesn't exist yet (e.g. an entry point that never
            // loaded dashboard.php's migration gate, such as the webhook or
            // gate-scanner endpoints). Create it once and retry, instead of
            // doing this CREATE TABLE check on every normal call.
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_logs` (
                    `id`          INT AUTO_INCREMENT PRIMARY KEY,
                    `log_uuid`    VARCHAR(50)  NOT NULL UNIQUE,
                    `school_uuid` VARCHAR(50)  DEFAULT NULL,
                    `user_uuid`   VARCHAR(50)  DEFAULT NULL,
                    `action`      VARCHAR(100) NOT NULL,
                    `target_uuid` VARCHAR(50)  DEFAULT NULL,
                    `description` TEXT         DEFAULT NULL,
                    `ip_address`  VARCHAR(45)  DEFAULT NULL,
                    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_school (`school_uuid`),
                    INDEX idx_action (`action`),
                    INDEX idx_created (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                $stmt = $pdo->prepare(
                    "INSERT INTO audit_logs
                        (log_uuid, school_uuid, user_uuid, action, target_uuid, description, ip_address)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    'log-' . uniqid(),
                    $school_uuid ?: null,
                    $user_uuid   ?: null,
                    substr($action,      0, 100),
                    substr($target_uuid, 0, 50) ?: null,
                    substr($description, 0, 1000) ?: null,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
            } catch (Exception $e2) {
                // Audit failures must never break the main flow.
                error_log('[AuditLog] ' . $e2->getMessage());
            }
        }
    }
}
