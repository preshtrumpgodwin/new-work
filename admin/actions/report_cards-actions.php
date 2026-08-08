<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
require_once __DIR__ . '/../lib/GradingEngine.php';

if (isset($_POST['action_save_report_comment'])) {
    $s_uuid  = safe_str($_POST['student_uuid']  ?? '');
    $session = safe_str($_POST['rc_session']    ?? '');
    $term    = safe_str($_POST['rc_term']       ?? '');
    $type    = safe_str($_POST['comment_type']  ?? 'teacher');
    $comment = safe_str(substr($_POST['comment_text'] ?? '', 0, 1000));

    $is_admin_or_full = in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('report_cards'));
    $std0 = $pdo->prepare("SELECT class FROM students WHERE student_uuid=? AND school_uuid=? LIMIT 1");
    $std0->execute([$s_uuid, $school_uuid]);
    $student_class = $std0->fetchColumn();
    $staff_uuid_lookup = $pdo->prepare("SELECT staff_uuid FROM staff WHERE user_uuid=? AND school_uuid=? LIMIT 1");
    $staff_uuid_lookup->execute([$user_uuid, $school_uuid]);
    $my_staff_uuid = $staff_uuid_lookup->fetchColumn() ?: '';
    $is_class_teacher_of_student = $student_class && isClassTeacherOf($pdo, $my_staff_uuid, $school_uuid, $student_class);

    if ($type === 'teacher' && !$is_admin_or_full && !$is_class_teacher_of_student) {
        $error_msg = 'Only this student\'s class teacher (for the current session/term) can write the teacher comment.';
        return;
    }
    if ($type === 'principal' && !in_array($active_role, ['School Admin','Platform Manager']) && !can_approve($active_role, feature_access('report_cards'))) {
        $error_msg = 'Only full-access staff or the school admin can write the principal comment.';
        return;
    }

    try {
        $rc2 = $pdo->prepare("SELECT report_uuid FROM report_cards WHERE school_uuid=? AND student_uuid=? AND session_name=? AND term_name=? LIMIT 1");
        $rc2->execute([$school_uuid, $s_uuid, $session, $term]);
        $existing = $rc2->fetchColumn();

        if ($existing) {
            $col = ($type === 'principal') ? 'principal_comment' : 'teacher_comment';
            $pdo->prepare("UPDATE report_cards SET $col=? WHERE report_uuid=?")->execute([$comment, $existing]);
        } else {
            $r_uuid = uid('rc');
            $std = $pdo->prepare("SELECT class, arm FROM students WHERE student_uuid=? AND school_uuid=? LIMIT 1");
            $std->execute([$s_uuid, $school_uuid]);
            $stdRow = $std->fetch() ?: ['class' => '', 'arm' => 'Gold'];
            $pdo->prepare("INSERT INTO report_cards (report_uuid,school_uuid,student_uuid,session_name,term_name,class_name,arm_name,grades_json,teacher_comment,principal_comment,status)
                VALUES (?,?,?,?,?,?,?,'{}',?,?,'Pending Approval')")
                ->execute([$r_uuid,$school_uuid,$s_uuid,$session,$term,$stdRow['class'],$stdRow['arm'],
                    $type==='teacher'?$comment:'',
                    $type==='principal'?$comment:'']);
        }
        AuditLog::write($pdo,$school_uuid,$user_uuid,'report_card.comment',$s_uuid,"$type comment saved");
        $success_msg = ucfirst($type) . "'s comment saved!";
    } catch (Exception $e) { $error_msg = safe_error('Save failed', $e); }
}

if (isset($_POST['action_approve_report_card']) && (in_array($active_role, ['School Admin','Platform Manager']) || can_approve($active_role, feature_access('report_cards')))) {
    $s_uuid  = safe_str($_POST['student_uuid'] ?? '');
    $session = safe_str($_POST['rc_session']   ?? '');
    $term    = safe_str($_POST['rc_term']      ?? '');
    try {
        $rc2 = $pdo->prepare("SELECT report_uuid FROM report_cards WHERE school_uuid=? AND student_uuid=? AND session_name=? AND term_name=? LIMIT 1");
        $rc2->execute([$school_uuid, $s_uuid, $session, $term]);
        $r_uuid = $rc2->fetchColumn();
        if ($r_uuid) {
            $pdo->prepare("UPDATE report_cards SET status='Approved', approved_by=? WHERE report_uuid=?")
                ->execute([$_SESSION['name'] ?? 'Admin', $r_uuid]);
        } else {
            $std = $pdo->prepare("SELECT class, arm FROM students WHERE student_uuid=? LIMIT 1");
            $std->execute([$s_uuid]);
            $stdRow = $std->fetch() ?: ['class'=>'','arm'=>'Gold'];
            $r_uuid = uid('rc');
            $pdo->prepare("INSERT INTO report_cards (report_uuid,school_uuid,student_uuid,session_name,term_name,class_name,arm_name,grades_json,status,approved_by)
                VALUES (?,?,?,?,?,?,?,'{}','Approved',?)")
                ->execute([$r_uuid,$school_uuid,$s_uuid,$session,$term,$stdRow['class'],$stdRow['arm'],$_SESSION['name']??'Admin']);
        }
        AuditLog::write($pdo,$school_uuid,$user_uuid,'report_card.approve',$s_uuid,'Report card approved');
        NotificationEngine::fireExamResultTrigger($pdo, $school_uuid, $s_uuid, $session, $term);
        $success_msg = 'Report card approved!';
    } catch (Exception $e) { $error_msg = safe_error('Approval failed', $e); }
}

if (isset($_POST['action_save_domain_rating']) && can_manage($active_role, $current_access)) {
    $s_uuid   = safe_str($_POST['student_uuid']  ?? '');
    $session  = safe_str($_POST['rc_session']    ?? '');
    $term     = safe_str($_POST['rc_term']       ?? '');
    $dtype    = in_array($_POST['domain_type']??'', ['Affective','Psychomotor']) ? $_POST['domain_type'] : 'Affective';
    $trait    = safe_str(substr($_POST['trait_name'] ?? '', 0, 100));
    $rating   = max(1, min(5, (int)($_POST['rating'] ?? 3)));

    $is_admin_or_full = in_array($active_role, ['School Admin','Platform Manager']);
    if (!$is_admin_or_full) {
        $std0 = $pdo->prepare("SELECT class FROM students WHERE student_uuid=? AND school_uuid=? LIMIT 1");
        $std0->execute([$s_uuid, $school_uuid]);
        $student_class = $std0->fetchColumn();
        $staff_uuid_lookup = $pdo->prepare("SELECT staff_uuid FROM staff WHERE user_uuid=? AND school_uuid=? LIMIT 1");
        $staff_uuid_lookup->execute([$user_uuid, $school_uuid]);
        $my_staff_uuid = $staff_uuid_lookup->fetchColumn() ?: '';
        if (!$student_class || !isClassTeacherOf($pdo, $my_staff_uuid, $school_uuid, $student_class)) {
            $error_msg = "Only this student's class teacher can rate affective/psychomotor domains.";
            return;
        }
    }

    try {
        $pdo->prepare("INSERT INTO student_domain_ratings (rating_uuid,school_uuid,student_uuid,session_name,term_name,domain_type,trait_name,rating)
            VALUES (?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE rating=VALUES(rating)")
            ->execute([uid('dr'),$school_uuid,$s_uuid,$session,$term,$dtype,$trait,$rating]);
        $success_msg = 'Domain rating saved!';
    } catch(Exception $e) { $error_msg = safe_error('Rating save failed', $e); }
}
