<?php
/**
 * Actions: Disciplinary Records (admin/sections/disciplinary.php)
 * Split out of the old academic-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_add_behavior_record'])) {
    if (!in_array($active_role, ['School Admin','Platform Manager']) && !can_manage($active_role, feature_access('disciplinary'))) {
        $error_msg = 'You do not have permission to log disciplinary records.';
    } else {
        $s_uuid = safe_str($_POST['student_uuid']   ?? '');
        $type   = safe_str($_POST['incident_type']  ?? 'Demerit');
        $title  = safe_str($_POST['title']          ?? '');
        $points = safe_int($_POST['points']         ?? 0);
        $action_taken = safe_str($_POST['action_taken'] ?? '');
        $notes  = safe_str($_POST['notes']          ?? '');

        $sn = $pdo->prepare("SELECT name FROM students WHERE student_uuid=? AND school_uuid=?");
        $sn->execute([$s_uuid, $school_uuid]);
        $student_name = $sn->fetchColumn();

        if (!$student_name || !$title) {
            $error_msg = 'Select a student and enter a title.';
        } else {
            $sc = $pdo->prepare("SELECT class FROM students WHERE student_uuid=? AND school_uuid=?");
            $sc->execute([$s_uuid, $school_uuid]);
            $student_class = $sc->fetchColumn();
            $uuid = uid('beh');
            $pdo->prepare("
                INSERT INTO student_behavior_records (record_uuid, school_uuid, student_uuid, student_name, class_name, incident_type, title, points, action_taken, description, reported_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$uuid, $school_uuid, $s_uuid, $student_name, $student_class, $type, $title, $points, $action_taken ?: 'Notice Sent to Parent', $notes, $_SESSION['name'] ?? $active_role]);
            AuditLog::write($pdo, $school_uuid, $user_uuid, 'disciplinary.log', $uuid, "$type: $title — $student_name");
            $success_msg = 'Incident logged.';
        }
    }
}
if (isset($_POST['action_delete_behavior_record']) && in_array($active_role, ['School Admin','Platform Manager'])) {
    $uuid = safe_str($_POST['record_uuid'] ?? '');
    $pdo->prepare("DELETE FROM student_behavior_records WHERE record_uuid=? AND school_uuid=?")->execute([$uuid, $school_uuid]);
    $success_msg = 'Record deleted.';
}
