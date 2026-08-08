<?php
/**
 * Actions: CBT Quizzes (admin/sections/cbt.php)
 * Split out of the old misc-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_add_cbt_test'])) {
    if (!(in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('cbt')))) { $error_msg = 'You do not have permission to create CBT tests.'; return; }
    $title   = safe_str($_POST['cbt_title']    ?? '');
    $subj    = safe_str($_POST['cbt_subject']  ?? '');
    $class   = safe_str($_POST['cbt_class']    ?? '');
    $dur     = safe_int($_POST['cbt_duration'] ?? 30);
    if ($title && $subj && $class) {
        $uuid = uid('cbt');
        $pdo->prepare("INSERT INTO cbt_tests (test_uuid,school_uuid,title,subject,class_name,duration_minutes,status,created_by) VALUES (?,?,?,?,?,?,'Pending Approval',?)")
            ->execute([$uuid,$school_uuid,$title,$subj,$class,$dur,$_SESSION['name']??'Admin']);
        $success_msg = "Test '$title' created — add questions below.";
    } else { $error_msg = 'Title, subject and class are required.'; }
}
if (isset($_POST['action_approve_cbt_test']) && $active_role === 'School Admin') {
    $pdo->prepare("UPDATE cbt_tests SET status='Approved',approved_by=? WHERE test_uuid=? AND school_uuid=?")
        ->execute([$_SESSION['name']??'Admin',safe_str($_POST['test_uuid']??''),$school_uuid]);
    $success_msg = 'Test approved!';
}
if (isset($_POST['action_add_cbt_question'])) {
    if (!(in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('cbt')))) { $error_msg = 'You do not have permission to add questions.'; return; }
    $test = safe_str($_POST['test_uuid']       ?? '');
    $qtext= safe_str($_POST['question_text']   ?? '');
    $a    = safe_str($_POST['option_a']        ?? '');
    $b    = safe_str($_POST['option_b']        ?? '');
    $c    = safe_str($_POST['option_c']        ?? '');
    $d    = safe_str($_POST['option_d']        ?? '');
    $corr = safe_str($_POST['correct_option']  ?? 'A');
    if ($test && $qtext && $a && $b && $c && $d) {
        $uuid = uid('q');
        $pdo->prepare("INSERT INTO cbt_questions (question_uuid,test_uuid,school_uuid,question_text,option_a,option_b,option_c,option_d,correct_option) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$uuid,$test,$school_uuid,$qtext,$a,$b,$c,$d,$corr]);
        $success_msg = 'Question added!';
    } else { $error_msg = 'Fill in the question and all four options.'; }
}
if (isset($_POST['action_delete_cbt_question']) && (in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('cbt')))) {
    $pdo->prepare("DELETE FROM cbt_questions WHERE question_uuid=? AND school_uuid=?")->execute([safe_str($_POST['question_uuid']??''),$school_uuid]);
    $success_msg = 'Question removed.';
}
