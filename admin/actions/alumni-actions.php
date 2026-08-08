<?php
/**
 * Actions: Alumni Network (admin/sections/alumni.php)
 * Split out of the old phase3-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_add_alumni'])) {
    $student_uuid = safe_str($_POST['student_uuid'] ?? '');
    $name  = safe_str($_POST['name']            ?? '');
    $year  = safe_int($_POST['graduation_year'] ?? date('Y'));
    $class = safe_str($_POST['final_class']     ?? 'SSS3');
    $gpa   = (float)($_POST['cumulative_gpa']   ?? 3.85);
    $cond  = safe_str($_POST['character_conduct'] ?? 'Exemplary & Outstanding');
    $test  = safe_str($_POST['testimonial_text'] ?? '');
    if ($name && $year) {
        $uuid = uid('alm');
        $pdo->prepare("INSERT INTO alumni (alumni_uuid,school_uuid,student_uuid,name,graduation_year,final_class,cumulative_gpa,character_conduct,testimonial_text,archived_date)
            VALUES (?,?,?,?,?,?,?,?,?,CURDATE())")
            ->execute([$uuid,$school_uuid,$student_uuid ?: uid('ext'),$name,$year,$class,$gpa,$cond,$test]);
        if ($student_uuid) {
            $pdo->prepare("UPDATE students SET status='Graduated' WHERE student_uuid=? AND school_uuid=?")->execute([$student_uuid,$school_uuid]);
        }
        AuditLog::write($pdo,$school_uuid,$user_uuid,'alumni.create',$uuid,"Archived $name (Class of $year)");
        $success_msg = "$name added to Alumni Network!";
    } else { $error_msg = 'Name and graduation year are required.'; }
}
if (isset($_POST['action_edit_alumni'])) {
    $au = safe_str($_POST['alumni_uuid'] ?? '');
    $pdo->prepare("UPDATE alumni SET name=?, graduation_year=?, final_class=?, cumulative_gpa=?, character_conduct=?, testimonial_text=? WHERE alumni_uuid=? AND school_uuid=?")
        ->execute([safe_str($_POST['name']??''), safe_int($_POST['graduation_year']??date('Y')), safe_str($_POST['final_class']??'SSS3'), (float)($_POST['cumulative_gpa']??3.85), safe_str($_POST['character_conduct']??''), safe_str($_POST['testimonial_text']??''), $au, $school_uuid]);
    $success_msg = 'Alumni profile updated!';
}
if (isset($_POST['action_delete_alumni'])) {
    $pdo->prepare("DELETE FROM alumni WHERE alumni_uuid=? AND school_uuid=?")->execute([safe_str($_POST['alumni_uuid']??''),$school_uuid]);
    $success_msg = 'Alumni record removed.';
}
