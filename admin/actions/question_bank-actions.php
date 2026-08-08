<?php
/**
 * Actions: Question Bank + Printed Exam Papers (admin/sections/question_bank.php)
 * Split out of the old phase5-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_add_question'])) {
    $subject = safe_str($_POST['subject_name'] ?? '');
    $class   = safe_str($_POST['class_name'] ?? '');
    $type    = safe_str($_POST['question_type'] ?? 'objective');
    $text    = trim((string)($_POST['question_text'] ?? ''));
    $a = safe_str($_POST['option_a'] ?? ''); $b = safe_str($_POST['option_b'] ?? '');
    $c = safe_str($_POST['option_c'] ?? ''); $d = safe_str($_POST['option_d'] ?? '');
    $correct = safe_str($_POST['correct_option'] ?? '');
    $year = safe_str($_POST['year'] ?? '');
    $topic = safe_str($_POST['topic'] ?? '');
    $for_printed = isset($_POST['for_printed_exam']) ? 1 : 0;

    if ($subject === '' || $text === '') {
        $error_msg = 'Subject and question text are required.';
    } else {
        $quuid = uid('qb');
        $pdo->prepare("INSERT INTO question_bank (question_uuid,school_uuid,subject_name,class_name,question_type,question_text,option_a,option_b,option_c,option_d,correct_option,year,topic,for_printed_exam,created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$quuid, $school_uuid, $subject, $class ?: null, $type, $text, $a ?: null, $b ?: null, $c ?: null, $d ?: null, $correct ?: null, $year ?: null, $topic ?: null, $for_printed, $_SESSION['name'] ?? 'Admin']);
        AuditLog::write($pdo, $school_uuid, $user_uuid, 'question_bank.add', $quuid, "$subject ($type)");
        $success_msg = 'Question added to bank!';
    }
}
if (isset($_POST['action_delete_question'])) {
    $pdo->prepare("DELETE FROM question_bank WHERE question_uuid=? AND school_uuid=?")->execute([safe_str($_POST['question_uuid'] ?? ''), $school_uuid]);
    $success_msg = 'Question removed.';
}
if (isset($_POST['action_build_printed_paper'])) {
    $title = safe_str($_POST['paper_title'] ?? '');
    $subject = safe_str($_POST['paper_subject'] ?? '');
    $class = safe_str($_POST['paper_class'] ?? '');
    $instructions = safe_str($_POST['instructions'] ?? '');
    $qids = $_POST['question_uuids'] ?? [];
    $qids = array_map('safe_str', is_array($qids) ? $qids : []);
    if ($title === '' || empty($qids)) {
        $error_msg = 'A title and at least one question are required.';
    } else {
        $puuid = uid('pep');
        $pdo->prepare("INSERT INTO printed_exam_papers (paper_uuid,school_uuid,title,subject_name,class_name,instructions,question_uuids,created_by)
            VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$puuid, $school_uuid, $title, $subject ?: null, $class ?: null, $instructions, implode(',', $qids), $_SESSION['name'] ?? 'Admin']);
        AuditLog::write($pdo, $school_uuid, $user_uuid, 'printed_paper.build', $puuid, $title);
        $success_msg = 'Printed exam paper created!';
    }
}
