<?php
/**
 * Actions: Student Career Advisory (admin/sections/career_advisory.php)
 * Split out of the old phase5-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_save_career_advisory'])) {
    $s_uuid = safe_str($_POST['student_uuid'] ?? '');
    $paths  = safe_str($_POST['recommended_paths'] ?? '');
    $strengths = safe_str($_POST['strengths'] ?? '');
    $notes  = safe_str($_POST['counselor_notes'] ?? '');

    $existing = $pdo->prepare("SELECT note_uuid FROM career_advisory_notes WHERE school_uuid=? AND student_uuid=?");
    $existing->execute([$school_uuid, $s_uuid]);
    $nuuid = $existing->fetchColumn();
    if ($nuuid) {
        $pdo->prepare("UPDATE career_advisory_notes SET recommended_paths=?, strengths=?, counselor_notes=?, created_by=? WHERE note_uuid=?")
            ->execute([$paths, $strengths, $notes, $_SESSION['name'] ?? 'Admin', $nuuid]);
    } else {
        $nuuid = uid('cad');
        $pdo->prepare("INSERT INTO career_advisory_notes (note_uuid,school_uuid,student_uuid,recommended_paths,strengths,counselor_notes,created_by) VALUES (?,?,?,?,?,?,?)")
            ->execute([$nuuid, $school_uuid, $s_uuid, $paths, $strengths, $notes, $_SESSION['name'] ?? 'Admin']);
    }
    AuditLog::write($pdo, $school_uuid, $user_uuid, 'career_advisory.save', $s_uuid, '');
    $success_msg = 'Career advisory note saved!';
}
