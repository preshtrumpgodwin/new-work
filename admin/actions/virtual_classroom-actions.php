<?php
/**
 * Actions: Virtual Classroom (admin/sections/virtual_classroom.php)
 * Split out of the old phase5-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_add_virtual_class'])) {
    $title   = safe_str($_POST['title'] ?? '');
    $link    = trim((string)($_POST['meeting_link'] ?? ''));
    $class   = safe_str($_POST['class_name'] ?? '');
    $subject = safe_str($_POST['subject_name'] ?? '');
    $sched   = safe_str($_POST['scheduled_at'] ?? '') ?: null;
    $plat    = safe_str($_POST['platform'] ?? 'Other');

    if ($title === '' || !filter_var($link, FILTER_VALIDATE_URL)) {
        $error_msg = 'A title and a valid meeting link are required.';
    } else {
        $vuuid = uid('vc');
        $pdo->prepare("INSERT INTO virtual_classes (class_session_uuid,school_uuid,title,class_name,subject_name,meeting_link,platform,scheduled_at,created_by)
            VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$vuuid, $school_uuid, $title, $class ?: null, $subject ?: null, $link, $plat, $sched ? str_replace('T',' ',$sched) : null, $_SESSION['name'] ?? 'Admin']);
        AuditLog::write($pdo, $school_uuid, $user_uuid, 'virtual_class.create', $vuuid, $title);
        $success_msg = 'Class scheduled!';
    }
}
