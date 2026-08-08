<?php
/**
 * Actions: Staff Attendance (admin/sections/staff_attendance.php)
 * Split out of the old phase5-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_save_staff_attendance_batch']) && in_array($active_role, ['School Admin','Platform Manager'])) {
    $date = safe_str($_POST['date'] ?? date('Y-m-d'));
    $statuses = $_POST['status'] ?? [];
    $clock_ins = $_POST['clock_in'] ?? [];
    $clock_outs = $_POST['clock_out'] ?? [];

    foreach ($statuses as $staff_uuid => $status) {
        $staff_uuid = safe_str($staff_uuid);
        $status = safe_str($status);
        $cin = safe_str($clock_ins[$staff_uuid] ?? '') ?: null;
        $cout = safe_str($clock_outs[$staff_uuid] ?? '') ?: null;
        $pdo->prepare("INSERT INTO staff_attendance (record_uuid, school_uuid, staff_uuid, date, clock_in, clock_out, status)
            VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE clock_in=VALUES(clock_in), clock_out=VALUES(clock_out), status=VALUES(status)")
            ->execute([uid('satt'), $school_uuid, $staff_uuid, $date, $cin, $cout, $status]);
    }
    AuditLog::write($pdo, $school_uuid, $user_uuid, 'staff_attendance.batch_save', '', "Date: $date");
    $success_msg = "Staff attendance saved for $date!";
}
